<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$chatWith = isset($_GET['with']) ? (int)$_GET['with'] : 0;
$activeTab = $_GET['tab'] ?? '';
$previewRequest = isset($_GET['preview_request']) ? (int)$_GET['preview_request'] : 0;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($previewRequest): ?>
<!-- PREVIEW REQUEST MODE - Instagram-style -->
<div class="dm-fullchat" id="dm-fullchat">
    <div class="dm-fullchat-header" id="dm-chat-header">
        <a href="<?= SITE_URL ?>/messages.php?tab=requests" class="dm-back-link"><i class="fas fa-arrow-right"></i></a>
        <div class="dm-fullchat-user">
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $previewRequest ?>" class="dm-fullchat-avatar" id="dm-header-avatar" style="text-decoration:none;cursor:pointer;">
                <i class="fas fa-user-circle"></i>
            </a>
            <div>
                <a href="<?= SITE_URL ?>/profile.php?id=<?= $previewRequest ?>" style="text-decoration:none;color:inherit;">
                    <strong id="dm-chat-username">بارکردن...</strong>
                </a>
            </div>
        </div>
    </div>
    
    <div class="dm-messages" id="dm-messages" style="padding-bottom:0;">
        <p class="text-gray" style="padding:40px;text-align:center;">بارکردنی نامەکان...</p>
    </div>
    
    <!-- Profile card + Accept/Reject/Report -->
    <div id="request-action-panel" style="border-top:1px solid rgba(255,255,255,0.06);padding:0;">
        <!-- User profile card -->
        <div id="request-profile-card" style="text-align:center;padding:20px 16px 10px;background:rgba(0,0,0,0.3);">
            <div id="request-avatar-large" style="width:80px;height:80px;border-radius:50%;border:2px solid rgba(245,197,24,0.2);overflow:hidden;margin:0 auto 10px;background:rgba(10,10,10,0.9);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-user-circle" style="font-size:2.5rem;color:var(--gray);"></i>
            </div>
            <div id="request-username-display" style="font-weight:700;font-size:1rem;margin-bottom:2px;"></div>
            <div id="request-follow-info" style="font-size:0.78rem;color:var(--gray);margin-bottom:6px;"></div>
        </div>
        
        <!-- Notice text -->
        <div style="text-align:center;padding:8px 16px 12px;background:rgba(0,0,0,0.3);">
            <p style="color:var(--gray-light);font-size:0.82rem;line-height:1.6;" id="request-notice-text">
                <strong id="request-sender-name"></strong> داواکاری نامەی بۆ ناردووی.<br>
                تەنها ١ نامە دەتوانن بنێرن هەتا قبوڵ بکەیت. بۆ لابردنی چات سڕینەوە هەڵبژێرە.
            </p>
        </div>
        
        <!-- Action buttons -->
        <div style="display:flex;border-top:1px solid rgba(255,255,255,0.06);">
            <button onclick="reportRequestSender(<?= $previewRequest ?>)" style="flex:1;padding:14px;background:none;border:none;border-left:1px solid rgba(255,255,255,0.06);color:var(--red);font-family:inherit;font-size:0.88rem;font-weight:600;cursor:pointer;">ڕاپۆرت</button>
            <button onclick="rejectRequest(<?= $previewRequest ?>,this)" style="flex:1;padding:14px;background:none;border:none;border-left:1px solid rgba(255,255,255,0.06);color:var(--white);font-family:inherit;font-size:0.88rem;font-weight:600;cursor:pointer;">سڕینەوە</button>
            <button onclick="acceptRequest(<?= $previewRequest ?>,this)" style="flex:1;padding:14px;background:none;border:none;color:var(--gold);font-family:inherit;font-size:0.88rem;font-weight:700;cursor:pointer;">قبوڵکردن</button>
        </div>
    </div>
</div>

