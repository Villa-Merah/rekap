(() => {
    'use strict';

    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('primary-navigation');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!menuToggle || !sidebar || !backdrop) return;

    const closeButton = sidebar.querySelector('.sidebar-close');
    const main = document.querySelector('.main');
    const mobileViewport = window.matchMedia('(max-width: 720px)');
    const isOpen = () => document.body.classList.contains('sidebar-open');

    function setMenuOpen(isOpen) {
        const shouldOpen = isOpen && mobileViewport.matches;
        if (main) main.inert = shouldOpen;
        if (!shouldOpen && mobileViewport.matches && sidebar.contains(document.activeElement)) {
            menuToggle.focus({ preventScroll: true });
        }
        document.body.classList.toggle('sidebar-open', shouldOpen);
        menuToggle.setAttribute('aria-expanded', String(shouldOpen));
        menuToggle.setAttribute('aria-label', shouldOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        sidebar.setAttribute('aria-hidden', String(mobileViewport.matches && !shouldOpen));
        sidebar.inert = mobileViewport.matches && !shouldOpen;
        if (shouldOpen) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }
        backdrop.hidden = !shouldOpen;
    }

    menuToggle.addEventListener('click', () => {
        setMenuOpen(!isOpen());
        requestAnimationFrame(() => requestAnimationFrame(() => {
            if (isOpen()) (closeButton || sidebar.querySelector('.nav-link'))?.focus({ preventScroll: true });
        }));
    });
    closeButton?.addEventListener('click', () => setMenuOpen(false));
    backdrop.addEventListener('click', () => {
        setMenuOpen(false);
        menuToggle.focus({ preventScroll: true });
    });
    sidebar.addEventListener('click', event => {
        if (mobileViewport.matches && event.target.closest('a')) setMenuOpen(false);
    });
    document.addEventListener('keydown', event => {
        if (!mobileViewport.matches || !isOpen()) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setMenuOpen(false);
        } else if (event.key === 'Tab') {
            const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                .filter(element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (!sidebar.contains(document.activeElement)) {
                event.preventDefault(); (event.shiftKey ? last : first)?.focus();
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first?.focus();
            }
        }
    });
    mobileViewport.addEventListener('change', () => setMenuOpen(false));
    setMenuOpen(false);
})();
