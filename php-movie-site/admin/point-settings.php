<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'تکایە دووبارە هەوڵ بدەرەوە.';
    } else {
        $pointsWatch = (int)($_POST['points_watch'] ?? 5);
        $pointsInvite = (int)($_POST['points_invite'] ?? 10);
        $pointsComment = (int)($_POST['points_comment'] ?? 2);
        $vipThreshold = (int)($_POST['vip_threshold'] ?? 500);

        $settings = [
            'points_watch' => $pointsWatch,
            'points_invite' => $pointsInvite,
            'points_comment' => $pointsComment,
            'vip_threshold' => $vipThreshold,
        ];

        foreach ($settings as $key => $value) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$key, (string)$value, (string)$value]);
        }
        $success = 'ڕێکخستنەکانی خاڵ بە سەرکەوتوویی پاشەکەوت کران!';
    }
}

// Load current settings
$currentSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('points_watch','points_invite','points_comment','vip_threshold')");
    while ($row = $stmt->fetch()) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$pointsWatch = (int)($currentSettings['points_watch'] ?? 5);
$pointsInvite = (int)($currentSettings['points_invite'] ?? 10);
$pointsComment = (int)($currentSettings['points_comment'] ?? 2);
$vipThreshold = (int)($currentSettings['vip_threshold'] ?? 500);

