function displayExtractionStatus(statusElement, message, state = 'success') {
    if (! (statusElement instanceof HTMLElement)) return;

    const isError = state === true || state === 'error';
    const isWarning = state === 'warning';

    statusElement.textContent = message;
    statusElement.classList.toggle('hidden', message === '');
    statusElement.classList.toggle('text-red-700', isError);
    statusElement.classList.toggle('dark:text-red-300', isError);
    statusElement.classList.toggle('text-amber-700', isWarning);
    statusElement.classList.toggle('dark:text-amber-300', isWarning);
    statusElement.classList.toggle('text-green-700', !isError && !isWarning);
    statusElement.classList.toggle('dark:text-green-300', !isError && !isWarning);
}

function setEmptyField(form, name, value) {
    if (value === null || value === undefined || value === '') return false;

    const field = form.elements.namedItem(name);

    if (! (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return false;
    }

    if (field.value !== '') return false;

    field.value = String(value);
    field.dispatchEvent(new Event('input', { bubbles: true }));

    return true;
}

function clearField(form, name) {
    const field = form.elements.namedItem(name);

    if (! (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return false;
    }

    if (field.value === '') return false;

    field.value = '';
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));

    return true;
}

function renderExtractionList(listElement, items, emptyMessage) {
    if (! (listElement instanceof HTMLElement)) return;

    listElement.replaceChildren();

    const values = Array.isArray(items)
        ? items.filter((item) => typeof item === 'string' && item !== '')
        : [];

    if (values.length === 0) {
        const item = document.createElement('li');
        item.className = 'text-gray-400 dark:text-slate-500';
        item.textContent = emptyMessage;
        listElement.append(item);

        return;
    }

    values.forEach((value) => {
        const item = document.createElement('li');
        item.textContent = value;
        listElement.append(item);
    });
}

