<?php
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../includes/functions.php'; requireAdmin();
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>مۆنیتۆری زیندوو - <?= SITE_NAME ?></title><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script>window.SITE_URL='<?= SITE_URL ?>';</script></head><body class="rtl">
<div class="admin-layout"><?php include __DIR__.'/sidebar.php'; ?>
<button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
<main class="admin-main">
<div class="admin-header"><h1><i class="fas fa-satellite-dish"></i> مۆنیتۆری زیندوو (Live Monitor)</h1></div>

<!-- Live Stats -->
<div class="stats-grid" id="live-stats">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-circle" style="color:var(--green)"></i></div><div class="stat-value" id="stat-online">0</div><div class="stat-label">ئۆنلاین ئێستا</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-tv"></i></div><div class="stat-value" id="stat-rooms">0</div><div class="stat-label">ژووری چالاک</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-envelope"></i></div><div class="stat-value" id="stat-msgs">0</div><div class="stat-label">نامەی ٢٤ کاتژمێر</div></div>
</div>

<!-- Online Users -->
<div class="panel-card">
    <h3><i class="fas fa-users" style="color:var(--green)"></i> بەکارهێنەرانی ئۆنلاین</h3>
    <div id="online-users-list" style="min-height:60px;">
        <p class="text-gray" style="padding:20px;text-align:center;">بارکردن...</p>
    </div>
</div>

<!-- Active Watch Rooms -->
<div class="panel-card">
    <h3><i class="fas fa-tv" style="color:var(--gold)"></i> ژوورەکانی Watch Party</h3>
    <div id="active-rooms-list" style="min-height:60px;">
        <p class="text-gray" style="padding:20px;text-align:center;">بارکردن...</p>
    </div>
</div>

</main></div>

<script>
function loadLiveData() {
    // Stats
    fetch(`${window.SITE_URL}/api/activity.php?action=stats`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            if (d.success) {
                document.getElementById('stat-online').textContent = d.online_count;
                document.getElementById('stat-rooms').textContent = d.active_rooms;
                document.getElementById('stat-msgs').textContent = d.messages_24h;
            }
        });
    
    // Online users
    fetch(`${window.SITE_URL}/api/activity.php?action=online_users`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            const el = document.getElementById('online-users-list');
            if (!d.success || d.users.length === 0) { el.innerHTML = '<p class="text-gray" style="padding:20px;text-align:center;">هیچ کەس ئۆنلاین نییە</p>'; return; }
            el.innerHTML = '<table class="admin-table"><thead><tr><th>بەکارهێنەر</th><th>سەیری</th><th>ژوور</th><th>کاتی دوایین</th></tr></thead><tbody>' +
                d.users.map(u => `<tr>
                    <td><i class="fas fa-circle" style="color:var(--green);font-size:8px;margin-left:6px;"></i> ${u.username}</td>
                    <td>${u.watching || '<span class="text-gray">-</span>'}</td>
                    <td>${u.in_room ? `<span class="status-badge status-approved">${u.in_room}</span>` : '<span class="text-gray">-</span>'}</td>
                    <td>${new Date(u.last_seen).toLocaleTimeString('ku')}</td>
                </tr>`).join('') + '</tbody></table>';
        });
    
    // Active rooms
    fetch(`${window.SITE_URL}/api/activity.php?action=active_rooms`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            const el = document.getElementById('active-rooms-list');
            if (!d.success || d.rooms.length === 0) { el.innerHTML = '<p class="text-gray" style="padding:20px;text-align:center;">هیچ ژوورێکی چالاک نییە</p>'; return; }
            el.innerHTML = '<table class="admin-table"><thead><tr><th>ژوور</th><th>فیلم</th><th>هۆست</th><th>ئەندام</th><th>بار</th></tr></thead><tbody>' +
                d.rooms.map(r => `<tr>
                    <td><code>${r.room_id}</code></td>
                    <td>${r.movie_title}</td>
                    <td>${r.host_name}</td>
                    <td>${r.member_count}</td>
                    <td><span class="status-badge ${r.playback_status === 'playing' ? 'status-approved' : 'status-pending'}">${r.playback_status}</span></td>
                </tr>`).join('') + '</tbody></table>';
        });
}

loadLiveData();
setInterval(loadLiveData, 5000);
</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script></body></html>
