<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear' && verifyCSRF($_POST['csrf'] ?? '')) {
    $pdo->exec("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
}

$logs = $pdo->query("SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 100")->fetchAll();

// Stats
$totalBlocked = $pdo->query("SELECT COUNT(*) FROM security_logs WHERE blocked = 1")->fetchColumn();
$today = $pdo->query("SELECT COUNT(*) FROM security_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$topIps = $pdo->query("SELECT ip_address, COUNT(*) as count FROM security_logs GROUP BY ip_address ORDER BY count DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ئاسایش - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="rtl">
<div class="admin-layout">
    <?php include __DIR__.'/sidebar.php'; ?>
    <button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>

    <main class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-shield-alt text-gold"></i> ڕاپۆرتی ئاسایش</h1>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-glass btn-sm"><i class="fas fa-broom"></i> پاککردنەوەی کۆن</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-ban"></i></div>
                <div class="stat-value"><?= number_format($totalBlocked) ?></div>
                <div class="stat-label">کۆی بلۆککراو</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-value"><?= $today ?></div>
                <div class="stat-label">هەڕەشەی ئەمڕۆ</div>
            </div>
        </div>

        <!-- Top Attacker IPs -->
        <?php if (!empty($topIps)): ?>
        <div class="glass" style="padding: 25px; margin-bottom: 25px;">
            <h3 style="margin-bottom: 15px; color: var(--gold);">⚠️ IPـە هەڕەشەکارەکان</h3>
            <?php foreach ($topIps as $ip): ?>
                <div style="display:flex; justify-content:space-between; padding:10px; border-bottom:1px solid var(--glass-border);">
                    <code style="color:var(--red);"><?= clean($ip['ip_address']) ?></code>
                    <span class="text-gold"><?= $ip['count'] ?> هەوڵ</span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Logs Table -->
        <div class="glass" style="padding: 25px;">
            <h3 style="margin-bottom: 20px; color: var(--gold);">لۆگەکان</h3>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>جۆری هەڕەشە</th>
                            <th>URI</th>
                            <th>بەروار</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><code style="color:var(--red);"><?= clean($log['ip_address']) ?></code></td>
                            <td><span class="tag"><?= clean($log['attack_type']) ?></span></td>
                            <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis;"><?= clean($log['request_uri']) ?></td>
                            <td><?= date('Y/m/d H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
