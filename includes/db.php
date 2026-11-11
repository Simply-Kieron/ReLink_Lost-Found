<?php

$configPath = __DIR__ . '/config.php';

if (!is_file($configPath)) {
    http_response_code(503);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Database unavailable</title></head><body><main><h1>Database unavailable</h1><p>Database configuration is missing. Copy includes/config.example.php to includes/config.php and update the connection settings.</p></main></body></html>';
    exit;
}

$config = require $configPath;

if (
    !is_array($config)
    || !isset($config['host'], $config['port'], $config['database'], $config['username'], $config['password'])
    || !is_string($config['host'])
    || $config['host'] === ''
    || !is_int($config['port'])
    || $config['port'] < 1
    || $config['port'] > 65535
    || !is_string($config['database'])
    || $config['database'] === ''
    || !is_string($config['username'])
    || $config['username'] === ''
    || !is_string($config['password'])
) {
    error_log('Database connection failed: configuration is invalid.');
    http_response_code(503);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Database unavailable</title></head><body><main><h1>Database unavailable</h1><p>The database is temporarily unavailable. Please try again later.</p></main></body></html>';
    exit;
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['database']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Database unavailable</title></head><body><main><h1>Database unavailable</h1><p>The database is temporarily unavailable. Please try again later.</p></main></body></html>';
    exit;
}
