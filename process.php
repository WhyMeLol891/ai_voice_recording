<?php
/**
 * AI Voice Recording Summarizer - Processing Handler
 *
 * Handles:
 * - CSRF verification
 * - Audio analysis using Google Gemini Multimodal API
 * - Full transcription generation
 * - 7-section executive summary generation
 * - PDF report creation
 * - Temporary audio recording cleanup (Stateless architecture)
 * - JSON response
 */

// Allow longer execution for AI processing of larger files (up to 10 minutes)
@set_time_limit(600);
@ini_set('memory_limit', '512M');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/GeminiClient.php';
require_once __DIR__ . '/includes/PdfReport.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

// Verify CSRF
if (!Security::verifyRequestCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF verification failed. Please refresh the page and try again.']);
    exit;
}

// Parse request parameters (support both form POST and raw JSON)
$input = $_POST;
if (empty($input)) {
    $raw = json_decode(file_get_contents('php://input'), true);
    if (is_array($raw)) {
        $input = $raw;
    }
}

$tempFilename = trim((string)($input['temp_file'] ?? ''));
$originalName = Security::sanitizeFilename(trim((string)($input['original_name'] ?? 'recording.mp3')));
$audioLanguage = trim((string)($input['audio_language'] ?? 'auto'));
$summaryLanguage = trim((string)($input['summary_language'] ?? 'zh'));
$duration = trim((string)($input['duration'] ?? 'N/A'));

// Optional client overrides from Settings / LocalStorage
$apiKey = !empty($input['gemini_api_key']) ? trim($input['gemini_api_key']) : null;
$model = !empty($input['gemini_model']) ? trim($input['gemini_model']) : null;
$endpoint = !empty($input['gemini_endpoint']) ? trim($input['gemini_endpoint']) : null;

// Validate temporary audio file
$filePath = Security::validateTempFilePath($tempFilename, TEMP_PATH);
if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Uploaded recording was not found or has expired. Please upload the file again.']);
    exit;
}

$fileSize = filesize($filePath);
$formattedSize = format_bytes($fileSize);

try {
    // Instantiate Gemini Client
    $client = new GeminiClient($apiKey, $model, $endpoint);

    if (!$client->hasKey()) {
        throw new Exception('Google Gemini API Key is missing. Please configure your Gemini API Key in the Settings panel (click Settings in top navigation).');
    }

    // Process Recording
    $result = $client->processAudio($filePath, $audioLanguage, $summaryLanguage, $originalName);

    // Prepare metadata
    $metadata = [
        'original_name' => $originalName,
        'duration' => $duration,
        'file_size' => $formattedSize,
        'summary_language' => $result['summary_language'],
        'detected_language' => $result['detected_language'],
        'processed_at' => date('Y-m-d H:i:s')
    ];

    // Generate PDF Report
    $reportFileName = PdfReport::generate($result, $metadata);

    // Cleanup: Delete the temporary audio recording file immediately (stateless requirement)
    if (file_exists($filePath)) {
        @unlink($filePath);
        app_log("Cleaned up temporary audio recording: $tempFilename");
    }

    // Return complete payload
    echo json_encode([
        'success' => true,
        'data' => [
            'overall_summary' => $result['overall_summary'],
            'main_topics' => $result['main_topics'],
            'important_points' => $result['important_points'],
            'key_metrics' => $result['key_metrics'],
            'decisions' => $result['decisions'],
            'action_items' => $result['action_items'],
            'high_level_synthesis' => $result['high_level_synthesis'],
            'transcription' => $result['transcription'],
            'detected_language' => $result['detected_language'],
            'summary_language' => $result['summary_language'],
            'metadata' => $metadata,
            'report_file' => $reportFileName
        ]
    ]);

} catch (\Throwable $e) {
    // Ensure temporary audio is removed even on failure
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    app_log("Processing error for $originalName: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
