<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'approve') {
        $pdo->prepare("UPDATE comments SET is_approved = 1 WHERE id = ?")->execute([$id]);
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE comments SET is_approved = 0 WHERE id = ?")->execute([$id]);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
    }
    header('Location: ' . adminUrl('comments.php')); exit;
}

// Get all comments
$comments = $pdo->query("SELECT c.*, u.username, m.title as movie_title 
    FROM comments c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN movies m ON c.movie_id = m.id 
    ORDER BY c.created_at DESC 
    LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بۆچوونەکان - <?= SITE_NAME ?></title>
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
            <h1><i class="fas fa-comments text-gold"></i> بۆچوونەکان</h1>
            <span class="tag" style="font-size:0.85rem;"><?= count($comments) ?> بۆچوون</span>
        </div>

        <div class="panel-card">
            <?php if (empty($comments)): ?>
                <p style="padding:40px;text-align:center;color:var(--gray);">هیچ بۆچوونێک نییە.</p>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>بەکارهێنەر</th>
                        <th>فیلم</th>
                        <th>بۆچوون</th>
                        <th>بار</th>
                        <th>بەروار</th>
                        <th>کردار</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($comments as $c): ?>
                    <tr>
                        <td><i class="fas fa-user-circle text-gold"></i> <?= clean($c['username'] ?? 'نەزانراو') ?></td>
                        <td><?= clean($c['movie_title'] ?? '-') ?></td>
                        <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;"><?= clean(mb_substr($c['content'], 0, 100)) ?></td>
                        <td>
                            <span class="status-badge status-<?= $c['is_approved'] ? 'approved' : 'pending' ?>">
                                <?= $c['is_approved'] ? 'پەسەندکراو' : 'چاوەڕوان' ?>
                            </span>
                        </td>
                        <td style="font-size:0.82rem;color:var(--gray);"><?= date('Y/m/d', strtotime($c['created_at'])) ?></td>
                        <td class="actions">
                            <?php if (!$c['is_approved']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-glass btn-sm" title="پەسەندکردن"><i class="fas fa-check" style="color:var(--green);"></i></button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="btn btn-glass btn-sm" title="ڕەتکردنەوە"><i class="fas fa-ban" style="color:var(--gold);"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('دڵنیایت لە سڕینەوە؟')">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
