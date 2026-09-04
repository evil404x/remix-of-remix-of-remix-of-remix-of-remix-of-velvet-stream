<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$roomId = clean($_GET['id'] ?? '');
if (!$roomId) { header('Location: ' . SITE_URL); exit; }

// Get room info
$wpStmt = $pdo->prepare("SELECT wp.*, m.title as movie_title, m.slug, m.poster, m.backdrop, u.username as host_name 
    FROM watch_parties wp 
    JOIN movies m ON wp.movie_id = m.id 
    JOIN users u ON wp.host_user_id = u.id 
    WHERE wp.room_id = ?");
$wpStmt->execute([$roomId]);
$room = $wpStmt->fetch();

if (!$room) { header('Location: ' . SITE_URL); exit; }

$currentUserId = $_SESSION['user_id'];
$isHost = ($room['host_user_id'] == $currentUserId);

// Get video links (direct MP4 only)
$videoLinks = getVideoLinks($pdo, $room['movie_id']);

// Get host last_seen for online status
$hostOnlineStmt = $pdo->prepare("SELECT last_seen FROM watch_party_members WHERE room_id = ? AND user_id = ?");
$hostOnlineStmt->execute([$roomId, $room['host_user_id']]);
$hostLastSeen = $hostOnlineStmt->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ============ Watch Room - Full Cinema Experience ============ */
* { touch-action: pan-x pan-y; }

.room-page {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: flex;
    flex-direction: column;
    background: #000;
    overflow: hidden;
    height: 100vh;
    height: 100dvh;
}

body:has(.room-page) .navbar,
body:has(.room-page) .mobile-bottom-bar,
body:has(.room-page) .footer,
body:has(.room-page) .smart-hub-bubble { display: none !important; }

/* Top bar */
.room-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 12px;
    background: rgba(0,0,0,0.92);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255,255,255,0.04);
    z-index: 10;
    flex-shrink: 0;
}

.room-topbar-left { display: flex; align-items: center; gap: 10px; }

.room-back {
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%; color: #fff;
    background: rgba(255,255,255,0.06);
    text-decoration: none; transition: all 0.2s;
}
.room-back:hover { background: rgba(255,255,255,0.12); }

.room-movie-info { display: flex; align-items: center; gap: 8px; }
.room-movie-poster { width: 28px; height: 40px; border-radius: 4px; object-fit: cover; border: 1px solid rgba(245,197,24,0.12); }
.room-movie-title { font-size: 0.82rem; font-weight: 600; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.room-host-status {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.68rem; color: var(--gray);
}
.room-host-dot {
    width: 6px; height: 6px; border-radius: 50%;
    transition: background 0.3s;
}
.room-host-dot.online { background: #4ade80; box-shadow: 0 0 6px rgba(74,222,128,0.5); }
.room-host-dot.offline { background: var(--gray); }

.room-live-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(255,59,48,0.12); border: 1px solid rgba(255,59,48,0.2);
    color: #FF3B30; padding: 3px 10px; border-radius: 16px;
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px;
}
.room-live-dot { width: 6px; height: 6px; border-radius: 50%; background: #FF3B30; animation: pulse-dot 1.5s ease-in-out infinite; }

.room-topbar-right { display: flex; align-items: center; gap: 5px; }

.room-action-btn {
    padding: 5px 10px; border-radius: 7px; font-size: 0.76rem;
    border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04);
    color: #fff; cursor: pointer; transition: all 0.2s; font-family: inherit;
    display: flex; align-items: center; gap: 5px;
}
.room-action-btn:hover { background: rgba(255,255,255,0.08); }
.room-action-btn.btn-invite { background: rgba(0,188,212,0.1); border-color: rgba(0,188,212,0.2); color: #00BCD4; }
.room-action-btn.btn-leave { background: rgba(255,59,48,0.08); border-color: rgba(255,59,48,0.15); color: #FF3B30; }
.room-action-btn.btn-toggle-chat { background: rgba(245,197,24,0.08); border-color: rgba(245,197,24,0.15); color: var(--gold); }

/* ===== SPLIT LAYOUT: Video always visible alongside chat ===== */
.room-body { flex: 1; display: flex; overflow: hidden; position: relative; min-height: 0; }

.room-player {
    flex: 1;
    position: relative;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
}

/* HTML5 Video Player */
.room-player video {
    width: 100%; height: 100%;
    border: none; position: absolute; inset: 0;
    background: #000; object-fit: contain;
}

/* Custom player controls overlay */
.cinema-controls {
    position: absolute; bottom: 0; left: 0; right: 0;
    z-index: 10; padding: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.4) 60%, transparent 100%);
    opacity: 0; transition: opacity 0.3s ease;
    display: flex; flex-direction: column; gap: 0;
}
.room-player:hover .cinema-controls,
.cinema-controls.visible { opacity: 1; }

/* Enhanced seek bar */
.cinema-progress {
    width: 100%; height: 5px; cursor: pointer;
    background: rgba(255,255,255,0.15); position: relative;
    transition: height 0.15s ease;
}
.cinema-progress:hover { height: 10px; }
.cinema-progress-buffer {
    position: absolute; top: 0; left: 0; height: 100%;
    background: rgba(255,255,255,0.1); pointer-events: none;
}
.cinema-progress-filled {
    height: 100%; background: linear-gradient(90deg, var(--gold), #e6a800);
    border-radius: 0 2px 2px 0; transition: width 0.1s linear; position: relative;
}
.cinema-progress-filled::after {
    content: ''; position: absolute; right: -6px; top: 50%; transform: translateY(-50%);
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--gold); box-shadow: 0 0 10px rgba(245,197,24,0.5);
    opacity: 0; transition: opacity 0.15s;
}
.cinema-progress:hover .cinema-progress-filled::after { opacity: 1; }

/* Seek preview tooltip */
.cinema-seek-tooltip {
    position: absolute; bottom: 100%; left: 0;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.85); color: #fff;
    padding: 3px 8px; border-radius: 4px;
    font-size: 0.72rem; font-variant-numeric: tabular-nums;
    pointer-events: none; display: none; white-space: nowrap;
}
.cinema-progress:hover .cinema-seek-tooltip { display: block; }

.cinema-controls-bar {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 16px 12px;
}