<script>
// Load preview request messages
fetch(`${window.SITE_URL}/api/messages.php?action=preview_request&sender_id=<?= $previewRequest ?>`, {credentials:'same-origin'})
    .then(r=>r.json()).then(d => {
        if (!d.success) return;
        const container = document.getElementById('dm-messages');
        container.innerHTML = '';
        
        if (d.sender) {
            document.getElementById('dm-chat-username').textContent = d.sender.username;
            document.getElementById('request-username-display').textContent = d.sender.username;
            document.getElementById('request-sender-name').textContent = d.sender.username;
            if (d.sender.avatar && d.sender.avatar !== 'default.png') {
                document.getElementById('dm-header-avatar').innerHTML = `<img src="${window.SITE_URL}/uploads/avatars/${d.sender.avatar}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">`;
                document.getElementById('request-avatar-large').innerHTML = `<img src="${window.SITE_URL}/uploads/avatars/${d.sender.avatar}" style="width:100%;height:100%;object-fit:cover;">`;
            }
        }
        
        d.messages.forEach(msg => {
            const isOwn = msg.sender_id == d.current_user_id;
            const row = document.createElement('div');
            row.className = 'dm-msg-row ' + (isOwn ? 'own' : 'other');
            const time = new Date(msg.created_at).toLocaleTimeString('ku', {hour:'2-digit',minute:'2-digit'});
            let avatarHtml = '';
            if (!isOwn && d.sender) {
                avatarHtml = `<a href="${window.SITE_URL}/profile.php?id=${msg.sender_id}" class="dm-msg-avatar">
                    ${d.sender.avatar && d.sender.avatar !== 'default.png' ? `<img src="${window.SITE_URL}/uploads/avatars/${d.sender.avatar}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">` : `<div class="dm-avatar-icon"><i class="fas fa-user"></i></div>`}
                </a>`;
            }
            row.innerHTML = `${avatarHtml}<div class="dm-msg ${isOwn ? 'own' : 'other'}"><div class="dm-msg-text">${msg.message}</div><div class="dm-msg-time">${time}</div></div>`;
            container.appendChild(row);
        });
    });

function acceptRequest(senderId, btn) {
    btn.disabled = true;
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'accept_request', sender_id: senderId})
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            if (typeof CineSound !== 'undefined') CineSound.success();
            window.location.href = `${window.SITE_URL}/messages.php?with=${senderId}`;
        }
    });
}

function rejectRequest(senderId, btn) {
    if (!confirm('سڕینەوەی داواکاری نامە؟')) return;
    btn.disabled = true;
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'reject_request', sender_id: senderId})
    }).then(r=>r.json()).then(d => {
        if (d.success) { window.location.href = `${window.SITE_URL}/messages.php?tab=requests`; }
    });
}

function reportRequestSender(userId) {
    alert('ڕاپۆرتەکەت تۆمار کرا');
}
</script>

