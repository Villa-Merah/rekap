document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    const input = toggle.closest('.password-field')?.querySelector('input[type="password"], input[type="text"]');
    const hiddenIcon = toggle.querySelector('[data-password-hidden-icon]');
    const visibleIcon = toggle.querySelector('[data-password-visible-icon]');

    if (!input || !hiddenIcon || !visibleIcon) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isVisible = input.type === 'text';

        input.type = isVisible ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', String(!isVisible));
        toggle.setAttribute('aria-label', isVisible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
        hiddenIcon.toggleAttribute('hidden', !isVisible);
        visibleIcon.toggleAttribute('hidden', isVisible);
    });
});
