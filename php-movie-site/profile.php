<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if viewing another user's profile
$viewingUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isOwnProfile = false;

if ($viewingUserId && isLoggedIn() && $viewingUserId == $_SESSION['user_id']) {
    $isOwnProfile = true;
} elseif ($viewingUserId) {
    $isOwnProfile = false;
} else {
    requireLogin();
    $viewingUserId = $_SESSION['user_id'];
    $isOwnProfile = true;
}

$error = '';
$success = '';
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$viewingUserId]);
$user = $user->fetch();

if (!$user) {
    header('Location: ' . SITE_URL);
    exit;
}

// Handle POST actions (only own profile)
if ($isOwnProfile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'تکایە دووبارە هەوڵ بدەرەوە.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'profile') {
            $newUsername = clean($_POST['username'] ?? '');
            $newDisplayName = clean($_POST['display_name'] ?? '');
            
            // Validate username
            if (strlen($newUsername) < 5) {
                $error = 'یوزەرنەیم لانیکەم ٥ پیت بێت.';
            } elseif (!preg_match('/^[a-z0-9._-]+$/', $newUsername)) {
                $error = 'یوزەرنەیم تەنها پیتی بچووکی ئینگلیزی، ژمارە، و (- _ .) بەکاربهێنە.';
            } elseif (preg_match('/[A-Z]/', $_POST['username'] ?? '')) {
                $error = 'یوزەرنەیم نابێت پیتی گەورە بێت.';
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $check->execute([$newUsername, $_SESSION['user_id']]);
                if ($check->fetch()) {
                    $error = 'ئەم یوزەرنەیمە پێشتر بەکارهاتووە.';
                } else {
                    $avatar = $user['avatar'];
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/uploads/avatars';
                        $newAvatar = secureUpload($_FILES['avatar'], $uploadDir, ['image/jpeg','image/png','image/gif','image/webp']);
                        if ($newAvatar) {
                            $avatar = $newAvatar;
                        } else {
                            $error = 'فۆرماتی وێنە نادروستە یان قەبارەکەی گەورەیە (حەدی ٥MB).';
                        }
                    }
                    if (!$error) {
                        // Auto-add display_name column
                        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
                        
                        $pdo->prepare("UPDATE users SET username = ?, display_name = ?, avatar = ? WHERE id = ?")->execute([$newUsername, $newDisplayName ?: null, $avatar, $_SESSION['user_id']]);
                        $_SESSION['username'] = $newUsername;
                        $success = 'پرۆفایلەکەت نوێ کرایەوە!';
                        $user['username'] = $newUsername;
                        $user['display_name'] = $newDisplayName;
                        $user['avatar'] = $avatar;
                    }
                }
            }
        }
        
        if ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            
            if (!password_verify($current, $user['password'])) {
                $error = 'وشەی نهێنی ئێستا هەڵەیە.';
            } elseif (strlen($new) < 8) {
                $error = 'وشەی نهێنی نوێ لانیکەم ٨ پیت بێت.';
            } elseif (!preg_match('/[A-Z]/', $new)) {
                $error = 'وشەی نهێنی دەبێت لانیکەم یەک پیتی گەورە هەبێت.';
            } elseif (!preg_match('/[0-9]/', $new)) {
                $error = 'وشەی نهێنی دەبێت لانیکەم یەک ژمارە هەبێت.';
            } elseif ($new !== $confirm) {
                $error = 'وشەی نهێنی نوێ وەک یەک نییە.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $_SESSION['user_id']]);
                $success = 'وشەی نهێنی گۆڕدرا!';
            }
        }
    }
}

// Get display name
$displayName = '';
try {
    $dnStmt = $pdo->prepare("SELECT display_name FROM users WHERE id = ?");
    $dnStmt->execute([$viewingUserId]);
    $displayName = $dnStmt->fetchColumn() ?: '';
} catch (Exception $e) {}

// Follow counts
try {
    $followersCount = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ? AND status = 'active'");
    $followersCount->execute([$viewingUserId]);
    $followersCount = (int)$followersCount->fetchColumn();

    $followingCount = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND status = 'active'");
    $followingCount->execute([$viewingUserId]);
    $followingCount = (int)$followingCount->fetchColumn();
} catch (Exception $e) {
    $followersCount = 0;
    $followingCount = 0;
}

// Check if current user follows this profile
$isFollowing = false;
$followStatus = 'none';
if (!$isOwnProfile && isLoggedIn()) {
    try {
        $fCheck = $pdo->prepare("SELECT status FROM follows WHERE follower_id = ? AND following_id = ?");
        $fCheck->execute([$_SESSION['user_id'], $viewingUserId]);
        $fRow = $fCheck->fetch();
        if ($fRow) { $isFollowing = true; $followStatus = $fRow['status']; }
    } catch (Exception $e) {}
}

// Privacy settings
try {
    $privStmt = $pdo->prepare("SELECT * FROM user_privacy WHERE user_id = ?");
    $privStmt->execute([$viewingUserId]);
    $privSettings = $privStmt->fetch();
} catch (Exception $e) { $privSettings = null; }

$disableFollow = $privSettings ? (bool)$privSettings['disable_follow'] : false;

// Favorites
$favorites = $pdo->prepare("SELECT m.*, f.created_at as fav_date FROM favorites f JOIN movies m ON f.movie_id = m.id WHERE f.user_id = ? ORDER BY f.created_at DESC LIMIT 12");
$favorites->execute([$viewingUserId]);
$favorites = $favorites->fetchAll();

// Watch history (only own profile)
$history = [];
if ($isOwnProfile) {
    $history = $pdo->prepare("SELECT m.*, wh.watched_at FROM watch_history wh JOIN movies m ON wh.movie_id = m.id WHERE wh.user_id = ? ORDER BY wh.watched_at DESC LIMIT 12");
    $history->execute([$viewingUserId]);
    $history = $history->fetchAll();
}

// Pending follow requests (own profile only)
$pendingRequests = [];
if ($isOwnProfile) {
    try {
        $pendingStmt = $pdo->prepare("SELECT u.id, u.username, u.avatar, f.created_at FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ? AND f.status = 'pending' ORDER BY f.created_at DESC");
        $pendingStmt->execute([$viewingUserId]);
        $pendingRequests = $pendingStmt->fetchAll();
    } catch (Exception $e) {}
}

// Online Status
$isOnline = false;
try {
    $lsStmt = $pdo->prepare("SELECT last_seen FROM users WHERE id = ?");
    $lsStmt->execute([$viewingUserId]);
    $ls = $lsStmt->fetchColumn();
    $isOnline = $ls && (strtotime($ls) > strtotime('-5 minutes'));
} catch(Exception $e) {}

// Get premium emoji
$premiumEmoji = '';
try { $pe = $pdo->prepare("SELECT premium_emoji FROM users WHERE id = ?"); $pe->execute([$viewingUserId]); $premiumEmoji = $pe->fetchColumn(); } catch(Exception $e) {}