<?php elseif ($chatWith): ?>
<!-- FULL SCREEN CHAT VIEW -->
<div class="dm-fullchat" id="dm-fullchat">
    <div class="dm-fullchat-header" id="dm-chat-header">
        <a href="<?= SITE_URL ?>/messages.php" class="dm-back-link"><i class="fas fa-arrow-right"></i></a>
        <div class="dm-fullchat-user">
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $chatWith ?>" class="dm-fullchat-avatar" id="dm-header-avatar" style="text-decoration:none;cursor:pointer;" title="پرۆفایل">
                <i class="fas fa-user-circle"></i>
            </a>
            <div>
                <a href="<?= SITE_URL ?>/profile.php?id=<?= $chatWith ?>" style="text-decoration:none;color:inherit;">
                    <strong id="dm-chat-username">بارکردن...</strong>
                </a>
                <div class="dm-fullchat-status">
                    <span class="dm-online-dot" id="dm-online-dot"></span>
                    <span id="dm-status-text" style="font-size:0.75rem;color:var(--gray);">...</span>
                </div>
                <span class="dm-typing-indicator" id="dm-typing" style="display:none;font-size:0.72rem;color:#00BCD4;">تایپ دەکات...</span>
            </div>
        </div>
        <a href="<?= SITE_URL ?>/profile.php?id=<?= $chatWith ?>" class="dm-header-action"><i class="fas fa-user"></i></a>
    </div>
    
    <div class="dm-messages" id="dm-messages">
        <p class="text-gray" style="padding:40px;text-align:center;">بارکردنی نامەکان...</p>
    </div>
    
    <!-- Request notice banner -->
    <div id="dm-request-banner" style="display:none;padding:10px 16px;background:rgba(245,197,24,0.08);border-top:1px solid rgba(245,197,24,0.15);text-align:center;">
        <p style="color:var(--gold);font-size:0.82rem;margin-bottom:8px;"><i class="fas fa-info-circle"></i> نامەکەت بۆ بەشی داواکاریەکان نێردرا. تەنها ١ نامە دەتوانیت بنێریت.</p>
    </div>
    
    <!-- Request limit banner (shown when already sent) -->
    <div id="dm-request-limit-banner" style="display:none;padding:10px 16px;background:rgba(255,82,82,0.08);border-top:1px solid rgba(255,82,82,0.15);text-align:center;">
        <p style="color:var(--red);font-size:0.82rem;"><i class="fas fa-lock"></i> نامەکەت نێردرا. ناتوانیت زیاتر بنێریت هەتا قبوڵ دەکرێت.</p>
    </div>
    
    <form class="dm-send-form" id="dm-send-form">
        <div class="dm-input-wrap">
            <button type="button" id="dm-sticker-btn" style="background:none;border:none;color:var(--gold);font-size:1.1rem;cursor:pointer;padding:8px;" title="ستیکەر"><i class="fas fa-smile"></i></button>
            <input type="text" id="dm-input" placeholder="نامەیەک بنووسە..." autocomplete="off" maxlength="1000" style="font-size:16px !important;">
        </div>
        <button type="submit" class="dm-send-btn"><i class="fas fa-paper-plane"></i></button>
    </form>
    <!-- DM Sticker Picker -->
    <div id="dm-sticker-picker" style="display:none;position:absolute;bottom:65px;left:10px;right:10px;max-height:200px;overflow-y:auto;background:rgba(14,14,14,0.95);border:1px solid rgba(245,197,24,0.15);border-radius:12px;padding:10px;z-index:20;">
        <div id="dm-sticker-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(60px,1fr));gap:8px;"></div>
    </div>
</div>

<?php else: ?>
<!-- ALL USERS LIST VIEW -->
<div class="dm-users-page">
    <div class="dm-users-header">
        <h2><i class="fas fa-comments"></i> نامەکان</h2>
        
        <!-- Tab bar for Messages / Requests -->
        <div style="display:flex;gap:0;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:3px;margin-top:12px;">
            <button class="dm-tab-btn <?= $activeTab !== 'requests' ? 'active' : '' ?>" id="dm-tab-messages" onclick="switchDmTab('messages')" style="flex:1;padding:8px 14px;border:none;background:none;color:var(--gray);font-family:inherit;font-size:0.82rem;font-weight:600;cursor:pointer;border-radius:9px;transition:all 0.3s;">
                <i class="fas fa-comment-dots"></i> گفتوگۆکان
            </button>
            <button class="dm-tab-btn <?= $activeTab === 'requests' ? 'active' : '' ?>" id="dm-tab-requests" onclick="switchDmTab('requests')" style="flex:1;padding:8px 14px;border:none;background:none;color:var(--gray);font-family:inherit;font-size:0.82rem;font-weight:600;cursor:pointer;border-radius:9px;transition:all 0.3s;position:relative;">
                <i class="fas fa-inbox"></i> داواکاریەکان <span id="dm-request-count-badge" style="display:none;background:var(--red);color:#fff;font-size:0.65rem;padding:1px 6px;border-radius:10px;margin-right:4px;">0</span>
            </button>
        </div>
        
        <div class="dm-search-box" style="margin-top:12px;">
            <div class="dm-search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="dm-user-search" placeholder="گەڕان بکە..." autocomplete="off" style="font-size:16px !important;">
            </div>
            <div id="dm-search-results" style="display:none;"></div>
        </div>
    </div>
    
    <!-- Messages tab content -->
    <div class="dm-users-list" id="dm-conversations">
        <div class="dm-users-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>بارکردن...</p>
        </div>
    </div>
    
    <!-- Requests tab content -->
    <div class="dm-users-list" id="dm-requests-list" style="display:none;">
        <div class="dm-users-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>بارکردن...</p>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const dmChatWith = <?= $chatWith ?>;
