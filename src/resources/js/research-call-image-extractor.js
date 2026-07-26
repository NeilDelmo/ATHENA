function displayExtractionStatus(statusElement, message, isError = false) {
    if (! (statusElement instanceof HTMLElement)) return;

    statusElement.textContent = message;
    statusElement.classList.toggle('hidden', message === '');
    statusElement.classList.toggle('text-red-700', isError);
    statusElement.classList.toggle('dark:text-red-300', isError);
    statusElement.classList.toggle('text-green-700', !isError);
    statusElement.classList.toggle('dark:text-green-300', !isError);
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

        if (! (imageInput instanceof HTMLInputElement) || ! (extractButton instanceof HTMLButtonElement)) return;

        let previewUrl = null;

        const supportedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];

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

            const formData = new FormData();
            formData.append('reference_image', image);
            extractButton.disabled = true;
            displayExtractionStatus(statusElement, 'Reading the poster…');

            try {
                const response = await fetch(form.dataset.extractUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: formData,
                });
                const payload = await response.json();

                if (!response.ok) {
                    const validationMessage = Object.values(payload.errors || {}).flat().join(' ');

                    throw new Error(validationMessage || payload.message || 'The poster could not be read.');
                }

                const fields = payload.fields || {};
                const values = {
                    ...fields,
                    categories: Array.isArray(fields.categories) ? fields.categories.join(', ') : fields.categories,
                };
                const filledCount = Object.entries(values)
                    .filter(([name, value]) => setEmptyField(form, name, value)).length;

                displayExtractionStatus(
                    statusElement,
                    filledCount > 0
                        ? `${filledCount} field${filledCount === 1 ? '' : 's'} filled from the poster. Review them before saving.`
                        : 'No blank fields were detected. Review the poster and complete the form manually.',
                );
            } catch (error) {
                displayExtractionStatus(
                    statusElement,
                    error instanceof Error ? error.message : 'The poster could not be read. You can complete the form manually.',
                    true,
                );
            } finally {
                extractButton.disabled = false;
            }
        };

        imageInput.addEventListener('change', () => {
            const image = imageInput.files?.[0];

            if (!image) return;

            updatePreview(image);
            void extractImage();
        });
        extractButton.addEventListener('click', () => void extractImage());

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