function initializeResearchCallImageExtractors() {
    document.querySelectorAll('[data-research-call-form]').forEach((form) => {
        if (! (form instanceof HTMLFormElement) || form.dataset.researchCallImageReady === 'true') return;

        form.dataset.researchCallImageReady = 'true';

        const imageInput = form.querySelector('[data-research-call-image]');
        const dropzone = form.querySelector('[data-research-call-image-dropzone]');
        const preview = form.querySelector('[data-research-call-image-preview]');
        const emptyState = form.querySelector('[data-research-call-image-empty]');
        const imageName = form.querySelector('[data-research-call-image-name]');
        const extractButton = form.querySelector('[data-research-call-extract]');
        const statusElement = form.querySelector('[data-research-call-image-status]');
        const extractSpinner = form.querySelector('[data-research-call-extract-spinner]');
        const extractLabel = form.querySelector('[data-research-call-extract-label]');
        const extractionSummary = form.querySelector('[data-research-call-extraction-summary]');
        const detectedFields = form.querySelector('[data-research-call-detected-fields]');
        const missingFields = form.querySelector('[data-research-call-missing-fields]');
        const warningSection = form.querySelector('[data-research-call-warning-section]');
        const warningList = form.querySelector('[data-research-call-warning-list]');
        const expiredScheduleSection = form.querySelector('[data-research-call-expired-schedule-section]');
        const expiredScheduleWarning = form.querySelector('[data-research-call-expired-schedule-warning]');
        const clearScheduleSection = form.querySelector('[data-research-call-clear-schedule-section]');
        const clearScheduleButton = form.querySelector('[data-research-call-clear-schedule]');
        const transcriptionSection = form.querySelector('[data-research-call-transcription-section]');
        const transcriptionText = form.querySelector('[data-research-call-transcription]');

        if (! (imageInput instanceof HTMLInputElement) || ! (extractButton instanceof HTMLButtonElement)) return;

        let previewUrl = null;
        let activeRequest = null;
        let extractedScheduleFields = new Set();

        const supportedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const maximumImageSize = 10 * 1024 * 1024;
        const scheduleFieldNames = new Set([
            'opens_at',
            'closes_at',
            'initial_evaluation_start_date',
            'initial_evaluation_end_date',
            'paper_revisions_start_date',
            'paper_revisions_end_date',
            'lrec_start_date',
            'lrec_end_date',
            'implementation_start_date',
            'implementation_end_date',
        ]);

        const setExtracting = (isExtracting) => {
            extractButton.disabled = isExtracting || !imageInput.files?.[0];
            extractButton.setAttribute('aria-busy', String(isExtracting));
            extractSpinner?.classList.toggle('hidden', !isExtracting);

            if (extractLabel instanceof HTMLElement) {
                extractLabel.textContent = isExtracting ? 'Reading poster...' : 'Read image';
            }
        };

        const resetExtractionSummary = () => {
            extractionSummary?.classList.add('hidden');
            warningSection?.classList.add('hidden');
            expiredScheduleSection?.classList.add('hidden');
            transcriptionSection?.classList.add('hidden');

            if (transcriptionText instanceof HTMLElement) transcriptionText.textContent = '';
        };

        const updateClearScheduleAvailability = () => {
            clearScheduleSection?.classList.toggle('hidden', extractedScheduleFields.size === 0);
        };

        const showExtractionSummary = (payload) => {
            const detected = Array.isArray(payload.detected_fields) ? payload.detected_fields : [];
            const missing = Array.isArray(payload.missing_fields) ? payload.missing_fields : [];
            const warnings = Array.isArray(payload.warnings) ? payload.warnings : [];
            const transcription = typeof payload.transcription === 'string' ? payload.transcription.trim() : '';
            const expiredScheduleWarningText = typeof payload.expired_schedule_warning === 'string'
                ? payload.expired_schedule_warning
                : '';

            renderExtractionList(detectedFields, detected, 'No form sections were confidently detected.');
            renderExtractionList(missingFields, missing, 'All listed form sections were detected.');
            renderExtractionList(warningList, warnings, '');

            warningSection?.classList.toggle('hidden', warnings.length === 0);
            expiredScheduleSection?.classList.toggle('hidden', expiredScheduleWarningText === '');
            transcriptionSection?.classList.toggle('hidden', transcription === '');

            if (transcriptionText instanceof HTMLElement) transcriptionText.textContent = transcription;
            if (expiredScheduleWarning instanceof HTMLElement) expiredScheduleWarning.textContent = expiredScheduleWarningText;

            extractionSummary?.classList.remove('hidden');
        };

        const setDropzoneActive = (isActive) => {
            dropzone?.classList.toggle('border-red-500', isActive);
            dropzone?.classList.toggle('bg-red-50', isActive);
            dropzone?.classList.toggle('dark:border-red-600', isActive);
            dropzone?.classList.toggle('dark:bg-red-950/40', isActive);
        };

        const updatePreview = (image) => {
            if (! (preview instanceof HTMLImageElement)) return;

            if (previewUrl) URL.revokeObjectURL(previewUrl);

            previewUrl = URL.createObjectURL(image);
            preview.src = previewUrl;
            preview.classList.remove('hidden');
            emptyState?.classList.add('hidden');

            if (imageName instanceof HTMLElement) imageName.textContent = image.name;
        };

        const readDroppedFile = (image) => {
            if (!image) return;

            if (!supportedImageTypes.includes(image.type)) {
                displayExtractionStatus(statusElement, 'Please choose a JPG, PNG, or WebP image.', true);

                return;
            }

            if (image.size > maximumImageSize) {
                displayExtractionStatus(statusElement, 'The poster image may not be larger than 10 MB.', true);

                return;
            }

            try {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(image);
                imageInput.files = dataTransfer.files;
                imageInput.dispatchEvent(new Event('change', { bubbles: true }));
            } catch {
                displayExtractionStatus(statusElement, 'This browser could not attach the dropped image. Please use the browse option.', true);
            }
        };

        const extractImage = async () => {
            const image = imageInput.files?.[0];

            if (!image) {
                displayExtractionStatus(statusElement, 'Choose a poster image first.', true);

                return;
            }

            if (activeRequest instanceof AbortController) return;

            const formData = new FormData();
            formData.append('reference_image', image);
            activeRequest = new AbortController();
            const request = activeRequest;
            setExtracting(true);
            resetExtractionSummary();
            displayExtractionStatus(statusElement, 'Reading the poster...');

            try {
                const response = await fetch(form.dataset.extractUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: formData,
                    signal: request.signal,
                });
                const payload = await response.json();

                if (!response.ok) {
                    const validationMessage = Object.values(payload.errors || {}).flat().join(' ');

                    throw new Error(validationMessage || payload.message || 'The poster could not be read.');
                }

                const { maximum_budget: detectedBudget, ...values } = payload.fields || {};
                const populatedFields = Object.entries(values)
                    .filter(([name, value]) => setEmptyField(form, name, value))
                    .map(([name]) => name);
                const filledCount = populatedFields.length;

                populatedFields
                    .filter((name) => scheduleFieldNames.has(name))
                    .forEach((name) => extractedScheduleFields.add(name));
                updateClearScheduleAvailability();
                const warnings = Array.isArray(payload.warnings) ? payload.warnings : [];
                const detectedCount = Array.isArray(payload.detected_fields) ? payload.detected_fields.length : 0;
                const budgetNotice = detectedBudget === null || detectedBudget === undefined
                    ? ''
                    : ` Poster budget detected: PHP ${Number(detectedBudget).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`;

                showExtractionSummary(payload);

                displayExtractionStatus(
                    statusElement,
                    filledCount > 0
                        ? `${filledCount} blank field${filledCount === 1 ? '' : 's'} filled; ${detectedCount} poster section${detectedCount === 1 ? '' : 's'} detected. Review the summary before saving.${budgetNotice}`
                        : `Poster read. Existing entries were kept; review the detection summary before saving.${budgetNotice}`,
                    warnings.length > 0 ? 'warning' : 'success',
                );
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') return;

                displayExtractionStatus(
                    statusElement,
                    error instanceof Error ? error.message : 'The poster could not be read. You can complete the form manually.',
                    true,
                );
            } finally {
                if (activeRequest === request) {
                    activeRequest = null;
                    setExtracting(false);
                }
            }
        };

        imageInput.addEventListener('change', () => {
            const image = imageInput.files?.[0];

            if (activeRequest instanceof AbortController) {
                activeRequest.abort();
                activeRequest = null;
            }

            extractedScheduleFields = new Set();
            updateClearScheduleAvailability();
            resetExtractionSummary();

            if (!image) {
                setExtracting(false);

                return;
            }

            if (!supportedImageTypes.includes(image.type)) {
                imageInput.value = '';
                setExtracting(false);
                displayExtractionStatus(statusElement, 'Please choose a JPG, PNG, or WebP image.', true);

                return;
            }

            if (image.size > maximumImageSize) {
                imageInput.value = '';
                setExtracting(false);
                displayExtractionStatus(statusElement, 'The poster image may not be larger than 10 MB.', true);

                return;
            }

            updatePreview(image);
            setExtracting(false);
            displayExtractionStatus(statusElement, 'Poster selected. Click Read image when you are ready.');
        });
        extractButton.addEventListener('click', () => void extractImage());
        clearScheduleButton?.addEventListener('click', () => {
            if (extractedScheduleFields.size === 0) return;

            if (!window.confirm('Clear the schedule dates copied from this poster? Your call details and guidelines will stay unchanged.')) {
                return;
            }

            extractedScheduleFields.forEach((fieldName) => clearField(form, fieldName));
            extractedScheduleFields = new Set();
            updateClearScheduleAvailability();
            displayExtractionStatus(statusElement, 'The extracted schedule was cleared. Enter the current schedule before publishing.', 'warning');
        });

        setExtracting(false);
        updateClearScheduleAvailability();

        dropzone?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;

            event.preventDefault();
            imageInput.click();
        });

        dropzone?.addEventListener('dragenter', (event) => {
            event.preventDefault();
            setDropzoneActive(true);
        });

        dropzone?.addEventListener('dragover', (event) => {
            event.preventDefault();
            setDropzoneActive(true);
        });

        dropzone?.addEventListener('dragleave', (event) => {
            if (! (event.relatedTarget instanceof Node) || ! dropzone.contains(event.relatedTarget)) {
                setDropzoneActive(false);
            }
        });

        dropzone?.addEventListener('drop', (event) => {
            event.preventDefault();
            setDropzoneActive(false);
            readDroppedFile(event.dataTransfer?.files?.[0]);
        });

        form.addEventListener('paste', (event) => {
            const imageItem = [...(event.clipboardData?.items || [])]
                .find((item) => item.type.startsWith('image/'));
            const pastedImage = imageItem?.getAsFile();

            if (!pastedImage) return;

            event.preventDefault();
            readDroppedFile(pastedImage);
        });
    });
}

export default initializeResearchCallImageExtractors;