let dmLastMsgId = 0;
let dmPollInterval = null;

<?php if (!$chatWith && !$previewRequest): ?>
// ===== TAB SWITCHING =====
function switchDmTab(tab) {
    document.querySelectorAll('.dm-tab-btn').forEach(t => {
        t.classList.remove('active');
        t.style.background = '';
        t.style.color = 'var(--gray)';
    });
    if (tab === 'messages') {
        document.getElementById('dm-tab-messages').classList.add('active');
        document.getElementById('dm-tab-messages').style.background = 'rgba(245,197,24,0.1)';
        document.getElementById('dm-tab-messages').style.color = 'var(--gold)';
        document.getElementById('dm-conversations').style.display = '';
        document.getElementById('dm-requests-list').style.display = 'none';
    } else {
        document.getElementById('dm-tab-requests').classList.add('active');
        document.getElementById('dm-tab-requests').style.background = 'rgba(245,197,24,0.1)';
        document.getElementById('dm-tab-requests').style.color = 'var(--gold)';
        document.getElementById('dm-conversations').style.display = 'none';
        document.getElementById('dm-requests-list').style.display = '';
        loadPendingRequests();
    }
}

(function(){
    const activeTab = document.querySelector('.dm-tab-btn.active');
    if (activeTab) { activeTab.style.background = 'rgba(245,197,24,0.1)'; activeTab.style.color = 'var(--gold)'; }
})();

function loadConversations() {
    fetch(`${window.SITE_URL}/api/messages.php?action=all_users`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            if (!d.success) return;
            const list = document.getElementById('dm-conversations');
            
            // Only show users with conversations
            const withMessages = d.users.filter(u => u.has_conversation);
            
            if (withMessages.length === 0) {
                list.innerHTML = '<div class="dm-users-empty"><i class="fas fa-comment-dots" style="font-size:2.5rem;color:var(--gray);margin-bottom:14px;display:block;"></i><p style="color:var(--gray);">هیچ گفتوگۆیەکت نییە</p><p style="color:var(--gray);font-size:0.8rem;margin-top:6px;">لە بەشی گەڕان بەکارهێنەرێک بدۆزەوە و نامەیەک بنێرە</p></div>';
                return;
            }
            
            let html = '';
            withMessages.forEach((u, i) => { html += renderUserItem(u, i * 0.04); });
            list.innerHTML = html;
        });
    
    fetch(`${window.SITE_URL}/api/messages.php?action=unread_total`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            if (d.success && d.pending_count > 0) {
                const badge = document.getElementById('dm-request-count-badge');
                if (badge) { badge.textContent = d.pending_count; badge.style.display = 'inline'; }
            }
        });
}

