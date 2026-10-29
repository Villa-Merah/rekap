(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const config = document.body.dataset;
    const currentUser = Number(config.user);
    let contacts = [], notifications = [], active = null, messages = [], seenMessage = null, seenNotifications = null;
    let audio = null, soundEnabled = false, busy = false, polling = false, selection = 0;
    const time = value => new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z').toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const status = text => { $('status').textContent = text; };
    async function api(url, data) {
        const response = await fetch(url, {
            method: data === undefined ? 'GET' : 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            credentials: 'same-origin', body: data === undefined ? undefined : JSON.stringify(data)
        });
        if (response.status === 401 || response.status === 419) {
            throw new Error('Sesi berakhir. Muat ulang halaman untuk masuk kembali.');
        }
        const result = await response.json();
        if (!response.ok) throw new Error(response.status === 422 ? Object.values(result.errors || {}).flat().join(' ') : 'Permintaan gagal. Silakan coba kembali.');
        return result;
    }
    function tone() {
        if (!audio || !soundEnabled || audio.state !== 'running') return;
        [660, 880].forEach((frequency, index) => {
            const oscillator = audio.createOscillator(), gain = audio.createGain(), start = audio.currentTime + index * .14;
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0, start);
            gain.gain.linearRampToValueAtTime(.12, start + .02);
            gain.gain.exponentialRampToValueAtTime(.001, start + .25);
            oscillator.connect(gain); gain.connect(audio.destination);
            oscillator.start(start); oscillator.stop(start + .26);
        });
    }
    $('sound').onclick = async () => {
        try {
            audio ||= new (window.AudioContext || window.webkitAudioContext)();
            await audio.resume();
            soundEnabled = !soundEnabled;
            $('sound').textContent = soundEnabled ? '♫ Suara aktif' : '♫ Aktifkan suara';
            $('sound').setAttribute('aria-pressed', String(soundEnabled));
            if (soundEnabled) tone();
        } catch { status('Browser ini tidak mendukung suara notifikasi.'); }
    };
    function renderContacts() {
        const query = $('search').value.toLocaleLowerCase('id-ID');
        const list = contacts.filter(contact => contact.name.toLocaleLowerCase('id-ID').includes(query));
        $('contacts').replaceChildren();
        if (!list.length) $('contacts').append(element('p', 'empty', 'Tidak ada rekan yang ditemukan.'));
        list.forEach(contact => {
            const button = element('button', 'contact' + (active?.id === contact.id ? ' chosen' : ''));
            button.type = 'button';
            const detail = element('div');
            detail.append(element('span', 'name', contact.name), element('small', '', `${contact.role} · ${contact.branch || 'Pusat'}`));
            button.append(element('span', 'avatar', contact.name.slice(0, 1)), detail);
            if (contact.unread) button.append(element('span', 'badge', contact.unread));
            button.onclick = () => selectContact(contact);
            $('contacts').append(button);
        });
    }
    function renderNotifications() {
        $('notifications').replaceChildren();
        if (!notifications.length) $('notifications').append(element('p', 'empty', 'Belum ada notifikasi. Kabar terbaru akan muncul di sini.'));
        notifications.forEach(notification => {
            const row = element('article', 'notice' + (!notification.read_at ? ' unread' : ''));
            const detail = element('div');
            detail.append(element('strong', '', notification.data.title || 'Notifikasi'), element('p', '', notification.data.body || ''), element('small', '', time(notification.created_at)));
            row.append(element('span', 'summary-icon', '✉'), detail);
            const sender = contacts.find(contact => contact.id === notification.data.sender_id);
            const target = notification.data.url ? new URL(notification.data.url, location.origin) : null;
            const taskUrl = target?.origin === location.origin ? target.href : null;
            const button = element('button', 'outline', sender ? 'Buka pesan' : (taskUrl ? 'Buka tugas' : 'Sudah dibaca'));
            button.disabled = !sender && !taskUrl && !!notification.read_at;
            button.onclick = async () => {
                try {
                    await api(config.read, { ids: [notification.id] });
                    if (taskUrl) { location.assign(taskUrl); return; }
                    if (sender) { switchTab('chat'); await selectContact(sender); }
                    await refresh();
                } catch (error) { status(error.message); }
            };
            row.append(button); $('notifications').append(row);
        });
    }
    function renderMessages(preservePosition = false) {
        const container = $('messages'), oldHeight = container.scrollHeight, oldTop = container.scrollTop;
        const atBottom = oldHeight - oldTop - container.clientHeight < 70;
        container.replaceChildren();
        if (!messages.length) container.append(element('p', 'empty', 'Belum ada pesan. Sapa rekan Anda untuk memulai percakapan.'));
        messages.forEach(message => {
            const mine = message.sender_id === currentUser;
            const bubble = element('div', 'bubble' + (mine ? ' mine' : ''), message.body);
            bubble.append(element('small', '', time(message.created_at) + (mine ? (message.read_at ? ' · Dibaca' : ' · Terkirim') : '')));
            container.append(bubble);
        });
        if (preservePosition) container.scrollTop = oldTop + container.scrollHeight - oldHeight;
        else if (atBottom || container.dataset.contact !== String(active.id)) container.scrollTop = container.scrollHeight;
        else container.scrollTop = oldTop;
        container.dataset.contact = active.id;
        $('older').hidden = messages.length < 50;
    }
    async function loadConversation() {
        if (!active) return;
        const contact = active, version = selection;
        const result = await api(`${config.chat}/${contact.id}`);
        if (version !== selection) return;
        const older = messages.filter(message => result.messages.length && message.id < result.messages[0].id);
        messages = [...older, ...result.messages];
        renderMessages();
        if (!document.hidden && !$('chat-panel').hidden && messages.length) {
            await api(`${config.chat}/${contact.id}/read`, { through: messages[messages.length - 1].id });
        }
    }
    async function selectContact(contact) {
        selection++; active = contact; messages = [];
        $('messages').textContent = 'Memuat percakapan…';
        $('conversation-heading').replaceChildren(element('strong', '', contact.name), element('small', '', `${contact.role} · ${contact.branch || 'Pusat'}`));
        $('body').disabled = false; $('send').disabled = false;
        $('body').value = ''; renderContacts();
        try { await loadConversation(); } catch (error) { status(error.message); }
    }
    async function refresh() {
        if (polling) return;
        polling = true;
        try {
            const data = await api(config.feed);
            contacts = data.contacts; notifications = data.notifications;
            const unreadMessages = contacts.reduce((sum, contact) => sum + Number(contact.unread), 0);
            $('message-count').textContent = $('chat-badge').textContent = unreadMessages;
            $('notification-count').textContent = $('notice-badge').textContent = data.unread;
            $('contact-count').textContent = contacts.length;
            const newNotification = seenNotifications !== null && notifications.some(notification => !notification.read_at && !seenNotifications.has(notification.id));
            if (seenMessage !== null && (Number(data.latest_message_id) > seenMessage || newNotification)) tone();
            seenNotifications ||= new Set();
            notifications.forEach(notification => seenNotifications.add(notification.id));
            seenMessage = Number(data.latest_message_id);
            renderContacts(); renderNotifications();
            if (active && !contacts.some(contact => contact.id === active.id)) {
                selection++; active = null; messages = [];
                $('messages').textContent = 'Akses percakapan sudah berubah.';
                $('body').disabled = $('send').disabled = true;
            }
            if (active && !$('chat-panel').hidden) await loadConversation();
            $('connection').textContent = '● Terhubung · pembaruan 5 detik';
        } catch (error) {
            $('connection').textContent = '● Koneksi terputus'; status(error.message);
        } finally { polling = false; }
    }
    $('composer').onsubmit = async event => {
        event.preventDefault();
        if (!active || busy || !$('body').value.trim()) return;
        const recipient = active, draft = $('body').value;
        busy = true; $('send').disabled = true; status('');
        try {
            await api(`${config.chat}/${recipient.id}`, { body: draft });
            if (active.id === recipient.id && $('body').value === draft) $('body').value = '';
            await loadConversation();
            $('messages').scrollTop = $('messages').scrollHeight;
        } catch (error) { status(error.message + ' Pesan tetap tersedia di kolom penulisan.'); }
        finally { busy = false; $('send').disabled = !active; }
    };
    $('body').onkeydown = event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); $('composer').requestSubmit(); }
    };
    $('older').onclick = async () => {
        if (!active || !messages.length) return;
        const version = selection; $('older').disabled = true;
        try {
            const data = await api(`${config.chat}/${active.id}?before=${messages[0].id}`);
            if (version !== selection) return;
            messages = [...data.messages, ...messages]; renderMessages(true);
            $('older').hidden = data.messages.length < 50;
        } catch (error) { status(error.message); }
        finally { $('older').disabled = false; }
    };
    $('read-all').onclick = async () => {
        const ids = notifications.filter(notification => !notification.read_at).map(notification => notification.id);
        if (!ids.length) return;
        try { await api(config.read, { ids }); await refresh(); } catch (error) { status(error.message); }
    };
    function switchTab(tab) {
        $('chat-panel').hidden = tab !== 'chat'; $('notifications-panel').hidden = tab !== 'notifications';
        document.querySelectorAll('[data-tab]').forEach(button => button.classList.toggle('selected', button.dataset.tab === tab));
        if (tab === 'chat' && active) loadConversation().catch(error => status(error.message));
    }
    document.querySelectorAll('[data-tab]').forEach(button => { button.onclick = () => switchTab(button.dataset.tab); });
    $('search').oninput = renderContacts;
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    refresh(); setInterval(() => { if (!document.hidden) refresh(); }, 5000);
})();