.cinema-btn {
    background: none; border: none; color: #fff; cursor: pointer;
    font-size: 1.1rem; padding: 6px; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
}
.cinema-btn:hover { color: var(--gold); transform: scale(1.15); }
.cinema-btn.gold { color: var(--gold); }

.cinema-time {
    font-size: 0.78rem; color: rgba(255,255,255,0.8);
    font-variant-numeric: tabular-nums; user-select: none;
    min-width: 90px;
}

.cinema-spacer { flex: 1; }

.cinema-volume-wrap {
    display: flex; align-items: center; gap: 6px;
}
.cinema-volume-slider {
    width: 70px; height: 3px; -webkit-appearance: none; appearance: none;
    background: rgba(255,255,255,0.2); border-radius: 2px; outline: none;
    cursor: pointer; transition: all 0.2s;
}
.cinema-volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none; width: 12px; height: 12px;
    border-radius: 50%; background: var(--gold); cursor: pointer;
    box-shadow: 0 0 6px rgba(245,197,24,0.4);
}

/* Host badge on controls */
.cinema-host-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 12px;
    background: rgba(245,197,24,0.1); border: 1px solid rgba(245,197,24,0.2);
    color: var(--gold); font-size: 0.7rem; font-weight: 700;
}

/* Guest sync status on controls */
.cinema-sync-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 12px;
    font-size: 0.7rem; font-weight: 600;
    transition: all 0.3s;
}
.cinema-sync-badge.synced { background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.15); color: #4ade80; }
.cinema-sync-badge.syncing { background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.15); color: #fbbf24; }

/* Join Live Cinema Overlay */
.cinema-join-overlay {
    position: absolute; inset: 0; z-index: 30;
    background: rgba(0,0,0,0.92); backdrop-filter: blur(20px);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    cursor: pointer; animation: fadeIn 0.4s ease;
}
.cinema-join-icon {
    width: 100px; height: 100px; border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), #e6a800);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 24px;
    box-shadow: 0 0 60px rgba(245,197,24,0.25), 0 0 120px rgba(245,197,24,0.1);
    animation: pulse-dot 2s ease-in-out infinite;
    transition: transform 0.3s ease;
}
.cinema-join-overlay:hover .cinema-join-icon { transform: scale(1.08); }
.cinema-join-icon i { font-size: 2.5rem; color: #000; margin-left: 6px; }
.cinema-join-title {
    font-size: 1.3rem; color: #fff; font-weight: 800;
    margin-bottom: 8px; letter-spacing: 0.5px;
}
.cinema-join-sub {
    font-size: 0.85rem; color: var(--gray); max-width: 300px;
    text-align: center; line-height: 1.6;
}
.cinema-join-host {
    margin-top: 16px; padding: 6px 16px; border-radius: 20px;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);
    color: var(--gold); font-size: 0.78rem; font-weight: 600;
}

/* No video fallback */
.cinema-no-video {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 12px; color: var(--gray);
}
.cinema-no-video i { font-size: 3rem; opacity: 0.3; }

/* Sidebar Chat - Glass panel - always visible alongside video */
.room-sidebar {
    width: 320px;
    display: flex;
    flex-direction: column;
    background: rgba(6,6,6,0.95);
    backdrop-filter: blur(20px);
    border-left: 1px solid rgba(255,255,255,0.04);
    transition: width 0.3s ease, opacity 0.3s ease;
    overflow: hidden;
    flex-shrink: 0;
}
.room-sidebar.hidden { width: 0; border: none; opacity: 0; pointer-events: none; }

.room-sidebar-tabs { display: flex; border-bottom: 1px solid rgba(255,255,255,0.04); flex-shrink: 0; }

.room-tab {
    flex: 1; padding: 10px; text-align: center; font-size: 0.8rem;
    font-weight: 600; color: var(--gray); cursor: pointer; transition: all 0.2s;
    border: none; background: none; font-family: inherit; position: relative;
}
.room-tab.active { color: #00BCD4; }
.room-tab.active::after {
    content: ''; position: absolute; bottom: 0; left: 20%; width: 60%;
    height: 2px; background: #00BCD4; border-radius: 1px;
}
.room-tab:hover { color: #fff; }

.room-chat-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; position: relative; }
.room-chat-panel.hidden { display: none; }

.room-chat-messages {
    flex: 1; overflow-y: auto; padding: 10px;
    display: flex; flex-direction: column; gap: 4px;
}

.room-chat-msg {
    padding: 6px 10px; border-radius: 10px;
    background: rgba(255,255,255,0.02); font-size: 0.8rem;
    animation: fadeInUp 0.2s ease;
}
.room-chat-msg .msg-user { font-weight: 600; color: #00BCD4; font-size: 0.72rem; margin-bottom: 1px; }
.room-chat-msg .msg-text { color: var(--gray-light); line-height: 1.4; }
.room-chat-msg .msg-time { font-size: 0.63rem; color: var(--gray); margin-top: 1px; }
.room-chat-msg.own { background: rgba(0,188,212,0.05); }
.room-chat-msg.own .msg-user { color: var(--gold); }
.room-chat-msg.system { background: rgba(245,197,24,0.03); text-align: center; color: var(--gray); font-size: 0.76rem; padding: 8px; }

.room-chat-form {
    display: flex; gap: 6px; padding: 8px 10px;
    border-top: 1px solid rgba(255,255,255,0.04);
    background: rgba(3,3,3,0.6); direction: rtl; flex-shrink: 0;
}
.room-chat-form input {
    flex: 1; padding: 9px 12px;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 18px; color: #fff; font-family: inherit;
    font-size: 16px !important; outline: none; transition: all 0.2s;
}
.room-chat-form input:focus { border-color: rgba(0,188,212,0.25); }

.room-chat-send {
    background: linear-gradient(135deg, #00BCD4, #0097A7);
    border: none; color: #fff; border-radius: 50%;
    width: 36px; height: 36px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all 0.2s;
}
.room-chat-send:hover { transform: scale(1.05); }

/* Members panel */
.room-members-panel { flex: 1; display: none; flex-direction: column; overflow: hidden; }
.room-members-panel.active { display: flex; }
.room-members-list { flex: 1; overflow-y: auto; padding: 6px; }
.room-member {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: 8px; transition: all 0.2s;
}
.room-member:hover { background: rgba(255,255,255,0.03); }
.room-member-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(255,255,255,0.05); display: flex;
    align-items: center; justify-content: center;
    color: var(--gray); font-size: 0.9rem; position: relative;
}
.room-member-online {
    position: absolute; bottom: 0; right: 0;
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--green); border: 2px solid rgba(6,6,6,0.95);
}
.room-member-name { font-size: 0.83rem; flex: 1; }
.room-member-host {
    font-size: 0.63rem; background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--black); padding: 2px 7px; border-radius: 8px; font-weight: 700;
}
.room-member-count {
    padding: 7px 12px; font-size: 0.78rem; color: var(--gray);
    border-bottom: 1px solid rgba(255,255,255,0.03); flex-shrink: 0;
}