function loadPendingRequests() {
    fetch(`${window.SITE_URL}/api/messages.php?action=pending_messages`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            const list = document.getElementById('dm-requests-list');
            if (!d.success || !d.pending || d.pending.length === 0) {
                list.innerHTML = '<div class="dm-users-empty" style="padding:40px;text-align:center;"><i class="fas fa-inbox" style="font-size:2rem;color:var(--gray);margin-bottom:12px;display:block;"></i><p style="color:var(--gray);">هیچ داواکاریەکی نامە نییە</p></div>';
                return;
            }
            list.innerHTML = d.pending.map(p => `
                <a href="${window.SITE_URL}/messages.php?preview_request=${p.user_id}" class="dm-user-item" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.03);text-decoration:none;color:inherit;">
                    <div class="dm-user-avatar" style="position:relative;">
                        ${p.avatar ? `<img src="${window.SITE_URL}/uploads/avatars/${p.avatar}" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">` : `<i class="fas fa-user-circle" style="font-size:2.2rem;color:var(--gray);"></i>`}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <strong>${p.username}</strong>
                        <p style="color:var(--gray);font-size:0.8rem;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${p.first_message || p.last_message || ''}</p>
                        <span style="font-size:0.7rem;color:var(--gray);">${p.request_count} نامە</span>
                    </div>
                    <div style="flex-shrink:0;">
                        <span style="background:rgba(245,197,24,0.1);color:var(--gold);font-size:0.72rem;padding:4px 10px;border-radius:12px;">سەیرکردن</span>
                    </div>
                </a>
            `).join('');
        });
}

function acceptRequest(senderId, btn) {
    btn.disabled = true;
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'accept_request', sender_id: senderId})
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            if (typeof CineSound !== 'undefined') CineSound.success();
            window.location.href = `${window.SITE_URL}/messages.php?with=${senderId}`;
        }
    });
}

function rejectRequest(senderId, btn) {
    if (!confirm('سڕینەوەی داواکاری نامە؟')) return;
    btn.disabled = true;
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'reject_request', sender_id: senderId})
    }).then(r=>r.json()).then(d => {
        if (d.success) { btn.closest('.dm-user-item')?.remove(); loadPendingRequests(); }
    });
}

function renderUserItem(u, delay) {
    const avatarHtml = (u.avatar && u.avatar !== 'default.png') 
        ? `<img src="${window.SITE_URL}/uploads/avatars/${u.avatar}" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid rgba(245,197,24,0.1);">` 
        : `<div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,rgba(245,197,24,0.15),rgba(245,197,24,0.05));display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--gold);border:2px solid rgba(245,197,24,0.1);">${u.username.charAt(0).toUpperCase()}</div>`;
    return `
        <a href="${window.SITE_URL}/messages.php?with=${u.id}" class="dm-user-item" style="animation-delay:${delay}s">
            <div class="dm-user-avatar" style="position:relative;">
                ${avatarHtml}
                <span class="dm-user-status ${u.is_online ? 'online' : 'offline'}"></span>
            </div>
            <div class="dm-user-details">
                <strong>${u.username}</strong>
                ${u.last_message ? `<p class="dm-user-last-msg">${u.last_message.substring(0, 45)}${u.last_message.length > 45 ? '...' : ''}</p>` : `<p class="dm-user-last-msg" style="color:var(--gray);">نامەیەک بنێرە...</p>`}
            </div>
            <div class="dm-user-meta">
                ${u.unread_count > 0 ? `<span class="dm-user-badge">${u.unread_count}</span>` : ''}
                ${u.last_message_time ? `<span class="dm-user-time">${timeAgo(u.last_message_time)}</span>` : ''}
            </div>
        </a>
    `;
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const now = new Date();
    const date = new Date(dateStr);
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'ئێستا';
    if (diff < 3600) return Math.floor(diff / 60) + 'خ';
    if (diff < 86400) return Math.floor(diff / 3600) + 'ک';
    return Math.floor(diff / 86400) + 'ڕ';
}

loadConversations();
setInterval(loadConversations, 8000);

<?php if ($activeTab === 'requests'): ?>
switchDmTab('requests');
<?php endif; ?>

<?php elseif ($chatWith): ?>
// ===== CHAT VIEW =====
function loadThread(afterId) {
    fetch(`${window.SITE_URL}/api/messages.php?action=thread&user_id=${dmChatWith}&after_id=${afterId || 0}`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            if (!d.success) return;
            const container = document.getElementById('dm-messages');
            const header = document.getElementById('dm-chat-username');
            const dot = document.getElementById('dm-online-dot');
            const statusText = document.getElementById('dm-status-text');
            const headerAvatar = document.getElementById('dm-header-avatar');
            
            if (header && d.other_user) {
                header.textContent = d.other_user.username;
                if (dot) dot.className = 'dm-online-dot ' + (d.other_user.is_online ? 'online' : 'offline');
                if (statusText) statusText.textContent = d.other_user.is_online ? 'ئۆنلاین' : 'ئۆفلاین';
                if (headerAvatar && d.other_user.avatar) {
                    headerAvatar.innerHTML = `<img src="${window.SITE_URL}/uploads/avatars/${d.other_user.avatar}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">`;
                }
            }
            
            // Show request limit banner if applicable
            if (d.chat_status === 'request_sent') {
                const limitBanner = document.getElementById('dm-request-limit-banner');
                if (limitBanner) limitBanner.style.display = 'block';
            }
            
            if (afterId === 0 && container) container.innerHTML = '';
            
            let lastDate = '';
            let lastSender = null;
            
            d.messages.forEach(msg => {
                dmLastMsgId = msg.id;
                const isOwn = msg.sender_id == d.current_user_id;
                const msgDate = new Date(msg.created_at);
                const dateStr = msgDate.toLocaleDateString('ku', {month:'short', day:'numeric', year:'numeric'});
                const time = msgDate.toLocaleTimeString('ku', {hour:'2-digit',minute:'2-digit'});
                
                if (dateStr !== lastDate) {
                    lastDate = dateStr;
                    const sep = document.createElement('div');
                    sep.className = 'dm-date-sep';
                    sep.textContent = dateStr;
                    container.appendChild(sep);
                    lastSender = null;
                }
                
                const row = document.createElement('div');
                row.className = 'dm-msg-row ' + (isOwn ? 'own' : 'other');
                
                let avatarHtml = '';
                if (!isOwn) {
                    const showAvatar = lastSender !== msg.sender_id;
                    avatarHtml = `<a href="${window.SITE_URL}/profile.php?id=${msg.sender_id}" class="dm-msg-avatar" style="${showAvatar ? '' : 'visibility:hidden'}">
                        ${d.other_user.avatar ? `<img src="${window.SITE_URL}/uploads/avatars/${d.other_user.avatar}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">` : `<div class="dm-avatar-icon"><i class="fas fa-user"></i></div>`}
                    </a>`;
                }
                
                let seenHtml = '';
                if (isOwn) {
                    seenHtml = msg.is_read 
                        ? '<span class="dm-msg-seen-icon seen"><i class="fas fa-check-double"></i></span>' 
                        : '<span class="dm-msg-seen-icon"><i class="fas fa-check"></i></span>';
                }
                
                // Render stickers larger with bubbled borders
                let msgContent = msg.message;
                const stickerMatch = msgContent.match(/^\[sticker:(.+?)\]$/);
                if (stickerMatch) {
                    const stickerFile = stickerMatch[1];
                    if (stickerFile.endsWith('.webm')) {
                        msgContent = `<video src="${window.SITE_URL}/uploads/stickers/${stickerFile}" style="width:140px;height:140px;object-fit:contain;" autoplay loop muted playsinline></video>`;
                    } else {
                        msgContent = `<img src="${window.SITE_URL}/uploads/stickers/${stickerFile}" style="width:140px;height:140px;object-fit:contain;">`;
                    }
                }
                
                const isStickerMsg = !!stickerMatch;
                const stickerClass = isStickerMsg ? ' dm-sticker-bubble' : '';
                
                let actionsHtml = '';
                if (isOwn && !isStickerMsg) {
                    actionsHtml = `<div class="dm-msg-dots" style="position:absolute;top:4px;${isOwn?'left':'right'}:4px;">
                        <button onclick="toggleDmMenu(this)" style="background:none;border:none;color:var(--gray);cursor:pointer;padding:2px 5px;font-size:0.72rem;opacity:0;transition:opacity 0.2s;" class="dm-dots-btn"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="dm-msg-dropdown" style="display:none;position:absolute;top:100%;${isOwn?'left':'right'}:0;background:rgba(14,14,14,0.95);border:1px solid rgba(245,197,24,0.15);border-radius:10px;padding:4px;z-index:10;min-width:110px;backdrop-filter:blur(20px);box-shadow:0 8px 24px rgba(0,0,0,0.4);">
                            <button onclick="editDmMsg(${msg.id},this)" style="display:flex;align-items:center;gap:6px;width:100%;background:none;border:none;color:var(--gold);cursor:pointer;padding:7px 10px;font-size:0.75rem;border-radius:6px;font-family:inherit;" onmouseover="this.style.background='rgba(245,197,24,0.08)'" onmouseout="this.style.background=''"><i class="fas fa-edit"></i> دەستکاری</button>
                            <button onclick="deleteDmMsg(${msg.id})" style="display:flex;align-items:center;gap:6px;width:100%;background:none;border:none;color:var(--red);cursor:pointer;padding:7px 10px;font-size:0.75rem;border-radius:6px;font-family:inherit;" onmouseover="this.style.background='rgba(255,82,82,0.08)'" onmouseout="this.style.background=''"><i class="fas fa-trash"></i> سڕینەوە</button>
                        </div>
                    </div>`;
                }
                
                row.innerHTML = `${avatarHtml}<div class="dm-msg ${isOwn ? 'own' : 'other'}${stickerClass}" style="position:relative;" onmouseover="var d=this.querySelector('.dm-dots-btn');if(d)d.style.opacity='1'" onmouseout="var d=this.querySelector('.dm-dots-btn');if(d)d.style.opacity='0'">${actionsHtml}<div class="dm-msg-text">${msgContent}</div><div class="dm-msg-time">${time} ${seenHtml}</div></div>`;
                container.appendChild(row);
                lastSender = msg.sender_id;
            });
            
            if (d.messages.length > 0) {
                container.scrollTop = container.scrollHeight;
                if (afterId > 0 && d.messages.some(m => m.sender_id != d.current_user_id)) {
                    if (typeof CineSound !== 'undefined') CineSound.pop();
                }
            }
            
            if (d.messages.length > 0) {
                fetch(`${window.SITE_URL}/api/messages.php`, {
                    method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
                    body: JSON.stringify({action:'mark_seen', user_id: dmChatWith})
                });
            }
        });
}

// Send message
document.getElementById('dm-send-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('dm-input');
    const msg = input.value.trim();
    if (!msg) return;
    
    if (typeof CineSound !== 'undefined') CineSound.swoosh();
    
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({action:'send', receiver_id: dmChatWith, message: msg})
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            input.value = '';
            if (d.is_request) {
                const banner = document.getElementById('dm-request-banner');
                if (banner) banner.style.display = 'block';
                // Also show limit banner
                const limitBanner = document.getElementById('dm-request-limit-banner');
                if (limitBanner) limitBanner.style.display = 'block';
            }
            loadThread(dmLastMsgId);
        } else {
            if (d.is_request_limit) {
                const limitBanner = document.getElementById('dm-request-limit-banner');
                if (limitBanner) limitBanner.style.display = 'block';
            } else {
                alert(d.message || 'هەڵە');
            }
        }
    });
});

