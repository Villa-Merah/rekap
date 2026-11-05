(() => {
    'use strict';

    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('primary-navigation');
    const backdrop = document.getElementById('sidebar-backdrop');
    const mobileViewport = window.matchMedia('(max-width: 720px)');

    function setMenuOpen(isOpen) {
        const shouldOpen = isOpen && mobileViewport.matches;
        document.body.classList.toggle('sidebar-open', shouldOpen);
        menuToggle.setAttribute('aria-expanded', String(shouldOpen));
        menuToggle.setAttribute('aria-label', shouldOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        sidebar.setAttribute('aria-hidden', String(mobileViewport.matches && !shouldOpen));
        sidebar.inert = mobileViewport.matches && !shouldOpen;
        backdrop.hidden = !shouldOpen;
    }

    menuToggle.addEventListener('click', () => {
        setMenuOpen(menuToggle.getAttribute('aria-expanded') !== 'true');
        if (menuToggle.getAttribute('aria-expanded') === 'true') {
            sidebar.querySelector('.nav-link')?.focus();
        }
    });

    backdrop.addEventListener('click', () => {
        setMenuOpen(false);
        menuToggle.focus();
    });

    sidebar.addEventListener('click', event => {
        if (mobileViewport.matches && event.target.closest('a')) {
            setMenuOpen(false);
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menuToggle.getAttribute('aria-expanded') === 'true') {
            setMenuOpen(false);
            menuToggle.focus();
        }
    });

    mobileViewport.addEventListener('change', () => setMenuOpen(false));
    setMenuOpen(false);
})();
