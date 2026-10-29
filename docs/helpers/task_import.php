<?php
/**
 * Task Import Helpers
 * Shared by the bulk-assign JSON and .xlsx import paths: turns raw file
 * content into a normalized, validated list of task rows ready to hand to
 * Assignment::bulkCreateFromTasks().
 */

declare(strict_types=1);

const TASK_IMPORT_MAX_ROWS = 500;

/**
 * Parse a JSON task list. Accepts either a bare array of task objects, or
 * an object with a top-level "tasks" array (both are common export shapes).
 *
 * @throws RuntimeException on invalid JSON or an unexpected shape.
 */
function parse_tasks_from_json(string $jsonContent): array
{
    $data = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('The JSON file is not valid: ' . json_last_error_msg());
    }

    if (is_array($data) && isset($data['tasks']) && is_array($data['tasks'])) {
        $data = $data['tasks'];
    }

    if (!is_array($data)) {
        throw new RuntimeException('The JSON file must contain a list of task objects.');
    }

    $rows = [];
    foreach ($data as $item) {
        if (is_array($item)) {
            $rows[] = $item;
        }
    }

    return $rows;
}

/**
 * Return the first non-empty (after trim) value found across a list of
 * candidate keys in an associative row. Unlike chaining PHP's `??`
 * operator, this treats a present-but-blank key as "try the next one"
 * instead of stopping there — important because .csv/.xlsx rows have a
 * key for every column even when that cell is blank for a given row.
 */
function first_nonempty(array $normalized, array $keys): string
{
    foreach ($keys as $key) {
        $candidate = trim((string) ($normalized[$key] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }
    return '';
}

/**
 * Normalize one raw row (from JSON or an .xlsx row) into the canonical
 * task shape: title, category, text_content, reference_link, tags.
 * Tolerant of a few common header/key spellings.
 */
function normalize_task_row(array $row): array
{
    $normalized = [];
    foreach ($row as $key => $value) {
        $normalized[strtolower(trim((string) $key))] = $value;
    }

    $title    = trim((string) ($normalized['title'] ?? ''));
    $category = trim((string) ($normalized['category'] ?? ''));
    $text     = first_nonempty($normalized, ['content', 'text_content', 'text']);
    $link     = first_nonempty($normalized, ['reference_link', 'link', 'url']);

    $tagsRaw = $normalized['tags'] ?? '';
    if (is_array($tagsRaw)) {
        $tagList = array_map(static fn($t) => trim((string) $t), $tagsRaw);
    } else {
        // .xlsx cells arrive as plain strings — accept comma or pipe separated tags
        $tagString = str_replace('|', ',', (string) $tagsRaw);
        $tagList = array_map('trim', explode(',', $tagString));
    }
    $tagList = array_values(array_filter($tagList, static fn($t) => $t !== ''));
    $tags = implode(', ', $tagList);

    return [
        'title'          => mb_substr($title, 0, 150),
        'category'       => mb_substr($category, 0, 100),
        'text_content'   => mb_substr($text, 0, 20000),
        'reference_link' => $link !== '' ? mb_substr($link, 0, 500) : null,
        'tags'           => mb_substr($tags, 0, 500),
    ];
}

/**
 * Validate a normalized task row. Returns an error string, or null if valid.
 */
function validate_task_row(array $task): ?string
{
    if ($task['text_content'] === '') {
        return 'Missing required "content" (task text).';
    }
    if (mb_strlen($task['text_content']) > 20000) {
        return 'Content exceeds the 20,000 character limit.';
    }
    if ($task['reference_link'] !== null && !is_valid_url($task['reference_link'])) {
        return 'Reference link is not a valid http(s) URL: ' . $task['reference_link'];
    }
    return null;
}

/**
 * Normalize + validate a raw list of rows (already parsed from JSON or
 * .xlsx). Returns ['valid' => [...normalized tasks...], 'errors' => [...strings...]].
 * Enforces TASK_IMPORT_MAX_ROWS as a sane upper bound on a single import.
 */
function normalize_and_validate_tasks(array $rawRows): array
{
    if (count($rawRows) > TASK_IMPORT_MAX_ROWS) {
        throw new RuntimeException('The file has ' . count($rawRows) . ' rows, which exceeds the ' . TASK_IMPORT_MAX_ROWS . '-row limit per import. Please split it into smaller files.');
    }

    $valid = [];
    $errors = [];

    foreach ($rawRows as $i => $row) {
        $rowNumber = $i + 1;
        $task = normalize_task_row($row);
        $error = validate_task_row($task);
        if ($error !== null) {
            $errors[] = "Row $rowNumber: $error";
            continue;
        }
        $valid[] = $task;
    }

    return ['valid' => $valid, 'errors' => $errors];
}

/**
 * Read a CSV file (first row = headers) into an array of associative rows,
 * mirroring the shape read_xlsx_first_sheet() produces.
 */
function read_csv_rows(string $filePath): array
{
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new RuntimeException('Could not read the uploaded CSV file.');
    }

    $headers = fgetcsv($handle /* PHP 8.4 COMPAT START */, null, ',', '"', '\\' /* PHP 8.4 COMPAT END */);
    if ($headers === false) {
        fclose($handle);
        return [];
    }
    $headers = array_map(static fn($h) => strtolower(trim((string) $h)), $headers);

    $rows = [];
    while (($line = fgetcsv($handle /* PHP 8.4 COMPAT START */, null, ',', '"', '\\' /* PHP 8.4 COMPAT END */)) !== false) {
        $assoc = [];
        foreach ($headers as $i => $header) {
            if ($header === '') {
                continue;
            }
            $assoc[$header] = $line[$i] ?? '';
        }
        $hasContent = false;
        foreach ($assoc as $v) {
            if (trim((string) $v) !== '') {
                $hasContent = true;
                break;
            }
        }
        if ($hasContent) {
            $rows[] = $assoc;
        }
    }
    fclose($handle);

    return $rows;
}

/**
 * Read + parse an uploaded import file (JSON, .xlsx, or .csv) into a
 * normalized, validated task list. Handles all file-level validation
 * itself (extension, upload error codes, size).
 *
 * @return array{valid: array, errors: array}
 * @throws RuntimeException on any file-level problem.
 */
function parse_uploaded_task_file(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file was uploaded.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'The file is larger than this server allows (check your php.ini upload_max_filesize / post_max_size).',
            UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION =>
                'The server could not process this upload. Please try again or contact your administrator.',
            default => 'The file upload failed (error code ' . $file['error'] . '). Please try again.',
        };
        throw new RuntimeException($message);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid file upload.');
    }

    $maxBytes = 3 * 1024 * 1024; // 3 MB is generous for a few hundred text rows
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('The file is too large (max 3 MB).');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === 'json') {
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            throw new RuntimeException('Could not read the uploaded JSON file.');
        }
        $rawRows = parse_tasks_from_json($content);
    } elseif ($extension === 'xlsx') {
        $rawRows = read_xlsx_first_sheet($file['tmp_name']);
    } elseif ($extension === 'csv') {
        $rawRows = read_csv_rows($file['tmp_name']);
    } else {
        throw new RuntimeException('Unsupported file type. Please upload a .json, .xlsx, or .csv file.');
    }

    if (empty($rawRows)) {
        throw new RuntimeException('No task rows were found in the file.');
    }

    return normalize_and_validate_tasks($rawRows);
}
