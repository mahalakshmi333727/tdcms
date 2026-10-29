<?php
declare(strict_types=1);

const TASK_IMPORT_MAX_ROWS = 500;

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
    return array_values(array_filter($data, static fn($item) => is_array($item)));
}

function first_nonempty(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
}

function normalize_task_key(string $key): string
{
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
    return trim($key, '_');
}

function normalize_task_row(array $row, bool $preserveTitleLength = false): array
{
    $normalized = [];
    foreach ($row as $key => $value) {
        $normalized[normalize_task_key((string) $key)] = $value;
    }

    $tagsRaw = $normalized['tags'] ?? '';
    $tags = is_array($tagsRaw) ? array_map('strval', $tagsRaw) : preg_split('/[,|]/', (string) $tagsRaw);
    $tags = implode(', ', array_values(array_filter(array_map('trim', $tags ?: []))));

    return [
        'title' => $preserveTitleLength
            ? first_nonempty($normalized, ['title', 'page_section', 'page', 'section', 'task'])
            : mb_substr(first_nonempty($normalized, ['title', 'page_section', 'page', 'section', 'task']), 0, 150),
        'category' => mb_substr(first_nonempty($normalized, ['category', 'type', 'task_category']), 0, 100),
        'text_content' => mb_substr(first_nonempty($normalized, ['content', 'review_content', 'review_text', 'review', 'copy_content', 'copy', 'text_content', 'text', 'task_text', 'description', 'message', 'copy_text']), 0, 20000),
        'reference_link' => ($link = first_nonempty($normalized, ['reference_link', 'reference', 'link', 'url', 'website'])) !== '' ? mb_substr($link, 0, 500) : null,
        'tags' => mb_substr($tags, 0, 500),
    ];
}

function normalize_and_validate_tasks(array $rawRows, bool $requireTitle = false): array
{
    if (count($rawRows) > TASK_IMPORT_MAX_ROWS) {
        throw new RuntimeException('The file exceeds the 500-row limit.');
    }
    $valid = [];
    $errors = [];
    foreach ($rawRows as $index => $row) {
        $task = normalize_task_row($row, $requireTitle);
        if ($task['text_content'] === '') {
            $errors[] = 'Row ' . ($index + 1) . ': Missing required "content" (task text).';
        } elseif (
            $requireTitle
            && ($task['title'] === '' || mb_strlen($task['title']) > 150)
        ) {
            $errors[] = 'Row ' . ($index + 1) . ': Business-linked tasks require a title of 150 characters or fewer.';
        } elseif ($task['reference_link'] !== null && !is_valid_url($task['reference_link'])) {
            $errors[] = 'Row ' . ($index + 1) . ': Reference link is not a valid http(s) URL.';
        } else {
            $valid[] = $task;
        }
    }
    return ['valid' => $valid, 'errors' => $errors];
}

function read_csv_rows(string $filePath): array
{
    $handle = fopen($filePath, 'r');
    if ($handle === false) throw new RuntimeException('Could not read the CSV file.');
    $headers = fgetcsv($handle /* PHP 8.4 COMPAT START */, null, ',', '"', '\\' /* PHP 8.4 COMPAT END */);
    if ($headers === false) { fclose($handle); return []; }
    $headers = array_map(static fn($header) => normalize_task_key((string) $header), $headers);
    $rows = [];
    while (($line = fgetcsv($handle /* PHP 8.4 COMPAT START */, null, ',', '"', '\\' /* PHP 8.4 COMPAT END */)) !== false) {
        $row = [];
        foreach ($headers as $index => $header) if ($header !== '') $row[$header] = $line[$index] ?? '';
        if (count(array_filter($row, static fn($value) => trim((string) $value) !== '')) > 0) $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function parse_uploaded_task_file(array $file, bool $requireTitle = false): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The file upload failed.');
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid file upload.');
    }
    if ((int) $file['size'] > 3 * 1024 * 1024) throw new RuntimeException('The file is too large (max 3 MB).');

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if ($extension === 'json') {
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) throw new RuntimeException('Could not read the JSON file.');
        $rows = parse_tasks_from_json($content);
    } elseif ($extension === 'xlsx') {
        $rows = read_xlsx_first_sheet($file['tmp_name']);
    } elseif ($extension === 'xls') {
        $rows = read_xls_first_sheet($file['tmp_name']);
    } elseif ($extension === 'csv') {
        $rows = read_csv_rows($file['tmp_name']);
    } else {
        throw new RuntimeException('Unsupported file type. Use .xlsx, .xls, .csv, or .json.');
    }
    if (empty($rows)) throw new RuntimeException('No task rows were found in the file.');
    return normalize_and_validate_tasks($rows, $requireTitle);
}
