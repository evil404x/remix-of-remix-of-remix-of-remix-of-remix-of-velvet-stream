<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/functions.php';

// Load site name from settings
$dynamicSiteName = SITE_NAME;
try {
    $snStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
    $snStmt->execute();
    $dbSiteName = $snStmt->fetchColumn();
    if ($dbSiteName && !empty(trim($dbSiteName))) {
        $dynamicSiteName = $dbSiteName;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= clean($dynamicSiteName) ?> - بەهێزترین وێبسایتی فیلم</title>
    <meta name="description" content="باشترین وێبسایتی فیلم و زنجیرە بە کوالیتیی بەرز و سێ زمان: کوردی، عەرەبی، ئینگلیزی">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script>window.SITE_URL = '<?= SITE_URL ?>';</script>
    <script src="<?= SITE_URL ?>/assets/js/stickers.js?v=<?= filemtime(__DIR__ . '/../assets/js/stickers.js') ?>"></script>
    <script>
    (function(){
        var CACHE_VERSION = '<?= filemtime(__DIR__ . '/../assets/css/style.css') . filemtime(__DIR__ . '/../assets/js/main.js') ?>';
        var storedVersion = localStorage.getItem('cg_cache_v');
        if (storedVersion && storedVersion !== CACHE_VERSION) {
            if ('caches' in window) { caches.keys().then(function(names) { names.forEach(function(name) { caches.delete(name); }); }); }
            localStorage.setItem('cg_cache_v', CACHE_VERSION);
            location.reload(true);
        } else if (!storedVersion) { localStorage.setItem('cg_cache_v', CACHE_VERSION); }
    })();
    </script>
</head>
<body class="rtl"<?= isLoggedIn() ? ' data-logged-in="1"' : '' ?>>

<!-- Preloader -->
<div class="preloader">
    <div class="preloader-content">
        <div class="preloader-logo shah-pulse">🎬 <?= clean($dynamicSiteName) ?></div>
        <div class="preloader-spinner"></div>
        <div class="preloader-bar"></div>
    </div>
</div>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay"></div>

<!-- Inbox Overlay (TikTok Style - Unified) -->
<?php if (isLoggedIn()): ?>
<div class="inbox-overlay" id="inbox-overlay">
    <div class="inbox-overlay-header">
        <button class="inbox-overlay-close" id="inbox-overlay-close"><i class="fas fa-arrow-right"></i></button>
        <h3>Inbox <span class="inbox-online-dot"></span></h3>
        <button class="inbox-search-btn" id="inbox-search-toggle"><i class="fas fa-search"></i></button>
    </div>
    <div class="inbox-category-tabs">
        <button class="inbox-cat-tab active" data-inbox-cat="all"><span>هەموو</span></button>
        <button class="inbox-cat-tab" data-inbox-cat="followers"><i class="fas fa-user-plus"></i><span>فۆڵۆوەر</span></button>
        <button class="inbox-cat-tab" data-inbox-cat="invites"><i class="fas fa-film"></i><span>بانگهێشت</span></button>
        <button class="inbox-cat-tab" data-inbox-cat="messages"><i class="fas fa-envelope"></i><span>نامەکان</span></button>
    </div>
    <div class="inbox-overlay-body" id="inbox-feed">
        <div class="inbox-loading"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
</div>

<!-- User Search Modal -->
<div class="user-search-modal" id="user-search-modal">
    <div class="user-search-modal-overlay"></div>
    <div class="user-search-modal-content glass-strong">
        <div class="user-search-modal-header">
            <h3><i class="fas fa-user-plus"></i> گەڕانی بەکارهێنەر</h3>
            <button class="user-search-modal-close">&times;</button>
        </div>
        <div class="user-search-modal-input">
            <input type="text" id="global-user-search" placeholder="ناوی بەکارهێنەر بنووسە..." autocomplete="off">
        </div>
        <div class="user-search-modal-results" id="global-user-results">
            <p class="text-gray" style="text-align:center;padding:30px;">ناوی بەکارهێنەر بنووسە بۆ گەڕان</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Navbar -->
<nav class="navbar glass">
    <div class="nav-container">
        <a href="<?= SITE_URL ?>" class="nav-logo">🎬 <?= clean($dynamicSiteName) ?></a>
        
        <ul class="nav-links">
            <li><a href="<?= SITE_URL ?>"><i class="fas fa-home"></i> سەرەتا</a></li>
            <li><a href="<?= SITE_URL ?>/search.php?type=movie"><i class="fas fa-film"></i> فیلمەکان</a></li>
            <li><a href="<?= SITE_URL ?>/search.php?type=series"><i class="fas fa-tv"></i> زنجیرەکان</a></li>
            <li><a href="<?= SITE_URL ?>/request.php"><i class="fas fa-plus-circle"></i> داواکردنی فیلم</a></li>
            <?php if (isLoggedIn()): ?>
            <li><a href="<?= SITE_URL ?>/stories.php"><i class="fas fa-circle-play"></i> ستۆریەکان</a></li>
            <li class="nav-mobile-only"><a href="<?= SITE_URL ?>/messages.php"><i class="fas fa-envelope"></i> نامەکان</a></li>
                <li class="nav-mobile-only"><a href="<?= SITE_URL ?>/profile.php"><i class="fas fa-user-circle"></i> پرۆفایل</a></li>
                <li class="nav-mobile-only"><a href="<?= SITE_URL ?>/favorites.php"><i class="fas fa-heart"></i> دڵخوازەکان</a></li>

                <?php if (isAdmin()): ?>
                    <li class="nav-mobile-only"><a href="<?= adminUrl() ?>"><i class="fas fa-crown"></i> پانێڵی ئادمین</a></li>
                <?php endif; ?>
                <li class="nav-mobile-only"><a href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> چوونەدەرەوە</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="گەڕان بۆ فیلم..." autocomplete="off">
            <div class="search-dropdown glass-strong" style="display:none;"></div>
        </div>
        
        <div class="nav-user">
            <?php if (isLoggedIn()): ?>
                <div class="smart-hub nav-desktop-only">
                    <button class="smart-hub-btn" id="global-user-search-btn" title="گەڕانی بەکارهێنەر"><i class="fas fa-user-plus"></i></button>
                    <button class="smart-hub-btn" id="hub-followers-btn" title="فۆڵۆوەرە نوێیەکان"><i class="fas fa-user-friends"></i><span class="smart-hub-badge" id="hub-followers-badge" style="display:none;">0</span></button>
                    <a href="<?= SITE_URL ?>/messages.php" class="smart-hub-btn" id="hub-messages-btn" title="نامەکان"><i class="fas fa-envelope"></i><span class="smart-hub-badge" id="hub-messages-badge" style="display:none;">0</span></a>
                    <button class="smart-hub-btn" id="hub-notif-btn" title="ئاگادارکردنەوەکان"><i class="fas fa-bell"></i><span class="smart-hub-badge" id="hub-notif-badge" style="display:none;">0</span></button>
                </div>
                <a href="<?= SITE_URL ?>/profile.php" class="btn btn-glass btn-sm nav-desktop-only" title="پرۆفایل"><i class="fas fa-user-circle"></i></a>
                <a href="<?= SITE_URL ?>/favorites.php" class="btn btn-glass btn-sm nav-desktop-only"><i class="fas fa-heart"></i></a>
                <?php if (isAdmin()): ?>
                    <a href="<?= adminUrl() ?>" class="btn btn-gold btn-sm nav-desktop-only"><i class="fas fa-crown"></i> پانێل</a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>/logout.php" class="btn btn-glass btn-sm nav-desktop-only"><i class="fas fa-sign-out-alt"></i></a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/login.php" class="btn btn-gold btn-sm"><i class="fas fa-user"></i> چوونەژوورەوە</a>
            <?php endif; ?>
            <button class="mobile-toggle" aria-label="Menu"><i class="fas fa-bars"></i></button>
        </div>
    </div>
</nav>

<?php if (isLoggedIn()): ?>
<!-- Mobile Bottom Bar -->
<div class="mobile-bottom-bar">
    <a href="<?= SITE_URL ?>" class="mobile-bar-item"><i class="fas fa-home"></i><span>سەرەتا</span></a>
    <button class="mobile-bar-item" id="mobile-user-search-btn"><i class="fas fa-user-plus"></i><span>گەڕان</span></button>
    <a href="<?= SITE_URL ?>/messages.php" class="mobile-bar-item"><i class="fas fa-envelope"></i><span class="mobile-bar-badge" id="mobile-msg-badge" style="display:none;">0</span><span>نامە</span></a>
    <button class="mobile-bar-item" id="mobile-inbox-btn"><i class="fas fa-bell"></i><span class="mobile-bar-badge" id="mobile-notif-badge" style="display:none;">0</span><span>ئینبۆکس</span></button>
    <a href="<?= SITE_URL ?>/profile.php" class="mobile-bar-item"><i class="fas fa-user-circle"></i><span>پرۆفایل</span></a>
</div>
<?php endif; ?>
