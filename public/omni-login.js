(() => {
    const button = document.getElementById('omni-login');
    const status = document.getElementById('omni-status');
    if (!button || !status) return;
    let flow = null;
    function reset(message) {
        if (flow) {
            clearInterval(flow.timer);
            flow.popup.close();
        }
        flow = null;
        button.disabled = false;
        status.textContent = message;
    }
    window.addEventListener('message', (event) => {
        if (!flow || !flow.settings || event.source !== flow.popup || event.origin !== flow.settings.server_origin) return;
        if (!event.data || event.data.source !== 'omni_sso' || typeof event.data.redirect_url !== 'string') return;
        try {
            const incoming = new URL(event.data.redirect_url);
            const callback = new URL(flow.settings.callback_url);
            if (incoming.origin !== callback.origin || incoming.pathname !== callback.pathname) return;
            const payload = incoming.searchParams.get('sso_data');
            if (!payload || payload.length > 16000) return;
            callback.searchParams.set('sso_data', payload);
            callback.searchParams.set('state', flow.settings.state);
            clearInterval(flow.timer);
            flow.popup.close();
            flow = null;
            status.textContent = 'Login berhasil diverifikasi. Mengalihkan…';
            window.location.assign(callback.href);
        } catch {
            reset('Respons SSO tidak dapat dibaca. Silakan coba kembali.');
        }
    });
    button.addEventListener('click', async () => {
        if (flow) return;
        const popup = window.open('about:blank', 'omni-' + crypto.randomUUID(), 'popup,width=600,height=740');
        if (!popup) {
            status.textContent = 'Izinkan jendela popup untuk masuk dengan akun SIVMI.';
            return;
        }
        flow = { popup, settings: null, timer: null };
        button.disabled = true;
        status.textContent = 'Membuka login SIVMI…';
        try {
            const response = await fetch(button.dataset.prepare, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            if (!response.ok) throw new Error('prepare failed');
            const settings = await response.json();
            if (new URL(settings.callback_url).origin !== window.location.origin || new URL(settings.authorization_url).origin !== settings.server_origin) {
                throw new Error('invalid SSO configuration');
            }
            if (!flow) return;
            flow.settings = settings;
            popup.location.replace(settings.authorization_url);
            const started = Date.now();
            flow.timer = setInterval(() => {
                if (!flow) return;
                if (popup.closed || Date.now() - started > 300000) reset('Login SSO dibatalkan atau waktunya habis. Silakan coba kembali.');
            }, 500);
        } catch {
            reset('Login SSO belum dapat dibuka. Coba kembali atau gunakan login organisasi.');
        }
    });
})();