// Privacy helpers
$hideFollowing = $privSettings ? (bool)($privSettings['hide_following'] ?? 0) : false;
$hidePoints = $privSettings ? (bool)($privSettings['hide_points'] ?? 0) : false;

require_once __DIR__ . '/includes/header.php';
?>

<!-- TikTok-Style Profile Page -->
<div class="profile-page">
    <div class="container" style="padding-top: 80px; max-width: 680px;">
        
        <!-- Story Viewer Overlay (hidden) -->
        <div id="story-viewer-overlay" class="story-viewer-overlay" style="display:none;">
            <div class="story-viewer-container">
                <div class="story-viewer-header">
                    <div class="story-viewer-user">
                        <div class="story-viewer-avatar" id="story-viewer-avatar"></div>
                        <div>
                            <strong id="story-viewer-username"></strong>
                            <span id="story-viewer-time" style="font-size:0.72rem;color:var(--gray);display:block;"></span>
                        </div>
                    </div>
                    <button class="story-viewer-close" onclick="closeStoryViewer()">&times;</button>
                </div>
                <div class="story-viewer-progress" id="story-viewer-progress"></div>
                <div class="story-viewer-media" id="story-viewer-media"></div>
                <div class="story-viewer-caption" id="story-viewer-caption"></div>
                <div class="story-viewer-nav">
                    <div class="story-nav-prev" onclick="prevStory()"></div>
                    <div class="story-nav-next" onclick="nextStory()"></div>
                </div>
                <?php if ($isOwnProfile): ?>
                <div class="story-viewer-views" id="story-viewer-views" style="position:absolute;bottom:60px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.6);padding:6px 14px;border-radius:20px;font-size:0.75rem;color:var(--gray-light);cursor:pointer;" onclick="showStoryViewers()">
                    <i class="fas fa-eye"></i> <span id="story-view-count">0</span> بینین
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== TikTok-Style Profile Header ===== -->
        <div style="text-align:center; padding: 20px 0;">
            
            <!-- Avatar with Story Ring -->
            <div class="profile-avatar-wrap" id="profile-avatar-wrap" style="position:relative;display:inline-block;cursor:pointer;margin-bottom:16px;" onclick="handleAvatarClick()">
                <div id="story-ring" class="story-ring" style="display:none;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1;">
                    <svg width="110" height="110" viewBox="0 0 110 110">
                        <circle cx="55" cy="55" r="52" fill="none" stroke="url(#storyGrad)" stroke-width="2.5" stroke-dasharray="327" stroke-linecap="round" id="story-ring-circle"/>
                        <defs><linearGradient id="storyGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#F5C518"/><stop offset="50%" stop-color="#FF6B6B"/><stop offset="100%" stop-color="#C9A000"/></linearGradient></defs>
                    </svg>
                </div>
                <div style="width:96px;height:96px;border-radius:50%;border:2px solid rgba(255,255,255,0.15);overflow:hidden;background:rgba(10,10,10,0.9);display:flex;align-items:center;justify-content:center;position:relative;z-index:2;">
                    <?php 
                    $avatarUrl = ($user['avatar'] && $user['avatar'] !== 'default.png') 
                        ? SITE_URL . '/uploads/avatars/' . $user['avatar'] 
                        : '';
                    ?>
                    <?php if ($avatarUrl): ?>
                        <img src="<?= $avatarUrl ?>" alt="<?= clean($user['username']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <i class="fas fa-user" style="font-size:2.5rem;color:var(--gray);"></i>
                    <?php endif; ?>
                </div>
                <?php if ($isOwnProfile): ?>
                <button class="story-add-btn" id="story-add-btn" title="زیادکردنی ستۆری" style="position:absolute;bottom:0;right:0;z-index:5;width:28px;height:28px;border-radius:50%;background:#25F4EE;border:3px solid var(--black);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.7rem;" onclick="event.stopPropagation();openStoryCreator();">
                    <i class="fas fa-plus"></i>
                </button>
                <input type="file" id="story-upload-input" accept="image/*,video/mp4,video/webm" style="display:none;" onchange="uploadStoryFile(this)">
                <?php endif; ?>
            </div>
            
            <!-- Name & Username (TikTok Style) -->
            <div style="margin-bottom:4px;">
                <h1 style="font-size:1.3rem;font-weight:800;display:inline-flex;align-items:center;gap:6px;">
                    <?= clean($displayName ?: $user['username']) ?>
                    <?php if ($premiumEmoji): ?>
                        <span style="font-size:1rem;"><?= $premiumEmoji ?></span>
                    <?php endif; ?>
                </h1>
                <?php if ($isOwnProfile): ?>
                <button class="btn btn-glass btn-sm" style="border-radius:8px;padding:4px 14px;font-size:0.78rem;margin-right:8px;vertical-align:middle;" onclick="switchProfileTab(document.querySelector('[data-tab=settings]'),'settings')">
                    <i class="fas fa-pen"></i> دەستکاری
                </button>
                <?php endif; ?>
            </div>
            <div style="color:var(--gray);font-size:0.85rem;margin-bottom:2px;">@<?= clean($user['username']) ?></div>
            
            <span id="vip-badge" style="display:none;font-size:0.65rem;background:linear-gradient(135deg,var(--gold),var(--gold-light));color:var(--black);padding:2px 10px;border-radius:20px;">👑 VIP</span>
            
            <!-- Bio -->
            <div id="user-bio-display" style="margin-top:8px;color:var(--gray-light);font-size:0.85rem;max-width:400px;margin-left:auto;margin-right:auto;text-align:center;line-height:1.7;min-height:20px;"></div>
            
            <!-- Cinema Mood -->
            <div id="cinema-mood-display" style="margin-top:6px;display:none;">
                <span style="background:rgba(245,197,24,0.08);border:1px solid rgba(245,197,24,0.15);border-radius:20px;padding:4px 14px;font-size:0.78rem;color:var(--gold-light);display:inline-flex;align-items:center;gap:5px;">
                    <span id="cinema-mood-text"></span>
                    <?php if ($isOwnProfile): ?>
                    <button onclick="event.stopPropagation();toggleInlineMoodEdit()" style="background:none;border:none;color:var(--gray);cursor:pointer;font-size:0.7rem;padding:0 4px;"><i class="fas fa-pen"></i></button>
                    <?php endif; ?>
                </span>
            </div>
            
            <!-- Stats Row (TikTok Style) -->
            <div style="display:flex;justify-content:center;gap:28px;margin-top:18px;">
                <?php if (!$hideFollowing || $isOwnProfile): ?>
                <span onclick="openFollowModal('following', <?= $viewingUserId ?>)" style="cursor:pointer;text-align:center;">
                    <strong id="following-count" style="font-size:1.2rem;display:block;font-weight:800;"><?= $followingCount ?></strong>
                    <span style="font-size:0.78rem;color:var(--gray);">فۆڵۆکردن</span>
                </span>
                <div style="width:1px;height:30px;background:rgba(255,255,255,0.1);align-self:center;"></div>
                <span onclick="openFollowModal('followers', <?= $viewingUserId ?>)" style="cursor:pointer;text-align:center;">
                    <strong id="followers-count" style="font-size:1.2rem;display:block;font-weight:800;"><?= $followersCount ?></strong>
                    <span style="font-size:0.78rem;color:var(--gray);">فۆڵۆوەر</span>
                </span>
                <?php else: ?>
                <span style="text-align:center;"><strong style="font-size:1.2rem;display:block;font-weight:800;"><?= $followingCount ?></strong><span style="font-size:0.78rem;color:var(--gray);">فۆڵۆکردن</span></span>
                <div style="width:1px;height:30px;background:rgba(255,255,255,0.1);align-self:center;"></div>
                <span style="text-align:center;"><strong style="font-size:1.2rem;display:block;font-weight:800;"><?= $followersCount ?></strong><span style="font-size:0.78rem;color:var(--gray);">فۆڵۆوەر</span></span>
                <?php endif; ?>
                
                <?php if (!$hidePoints || $isOwnProfile): ?>
                <div style="width:1px;height:30px;background:rgba(255,255,255,0.1);align-self:center;"></div>
                <span style="text-align:center;" id="profile-points">
                    <strong id="user-points-count" style="font-size:1.2rem;display:block;font-weight:800;color:var(--gold);">0</strong>
                    <span style="font-size:0.78rem;color:var(--gray);">خاڵ</span>
                </span>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div style="display:flex;gap:8px;margin-top:18px;justify-content:center;flex-wrap:wrap;">
                <?php if (!$isOwnProfile && isLoggedIn() && !$disableFollow): ?>
                <button class="btn <?= $isFollowing ? 'btn-glass' : 'btn-gold' ?> btn-sm follow-btn" data-user-id="<?= $viewingUserId ?>" style="border-radius:8px;padding:8px 28px;font-weight:700;min-width:120px;justify-content:center;">
                    <?php if ($isFollowing && $followStatus === 'pending'): ?>
                        <i class="fas fa-clock"></i> چاوەڕوان
                    <?php elseif ($isFollowing): ?>
                        <i class="fas fa-user-check"></i> فۆڵۆکراوە
                    <?php else: ?>
                        <i class="fas fa-user-plus"></i> فۆڵۆ
                    <?php endif; ?>
                </button>
                <?php endif; ?>
                
                <?php if (!$isOwnProfile && isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/messages.php?with=<?= $viewingUserId ?>" class="btn btn-glass btn-sm" style="border-radius:8px;padding:8px 20px;">
                    <i class="fas fa-envelope"></i> نامە
                </a>
                <button class="btn btn-glass btn-sm" id="block-user-btn" data-user-id="<?= $viewingUserId ?>" style="color:var(--red);border-color:rgba(255,82,82,0.15);border-radius:8px;padding:8px 14px;">
                    <i class="fas fa-ban"></i> بلۆک
                </button>
                <button class="btn btn-glass btn-sm" onclick="showReportUserModal(<?= $viewingUserId ?>)" style="color:var(--orange);border-color:rgba(255,152,0,0.15);border-radius:8px;padding:8px 14px;">
                    <i class="fas fa-flag"></i> ڕاپۆرت
                </button>
                <?php endif; ?>
                
                <?php if ($isOwnProfile): ?>
                <span style="display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:8px;font-size:0.78rem;background:<?= $isOnline ? 'rgba(74,222,128,0.08)' : 'rgba(255,255,255,0.03)' ?>;border:1px solid <?= $isOnline ? 'rgba(74,222,128,0.15)' : 'rgba(255,255,255,0.06)' ?>;color:<?= $isOnline ? '#4ade80' : 'var(--gray)' ?>;">
                    <i class="fas fa-circle" style="font-size:0.45rem;"></i> <?= $isOnline ? 'ئۆنلاین' : 'ئۆفلاین' ?>
                </span>
                <?php endif; ?>
            </div>
            
            <?php if ($isOwnProfile): ?>
            <!-- Inline Edit Buttons -->
            <div style="display:flex;gap:6px;margin-top:10px;justify-content:center;">
                <button class="btn btn-glass btn-xs" onclick="toggleInlineMoodEdit()" style="border-radius:16px;font-size:0.72rem;padding:4px 10px;"><i class="fas fa-film"></i> مۆد</button>
                <button class="btn btn-glass btn-xs" onclick="toggleInlineEmojiEdit()" style="border-radius:16px;font-size:0.72rem;padding:4px 10px;"><i class="fas fa-crown"></i> ئیمۆجی</button>
            </div>
            
            <!-- Inline Mood Editor -->
            <div id="inline-mood-editor" style="display:none;margin-top:8px;max-width:350px;width:100%;margin-left:auto;margin-right:auto;">
                <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px;justify-content:center;">
                    <button class="btn btn-glass btn-xs" onclick="setMoodPreset('خەریکی سەیری فیلمی ترسناکم 🍿')" style="font-size:0.7rem;">🍿 ترسناک</button>
                    <button class="btn btn-glass btn-xs" onclick="setMoodPreset('لە مۆدی فیلمی خەمناکم 🎬')" style="font-size:0.7rem;">🎬 خەمناک</button>
                    <button class="btn btn-glass btn-xs" onclick="setMoodPreset('سەیری کۆمیدی دەکەم 😂')" style="font-size:0.7rem;">😂 کۆمیدی</button>
                    <button class="btn btn-glass btn-xs" onclick="setMoodPreset('مۆدی ئاکشن 🔥')" style="font-size:0.7rem;">🔥 ئاکشن</button>
                </div>
                <div style="display:flex;gap:6px;">
                    <input type="text" id="cinema-mood-input" class="form-control" placeholder="حاڵەتی خۆت بنووسە..." maxlength="100" style="font-size:14px !important;flex:1;padding:6px 10px;border-radius:16px;">
                    <button class="btn btn-gold btn-xs" onclick="saveCinemaMood()" style="border-radius:16px;"><i class="fas fa-check"></i></button>
                    <button class="btn btn-glass btn-xs" onclick="clearCinemaMood()" style="border-radius:16px;color:var(--red);"><i class="fas fa-times"></i></button>
                </div>
            </div>
            
            <!-- Inline Premium Emoji Editor -->
            <div id="inline-emoji-editor" style="display:none;margin-top:8px;">
                <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:center;">
                    <?php $premiumEmojis = ['👑','⭐','💎','🔥','🌟','✨','🎬','🍿','🎭','💫','🦁','🐺','🎪','🏆']; ?>
                    <?php foreach($premiumEmojis as $pe): ?>
                    <button class="btn btn-glass btn-xs" onclick="setPremiumEmoji('<?= $pe ?>')" style="font-size:1rem;padding:4px 8px;"><?= $pe ?></button>
                    <?php endforeach; ?>
                    <button class="btn btn-glass btn-xs" onclick="setPremiumEmoji('')" style="color:var(--red);font-size:0.7rem;"><i class="fas fa-times"></i></button>
                </div>
            </div>
            
            <p style="color:var(--gray);font-size:0.75rem;margin-top:10px;"><i class="fas fa-calendar"></i> ئەندام لە: <?= date('Y/m/d', strtotime($user['created_at'])) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?><div class="alert alert-error" style="margin-top:20px;"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success" style="margin-top:20px;"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>

        <!-- Pending Follow Requests -->
        <?php if ($isOwnProfile && !empty($pendingRequests)): ?>
        <div class="glass" style="padding:18px;margin-top:20px;">
            <h3 style="color:var(--gold);margin-bottom:14px;font-size:0.95rem;"><i class="fas fa-user-clock"></i> داواکاری فۆڵۆ (<?= count($pendingRequests) ?>)</h3>
            <?php foreach ($pendingRequests as $req): ?>
            <div class="follow-request-item">
                <span><i class="fas fa-user"></i> <?= clean($req['username']) ?></span>
                <div>
                    <button class="btn btn-gold btn-xs follow-accept-btn" data-follower-id="<?= $req['id'] ?>"><i class="fas fa-check"></i> قبوڵ</button>
                    <button class="btn btn-danger btn-xs follow-reject-btn" data-follower-id="<?= $req['id'] ?>"><i class="fas fa-times"></i> ڕەت</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Tabs (TikTok Style - Icon tabs) -->
        <div style="display:flex;gap:0;border-bottom:1px solid rgba(255,255,255,0.08);margin-top:20px;">
            <button class="profile-tab active" data-tab="watchlist" style="flex:1;padding:12px 8px;border:none;background:none;color:var(--gray);font-family:inherit;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.3s;border-bottom:2px solid transparent;" onclick="switchProfileTab(this,'watchlist')"><i class="fas fa-th"></i></button>
            <?php if ($isOwnProfile): ?>
            <button class="profile-tab" data-tab="stories" style="flex:1;padding:12px 8px;border:none;background:none;color:var(--gray);font-family:inherit;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.3s;border-bottom:2px solid transparent;" onclick="switchProfileTab(this,'stories')"><i class="fas fa-camera"></i></button>
            <button class="profile-tab" data-tab="history" style="flex:1;padding:12px 8px;border:none;background:none;color:var(--gray);font-family:inherit;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.3s;border-bottom:2px solid transparent;" onclick="switchProfileTab(this,'history')"><i class="fas fa-history"></i></button>
            <button class="profile-tab" data-tab="settings" style="flex:1;padding:12px 8px;border:none;background:none;color:var(--gray);font-family:inherit;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.3s;border-bottom:2px solid transparent;" onclick="switchProfileTab(this,'settings')"><i class="fas fa-cog"></i></button>
            <?php endif; ?>
        </div>

        <!-- Watchlist Tab -->
        <div class="profile-tab-content active" id="tab-watchlist">
            <div class="section-title" style="margin-top:16px;"><span class="gold-line"></span><span><i class="fas fa-heart"></i> دڵخوازەکان (<?= count($favorites) ?>)</span></div>
            <?php if (!empty($favorites)): ?>
            <div class="movies-grid">
                <?php foreach ($favorites as $movie): ?>
                <a href="<?= SITE_URL ?>/movie/<?= $movie['slug'] ?>" class="movie-card">
                    <div class="poster">
                        <img src="<?= SITE_URL ?>/uploads/posters/<?= $movie['poster'] ?: 'default.jpg' ?>" alt="<?= clean($movie['title']) ?>" loading="lazy">
                        <div class="overlay"><div class="play-btn"><i class="fas fa-play"></i></div></div>
                        <span class="quality-badge"><?= $movie['quality'] ?></span>
                    </div>
                    <div class="info"><h3><?= clean($movie['title']) ?></h3><div class="meta"><span><?= $movie['release_year'] ?></span><span class="rating"><i class="fas fa-star"></i> <?= $movie['imdb_rate'] ?></span></div></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><i class="fas fa-heart"></i><p>هیچ فیلمێکت پاشەکەوت نەکردووە</p></div>
            <?php endif; ?>
        </div>

        <?php if ($isOwnProfile): ?>
        <!-- Manage Stories Tab -->
        <div class="profile-tab-content" id="tab-stories">
            <div class="section-title" style="margin-top:16px;"><span class="gold-line"></span><span><i class="fas fa-camera"></i> بەڕێوەبردنی ستۆریەکان</span></div>
            <div id="my-stories-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-top:16px;">
                <div style="text-align:center;padding:30px;grid-column:1/-1;"><i class="fas fa-spinner fa-spin" style="color:var(--gold);"></i></div>
            </div>
        </div>

        <!-- History Tab -->
        <div class="profile-tab-content" id="tab-history">
            <div class="section-title" style="margin-top:16px;"><span class="gold-line"></span><span><i class="fas fa-history"></i> مێژووی سەیرکردن</span></div>
            <?php if (!empty($history)): ?>
            <div class="movies-grid">
                <?php foreach ($history as $movie): ?>
                <a href="<?= SITE_URL ?>/movie/<?= $movie['slug'] ?>" class="movie-card">
                    <div class="poster">
                        <img src="<?= SITE_URL ?>/uploads/posters/<?= $movie['poster'] ?: 'default.jpg' ?>" alt="<?= clean($movie['title']) ?>" loading="lazy">
                        <div class="overlay"><div class="play-btn"><i class="fas fa-play"></i></div></div>
                        <span class="quality-badge"><?= $movie['quality'] ?></span>
                    </div>
                    <div class="info"><h3><?= clean($movie['title']) ?></h3><div class="meta"><span><?= $movie['release_year'] ?></span><span class="rating"><i class="fas fa-star"></i> <?= $movie['imdb_rate'] ?></span></div></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><i class="fas fa-film"></i><p>هیچ فیلمێکت سەیر نەکردووە</p></div>
            <?php endif; ?>
        </div>

        <!-- Settings Tab -->
        <div class="profile-tab-content" id="tab-settings">
            <div class="settings-grid">
                <div class="panel-card glass">
                    <h3><i class="fas fa-user-edit"></i> زانیاری پرۆفایل</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                        <input type="hidden" name="action" value="profile">
                        <div class="form-group">
                            <label>ناو (ناوی نیشاندان)</label>
                            <input type="text" name="display_name" class="form-control" value="<?= clean($displayName ?: $user['username']) ?>" placeholder="ناوی خۆت بنووسە...">
                            <small class="text-gray">ئەم ناوە لە پرۆفایلتدا پیشان دەدرێت</small>
                        </div>
                        <div class="form-group">
                            <label>یوزەرنەیم (Username)</label>
                            <input type="text" name="username" class="form-control" value="<?= clean($user['username']) ?>" required pattern="[a-z0-9._-]+" title="تەنها پیتی بچووکی ئینگلیزی، ژمارە، و (- _ .)">
                            <small class="text-gray">لانیکەم ٥ پیت • تەنها پیتی بچووک، ژمارە، و (- _ .)</small>
                        </div>
                        <div class="form-group">
                            <label>بایۆ (Bio)</label>
                            <textarea name="bio_text" id="bio-edit-field" class="form-control" rows="2" maxlength="300" placeholder="چەند وشەیەک لەسەر خۆت بنووسە..." style="font-size:16px !important;"></textarea>
                            <small class="text-gray">حەدی ٣٠٠ پیت</small>
                        </div>
                        <div class="form-group">
                            <label>وێنەی پرۆفایل</label>
                            <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                            <small class="text-gray">حەدی ٥MB - JPG, PNG, GIF, WebP</small>
                        </div>
                        <button type="submit" class="btn btn-gold btn-sm" onclick="saveBioBeforeSubmit()"><i class="fas fa-save"></i> پاشەکەوت</button>
                    </form>
                </div>

                <div class="panel-card glass">
                    <h3><i class="fas fa-lock"></i> گۆڕینی وشەی نهێنی</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                        <input type="hidden" name="action" value="password">
                        <div class="form-group"><label>وشەی نهێنی ئێستا</label><input type="password" name="current_password" class="form-control" required></div>
                        <div class="form-group">
                            <label>وشەی نهێنی نوێ</label>
                            <input type="password" name="new_password" id="new-password-input" class="form-control" required minlength="8">
                            <div id="password-strength" style="margin-top:6px;font-size:0.78rem;"></div>
                            <small class="text-gray">لانیکەم ٨ پیت • یەک پیتی گەورە • یەک ژمارە</small>
                        </div>
                        <div class="form-group"><label>دووبارەکردنەوەی وشەی نهێنی نوێ</label><input type="password" name="confirm_password" class="form-control" required></div>
                        <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-key"></i> گۆڕین</button>
                    </form>
                </div>

                <!-- UI Sounds Setting -->
                <div class="panel-card glass">
                    <h3><i class="fas fa-volume-up"></i> دەنگەکانی ڕووکار</h3>
                    <div class="privacy-option">
                        <label class="privacy-label">
                            <div>
                                <strong><i class="fas fa-bell"></i> چالاککردنی دەنگەکانی UI</strong>
                                <p class="text-gray" style="font-size:0.82rem;margin-top:4px;">دەنگی کلیک، ئاگادارکردنەوە، و سەرکەوتن</p>
                            </div>
                            <input type="checkbox" id="ui-sounds-toggle" style="accent-color:var(--gold);width:18px;height:18px;">
                        </label>
                    </div>
                </div>

                <!-- Privacy Settings -->
                <div class="panel-card glass">
                    <h3><i class="fas fa-shield-alt"></i> ڕێکخستنەکانی تایبەتمەندی</h3>
                    <div id="privacy-msg" class="alert" style="display:none;"></div>
                    <form id="privacy-form">
                        <div class="privacy-option"><label class="privacy-label"><div><strong><i class="fas fa-lock"></i> ئەکاونتی تایبەت</strong><p class="text-gray" style="font-size:0.82rem;margin-top:4px;">پێویستیان بە ڕەزامەندی تۆیە بۆ فۆڵۆکردنت</p></div><input type="checkbox" id="privacy-private" style="accent-color:var(--gold);width:18px;height:18px;"></label></div>
                        <div class="privacy-option"><label class="privacy-label"><div><strong><i class="fas fa-eye-slash"></i> شاردنەوە لە گەڕان</strong><p class="text-gray" style="font-size:0.82rem;margin-top:4px;">ناوی تۆ لە ئەنجامی گەڕانی گشتیدا نییە</p></div><input type="checkbox" id="privacy-hide-search" style="accent-color:var(--gold);width:18px;height:18px;"></label></div>
                        <div class="privacy-option"><label class="privacy-label"><div><strong><i class="fas fa-user-slash"></i> ناچالاککردنی فۆڵۆ</strong><p class="text-gray" style="font-size:0.82rem;margin-top:4px;">هیچ کەس ناتوانێت فۆڵۆت بکات</p></div><input type="checkbox" id="privacy-disable-follow" style="accent-color:var(--gold);width:18px;height:18px;"></label></div>
                        <div class="privacy-option"><label class="privacy-label"><div><strong><i class="fas fa-eye-slash"></i> شاردنەوەی لیستی فۆڵۆکردن</strong><p class="text-gray" style="font-size:0.82rem;margin-top:4px;">کەسانی تر ناتوانن ببینن کێ فۆڵۆ کردووە</p></div><input type="checkbox" id="privacy-hide-following" style="accent-color:var(--gold);width:18px;height:18px;"></label></div>
                        <div class="privacy-option"><label class="privacy-label"><div><strong><i class="fas fa-coins"></i> شاردنەوەی خاڵەکان</strong><p class="text-gray" style="font-size:0.82rem;margin-top:4px;">بڕی خاڵەکانت شاراوەتەوە</p></div><input type="checkbox" id="privacy-hide-points" style="accent-color:var(--gold);width:18px;height:18px;"></label></div>
                        <div class="privacy-option"><label class="privacy-label"><div><strong><i class="fas fa-comment-slash"></i> داواکاری چات (Chat Request)</strong><p class="text-gray" style="font-size:0.82rem;margin-top:4px;">کەسانی تر دەبێ داواکاری بکەن بۆ ناردنی نامە</p></div><input type="checkbox" id="privacy-chat-request" style="accent-color:var(--gold);width:18px;height:18px;"></label></div>
                        <button type="submit" class="btn btn-gold btn-sm" style="margin-top:16px;"><i class="fas fa-save"></i> پاشەکەوتکردن</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Story Creator Modal -->
<div id="story-creator-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.9);backdrop-filter:blur(20px);align-items:center;justify-content:center;">
    <div style="max-width:400px;width:95%;background:rgba(14,14,14,0.95);border:1px solid rgba(245,197,24,0.15);border-radius:20px;padding:28px;text-align:center;">
        <h3 style="color:var(--gold);margin-bottom:20px;"><i class="fas fa-plus-circle"></i> زیادکردنی ستۆری</h3>
        
        <div style="display:flex;gap:12px;margin-bottom:20px;">
            <button class="btn btn-gold" id="story-type-image-btn" onclick="selectStoryType('image')" style="flex:1;border-radius:12px;"><i class="fas fa-image"></i> وێنە / ڤیدیۆ</button>
            <button class="btn btn-glass" id="story-type-text-btn" onclick="selectStoryType('text')" style="flex:1;border-radius:12px;"><i class="fas fa-font"></i> دەق</button>
        </div>
        
        <div id="story-image-section">
            <div style="border:2px dashed rgba(245,197,24,0.2);border-radius:14px;padding:40px 20px;cursor:pointer;transition:all 0.3s;" onclick="document.getElementById('story-upload-input').click();">
                <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:var(--gold);margin-bottom:10px;display:block;"></i>
                <p style="color:var(--gray);">کلیک بکە بۆ هەڵبژاردنی وێنە یان ڤیدیۆ</p>
            </div>
        </div>
        
        <div id="story-text-section" style="display:none;">
            <textarea id="story-text-content" class="form-control" rows="4" maxlength="300" placeholder="دەقەکەت بنووسە..." style="font-size:16px !important;margin-bottom:12px;text-align:center;"></textarea>
            <div style="margin-bottom:12px;">
                <label style="font-size:0.8rem;color:var(--gray);margin-bottom:6px;display:block;">ڕەنگی باکگراوند:</label>
                <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:center;">
                    <?php 
                    $bgColors = ['#F5C518','#FF6B6B','#4ECDC4','#45B7D1','#96CEB4','#FFEAA7','#DDA0DD','#FF8C00','#6C5CE7','#00B894','#E17055','#0984E3'];
                    foreach($bgColors as $clr): ?>
                    <button class="story-bg-btn" onclick="selectStoryBg('<?= $clr ?>')" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:<?= $clr ?>;cursor:pointer;transition:all 0.2s;"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-gold" onclick="uploadTextStory()" style="width:100%;border-radius:12px;"><i class="fas fa-paper-plane"></i> ناردنی ستۆری</button>
        </div>
        
        <button class="btn btn-glass" onclick="closeStoryCreator()" style="width:100%;margin-top:12px;border-radius:12px;"><i class="fas fa-times"></i> داخستن</button>
    </div>
</div>

<script>
// UI Sounds toggle
const soundsToggle = document.getElementById('ui-sounds-toggle');
if (soundsToggle) { soundsToggle.checked = CineSound.isEnabled(); soundsToggle.addEventListener('change', function() { CineSound.setEnabled(this.checked); if (this.checked) CineSound.success(); }); }

// Password strength indicator
const pwInput = document.getElementById('new-password-input');
if (pwInput) {
    pwInput.addEventListener('input', function() {
        const val = this.value;
        const el = document.getElementById('password-strength');
        let strength = 0, msgs = [];
        if (val.length >= 8) strength++; else msgs.push('لانیکەم ٨ پیت');
        if (/[A-Z]/.test(val)) strength++; else msgs.push('یەک پیتی گەورە');
        if (/[0-9]/.test(val)) strength++; else msgs.push('یەک ژمارە');
        if (/[^A-Za-z0-9]/.test(val)) strength++;
        const colors = ['#FF5252','#FB923C','#F5C518','#4ADE80'];
        const labels = ['لاواز','مامناوەند','باش','بەهێز'];
        if (val.length > 0) {
            el.innerHTML = `<span style="color:${colors[strength-1] || colors[0]};">● ${labels[strength-1] || labels[0]}</span>${msgs.length ? ' <span style="color:var(--gray);">(' + msgs.join('، ') + ')</span>' : ''}`;
        } else el.innerHTML = '';
    });
}

// Profile tabs  
function switchProfileTab(btn, tabName) {
    document.querySelectorAll('.profile-tab').forEach(t => { t.classList.remove('active'); t.style.borderBottomColor = 'transparent'; t.style.color = 'var(--gray)'; });
    document.querySelectorAll('.profile-tab-content').forEach(c => { c.classList.remove('active'); c.style.display = 'none'; });
    btn.classList.add('active');
    btn.style.borderBottomColor = 'var(--white)';
    btn.style.color = 'var(--white)';
    const target = document.getElementById('tab-' + tabName);
    if (target) { target.classList.add('active'); target.style.display = 'block'; }
    if (tabName === 'stories') loadMyStories();
}
(function(){ const at = document.querySelector('.profile-tab.active'); if (at) { at.style.borderBottomColor = 'var(--white)'; at.style.color = 'var(--white)'; } })();

// Load points
fetch(`${window.SITE_URL}/api/points.php?action=get&user_id=<?= $viewingUserId ?>`, {credentials:'same-origin'})
    .then(r=>r.json()).then(d => {
        if (d.success) { const el = document.getElementById('user-points-count'); if (el) el.textContent = d.points; if (d.is_vip) document.getElementById('vip-badge').style.display = 'inline'; }
    }).catch(() => {});

// Load Bio
fetch(`${window.SITE_URL}/api/bio.php?action=get&user_id=<?= $viewingUserId ?>`, {credentials:'same-origin'})
    .then(r=>r.json()).then(d => {
        if (d.success && d.bio) {
            const bioEl = document.getElementById('user-bio-display');
            if (bioEl) bioEl.textContent = d.bio;
            const editField = document.getElementById('bio-edit-field');
            if (editField) editField.value = d.bio;
        }
    }).catch(() => {});

function saveBioBeforeSubmit() {
    const bioField = document.getElementById('bio-edit-field');
    if (bioField && bioField.value.trim()) {
        fetch(`${window.SITE_URL}/api/bio.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'update',bio:bioField.value.trim()})});
    }
}

// Block user
const blockBtn = document.getElementById('block-user-btn');
if (blockBtn) {
    fetch(`${window.SITE_URL}/api/block.php?action=check&user_id=<?= $viewingUserId ?>`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            if (d.is_blocked) { blockBtn.innerHTML = '<i class="fas fa-ban"></i> لابردنی بلۆک'; blockBtn.style.color = '#4ade80'; blockBtn.dataset.blocked = '1'; }
        });
    blockBtn.addEventListener('click', function() {
        const isBlocked = this.dataset.blocked === '1';
        const action = isBlocked ? 'unblock' : 'block';
        if (!isBlocked && !confirm('دڵنیایت لە بلۆککردنی ئەم بەکارهێنەرە؟')) return;
        fetch(`${window.SITE_URL}/api/block.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:action,user_id:<?= $viewingUserId ?>})})
            .then(r=>r.json()).then(d => {
                if (d.success) {
                    if (action === 'block') { this.innerHTML = '<i class="fas fa-ban"></i> لابردنی بلۆک'; this.style.color = '#4ade80'; this.dataset.blocked = '1'; CineSound.success(); }
                    else { this.innerHTML = '<i class="fas fa-ban"></i> بلۆک'; this.style.color = 'var(--red)'; this.dataset.blocked = '0'; }
                }
            });
    });
}