loadThread(0);
dmPollInterval = setInterval(() => loadThread(dmLastMsgId), 2000);

// Typing indicator
let typingTimeout;
document.getElementById('dm-input').addEventListener('input', function() {
    clearTimeout(typingTimeout);
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'typing', receiver_id: dmChatWith})
    });
});

setInterval(() => {
    fetch(`${window.SITE_URL}/api/messages.php?action=typing_status&user_id=${dmChatWith}`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            const typingEl = document.getElementById('dm-typing');
            if (typingEl) typingEl.style.display = d.is_typing ? 'block' : 'none';
        });
}, 2000);

// DM Sticker Picker
const dmStickerBtn = document.getElementById('dm-sticker-btn');
const dmStickerPicker = document.getElementById('dm-sticker-picker');
if (dmStickerBtn && dmStickerPicker && window.CineStickers) {
    CineStickers.attach({
        btn: dmStickerBtn,
        picker: dmStickerPicker,
        onSelect: file => sendDmSticker(file)
    });
}

function sendDmSticker(fileName) {
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'send', receiver_id: dmChatWith, message: '[sticker:'+fileName+']'})
    }).then(r=>r.json()).then(d => { if(d.success) { loadThread(dmLastMsgId); if (window.CineStickers) CineStickers.close(dmStickerPicker); else dmStickerPicker.style.display='none'; } });
}