/* Sync Toast Notification */
.room-sync-toast {
    position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
    z-index: 9999; padding: 12px 20px; border-radius: 12px;
    background: rgba(10,10,10,0.95); backdrop-filter: blur(20px);
    border: 1px solid rgba(245,197,24,0.15);
    color: #fff; font-size: 0.85rem; font-weight: 500;
    display: flex; align-items: center; gap: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    animation: slideDownToast 0.4s ease;
    max-width: 90vw;
}
.room-sync-toast .toast-icon { font-size: 1.1rem; }
.room-sync-toast .toast-text { flex: 1; line-height: 1.4; }
.room-sync-toast .toast-close {
    background: none; border: none; color: var(--gray); cursor: pointer;
    font-size: 1rem; padding: 0 0 0 4px;
}

@keyframes slideDownToast {
    from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

/* Room Create Modal */
.room-create-modal {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(10px);
    animation: fadeIn 0.3s ease;
}
.room-create-modal-content {
    background: rgba(14,14,14,0.95); border: 1px solid rgba(245,197,24,0.15);
    border-radius: 16px; padding: 30px; max-width: 380px; width: 90%;
    text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}
.room-create-modal h3 { font-size: 1.1rem; margin-bottom: 8px; color: #fff; }
.room-create-modal p { font-size: 0.82rem; color: var(--gray); margin-bottom: 20px; }
.room-type-options { display: flex; gap: 12px; margin-bottom: 20px; }
.room-type-option {
    flex: 1; padding: 18px 12px; border-radius: 12px; cursor: pointer;
    border: 2px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02);
    transition: all 0.3s; text-align: center;
}
.room-type-option:hover { border-color: rgba(245,197,24,0.3); }
.room-type-option.selected { border-color: var(--gold); background: rgba(245,197,24,0.06); }
.room-type-option i { font-size: 1.5rem; display: block; margin-bottom: 8px; }
.room-type-option .type-label { font-size: 0.85rem; font-weight: 600; }
.room-type-option .type-desc { font-size: 0.72rem; color: var(--gray); margin-top: 4px; }

/* Resume Session Banner */
.room-resume-banner {
    position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
    z-index: 1100; padding: 12px 20px; border-radius: 14px;
    background: rgba(10,10,10,0.95); backdrop-filter: blur(20px);
    border: 1px solid rgba(245,197,24,0.2);
    display: flex; align-items: center; gap: 14px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    animation: slideDownToast 0.5s ease;
    max-width: 90vw;
}
.room-resume-banner .resume-info { flex: 1; }
.room-resume-banner .resume-title { font-size: 0.85rem; font-weight: 600; color: #fff; }
.room-resume-banner .resume-sub { font-size: 0.72rem; color: var(--gray); margin-top: 2px; }
.room-resume-btn { padding: 6px 14px; border-radius: 8px; font-size: 0.78rem; cursor: pointer; border: none; font-family: inherit; font-weight: 600; }
.room-resume-btn.resume-go { background: var(--gold); color: #000; }
.room-resume-btn.resume-dismiss { background: rgba(255,255,255,0.06); color: var(--gray); margin-right: 6px; }

/* ========= Mobile - Video stays visible, chat slides as bottom sheet ========= */
@media (max-width: 768px) {
    .room-body { flex-direction: column; }
    .room-player { flex: none; height: 35vh; min-height: 200px; }
    .room-sidebar {
        flex: 1; width: 100% !important;
        border-left: none;
        border-top: 1px solid rgba(255,255,255,0.06);
        border-radius: 0;
        transition: none;
    }
    .room-sidebar.hidden { display: none; width: 100% !important; opacity: 1; pointer-events: auto; }
    .room-movie-title { max-width: 90px; font-size: 0.76rem; }
    .room-topbar { padding: 5px 8px; }
    .room-topbar-left { gap: 6px; }
    .room-topbar-right { gap: 3px; }
    .room-action-btn { padding: 4px 7px; font-size: 0.7rem; }
    .room-movie-poster { width: 22px; height: 32px; }
    .room-live-badge { padding: 2px 6px; font-size: 0.66rem; }
    .room-chat-form { padding-bottom: max(8px, env(safe-area-inset-bottom)); }
    .room-chat-form input { font-size: 16px !important; }
    .mobile-bottom-bar { display: none !important; }
    .room-action-btn .btn-text-desktop { display: none; }
    .cinema-volume-wrap { display: none; }
    .cinema-controls-bar { padding: 6px 10px 8px; gap: 6px; }
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
</style>

<!-- ==================== ROOM UI ==================== -->
<div class="room-page" id="room-page">
    <!-- Top Bar -->
    <div class="room-topbar">
        <div class="room-topbar-left">
            <a href="<?= SITE_URL ?>/movie/<?= $room['slug'] ?>" class="room-back"><i class="fas fa-arrow-right"></i></a>
            <div class="room-movie-info">
                <img src="<?= SITE_URL ?>/uploads/posters/<?= $room['poster'] ?: 'default.jpg' ?>" class="room-movie-poster" alt="">
                <div>
                    <div class="room-movie-title"><?= clean($room['movie_title']) ?></div>
                    <div class="room-host-status">
                        <span class="room-host-dot online" id="host-status-dot"></span>
                        <span id="host-status-name"><?= clean($room['host_name']) ?></span>
                    </div>
                </div>
            </div>
            <span class="room-live-badge"><span class="room-live-dot"></span> LIVE</span>
        </div>
        <div class="room-topbar-right">
            <button class="room-action-btn btn-toggle-chat" id="toggle-chat-btn" title="چات"><i class="fas fa-comments"></i></button>
            <?php if ($isHost): ?>
            <button class="room-action-btn" id="room-public-toggle" title="گشتی/تایبەت" style="color:var(--green);">
                <i class="fas fa-globe"></i> <span class="btn-text-desktop" id="room-public-label">گشتی</span>
            </button>
            <?php endif; ?>
            <button class="room-action-btn btn-invite" id="room-invite-btn"><i class="fas fa-user-plus"></i> <span class="btn-text-desktop">بانگهێشت</span></button>
            <button class="room-action-btn" id="room-copy-link" data-link="<?= SITE_URL ?>/room.php?id=<?= $roomId ?>"><i class="fas fa-link"></i></button>
            <button class="room-action-btn btn-leave" id="room-leave-btn"><i class="fas fa-sign-out-alt"></i></button>
        </div>
    </div>
    
    <!-- Body: Player + Sidebar -->
    <div class="room-body">
        <!-- Player -->
        <div class="room-player" id="room-player">
            <?php if (!empty($videoLinks)): ?>
                <?php 
                    $firstLink = reset($videoLinks); 
                    $videoUrl = $firstLink['video_url'];
                    // Group room video links by language for server switching
                    $roomLinksByLang = [];
                    foreach ($videoLinks as $vl) {
                        $roomLinksByLang[$vl['language']][] = $vl;
                    }
                ?>
                <video id="room-video" preload="auto" playsinline>
                    <source src="<?= clean($videoUrl) ?>" type="video/mp4">
                </video>
                
                <!-- Room Server Switcher -->
                <?php if (count($videoLinks) > 1): ?>
                <div id="room-server-panel" style="position:absolute;top:8px;left:8px;z-index:15;display:flex;gap:4px;flex-wrap:wrap;">
                    <?php foreach ($videoLinks as $vi => $rvl): ?>
                    <button class="room-server-btn <?= $vi === 0 ? 'active' : '' ?>" data-url="<?= clean($rvl['video_url']) ?>" style="padding:4px 10px;border-radius:8px;font-size:0.7rem;font-weight:600;border:1px solid rgba(255,255,255,0.1);background:<?= $vi === 0 ? 'rgba(245,197,24,0.2)' : 'rgba(0,0,0,0.6)' ?>;color:<?= $vi === 0 ? 'var(--gold)' : '#fff' ?>;cursor:pointer;backdrop-filter:blur(10px);font-family:inherit;transition:all 0.2s;">
                        <i class="fas fa-server" style="margin-left:3px;font-size:0.6rem;"></i> <?= clean($rvl['server_name']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Custom Cinema Controls -->
                <div class="cinema-controls" id="cinema-controls">
                    <div class="cinema-progress" id="cinema-progress">
                        <div class="cinema-progress-buffer" id="cinema-progress-buffer"></div>
                        <div class="cinema-progress-filled" id="cinema-progress-filled" style="width:0%"></div>
                        <div class="cinema-seek-tooltip" id="cinema-seek-tooltip">0:00</div>
                    </div>
                    <div class="cinema-controls-bar">
                        <button class="cinema-btn" id="cinema-play-btn"><i class="fas fa-play"></i></button>
                        <div class="cinema-time" id="cinema-time">0:00 / 0:00</div>
                        <div class="cinema-spacer"></div>
                        <?php if ($isHost): ?>
                            <span class="cinema-host-badge"><i class="fas fa-crown"></i> هۆست</span>
                        <?php else: ?>
                            <span class="cinema-sync-badge synced" id="cinema-sync-badge"><i class="fas fa-check-circle"></i> سینک</span>
                        <?php endif; ?>
                        <div class="cinema-volume-wrap">
                            <button class="cinema-btn" id="cinema-vol-btn"><i class="fas fa-volume-up"></i></button>
                            <input type="range" class="cinema-volume-slider" id="cinema-vol-slider" min="0" max="1" step="0.05" value="1">
                        </div>
                        <button class="cinema-btn" id="cinema-fullscreen-btn"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                
                <?php if (!$isHost): ?>
                <!-- Join Live Cinema overlay -->
                <div class="cinema-join-overlay" id="cinema-join-overlay">
                    <div class="cinema-join-icon"><i class="fas fa-play"></i></div>
                    <div class="cinema-join-title">Join Live Cinema</div>
                    <div class="cinema-join-sub">کلیک بکە بۆ پەیوەستبوون بە ژووری سینەما و هاوکاتکردن لەگەڵ هۆست</div>
                    <div class="cinema-join-host"><i class="fas fa-crown"></i> هۆست: <?= clean($room['host_name']) ?></div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="cinema-no-video"><i class="fas fa-film"></i><p>هیچ لینکی ڤیدیۆ نییە</p></div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="room-sidebar" id="room-sidebar">
            <div class="room-sidebar-tabs">
                <button class="room-tab active" onclick="switchRoomTab('chat')"><i class="fas fa-comments"></i> چات</button>
                <button class="room-tab" onclick="switchRoomTab('members')"><i class="fas fa-users"></i> ئەندامان <span id="room-member-badge" style="font-size:0.66rem;color:#00BCD4;margin-right:3px;">0</span></button>
            </div>
            
            <div class="room-chat-panel" id="room-chat-panel">
                <div class="room-chat-messages" id="room-chat-messages">
                    <div class="room-chat-msg system">بەخێر بێیت بۆ ژووری سەیرکردن! 🎬</div>
                </div>
                <form class="room-chat-form" id="room-chat-form">
                    <button type="button" class="room-sticker-btn" id="room-sticker-btn" style="background:none;border:none;color:var(--gold);font-size:1.1rem;cursor:pointer;padding:6px;" title="ستیکەر"><i class="fas fa-smile"></i></button>
                    <input type="text" id="room-chat-input" placeholder="نامەیەک بنووسە..." autocomplete="off" maxlength="500">
                    <button type="submit" class="room-chat-send"><i class="fas fa-paper-plane"></i></button>
                </form>
                <!-- Sticker Picker -->
                <div id="room-sticker-picker" style="display:none;position:absolute;bottom:55px;left:10px;right:10px;max-height:200px;overflow-y:auto;background:rgba(14,14,14,0.95);border:1px solid rgba(245,197,24,0.15);border-radius:12px;padding:10px;z-index:20;">
                    <div id="room-sticker-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(60px,1fr));gap:8px;"></div>
                </div>
            </div>
            
            <div class="room-members-panel" id="room-members-panel">
                <div class="room-member-count" id="room-members-count">بارکردن...</div>
                <div class="room-members-list" id="room-members-list"></div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="room-id" value="<?= $roomId ?>">
<input type="hidden" id="room-user-id" value="<?= $currentUserId ?>">
<input type="hidden" id="room-is-host" value="<?= $isHost ? '1' : '0' ?>">

<script>
const ROOM_ID = '<?= $roomId ?>';
const ROOM_USER_ID = <?= $currentUserId ?>;
const IS_HOST = <?= $isHost ? 'true' : 'false' ?>;
let roomLastChatId = 0;
let chatVisible = true;

// ============ ROOM SERVER SWITCHING ============
document.querySelectorAll('.room-server-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const url = this.dataset.url;
        if (!url || !video) return;
        document.querySelectorAll('.room-server-btn').forEach(b => {
            b.classList.remove('active');
            b.style.background = 'rgba(0,0,0,0.6)';
            b.style.color = '#fff';
        });
        this.classList.add('active');
        this.style.background = 'rgba(245,197,24,0.2)';
        this.style.color = 'var(--gold)';
        const currentTime = video.currentTime;
        const wasPlaying = !video.paused;
        video.src = url;
        video.load();
        video.addEventListener('loadedmetadata', function onLoad() {
            video.currentTime = currentTime;
            if (wasPlaying) video.play().catch(()=>{});
            video.removeEventListener('loadedmetadata', onLoad);
        });
    });
});

// ============ TOGGLE CHAT SIDEBAR ============
document.getElementById('toggle-chat-btn').addEventListener('click', function() {
    const sidebar = document.getElementById('room-sidebar');
    chatVisible = !chatVisible;
    sidebar.classList.toggle('hidden', !chatVisible);
    this.style.color = chatVisible ? 'var(--gold)' : 'var(--gray)';
});

// ============ TAB SWITCHING ============
function switchRoomTab(tab) {
    document.querySelectorAll('.room-tab').forEach((t,i) => {
        t.classList.toggle('active', (tab === 'chat' && i === 0) || (tab === 'members' && i === 1));
    });
    document.getElementById('room-chat-panel').classList.toggle('hidden', tab !== 'chat');
    document.getElementById('room-members-panel').classList.toggle('active', tab === 'members');
    if (tab === 'members') loadRoomMembers();
}

// ============ HEARTBEAT ============
function roomHeartbeat() {
    fetch(`${window.SITE_URL}/api/watch-party.php`, {
        method: 'POST', headers: {'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'join', room_id: ROOM_ID})
    });
}

// ============ HOST ONLINE STATUS ============
function updateHostStatus(members) {
    const dot = document.getElementById('host-status-dot');
    if (!dot) return;
    // Host is online if they have a member entry (heartbeat keeps them alive)
    const hostMember = members.find(m => m.is_host);
    if (hostMember) {
        dot.className = 'room-host-dot online';
    } else {
        dot.className = 'room-host-dot offline';
    }
}

// ============ MEMBERS ============
function loadRoomMembers() {
    fetch(`${window.SITE_URL}/api/watch-party.php?action=members&room_id=${ROOM_ID}`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            if (!d.success) return;
            document.getElementById('room-member-badge').textContent = d.members.length;
            document.getElementById('room-members-count').textContent = d.members.length + ' ئەندام ئۆنلاین';
            document.getElementById('room-members-list').innerHTML = d.members.map(m => `
                <div class="room-member">
                    <div class="room-member-avatar"><i class="fas fa-user"></i><span class="room-member-online"></span></div>
                    <span class="room-member-name">${m.username}</span>
                    ${m.is_host ? '<span class="room-member-host">هۆست</span>' : ''}
                </div>
            `).join('');
            // Update host online status
            updateHostStatus(d.members);
        });
}

// ============ CHAT ============
function loadRoomChat() {
    fetch(`${window.SITE_URL}/api/watch-party.php?action=chat_poll&room_id=${ROOM_ID}&after=${roomLastChatId}`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            if (!d.success || d.messages.length === 0) return;
            const container = document.getElementById('room-chat-messages');
            d.messages.forEach(msg => {
                roomLastChatId = msg.id;
                const isOwn = msg.user_id == d.current_user_id;
                const div = document.createElement('div');
                div.className = 'room-chat-msg' + (isOwn ? ' own' : '');
                const time = new Date(msg.created_at).toLocaleTimeString('ku', {hour:'2-digit',minute:'2-digit'});
                // Render stickers as images or video (webm)
                let msgContent = msg.message;
                const stickerMatch = msgContent.match(/^\[sticker:(.+?)\]$/);
                if (stickerMatch) {
                    const stickerFile = stickerMatch[1];
                    if (stickerFile.endsWith('.webm')) {
                        msgContent = `<video src="${window.SITE_URL}/uploads/stickers/${stickerFile}" style="width:80px;height:80px;object-fit:contain;" autoplay loop muted playsinline></video>`;
                    } else {
                        msgContent = `<img src="${window.SITE_URL}/uploads/stickers/${stickerFile}" style="width:80px;height:80px;object-fit:contain;">`;
                    }
                }
                div.innerHTML = `<div class="msg-user">${msg.username}</div><div class="msg-text">${msgContent}</div><div class="msg-time">${time}</div>`;
                container.appendChild(div);
            });
            container.scrollTop = container.scrollHeight;
            if (d.messages.some(m => m.user_id != d.current_user_id) && typeof CineSound !== 'undefined') CineSound.pop();
        });
}

document.getElementById('room-chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('room-chat-input');
    const msg = input.value.trim();
    if (!msg) return;
    if (typeof CineSound !== 'undefined') CineSound.swoosh();
    fetch(`${window.SITE_URL}/api/watch-party.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'chat_send', room_id: ROOM_ID, message: msg})
    }).then(r=>r.json()).then(d => { if(d.success) { input.value=''; loadRoomChat(); } });
});

// ======================================================
// ============ HTML5 CINEMA PLAYER + MASTER SYNC =======
// ======================================================
const video = document.getElementById('room-video');
const playBtn = document.getElementById('cinema-play-btn');
const timeDisplay = document.getElementById('cinema-time');
const progressBar = document.getElementById('cinema-progress');
const progressFilled = document.getElementById('cinema-progress-filled');
const progressBuffer = document.getElementById('cinema-progress-buffer');
const seekTooltip = document.getElementById('cinema-seek-tooltip');
const volBtn = document.getElementById('cinema-vol-btn');
const volSlider = document.getElementById('cinema-vol-slider');
const fullscreenBtn = document.getElementById('cinema-fullscreen-btn');
const controlsEl = document.getElementById('cinema-controls');
let controlsTimeout;

// Format time helper
function fmtTime(s) {
    s = Math.floor(s || 0);
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    return h > 0 ? h+':'+String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0') : m+':'+String(sec).padStart(2,'0');
}

if (video) {
    // === CUSTOM CONTROLS ===
    video.addEventListener('loadedmetadata', () => updateTimeDisplay());
    video.addEventListener('timeupdate', () => {
        if (!video.duration) return;
        progressFilled.style.width = (video.currentTime / video.duration * 100) + '%';
        updateTimeDisplay();
    });

    // Buffer progress
    video.addEventListener('progress', () => {
        if (video.buffered.length > 0 && progressBuffer) {
            const buffEnd = video.buffered.end(video.buffered.length - 1);
            progressBuffer.style.width = (buffEnd / video.duration * 100) + '%';
        }
    });

    function updateTimeDisplay() {
        if (timeDisplay) timeDisplay.textContent = fmtTime(video.currentTime) + ' / ' + fmtTime(video.duration);
    }

    // Play/Pause button
    if (playBtn) playBtn.addEventListener('click', () => {
        video.paused ? video.play().catch(()=>{}) : video.pause();
    });
    video.addEventListener('play', () => { if(playBtn) playBtn.innerHTML = '<i class="fas fa-pause"></i>'; });
    video.addEventListener('pause', () => { if(playBtn) playBtn.innerHTML = '<i class="fas fa-play"></i>'; });

    // Enhanced progress bar seek with tooltip and drag
    if (progressBar) {
        let isSeeking = false;
        
        function seekFromEvent(e) {
            const rect = progressBar.getBoundingClientRect();
            const pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            return pct;
        }
        
        // Show tooltip on hover
        progressBar.addEventListener('mousemove', (e) => {
            if (!video.duration || !seekTooltip) return;
            const pct = seekFromEvent(e);
            seekTooltip.textContent = fmtTime(pct * video.duration);
            seekTooltip.style.left = (pct * 100) + '%';
        });
        
        progressBar.addEventListener('mousedown', (e) => {
            isSeeking = true;
            const pct = seekFromEvent(e);
            video.currentTime = pct * video.duration;
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isSeeking) return;
            const pct = seekFromEvent(e);
            video.currentTime = pct * video.duration;
            progressFilled.style.width = (pct * 100) + '%';
        });
        
        document.addEventListener('mouseup', () => { isSeeking = false; });
        
        // Touch seek support
        progressBar.addEventListener('touchstart', (e) => {
            isSeeking = true;
            const touch = e.touches[0];
            const rect = progressBar.getBoundingClientRect();
            const pct = Math.max(0, Math.min(1, (touch.clientX - rect.left) / rect.width));
            video.currentTime = pct * video.duration;
        }, {passive: true});
        
        progressBar.addEventListener('touchmove', (e) => {
            if (!isSeeking) return;
            const touch = e.touches[0];
            const rect = progressBar.getBoundingClientRect();
            const pct = Math.max(0, Math.min(1, (touch.clientX - rect.left) / rect.width));
            video.currentTime = pct * video.duration;
            progressFilled.style.width = (pct * 100) + '%';
        }, {passive: true});
        
        progressBar.addEventListener('touchend', () => { isSeeking = false; }, {passive: true});
    }

    // Volume
    if (volSlider) volSlider.addEventListener('input', () => { video.volume = volSlider.value; updateVolIcon(); });
    if (volBtn) volBtn.addEventListener('click', () => { video.muted = !video.muted; updateVolIcon(); });
    function updateVolIcon() {
        if (!volBtn) return;
        const v = video.muted ? 0 : video.volume;
        volBtn.innerHTML = v === 0 ? '<i class="fas fa-volume-mute"></i>' : v < 0.5 ? '<i class="fas fa-volume-down"></i>' : '<i class="fas fa-volume-up"></i>';
    }

    // Fullscreen - works on mobile too
    if (fullscreenBtn) fullscreenBtn.addEventListener('click', () => {
        const el = document.getElementById('room-player');
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        } else {
            // Try video element first for mobile (native fullscreen)
            if (video.requestFullscreen) video.requestFullscreen().catch(() => el.requestFullscreen().catch(()=>{}));
            else if (video.webkitEnterFullscreen) video.webkitEnterFullscreen();
            else if (el.requestFullscreen) el.requestFullscreen().catch(()=>{});
            else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
        }
    });

    // Auto-hide controls
    const playerEl = document.getElementById('room-player');
    playerEl.addEventListener('mousemove', () => {
        controlsEl.classList.add('visible');
        clearTimeout(controlsTimeout);
        controlsTimeout = setTimeout(() => controlsEl.classList.remove('visible'), 3000);
    });
    playerEl.addEventListener('mouseleave', () => {
        clearTimeout(controlsTimeout);
        controlsTimeout = setTimeout(() => controlsEl.classList.remove('visible'), 1000);
    });
    // Touch support
    playerEl.addEventListener('touchstart', () => {
        controlsEl.classList.add('visible');
        clearTimeout(controlsTimeout);
        controlsTimeout = setTimeout(() => controlsEl.classList.remove('visible'), 4000);
    }, {passive: true});

    // Double-click to fullscreen
    video.addEventListener('dblclick', () => {
        if (document.fullscreenElement) document.exitFullscreen();
        else playerEl.requestFullscreen().catch(()=>{});
    });
}

// ======================================================
// ============ MASTER SYNC LOGIC =======================
// ======================================================

function broadcastState(status) {
    if (!video) return;
    fetch(`${window.SITE_URL}/api/watch-party.php`, {
        method: 'POST', headers: {'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({ action: 'sync', room_id: ROOM_ID, current_time: video.currentTime, status: status })
    });
}

if (IS_HOST && video) {
    // === HOST: Auto-broadcast on every play/pause/seek ===
    video.addEventListener('play', () => broadcastState('playing'));
    video.addEventListener('pause', () => broadcastState('paused'));
    video.addEventListener('seeked', () => broadcastState(video.paused ? 'paused' : 'playing'));
    
    // Periodic broadcast every 2s
    setInterval(() => broadcastState(video.paused ? 'paused' : 'playing'), 2000);

} else if (!IS_HOST && video) {
    // === GUEST: Master Sync with Join overlay + Auto-Sync on join ===
    let guestJoined = false;
    const joinOverlay = document.getElementById('cinema-join-overlay');
    const syncBadge = document.getElementById('cinema-sync-badge');

    function showSyncToast(msg, icon) {
        const old = document.querySelector('.room-sync-toast');
        if (old) old.remove();
        const t = document.createElement('div');
        t.className = 'room-sync-toast';
        t.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-text">${msg}</span><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>`;
        document.body.appendChild(t);
        setTimeout(() => { if (t.parentElement) t.remove(); }, 5000);
    }

    function syncToHost(state) {
        if (!state || !video) return;
        const diff = Math.abs(video.currentTime - state.current_time);
        
        // Auto-sync: jump to host time if diff > 1s
        if (diff > 1.0) {
            video.currentTime = state.current_time;
        }
        
        if (state.status === 'playing' && video.paused) {
            video.play().catch(() => {
                showSyncToast('تکایە کلیک بکە بۆ دەستپێکردن', '⚠️');
            });
        } else if (state.status === 'paused' && !video.paused) {
            video.pause();
        }
        
        if (syncBadge) {
            if (diff < 2) {
                syncBadge.className = 'cinema-sync-badge synced';
                syncBadge.innerHTML = '<i class="fas fa-check-circle"></i> سینک';
            } else {
                syncBadge.className = 'cinema-sync-badge syncing';
                syncBadge.innerHTML = '<i class="fas fa-sync-alt"></i> هاوکاتکردن...';
            }
        }
    }

    // Join overlay click - auto-sync to host's current timestamp
    if (joinOverlay) {
        joinOverlay.addEventListener('click', function() {
            guestJoined = true;
            this.style.animation = 'fadeIn 0.3s ease reverse';
            setTimeout(() => this.remove(), 300);
            
            // Fetch host state and jump to exact position
            fetch(`${window.SITE_URL}/api/watch-party.php?action=poll&room_id=${ROOM_ID}`, {credentials:'same-origin'})
                .then(r => r.json())
                .then(state => {
                    if (!state.success) return;
                    video.currentTime = state.current_time;
                    if (state.status === 'playing') {
                        video.play().catch(() => {});
                    }
                    showSyncToast('پەیوەست بوویت! ڤیدیۆ لە ' + fmtTime(state.current_time) + ' دەست پێدەکات 🎬', '✅');
                });
        });
    }

    video.addEventListener('play', () => { if (!guestJoined) { video.pause(); return; } });
    video.addEventListener('pause', () => { if (!guestJoined) return; });
    video.addEventListener('seeked', () => { if (!guestJoined) return; });

    // Poll host state every 1 second
    let lastHostStatus = '';
    setInterval(() => {
        if (!guestJoined) return;
        fetch(`${window.SITE_URL}/api/watch-party.php?action=poll&room_id=${ROOM_ID}`, {credentials:'same-origin'})
            .then(r => r.json())
            .then(state => {
                if (!state.success) {
                    showSyncToast('ژوورەکە داخرا! 🚪', '❌');
                    setTimeout(() => { window.location.href = window.SITE_URL; }, 2000);
                    return;
                }
                if (state.is_host) { window.location.reload(); return; }
                
                if (state.room_closed) {
                    showSyncToast('هۆست ژوورەکەی داخست! 🚪', '❌');
                    setTimeout(() => { window.location.href = window.SITE_URL; }, 2000);
                    return;
                }
                
                if (state.status !== lastHostStatus && lastHostStatus !== '') {
                    if (state.status === 'playing') showSyncToast('هۆست فیلمەکەی دەستپێکرد ▶️', '🎬');
                    else if (state.status === 'paused') showSyncToast('هۆست فیلمەکەی وەستاند ⏸️', '⏸️');
                }
                lastHostStatus = state.status;
                syncToHost(state);
            })
            .catch(() => {
                if (syncBadge) {
                    syncBadge.className = 'cinema-sync-badge syncing';
                    syncBadge.innerHTML = '<i class="fas fa-exclamation-triangle"></i> پەیوەندی...';
                }
            });
    }, 1000);
}

// ============ COPY LINK ============
document.getElementById('room-copy-link').addEventListener('click', function() {
    const link = this.dataset.link;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(link).then(() => {
            if (typeof CineSound !== 'undefined') CineSound.success();
            this.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => this.innerHTML = '<i class="fas fa-link"></i>', 2000);
        }).catch(() => fallbackCopy(link, this));
    } else {
        fallbackCopy(link, this);
    }
});

