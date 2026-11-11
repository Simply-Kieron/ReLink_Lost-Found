<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('staff');
require_once __DIR__ . '/../includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(404);
    exit('Student verification photo not found.');
}

$statement = $pdo->prepare('SELECT id_image_path FROM student_verifications WHERE id = :id LIMIT 1');
$statement->execute(['id' => $id]);
$path = $statement->fetchColumn();

if (!is_string($path) || preg_match('/\Auploads\/student-verification\/([a-f0-9]{32})\.(jpg|png|webp)\z/', $path, $matches) !== 1) {
    http_response_code(404);
    exit('Student verification photo not found.');
}

$filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
    . 'student-verification' . DIRECTORY_SEPARATOR . $matches[1] . '.' . $matches[2];
if (!is_file($filePath) || !is_readable($filePath)) {
    error_log('Student verification photo is missing or unreadable.');
    http_response_code(404);
    exit('Student verification photo not found.');
}

$contentTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . $contentTypes[$matches[2]]);
header('Content-Length: ' . (string) filesize($filePath));
header('Cache-Control: private, no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
readfile($filePath);
