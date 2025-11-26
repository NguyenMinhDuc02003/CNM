<?php

/**
 * Centralised chat configuration bridge.
 *
 * We try to reuse the Admin module's settings when available so both back-office
 * and the standalone chat module stay in sync. Reasonable defaults are defined
 * below to keep the chat working even if the Admin config has not been included.
 */
$adminChatConfig = dirname(__DIR__) . '/../Admin/kaiadmin-lite-1.2.0/includes/chat_config.php';
if (file_exists($adminChatConfig)) {
    require_once $adminChatConfig;
}

// Default websocket endpoint pieces in case the admin config is absent.
if (!defined('CHAT_WS_SCHEME')) {
    define('CHAT_WS_SCHEME', 'ws');
}

if (!defined('CHAT_WS_HOST')) {
    define('CHAT_WS_HOST', '127.0.0.1');
}

if (!defined('CHAT_WS_PORT')) {
    define('CHAT_WS_PORT', 8080);
}

// Upload settings reused by both chat_app and the admin portal.
if (!defined('CHAT_UPLOAD_DIRECTORY')) {
    define('CHAT_UPLOAD_DIRECTORY', __DIR__ . '/../uploads');
}

if (!defined('CHAT_UPLOAD_RELATIVE_PATH')) {
    define('CHAT_UPLOAD_RELATIVE_PATH', '/chat_app/uploads');
}

if (!defined('CHAT_MAX_UPLOAD_SIZE')) {
    define('CHAT_MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
}

if (!defined('CHAT_ALLOWED_IMAGE_TYPES')) {
    define('CHAT_ALLOWED_IMAGE_TYPES', [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ]);
}

if (!defined('CHAT_ALLOWED_FILE_TYPES')) {
    define('CHAT_ALLOWED_FILE_TYPES', [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-rar-compressed',
        'text/plain',
    ]);
}

if (!defined('CHAT_APP_BASE_PATH')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null;
    $chatDir = realpath(__DIR__ . '/..');
    $basePath = '/chat_app';

    if ($docRoot && $chatDir) {
        $normalizedDocRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $normalizedChatDir = str_replace('\\', '/', $chatDir);
        if (strpos($normalizedChatDir, $normalizedDocRoot) === 0) {
            $relative = substr($normalizedChatDir, strlen($normalizedDocRoot));
            $basePath = $relative === '' ? '/' : '/' . ltrim($relative, '/');
        }
    }

    define('CHAT_APP_BASE_PATH', rtrim($basePath, '/') . '/');
}

if (!defined('CHAT_APP_BASE_URL')) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        define('CHAT_APP_BASE_URL', $scheme . '://' . $host . CHAT_APP_BASE_PATH);
    } else {
        define('CHAT_APP_BASE_URL', CHAT_APP_BASE_PATH);
    }
}

/**
 * Convenience helper returning the full websocket URL.
 */
function chat_build_websocket_url(string $token = ''): string
{
    $host = CHAT_WS_HOST;
    $port = CHAT_WS_PORT;
    $scheme = CHAT_WS_SCHEME;

    $query = $token !== '' ? '?token=' . urlencode($token) : '';

    return sprintf('%s://%s:%s%s', $scheme, $host, $port, $query);
}
