(() => {
    'use strict';
    const settings = document.body.dataset;
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const output = document.getElementById('work-status');
    const textNode = (tag, text, className = '') => {
        const node = document.createElement(tag); node.textContent = text; node.className = className; return node;
    };
    let audio = null, sound = false, seen = null, loading = false;
    function beep() {
        if (!sound || !audio || audio.state !== 'running') return;
        const oscillator = audio.createOscillator(), gain = audio.createGain();
        oscillator.frequency.value = 800; oscillator.connect(gain); gain.connect(audio.destination);
        gain.gain.setValueAtTime(.1, audio.currentTime); gain.gain.exponentialRampToValueAtTime(.001, audio.currentTime + .35);
        oscillator.start(); oscillator.stop(audio.currentTime + .36);
    }
    document.getElementById('work-sound').onclick = async event => {
        try {
            audio ||= new (window.AudioContext || window.webkitAudioContext)();
            await audio.resume(); sound = !sound;
            event.target.textContent = sound ? '♫ Suara aktif' : '♫ Aktifkan suara';
            event.target.setAttribute('aria-pressed', String(sound));
            if (sound) beep();
        } catch { output.textContent = 'Browser ini tidak mendukung suara notifikasi.'; }
    };
    async function markRead(id) {
        const response = await fetch(settings.read, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ ids: [id] }) });
        if (!response.ok) throw new Error('Gagal menandai notifikasi. Muat ulang halaman jika sesi telah berakhir.');
    }
    async function refresh() {
        if (loading || document.hidden) return;
        loading = true;
        try {
            const response = await fetch(settings.feed, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Notifikasi belum dapat dimuat. Periksa koneksi atau muat ulang halaman.');
            const data = await response.json();
            document.getElementById('work-unread').textContent = data.unread;
            const list = document.getElementById('work-notices'); list.replaceChildren();
            if (seen !== null && data.notifications.some(item => !item.read_at && !seen.has(item.id))) beep();
            seen ||= new Set();
            data.notifications.forEach(item => {
                seen.add(item.id);
                const row = textNode('article', '', 'work-notice' + (!item.read_at ? ' unread' : ''));
                row.append(textNode('strong', item.data.title || 'Notifikasi'), textNode('p', item.data.body || ''));
                const target = item.data.url || (item.type === 'chat' ? '/' : null);
                if (target) {
                    const url = new URL(target, location.origin);
                    if (url.origin === location.origin) {
                        const link = textNode('a', item.type === 'chat' ? 'Buka komunikasi tim →' : 'Buka tugas →');
                        link.href = url.href;
                        link.onclick = async event => {
                            event.preventDefault();
                            try { await markRead(item.id); location.assign(url.href); } catch (error) { output.textContent = error.message; }
                        };
                        row.append(link);
                    }
                }
                if (!item.read_at) {
                    const button = textNode('button', 'Tandai dibaca', 'outline');
                    button.onclick = async () => {
                        try { await markRead(item.id); await refresh(); } catch (error) { output.textContent = error.message; }
                    };
                    row.append(button);
                }
                list.append(row);
            });
            if (!data.notifications.length) list.append(textNode('p', 'Belum ada notifikasi.', 'empty'));
        } catch (error) { output.textContent = error.message; }
        finally { loading = false; }
    }
    refresh(); setInterval(refresh, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    const platform = document.getElementById('task-platform');
    if (platform) {
        const options = JSON.parse(document.getElementById('platform-options').textContent);
        const update = () => document.querySelectorAll('[data-activity]').forEach(label => {
            const available = options[platform.value].includes(label.dataset.activity);
            label.hidden = !available; label.querySelector('input').disabled = !available;
            if (!available) label.querySelector('input').checked = false;
        });
        platform.onchange = update; update();
        const branch = document.getElementById('task-branch');
        const updateBranch = () => document.querySelectorAll('[data-branch]').forEach(label => {
            const match = label.dataset.branch === branch.value;
            label.hidden = !match; label.querySelector('input').disabled = !match;
            if (!match) label.querySelector('input').checked = false;
        });
        branch.onchange = updateBranch; updateBranch();
        document.getElementById('select-branch').onclick = () => document.querySelectorAll('[data-branch]:not([hidden]) input').forEach(input => { input.checked = true; });
    }
    document.querySelectorAll('.evidence-input').forEach(input => {
        let previews = [];
        input.onchange = () => {
            previews.forEach(url => URL.revokeObjectURL(url)); previews = [];
            const grid = input.closest('form').querySelector('.preview-grid'); grid.replaceChildren();
            Array.from(input.files).forEach(file => {
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
                const image = document.createElement('img'); image.src = URL.createObjectURL(file); image.alt = 'Pratinjau ' + file.name;
                previews.push(image.src); grid.append(image);
            });
        };
    });
    document.querySelectorAll('.review-decision').forEach(select => {
        select.onchange = () => { select.closest('form').querySelector('textarea').required = select.value !== 'Disetujui'; };
    });
})();
