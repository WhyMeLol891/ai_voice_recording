<?php
/**
 * AI Voice Recording Summarizer - Application Configuration
 */

// Prevent multiple inclusions
if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);

// Base directory paths
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('TEMP_PATH', STORAGE_PATH . '/temp');
define('LOGS_PATH', STORAGE_PATH . '/logs');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// Ensure essential directories exist and are writable
foreach ([TEMP_PATH, LOGS_PATH, UPLOADS_PATH] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

/**
 * Load environment variables from .env file
 */
function load_env($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos !== false) {
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Remove quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    return true;
}

// Load .env
load_env(BASE_PATH . '/.env');

/**
 * Helper to get environment variable with fallback
 */
function env($key, $default = null) {
    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }
    return $_ENV[$key] ?? $default;
}

/**
 * Update or save environment variables in .env
 */
function save_env(array $data) {
    $filePath = BASE_PATH . '/.env';
    $lines = file_exists($filePath) ? file($filePath, FILE_IGNORE_NEW_LINES) : [];
    $existing = [];

    // Parse existing lines
    foreach ($lines as $idx => $line) {
        $trimmed = trim($line);
        if ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
            $pos = strpos($trimmed, '=');
            if ($pos !== false) {
                $key = trim(substr($trimmed, 0, $pos));
                $existing[$key] = $idx;
            }
        }
    }

    // Update existing or append new keys
    foreach ($data as $key => $value) {
        $newLine = $key . '=' . $value;
        if (isset($existing[$key])) {
            $lines[$existing[$key]] = $newLine;
        } else {
            $lines[] = $newLine;
        }
        $_ENV[$key] = (string)$value;
        putenv("$key=$value");
    }

    $content = implode("\n", $lines) . "\n";
    return file_put_contents($filePath, $content, LOCK_EX) !== false;
}

/**
 * Application Logging Helper
 */
function app_log($message, $level = 'INFO') {
    $logFile = LOGS_PATH . '/app.log';
    $date = date('Y-m-d H:i:s');
    $formatted = "[$date] [$level] " . (is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message) . PHP_EOL;
    @file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX);
}

/**
 * Format bytes to readable string (e.g. 15.2 MB)
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Application Constants
define('APP_NAME', 'AI Voice Recording Summarizer');
define('APP_VERSION', '1.0.0');
define('APP_URL', env('APP_URL', 'http://localhost/ai_voice_recording'));

// File Upload Limits
define('MAX_UPLOAD_SIZE', 250 * 1024 * 1024); // 250MB
define('GEMINI_INLINE_MAX_SIZE', 15 * 1024 * 1024); // 15MB threshold for Strategy A vs Strategy B

// Allowed extensions
define('ALLOWED_EXTENSIONS', [
    'mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg', 'wma', // Audio
    'mp4', 'wmv'                                      // Video
]);

// Allowed MIME types
define('ALLOWED_MIME_TYPES', [
    // Audio
    'audio/mpeg',
    'audio/mp3',
    'audio/wav',
    'audio/x-wav',
    'audio/wave',
    'audio/m4a',
    'audio/x-m4a',
    'audio/mp4',
    'audio/aac',
    'audio/x-aac',
    'audio/flac',
    'audio/x-flac',
    'audio/ogg',
    'application/ogg',
    'audio/x-ms-wma',
    'video/x-ms-asf',
    // Video
    'video/mp4',
    'video/x-ms-wmv'
]);

// Gemini Defaults
define('DEFAULT_GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1');
define('DEFAULT_GEMINI_MODEL', 'gemini-3.5-flash-lite');

// Agnes AI Defaults
define('DEFAULT_AGNES_ENDPOINT', 'https://apihub.agnes-ai.com/v1');
define('DEFAULT_AGNES_MODEL', 'agnes-3.0-flash');
