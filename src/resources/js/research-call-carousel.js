function relativeSlideIndex(index, activeIndex, slideCount) {
    let difference = index - activeIndex;

    if (difference > slideCount / 2) difference -= slideCount;
    if (difference < -slideCount / 2) difference += slideCount;

    return difference;
}

function positionSlide(slide, difference, isHovered) {
    const isActive = difference === 0;
    const scale = isActive ? (isHovered ? 1.29 : 1.24) : (isHovered ? 1 : 0.88);

    slide.style.transform = `translate(-50%, -50%) scale(${scale})`;
    slide.style.opacity = isActive ? '1' : '0';
    slide.style.filter = isActive ? 'none' : 'grayscale(0.2)';
    slide.style.visibility = isActive ? 'visible' : 'hidden';
    slide.style.zIndex = isActive ? '30' : '0';
    slide.setAttribute('aria-hidden', String(!isActive));
}

function initializeResearchCallCarousels() {
    document.querySelectorAll('[data-research-call-carousel]').forEach((carousel) => {
        if (carousel.dataset.researchCallCarouselReady === 'true') return;

        const slides = [...carousel.querySelectorAll('[data-research-call-slide]')];
        const previousButton = carousel.querySelector('[data-research-call-previous]');
        const nextButton = carousel.querySelector('[data-research-call-next]');
        const lightbox = carousel.querySelector('[data-research-call-lightbox]');
        const lightboxImage = carousel.querySelector('[data-research-call-lightbox-image]');
        const lightboxCloseButton = carousel.querySelector('[data-research-call-lightbox-close]');
        const sidebar = document.getElementById('app-sidebar');

        if (slides.length === 0) return;

        carousel.dataset.researchCallCarouselReady = 'true';

        let activeIndex = 0;
        let hoveredSlideIndex = null;
        let autoplayId;
        let previouslyFocusedElement = null;
        let previousBodyOverflow = '';

        const syncLightboxPosition = () => {
            if (! (lightbox instanceof HTMLElement)) return;

            const sidebarWidth = sidebar instanceof HTMLElement ? sidebar.getBoundingClientRect().width : 0;
            lightbox.style.left = `${sidebarWidth}px`;
        };

        const renderSlides = () => {
            slides.forEach((slide, slideIndex) => {
                positionSlide(
                    slide,
                    relativeSlideIndex(slideIndex, activeIndex, slides.length),
                    hoveredSlideIndex === slideIndex,
                );
            });
        };

        const stopAutoplay = () => {
            if (autoplayId) {
                window.clearInterval(autoplayId);
                autoplayId = undefined;
            }
        };

        const startAutoplay = () => {
            stopAutoplay();

            if (slides.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            autoplayId = window.setInterval(() => {
                activeIndex = (activeIndex + 1) % slides.length;
                renderSlides();
            }, 5500);
        };

        const moveTo = (index) => {
            activeIndex = (index + slides.length) % slides.length;
            renderSlides();
            startAutoplay();
        };

        previousButton?.addEventListener('click', () => moveTo(activeIndex - 1));
        nextButton?.addEventListener('click', () => moveTo(activeIndex + 1));

        const closeLightbox = () => {
            if (! (lightbox instanceof HTMLElement)) return;

            lightbox.classList.add('hidden');
            lightbox.classList.remove('flex');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = previousBodyOverflow;

            if (previouslyFocusedElement instanceof HTMLElement) previouslyFocusedElement.focus();
        };

        const openLightbox = (image) => {
            if (! (lightbox instanceof HTMLElement) || ! (lightboxImage instanceof HTMLImageElement)) return;

            previouslyFocusedElement = document.activeElement;
            previousBodyOverflow = document.body.style.overflow;
            lightboxImage.src = image.currentSrc || image.src;
            lightboxImage.alt = image.alt;
            lightbox.classList.remove('hidden');
            lightbox.classList.add('flex');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            lightboxCloseButton?.focus();
        };

        lightboxCloseButton?.addEventListener('click', closeLightbox);
        lightbox?.addEventListener('click', (event) => {
            if (event.target === lightbox) closeLightbox();
        });
        lightbox?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeLightbox();
        });

        slides.forEach((slide, slideIndex) => {
            const posterImage = slide.querySelector('[data-research-call-poster-trigger]');

            slide.addEventListener('click', (event) => {
                if (event.target instanceof Element && event.target.closest('a, button')) return;
                if (posterImage instanceof HTMLImageElement) openLightbox(posterImage);
            });
            slide.addEventListener('mouseenter', () => {
                hoveredSlideIndex = slideIndex;
                renderSlides();
            });
            slide.addEventListener('mouseleave', () => {
                if (hoveredSlideIndex !== slideIndex) return;

                hoveredSlideIndex = null;
                renderSlides();
            });
        });

        syncLightboxPosition();
        window.addEventListener('resize', syncLightboxPosition, { passive: true });
        if (sidebar instanceof HTMLElement && 'ResizeObserver' in window) {
            new ResizeObserver(syncLightboxPosition).observe(sidebar);
        }

        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        carousel.addEventListener('focusin', stopAutoplay);
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) startAutoplay();
        });
        window.addEventListener('resize', renderSlides, { passive: true });

        renderSlides();
        startAutoplay();
    });
}

export default initializeResearchCallCarousels;
