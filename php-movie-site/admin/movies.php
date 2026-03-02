<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Handle delete FIRST before fetching movies
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    // Delete related records first (with error handling for tables that may not exist)
    $relatedTables = [
        'video_links' => 'movie_id',
        'movie_genres' => 'movie_id', 
        'favorites' => 'movie_id',
        'watch_history' => 'movie_id',
        'comments' => 'movie_id',
        'reports' => 'movie_id',
        'movie_actors' => 'movie_id',
    ];
    foreach ($relatedTables as $table => $col) {
        try {
            $pdo->prepare("DELETE FROM $table WHERE $col = ?")->execute([$delId]);
        } catch (Exception $e) {}
    }
    // Delete the movie itself
    try {
        $pdo->prepare("DELETE FROM movies WHERE id = ?")->execute([$delId]);
    } catch (Exception $e) {}
    header('Location: ' . adminUrl('movies.php'));
    exit;
}

$movies = $pdo->query("SELECT * FROM movies ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>فیلمەکان - <?= SITE_NAME ?></title><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script>window.SITE_URL='<?= SITE_URL ?>';</script></head><body class="rtl">
<div class="admin-layout"><?php include __DIR__.'/sidebar.php'; ?>
<button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
<main class="admin-main">
<div class="admin-header"><h1><i class="fas fa-film"></i> فیلمەکان</h1><a href="add-movie.php" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> زیادکردن</a></div>
<div class="panel-card"><table class="admin-table"><thead><tr><th>ناو</th><th>جۆر</th><th>IMDb</th><th>کوالیتی</th><th>بینەر</th><th>بار</th><th>کردار</th></tr></thead><tbody>
<?php foreach($movies as $m): ?>
<tr><td><?= clean($m['title']) ?></td><td><span class="tag"><?= $m['type']==='movie'?'فیلم':'زنجیرە' ?></span></td><td class="text-gold"><?= $m['imdb_rate'] ?></td><td><?= $m['quality'] ?></td><td><?= number_format($m['views']) ?></td><td><span class="status-badge status-<?= $m['status']==='published'?'approved':'pending' ?>"><?= $m['status'] ?></span></td>
<td class="actions"><a href="edit-movie.php?id=<?= $m['id'] ?>" class="btn btn-glass btn-sm"><i class="fas fa-edit"></i></a><a href="?delete=<?= $m['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('دڵنیایت لە سڕینەوەی ئەم فیلمە؟')"><i class="fas fa-trash"></i></a></td></tr>
<?php endforeach; ?>
</tbody></table></div></main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script></body></html>