function fallbackCopy(text, btn) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.cssText = 'position:fixed;left:-9999px;';
    document.body.appendChild(textarea);
    textarea.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(textarea);
    if (typeof CineSound !== 'undefined') CineSound.success();
    btn.innerHTML = '<i class="fas fa-check"></i>';
    setTimeout(() => btn.innerHTML = '<i class="fas fa-link"></i>', 2000);
}

// ============ PUBLIC/PRIVATE TOGGLE ============
const publicToggle = document.getElementById('room-public-toggle');
if (publicToggle) {
    let isPublic = true;
    publicToggle.addEventListener('click', function() {
        isPublic = !isPublic;
        fetch(`${window.SITE_URL}/api/watch-party.php`, {
            method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
            body: JSON.stringify({action:'toggle_public', room_id: ROOM_ID, is_public: isPublic ? 1 : 0})
        }).then(r=>r.json()).then(d => {
            if (d.success) {
                const label = document.getElementById('room-public-label');
                if (isPublic) {
                    publicToggle.style.color = 'var(--green)';
                    publicToggle.querySelector('i').className = 'fas fa-globe';
                    if (label) label.textContent = 'گشتی';
                } else {
                    publicToggle.style.color = 'var(--red)';
                    publicToggle.querySelector('i').className = 'fas fa-lock';
                    if (label) label.textContent = 'تایبەت';
                }
                if (typeof CineSound !== 'undefined') CineSound.click();
            }
        });
    });
}