// Get top users
$topUsers = [];
try {
    $topUsers = $pdo->query("SELECT up.*, u.username FROM user_points up JOIN users u ON up.user_id = u.id ORDER BY up.points DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/sidebar.php';
?>

<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ڕێکخستنی خاڵەکان - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="rtl admin-body">
    
    <button class="admin-toggle"><i class="fas fa-bars"></i></button>

    <div class="admin-content">
        <div class="admin-header">
            <h1><i class="fas fa-coins"></i> ڕێکخستنی خاڵەکان (Point Settings)</h1>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:20px;"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <div class="admin-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <!-- Point Values -->
            <div class="panel-card glass">
                <h3 style="color:var(--gold);margin-bottom:20px;"><i class="fas fa-sliders-h"></i> بڕی خاڵەکان</h3>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                    
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="color:var(--gray-light);font-size:0.88rem;margin-bottom:6px;display:block;">
                            <i class="fas fa-film"></i> خاڵ بۆ سەیرکردنی فیلم
                        </label>
                        <input type="number" name="points_watch" class="form-control" value="<?= $pointsWatch ?>" min="0" max="1000" required>
                        <small class="text-gray">هەر جارێک فیلمێک سەیر بکات ئەم بڕە خاڵەی وەردەگرێت</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="color:var(--gray-light);font-size:0.88rem;margin-bottom:6px;display:block;">
                            <i class="fas fa-user-plus"></i> خاڵ بۆ بانگهێشتکردن (Invite)
                        </label>
                        <input type="number" name="points_invite" class="form-control" value="<?= $pointsInvite ?>" min="0" max="1000" required>
                        <small class="text-gray">هەر کەسێک بانگهێشت بکات ئەم بڕە خاڵەی وەردەگرێت</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="color:var(--gray-light);font-size:0.88rem;margin-bottom:6px;display:block;">
                            <i class="fas fa-comment"></i> خاڵ بۆ بۆچوون نووسین
                        </label>
                        <input type="number" name="points_comment" class="form-control" value="<?= $pointsComment ?>" min="0" max="1000" required>
                        <small class="text-gray">هەر بۆچوونێک بنووسێت ئەم بڕە خاڵەی وەردەگرێت</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="color:var(--gray-light);font-size:0.88rem;margin-bottom:6px;display:block;">
                            <i class="fas fa-crown"></i> ئاستی VIP (VIP Threshold)
                        </label>
                        <input type="number" name="vip_threshold" class="form-control" value="<?= $vipThreshold ?>" min="1" max="100000" required>
                        <small class="text-gray">کاتێک بەکارهێنەر بگاتە ئەم بڕە خاڵە، VIP دەبێت بۆ ٣٠ ڕۆژ</small>
                    </div>
                    
                    <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center;">
                        <i class="fas fa-save"></i> پاشەکەوتکردن
                    </button>
                </form>
            </div>

            <!-- Top Users & Quick Adjust -->
            <div class="panel-card glass">
                <h3 style="color:var(--gold);margin-bottom:20px;"><i class="fas fa-trophy"></i> باڵاترین خاڵەکان</h3>
                <?php if (empty($topUsers)): ?>
                <p class="text-gray" style="text-align:center;padding:20px;">هیچ داتایەک نییە</p>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($topUsers as $i => $tu): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:rgba(255,255,255,0.03);border-radius:var(--radius-xs);border:1px solid rgba(255,255,255,0.05);">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="color:<?= $i < 3 ? 'var(--gold)' : 'var(--gray)' ?>;font-weight:700;min-width:22px;">#<?= $i + 1 ?></span>
                            <span><?= clean($tu['username']) ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="color:var(--gold);font-weight:600;"><i class="fas fa-coins"></i> <?= number_format($tu['points']) ?></span>
                            <?php if ($tu['vip_until'] && strtotime($tu['vip_until']) > time()): ?>
                            <span style="background:linear-gradient(135deg,var(--gold),var(--gold-light));color:var(--black);padding:2px 8px;border-radius:var(--radius-full);font-size:0.7rem;font-weight:700;">VIP</span>
                            <?php endif; ?>
                            <div style="display:flex;gap:4px;">
                                <button class="btn btn-glass btn-xs admin-adjust-points" data-user-id="<?= $tu['user_id'] ?>" data-action="add" title="زیادکردنی خاڵ">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="btn btn-glass btn-xs admin-adjust-points" data-user-id="<?= $tu['user_id'] ?>" data-action="remove" title="کەمکردنەوەی خاڵ">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);">
                    <h4 style="color:var(--gray-light);font-size:0.9rem;margin-bottom:12px;"><i class="fas fa-user-cog"></i> دەستکاری خاڵی بەکارهێنەر</h4>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <input type="number" id="adjust-user-id" class="form-control" placeholder="ID ی بەکارهێنەر" style="flex:1;min-width:100px;">
                        <input type="number" id="adjust-points" class="form-control" placeholder="بڕی خاڵ (- بۆ کەمکردنەوە)" style="flex:1;min-width:100px;">
                        <button class="btn btn-gold btn-sm" id="adjust-submit"><i class="fas fa-edit"></i> جێبەجێکردن</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    window.SITE_URL = '<?= SITE_URL ?>';
    
    // Quick adjust buttons
    document.querySelectorAll('.admin-adjust-points').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const action = this.dataset.action;
            const amount = prompt(action === 'add' ? 'چەند خاڵ زیاد بکرێت؟' : 'چەند خاڵ کەم بکرێتەوە؟', '10');
            if (!amount) return;
            const points = action === 'add' ? parseInt(amount) : -parseInt(amount);
            
            fetch(`${window.SITE_URL}/api/points.php`, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({action:'admin_adjust', user_id: parseInt(userId), points: points})
            }).then(r => r.json()).then(d => {
                if (d.success) location.reload();
                else alert('هەڵە');
            });
        });
    });

    // Manual adjust
    document.getElementById('adjust-submit')?.addEventListener('click', function() {
        const userId = parseInt(document.getElementById('adjust-user-id').value);
        const points = parseInt(document.getElementById('adjust-points').value);
        if (!userId || isNaN(points)) { alert('تکایە زانیارییەکان پڕ بکەرەوە'); return; }
        
        fetch(`${window.SITE_URL}/api/points.php`, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({action:'admin_adjust', user_id: userId, points: points})
        }).then(r => r.json()).then(d => {
            if (d.success) { alert('سەرکەوتوو!'); location.reload(); }
            else alert('هەڵە');
        });
    });
    </script>
    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>