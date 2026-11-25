<?php
/**
 * AI Voice Recording Summarizer - Security Handler
 */

class Security {

    /**
     * Start session securely
     */
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                           (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            @session_start();
        }
    }

    /**
     * Get or generate CSRF token
     */
    public static function getCsrfToken(): string {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token from request
     */
    public static function validateCsrfToken(?string $token): bool {
        self::startSession();
        $stored = $_SESSION['csrf_token'] ?? '';
        if (empty($stored) || empty($token)) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    /**
     * Verify CSRF token from POST body or HTTP headers
     */
    public static function verifyRequestCsrf(): bool {
        $token = $_POST['csrf_token'] ??
                 $_SERVER['HTTP_X_CSRF_TOKEN'] ??
                 $_SERVER['HTTP_X_XSRF_TOKEN'] ??
                 null;

        if (!$token) {
            // Check raw input if JSON
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && !empty($input['csrf_token'])) {
                $token = $input['csrf_token'];
            }
        }

        return self::validateCsrfToken($token);
    }

    /**
     * HTML Output Escaping
     */
    public static function escape(?string $string): string {
        return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitize original filename for safe display or storage
     */
    public static function sanitizeFilename(string $filename): string {
        // Strip directory separators and null bytes
        $filename = basename($filename);
        $filename = str_replace(["\0", "\r", "\n"], '', $filename);
        // Replace unwanted characters but preserve unicode and standard punctuation
        $filename = preg_replace('/[^\p{L}\p{N}_\-\. \(\)\[\]]/u', '_', $filename);
        return trim(substr($filename, 0, 150));
    }

    /**
     * Generate secure unique temporary filename
     */
    public static function generateTempFilename(string $extension): string {
        $ext = strtolower(ltrim($extension, '.'));
        return 'rec_' . bin2hex(random_bytes(16)) . '.' . $ext;
    }

    /**
     * Validate file upload
     */
    public static function validateUpload(
        array $file,
        array $allowedExtensions = ALLOWED_EXTENSIONS,
        int $maxSize = MAX_UPLOAD_SIZE,
        array $allowedMimes = ALLOWED_MIME_TYPES
    ): array {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload parameters.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['valid' => false, 'error' => 'The uploaded file exceeds the maximum allowed size (250MB).'];
            case UPLOAD_ERR_PARTIAL:
                return ['valid' => false, 'error' => 'The file was only partially uploaded. Please try again.'];
            case UPLOAD_ERR_NO_FILE:
                return ['valid' => false, 'error' => 'No file was uploaded.'];
            case UPLOAD_ERR_NO_TMP_DIR:
                return ['valid' => false, 'error' => 'Missing temporary folder on server.'];
            case UPLOAD_ERR_CANT_WRITE:
                return ['valid' => false, 'error' => 'Failed to write file to disk.'];
            default:
                return ['valid' => false, 'error' => 'Unknown upload error occurred.'];
        }

        if ($file['size'] > $maxSize) {
            return [
                'valid' => false,
                'error' => 'File size exceeds maximum limit of ' . format_bytes($maxSize) . ' (File size: ' . format_bytes($file['size']) . ').'
            ];
        }

        if ($file['size'] <= 0) {
            return ['valid' => false, 'error' => 'Uploaded file is empty (0 bytes).'];
        }

        // Validate extension
        $origName = $file['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions, true)) {
            return [
                'valid' => false,
                'error' => "Unsupported file format (.$ext). Supported formats: " . implode(', ', array_map('strtoupper', $allowedExtensions))
            ];
        }

        // Validate MIME type
        $tmpPath = $file['tmp_name'];
        if (!file_exists($tmpPath) || (php_sapi_name() !== 'cli' && !is_uploaded_file($tmpPath))) {
            return ['valid' => false, 'error' => 'File upload verification failed.'];
        }

        $detectedMime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
            }
        }

        if (empty($detectedMime) && function_exists('mime_content_type')) {
            $detectedMime = mime_content_type($tmpPath);
        }

        // Standardize common audio/video detected types
        $detectedMime = strtolower($detectedMime ?: 'application/octet-stream');

        // Note: some systems detect MP4/M4A/FLAC/OGG as application/octet-stream or video/mp4.
        // We verify that the detected MIME matches allowed list OR falls into audio/video category matching extension.
        $mimeValid = in_array($detectedMime, $allowedMimes, true);
        if (!$mimeValid) {
            // Check if it's a known audio/video container recognized by extension
            $extensionMimes = [
                'mp3' => ['audio/mpeg', 'audio/mp3', 'application/octet-stream'],
                'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'application/octet-stream'],
                'm4a' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4', 'application/octet-stream'],
                'aac' => ['audio/aac', 'audio/x-aac', 'audio/mp4', 'application/octet-stream'],
                'flac'=> ['audio/flac', 'audio/x-flac', 'application/octet-stream'],
                'ogg' => ['audio/ogg', 'application/ogg', 'video/ogg', 'application/octet-stream'],
                'wma' => ['audio/x-ms-wma', 'video/x-ms-asf', 'application/octet-stream'],
                'mp4' => ['video/mp4', 'audio/mp4', 'application/octet-stream'],
                'wmv' => ['video/x-ms-wmv', 'video/x-ms-asf', 'application/octet-stream']
            ];

            if (isset($extensionMimes[$ext]) && in_array($detectedMime, $extensionMimes[$ext], true)) {
                $mimeValid = true;
            }
        }

        if (!$mimeValid) {
            return [
                'valid' => false,
                'error' => "Invalid file MIME type ($detectedMime) for extension .$ext."
            ];
        }

        return [
            'valid' => true,
            'extension' => $ext,
            'mime' => $detectedMime,
            'original_name' => self::sanitizeFilename($origName),
            'size' => (int)$file['size'],
            'error' => null
        ];
    }

    /**
     * Validate temporary file existence and directory traversal protection
     */
    public static function validateTempFilePath(string $filename, string $baseDir = TEMP_PATH): ?string {
        if (empty($filename) || str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return null;
        }

        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $filename;
        $realBase = realpath($baseDir);
        $realPath = realpath($fullPath);

        if ($realBase === false || $realPath === false) {
            return null;
        }

        // Verify the file is strictly inside the target directory
        if (!str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }
}
