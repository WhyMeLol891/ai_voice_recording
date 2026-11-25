<?php
/**
 * AI Voice Recording Summarizer - Gemini API Client
 *
 * Implements Google Gemini Multimodal Audio/Video Processing:
 * - Strategy A: Base64 inlineData for files <= 15MB
 * - Strategy B: Gemini Files API resumable upload for files > 15MB up to 250MB
 * - Complete verbatim transcription
 * - Structured 7-section executive summary in requested language
 */

class GeminiClient {

    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $endpoint = null) {
        $this->apiKey = trim($apiKey ?? env('GEMINI_API_KEY', ''));
        $this->model = trim($model ?? env('GEMINI_MODEL', DEFAULT_GEMINI_MODEL));
        $this->endpoint = rtrim(trim($endpoint ?? env('GEMINI_ENDPOINT', DEFAULT_GEMINI_ENDPOINT)), '/');
    }

    /**
     * Get API key (for internal use)
     */
    public function hasKey(): bool {
        return !empty($this->apiKey) && $this->apiKey !== 'your-new-key-here';
    }

    /**
     * Test Gemini API connectivity
     */
    public function testConnection(): array {
        if (!$this->hasKey()) {
            return [
                'success' => false,
                'error' => 'Gemini API key is not configured. Please enter a valid API key in Settings.'
            ];
        }

        $startTime = microtime(true);
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'Hello. Reply with the single word "OK" to confirm connection test.']
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 10,
                'temperature' => 0.1
            ]
        ];

        // Try configured endpoint first; if v1 fails with 404 (model only on v1beta), retry v1beta
        $endpointsToTry = [$this->endpoint];
        if (str_contains($this->endpoint, '/v1') && !str_contains($this->endpoint, '/v1beta')) {
            $endpointsToTry[] = str_replace('/v1', '/v1beta', $this->endpoint);
        }

        $lastError = '';
        foreach ($endpointsToTry as $ep) {
            $url = $ep . '/models/' . urlencode($this->model) . ':generateContent?key=' . urlencode($this->apiKey);
            $response = $this->executeCurl($url, 'POST', $payload, ['Content-Type: application/json'], 25);

            if ($response['status'] === 200) {
                $latency = round((microtime(true) - $startTime) * 1000);
                $body = json_decode($response['body'], true);
                $reply = $body['candidates'][0]['content']['parts'][0]['text'] ?? 'OK';
                return [
                    'success' => true,
                    'message' => 'Gemini API connection successful! Model: ' . htmlspecialchars($this->model) . ' responded.',
                    'reply' => trim($reply),
                    'latency_ms' => $latency,
                    'endpoint' => $ep
                ];
            } else {
                $errBody = json_decode($response['body'], true);
                $msg = $errBody['error']['message'] ?? ($response['error'] ?: 'HTTP status ' . $response['status']);
                $lastError = $msg;

                // If not a 404 model not found, don't keep trying other versions
                if ($response['status'] !== 404) {
                    break;
                }
            }
        }

        return [
            'success' => false,
            'error' => 'Gemini connection failed: ' . $lastError
        ];
    }

    /**
     * Map file extension to exact standard Gemini MIME type
     */
    public static function getMimeType(string $filePath, string $ext = ''): string {
        $ext = strtolower($ext ?: pathinfo($filePath, PATHINFO_EXTENSION));
        $map = [
            'mp3'  => 'audio/mp3',
            'wav'  => 'audio/wav',
            'm4a'  => 'audio/mp4',
            'aac'  => 'audio/aac',
            'flac' => 'audio/flac',
            'ogg'  => 'audio/ogg',
            'wma'  => 'audio/x-ms-wma',
            'mp4'  => 'video/mp4',
            'wmv'  => 'video/x-ms-wmv'
        ];
        return $map[$ext] ?? 'audio/mp3';
    }

    /**
     * Build Prompt for Transcription & 7-Section Summary
     */
    private function buildPrompt(string $audioLanguage, string $summaryLanguage): string {
        $audioLangInstruction = match($audioLanguage) {
            'zh' => 'The spoken audio is in Chinese (Mandarin/Cantonese/dialects).',
            'en' => 'The spoken audio is in English.',
            'ms' => 'The spoken audio is in Malay (Bahasa Melayu).',
            default => 'Automatically detect the spoken language(s). The recording may be multilingual, bilingual, or contain code-switching (e.g. English, Chinese, Malay).'
        };

        $summaryLangName = match($summaryLanguage) {
            'en' => 'English',
            'ms' => 'Malay (Bahasa Melayu)',
            default => 'Simplified Chinese (简体中文)'
        };

        return <<<PROMPT
You are an expert executive audio transcriptionist and senior intelligence analyst.
Analyze the provided audio/video recording thoroughly from beginning to end.

### LANGUAGE REQUIREMENTS:
- Spoken audio context: {$audioLangInstruction}
- TARGET SUMMARY LANGUAGE: You MUST generate all 7 summary sections strictly in {$summaryLangName}.
- TRANSCRIPTION LANGUAGE: The transcription must be in the verbatim original spoken language(s).

### TASK 1: COMPLETE AUDIO TRANSCRIPTION
- Produce a full, unabridged, verbatim transcript of the entire recording from start to finish.
- Accurately identify speakers if possible (e.g., Speaker 1, Speaker 2) or transcribe sequentially.
- Support multilingual speech and code-switching seamlessly.
- Preserve all technical terms, names, dates, numbers, acronyms, and critical context without omission.
- Do NOT abbreviate, truncate, or summarize the transcription text.

### TASK 2: STRUCTURED 7-SECTION EXECUTIVE SUMMARY
Generate a comprehensive, high-precision executive summary in {$summaryLangName} divided into exactly seven sections:

1. **Overall Summary** (`overall_summary`):
   A comprehensive, well-structured paragraph explaining the overarching context, background, purpose, and essence of the recording.

2. **Main Topics Discussed** (`main_topics`):
   An array of distinct topics, themes, and agenda items explored during the recording.

3. **Important Points** (`important_points`):
   An array of critical statements, explanations, perspectives, and key discussion highlights.

4. **Key Metrics, References & Information** (`key_metrics`):
   An array of specific data points, dates, timelines, quantities, metrics, technical references, facts, and measurements mentioned.

5. **Decisions & Conclusions** (`decisions`):
   An array of definitive decisions, agreements, resolutions, and conclusions reached. If no formal decisions were made, return an empty array [].

6. **Action Items & Next Steps** (`action_items`):
   An array of structured tasks. Each item should be an object with:
   - "task": Clear description of the action required.
   - "assignee": Responsible person or team (or "Unassigned" if not specified).
   - "deadline": Specified due date or timeline (or "Not specified" if not mentioned).
   If no action items were identified, return an empty array [].

7. **High-Level Synthesis** (`high_level_synthesis`):
   An executive synthesis paragraph articulating the strategic takeaways, overarching significance, and future outlook.

### STRICT JSON OUTPUT FORMAT:
You MUST respond with a valid JSON object matching this exact structure:
{
  "detected_language": "Detected language description (e.g., English, Chinese, Malay, Bilingual)",
  "transcription": "Full verbatim transcript...",
  "overall_summary": "Comprehensive overview paragraph...",
  "main_topics": [
    "Topic 1...",
    "Topic 2..."
  ],
  "important_points": [
    "Point 1...",
    "Point 2..."
  ],
  "key_metrics": [
    "Metric / Reference 1...",
    "Metric / Reference 2..."
  ],
  "decisions": [
    "Decision 1..."
  ],
  "action_items": [
    {
      "task": "Task description...",
      "assignee": "Person / team...",
      "deadline": "Deadline..."
    }
  ],
  "high_level_synthesis": "Executive synthesis and final takeaway..."
}
PROMPT;
    }

    /**
     * Process Audio File (Chooses Strategy A or Strategy B automatically)
     */
    public function processAudio(
        string $filePath,
        string $audioLanguage = 'auto',
        string $summaryLanguage = 'zh',
        ?string $originalName = null
    ): array {
        if (!$this->hasKey()) {
            throw new Exception('Gemini API key is missing. Please configure it in the Settings panel.');
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception('Audio recording file not found or is unreadable.');
        }

        $fileSize = filesize($filePath);
        if ($fileSize <= 0) {
            throw new Exception('Audio recording file is empty.');
        }

        $mimeType = self::getMimeType($filePath);
        $prompt = $this->buildPrompt($audioLanguage, $summaryLanguage);

        app_log("Starting audio processing for: " . ($originalName ?: basename($filePath)) . " (" . format_bytes($fileSize) . ", $mimeType)");

        // Select Strategy based on file size threshold (15MB)
        if ($fileSize <= GEMINI_INLINE_MAX_SIZE) {
            app_log("Using Strategy A: Base64 Inline Upload (<= 15MB)");
            $result = $this->processInlineStrategy($filePath, $mimeType, $prompt);
        } else {
            app_log("Using Strategy B: Gemini Files API Resumable Upload (> 15MB)");
            $result = $this->processFilesApiStrategy($filePath, $mimeType, $prompt, $originalName ?: basename($filePath));
        }

        // Normalize structured output
        return $this->normalizeResult($result, $audioLanguage, $summaryLanguage);
    }

    /**
     * Strategy A: Base64 Inline Data (for files <= 15MB)
     */
    private function processInlineStrategy(string $filePath, string $mimeType, string $prompt): array {
        $fileBytes = file_get_contents($filePath);
        if ($fileBytes === false) {
            throw new Exception('Failed to read audio file contents.');
        }

        $base64Data = base64_encode($fileBytes);
        unset($fileBytes); // Free memory

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Data
                            ]
                        ],
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.2
            ]
        ];

        return $this->callGenerateContent($payload);
    }

    /**
     * Strategy B: Gemini Files API Resumable Upload (for files > 15MB up to 250MB)
     */
    private function processFilesApiStrategy(string $filePath, string $mimeType, string $prompt, string $displayName): array {
        $fileSize = filesize($filePath);
        $uploadedFileResource = null;

        try {
            // Step 1: Initiate Resumable Upload Session
            $initUrl = 'https://generativelanguage.googleapis.com/upload/v1beta/files?key=' . urlencode($this->apiKey);
            $initHeaders = [
                'X-Goog-Upload-Protocol: resumable',
                'X-Goog-Upload-Command: start',
                'X-Goog-Upload-Header-Content-Length: ' . $fileSize,
                'X-Goog-Upload-Header-Content-Type: ' . $mimeType,
                'Content-Type: application/json'
            ];
            $initBody = json_encode([
                'file' => [
                    'display_name' => Security::sanitizeFilename($displayName)
                ]
            ]);

            $initRes = $this->executeCurlWithHeaders($initUrl, 'POST', $initBody, $initHeaders, 30);
            if ($initRes['status'] !== 200) {
                throw new Exception('Failed to initiate Gemini Files API upload session: ' . $initRes['body']);
            }

            // Extract upload URL from response header
            $uploadUrl = $initRes['response_headers']['x-goog-upload-url'] ??
                         $initRes['response_headers']['X-Goog-Upload-URL'] ??
                         null;

            if (!$uploadUrl) {
                throw new Exception('Did not receive resumable upload URL from Gemini Files API.');
            }

            // Step 2: Upload File Binary
            $fileHandle = fopen($filePath, 'rb');
            if (!$fileHandle) {
                throw new Exception('Unable to open recording file for streaming upload.');
            }

            $uploadHeaders = [
                'Content-Length: ' . $fileSize,
                'X-Goog-Upload-Offset: 0',
                'X-Goog-Upload-Command: upload, finalize'
            ];

            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, stream_get_contents($fileHandle));
            fclose($fileHandle);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $uploadHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 minutes max for 250MB
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $uploadResponseBody = curl_exec($ch);
            $uploadStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $uploadErr = curl_error($ch);
            curl_close($ch);

            if ($uploadStatus !== 200 || !$uploadResponseBody) {
                throw new Exception('Gemini Files API file upload failed: ' . ($uploadErr ?: $uploadResponseBody));
            }

            $uploadJson = json_decode($uploadResponseBody, true);
            $fileInfo = $uploadJson['file'] ?? null;
            if (!$fileInfo || empty($fileInfo['uri'])) {
                throw new Exception('Invalid response received from Gemini Files API: ' . $uploadResponseBody);
            }

            $uploadedFileResource = $fileInfo;
            $fileUri = $fileInfo['uri'];
            $fileName = $fileInfo['name']; // e.g. "files/xyz123"

            // Step 3: Wait until file state is ACTIVE (if in PROCESSING state)
            $state = $fileInfo['state'] ?? 'ACTIVE';
            $pollAttempts = 0;
            $maxPollAttempts = 30; // 30 * 2s = 60s max
            while ($state === 'PROCESSING' && $pollAttempts < $maxPollAttempts) {
                sleep(2);
                $pollAttempts++;
                $checkUrl = 'https://generativelanguage.googleapis.com/v1beta/' . $fileName . '?key=' . urlencode($this->apiKey);
                $checkRes = $this->executeCurl($checkUrl, 'GET', null, [], 15);
                if ($checkRes['status'] === 200) {
                    $checkJson = json_decode($checkRes['body'], true);
                    $state = $checkJson['state'] ?? 'ACTIVE';
                    if ($state === 'FAILED') {
                        throw new Exception('Gemini audio file processing failed in cloud.');
                    }
                }
            }

            // Step 4: Call generateContent with file_data
            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'file_data' => [
                                    'mime_type' => $mimeType,
                                    'file_uri' => $fileUri
                                ]
                            ],
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.2
                ]
            ];

            $result = $this->callGenerateContent($payload);
            return $result;

        } finally {
            // Step 5: Always clean up remote Gemini cloud file after processing
            if (!empty($uploadedFileResource['name'])) {
                $this->deleteRemoteFile($uploadedFileResource['name']);
            }
        }
    }

    /**
     * Delete remote Gemini cloud file after processing
     */
    private function deleteRemoteFile(string $remoteFileName): void {
        try {
            $delUrl = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($remoteFileName, '/') . '?key=' . urlencode($this->apiKey);
            $this->executeCurl($delUrl, 'DELETE', null, [], 10);
            app_log("Deleted remote Gemini file: $remoteFileName");
        } catch (\Throwable $e) {
            app_log("Warning: Failed to delete remote Gemini file $remoteFileName: " . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * Execute generateContent API call
     */
    private function callGenerateContent(array $payload): array {
        // Try configured endpoint, with v1beta fallback if necessary
        $endpoints = [$this->endpoint];
        if (str_contains($this->endpoint, '/v1') && !str_contains($this->endpoint, '/v1beta')) {
            $endpoints[] = str_replace('/v1', '/v1beta', $this->endpoint);
        }

        $lastError = '';
        foreach ($endpoints as $ep) {
            $url = $ep . '/models/' . urlencode($this->model) . ':generateContent?key=' . urlencode($this->apiKey);
            $response = $this->executeCurl($url, 'POST', $payload, ['Content-Type: application/json'], 300);

            if ($response['status'] === 200) {
                $json = json_decode($response['body'], true);
                $candidate = $json['candidates'][0] ?? null;

                if (!$candidate) {
                    $promptFeedback = $json['promptFeedback'] ?? null;
                    if (!empty($promptFeedback['blockReason'])) {
                        throw new Exception('Content processing was blocked: ' . $promptFeedback['blockReason']);
                    }
                    throw new Exception('No candidate response returned from Gemini.');
                }

                $text = $candidate['content']['parts'][0]['text'] ?? '';
                if (empty($text)) {
                    throw new Exception('Gemini returned an empty text response.');
                }

                // Parse the output as JSON
                return $this->parseJsonResponse($text);
            } else {
                $errJson = json_decode($response['body'], true);
                $msg = $errJson['error']['message'] ?? ($response['error'] ?: 'HTTP ' . $response['status']);
                $lastError = $msg;

                if ($response['status'] !== 404) {
                    break;
                }
            }
        }

        throw new Exception('Gemini API Error: ' . $lastError);
    }

    /**
     * Parse and clean JSON string from Gemini response
     */
    private function parseJsonResponse(string $text): array {
        $clean = trim($text);

        // Strip markdown code fences if model enclosed in ```json ... ```
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
            $clean = trim($clean);
        }

        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // If direct parse fails, try extracting the first {...} block
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $fallback = json_decode($matches[0], true);
            if (is_array($fallback)) {
                return $fallback;
            }
        }

        throw new Exception('Failed to parse Gemini response as JSON. Raw text: ' . substr($text, 0, 500));
    }

    /**
     * Normalize the parsed result to ensure all 7 sections and transcription exist
     */
    private function normalizeResult(array $data, string $audioLang, string $summaryLang): array {
        $normalized = [
            'detected_language' => !empty($data['detected_language']) ? (string)$data['detected_language'] : 'Auto Detected',
            'summary_language' => match($summaryLang) {
                'en' => 'English',
                'ms' => 'Malay',
                default => 'Chinese'
            },
            'transcription' => trim((string)($data['transcription'] ?? 'No transcription generated.')),
            'overall_summary' => trim((string)($data['overall_summary'] ?? 'No summary generated.')),
            'main_topics' => $this->ensureArray($data['main_topics'] ?? []),
            'important_points' => $this->ensureArray($data['important_points'] ?? []),
            'key_metrics' => $this->ensureArray($data['key_metrics'] ?? []),
            'decisions' => $this->ensureArray($data['decisions'] ?? []),
            'action_items' => $this->normalizeActionItems($data['action_items'] ?? []),
            'high_level_synthesis' => trim((string)($data['high_level_synthesis'] ?? ''))
        ];

        return $normalized;
    }

    private function ensureArray(mixed $val): array {
        if (is_array($val)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $val))));
        }
        if (is_string($val) && trim($val) !== '') {
            return [trim($val)];
        }
        return [];
    }

    private function normalizeActionItems(mixed $items): array {
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $task = trim((string)($item['task'] ?? $item['action'] ?? $item['description'] ?? ''));
                if ($task === '') continue;
                $result[] = [
                    'task' => $task,
                    'assignee' => trim((string)($item['assignee'] ?? $item['owner'] ?? 'Not specified')),
                    'deadline' => trim((string)($item['deadline'] ?? $item['due_date'] ?? 'Not specified'))
                ];
            } elseif (is_string($item) && trim($item) !== '') {
                $result[] = [
                    'task' => trim($item),
                    'assignee' => 'Not specified',
                    'deadline' => 'Not specified'
                ];
            }
        }
        return $result;
    }

    /**
     * cURL Execution Helper
     */
    private function executeCurl(string $url, string $method = 'GET', mixed $data = null, array $headers = [], int $timeout = 60): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data !== null) {
            $payload = is_array($data) ? json_encode($data) : $data;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => $body ?: '',
            'error' => $error
        ];
    }

    /**
     * cURL Execution with Response Header Capture (for Resumable Upload)
     */
    private function executeCurlWithHeaders(string $url, string $method = 'POST', mixed $data = null, array $headers = [], int $timeout = 60): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in output
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data !== null) {
            $payload = is_array($data) ? json_encode($data) : $data;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        $headerText = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $headerText) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = strtolower(trim($parts[0]));
                $parsedHeaders[$key] = trim($parts[1]);
                $parsedHeaders[trim($parts[0])] = trim($parts[1]);
            }
        }

        return [
            'status' => $status,
            'body' => $body ?: '',
            'response_headers' => $parsedHeaders,
            'error' => $error
        ];
    }
}
