function showAnnouncementImageStatus(statusElement, message, isError = false) {
    if (! (statusElement instanceof HTMLElement)) return;

    statusElement.textContent = message;
    statusElement.classList.toggle('hidden', message === '');
    statusElement.classList.toggle('text-red-700', isError);
    statusElement.classList.toggle('dark:text-red-300', isError);
    statusElement.classList.toggle('text-green-700', !isError);
    statusElement.classList.toggle('dark:text-green-300', !isError);
}

function initializeAnnouncementImageUploads() {
    document.querySelectorAll('[data-announcement-image-form]').forEach((form) => {
        if (! (form instanceof HTMLFormElement) || form.dataset.announcementImageReady === 'true') return;

        const imageInput = form.querySelector('[data-announcement-image]');
        const dropzone = form.querySelector('[data-announcement-image-dropzone]');
        const preview = form.querySelector('[data-announcement-image-preview]');
        const emptyState = form.querySelector('[data-announcement-image-empty]');
        const imageName = form.querySelector('[data-announcement-image-name]');
        const statusElement = form.querySelector('[data-announcement-image-status]');

        if (! (imageInput instanceof HTMLInputElement)) return;

        form.dataset.announcementImageReady = 'true';
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
            showAnnouncementImageStatus(statusElement, 'Image ready to upload.');
        };

        const attachImage = (image) => {
            if (!image) return;

            if (!supportedImageTypes.includes(image.type)) {
                showAnnouncementImageStatus(statusElement, 'Please choose a JPG, PNG, or WebP image.', true);

                return;
            }

            try {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(image);
                imageInput.files = dataTransfer.files;
                updatePreview(image);
            } catch {
                showAnnouncementImageStatus(statusElement, 'This browser could not attach the image. Please use the browse option.', true);
            }
        };

        imageInput.addEventListener('change', () => attachImage(imageInput.files?.[0]));

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
            if (! (event.relatedTarget instanceof Node) || ! dropzone.contains(event.relatedTarget)) setDropzoneActive(false);
        });
        dropzone?.addEventListener('drop', (event) => {
            event.preventDefault();
            setDropzoneActive(false);
            attachImage(event.dataTransfer?.files?.[0]);
        });
        form.addEventListener('paste', (event) => {
            const imageItem = [...(event.clipboardData?.items || [])].find((item) => item.type.startsWith('image/'));
            const pastedImage = imageItem?.getAsFile();

            if (!pastedImage) return;

            event.preventDefault();
            attachImage(pastedImage);
        });
    });
}

export default initializeAnnouncementImageUploads;
