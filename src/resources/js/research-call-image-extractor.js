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

function pickerFieldParts(form, name) {
    const hiddenInput = form.elements.namedItem(name);

    if (! (hiddenInput instanceof HTMLInputElement)) return null;

    const pickerRoot = hiddenInput.closest('[x-data]');
    const displayInput = pickerRoot instanceof HTMLElement
        ? pickerRoot.querySelector('[x-ref="display"]')
        : null;

    return { hiddenInput, displayInput };
}

function parseSuggestionDate(value) {
    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatSuggestionClock(value) {
    if (typeof value !== 'string' || ! value.includes('T')) return '';

    const time = value.slice(11, 16);

    if (time === '00:00' || time === '23:59') return '';

    const parsed = parseSuggestionDate(value);

    return parsed
        ? ` at ${parsed.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`
        : '';
}

function formatSuggestionDay(value, includeYear) {
    const parsed = parseSuggestionDate(value);

    if (! parsed) return String(value);

    const sameYear = parsed.getFullYear() === new Date().getFullYear();
    const month = parsed.toLocaleDateString([], { month: 'short', day: 'numeric' });

    return includeYear || ! sameYear ? `${month}, ${parsed.getFullYear()}` : month;
}

function formatSuggestionLabel(entries) {
    if (entries.length === 0) return '';

    if (entries.length === 1 || entries[0].value === entries[1].value) {
        return formatSuggestionDay(entries[0].value, true) + formatSuggestionClock(entries[0].value);
    }

    const sameYear = entries.every((entry) => parseSuggestionDate(entry.value)?.getFullYear()
        === parseSuggestionDate(entries[0].value)?.getFullYear());

    return `${formatSuggestionDay(entries[0].value, ! sameYear)} – ${formatSuggestionDay(entries[1].value, true)}`;
}

function buildSuggestionContent(row, entries, { onAccept, onDismiss }) {
    row.replaceChildren();

    const wrapper = document.createElement('span');
    wrapper.className = 'inline-flex flex-wrap items-center gap-2 rounded-2xl border border-rose-100 bg-rose-50/80 px-3 py-1.5 text-xs dark:border-red-950 dark:bg-red-950/30';

    const sparkle = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    sparkle.setAttribute('class', 'h-3.5 w-3.5 shrink-0 text-[#7A0019] dark:text-red-300');
    sparkle.setAttribute('viewBox', '0 0 24 24');
    sparkle.setAttribute('fill', 'none');
    sparkle.setAttribute('stroke', 'currentColor');
    sparkle.setAttribute('stroke-width', '2');
    sparkle.setAttribute('aria-hidden', 'true');
    const sparklePath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    sparklePath.setAttribute('stroke-linecap', 'round');
    sparklePath.setAttribute('stroke-linejoin', 'round');
    sparklePath.setAttribute('d', 'M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z');
    sparkle.append(sparklePath);

    const label = document.createElement('span');
    label.className = 'font-bold text-gray-600 dark:text-slate-300';
    label.append('Suggested: ');

    const value = document.createElement('span');
    value.dataset.suggestionValue = 'true';
    value.className = 'font-black text-gray-900 dark:text-white';
    value.textContent = formatSuggestionLabel(entries);
    label.append(value);

    const acceptButton = document.createElement('button');
    acceptButton.type = 'button';
    acceptButton.className = 'inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 transition hover:bg-emerald-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-emerald-950 dark:text-emerald-300 dark:hover:bg-emerald-900';
    acceptButton.setAttribute('aria-label', 'Accept suggested date');
    acceptButton.title = 'Accept suggested date';
    acceptButton.append('✓');
    acceptButton.addEventListener('click', () => onAccept());

    const dismissButton = document.createElement('button');
    dismissButton.type = 'button';
    dismissButton.className = 'inline-flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-[#7A0019] transition hover:bg-rose-200 focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900';
    dismissButton.setAttribute('aria-label', 'Dismiss suggested date');
    dismissButton.title = 'Dismiss suggested date';
    dismissButton.append('✕');
    dismissButton.addEventListener('click', () => onDismiss());

    wrapper.append(sparkle, label, acceptButton, dismissButton);
    row.append(wrapper);
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
        const posterReadingLoadingScreen = form.querySelector('[data-research-call-poster-reading-loading]');
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
        const pendingSuggestions = new Map();
        const rejectedSuggestions = new Map();

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

        const suggestionRows = () => [...form.querySelectorAll('[data-research-call-suggestion]')]
            .filter((row) => row instanceof HTMLElement);

        const rowEntries = (row) => {
            if (! (row instanceof HTMLElement)) return [];

            return (row.dataset.suggestionFields || '')
                .split(',')
                .map((field) => field.trim())
                .filter((field) => field !== '' && pendingSuggestions.has(field))
                .map((field) => ({ field, value: pendingSuggestions.get(field) }));
        };

        const fieldIsEmpty = (name) => {
            const parts = pickerFieldParts(form, name);

            return ! parts || parts.hiddenInput.value === '';
        };

        const hideSuggestionRow = (row) => {
            rowEntries(row).forEach(({ field }) => pendingSuggestions.delete(field));
            row.replaceChildren();
            row.hidden = true;
        };

        const dismissPendingSuggestions = () => {
            suggestionRows().forEach((row) => hideSuggestionRow(row));
        };

        const acceptSuggestionRow = (row) => {
            const entries = rowEntries(row);

            if (entries.length === 0) return;

            entries.forEach(({ field }) => pendingSuggestions.delete(field));
            entries.forEach(({ field, value }) => {
                const parts = pickerFieldParts(form, field);

                if (! parts) return;

                parts.hiddenInput.value = value;
                parts.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                parts.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                extractedScheduleFields.add(field);
            });

            row.replaceChildren();
            row.hidden = true;
            updateClearScheduleAvailability();
        };

        const rejectSuggestionRow = (row) => {
            rowEntries(row).forEach(({ field, value }) => {
                pendingSuggestions.delete(field);

                if (! rejectedSuggestions.has(field)) rejectedSuggestions.set(field, new Set());
                rejectedSuggestions.get(field).add(value);
            });

            row.replaceChildren();
            row.hidden = true;
        };

        const renderSuggestions = (values) => {
            dismissPendingSuggestions();

            let suggestionCount = 0;

            suggestionRows().forEach((row) => {
                const fields = (row.dataset.suggestionFields || '')
                    .split(',')
                    .map((field) => field.trim())
                    .filter((field) => field !== '');

                const entries = fields
                    .filter((field) => {
                        const value = values[field];

                        return typeof value === 'string' && value !== ''
                            && fieldIsEmpty(field)
                            && ! rejectedSuggestions.get(field)?.has(value);
                    })
                    .map((field) => ({ field, value: values[field] }));

                if (entries.length === 0) {
                    row.replaceChildren();
                    row.hidden = true;

                    return;
                }

                entries.forEach(({ field, value }) => pendingSuggestions.set(field, value));
                suggestionCount += entries.length;

                buildSuggestionContent(row, entries, {
                    onAccept: () => acceptSuggestionRow(row),
                    onDismiss: () => rejectSuggestionRow(row),
                });
                row.hidden = false;
            });

            return suggestionCount;
        };

        form.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== 'Escape') return;

            const target = event.target;

            if (! (target instanceof Element)) return;

            const displayInput = target.closest('[x-ref="display"]');

            if (! (displayInput instanceof HTMLElement)) return;

            const hiddenInput = displayInput.closest('[x-data]')?.querySelector('input[type="hidden"][name]');

            if (! (hiddenInput instanceof HTMLInputElement) || ! pendingSuggestions.has(hiddenInput.name)) return;

            if (displayInput.getAttribute('aria-expanded') === 'true') return;

            const row = suggestionRows().find((candidate) => rowEntries(candidate).some(({ field }) => field === hiddenInput.name));

            if (! (row instanceof HTMLElement)) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            if (event.key === 'Enter') {
                acceptSuggestionRow(row);
            } else {
                rejectSuggestionRow(row);
            }
        }, true);

        form.addEventListener('input', (event) => {
            const target = event.target;

            if (! (target instanceof HTMLInputElement) || ! target.matches('input[type="hidden"][name]')) return;

            if (! pendingSuggestions.has(target.name)) return;

            const row = suggestionRows().find((candidate) => rowEntries(candidate).some(({ field }) => field === target.name));

            pendingSuggestions.delete(target.name);

            if (row instanceof HTMLElement) {
                const remaining = rowEntries(row);

                if (remaining.length === 0) {
                    row.replaceChildren();
                    row.hidden = true;
                } else {
                    row.querySelector('[data-suggestion-value]')?.replaceChildren(formatSuggestionLabel(remaining));
                }
            }
        });

        const setExtracting = (isExtracting) => {
            extractButton.disabled = isExtracting || !imageInput.files?.[0];
            extractButton.setAttribute('aria-busy', String(isExtracting));
            extractSpinner?.classList.toggle('hidden', !isExtracting);
            posterReadingLoadingScreen?.toggleAttribute('hidden', !isExtracting);
            posterReadingLoadingScreen?.setAttribute('aria-hidden', String(!isExtracting));

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
                    .filter(([name, value]) => ! scheduleFieldNames.has(name) && setEmptyField(form, name, value))
                    .map(([name]) => name);
                const filledCount = populatedFields.length;
                const suggestionCount = renderSuggestions(values);

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

                const suggestionNotice = suggestionCount > 0
                    ? ` ${suggestionCount} suggested date${suggestionCount === 1 ? '' : 's'} awaiting confirmation below.`
                    : '';

                displayExtractionStatus(
                    statusElement,
                    filledCount > 0
                        ? `${filledCount} blank field${filledCount === 1 ? '' : 's'} filled; ${detectedCount} poster section${detectedCount === 1 ? '' : 's'} detected.${suggestionNotice} Review the summary before saving.${budgetNotice}`
                        : `Poster read. Existing entries were kept.${suggestionNotice} Review the detection summary before saving.${budgetNotice}`,
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
            rejectedSuggestions.clear();
            dismissPendingSuggestions();
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
            if (extractedScheduleFields.size === 0 && pendingSuggestions.size === 0) return;

            if (!window.confirm('Clear the schedule dates copied from this poster? Your call details and guidelines will stay unchanged.')) {
                return;
            }

            extractedScheduleFields.forEach((fieldName) => clearField(form, fieldName));
            extractedScheduleFields = new Set();
            dismissPendingSuggestions();
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
