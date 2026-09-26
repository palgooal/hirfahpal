// Password visibility toggle for the admin login page. It only switches the
// input type; the password is never read, stored or sent from here.
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));

    if (!input) {
        return;
    }

    button.addEventListener('click', () => {
        const reveal = input.type === 'password';

        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        button.setAttribute('aria-label', reveal ? button.dataset.labelHide : button.dataset.labelShow);
        button.querySelector('[data-icon-show]')?.classList.toggle('hidden', reveal);
        button.querySelector('[data-icon-hide]')?.classList.toggle('hidden', !reveal);
    });
});
