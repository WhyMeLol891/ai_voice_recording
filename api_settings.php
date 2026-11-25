<?php
/**
 * AI Voice Recording Summarizer - API Settings Handler
 *
 * Handles:
 * - Gemini API connectivity testing
 * - Agnes AI connectivity testing
 * - Saving AI configuration to .env file
 * - CSRF verification
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/GeminiClient.php';

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

// Parse input
$input = $_POST;
if (empty($input)) {
    $raw = json_decode(file_get_contents('php://input'), true);
    if (is_array($raw)) {
        $input = $raw;
    }
}

$action = trim((string)($input['action'] ?? ''));

switch ($action) {
    case 'test_gemini':
        handleTestGemini($input);
        break;

    case 'test_agnes':
        handleTestAgnes($input);
        break;

    case 'save_settings':
        handleSaveSettings($input);
        break;

    case 'get_settings':
        handleGetSettings();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}

/**
 * Test Google Gemini Connection
 */
function handleTestGemini(array $input): void {
    $apiKey = trim((string)($input['gemini_api_key'] ?? ''));
    if ($apiKey === '' || $apiKey === '••••••••••••••••') {
        $apiKey = env('GEMINI_API_KEY', '');
    }

    $model = trim((string)($input['gemini_model'] ?? '')) ?: env('GEMINI_MODEL', DEFAULT_GEMINI_MODEL);
    $endpoint = trim((string)($input['gemini_endpoint'] ?? '')) ?: env('GEMINI_ENDPOINT', DEFAULT_GEMINI_ENDPOINT);

    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Please enter a Gemini API Key to test.']);
        return;
    }

    $client = new GeminiClient($apiKey, $model, $endpoint);
    $res = $client->testConnection();
    echo json_encode($res);
}

/**
 * Test Agnes AI Connection
 */
function handleTestAgnes(array $input): void {
    $apiKey = trim((string)($input['agnes_api_key'] ?? ''));
    if ($apiKey === '' || $apiKey === '••••••••••••••••') {
        $apiKey = env('AGNES_API_KEY', '');
    }

    $model = trim((string)($input['agnes_model'] ?? '')) ?: env('AGNES_MODEL', DEFAULT_AGNES_MODEL);
    $endpoint = rtrim(trim((string)($input['agnes_endpoint'] ?? '')) ?: env('AGNES_ENDPOINT', DEFAULT_AGNES_ENDPOINT), '/');

    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Please enter an Agnes AI API Key to test.']);
        return;
    }

    $startTime = microtime(true);

    // Agnes AI endpoint testing via OpenAI-compatible /chat/completions or /models
    $testUrl = $endpoint . '/chat/completions';
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => 'Test connection. Reply with "OK".']
        ],
        'max_tokens' => 10,
        'temperature' => 0.1
    ];

    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $responseBody = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $latency = round((microtime(true) - $startTime) * 1000);

    if ($status === 200) {
        $json = json_decode($responseBody, true);
        $reply = $json['choices'][0]['message']['content'] ?? 'OK';
        echo json_encode([
            'success' => true,
            'message' => "Agnes AI connection successful! Model: " . htmlspecialchars($model) . " responded.",
            'reply' => trim($reply),
            'latency_ms' => $latency
        ]);
        return;
    }

    // If chat endpoint gave 404, try /models listing
    if ($status === 404) {
        $modelsUrl = $endpoint . '/models';
        $ch2 = curl_init($modelsUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, true);
        $res2 = curl_exec($ch2);
        $st2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($st2 === 200) {
            echo json_encode([
                'success' => true,
                'message' => "Agnes AI connection successful! API key and models endpoint verified.",
                'latency_ms' => $latency
            ]);
            return;
        }
    }

    $errMsg = 'HTTP ' . $status;
    if ($responseBody) {
        $errJson = json_decode($responseBody, true);
        $errMsg = $errJson['error']['message'] ?? $errJson['message'] ?? $responseBody;
    } elseif ($error) {
        $errMsg = $error;
    }

    echo json_encode([
        'success' => false,
        'error' => "Agnes AI connection failed: " . htmlspecialchars($errMsg)
    ]);
}

/**
 * Save settings to .env
 */
function handleSaveSettings(array $input): void {
    $updates = [];

    // Gemini
    if (isset($input['gemini_api_key'])) {
        $key = trim((string)$input['gemini_api_key']);
        if ($key !== '' && $key !== '••••••••••••••••') {
            $updates['GEMINI_API_KEY'] = $key;
        }
    }
    if (isset($input['gemini_model']) && trim((string)$input['gemini_model']) !== '') {
        $updates['GEMINI_MODEL'] = trim((string)$input['gemini_model']);
    }
    if (isset($input['gemini_endpoint']) && trim((string)$input['gemini_endpoint']) !== '') {
        $updates['GEMINI_ENDPOINT'] = trim((string)$input['gemini_endpoint']);
    }

    // Agnes AI
    if (isset($input['agnes_api_key'])) {
        $key = trim((string)$input['agnes_api_key']);
        if ($key !== '' && $key !== '••••••••••••••••') {
            $updates['AGNES_API_KEY'] = $key;
        }
    }
    if (isset($input['agnes_model']) && trim((string)$input['agnes_model']) !== '') {
        $updates['AGNES_MODEL'] = trim((string)$input['agnes_model']);
    }
    if (isset($input['agnes_endpoint']) && trim((string)$input['agnes_endpoint']) !== '') {
        $updates['AGNES_ENDPOINT'] = trim((string)$input['agnes_endpoint']);
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'No settings were changed.']);
        return;
    }

    $saved = save_env($updates);
    if ($saved) {
        app_log("Updated AI settings in .env: " . implode(', ', array_keys($updates)));
        echo json_encode(['success' => true, 'message' => 'Settings successfully saved to server (.env) file!']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to write configuration to .env file. Please check file permissions.']);
    }
}

/**
 * Return current non-sensitive settings
 */
function handleGetSettings(): void {
    $geminiKey = env('GEMINI_API_KEY', '');
    $agnesKey = env('AGNES_API_KEY', '');

    echo json_encode([
        'success' => true,
        'settings' => [
            'gemini_endpoint' => env('GEMINI_ENDPOINT', DEFAULT_GEMINI_ENDPOINT),
            'gemini_model' => env('GEMINI_MODEL', DEFAULT_GEMINI_MODEL),
            'gemini_has_key' => !empty($geminiKey),
            'agnes_endpoint' => env('AGNES_ENDPOINT', DEFAULT_AGNES_ENDPOINT),
            'agnes_model' => env('AGNES_MODEL', DEFAULT_AGNES_MODEL),
            'agnes_has_key' => !empty($agnesKey),
        ]
    ]);
}
