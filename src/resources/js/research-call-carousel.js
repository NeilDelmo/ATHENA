function relativeSlideIndex(index, activeIndex, slideCount) {
    let difference = index - activeIndex;

    if (difference > slideCount / 2) difference -= slideCount;
    if (difference < -slideCount / 2) difference += slideCount;

    return difference;
}

function positionSlide(slide, difference) {
    const isActive = difference === 0;
    const posterImage = slide.querySelector('[data-research-call-poster-trigger]');
    const submitOverlay = slide.querySelector('[data-research-call-submit-overlay]');

    slide.style.transform = isActive
        ? 'translate(-50%, -50%)'
        : 'translate(-50%, -50%) scale(0.92)';
    slide.style.opacity = isActive ? '1' : '0';
    slide.style.filter = isActive ? 'none' : 'blur(4px) brightness(0.8)';
    slide.style.visibility = 'visible';
    slide.style.pointerEvents = isActive ? 'auto' : 'none';
    slide.style.zIndex = isActive ? '30' : '10';
    slide.setAttribute('aria-hidden', String(!isActive));
    slide.dataset.researchCallActive = String(isActive);

    if (posterImage instanceof HTMLImageElement) {
        posterImage.style.transform = isActive && slide.matches(':hover') ? 'scale(1.04)' : 'scale(1)';
    }

    if (submitOverlay instanceof HTMLElement) {
        if (isActive) {
            submitOverlay.style.removeProperty('opacity');
            submitOverlay.style.removeProperty('pointer-events');
        } else {
            submitOverlay.style.opacity = '0';
            submitOverlay.style.pointerEvents = 'none';
        }
    }
}

function initializeResearchCallCarousels() {
    document.querySelectorAll('[data-research-call-carousel]').forEach((carousel) => {
        if (carousel.dataset.researchCallCarouselReady === 'true') return;

        const slides = [...carousel.querySelectorAll('[data-research-call-slide]')];
        const previousButton = carousel.querySelector('[data-research-call-previous]');
        const nextButton = carousel.querySelector('[data-research-call-next]');
        const indicators = [...carousel.querySelectorAll('[data-research-call-indicator]')];
        const lightbox = carousel.querySelector('[data-research-call-lightbox]');
        const lightboxImage = carousel.querySelector('[data-research-call-lightbox-image]');
        if (slides.length === 0) return;

        carousel.dataset.researchCallCarouselReady = 'true';

        if (lightbox instanceof HTMLElement) {
            document.body.append(lightbox);
        }

        let activeIndex = 0;
        let autoplayId;
        let previouslyFocusedElement = null;
        let previousBodyOverflow = '';
        let lightboxBackdropAnimation;
        let lightboxPosterAnimation;

        const renderSlides = () => {
            slides.forEach((slide, slideIndex) => {
                positionSlide(
                    slide,
                    relativeSlideIndex(slideIndex, activeIndex, slides.length),
                );
            });

            indicators.forEach((indicator, indicatorIndex) => {
                const isActive = indicatorIndex === activeIndex;

                indicator.classList.toggle('w-5', isActive);
                indicator.classList.toggle('bg-red-600', isActive);
                indicator.classList.toggle('w-2', ! isActive);
                indicator.classList.toggle('bg-white/80', ! isActive);
                indicator.setAttribute('aria-current', isActive ? 'true' : 'false');
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
                moveTo(activeIndex + 1);
            }, 5000);
        };

        const moveTo = (index) => {
            const nextIndex = (index + slides.length) % slides.length;

            if (nextIndex === activeIndex) return;

            activeIndex = nextIndex;
            renderSlides();
            startAutoplay();
        };

        previousButton?.addEventListener('click', () => moveTo(activeIndex - 1));
        nextButton?.addEventListener('click', () => moveTo(activeIndex + 1));
        indicators.forEach((indicator, indicatorIndex) => {
            indicator.addEventListener('click', () => moveTo(indicatorIndex));
        });

        const closeLightbox = () => {
            if (! (lightbox instanceof HTMLElement)) return;

            const finishClosingLightbox = () => {
                lightbox.classList.add('hidden');
                lightbox.classList.remove('flex');
                lightbox.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = previousBodyOverflow;

                if (previouslyFocusedElement instanceof HTMLElement) previouslyFocusedElement.focus();
            };

            lightboxBackdropAnimation?.cancel();
            lightboxPosterAnimation?.cancel();

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                finishClosingLightbox();

                return;
            }

            lightboxPosterAnimation = lightboxImage?.animate([
                { opacity: 1, transform: 'translateY(0) scale(1)' },
                { opacity: 0, transform: 'translateY(1rem) scale(0.94)' },
            ], {
                duration: 200,
                easing: 'ease-in',
                fill: 'both',
            });
            lightboxBackdropAnimation = lightbox.animate([
                { opacity: 1 },
                { opacity: 0 },
            ], {
                duration: 220,
                easing: 'ease-in',
            });
            lightboxBackdropAnimation.onfinish = finishClosingLightbox;
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
            lightbox.focus();

            lightboxBackdropAnimation?.cancel();
            lightboxPosterAnimation?.cancel();

            if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                lightboxBackdropAnimation = lightbox.animate([
                    { opacity: 0 },
                    { opacity: 1 },
                ], {
                    duration: 280,
                    easing: 'ease-out',
                });
                lightboxPosterAnimation = lightboxImage.animate([
                    { opacity: 0, transform: 'translateY(1.5rem) scale(0.9)' },
                    { opacity: 1, transform: 'translateY(0) scale(1)' },
                ], {
                    duration: 420,
                    easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                });
            }
        };

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

                if (slideIndex !== activeIndex) {
                    moveTo(slideIndex);

                    return;
                }

                if (posterImage instanceof HTMLImageElement) openLightbox(posterImage);
            });
            slide.addEventListener('mouseenter', () => {
                if (slideIndex === activeIndex && posterImage instanceof HTMLImageElement) {
                    posterImage.style.transform = 'scale(1.04)';
                }
            });
            slide.addEventListener('mouseleave', () => {
                if (posterImage instanceof HTMLImageElement) {
                    posterImage.style.transform = 'scale(1)';
                }
            });
        });

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
