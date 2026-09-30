function initializeResearchCallCarousels() {
    document.querySelectorAll('[data-research-call-carousel]').forEach((carousel) => {
        if (carousel.dataset.researchCallCarouselReady === 'true') return;

        const slides = [...carousel.querySelectorAll('[data-research-call-slide]')];
        if (slides.length === 0) return;

        carousel.dataset.researchCallCarouselReady = 'true';
        const lightbox = carousel.querySelector('[data-research-call-lightbox]');
        const lightboxImage = carousel.querySelector('[data-research-call-lightbox-image]');
        const closeButton = carousel.querySelector('[data-research-call-lightbox-close]');
        const counter = carousel.querySelector('[data-research-call-counter]');
        let activeIndex = 0;
        let previousFocus = null;
        let previousOverflow = '';
        let previewOpen = false;
        let inertElements = [];

        if (lightbox) document.body.append(lightbox);

        const moveTo = (index) => {
            activeIndex = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => {
                const isActive = slideIndex === activeIndex;
                slide.hidden = !isActive;
                slide.style.display = isActive ? 'grid' : 'none';
                slide.inert = !isActive;
                slide.setAttribute('aria-hidden', String(!isActive));
                slide.dataset.researchCallActive = String(isActive);
            });
            if (counter) counter.textContent = `Announcement ${activeIndex + 1} of ${slides.length}`;
        };

        const closePreview = () => {
            if (!lightbox || !previewOpen) return;
            previewOpen = false;
            lightbox.classList.add('hidden');
            lightbox.classList.remove('flex');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = previousOverflow;
            inertElements.forEach((element) => { element.inert = false; });
            inertElements = [];
            if (previousFocus?.isConnected) previousFocus.focus();
        };

        const openPreview = (slide) => {
            const image = slide.querySelector('[data-research-call-poster-trigger]');
            if (!lightbox || !lightboxImage || !image || previewOpen) return;
            previousFocus = document.activeElement;
            previousOverflow = document.body.style.overflow;
            lightboxImage.src = image.currentSrc || image.src;
            lightboxImage.alt = image.alt;
            previewOpen = true;
            inertElements = [...document.body.children].filter((element) => element !== lightbox && !element.inert && !['SCRIPT', 'STYLE'].includes(element.tagName));
            inertElements.forEach((element) => { element.inert = true; });
            lightbox.classList.remove('hidden');
            lightbox.classList.add('flex');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            closeButton?.focus();
        };

        carousel.querySelector('[data-research-call-previous]')?.addEventListener('click', () => moveTo(activeIndex - 1));
        carousel.querySelector('[data-research-call-next]')?.addEventListener('click', () => moveTo(activeIndex + 1));
        slides.forEach((slide) => {
            slide.querySelectorAll('[data-research-call-preview]').forEach((button) => {
                button.addEventListener('click', () => openPreview(slide));
            });
        });
        closeButton?.addEventListener('click', closePreview);
        lightbox?.addEventListener('click', (event) => {
            if (event.target === lightbox) closePreview();
        });
        lightbox?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closePreview();
            if (event.key === 'Tab') {
                event.preventDefault();
                closeButton?.focus();
            }
        });
        document.addEventListener('livewire:navigating', () => {
            closePreview();
            lightbox?.remove();
        }, { once: true });
        moveTo(0);
    });
}

export default initializeResearchCallCarousels;
