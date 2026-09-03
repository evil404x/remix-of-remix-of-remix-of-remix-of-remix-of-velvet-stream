/**
 * CineChatPopup - global incoming-message toast + floating quick chat
 * Shows a toast at the top of any page when someone messages you.
 * Clicking it opens a floating chat modal WITHOUT leaving the current page.
 */
(function () {
    const SITE = window.SITE_URL || '';
    if (!document.body || !document.querySelector('.mobile-bottom-bar, .smart-hub')) {
        // only logged-in layouts render these; bail out for guests
    }

    let lastId = 0;
    let openWith = 0;
    let threadTimer = null;
    let threadLastId = 0;

    // ---------- toast ----------
    function toastLayer() {
        let l = document.getElementById('cine-toast-layer');
        if (!l) {
            l = document.createElement('div');
            l.id = 'cine-toast-layer';
            l.className = 'cine-toast-layer';
            document.body.appendChild(l);
        }
        return l;
    }

    function avatarHtml(avatar, size) {
        if (avatar && avatar !== 'default.png') {
            return `<img src="${SITE}/uploads/avatars/${avatar}" alt="" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;">`;
        }
        return `<span class="cine-toast-fallback" style="width:${size}px;height:${size}px;"><i class="fas fa-user"></i></span>`;
    }

    function previewText(msg) {
        const m = String(msg || '').match(/^\[sticker:(.+?)\]$/);
        if (m) return '🖼️ ستیکەرێکی نارد';
        return String(msg || '').slice(0, 70);
    }

    function showToast(alert) {
        const layer = toastLayer();
        const el = document.createElement('div');
        el.className = 'cine-toast';
        el.innerHTML = `
            <div class="cine-toast-avatar">${avatarHtml(alert.avatar, 42)}<span class="cine-toast-dot"></span></div>
            <div class="cine-toast-body">
                <strong>${alert.username}</strong>
                <span>${previewText(alert.message)}</span>
            </div>
            <div class="cine-toast-actions">
                <button type="button" class="cine-toast-open"><i class="fas fa-reply"></i></button>
                <button type="button" class="cine-toast-close">&times;</button>
            </div>`;
        layer.appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));

        try { if (window.CineSound && CineSound.notify) CineSound.notify(); } catch (e) {}

        const dismiss = () => { el.classList.remove('show'); setTimeout(() => el.remove(), 280); };
        el.querySelector('.cine-toast-close').addEventListener('click', e => { e.stopPropagation(); dismiss(); });
        el.addEventListener('click', () => { dismiss(); openChat(alert.sender_id, alert.username, alert.avatar); });
        setTimeout(dismiss, 7000);
    }

    // ---------- floating quick chat ----------
    function ensureChat() {
        let m = document.getElementById('cine-quickchat');
        if (m) return m;
        m = document.createElement('div');
        m.id = 'cine-quickchat';
        m.className = 'cine-quickchat';
        m.innerHTML = `
            <div class="cine-qc-card glass-strong">
                <div class="cine-qc-head">
                    <a class="cine-qc-user" id="cine-qc-user" href="#">
                        <span id="cine-qc-avatar"></span>
                        <span>
                            <strong id="cine-qc-name">...</strong>
                            <small id="cine-qc-status"></small>
                        </span>
                    </a>
                    <div class="cine-qc-head-actions">
                        <a class="cine-qc-full" id="cine-qc-full" href="#" title="کردنەوە بە تەواوی"><i class="fas fa-up-right-and-down-left-from-center"></i></a>
                        <button type="button" class="cine-qc-close" title="داخستن">&times;</button>
                    </div>
                </div>
                <div class="cine-qc-messages" id="cine-qc-messages"></div>
                <div class="cine-qc-sticker-picker" id="cine-qc-sticker-picker" style="display:none;"></div>
                <form class="cine-qc-form" id="cine-qc-form">
                    <button type="button" class="cine-qc-sticker" id="cine-qc-sticker-btn" title="ستیکەر"><i class="fas fa-face-smile"></i></button>
                    <input type="text" id="cine-qc-input" placeholder="نامەیەک بنووسە..." autocomplete="off" maxlength="1000">
                    <button type="submit" class="cine-qc-send"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>`;
        document.body.appendChild(m);

        m.querySelector('.cine-qc-close').addEventListener('click', closeChat);
        m.addEventListener('click', e => { if (e.target === m) closeChat(); });

        m.querySelector('#cine-qc-form').addEventListener('submit', e => {
            e.preventDefault();
            const input = m.querySelector('#cine-qc-input');
            const text = input.value.trim();
            if (!text || !openWith) return;
            input.value = '';
            sendMessage(text);
        });

        if (window.CineStickers) {
            CineStickers.attach({
                btn: m.querySelector('#cine-qc-sticker-btn'),
                picker: m.querySelector('#cine-qc-sticker-picker'),
                onSelect: file => {
                    sendMessage('[sticker:' + file + ']');
                    CineStickers.close(m.querySelector('#cine-qc-sticker-picker'));
                }
            });
        }
        return m;
    }

    function sendMessage(text) {
        fetch(`${SITE}/api/messages.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
            body: JSON.stringify({ action: 'send', receiver_id: openWith, message: text })
        }).then(r => r.json()).then(d => {
            if (!d.success) alert(d.message || 'نەتوانرا بنێردرێت');
            loadThread();
        });
    }

    function renderMessages(msgs, meId, append) {
        const box = document.getElementById('cine-qc-messages');
        if (!append) box.innerHTML = '';
        msgs.forEach(msg => {
            const own = parseInt(msg.sender_id, 10) === parseInt(meId, 10);
            const st = String(msg.message).match(/^\[sticker:(.+?)\]$/);
            let content;
            if (st) {
                content = /\.(webm|mp4)$/i.test(st[1])
                    ? `<video src="${SITE}/uploads/stickers/${st[1]}" autoplay loop muted playsinline class="cine-qc-sticker-media"></video>`
                    : `<img src="${SITE}/uploads/stickers/${st[1]}" class="cine-qc-sticker-media">`;
            } else {
                content = String(msg.message).replace(/</g, '&lt;');
            }
            const row = document.createElement('div');
            row.className = 'cine-qc-msg ' + (own ? 'own' : 'other') + (st ? ' sticker' : '');
            row.innerHTML = `<div class="cine-qc-bubble">${content}</div>`;
            box.appendChild(row);
            threadLastId = Math.max(threadLastId, parseInt(msg.id, 10) || 0);
        });
        box.scrollTop = box.scrollHeight;
    }

    function loadThread(initial) {
        if (!openWith) return;
        const after = initial ? 0 : threadLastId;
        fetch(`${SITE}/api/messages.php?action=thread&user_id=${openWith}&after_id=${after}`, { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if (!d.success) return;
                if (initial) threadLastId = 0;
                renderMessages(d.messages || [], d.current_user_id, !initial);
                if (d.other_user) {
                    document.getElementById('cine-qc-status').textContent = d.other_user.is_online ? 'ئۆنلاین' : 'ئۆفلاین';
                    document.getElementById('cine-qc-status').className = d.other_user.is_online ? 'online' : '';
                }
            });
    }

    function openChat(userId, username, avatar) {
        const m = ensureChat();
        openWith = parseInt(userId, 10);
        threadLastId = 0;
        m.querySelector('#cine-qc-name').textContent = username || '';
        m.querySelector('#cine-qc-avatar').innerHTML = avatarHtml(avatar, 38);
        m.querySelector('#cine-qc-user').href = `${SITE}/profile.php?id=${openWith}`;
        m.querySelector('#cine-qc-full').href = `${SITE}/messages.php?with=${openWith}`;
        m.querySelector('#cine-qc-messages').innerHTML = '<div class="cine-qc-loading"><i class="fas fa-spinner fa-spin"></i></div>';
        m.classList.add('open');
        document.body.classList.add('cine-qc-open');
        loadThread(true);
        clearInterval(threadTimer);
        threadTimer = setInterval(() => loadThread(false), 3000);
        setTimeout(() => m.querySelector('#cine-qc-input').focus(), 200);
    }

    function closeChat() {
        const m = document.getElementById('cine-quickchat');
        if (m) m.classList.remove('open');
        document.body.classList.remove('cine-qc-open');
        openWith = 0;
        clearInterval(threadTimer);
    }

    // ---------- polling ----------
    function poll() {
        fetch(`${SITE}/api/dm-alerts.php?after_id=${lastId}`, { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if (!d.success) return;
                const onMessagesPage = /messages\.php/.test(location.pathname);
                (d.alerts || []).forEach(a => {
                    const sid = parseInt(a.sender_id, 10);
                    if (sid === openWith) { loadThread(false); return; }
                    if (onMessagesPage && new URLSearchParams(location.search).get('with') == sid) return;
                    showToast(a);
                });
                lastId = d.last_id || lastId;
                const badges = ['mobile-msg-badge', 'hub-messages-badge'];
                badges.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) { el.textContent = d.unread; el.style.display = d.unread > 0 ? '' : 'none'; }
                });
            }).catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.dataset.loggedIn) return;
        poll();
        setInterval(poll, 5000);
        document.addEventListener('click', e => {
            const trigger = e.target.closest('[data-quickchat]');
            if (!trigger) return;
            e.preventDefault();
            openChat(trigger.dataset.quickchat, trigger.dataset.quickchatName || '', trigger.dataset.quickchatAvatar || '');
        });
    });

    window.CineChat = { open: openChat, close: closeChat };
})();