// Report user
function showReportUserModal(userId) {
    let modal = document.getElementById('report-user-modal'); if (modal) modal.remove();
    modal = document.createElement('div'); modal.id = 'report-user-modal'; modal.className = 'report-modal-overlay-wrap';
    modal.innerHTML = `<div class="report-modal-overlay" onclick="this.parentElement.remove()"></div><div class="report-modal-content glass-strong"><button class="report-modal-close" onclick="this.closest('.report-modal-overlay-wrap').remove()">&times;</button><h3 style="color:var(--gold);margin-bottom:18px;"><i class="fas fa-flag"></i> ڕاپۆرتکردنی بەکارهێنەر</h3><textarea id="report-user-reason" class="form-control" rows="3" placeholder="هۆکاری ڕاپۆرت بنووسە..." style="font-size:16px !important;margin-bottom:12px;"></textarea><button class="btn btn-gold btn-sm" onclick="submitUserReport(${userId})"><i class="fas fa-paper-plane"></i> ناردن</button></div>`;
    document.body.appendChild(modal); requestAnimationFrame(() => modal.classList.add('show'));
}
function submitUserReport(userId) {
    const reason = document.getElementById('report-user-reason').value.trim(); if (!reason) return;
    fetch(`${window.SITE_URL}/api/block.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'report_user',user_id:userId,reason:reason})})
        .then(r=>r.json()).then(d => { if (d.success) { document.getElementById('report-user-modal')?.remove(); CineSound.success(); alert('ڕاپۆرتەکەت نێردرا!'); } });
}

// Follow modal
function openFollowModal(type, userId) {
    const title = type === 'followers' ? 'فۆڵۆوەرەکان' : 'فۆڵۆکردنەکان';
    let modal = document.getElementById('follow-list-modal'); if (modal) modal.remove();
    modal = document.createElement('div'); modal.id = 'follow-list-modal'; modal.className = 'follow-modal-wrap';
    modal.innerHTML = `<div class="follow-modal-overlay"></div><div class="follow-modal-content glass-strong"><div class="follow-modal-header"><h3><i class="fas fa-users"></i> ${title}</h3><button class="follow-modal-close">&times;</button></div><div class="follow-modal-body" id="follow-modal-list"><div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--gold);"></i></div></div></div>`;
    document.body.appendChild(modal); requestAnimationFrame(() => modal.classList.add('show'));
    modal.querySelector('.follow-modal-overlay').addEventListener('click', () => closeFollowModal());
    modal.querySelector('.follow-modal-close').addEventListener('click', () => closeFollowModal());
    fetch(`${window.SITE_URL}/api/follow.php?action=${type}&user_id=${userId}`, {credentials:'same-origin'}).then(r=>r.json()).then(data => {
        const listEl = document.getElementById('follow-modal-list');
        if (!data.success || !data.users || data.users.length === 0) { listEl.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray);"><p>هیچ کەسێک نییە</p></div>'; return; }
        if (data.hidden) { listEl.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray);"><i class="fas fa-lock" style="font-size:2rem;margin-bottom:10px;display:block;"></i><p>شاراوەتەوە</p></div>'; return; }
        listEl.innerHTML = data.users.map(u => {
            const av = (u.avatar && u.avatar !== 'default.png') ? `<img src="${window.SITE_URL}/uploads/avatars/${u.avatar}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">` : `<i class="fas fa-user-circle" style="font-size:2rem;color:var(--gray);"></i>`;
            return `<div class="follow-modal-item"><a href="${window.SITE_URL}/profile.php?id=${u.id}" class="follow-modal-user">${av}<span>${u.username}</span></a></div>`;
        }).join('');
    });
}
function closeFollowModal() { const m = document.getElementById('follow-list-modal'); if (m) { m.classList.remove('show'); setTimeout(() => m.remove(), 300); } }

