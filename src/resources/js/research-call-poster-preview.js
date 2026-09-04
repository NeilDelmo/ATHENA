function initializeResearchCallPosterPreviews() {
    document.querySelectorAll('[data-research-call-poster-gallery]').forEach((gallery) => {
        if (gallery.dataset.researchCallPosterGalleryReady === 'true') return;

        const previewButtons = [...gallery.querySelectorAll('[data-research-call-poster-preview]')];
        const modal = gallery.querySelector('[data-research-call-poster-modal]');
        const modalImage = gallery.querySelector('[data-research-call-poster-modal-image]');
        const closeButton = gallery.querySelector('[data-research-call-poster-modal-close]');

        if (! (modal instanceof HTMLElement) || ! (modalImage instanceof HTMLImageElement)) return;

        gallery.dataset.researchCallPosterGalleryReady = 'true';
        document.body.append(modal);

        let previouslyFocusedElement = null;
        let previousBodyOverflow = '';
        let backdropAnimation;
        let posterAnimation;

        const closePreview = () => {
            const finishClosing = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = previousBodyOverflow;

                if (previouslyFocusedElement instanceof HTMLElement) previouslyFocusedElement.focus();
            };

            backdropAnimation?.cancel();
            posterAnimation?.cancel();

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                finishClosing();

                return;
            }

            posterAnimation = modalImage.animate([
                { opacity: 1, transform: 'translateY(0) scale(1)' },
                { opacity: 0, transform: 'translateY(1rem) scale(0.96)' },
            ], {
                duration: 180,
                easing: 'ease-in',
                fill: 'both',
            });
            backdropAnimation = modal.animate([
                { opacity: 1 },
                { opacity: 0 },
            ], {
                duration: 200,
                easing: 'ease-in',
            });
            backdropAnimation.onfinish = finishClosing;
        };

        const openPreview = (previewButton) => {
            const posterUrl = previewButton.dataset.posterUrl;

            if (! posterUrl) return;

            previouslyFocusedElement = document.activeElement;
            previousBodyOverflow = document.body.style.overflow;
            modalImage.src = posterUrl;
            modalImage.alt = previewButton.dataset.posterAlt ?? 'Research call poster';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            modal.focus();

            backdropAnimation?.cancel();
            posterAnimation?.cancel();

            if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                backdropAnimation = modal.animate([
                    { opacity: 0 },
                    { opacity: 1 },
                ], {
                    duration: 260,
                    easing: 'ease-out',
                });
                posterAnimation = modalImage.animate([
                    { opacity: 0, transform: 'translateY(1.5rem) scale(0.92)' },
                    { opacity: 1, transform: 'translateY(0) scale(1)' },
                ], {
                    duration: 380,
                    easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                });
            }
        };

        previewButtons.forEach((previewButton) => {
            previewButton.addEventListener('click', () => openPreview(previewButton));
        });
        closeButton?.addEventListener('click', closePreview);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closePreview();
        });
        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closePreview();
        });
    });
}

export default initializeResearchCallPosterPreviews;
