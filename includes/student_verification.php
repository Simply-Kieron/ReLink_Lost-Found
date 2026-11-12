<?php

function student_verification_code_encryption_key(array $config): string
{
    $encodedKey = $config['verification_code_encryption_key'] ?? null;
    if (!is_string($encodedKey) || preg_match('/\A[a-f0-9]{64}\z/i', $encodedKey) !== 1) {
        throw new RuntimeException('Set a 64-character verification_code_encryption_key in includes/config.php before issuing or viewing codes.');
    }

    $key = hex2bin($encodedKey);
    if (!is_string($key)) {
        throw new RuntimeException('The verification code encryption key is invalid.');
    }

    return $key;
}

function encrypt_student_verification_code(string $code, string $key, int $userId): string
{
    if (preg_match('/\A[0-9]{8}\z/', $code) !== 1 || strlen($key) !== 32) {
        throw new InvalidArgumentException('A valid verification code and encryption key are required.');
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('The OpenSSL extension is required to store verification codes securely.');
    }

    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $code,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        'relink-student-verification:' . $userId
    );
    if (!is_string($ciphertext) || strlen($tag) !== 16) {
        throw new RuntimeException('The verification code could not be stored securely.');
    }

    return 'v1:' . base64_encode($nonce . $tag . $ciphertext);
}

function decrypt_student_verification_code(string $encryptedCode, string $key, int $userId): string
{
    if (strlen($key) !== 32 || !function_exists('openssl_decrypt')) {
        throw new RuntimeException('Verification code decryption is unavailable. Check the OpenSSL extension and encryption key.');
    }

    if (preg_match('/\Av1:([A-Za-z0-9+\/=]+)\z/', $encryptedCode, $matches) !== 1) {
        throw new RuntimeException('The stored verification code is invalid or uses an unsupported format.');
    }

    $payload = base64_decode($matches[1], true);
    if (!is_string($payload) || strlen($payload) < 29) {
        throw new RuntimeException('The stored verification code is invalid or incomplete.');
    }

    $nonce = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);
    $code = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        'relink-student-verification:' . $userId
    );
    if (!is_string($code) || preg_match('/\A[0-9]{8}\z/', $code) !== 1) {
        throw new RuntimeException('The verification code could not be decrypted. Confirm the configured encryption key has not changed.');
    }

    return $code;
}

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
