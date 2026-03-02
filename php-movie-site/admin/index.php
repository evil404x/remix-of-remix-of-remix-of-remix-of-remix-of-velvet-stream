<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$stats = getDashboardStats($pdo);
$recentMovies = $pdo->query("SELECT * FROM movies ORDER BY created_at DESC LIMIT 10")->fetchAll();
$recentRequests = $pdo->query("SELECT mr.*, u.username FROM movie_requests mr LEFT JOIN users u ON mr.user_id = u.id ORDER BY mr.created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پانێڵی ئادمین - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script>window.SITE_URL = '<?= SITE_URL ?>'; window.ADMIN_DIR = '<?= ADMIN_DIR ?>';</script>
</head>
<body class="rtl">
<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
    <main class="admin-main">
        <div class="admin-header">
            <div>
            <h1>👑 داشبۆرد</h1></div>
            <span style="color:var(--gray);">بەخێربێیت، <?= clean($_SESSION['username']) ?></span>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-film"></i></div><div class="stat-value"><?= number_format($stats['total_movies']) ?></div><div class="stat-label">فیلم</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-tv"></i></div><div class="stat-value"><?= number_format($stats['total_series']) ?></div><div class="stat-label">زنجیرە</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-value"><?= number_format($stats['total_users']) ?></div><div class="stat-label">بەکارهێنەر</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-eye"></i></div><div class="stat-value"><?= number_format($stats['total_views']) ?></div><div class="stat-label">کۆی بینەر</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-inbox"></i></div><div class="stat-value"><?= $stats['pending_requests'] ?></div><div class="stat-label">داواکاری چاوەڕوان</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-shield-alt"></i></div><div class="stat-value"><?= $stats['recent_attacks'] ?></div><div class="stat-label">هەڕەشەی ٢٤ کاتژمێر</div></div>
        </div>
        <div class="panel-card">
            <h3><i class="fas fa-clock"></i> دوایین فیلمەکان</h3>
            <table class="admin-table">
                <thead><tr><th>ناو</th><th>جۆر</th><th>IMDb</th><th>بینەر</th><th>بەروار</th><th>کردار</th></tr></thead>
                <tbody>
                <?php foreach ($recentMovies as $m): ?>
                <tr>
                    <td><?= clean($m['title']) ?></td>
                    <td><span class="tag"><?= $m['type']==='movie'?'فیلم':'زنجیرە' ?></span></td>
                    <td class="text-gold"><?= $m['imdb_rate'] ?></td>
                    <td><?= number_format($m['views']) ?></td>
                    <td><?= date('Y/m/d', strtotime($m['created_at'])) ?></td>
                    <td class="actions">
                        <a href="<?= adminUrl('edit-movie.php?id=' . $m['id']) ?>" class="btn btn-glass btn-sm"><i class="fas fa-edit"></i></a>
                        <a href="<?= adminUrl('delete-movie.php?id=' . $m['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('دڵنیایت؟')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-card">
            <h3><i class="fas fa-inbox"></i> داواکاریە نوێیەکان</h3>
            <?php foreach ($recentRequests as $req): ?>
            <div style="padding:14px;border-bottom:1px solid rgba(255,255,255,0.04);">
                <strong><?= clean($req['movie_name']) ?></strong>
                <span class="status-badge status-<?= $req['status'] ?>" style="margin-right:10px;"><?= $req['status'] ?></span><br>
                <small class="text-gray">لەلایەن: <?= clean($req['username'] ?? 'میوان') ?> • <?= date('Y/m/d', strtotime($req['created_at'])) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body></html>
