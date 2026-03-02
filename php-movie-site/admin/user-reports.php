<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Auto-create table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        reported_id INT NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
        admin_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = clean($_POST['note'] ?? '');
    
    if ($action === 'review' && $id) {
        $pdo->prepare("UPDATE user_reports SET status = 'reviewed', admin_note = ? WHERE id = ?")->execute([$note, $id]);
        // Send notification to reporter
        $report = $pdo->prepare("SELECT reporter_id, reported_id FROM user_reports WHERE id = ?");
        $report->execute([$id]);
        $rData = $report->fetch();
        if ($rData) {
            sendNotification($pdo, $rData['reporter_id'], 'ڕاپۆرتەکەت بینرا', 'ئادمین ڕاپۆرتەکەتی بینی و وەڵامی دایەوە: ' . $note, SITE_URL . '/profile.php?id=' . $rData['reported_id']);
        }
    }
    if ($action === 'dismiss' && $id) {
        $pdo->prepare("UPDATE user_reports SET status = 'dismissed' WHERE id = ?")->execute([$id]);
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

// Get reports
$reports = [];
try {
    $reports = $pdo->query("
        SELECT ur.*, 
            reporter.username as reporter_name,
            reported.username as reported_name
        FROM user_reports ur
        JOIN users reporter ON ur.reporter_id = reporter.id
        JOIN users reported ON ur.reported_id = reported.id
        ORDER BY ur.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ڕاپۆرتی بەکارهێنەران - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>window.SITE_URL='<?= SITE_URL ?>';</script>
</head>
<body class="rtl">
<div class="admin-layout">
    <?php include __DIR__.'/sidebar.php'; ?>
    <button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>

    <main class="admin-main">
        <div class="admin-header"><h1><i class="fas fa-exclamation-triangle text-gold"></i> ڕاپۆرتی بەکارهێنەران</h1></div>
        
        <?php if (empty($reports)): ?>
            <div class="panel-card"><p class="text-gray text-center" style="padding:30px;">هیچ ڕاپۆرتێک نییە 🎉</p></div>
        <?php else: ?>
        <div class="panel-card">
            <table class="admin-table">
                <thead><tr><th>ڕاپۆرتکەر</th><th>ڕاپۆرت کراو</th><th>هۆکار</th><th>بار</th><th>بەروار</th><th>کردار</th></tr></thead>
                <tbody>
                <?php foreach ($reports as $r): ?>
                <tr>
                    <td><a href="<?= SITE_URL ?>/profile.php?id=<?= $r['reporter_id'] ?>" style="color:var(--white);"><?= clean($r['reporter_name']) ?></a></td>
                    <td><a href="<?= SITE_URL ?>/profile.php?id=<?= $r['reported_id'] ?>" style="color:var(--gold);"><?= clean($r['reported_name']) ?></a></td>
                    <td style="max-width:200px;"><?= clean($r['reason']) ?></td>
                    <td><span class="status-badge status-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                    <td><?= date('Y/m/d', strtotime($r['created_at'])) ?></td>
                    <td class="actions">
                        <?php if ($r['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="review">
                            <input type="text" name="note" placeholder="وەڵام..." style="width:100px;padding:4px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:4px;color:var(--white);font-size:0.75rem;">
                            <button class="btn btn-gold btn-sm"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="dismiss">
                            <button class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                        </form>
                        <?php else: ?>
                            <?php if ($r['admin_note']): ?><small class="text-gray"><?= clean($r['admin_note']) ?></small><?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
