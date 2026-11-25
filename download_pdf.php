<?php
/**
 * AI Voice Recording Summarizer - Secure PDF Report Downloader
 *
 * Handles:
 * - Filename regex validation
 * - Path traversal prevention
 * - PDF streaming with download headers
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';

$filename = trim((string)($_GET['file'] ?? ''));

// Validate filename format (must match report_<hex32>.pdf)
if (!preg_match('/^report_[a-f0-9]{32}\.pdf$/i', $filename)) {
    http_response_code(400);
    die('Invalid or malformed report filename requested.');
}

// Validate directory traversal and path existence
$fullPath = Security::validateTempFilePath($filename, TEMP_PATH);
if (!$fullPath || !file_exists($fullPath) || !is_readable($fullPath)) {
    http_response_code(404);
    die('Report not found or has expired. Please process the recording again.');
}

$fileSize = filesize($fullPath);
$downloadName = 'AI_Summary_Report_' . date('Ymd_His') . '.pdf';

// Send secure binary download headers
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

// Clean output buffer before streaming
if (ob_get_level()) {
    ob_end_clean();
}

readfile($fullPath);
exit;
