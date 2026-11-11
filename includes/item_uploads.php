<?php

function validate_item_image_upload(array $upload): array
{
    $maxBytes = 15 * 1024 * 1024;
    $allowedMimeExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
    ];

    if ($upload === []) {
        return ['path' => null, 'error' => null];
    }

    if (!isset($upload['error'], $upload['tmp_name'], $upload['name'], $upload['size'])) {
        return ['path' => null, 'error' => 'The image upload is invalid.'];
    }

    if (!is_int($upload['error']) || !is_string($upload['name']) || !is_string($upload['tmp_name'])) {
        return ['path' => null, 'error' => 'The image upload is invalid.'];
    }

    if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $message = match ($upload['error']) {
            UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload limit. Choose a smaller image and try again.',
            UPLOAD_ERR_FORM_SIZE => 'The image must be no larger than 15 MB.',
            UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Please choose the image again and retry.',
            default => 'The image upload failed. Please choose another image and try again.',
        };

        return ['path' => null, 'error' => $message];
    }

    if (!is_int($upload['size']) || $upload['size'] < 1 || $upload['size'] > $maxBytes) {
        return ['path' => null, 'error' => 'The image must be no larger than 15 MB.'];
    }

    if (!is_uploaded_file($upload['tmp_name'])) {
        return ['path' => null, 'error' => 'The uploaded image could not be verified. Please try again.'];
    }

    $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return ['path' => null, 'error' => 'Use a JPG, PNG, GIF, or WebP image.'];
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($upload['tmp_name']);
    $imageInfo = @getimagesize($upload['tmp_name']);

    if (
        !is_string($mimeType)
        || !isset($allowedMimeExtensions[$mimeType])
        || !in_array($extension, $allowedMimeExtensions[$mimeType], true)
        || $imageInfo === false
        || !isset($imageInfo['mime'])
        || $imageInfo['mime'] !== $mimeType
    ) {
        return ['path' => null, 'error' => 'The file contents do not match a supported image type.'];
    }

    $storedExtension = $mimeType === 'image/jpeg' ? 'jpg' : $allowedMimeExtensions[$mimeType][0];
    $filename = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';

    if (!is_dir($directory) || !is_writable($directory)) {
        error_log('Image upload failed: uploads directory is missing or not writable.');
        return ['path' => null, 'error' => 'Image uploads are temporarily unavailable. Please try again later.'];
    }

    $destination = $directory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($upload['tmp_name'], $destination)) {
        error_log('Image upload failed: the uploaded file could not be moved.');
        return ['path' => null, 'error' => 'The image could not be saved. Please try again.'];
    }

    return ['path' => 'uploads/' . $filename, 'error' => null];
}

function delete_item_image(?string $path): void
{
    if ($path === null || preg_match('/\Auploads\/[a-f0-9]{32}\.(?:jpg|gif|png|webp)\z/', $path) !== 1) {
        return;
    }

    $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Image cleanup failed for an item image.');
    }
}
