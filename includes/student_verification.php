<?php

function validate_student_id_upload(array $upload): array
{
    $maxBytes = 15 * 1024 * 1024;
    $allowedMimeExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($upload['error'], $upload['tmp_name'], $upload['name'], $upload['size'])) {
        return ['path' => null, 'error' => 'Choose a clear photo of your school ID.'];
    }

    if (
        !is_int($upload['error'])
        || !is_string($upload['name'])
        || !is_string($upload['tmp_name'])
        || !is_int($upload['size'])
    ) {
        return ['path' => null, 'error' => 'The school ID photo upload is invalid.'];
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $message = match ($upload['error']) {
            UPLOAD_ERR_INI_SIZE => 'The school ID photo exceeds the server upload limit. Choose a smaller photo and try again.',
            UPLOAD_ERR_FORM_SIZE => 'The school ID photo must be no larger than 15 MB.',
            UPLOAD_ERR_PARTIAL => 'The school ID photo upload was interrupted. Please choose the photo again and retry.',
            default => 'The school ID photo upload failed. Please try again.',
        };
        return ['path' => null, 'error' => $message];
    }

    if ($upload['size'] < 1 || $upload['size'] > $maxBytes || !is_uploaded_file($upload['tmp_name'])) {
        return ['path' => null, 'error' => 'The school ID photo must be a valid image no larger than 15 MB.'];
    }

    $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($upload['tmp_name']);
    $imageInfo = @getimagesize($upload['tmp_name']);

    if (
        !is_string($mimeType)
        || !isset($allowedMimeExtensions[$mimeType])
        || !in_array($extension, (array) $allowedMimeExtensions[$mimeType], true)
        || $imageInfo === false
        || !isset($imageInfo['mime'])
        || $imageInfo['mime'] !== $mimeType
    ) {
        return ['path' => null, 'error' => 'Use a valid JPG, PNG, or WebP photo of your school ID.'];
    }

    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student-verification';
    if (!is_dir($directory) || !is_writable($directory)) {
        error_log('Student ID upload failed: private verification directory is missing or not writable.');
        return ['path' => null, 'error' => 'ID uploads are temporarily unavailable. Please try again later.'];
    }

    $storedExtension = $mimeType === 'image/jpeg' ? 'jpg' : $allowedMimeExtensions[$mimeType];
    $filename = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        error_log('Student ID upload failed: the uploaded file could not be moved.');
        return ['path' => null, 'error' => 'The school ID photo could not be saved. Please try again.'];
    }

    return ['path' => 'uploads/student-verification/' . $filename, 'error' => null];
}

function delete_student_id_photo(?string $path): void
{
    if ($path === null || preg_match('/\Auploads\/student-verification\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $path) !== 1) {
        return;
    }

    $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Student ID cleanup failed.');
    }
}
