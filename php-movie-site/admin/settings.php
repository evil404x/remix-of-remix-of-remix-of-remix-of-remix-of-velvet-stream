<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';
$error = '';

// Get current settings
function getSetting(PDO $pdo, string $key): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn() ?: '';
}

// Handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'general') {
        $fields = ['site_name','site_description','contact_email','site_logo','favicon','loader_logo','tmdb_api_key','facebook_url','twitter_url','instagram_url','telegram_url','youtube_url'];
        foreach ($fields as $f) {
            $val = clean($_POST[$f] ?? '');
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                ->execute([$f, $val]);
        }
        $success = 'ڕێکخستنەکان پاشەکەوت کران!';
    }
    
    if ($action === 'footer') {
        $footerFields = ['footer_text','copyright_text'];
        foreach ($footerFields as $f) {
            $val = clean($_POST[$f] ?? '');
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                ->execute([$f, $val]);
        }
        $success = 'فووتەر پاشەکەوت کرا!';
    }
    
    if ($action === 'maintenance') {
        $mode = isset($_POST['maintenance_mode']) ? '1' : '0';
        $msg = clean($_POST['maintenance_message'] ?? 'ئێستا چاکسازیمان هەیە. بەمزووانە دەگەڕێینەوە!');
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$mode]);
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('maintenance_message', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$msg]);
        $success = $mode === '1' ? '⚠️ سایتەکە خرایە بارکردن (Maintenance Mode)' : '✅ سایتەکە کرایەوە!';
    }
}

