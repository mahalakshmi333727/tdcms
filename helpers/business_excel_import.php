<?php
declare(strict_types=1);

function parse_business_excel_upload(array $file): array
{
    $uploadError = filter_var($file['error'] ?? null, FILTER_VALIDATE_INT);
    if ($uploadError === false) {
        throw new DomainException('The Excel upload is invalid.');
    }
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        throw new DomainException('Select an .xlsx file to import.');
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new DomainException(upload_error_message($uploadError));
    }

    $temporaryPath = $file['tmp_name'] ?? null;
    $filename = $file['name'] ?? null;
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath) || !is_string($filename)) {
        throw new DomainException('The uploaded Excel file could not be verified.');
    }
    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') {
        throw new DomainException('Only .xlsx files can be imported.');
    }
    $size = filesize($temporaryPath);
    if ($size === false || $size < 1) {
        throw new DomainException('The Excel file is empty or could not be read.');
    }
    if (!class_exists('ZipArchive') || !extension_loaded('simplexml')) {
        throw new RuntimeException('The PHP zip and simplexml extensions are required for .xlsx imports.');
    }

    $zip = new ZipArchive();
    if ($zip->open($temporaryPath, ZipArchive::CHECKCONS) !== true) {
        throw new DomainException('The uploaded file is not a valid .xlsx workbook.');
    }
    try {
        $contentTypes = $zip->getFromName('[Content_Types].xml');
        $workbook = $zip->getFromName('xl/workbook.xml');
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    } finally {
        $zip->close();
    }
    if (
        !is_string($contentTypes)
        || !str_contains($contentTypes, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml')
        || !is_string($workbook)
        || !is_string($worksheet)
    ) {
        throw new DomainException('The uploaded file is not a valid .xlsx workbook.');
    }

    try {
        $rows = read_xlsx_first_sheet($temporaryPath);
    } catch (RuntimeException $error) {
        throw new DomainException($error->getMessage(), 0, $error);
    }

    return parse_business_excel_rows($rows, basename($filename));
}

function parse_business_excel_rows(array $rows, string $filename): array
{
    if ($rows === []) {
        throw new DomainException('The workbook contains no content rows.');
    }

    $records = [];
    $errors = [];
    $seen = [];
    foreach ($rows as $index => $row) {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[normalize_task_key((string) $key)] = trim((string) $value);
        }

        $title = '';
        foreach (['title', 'page_section', 'page', 'section', 'task'] as $key) {
            if (($normalized[$key] ?? '') !== '') {
                $title = $normalized[$key];
                break;
            }
        }
        $text = '';
        foreach (['content', 'review_content', 'review_text', 'review', 'copy_content', 'copy', 'text_content', 'text', 'task_text', 'description', 'message', 'copy_text'] as $key) {
            if (($normalized[$key] ?? '') !== '') {
                $text = $normalized[$key];
                break;
            }
        }
        $referenceLink = '';
        foreach (['reference_link', 'reference', 'link', 'url', 'website'] as $key) {
            if (($normalized[$key] ?? '') !== '') {
                $referenceLink = $normalized[$key];
                break;
            }
        }

        $recordNumber = $index + 2;
        $titleLength = preg_match_all('/./us', $title);
        if ($title === '') {
            $errors[] = "Row $recordNumber: title is required.";
            continue;
        }
        if ($titleLength === false || $titleLength > 150) {
            $errors[] = "Row $recordNumber: title must be 150 characters or fewer.";
            continue;
        }
        if ($text === '') {
            $errors[] = "Row $recordNumber: content is required.";
            continue;
        }
        if (strlen($text) > 65535) {
            $errors[] = "Row $recordNumber: content must be 65,535 bytes or fewer.";
            continue;
        }
        if ($referenceLink !== '' && !valid_business_http_url($referenceLink, 500)) {
            $errors[] = "Row $recordNumber: reference link must be a valid HTTP or HTTPS URL no longer than 500 characters.";
            continue;
        }

        $referenceLink = $referenceLink === '' ? null : $referenceLink;
        $normalizedText = str_replace(["\r\n", "\r"], "\n", $text);
        $hash = hash('sha256', trim($title) . "\0" . $normalizedText . "\0" . ($referenceLink ?? ''));
        if (isset($seen[$hash])) {
            $errors[] = "Row $recordNumber: duplicate content in this workbook.";
            continue;
        }
        $seen[$hash] = true;
        $records[] = [
            'source_record' => $recordNumber,
            'title' => trim($title),
            'text_content' => $normalizedText,
            'reference_link' => $referenceLink,
            'content_hash' => $hash,
        ];
    }

    return [
        'filename' => basename($filename),
        'records' => $records,
        'errors' => $errors,
    ];
}
