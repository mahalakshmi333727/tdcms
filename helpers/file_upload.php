<?php
/**
 * File upload helpers for permanent server-side storage.
 */

declare(strict_types=1);

function ensure_uploads_directory(string $subfolder): string
{
    $root = rtrim(UPLOADS_DIR, '/\\');
    $directory = $root . '/' . trim($subfolder, '/\\');

    if (!is_dir($root)) {
        if (!mkdir($root, 0755, true) && !is_dir($root)) {
            throw new RuntimeException('Upload storage directory could not be created.');
        }
    }

    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Upload storage directory could not be created.');
        }
    }

    return $directory;
}

function sanitize_upload_filename(string $filename): string
{
    $filename = pathinfo($filename, PATHINFO_FILENAME);
    $filename = preg_replace('/[^\w\s.-]/u', '', $filename);
    $filename = preg_replace('/[\s]+/u', '_', trim($filename));
    if ($filename === '') {
        return 'file';
    }
    return mb_substr($filename, 0, 120);
}

function upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than this server allows. Please check your upload limits.',
        UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION =>
            'The server could not process the upload. Please try again or contact your administrator.',
        default => 'The file upload failed (error code ' . $errorCode . '). Please try again.',
    };
}

function save_uploaded_file(array $file, string $subfolder, array $allowedExtensions): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE));
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid file upload.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Unsupported file type.');
    }

    $targetDir = ensure_uploads_directory($subfolder);
    $baseName = sanitize_upload_filename($originalName);
    $storedFilename = $baseName . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = $targetDir . '/' . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to store uploaded file on the server.');
    }

    return [
        'original_name' => $originalName,
        'stored_path'   => trim($subfolder, '/\\') . '/' . $storedFilename,
        'file_type'     => $extension,
    ];
}