// ============ STICKER PICKER (supports webm) ============
const stickerBtn = document.getElementById('room-sticker-btn');
const stickerPicker = document.getElementById('room-sticker-picker');
if (stickerBtn && stickerPicker && window.CineStickers) {
    CineStickers.attach({
        btn: stickerBtn,
        picker: stickerPicker,
        onSelect: file => sendSticker(file)
    });
}

function sendSticker(fileName, name) {
    const stickerMsg = `[sticker:${fileName}]`;
    fetch(`${window.SITE_URL}/api/watch-party.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'chat_send', room_id: ROOM_ID, message: stickerMsg})
    }).then(r=>r.json()).then(d => { if(d.success) { loadRoomChat(); stickerPicker.style.display = 'none'; } });
}

// ============ LEAVE ============
document.getElementById('room-leave-btn').addEventListener('click', function() {
    fetch(`${window.SITE_URL}/api/watch-party.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'leave', room_id: ROOM_ID})
    }).then(() => { window.location.href = `${window.SITE_URL}/movie/<?= $room['slug'] ?>`; });
});

// ============ INVITE MODAL ============
document.getElementById('room-invite-btn').addEventListener('click', showRoomInviteModal);

function showRoomInviteModal() {
    let modal = document.getElementById('room-invite-modal');
    if (modal) modal.remove();
    
    modal = document.createElement('div');
    modal.id = 'room-invite-modal';
    modal.className = 'user-search-modal show';
    modal.innerHTML = `
        <div class="user-search-modal-overlay" onclick="this.parentElement.remove()"></div>
        <div class="user-search-modal-content glass-strong">
            <div class="user-search-modal-header">
                <h3><i class="fas fa-user-plus"></i> بانگهێشتکردن بۆ ژوور</h3>
                <button class="user-search-modal-close" onclick="this.closest('.user-search-modal').remove()">&times;</button>
            </div>
            <div class="user-search-modal-input">
                <input type="text" id="room-invite-search" placeholder="ناوی بەکارهێنەر بنووسە..." autocomplete="off" style="font-size:16px !important;">
            </div>
            <div class="user-search-modal-results" id="room-invite-results">
                <p style="padding:20px;text-align:center;color:var(--gray);font-size:0.85rem;">ناوی بەکارهێنەر بنووسە بۆ گەڕان</p>
            </div>
            <div style="padding:12px 20px;border-top:1px solid rgba(255,255,255,0.06);">
                <button class="room-action-btn btn-invite" style="width:100%;justify-content:center;" onclick="navigator.clipboard.writeText('${window.SITE_URL}/room.php?id=${ROOM_ID}');this.innerHTML='<i class=\\'fas fa-check\\'></i> لینک کۆپی کرا!';setTimeout(()=>this.innerHTML='<i class=\\'fas fa-link\\'></i> کۆپیکردنی لینکی ژوور',2000);">
                    <i class="fas fa-link"></i> کۆپیکردنی لینکی ژوور
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    const searchInput = document.getElementById('room-invite-search');
    const resultsDiv = document.getElementById('room-invite-results');
    let searchTimeout;
    
    searchInput.focus();
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) { resultsDiv.innerHTML = '<p style="padding:20px;text-align:center;color:var(--gray);font-size:0.85rem;">ناوی بەکارهێنەر بنووسە بۆ گەڕان</p>'; return; }
        searchTimeout = setTimeout(() => {
            fetch(`${window.SITE_URL}/api/user-search.php?q=${encodeURIComponent(q)}`, {credentials:'same-origin'})
                .then(r => r.json()).then(d => {
                    if (!d.results || d.results.length === 0) {
                        resultsDiv.innerHTML = '<p style="padding:20px;text-align:center;color:var(--gray);font-size:0.85rem;">هیچ نەدۆزرایەوە</p>';
                        return;
                    }
                    resultsDiv.innerHTML = d.results.map(u => `
                        <div class="user-search-result-item">
                            <a href="javascript:void(0)" style="pointer-events:none;">
                                <i class="fas fa-user-circle" style="font-size:1.5rem;color:var(--gray);"></i>
                                <span>${u.username}</span>
                            </a>
                            <button class="room-action-btn btn-invite" onclick="sendRoomInvite(${u.id}, '${u.username}', this)">
                                <i class="fas fa-paper-plane"></i> بانگهێشت
                            </button>
                        </div>
                    `).join('');
                });
        }, 300);
    });
}

function sendRoomInvite(userId, username, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch(`${window.SITE_URL}/api/invite.php`, {
        method: 'POST', headers: {'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'send', room_id: ROOM_ID, invitee_id: userId})
    }).then(r => r.json()).then(d => {
        if (d.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> نێردرا';
            btn.style.color = 'var(--green)';
            if (typeof CineSound !== 'undefined') CineSound.success();
        } else {
            btn.innerHTML = '<i class="fas fa-times"></i> ' + (d.message || 'هەڵە');
            btn.disabled = false;
        }
    }).catch(() => {
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> بانگهێشت';
        btn.disabled = false;
    });
}

// ============ INIT ============
roomHeartbeat();
loadRoomChat();
loadRoomMembers();

setInterval(roomHeartbeat, 5000);
setInterval(loadRoomChat, 2000);
setInterval(loadRoomMembers, 8000);

// ============ ANTI-ZOOM ============
document.addEventListener('touchstart', function(e) {
    if (e.touches.length > 1) e.preventDefault();
}, { passive: false });
document.addEventListener('gesturestart', function(e) { e.preventDefault(); }, { passive: false });
document.addEventListener('wheel', function(e) {
    if (e.ctrlKey) e.preventDefault();
}, { passive: false });
let lastTouchEnd = 0;
document.addEventListener('touchend', function(e) {
    const now = Date.now();
    if (now - lastTouchEnd <= 300) e.preventDefault();
    lastTouchEnd = now;
}, { passive: false });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
