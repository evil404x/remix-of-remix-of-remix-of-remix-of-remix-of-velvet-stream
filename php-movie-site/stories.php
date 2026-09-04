<?php
/**
 * SHAH CINEMA - Stories page (dedicated feed + full screen viewer)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$openUser = (int)($_GET['user'] ?? 0);
$pageTitle = 'ستۆرییەکان';

require_once __DIR__ . '/includes/header.php';
?>

<div class="stories-page">
    <div class="stories-head">
        <div class="section-title" style="padding-top:10px;">
            <span class="gold-line"></span>
            <span><i class="fas fa-camera text-gold"></i> ستۆرییەکان</span>
        </div>
        <div class="stories-tools">
            <div class="stories-search">
                <i class="fas fa-search"></i>
                <input type="text" id="stories-search-input" placeholder="گەڕان بەدوای بەکارهێنەر...">
            </div>
            <button class="stories-add" id="stories-add-btn"><i class="fas fa-plus"></i> ستۆری نوێ</button>
        </div>
    </div>

    <div class="stories-rail" id="stories-rail"></div>

    <div class="stories-grid" id="stories-grid">
        <div class="stories-loading"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
</div>

<!-- ============ FULLSCREEN VIEWER ============ -->
<div class="sv-overlay" id="sv-overlay">
    <div class="sv-shell">
        <div class="sv-progress" id="sv-progress"></div>
        <div class="sv-head">
            <a class="sv-user" id="sv-user" href="#">
                <span id="sv-avatar"></span>
                <span><strong id="sv-username"></strong><small id="sv-time"></small></span>
            </a>
            <div class="sv-head-actions">
                <button type="button" id="sv-delete" title="سڕینەوە" style="display:none;"><i class="fas fa-trash"></i></button>
                <button type="button" id="sv-close">&times;</button>
            </div>
        </div>
        <div class="sv-media" id="sv-media"></div>
        <div class="sv-caption" id="sv-caption"></div>
        <div class="sv-nav">
            <div class="sv-prev" id="sv-prev"></div>
            <div class="sv-next" id="sv-next"></div>
        </div>
        <div class="sv-views" id="sv-views" style="display:none;"><i class="fas fa-eye"></i> <span id="sv-view-count">0</span></div>
    </div>
</div>

<!-- ============ CREATOR ============ -->
<div class="sc-modal" id="sc-modal">
    <div class="sc-card glass-strong">
        <div class="sc-head">
            <h3><i class="fas fa-camera"></i> ستۆری نوێ</h3>
            <button type="button" id="sc-close">&times;</button>
        </div>
        <div class="sc-tabs">
            <button type="button" class="sc-tab active" data-sc-tab="media"><i class="fas fa-image"></i> وێنە / ڤیدیۆ</button>
            <button type="button" class="sc-tab" data-sc-tab="text"><i class="fas fa-font"></i> دەق</button>
        </div>

        <div class="sc-pane" id="sc-pane-media">
            <label class="sc-drop" id="sc-drop">
                <input type="file" id="sc-file" accept="image/*,video/mp4,video/webm" hidden>
                <div id="sc-drop-inner">
                    <i class="fas fa-cloud-arrow-up"></i>
                    <span>وێنە یان ڤیدیۆ هەڵبژێرە</span>
                    <small>JPG · PNG · GIF · WebP · MP4 · WebM — حەدی ١٠MB</small>
                </div>
            </label>
        </div>

        <div class="sc-pane" id="sc-pane-text" style="display:none;">
            <div class="sc-text-preview" id="sc-text-preview"><span>دەقەکەت لێرە دەردەکەوێت</span></div>
            <textarea id="sc-text" maxlength="300" rows="3" placeholder="دەقەکەت بنووسە..."></textarea>
            <div class="sc-colors" id="sc-colors"></div>
        </div>

        <input type="text" id="sc-caption" maxlength="200" placeholder="کاپشن (ئارەزوومەندانە)">
        <div class="sc-msg" id="sc-msg"></div>
        <button type="button" class="sc-save" id="sc-save"><i class="fas fa-paper-plane"></i> بڵاوکردنەوە</button>
    </div>
</div>

<script>
const SITE = window.SITE_URL;
const ME = <?= (int)$_SESSION['user_id'] ?>;
let feedUsers = [];
let stories = [], idx = 0, timer = null, ownerId = 0;

function avatarBox(avatar, cls) {
    return (avatar && avatar !== 'default.png')
        ? `<img class="${cls}" src="${SITE}/uploads/avatars/${avatar}" alt="">`
        : `<span class="${cls} sv-fallback"><i class="fas fa-user"></i></span>`;
}

// ---------- feed ----------
function loadFeed(q) {
    fetch(`${SITE}/api/stories.php?action=feed&q=${encodeURIComponent(q || '')}`, {credentials:'same-origin'})
        .then(r => r.json()).then(d => {
            if (!d.success) return;
            feedUsers = d.users || [];
            renderFeed();
        });
}

function renderFeed() {
    const rail = document.getElementById('stories-rail');
    const grid = document.getElementById('stories-grid');
    if (!feedUsers.length) {
        rail.innerHTML = '';
        grid.innerHTML = '<div class="stories-empty"><i class="fas fa-camera"></i><p>هیچ ستۆرییەک نییە</p></div>';
        return;
    }
    rail.innerHTML = feedUsers.map(u => `
        <button type="button" class="stories-rail-item ${u.all_viewed ? 'seen' : ''}" data-open="${u.id}">
            <span class="stories-ring">${avatarBox(u.avatar, 'stories-rail-avatar')}</span>
            <small>${u.is_me ? 'ستۆری من' : u.username}</small>
        </button>`).join('');

    grid.innerHTML = feedUsers.map(u => `
        <div class="stories-card ${u.all_viewed ? 'seen' : ''}" data-open="${u.id}">
            <span class="stories-ring lg">${avatarBox(u.avatar, 'stories-card-avatar')}</span>
            <strong>${u.is_me ? 'ستۆری من' : u.username}</strong>
            <small>${u.story_count} ستۆری · ${u.time_ago}</small>
        </div>`).join('');

    document.querySelectorAll('[data-open]').forEach(el =>
        el.addEventListener('click', () => openUserStories(parseInt(el.dataset.open, 10))));
}

// ---------- viewer ----------
function openUserStories(userId) {
    ownerId = userId;
    fetch(`${SITE}/api/stories.php?action=get_user_stories&user_id=${userId}`, {credentials:'same-origin'})
        .then(r => r.json()).then(d => {
            if (!d.success || !d.stories.length) return;
            stories = d.stories; idx = 0;
            document.getElementById('sv-overlay').classList.add('open');
            document.body.style.overflow = 'hidden';
            show(0);
        });
}

function closeViewer() {
    document.getElementById('sv-overlay').classList.remove('open');
    document.getElementById('sv-media').innerHTML = '';
    document.body.style.overflow = '';
    clearTimeout(timer);
    loadFeed(document.getElementById('stories-search-input').value.trim());
}

function show(i) {
    if (i < 0) return;
    if (i >= stories.length) { closeViewer(); return; }
    idx = i;
    clearTimeout(timer);
    const s = stories[i];

    document.getElementById('sv-progress').innerHTML = stories.map((_, k) =>
        `<span class="sv-bar ${k < i ? 'done' : ''} ${k === i ? 'active' : ''}"><i></i></span>`).join('');
    document.getElementById('sv-username').textContent = s.username;
    document.getElementById('sv-time').textContent = s.time_ago || '';
    document.getElementById('sv-avatar').innerHTML = avatarBox(s.avatar, 'sv-avatar-img');
    document.getElementById('sv-user').href = `${SITE}/profile.php?id=${s.user_id}`;
    document.getElementById('sv-caption').textContent = s.caption || '';

    const isMine = parseInt(s.user_id, 10) === ME;
    document.getElementById('sv-delete').style.display = isMine ? '' : 'none';
    const views = document.getElementById('sv-views');
    views.style.display = isMine ? '' : 'none';
    document.getElementById('sv-view-count').textContent = s.views_count || 0;

    const media = document.getElementById('sv-media');
    let dur = 5000;
    if (s.media_type === 'text') {
        media.innerHTML = `<div class="sv-text" style="background:${s.text_bg_color || '#F5C518'}"><p>${String(s.text_content || '').replace(/</g,'&lt;')}</p></div>`;
    } else if (s.media_type === 'video') {
        media.innerHTML = `<video src="${SITE}/uploads/stories/${s.media_file}" autoplay playsinline controls></video>`;
        dur = 15000;
    } else {
        media.innerHTML = `<img src="${SITE}/uploads/stories/${s.media_file}" alt="">`;
    }

    fetch(`${SITE}/api/stories.php?action=view`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'view', story_id: s.id})
    });

    timer = setTimeout(() => show(idx + 1), dur);
}

document.getElementById('sv-close').addEventListener('click', closeViewer);
document.getElementById('sv-next').addEventListener('click', () => show(idx + 1));
document.getElementById('sv-prev').addEventListener('click', () => show(idx - 1));
document.addEventListener('keydown', e => {
    if (!document.getElementById('sv-overlay').classList.contains('open')) return;
    if (e.key === 'Escape') closeViewer();
    if (e.key === 'ArrowLeft') show(idx + 1);
    if (e.key === 'ArrowRight') show(idx - 1);
});
document.getElementById('sv-delete').addEventListener('click', () => {
    const s = stories[idx];
    if (!s || !confirm('ستۆرییەکە بسڕدرێتەوە؟')) return;
    fetch(`${SITE}/api/stories.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'delete', story_id: s.id})
    }).then(r => r.json()).then(d => {
        if (d.success) { stories.splice(idx, 1); stories.length ? show(Math.min(idx, stories.length - 1)) : closeViewer(); }
        else alert(d.message || 'نەکرا بسڕدرێتەوە');
    });
});

// ---------- creator ----------
const scModal = document.getElementById('sc-modal');
const colors = ['#F5C518','#FF6B6B','#25F4EE','#7C3AED','#22C55E','#0EA5E9','#EC4899','#111111'];
let scType = 'media', scBg = '#F5C518', scFile = null;

document.getElementById('sc-colors').innerHTML = colors.map((c, i) =>
    `<button type="button" class="sc-color ${i === 0 ? 'active' : ''}" data-color="${c}" style="background:${c}"></button>`).join('');
document.querySelectorAll('.sc-color').forEach(b => b.addEventListener('click', () => {
    scBg = b.dataset.color;
    document.querySelectorAll('.sc-color').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    document.getElementById('sc-text-preview').style.background = scBg;
}));

document.querySelectorAll('.sc-tab').forEach(t => t.addEventListener('click', () => {
    scType = t.dataset.scTab;
    document.querySelectorAll('.sc-tab').forEach(x => x.classList.toggle('active', x === t));
    document.getElementById('sc-pane-media').style.display = scType === 'media' ? '' : 'none';
    document.getElementById('sc-pane-text').style.display = scType === 'text' ? '' : 'none';
}));

document.getElementById('sc-text').addEventListener('input', e => {
    const v = e.target.value.trim();
    document.getElementById('sc-text-preview').innerHTML = `<span>${(v || 'دەقەکەت لێرە دەردەکەوێت').replace(/</g,'&lt;')}</span>`;
});

document.getElementById('sc-file').addEventListener('change', e => {
    const f = e.target.files[0];
    if (!f) return;
    if (f.size > 10 * 1024 * 1024) { scMsg('قەبارە زۆر گەورەیە (حەدی ١٠MB)', 'err'); e.target.value = ''; return; }
    scFile = f;
    const url = URL.createObjectURL(f);
    document.getElementById('sc-drop-inner').innerHTML = f.type.startsWith('video')
        ? `<video src="${url}" class="sc-preview" autoplay muted loop playsinline></video>`
        : `<img src="${url}" class="sc-preview">`;
    scMsg('');
});

function scMsg(text, cls) {
    const el = document.getElementById('sc-msg');
    el.textContent = text || '';
    el.className = 'sc-msg ' + (cls || '');
}

function openCreator() { scModal.classList.add('open'); }
function closeCreator() { scModal.classList.remove('open'); }
document.getElementById('stories-add-btn').addEventListener('click', openCreator);
document.getElementById('sc-close').addEventListener('click', closeCreator);
scModal.addEventListener('click', e => { if (e.target === scModal) closeCreator(); });

document.getElementById('sc-save').addEventListener('click', function () {
    const btn = this;
    const caption = document.getElementById('sc-caption').value.trim();
    const fd = new FormData();
    fd.append('caption', caption);

    if (scType === 'text') {
        const text = document.getElementById('sc-text').value.trim();
        if (!text) { scMsg('تکایە دەقێک بنووسە', 'err'); return; }
        fd.append('story_type', 'text');
        fd.append('text_content', text);
        fd.append('text_bg_color', scBg);
    } else {
        if (!scFile) { scMsg('تکایە فایلێک هەڵبژێرە', 'err'); return; }
        fd.append('story_type', 'image');
        fd.append('story_media', scFile);
    }

    btn.disabled = true;
    scMsg('بارکردن...', '');
    fetch(`${SITE}/api/stories.php?action=upload`, { method:'POST', body: fd, credentials:'same-origin' })
        .then(r => r.json()).then(d => {
            btn.disabled = false;
            if (d.success) {
                scMsg('بڵاوکرایەوە!', 'ok');
                setTimeout(() => { closeCreator(); location.reload(); }, 600);
            } else scMsg(d.message || 'هەڵەیەک ڕوویدا', 'err');
        }).catch(() => { btn.disabled = false; scMsg('هەڵەی پەیوەندی — دووبارە هەوڵبدەرەوە', 'err'); });
});

// ---------- search ----------
let searchTimer = null;
document.getElementById('stories-search-input').addEventListener('input', e => {
    clearTimeout(searchTimer);
    const v = e.target.value.trim();
    searchTimer = setTimeout(() => loadFeed(v), 300);
});

loadFeed('');
<?php if ($openUser): ?>setTimeout(() => openUserStories(<?= $openUser ?>), 400);<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
