<?php
/**
 * AI Voice Recording Summarizer - Upload Handler
 *
 * Handles:
 * - CSRF verification
 * - File validation (size up to 250MB, MIME type, extension)
 * - Temporary file storage in storage/temp/
 * - JSON response
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

// Verify CSRF
if (!Security::verifyRequestCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF security token. Please refresh the page and try again.']);
    exit;
}

// Check if file is provided
if (empty($_FILES['recording'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No recording file was uploaded.']);
    exit;
}

// Validate file
$validation = Security::validateUpload($_FILES['recording']);
if (!$validation['valid']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $validation['error']]);
    exit;
}

// Generate unique temporary filename
$tempFilename = Security::generateTempFilename($validation['extension']);
$targetPath = TEMP_PATH . DIRECTORY_SEPARATOR . $tempFilename;

// Move uploaded file to storage/temp/
if (!move_uploaded_file($_FILES['recording']['tmp_name'], $targetPath)) {
    http_response_code(500);
    app_log("Failed to move uploaded file to $targetPath", 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Server failed to save the uploaded recording to temporary storage.']);
    exit;
}

app_log("File uploaded successfully: {$validation['original_name']} -> $tempFilename (" . format_bytes($validation['size']) . ")");

echo json_encode([
    'success' => true,
    'temp_file' => $tempFilename,
    'original_name' => $validation['original_name'],
    'mime_type' => $validation['mime'],
    'file_size' => $validation['size'],
    'file_size_formatted' => format_bytes($validation['size'])
]);
