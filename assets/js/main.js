/**
 * AI Voice Recording Summarizer - Main Application JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // State
    let selectedFile = null;
    let audioDuration = 'N/A';
    let currentObjectUrl = null;
    let currentSummaryData = null;

    // Elements
    const csrfToken = document.getElementById('csrfToken')?.value || '';
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const btnBrowseFile = document.getElementById('btnBrowseFile');
    const filePreviewCard = document.getElementById('filePreviewCard');
    const previewFilename = document.getElementById('previewFilename');
    const previewFileSize = document.getElementById('previewFileSize');
    const previewDuration = document.getElementById('previewDuration');
    const previewFileType = document.getElementById('previewFileType');
    const fileTypeIcon = document.getElementById('fileTypeIcon');
    const audioPreview = document.getElementById('audioPreview');
    const btnRemoveFile = document.getElementById('btnRemoveFile');
    const audioLanguageSelect = document.getElementById('audioLanguageSelect');
    const summaryLanguageSelect = document.getElementById('summaryLanguageSelect');
    const btnProcess = document.getElementById('btnProcess');

    // Settings Elements
    const geminiEndpointInput = document.getElementById('geminiEndpointInput');
    const geminiModelSelect = document.getElementById('geminiModelSelect');
    const geminiCustomModelWrapper = document.getElementById('geminiCustomModelWrapper');
    const geminiCustomModelInput = document.getElementById('geminiCustomModelInput');
    const geminiApiKeyInput = document.getElementById('geminiApiKeyInput');
    const btnTestGemini = document.getElementById('btnTestGemini');
    const geminiTestFeedback = document.getElementById('geminiTestFeedback');

    const agnesEndpointInput = document.getElementById('agnesEndpointInput');
    const agnesModelSelect = document.getElementById('agnesModelSelect');
    const agnesCustomModelWrapper = document.getElementById('agnesCustomModelWrapper');
    const agnesCustomModelInput = document.getElementById('agnesCustomModelInput');
    const agnesApiKeyInput = document.getElementById('agnesApiKeyInput');
    const btnTestAgnes = document.getElementById('btnTestAgnes');
    const agnesTestFeedback = document.getElementById('agnesTestFeedback');

    const btnSaveSettings = document.getElementById('btnSaveSettings');
    const saveSettingsFeedback = document.getElementById('saveSettingsFeedback');

    // Progress Elements
    const processingStatusCard = document.getElementById('processingStatusCard');
    const processingStatusTitle = document.getElementById('processingStatusTitle');
    const uploadPercentageBadge = document.getElementById('uploadPercentageBadge');
    const processingProgressBar = document.getElementById('processingProgressBar');
    const stepperProgressLine = document.getElementById('stepperProgressLine');
    const processingStatusDetail = document.getElementById('processingStatusDetail');

    // Results Elements
    const resultsSection = document.getElementById('resultsSection');
    const btnCopySummary = document.getElementById('btnCopySummary');
    const btnNewAudio = document.getElementById('btnNewAudio');
    const btnDownloadPdf = document.getElementById('btnDownloadPdf');
    const btnCopyTranscript = document.getElementById('btnCopyTranscript');
    const transcriptionContent = document.getElementById('transcriptionContent');

    // Summary Section Containers
    const sectionOverallSummary = document.getElementById('sectionOverallSummary');
    const sectionMainTopics = document.getElementById('sectionMainTopics');
    const sectionImportantPoints = document.getElementById('sectionImportantPoints');
    const sectionKeyMetrics = document.getElementById('sectionKeyMetrics');
    const sectionDecisions = document.getElementById('sectionDecisions');
    const sectionActionItems = document.getElementById('sectionActionItems');
    const sectionHighLevelSynthesis = document.getElementById('sectionHighLevelSynthesis');

    // Metadata items
    const resMetaFile = document.querySelector('#resMetaFile .val');
    const resMetaDuration = document.querySelector('#resMetaDuration .val');
    const resMetaSize = document.querySelector('#resMetaSize .val');
    const resMetaLang = document.querySelector('#resMetaLang .val');
    const resMetaTime = document.querySelector('#resMetaTime .val');

    // Toast
    const toastEl = document.getElementById('appToast');
    const toastMessage = document.getElementById('toastMessage');
    const bsToast = toastEl ? new bootstrap.Toast(toastEl, { delay: 3500 }) : null;

    function showToast(message, isSuccess = true) {
        if (!toastEl) return;
        toastMessage.innerHTML = isSuccess 
            ? `<i class="fa-solid fa-circle-check text-success"></i> ${escapeHtml(message)}`
            : `<i class="fa-solid fa-triangle-exclamation text-danger"></i> ${escapeHtml(message)}`;
        bsToast.show();
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) return '00:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        if (mins >= 60) {
            const hrs = Math.floor(mins / 60);
            const remMins = mins % 60;
            return `${hrs.toString().padStart(2, '0')}:${remMins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    // ==========================================
    // 1. LocalStorage & Settings Initialization
    // ==========================================
    function initLocalStorageSettings() {
        const localGeminiKey = localStorage.getItem('ai_voice_gemini_key');
        if (localGeminiKey && !geminiApiKeyInput.value) {
            geminiApiKeyInput.value = localGeminiKey;
        }

        const localAgnesKey = localStorage.getItem('ai_voice_agnes_key');
        if (localAgnesKey && !agnesApiKeyInput.value) {
            agnesApiKeyInput.value = localAgnesKey;
        }

        const localGeminiModel = localStorage.getItem('ai_voice_gemini_model');
        if (localGeminiModel && geminiModelSelect) {
            if ([...geminiModelSelect.options].some(o => o.value === localGeminiModel)) {
                geminiModelSelect.value = localGeminiModel;
            } else {
                geminiModelSelect.value = 'custom';
                geminiCustomModelWrapper.classList.remove('d-none');
                geminiCustomModelInput.value = localGeminiModel;
            }
        }
    }
    initLocalStorageSettings();

    // Toggle custom model inputs
    geminiModelSelect?.addEventListener('change', (e) => {
        if (e.target.value === 'custom') {
            geminiCustomModelWrapper.classList.remove('d-none');
            geminiCustomModelInput.focus();
        } else {
            geminiCustomModelWrapper.classList.add('d-none');
        }
    });

    agnesModelSelect?.addEventListener('change', (e) => {
        if (e.target.value === 'custom') {
            agnesCustomModelWrapper.classList.remove('d-none');
            agnesCustomModelInput.focus();
        } else {
            agnesCustomModelWrapper.classList.add('d-none');
        }
    });

    // Password visibility toggle
    document.querySelectorAll('.toggle-password-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Helper to get active model names
    function getActiveGeminiModel() {
        if (geminiModelSelect.value === 'custom') {
            return geminiCustomModelInput.value.trim() || 'gemini-3.5-flash-lite';
        }
        return geminiModelSelect.value;
    }

    function getActiveAgnesModel() {
        if (agnesModelSelect.value === 'custom') {
            return agnesCustomModelInput.value.trim() || 'agnes-3.0-flash';
        }
        return agnesModelSelect.value;
    }

    // ==========================================
    // 2. Settings API Testing & Saving
    // ==========================================
    btnTestGemini?.addEventListener('click', async () => {
        const apiKey = geminiApiKeyInput.value.trim();
        const model = getActiveGeminiModel();
        const endpoint = geminiEndpointInput.value.trim();

        geminiTestFeedback.innerHTML = `<span class="text-primary"><i class="fa-solid fa-spinner fa-spin me-1"></i> Testing Gemini connection...</span>`;
        btnTestGemini.disabled = true;

        try {
            const res = await fetch('api_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'test_gemini',
                    csrf_token: csrfToken,
                    gemini_api_key: apiKey,
                    gemini_model: model,
                    gemini_endpoint: endpoint
                })
            });

            const data = await res.json();
            if (data.success) {
                geminiTestFeedback.innerHTML = `<span class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> ${escapeHtml(data.message)} (${data.latency_ms}ms)</span>`;
            } else {
                geminiTestFeedback.innerHTML = `<span class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i> ${escapeHtml(data.error)}</span>`;
            }
        } catch (err) {
            geminiTestFeedback.innerHTML = `<span class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Request failed: ${escapeHtml(err.message)}</span>`;
        } finally {
            btnTestGemini.disabled = false;
        }
    });

    btnTestAgnes?.addEventListener('click', async () => {
        const apiKey = agnesApiKeyInput.value.trim();
        const model = getActiveAgnesModel();
        const endpoint = agnesEndpointInput.value.trim();

        agnesTestFeedback.innerHTML = `<span class="text-secondary"><i class="fa-solid fa-spinner fa-spin me-1"></i> Testing Agnes connection...</span>`;
        btnTestAgnes.disabled = true;

        try {
            const res = await fetch('api_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'test_agnes',
                    csrf_token: csrfToken,
                    agnes_api_key: apiKey,
                    agnes_model: model,
                    agnes_endpoint: endpoint
                })
            });

            const data = await res.json();
            if (data.success) {
                agnesTestFeedback.innerHTML = `<span class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> ${escapeHtml(data.message)} (${data.latency_ms}ms)</span>`;
            } else {
                agnesTestFeedback.innerHTML = `<span class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i> ${escapeHtml(data.error)}</span>`;
            }
        } catch (err) {
            agnesTestFeedback.innerHTML = `<span class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Request failed: ${escapeHtml(err.message)}</span>`;
        } finally {
            btnTestAgnes.disabled = false;
        }
    });

    btnSaveSettings?.addEventListener('click', async () => {
        const geminiKey = geminiApiKeyInput.value.trim();
        const geminiModel = getActiveGeminiModel();
        const geminiEndpoint = geminiEndpointInput.value.trim();

        const agnesKey = agnesApiKeyInput.value.trim();
        const agnesModel = getActiveAgnesModel();
        const agnesEndpoint = agnesEndpointInput.value.trim();

        btnSaveSettings.disabled = true;
        btnSaveSettings.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...`;
        saveSettingsFeedback.innerHTML = '';

        try {
            const res = await fetch('api_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'save_settings',
                    csrf_token: csrfToken,
                    gemini_api_key: geminiKey,
                    gemini_model: geminiModel,
                    gemini_endpoint: geminiEndpoint,
                    agnes_api_key: agnesKey,
                    agnes_model: agnesModel,
                    agnes_endpoint: agnesEndpoint
                })
            });

            const data = await res.json();
            if (data.success) {
                // Save to localStorage for convenience
                if (geminiKey && geminiKey !== '••••••••••••••••') {
                    localStorage.setItem('ai_voice_gemini_key', geminiKey);
                }
                if (agnesKey && agnesKey !== '••••••••••••••••') {
                    localStorage.setItem('ai_voice_agnes_key', agnesKey);
                }
                localStorage.setItem('ai_voice_gemini_model', geminiModel);

                saveSettingsFeedback.innerHTML = `<div class="alert alert-success py-2 px-3 small d-inline-block mb-0"><i class="fa-solid fa-circle-check me-1"></i> ${escapeHtml(data.message)}</div>`;
                showToast("Settings successfully saved to server (.env)!");
            } else {
                saveSettingsFeedback.innerHTML = `<div class="alert alert-danger py-2 px-3 small d-inline-block mb-0"><i class="fa-solid fa-triangle-exclamation me-1"></i> ${escapeHtml(data.error)}</div>`;
            }
        } catch (err) {
            saveSettingsFeedback.innerHTML = `<div class="alert alert-danger py-2 px-3 small d-inline-block mb-0"><i class="fa-solid fa-triangle-exclamation me-1"></i> Error: ${escapeHtml(err.message)}</div>`;
        } finally {
            btnSaveSettings.disabled = false;
            btnSaveSettings.innerHTML = `<i class="fa-solid fa-floppy-disk me-1"></i> Save Settings to Server (.env)`;
        }
    });

    // ==========================================
    // 3. Drag & Drop and File Selection
    // ==========================================
    const allowedExtensions = ['mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg', 'wma', 'mp4', 'wmv'];
    const maxFileSize = 250 * 1024 * 1024; // 250MB

    btnBrowseFile?.addEventListener('click', () => fileInput.click());
    dropZone?.addEventListener('click', (e) => {
        if (e.target !== btnBrowseFile) {
            fileInput.click();
        }
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone?.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone?.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('dragover');
        });
    });

    dropZone?.addEventListener('drop', (e) => {
        const files = e.dataTransfer?.files;
        if (files && files.length > 0) {
            handleSelectedFile(files[0]);
        }
    });

    fileInput?.addEventListener('change', (e) => {
        if (fileInput.files && fileInput.files.length > 0) {
            handleSelectedFile(fileInput.files[0]);
        }
    });

    function handleSelectedFile(file) {
        if (!file) return;

        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(ext)) {
            alert(`Unsupported file format (.${ext}).\nSupported formats: MP3, WAV, M4A, AAC, FLAC, OGG, WMA, MP4, WMV`);
            return;
        }

        if (file.size > maxFileSize) {
            alert(`File exceeds the maximum limit of 250MB.\nSelected file size: ${formatBytes(file.size)}`);
            return;
        }

        if (file.size === 0) {
            alert('The selected file is empty (0 bytes).');
            return;
        }

        selectedFile = file;

        // UI Updates
        previewFilename.textContent = file.name;
        previewFileSize.textContent = formatBytes(file.size);
        previewDuration.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i>Calculating duration...`;

        const isVideo = ['mp4', 'wmv'].includes(ext);
        previewFileType.textContent = isVideo ? `Video (${ext.toUpperCase()})` : `Audio (${ext.toUpperCase()})`;
        fileTypeIcon.innerHTML = isVideo ? `<i class="fa-solid fa-file-video text-danger"></i>` : `<i class="fa-solid fa-file-audio text-primary"></i>`;

        // Clean up previous Object URL if existing
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
        }

        currentObjectUrl = URL.createObjectURL(file);
        audioPreview.src = currentObjectUrl;

        // Extract exact duration via HTML5 media element
        audioDuration = 'N/A';
        const tempMedia = document.createElement(isVideo ? 'video' : 'audio');
        tempMedia.src = currentObjectUrl;
        tempMedia.onloadedmetadata = () => {
            if (tempMedia.duration && !isNaN(tempMedia.duration)) {
                audioDuration = formatTime(tempMedia.duration);
                previewDuration.innerHTML = `<i class="fa-regular fa-clock me-1"></i>${audioDuration}`;
            } else {
                audioDuration = 'N/A';
                previewDuration.innerHTML = `<i class="fa-regular fa-clock me-1"></i>Duration unavailable`;
            }
        };
        tempMedia.onerror = () => {
            audioDuration = 'N/A';
            previewDuration.innerHTML = `<i class="fa-regular fa-clock me-1"></i>Duration unavailable`;
        };

        // Reveal preview card and enable CTA button
        filePreviewCard.classList.remove('d-none');
        dropZone.classList.add('d-none');
        btnProcess.disabled = false;
    }

    // Remove File Button
    btnRemoveFile?.addEventListener('click', () => {
        resetFileSelection();
    });

    function resetFileSelection() {
        selectedFile = null;
        audioDuration = 'N/A';
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }
        audioPreview.src = '';
        fileInput.value = '';
        filePreviewCard.classList.add('d-none');
        dropZone.classList.remove('d-none');
        btnProcess.disabled = true;
    }

    // ==========================================
    // 4. Audio Processing Workflow & Stepper
    // ==========================================
    const steps = [
        { id: 'step1', pct: 15, label: 'Uploading recording...' },
        { id: 'step2', pct: 30, label: 'Preparing recording...' },
        { id: 'step3', pct: 55, label: 'AI analysis with Google Gemini...' },
        { id: 'step4', pct: 80, label: 'Generating executive summaries...' },
        { id: 'step5', pct: 95, label: 'Compiling executive PDF report...' },
        { id: 'step6', pct: 100, label: 'Processing completed successfully!' }
    ];

    function setStepperStep(stepNumber) {
        steps.forEach((s, idx) => {
            const el = document.getElementById(s.id);
            if (!el) return;
            const currentIdx = stepNumber - 1;

            if (idx < currentIdx) {
                el.classList.add('completed');
                el.classList.remove('active');
            } else if (idx === currentIdx) {
                el.classList.add('active');
                el.classList.remove('completed');
            } else {
                el.classList.remove('active', 'completed');
            }
        });

        const activeStep = steps[stepNumber - 1];
        if (activeStep) {
            processingStatusTitle.textContent = activeStep.label;
            processingStatusDetail.textContent = activeStep.label;
            const linePct = Math.round(((stepNumber - 1) / (steps.length - 1)) * 100);
            stepperProgressLine.style.width = `${linePct}%`;
        }
    }

    btnProcess?.addEventListener('click', async () => {
        if (!selectedFile) return;

        // UI Reset
        btnProcess.disabled = true;
        resultsSection.classList.add('d-none');
        processingStatusCard.classList.remove('d-none');
        processingStatusCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        setStepperStep(1);
        processingProgressBar.style.width = '0%';
        uploadPercentageBadge.textContent = '0%';

        try {
            // STEP 1: Upload recording via XMLHttpRequest with progress tracking
            const uploadResult = await uploadFileWithProgress(selectedFile);
            if (!uploadResult.success) {
                throw new Error(uploadResult.error || 'Failed to upload audio recording.');
            }

            // STEP 2: Preparing recording
            setStepperStep(2);
            uploadPercentageBadge.textContent = '30%';
            processingProgressBar.style.width = '30%';
            await new Promise(r => setTimeout(r, 600));

            // STEP 3: AI Analysis with Gemini
            setStepperStep(3);
            uploadPercentageBadge.textContent = '55%';
            processingProgressBar.style.width = '55%';

            // Gather client overrides
            const clientApiKey = geminiApiKeyInput.value.trim();
            const clientModel = getActiveGeminiModel();
            const clientEndpoint = geminiEndpointInput.value.trim();

            const processParams = {
                csrf_token: csrfToken,
                temp_file: uploadResult.temp_file,
                original_name: selectedFile.name,
                audio_language: audioLanguageSelect.value,
                summary_language: summaryLanguageSelect.value,
                duration: audioDuration,
                gemini_api_key: clientApiKey,
                gemini_model: clientModel,
                gemini_endpoint: clientEndpoint
            };

            // Call process.php
            const response = await fetch('process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(processParams)
            });

            // STEP 4 & 5: Transition stepper while receiving data
            setStepperStep(4);
            processingProgressBar.style.width = '80%';
            uploadPercentageBadge.textContent = '80%';

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to process audio recording with Gemini AI.');
            }

            setStepperStep(5);
            processingProgressBar.style.width = '95%';
            uploadPercentageBadge.textContent = '95%';
            await new Promise(r => setTimeout(r, 400));

            // STEP 6: Completed
            setStepperStep(6);
            processingProgressBar.style.width = '100%';
            uploadPercentageBadge.textContent = '100%';

            currentSummaryData = data.data;

            // Render Results
            renderResults(data.data);

            // Hide processing card after brief moment and display results
            setTimeout(() => {
                processingStatusCard.classList.add('d-none');
                resultsSection.classList.remove('d-none');
                resultsSection.scrollIntoView({ behavior: 'smooth' });
                btnProcess.disabled = false;
            }, 800);

        } catch (err) {
            alert(`Processing Error:\n${err.message}`);
            processingStatusCard.classList.add('d-none');
            btnProcess.disabled = false;
        }
    });

    /**
     * Upload file with upload progress
     */
    function uploadFileWithProgress(file) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('recording', file);

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    // Map 0-100% upload to 0-30% on overall bar
                    const overallPercent = Math.round(percent * 0.3);
                    processingProgressBar.style.width = `${overallPercent}%`;
                    uploadPercentageBadge.textContent = `${percent}%`;
                }
            });

            xhr.addEventListener('load', () => {
                try {
                    const res = JSON.parse(xhr.responseText);
                    resolve(res);
                } catch (e) {
                    reject(new Error(`Server response error: ${xhr.responseText.substring(0, 300)}`));
                }
            });

            xhr.addEventListener('error', () => reject(new Error('Network connection error during file upload.')));
            xhr.addEventListener('abort', () => reject(new Error('File upload was cancelled.')));

            xhr.open('POST', 'upload.php');
            xhr.send(formData);
        });
    }

    // ==========================================
    // 5. Results Rendering
    // ==========================================
    function renderResults(data) {
        const meta = data.metadata || {};

        // Populate Metadata Bar
        resMetaFile.textContent = meta.original_name || 'recording.mp3';
        resMetaDuration.textContent = meta.duration || 'N/A';
        resMetaSize.textContent = meta.file_size || 'N/A';
        resMetaLang.textContent = `${data.detected_language} ➔ ${data.summary_language}`;
        resMetaTime.textContent = meta.processed_at || 'Just now';

        // Section 1: Overall Summary
        sectionOverallSummary.textContent = data.overall_summary || 'No overall summary generated.';

        // Section 2: Main Topics
        if (data.main_topics && data.main_topics.length > 0) {
            sectionMainTopics.innerHTML = data.main_topics.map(t => `
                <div class="summary-list-item">${escapeHtml(t)}</div>
            `).join('');
        } else {
            sectionMainTopics.innerHTML = `<div class="empty-state-notice">No distinct topics listed.</div>`;
        }

        // Section 3: Important Points
        if (data.important_points && data.important_points.length > 0) {
            sectionImportantPoints.innerHTML = data.important_points.map(p => `
                <div class="summary-list-item">${escapeHtml(p)}</div>
            `).join('');
        } else {
            sectionImportantPoints.innerHTML = `<div class="empty-state-notice">No important points listed.</div>`;
        }

        // Section 4: Key Metrics & References
        if (data.key_metrics && data.key_metrics.length > 0) {
            sectionKeyMetrics.innerHTML = data.key_metrics.map(m => `
                <div class="metric-card-item">
                    <i class="fa-solid fa-hashtag text-primary mt-1"></i>
                    <div>${escapeHtml(m)}</div>
                </div>
            `).join('');
        } else {
            sectionKeyMetrics.innerHTML = `<div class="empty-state-notice">No key metrics or specific references mentioned.</div>`;
        }

        // Section 5: Decisions & Conclusions
        if (data.decisions && data.decisions.length > 0) {
            sectionDecisions.innerHTML = data.decisions.map(d => `
                <div class="d-flex align-items-start gap-2 mb-2">
                    <i class="fa-solid fa-circle-check text-success mt-1"></i>
                    <div class="text-dark">${escapeHtml(d)}</div>
                </div>
            `).join('');
        } else {
            const emptyDecisionMsg = data.summary_language === 'Chinese'
                ? '本录音中未明确提及正式决策或最终结论。'
                : (data.summary_language === 'Malay' 
                    ? 'Tiada keputusan rasmi atau kesimpulan akhir dinyatakan dalam rakaman ini.'
                    : 'No formal decisions or final conclusions were explicitly stated in this recording.');
            sectionDecisions.innerHTML = `<div class="empty-state-notice"><i class="fa-solid fa-circle-info me-1"></i>${escapeHtml(emptyDecisionMsg)}</div>`;
        }

        // Section 6: Action Items & Next Steps
        if (data.action_items && data.action_items.length > 0) {
            sectionActionItems.innerHTML = data.action_items.map(item => {
                const task = typeof item === 'object' ? (item.task || '') : item;
                const assignee = typeof item === 'object' ? (item.assignee || 'Not specified') : 'Not specified';
                const deadline = typeof item === 'object' ? (item.deadline || 'Not specified') : 'Not specified';
                return `
                    <div class="action-item-card">
                        <div class="action-item-title"><i class="fa-regular fa-square-check text-teal me-1"></i> ${escapeHtml(task)}</div>
                        <div class="action-item-meta">
                            <span><i class="fa-solid fa-user me-1 text-secondary"></i> Assignee: <strong>${escapeHtml(assignee)}</strong></span>
                            <span><i class="fa-regular fa-calendar me-1 text-secondary"></i> Due: <strong>${escapeHtml(deadline)}</strong></span>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            const emptyActionMsg = data.summary_language === 'Chinese'
                ? '本录音中未明确指派具体的后续待办事项。'
                : (data.summary_language === 'Malay'
                    ? 'Tiada tindakan susulan atau tanggungjawab khusus dikenal pasti dalam rakaman ini.'
                    : 'No specific action items or responsibilities were identified in this recording.');
            sectionActionItems.innerHTML = `<div class="empty-state-notice"><i class="fa-solid fa-circle-info me-1"></i>${escapeHtml(emptyActionMsg)}</div>`;
        }

        // Section 7: High-Level Synthesis
        sectionHighLevelSynthesis.textContent = data.high_level_synthesis || 'No high-level synthesis provided.';

        // Complete Audio Transcription
        transcriptionContent.textContent = data.transcription || 'No transcription generated.';

        // Setup PDF download button
        if (data.report_file) {
            btnDownloadPdf.href = `download_pdf.php?file=${encodeURIComponent(data.report_file)}`;
        }
    }

    // ==========================================
    // 6. Action Buttons Handlers
    // ==========================================

    // Copy Summary
    btnCopySummary?.addEventListener('click', async () => {
        if (!currentSummaryData) return;

        const d = currentSummaryData;
        const textParts = [
            `========================================`,
            `AI VOICE RECORDING EXECUTIVE SUMMARY`,
            `Recording: ${d.metadata?.original_name || 'recording.mp3'}`,
            `Duration: ${d.metadata?.duration || 'N/A'} | Size: ${d.metadata?.file_size || 'N/A'}`,
            `Summary Language: ${d.summary_language || 'Chinese'}`,
            `Generated: ${d.metadata?.processed_at || new Date().toLocaleString()}`,
            `========================================\n`,
            `1. OVERALL SUMMARY`,
            `${d.overall_summary || 'N/A'}\n`,
            `2. MAIN TOPICS DISCUSSED`,
            (d.main_topics || []).map(t => `• ${t}`).join('\n') || 'None\n',
            `\n3. IMPORTANT POINTS`,
            (d.important_points || []).map(p => `• ${p}`).join('\n') || 'None\n',
            `\n4. KEY METRICS, REFERENCES & INFORMATION`,
            (d.key_metrics || []).map(m => `• ${m}`).join('\n') || 'None\n',
            `\n5. DECISIONS & CONCLUSIONS`,
            (d.decisions && d.decisions.length > 0) ? d.decisions.map(dc => `• ${dc}`).join('\n') : 'No formal decisions recorded.\n',
            `\n6. ACTION ITEMS & NEXT STEPS`,
            (d.action_items && d.action_items.length > 0) 
                ? d.action_items.map(a => `• [${a.assignee || 'Unassigned'}] ${a.task} (Due: ${a.deadline || 'N/A'})`).join('\n')
                : 'No action items identified.\n',
            `\n7. HIGH-LEVEL SYNTHESIS`,
            `${d.high_level_synthesis || 'N/A'}\n`,
            `========================================`
        ];

        const fullSummaryText = textParts.join('\n');

        try {
            await navigator.clipboard.writeText(fullSummaryText);
            showToast('Executive summary copied to clipboard!');
        } catch (err) {
            fallbackCopyText(fullSummaryText);
            showToast('Executive summary copied to clipboard!');
        }
    });

    // Copy Transcript
    btnCopyTranscript?.addEventListener('click', async () => {
        const text = transcriptionContent.textContent || '';
        try {
            await navigator.clipboard.writeText(text);
            showToast('Complete transcript copied to clipboard!');
        } catch (err) {
            fallbackCopyText(text);
            showToast('Complete transcript copied to clipboard!');
        }
    });

    function fallbackCopyText(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
    }

    // New Audio
    btnNewAudio?.addEventListener('click', () => {
        resultsSection.classList.add('d-none');
        resetFileSelection();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