// Load settings
$s = [];
$allSettings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
foreach ($allSettings as $row) {
    $s[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ڕێکخستنەکانی سایت - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>window.SITE_URL='<?= SITE_URL ?>';</script>
</head>
<body class="rtl">
<div class="admin-layout">
    <?php include __DIR__.'/sidebar.php'; ?>
    <button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>

    <main class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-cog text-gold"></i> ڕێکخستنەکانی سایت</h1>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <!-- Maintenance Mode -->
        <div class="panel-card glass">
            <h3><i class="fas fa-tools"></i> باری چاکسازی (Maintenance Mode)</h3>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                <input type="hidden" name="action" value="maintenance">
                
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:12px; cursor:pointer; padding: 14px; background: <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'rgba(255,68,68,0.08)' : 'rgba(68,255,136,0.05)' ?>; border-radius: var(--radius-sm); border: 1px solid <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'rgba(255,68,68,0.2)' : 'rgba(68,255,136,0.15)' ?>;">
                        <input type="checkbox" name="maintenance_mode" <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--gold);">
                        <div>
                            <strong style="color: <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'var(--red)' : 'var(--green)' ?>;">
                                <?= ($s['maintenance_mode'] ?? '0') === '1' ? '⚠️ سایتەکە ئێستا داخراوە!' : '✅ سایتەکە کراوەیە' ?>
                            </strong>
                            <p style="font-size:0.82rem; color:var(--gray); margin-top:4px;">کاتێک چالاک بکەیت، بینەران لاپەڕەی چاکسازی دەبینن</p>
                        </div>
                    </label>
                </div>
                
                <div class="form-group">
                    <label>پەیامی چاکسازی</label>
                    <textarea name="maintenance_message" class="form-control" rows="2"><?= clean($s['maintenance_message'] ?? 'ئێستا چاکسازیمان هەیە. بەمزووانە دەگەڕێینەوە!') ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-save"></i> پاشەکەوت</button>
            </form>
        </div>

        <!-- Footer Editor -->
        <div class="panel-card glass">
            <h3><i class="fas fa-shoe-prints"></i> ویراستنی فووتەر</h3>
            <form method="POST" class="admin-form">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                <input type="hidden" name="action" value="footer">
                
                <div class="form-group">
                    <label>دەقی فووتەر (باسکردن)</label>
                    <textarea name="footer_text" class="form-control" rows="2" placeholder="باسکردنەکەی فووتەر بنووسە..."><?= clean($s['footer_text'] ?? 'باشترین وێبسایتی فیلم و زنجیرە بە کوالیتیی بەرز و سێ زمانی جیاواز.') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>دەقی مافی بەرهەمهێنان (Copyright)</label>
                    <input type="text" name="copyright_text" class="form-control" value="<?= clean($s['copyright_text'] ?? '© ' . date('Y') . ' ' . SITE_NAME . ' - هەموو مافێک پارێزراوە') ?>">
                </div>
                
                <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-save"></i> پاشەکەوتکردنی فووتەر</button>
            </form>
        </div>

        <!-- General Settings -->
        <div class="panel-card glass">
            <h3><i class="fas fa-globe"></i> ڕێکخستنەکانی گشتی</h3>
            <form method="POST" class="admin-form">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                <input type="hidden" name="action" value="general">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>ناوی سایت</label>
                        <input type="text" name="site_name" class="form-control" value="<?= clean($s['site_name'] ?? 'CineGold') ?>">
                    </div>
                    <div class="form-group">
                        <label>ئیمەیڵی پەیوەندی</label>
                        <input type="email" name="contact_email" class="form-control" value="<?= clean($s['contact_email'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>باسکردنی سایت</label>
                    <textarea name="site_description" class="form-control" rows="2"><?= clean($s['site_description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>لۆگۆ (ناوی فایل)</label>
                        <input type="text" name="site_logo" class="form-control" value="<?= clean($s['site_logo'] ?? 'logo.png') ?>">
                    </div>
                    <div class="form-group">
                        <label>فەڤیکۆن (ناوی فایل)</label>
                        <input type="text" name="favicon" class="form-control" value="<?= clean($s['favicon'] ?? 'favicon.ico') ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>لۆگۆی لۆدەر (ناوی فایل)</label>
                        <input type="text" name="loader_logo" class="form-control" value="<?= clean($s['loader_logo'] ?? '') ?>" placeholder="loader.gif">
                    </div>
                    <div class="form-group">
                        <label>دەقی فووتەر</label>
                        <input type="text" name="footer_text" class="form-control" value="<?= clean($s['footer_text'] ?? '') ?>" placeholder="هەموو مافێکی پارێزراوە">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>دەقی مافی پاراستن (Copyright)</label>
                    <input type="text" name="copyright_text" class="form-control" value="<?= clean($s['copyright_text'] ?? '© 2026 CineGold - هەموو مافێک پارێزراوە') ?>">
                </div>

                <h4 style="color:var(--gold); margin: 25px 0 15px;"><i class="fas fa-key"></i> TMDB API</h4>
                <div class="form-group">
                    <label>TMDB API Key</label>
                    <input type="text" name="tmdb_api_key" class="form-control" value="<?= clean($s['tmdb_api_key'] ?? '') ?>" placeholder="API Key لێرە بنووسە...">
                    <small class="text-gray">لە <a href="https://www.themoviedb.org/settings/api" target="_blank" style="color:var(--gold);">tmdb.org</a> بیهێنە</small>
                </div>

                <h4 style="color:var(--gold); margin: 25px 0 15px;"><i class="fas fa-share-alt"></i> سۆشیاڵ میدیا</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fab fa-facebook"></i> Facebook</label>
                        <input type="url" name="facebook_url" class="form-control" value="<?= clean($s['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/...">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-twitter"></i> Twitter</label>
                        <input type="url" name="twitter_url" class="form-control" value="<?= clean($s['twitter_url'] ?? '') ?>" placeholder="https://twitter.com/...">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fab fa-instagram"></i> Instagram</label>
                        <input type="url" name="instagram_url" class="form-control" value="<?= clean($s['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/...">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-telegram"></i> Telegram</label>
                        <input type="url" name="telegram_url" class="form-control" value="<?= clean($s['telegram_url'] ?? '') ?>" placeholder="https://t.me/...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-youtube"></i> YouTube</label>
                    <input type="url" name="youtube_url" class="form-control" value="<?= clean($s['youtube_url'] ?? '') ?>" placeholder="https://youtube.com/...">
                </div>
                
                <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> پاشەکەوتکردن</button>
            </form>
        </div>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