function deleteDmMsg(msgId) {
    if (!confirm('سڕینەوەی نامە؟')) return;
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'delete_message', message_id: msgId})
    }).then(r=>r.json()).then(d => { if(d.success) location.reload(); });
}

function editDmMsg(msgId, btn) {
    const msgEl = btn.closest('.dm-msg');
    const textEl = msgEl.querySelector('.dm-msg-text');
    const oldText = textEl.innerText;
    textEl.innerHTML = `<input type="text" value="${oldText}" style="width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(245,197,24,0.2);border-radius:8px;padding:6px 10px;color:#fff;font-size:0.85rem;font-family:inherit;" onkeydown="if(event.key==='Enter'){saveDmMsg(${msgId},this.value);}" id="edit-dm-${msgId}">`;
    document.getElementById('edit-dm-'+msgId).focus();
}

function saveDmMsg(msgId, newMsg) {
    if (!newMsg.trim()) return;
    fetch(`${window.SITE_URL}/api/messages.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'edit_message', message_id: msgId, message: newMsg.trim()})
    }).then(r=>r.json()).then(d => { if(d.success) location.reload(); });
}
function toggleDmMenu(btn) {
    document.querySelectorAll('.dm-msg-dropdown').forEach(d => {
        if (d !== btn.nextElementSibling) d.style.display = 'none';
    });
    const dropdown = btn.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', e => {
    if (!e.target.closest('.dm-msg-dots')) {
        document.querySelectorAll('.dm-msg-dropdown').forEach(d => d.style.display = 'none');
    }
});
<?php endif; ?>

// User search (both views)
const dmSearch = document.getElementById('dm-user-search');
const dmSearchResults = document.getElementById('dm-search-results');
let dmSearchTimeout;

if (dmSearch) {
    dmSearch.addEventListener('input', function() {
        clearTimeout(dmSearchTimeout);
        const q = this.value.trim();
        if (q.length < 2) { dmSearchResults.style.display = 'none'; return; }
        dmSearchTimeout = setTimeout(() => {
            fetch(`${window.SITE_URL}/api/user-search.php?q=${encodeURIComponent(q)}`, {credentials:'same-origin'})
                .then(r=>r.json()).then(d => {
                    if (!d.results || d.results.length === 0) {
                        dmSearchResults.style.display = 'block';
                        dmSearchResults.innerHTML = '<p style="padding:12px;text-align:center;color:var(--gray);font-size:0.85rem;">هیچ نەدۆزرایەوە</p>';
                        return;
                    }
                    dmSearchResults.style.display = 'block';
                    dmSearchResults.innerHTML = d.results.map(u => {
                        const sAvatar = (u.avatar && u.avatar !== 'default.png') 
                            ? `<img src="${window.SITE_URL}/uploads/avatars/${u.avatar}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">` 
                            : `<div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,rgba(245,197,24,0.15),rgba(245,197,24,0.05));display:flex;align-items:center;justify-content:center;font-size:0.9rem;color:var(--gold);">${u.username.charAt(0).toUpperCase()}</div>`;
                        return `<a href="${window.SITE_URL}/messages.php?with=${u.id}" class="dm-search-result">
                            ${sAvatar}
                            <span>${u.username}</span>
                        </a>`;
                    }).join('');
                });
        }, 300);
    });
}
</script>

<style>
.dm-sticker-bubble { background: transparent !important; border: none !important; box-shadow: none !important; padding: 4px !important; }
.dm-sticker-bubble .dm-msg-text { padding: 0 !important; }
.dm-sticker-bubble .dm-msg-text img,
.dm-sticker-bubble .dm-msg-text video { border-radius: 16px; border: 2px solid rgba(245,197,24,0.12); }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
