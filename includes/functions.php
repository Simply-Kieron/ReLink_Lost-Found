<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');

function start_app_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function item_image_alt(?string $alt, string $fallback): string
{
    return $alt !== null && trim($alt) !== '' ? $alt : $fallback;
}

function request_body_exceeds_post_limit(): bool
{
    if (!isset($_SERVER['CONTENT_LENGTH'], $_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    $contentLength = filter_var($_SERVER['CONTENT_LENGTH'], FILTER_VALIDATE_INT);
    $postLimit = trim((string) ini_get('post_max_size'));

    if ($postLimit === '0') {
        return false;
    }

    if ($contentLength === false || preg_match('/\A(\d+)\s*([KMG]?)\z/i', $postLimit, $matches) !== 1) {
        return false;
    }

    $limitBytes = (int) $matches[1];
    $unit = strtoupper($matches[2]);

    if ($unit === 'K') {
        $limitBytes *= 1024;
    } elseif ($unit === 'M') {
        $limitBytes *= 1024 * 1024;
    } elseif ($unit === 'G') {
        $limitBytes *= 1024 * 1024 * 1024;
    }

    return $contentLength > $limitBytes;
}

function redirect(string $path): void
{
    if (preg_match('/[\r\n\\\\]/', $path) || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $path)) {
        throw new InvalidArgumentException('Redirects must use a local path.');
    }

    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    start_app_session();
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    start_app_session();

    if (!isset($_SESSION['flash'][$type])) {
        return null;
    }

    $message = (string) $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);

    return $message;
}
