document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.landing-page header details a').forEach((link) => {
        link.addEventListener('click', () => {
            link.closest('details').open = false;
        });
    });
});
