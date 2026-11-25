<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Security.php';

$csrfToken = Security::getCsrfToken();

// Load current configuration
$geminiEndpoint = Security::escape(env('GEMINI_ENDPOINT', DEFAULT_GEMINI_ENDPOINT));
$geminiModel = Security::escape(env('GEMINI_MODEL', DEFAULT_GEMINI_MODEL));
$hasGeminiKey = !empty(env('GEMINI_API_KEY'));

$agnesEndpoint = Security::escape(env('AGNES_ENDPOINT', DEFAULT_AGNES_ENDPOINT));
$agnesModel = Security::escape(env('AGNES_MODEL', DEFAULT_AGNES_MODEL));
$hasAgnesKey = !empty(env('AGNES_API_KEY'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Voice Recording Summarizer | Executive Intelligence</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Custom Glassmorphism Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Hidden CSRF Token -->
    <input type="hidden" id="csrfToken" value="<?= $csrfToken ?>">

    <!-- Top Navigation Bar -->
    <nav class="app-navbar py-2 px-3">
        <div class="app-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="brand-icon">
                    <i class="fa-solid fa-microphone-lines"></i>
                </div>
                <div>
                    <span class="brand-title">AI Voice Summarizer</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- AI Engine Ready Indicator -->
                <div class="badge-engine-ready d-none d-sm-inline-flex">
                    <span class="pulse-dot"></span>
                    <span>AI Engine Ready</span>
                </div>

                <!-- Stateless Realtime Badge -->
                <div class="badge-stateless d-none d-md-inline-block">
                    <i class="fa-solid fa-bolt-lightning me-1"></i> Stateless Realtime
                </div>

                <!-- Settings Button -->
                <button class="btn btn-nav-settings" type="button" data-bs-toggle="collapse" data-bs-target="#settingsPanel" aria-expanded="false" aria-controls="settingsPanel" id="settingsToggleBtn">
                    <i class="fa-solid fa-sliders me-1"></i> Settings
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="container py-4 flex-grow-1">
        <div class="app-container">

            <!-- Collapsible AI Settings Section -->
            <div class="collapse mb-4" id="settingsPanel">
                <div class="glass-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-sliders text-primary me-2"></i>AI Provider Configurations
                        </h5>
                        <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#settingsPanel" aria-label="Close"></button>
                    </div>

                    <div class="row g-4">
                        <!-- Provider 1: Google Gemini AI (Primary Engine) -->
                        <div class="col-lg-6">
                            <div class="p-3 bg-white rounded-3 border h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold text-primary mb-0">
                                        <i class="fa-brands fa-google me-1"></i> Google Gemini AI (Primary)
                                    </h6>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Core Engine</span>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom" for="geminiEndpointInput">
                                        API Base Endpoint
                                    </label>
                                    <input type="text" class="form-control form-control-custom" id="geminiEndpointInput" value="<?= $geminiEndpoint ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom" for="geminiModelSelect">
                                        Gemini Model
                                    </label>
                                    <select class="form-select form-select-custom" id="geminiModelSelect">
                                        <option value="gemini-3.5-flash-lite" <?= $geminiModel === 'gemini-3.5-flash-lite' ? 'selected' : '' ?>>gemini-3.5-flash-lite (Recommended)</option>
                                        <option value="gemini-3.1-flash-lite" <?= $geminiModel === 'gemini-3.1-flash-lite' ? 'selected' : '' ?>>gemini-3.1-flash-lite</option>
                                        <option value="gemini-2.0-flash" <?= $geminiModel === 'gemini-2.0-flash' ? 'selected' : '' ?>>gemini-2.0-flash</option>
                                        <option value="custom" <?= !in_array($geminiModel, ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-2.0-flash']) ? 'selected' : '' ?>>Custom Model Name</option>
                                    </select>
                                    <div class="mt-2 <?= in_array($geminiModel, ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-2.0-flash']) ? 'd-none' : '' ?>" id="geminiCustomModelWrapper">
                                        <input type="text" class="form-control form-control-custom" id="geminiCustomModelInput" placeholder="e.g. gemini-2.5-flash" value="<?= !in_array($geminiModel, ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-2.0-flash']) ? $geminiModel : '' ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom" for="geminiApiKeyInput">
                                        Gemini API Key
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-custom" id="geminiApiKeyInput" placeholder="<?= $hasGeminiKey ? '••••••••••••••••' : 'Enter your Gemini API Key' ?>" autocomplete="off">
                                        <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="geminiApiKeyInput" title="Toggle visibility">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnTestGemini">
                                        <i class="fa-solid fa-plug me-1"></i> Test Gemini Connection
                                    </button>
                                </div>
                                <div id="geminiTestFeedback" class="mt-2 small" style="min-height: 20px;"></div>
                            </div>
                        </div>

                        <!-- Provider 2: Agnes AI (Secondary Configuration) -->
                        <div class="col-lg-6">
                            <div class="p-3 bg-white rounded-3 border h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold text-secondary mb-0">
                                        <i class="fa-solid fa-robot me-1"></i> Agnes AI (Secondary Provider)
                                    </h6>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Configurable</span>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom" for="agnesEndpointInput">
                                        Agnes API Endpoint
                                    </label>
                                    <input type="text" class="form-control form-control-custom" id="agnesEndpointInput" value="<?= $agnesEndpoint ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom" for="agnesModelSelect">
                                        Agnes AI Model
                                    </label>
                                    <select class="form-select form-select-custom" id="agnesModelSelect">
                                        <option value="agnes-3.0-flash" <?= $agnesModel === 'agnes-3.0-flash' ? 'selected' : '' ?>>agnes-3.0-flash (Default)</option>
                                        <option value="agnes-2.5-flash" <?= $agnesModel === 'agnes-2.5-flash' ? 'selected' : '' ?>>agnes-2.5-flash</option>
                                        <option value="custom" <?= !in_array($agnesModel, ['agnes-3.0-flash', 'agnes-2.5-flash']) ? 'selected' : '' ?>>Custom Model Name</option>
                                    </select>
                                    <div class="mt-2 <?= in_array($agnesModel, ['agnes-3.0-flash', 'agnes-2.5-flash']) ? 'd-none' : '' ?>" id="agnesCustomModelWrapper">
                                        <input type="text" class="form-control form-control-custom" id="agnesCustomModelInput" placeholder="e.g. agnes-custom-model" value="<?= !in_array($agnesModel, ['agnes-3.0-flash', 'agnes-2.5-flash']) ? $agnesModel : '' ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom" for="agnesApiKeyInput">
                                        Agnes API Key
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-custom" id="agnesApiKeyInput" placeholder="<?= $hasAgnesKey ? '••••••••••••••••' : 'Enter your Agnes API Key' ?>" autocomplete="off">
                                        <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="agnesApiKeyInput" title="Toggle visibility">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTestAgnes">
                                        <i class="fa-solid fa-plug me-1"></i> Test Agnes AI Connection
                                    </button>
                                </div>
                                <div id="agnesTestFeedback" class="mt-2 small" style="min-height: 20px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Settings Button -->
                    <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top flex-wrap gap-2">
                        <div class="text-muted small">
                            <i class="fa-solid fa-shield-halved text-success me-1"></i> Settings saved to server (.env) are encrypted &amp; protected against unauthorized web access.
                        </div>
                        <button type="button" class="btn btn-primary px-4 fw-semibold" id="btnSaveSettings">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Settings to Server (.env)
                        </button>
                    </div>
                    <div id="saveSettingsFeedback" class="mt-2 text-end"></div>
                </div>
            </div>

            <!-- Main Introduction Section -->
            <div class="text-center py-4 mb-4">
                <h1 class="hero-title mb-3">
                    Transform Voice &amp; Video into Executive Intelligence
                </h1>
                <p class="hero-subtitle">
                    Transcribe meeting recordings, technical lectures, or bilingual audio with high-precision AI. Generate comprehensive multi-section summaries and PDF reports.
                </p>
            </div>

            <!-- Audio Upload & Processing Card -->
            <div class="glass-card p-4 p-md-5 mb-4" id="uploadSectionCard">
                <!-- Drag and Drop Area -->
                <div class="upload-zone" id="dropZone">
                    <input type="file" id="fileInput" class="d-none" accept=".mp3,.wav,.m4a,.aac,.flac,.ogg,.wma,.mp4,.wmv,audio/*,video/mp4,video/x-ms-wmv">
                    
                    <div class="upload-icon-wrapper">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>

                    <h5 class="fw-bold text-dark mb-2">
                        Drag and drop your recording here or
                    </h5>

                    <button type="button" class="btn btn-outline-primary px-4 py-2 fw-semibold rounded-pill mb-3" id="btnBrowseFile">
                        <i class="fa-solid fa-folder-open me-1"></i> Browse File
                    </button>

                    <div class="text-muted small mb-2">
                        Supported Audio &amp; Video Formats (Max 250MB):
                    </div>
                    <div>
                        <span class="format-pill">MP3</span>
                        <span class="format-pill">WAV</span>
                        <span class="format-pill">M4A</span>
                        <span class="format-pill">AAC</span>
                        <span class="format-pill">FLAC</span>
                        <span class="format-pill">OGG</span>
                        <span class="format-pill">WMA</span>
                        <span class="format-pill">MP4</span>
                        <span class="format-pill">WMV</span>
                    </div>
                </div>

                <!-- Selected File Preview Card (Hidden until file selected) -->
                <div class="file-preview-card mt-3 d-none" id="filePreviewCard">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="file-preview-icon" id="fileTypeIcon">
                                <i class="fa-solid fa-file-audio"></i>
                            </div>
                            <div>
                                <div class="file-name-text" id="previewFilename">recording.mp3</div>
                                <div class="file-meta-text mt-1">
                                    <span id="previewFileSize">0 MB</span> • 
                                    <span id="previewDuration"><i class="fa-regular fa-clock me-1"></i>Analyzing duration...</span> • 
                                    <span class="badge bg-light text-dark border" id="previewFileType">Audio</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveFile">
                            <i class="fa-solid fa-trash-can me-1"></i> Remove
                        </button>
                    </div>

                    <!-- Audio Player Preview -->
                    <div id="audioPlayerWrapper" class="mt-2">
                        <audio id="audioPreview" controls class="custom-audio-player w-100"></audio>
                    </div>
                </div>

                <!-- Language Configuration -->
                <div class="row g-3 mt-3 pt-3 border-top">
                    <!-- Spoken Audio Language -->
                    <div class="col-md-6">
                        <label class="form-label-custom" for="audioLanguageSelect">
                            <i class="fa-solid fa-headset text-primary"></i> Audio Language
                        </label>
                        <select class="form-select form-select-custom" id="audioLanguageSelect">
                            <option value="auto" selected>Auto Detect</option>
                            <option value="zh">Chinese</option>
                            <option value="en">English</option>
                            <option value="ms">Malay</option>
                        </select>
                        <div class="form-text small text-muted">Supports multilingual speech &amp; code-switching.</div>
                    </div>

                    <!-- Summary Language -->
                    <div class="col-md-6">
                        <label class="form-label-custom" for="summaryLanguageSelect">
                            <i class="fa-solid fa-language text-primary"></i> Summary Language
                        </label>
                        <select class="form-select form-select-custom" id="summaryLanguageSelect">
                            <option value="zh" selected>Chinese</option>
                            <option value="en">English</option>
                            <option value="ms">Malay</option>
                        </select>
                        <div class="form-text small text-muted">Determines the language for all 7 summary sections.</div>
                    </div>
                </div>

                <!-- Process Button -->
                <div class="text-center mt-4">
                    <button type="button" class="btn-process-action" id="btnProcess" disabled>
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span>Analyze &amp; Summarize Recording</span>
                    </button>
                </div>
            </div>

            <!-- Processing Status & Stepper Card (Hidden initially) -->
            <div class="glass-card p-4 mb-4 d-none" id="processingStatusCard">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span id="processingStatusTitle">Processing Recording...</span>
                    </h6>
                    <span class="badge bg-primary px-3 py-2 fw-semibold" id="uploadPercentageBadge">0%</span>
                </div>

                <!-- Progress Bar -->
                <div class="progress mb-3" style="height: 8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="processingProgressBar" role="progressbar" style="width: 0%;"></div>
                </div>

                <!-- Processing Stepper (6 Steps) -->
                <div class="stepper-container">
                    <div class="stepper-progress-line" id="stepperProgressLine"></div>
                    
                    <div class="step-item" id="step1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Uploading</div>
                    </div>
                    <div class="step-item" id="step2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Preparing</div>
                    </div>
                    <div class="step-item" id="step3">
                        <div class="step-circle">3</div>
                        <div class="step-label">AI Analysis</div>
                    </div>
                    <div class="step-item" id="step4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Summarizing</div>
                    </div>
                    <div class="step-item" id="step5">
                        <div class="step-circle">5</div>
                        <div class="step-label">PDF Report</div>
                    </div>
                    <div class="step-item" id="step6">
                        <div class="step-circle">6</div>
                        <div class="step-label">Completed</div>
                    </div>
                </div>

                <div class="text-center text-muted small mt-2" id="processingStatusDetail">
                    Uploading your audio recording securely to the server...
                </div>
            </div>

            <!-- Results Section (Hidden initially) -->
            <div class="glass-card p-4 p-md-5 mb-5 d-none" id="resultsSection">
                <!-- Action Buttons Bar -->
                <div class="results-header-bar">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">
                            <i class="fa-solid fa-file-invoice text-primary me-2"></i>Executive Summary Report
                        </h4>
                        <div class="text-muted small">Generated with Google Gemini Multimodal Intelligence</div>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Copy Summary -->
                        <button type="button" class="btn btn-sm btn-outline-primary fw-semibold px-3 py-2" id="btnCopySummary">
                            <i class="fa-regular fa-copy me-1"></i> Copy Summary
                        </button>
                        
                        <!-- New Audio -->
                        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold px-3 py-2" id="btnNewAudio">
                            <i class="fa-solid fa-rotate-right me-1"></i> New Audio
                        </button>

                        <!-- Download PDF -->
                        <a href="#" class="btn btn-sm btn-primary fw-semibold px-3 py-2" id="btnDownloadPdf" download>
                            <i class="fa-solid fa-file-pdf me-1"></i> Download PDF
                        </a>
                    </div>
                </div>

                <!-- Result Metadata Bar -->
                <div class="d-flex flex-wrap gap-2 mb-4" id="resultMetadataBar">
                    <span class="result-badge-meta" id="resMetaFile"><i class="fa-solid fa-file-audio text-primary"></i> <span class="val">-</span></span>
                    <span class="result-badge-meta" id="resMetaDuration"><i class="fa-regular fa-clock text-info"></i> <span class="val">-</span></span>
                    <span class="result-badge-meta" id="resMetaSize"><i class="fa-solid fa-database text-secondary"></i> <span class="val">-</span></span>
                    <span class="result-badge-meta" id="resMetaLang"><i class="fa-solid fa-language text-success"></i> <span class="val">-</span></span>
                    <span class="result-badge-meta" id="resMetaTime"><i class="fa-regular fa-calendar-check text-muted"></i> <span class="val">-</span></span>
                </div>

                <!-- Section 1: Overall Summary -->
                <div class="section-block">
                    <div class="section-block-title">
                        <span class="icon-badge icon-blue"><i class="fa-solid fa-compass"></i></span>
                        <span>1. Overall Summary</span>
                    </div>
                    <div id="sectionOverallSummary" class="text-dark lh-base" style="font-size: 0.98rem;"></div>
                </div>

                <!-- Section 2: Main Topics Discussed -->
                <div class="section-block">
                    <div class="section-block-title">
                        <span class="icon-badge icon-indigo"><i class="fa-solid fa-list-check"></i></span>
                        <span>2. Main Topics Discussed</span>
                    </div>
                    <div id="sectionMainTopics"></div>
                </div>

                <!-- Section 3: Important Points -->
                <div class="section-block">
                    <div class="section-block-title">
                        <span class="icon-badge icon-emerald"><i class="fa-solid fa-star"></i></span>
                        <span>3. Important Points</span>
                    </div>
                    <div id="sectionImportantPoints"></div>
                </div>

                <!-- Section 4: Key Metrics, References & Information -->
                <div class="section-block">
                    <div class="section-block-title">
                        <span class="icon-badge icon-amber"><i class="fa-solid fa-chart-pie"></i></span>
                        <span>4. Key Metrics, References &amp; Information</span>
                    </div>
                    <div id="sectionKeyMetrics"></div>
                </div>

                <!-- Section 5: Decisions & Conclusions -->
                <div class="section-block">
                    <div class="section-block-title">
                        <span class="icon-badge icon-purple"><i class="fa-solid fa-gavel"></i></span>
                        <span>5. Decisions &amp; Conclusions</span>
                    </div>
                    <div id="sectionDecisions"></div>
                </div>

                <!-- Section 6: Action Items & Next Steps -->
                <div class="section-block">
                    <div class="section-block-title">
                        <span class="icon-badge icon-teal"><i class="fa-solid fa-calendar-check"></i></span>
                        <span>6. Action Items &amp; Next Steps</span>
                    </div>
                    <div id="sectionActionItems"></div>
                </div>

                <!-- Section 7: High-Level Synthesis -->
                <div class="section-block">
                    <div class="section-block-title">
                        <span class="icon-badge icon-rose"><i class="fa-solid fa-rocket"></i></span>
                        <span>7. High-Level Synthesis</span>
                    </div>
                    <div class="synthesis-card" id="sectionHighLevelSynthesis"></div>
                </div>

                <!-- Complete Audio Transcription Accordion -->
                <div class="accordion accordion-custom mt-4" id="transcriptionAccordion">
                    <div class="accordion-item border rounded-3 overflow-hidden">
                        <h2 class="accordion-header" id="headingTranscription">
                            <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTranscription" aria-expanded="false" aria-controls="collapseTranscription">
                                <i class="fa-solid fa-align-left text-primary me-2"></i>
                                <span>Complete Audio Transcription (Whisper AI)</span>
                            </button>
                        </h2>
                        <div id="collapseTranscription" class="accordion-collapse collapse" aria-labelledby="headingTranscription" data-bs-parent="#transcriptionAccordion">
                            <div class="accordion-body bg-light p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted">Verbatim multilingual transcript captured by multimodal AI</span>
                                    <button type="button" class="btn btn-sm btn-outline-dark" id="btnCopyTranscript">
                                        <i class="fa-regular fa-copy me-1"></i> Copy Transcript
                                    </button>
                                </div>
                                <div class="transcription-box" id="transcriptionContent">
                                    No transcription available.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Toast Notification Container -->
    <div class="toast-container-custom">
        <div id="appToast" class="toast align-items-center text-white bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2" id="toastMessage">
                    <i class="fa-solid fa-check text-success"></i> Content copied to clipboard!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="app-footer text-center">
        <div class="app-container">
            <div class="fw-semibold text-dark mb-1">
                AI Voice Recording Summarizer
            </div>
            <div>
                Stateless Zero-Database Architecture • Powered by Google Gemini Multimodal Intelligence
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Main Client-Side Application JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
