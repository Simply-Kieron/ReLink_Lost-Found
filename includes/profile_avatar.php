<?php

function profile_avatar_src(?string $path): ?string
{
    if (
        $path === null
        || preg_match('/\Auploads\/profile-avatars\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $path) !== 1
    ) {
        return null;
    }

    return '../' . $path;
}

function validate_profile_avatar_upload(array $upload): array
{
    $maxBytes = 15 * 1024 * 1024;
    $allowedMimeExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    if (
        !isset($upload['error'], $upload['tmp_name'], $upload['name'], $upload['size'])
        || !is_int($upload['error'])
        || !is_string($upload['name'])
        || !is_string($upload['tmp_name'])
        || !is_int($upload['size'])
    ) {
        return ['path' => null, 'error' => 'Choose a profile photo to upload.'];
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $message = match ($upload['error']) {
            UPLOAD_ERR_INI_SIZE => 'The profile photo exceeds the server upload limit. Choose a smaller image and try again.',
            UPLOAD_ERR_FORM_SIZE => 'The profile photo must be no larger than 15 MB.',
            UPLOAD_ERR_PARTIAL => 'The profile photo upload was interrupted. Please choose the photo again and retry.',
            default => 'The profile photo upload failed. Please choose another image and try again.',
        };

        return ['path' => null, 'error' => $message];
    }

    if ($upload['size'] < 1 || $upload['size'] > $maxBytes || !is_uploaded_file($upload['tmp_name'])) {
        return ['path' => null, 'error' => 'The profile photo must be a valid image no larger than 15 MB.'];
    }

    $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($upload['tmp_name']);
    $imageInfo = @getimagesize($upload['tmp_name']);

    if (
        !is_string($mimeType)
        || !isset($allowedMimeExtensions[$mimeType])
        || !in_array($extension, $allowedMimeExtensions[$mimeType], true)
        || $imageInfo === false
        || !isset($imageInfo['mime'], $imageInfo[0], $imageInfo[1])
        || $imageInfo['mime'] !== $mimeType
        || $imageInfo[0] > 6000
        || $imageInfo[1] > 6000
        || $imageInfo[0] * $imageInfo[1] > 24000000
    ) {
        return ['path' => null, 'error' => 'Use a valid JPG, PNG, or WebP profile photo no larger than 24 megapixels.'];
    }

    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile-avatars';
    if (!is_dir($directory) || !is_writable($directory)) {
        error_log('Profile avatar upload failed: public avatar directory is missing or not writable.');
        return ['path' => null, 'error' => 'Profile photo uploads are temporarily unavailable. Please try again later.'];
    }

    $storedExtension = $mimeType === 'image/jpeg' ? 'jpg' : $allowedMimeExtensions[$mimeType][0];
    $filename = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        error_log('Profile avatar upload failed: the uploaded file could not be moved.');
        return ['path' => null, 'error' => 'The profile photo could not be saved. Please try again.'];
    }

    return ['path' => 'uploads/profile-avatars/' . $filename, 'error' => null];
}

function delete_profile_avatar(?string $path): void
{
    if (
        $path === null
        || preg_match('/\Auploads\/profile-avatars\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $path) !== 1
    ) {
        return;
    }

    $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Profile avatar cleanup failed.');
    }
}