// ============ STORIES SYSTEM ============
let currentStories = [];
let currentStoryIndex = 0;
let storyTimer = null;

fetch(`${window.SITE_URL}/api/stories.php?action=has_story&user_id=<?= $viewingUserId ?>`, {credentials:'same-origin'})
    .then(r=>r.json()).then(d => {
        if (d.success && d.has_story) {
            const ring = document.getElementById('story-ring');
            if (ring) { ring.style.display = 'block'; const circle = document.getElementById('story-ring-circle'); if (d.all_viewed && circle) circle.style.stroke = 'var(--gray)'; }
        }
    }).catch(()=>{});

function handleAvatarClick() {
    fetch(`${window.SITE_URL}/api/stories.php?action=get_user_stories&user_id=<?= $viewingUserId ?>`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            if (d.success && d.stories.length > 0) { currentStories = d.stories; currentStoryIndex = 0; openStoryViewer(); }
        });
}

function openStoryViewer() { document.getElementById('story-viewer-overlay').style.display = 'flex'; document.body.style.overflow = 'hidden'; showStory(currentStoryIndex); }
function closeStoryViewer() { document.getElementById('story-viewer-overlay').style.display = 'none'; document.body.style.overflow = ''; clearTimeout(storyTimer); }

function showStory(index) {
    if (index < 0 || index >= currentStories.length) { closeStoryViewer(); return; }
    currentStoryIndex = index;
    const story = currentStories[index];
    document.getElementById('story-viewer-progress').innerHTML = currentStories.map((s, i) => 
        `<div class="story-progress-bar ${i < index ? 'viewed' : ''} ${i === index ? 'active' : ''}"><div class="story-progress-fill"></div></div>`
    ).join('');
    document.getElementById('story-viewer-username').textContent = story.username;
    document.getElementById('story-viewer-time').textContent = story.time_ago;
    const avatarDiv = document.getElementById('story-viewer-avatar');
    avatarDiv.innerHTML = story.avatar && story.avatar !== 'default.png' 
        ? `<img src="${window.SITE_URL}/uploads/avatars/${story.avatar}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">` 
        : '<i class="fas fa-user-circle" style="font-size:1.8rem;color:var(--gray);"></i>';
    const mediaDiv = document.getElementById('story-viewer-media');
    if (story.media_type === 'text') {
        mediaDiv.innerHTML = `<div style="width:100%;min-height:300px;max-height:80vh;display:flex;align-items:center;justify-content:center;border-radius:16px;background:${story.text_bg_color || '#F5C518'};padding:30px;">
            <p style="color:#fff;font-size:1.4rem;font-weight:700;text-align:center;text-shadow:0 2px 8px rgba(0,0,0,0.3);line-height:1.8;word-break:break-word;">${story.text_content}</p>
        </div>`;
    } else if (story.media_type === 'video') {
        mediaDiv.innerHTML = `<video src="${window.SITE_URL}/uploads/stories/${story.media_file}" autoplay playsinline style="max-width:100%;max-height:80vh;border-radius:12px;"></video>`;
    } else {
        mediaDiv.innerHTML = `<img src="${window.SITE_URL}/uploads/stories/${story.media_file}" style="max-width:100%;max-height:80vh;border-radius:12px;object-fit:contain;">`;
    }
    const captionDiv = document.getElementById('story-viewer-caption');
    captionDiv.textContent = story.caption || '';
    captionDiv.style.display = story.caption ? 'block' : 'none';
    const viewCountEl = document.getElementById('story-view-count');
    if (viewCountEl) viewCountEl.textContent = story.views_count;
    fetch(`${window.SITE_URL}/api/stories.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'view',story_id:story.id})});
    clearTimeout(storyTimer);
    storyTimer = setTimeout(() => nextStory(), 5000);
}
function nextStory() { showStory(currentStoryIndex + 1); }
function prevStory() { showStory(currentStoryIndex - 1); }

// Story Creator
let selectedStoryType = 'image';
let selectedStoryBg = '#F5C518';
function openStoryCreator() { document.getElementById('story-creator-modal').style.display = 'flex'; document.body.style.overflow = 'hidden'; }
function closeStoryCreator() { document.getElementById('story-creator-modal').style.display = 'none'; document.body.style.overflow = ''; }
function selectStoryType(type) {
    selectedStoryType = type;
    document.getElementById('story-image-section').style.display = type === 'image' ? '' : 'none';
    document.getElementById('story-text-section').style.display = type === 'text' ? '' : 'none';
    document.getElementById('story-type-image-btn').className = type === 'image' ? 'btn btn-gold' : 'btn btn-glass';
    document.getElementById('story-type-text-btn').className = type === 'text' ? 'btn btn-gold' : 'btn btn-glass';
}
function selectStoryBg(color) { selectedStoryBg = color; document.querySelectorAll('.story-bg-btn').forEach(b => b.style.borderColor = 'transparent'); event.target.style.borderColor = '#fff'; }
function uploadStoryFile(input) {
    if (!input.files[0]) return;
    const formData = new FormData();
    formData.append('story_media', input.files[0]);
    formData.append('action', 'upload');
    formData.append('story_type', 'image');
    fetch(`${window.SITE_URL}/api/stories.php`, {method:'POST',credentials:'same-origin',body:formData})
        .then(r => {
            if (!r.ok) throw new Error('Server error: ' + r.status);
            return r.json();
        })
        .then(d => { if (d.success) { CineSound.success(); closeStoryCreator(); location.reload(); } else alert(d.message || 'هەڵە'); })
        .catch(err => alert('هەڵەی ئاپڵۆد: ' + err.message));
    input.value = '';
}
function uploadTextStory() {
    const text = document.getElementById('story-text-content').value.trim();
    if (!text) { alert('تکایە دەقێک بنووسە'); return; }
    fetch(`${window.SITE_URL}/api/stories.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',
        body:JSON.stringify({action:'upload',story_type:'text',text_content:text,text_bg_color:selectedStoryBg})
    }).then(r=>r.json()).then(d => { if (d.success) { CineSound.success(); closeStoryCreator(); location.reload(); } else alert(d.message || 'هەڵە'); });
}
function showStoryViewers() {
    const story = currentStories[currentStoryIndex];
    fetch(`${window.SITE_URL}/api/stories.php?action=viewers&story_id=${story.id}`,{credentials:'same-origin'})
        .then(r=>r.json()).then(d => { if (d.success) alert('بینەران: ' + d.viewers.map(v=>v.username).join(', ')); });
}
function loadMyStories() {
    fetch(`${window.SITE_URL}/api/stories.php?action=my_stories`, {credentials:'same-origin'})
        .then(r=>r.json()).then(d => {
            const list = document.getElementById('my-stories-list');
            if (!d.success || !d.stories || d.stories.length === 0) {
                list.innerHTML = '<div style="text-align:center;padding:40px;grid-column:1/-1;"><i class="fas fa-camera" style="font-size:2rem;color:var(--gray);margin-bottom:12px;display:block;"></i><p style="color:var(--gray);">هیچ ستۆرییەکی چالاکت نییە</p><button class="btn btn-gold btn-sm" style="margin-top:12px;" onclick="openStoryCreator()"><i class="fas fa-plus"></i> زیادکردن</button></div>';
                return;
            }
            list.innerHTML = d.stories.map(s => {
                let preview = '';
                if (s.media_type === 'text') {
                    preview = `<div style="width:100%;height:200px;border-radius:12px;background:${s.text_bg_color || '#F5C518'};display:flex;align-items:center;justify-content:center;padding:16px;"><p style="color:#fff;font-size:0.9rem;font-weight:600;text-align:center;text-shadow:0 1px 4px rgba(0,0,0,0.3);word-break:break-word;">${s.text_content}</p></div>`;
                } else if (s.media_type === 'video') {
                    preview = `<video src="${window.SITE_URL}/uploads/stories/${s.media_file}" style="width:100%;height:200px;object-fit:cover;border-radius:12px;" muted></video>`;
                } else {
                    preview = `<img src="${window.SITE_URL}/uploads/stories/${s.media_file}" style="width:100%;height:200px;object-fit:cover;border-radius:12px;">`;
                }
                return `<div class="glass" style="border-radius:14px;overflow:hidden;">
                    ${preview}
                    <div style="padding:10px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.75rem;color:var(--gray);"><i class="fas fa-eye"></i> ${s.total_views}</span>
                            <button class="btn btn-danger btn-xs" onclick="deleteStory(${s.id})"><i class="fas fa-trash"></i></button>
                        </div>
                        ${s.caption ? `<p style="font-size:0.78rem;color:var(--gray-light);margin-top:4px;">${s.caption}</p>` : ''}
                    </div>
                </div>`;
            }).join('');
        });
}
function deleteStory(id) {
    if (!confirm('سڕینەوەی ئەم ستۆرییە؟')) return;
    fetch(`${window.SITE_URL}/api/stories.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'delete',story_id:id})})
        .then(r=>r.json()).then(d => { if (d.success) { CineSound.success(); loadMyStories(); } });
}

// Cinema Mood
fetch(`${window.SITE_URL}/api/cinema-mood.php?action=get&user_id=<?= $viewingUserId ?>`, {credentials:'same-origin'})
    .then(r=>r.json()).then(d => {
        if (d.success && d.mood) { document.getElementById('cinema-mood-text').textContent = d.mood; document.getElementById('cinema-mood-display').style.display = ''; }
    }).catch(()=>{});

function toggleInlineMoodEdit() { const ed = document.getElementById('inline-mood-editor'); ed.style.display = ed.style.display === 'none' ? '' : 'none'; }
function toggleInlineEmojiEdit() { const ed = document.getElementById('inline-emoji-editor'); ed.style.display = ed.style.display === 'none' ? '' : 'none'; }
function setMoodPreset(text) { document.getElementById('cinema-mood-input').value = text; }
function saveCinemaMood() {
    const mood = document.getElementById('cinema-mood-input').value.trim();
    fetch(`${window.SITE_URL}/api/cinema-mood.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'set',mood:mood})})
        .then(r=>r.json()).then(d => { if (d.success) { CineSound.success(); location.reload(); } });
}
function clearCinemaMood() {
    fetch(`${window.SITE_URL}/api/cinema-mood.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'clear'})})
        .then(r=>r.json()).then(d => { if (d.success) { CineSound.success(); location.reload(); } });
}
function setPremiumEmoji(emoji) {
    fetch(`${window.SITE_URL}/api/bio.php`, {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'set_premium_emoji',emoji:emoji})})
        .then(r=>r.json()).then(d => { if (d.success) { CineSound.success(); location.reload(); } });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
