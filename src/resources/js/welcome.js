function revealLandingSections() {
    const elements = document.querySelectorAll('.landing-page .reveal');

    if (!('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12 });

    elements.forEach((element) => observer.observe(element));
}

function addIllustrationFeedback() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    document.querySelectorAll('[data-landing-tilt]').forEach((illustration) => {
        illustration.addEventListener('pointermove', (event) => {
            const bounds = illustration.getBoundingClientRect();
            const rotateX = ((event.clientY - bounds.top) / bounds.height - 0.5) * -3;
            const rotateY = ((event.clientX - bounds.left) / bounds.width - 0.5) * 3;

            illustration.style.transform = `perspective(900px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
        });

        illustration.addEventListener('pointerleave', () => {
            illustration.style.transform = '';
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.landing-page')) {
        return;
    }

    revealLandingSections();
    addIllustrationFeedback();
});
