<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_season') {
        $movieId = (int)$_POST['movie_id'];
        $seasonNum = (int)$_POST['season_number'];
        $episodeCount = (int)$_POST['episode_count'];
        $seasonTitle = clean($_POST['season_title'] ?? "وەرزی $seasonNum");
        
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO seasons (movie_id, season_number, title) VALUES (?, ?, ?)")
                ->execute([$movieId, $seasonNum, $seasonTitle]);
            $seasonId = $pdo->lastInsertId();
            
            for ($ep = 1; $ep <= $episodeCount; $ep++) {
                $pdo->prepare("INSERT INTO episodes (season_id, episode_number, title) VALUES (?, ?, ?)")
                    ->execute([$seasonId, $ep, "ئەڵقەی $ep"]);
            }
            $pdo->commit();
            $success = "وەرزی $seasonNum بە $episodeCount ئەڵقە زیاد کرا!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'هەڵە: ' . $e->getMessage();
        }
    }
    
    if ($action === 'add_episode') {
        $seasonId = (int)$_POST['season_id'];
        $epNum = (int)$_POST['episode_number'];
        $epTitle = clean($_POST['episode_title'] ?? "ئەڵقەی $epNum");
        $epDuration = clean($_POST['episode_duration'] ?? '');
        
        try {
            $pdo->prepare("INSERT INTO episodes (season_id, episode_number, title, duration) VALUES (?, ?, ?, ?)")
                ->execute([$seasonId, $epNum, $epTitle, $epDuration]);
            $success = "ئەڵقەی $epNum زیاد کرا!";
        } catch (Exception $e) {
            $error = 'هەڵە: ' . $e->getMessage();
        }
    }
    
    if ($action === 'add_ep_link') {
        $movieId = (int)$_POST['movie_id'];
        $season = (int)$_POST['season_number'];
        $episode = (int)$_POST['episode_number'];
        $language = in_array($_POST['language'] ?? '', ['kurdish','arabic','english']) ? $_POST['language'] : 'kurdish';
        $serverName = clean($_POST['server_name'] ?? 'Server 1');
        $videoUrl = clean($_POST['video_url'] ?? '');
        
        if ($videoUrl) {
            $pdo->prepare("INSERT INTO video_links (movie_id, season_number, episode_number, language, server_name, video_url) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$movieId, $season, $episode, $language, $serverName, $videoUrl]);
            $success = 'لینک زیاد کرا!';
        }
    }
    
    if ($action === 'delete_season') {
        $seasonId = (int)$_POST['season_id'];
        $pdo->prepare("DELETE FROM seasons WHERE id = ?")->execute([$seasonId]);
        $success = 'وەرزەکە سڕدرایەوە.';
    }
    
    if ($action === 'delete_episode') {
        $epId = (int)$_POST['episode_id'];
        $pdo->prepare("DELETE FROM episodes WHERE id = ?")->execute([$epId]);
        $success = 'ئەڵقەکە سڕدرایەوە.';
    }
    
    if ($action === 'delete_link') {
        $linkId = (int)$_POST['link_id'];
        $pdo->prepare("DELETE FROM video_links WHERE id = ?")->execute([$linkId]);
        $success = 'لینکەکە سڕدرایەوە.';
    }
    
    if ($action === 'update_episode') {
        $epId = (int)$_POST['episode_id'];
        $epTitle = clean($_POST['episode_title'] ?? '');
        $epDuration = clean($_POST['episode_duration'] ?? '');
        $pdo->prepare("UPDATE episodes SET title = ?, duration = ? WHERE id = ?")->execute([$epTitle, $epDuration, $epId]);
        $success = 'ئەڵقەکە نوێ کرایەوە.';
    }
}

// Get selected series
$movieId = (int)($_GET['movie_id'] ?? $_POST['movie_id'] ?? 0);

// Get all series
$allSeries = $pdo->query("SELECT id, title, slug FROM movies WHERE type = 'series' ORDER BY title")->fetchAll();

// If movie selected, get its data
$movie = null;
$seasons = [];
if ($movieId) {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ? AND type = 'series'");
    $stmt->execute([$movieId]);
    $movie = $stmt->fetch();
    
    if ($movie) {
        $sStmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM episodes WHERE season_id = s.id) as ep_count FROM seasons s WHERE s.movie_id = ? ORDER BY s.season_number");
        $sStmt->execute([$movieId]);
        $seasons = $sStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بەڕێوەبردنی زنجیرەکان - <?= SITE_NAME ?></title>
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
            <h1><i class="fas fa-tv text-gold"></i> بەڕێوەبردنی زنجیرەکان</h1>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <!-- Series Selector -->
        <div class="panel-card glass" style="margin-bottom:20px;">
            <h3><i class="fas fa-list"></i> هەڵبژاردنی زنجیرە</h3>
            <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                    <select name="movie_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- زنجیرەیەک هەڵبژێرە --</option>
                        <?php foreach ($allSeries as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $movieId == $s['id'] ? 'selected' : '' ?>><?= clean($s['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($movie): ?>
        <!-- Series Info -->
        <div class="panel-card glass" style="margin-bottom:20px;">
            <h3><i class="fas fa-film"></i> <?= clean($movie['title']) ?></h3>
            <p style="color:var(--gray);font-size:0.88rem;"><?= count($seasons) ?> وەرز • <?= $movie['release_year'] ?> • <?= $movie['quality'] ?></p>
        </div>

        <!-- Add Season -->
        <div class="panel-card glass" style="margin-bottom:20px;">
            <h3><i class="fas fa-plus-circle"></i> زیادکردنی وەرزی نوێ</h3>
            <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <input type="hidden" name="action" value="add_season">
                <input type="hidden" name="movie_id" value="<?= $movieId ?>">
                <div class="form-group" style="margin:0;">
                    <label>ژمارەی وەرز</label>
                    <input type="number" name="season_number" class="form-control" min="1" value="<?= count($seasons) + 1 ?>" style="width:100px;" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>ناو</label>
                    <input type="text" name="season_title" class="form-control" placeholder="وەرزی ١" style="width:150px;">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>ژمارەی ئەڵقەکان</label>
                    <input type="number" name="episode_count" class="form-control" min="1" value="10" style="width:100px;" required>
                </div>
                <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> زیادکردن</button>
            </form>
        </div>

        <!-- Seasons List -->
        <?php foreach ($seasons as $season): ?>
        <div class="panel-card glass" style="margin-bottom:16px;">
            <div class="flex-between" style="margin-bottom:14px;flex-wrap:wrap;gap:10px;">
                <h3 style="margin:0;"><i class="fas fa-layer-group"></i> <?= clean($season['title'] ?: 'وەرزی '.$season['season_number']) ?> <span class="status-badge status-approved"><?= $season['ep_count'] ?> ئەڵقە</span></h3>
                <form method="POST" style="display:inline;" onsubmit="return confirm('دڵنیایت لە سڕینی ئەم وەرزە و هەموو ئەڵقەکانی؟')">
                    <input type="hidden" name="action" value="delete_season">
                    <input type="hidden" name="season_id" value="<?= $season['id'] ?>">
                    <input type="hidden" name="movie_id" value="<?= $movieId ?>">
                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> سڕینی وەرز</button>
                </form>
            </div>

            <!-- Episodes -->
            <?php
            $eps = $pdo->prepare("SELECT * FROM episodes WHERE season_id = ? ORDER BY episode_number");
            $eps->execute([$season['id']]);
            $episodes = $eps->fetchAll();
            ?>
            
            <div class="episodes-admin-grid">
                <?php foreach ($episodes as $ep): ?>
                <div class="episode-admin-card">
                    <div class="flex-between" style="margin-bottom:8px;">
                        <span class="text-gold" style="font-weight:600;font-size:0.85rem;"><i class="fas fa-play-circle"></i> ئەڵقەی <?= $ep['episode_number'] ?></span>
                        <div style="display:flex;gap:4px;">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('سڕینەوە؟')">
                                <input type="hidden" name="action" value="delete_episode">
                                <input type="hidden" name="episode_id" value="<?= $ep['id'] ?>">
                                <input type="hidden" name="movie_id" value="<?= $movieId ?>">
                                <button class="btn btn-danger btn-sm" style="padding:3px 8px;font-size:0.72rem;"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    </div>
                    <form method="POST" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="update_episode">
                        <input type="hidden" name="episode_id" value="<?= $ep['id'] ?>">
                        <input type="hidden" name="movie_id" value="<?= $movieId ?>">
                        <input type="text" name="episode_title" class="form-control" value="<?= clean($ep['title']) ?>" style="flex:1;min-width:100px;padding:6px 10px;font-size:0.8rem;">
                        <input type="text" name="episode_duration" class="form-control" value="<?= clean($ep['duration'] ?? '') ?>" placeholder="45 min" style="width:80px;padding:6px 10px;font-size:0.8rem;">
                        <button class="btn btn-glass btn-sm" style="padding:4px 10px;font-size:0.76rem;"><i class="fas fa-save"></i></button>
                    </form>
                    
                    <!-- Episode Links -->
                    <?php
                    $links = $pdo->prepare("SELECT * FROM video_links WHERE movie_id = ? AND season_number = ? AND episode_number = ? ORDER BY language, sort_order");
                    $links->execute([$movieId, $season['season_number'], $ep['episode_number']]);
                    $epLinks = $links->fetchAll();
                    ?>
                    <?php if (!empty($epLinks)): ?>
                    <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.04);">
                        <?php foreach ($epLinks as $link): ?>
                        <div style="display:flex;gap:6px;align-items:center;margin-bottom:4px;">
                            <span style="font-size:0.72rem;color:var(--gold);min-width:55px;"><?= $link['language'] ?></span>
                            <span style="font-size:0.72rem;color:var(--gray);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($link['server_name']) ?> - <?= clean(substr($link['video_url'],0,40)) ?>...</span>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete_link">
                                <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                <input type="hidden" name="movie_id" value="<?= $movieId ?>">
                                <button class="btn btn-danger btn-sm" style="padding:2px 6px;font-size:0.66rem;"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add Link -->
                    <details style="margin-top:8px;">
                        <summary style="font-size:0.78rem;color:var(--gold);cursor:pointer;">+ زیادکردنی لینک</summary>
                        <form method="POST" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                            <input type="hidden" name="action" value="add_ep_link">
                            <input type="hidden" name="movie_id" value="<?= $movieId ?>">
                            <input type="hidden" name="season_number" value="<?= $season['season_number'] ?>">
                            <input type="hidden" name="episode_number" value="<?= $ep['episode_number'] ?>">
                            <select name="language" class="form-control" style="width:90px;padding:5px;font-size:0.78rem;">
                                <option value="kurdish">کوردی</option>
                                <option value="arabic">عەرەبی</option>
                                <option value="english">ئینگلیزی</option>
                            </select>
                            <input type="text" name="server_name" class="form-control" placeholder="Server 1" style="width:80px;padding:5px;font-size:0.78rem;">
                            <input type="url" name="video_url" class="form-control" placeholder="https://..." style="flex:1;min-width:120px;padding:5px;font-size:0.78rem;" required>
                            <button class="btn btn-gold btn-sm" style="padding:4px 10px;font-size:0.74rem;"><i class="fas fa-plus"></i></button>
                        </form>
                    </details>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Add Single Episode -->
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.04);">
                <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="action" value="add_episode">
                    <input type="hidden" name="season_id" value="<?= $season['id'] ?>">
                    <input type="hidden" name="movie_id" value="<?= $movieId ?>">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.78rem;">ژمارەی ئەڵقە</label>
                        <input type="number" name="episode_number" class="form-control" min="1" value="<?= $season['ep_count'] + 1 ?>" style="width:70px;padding:6px;" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.78rem;">ناو</label>
                        <input type="text" name="episode_title" class="form-control" placeholder="ئەڵقەی نوێ" style="width:130px;padding:6px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.78rem;">ماوە</label>
                        <input type="text" name="episode_duration" class="form-control" placeholder="45 min" style="width:80px;padding:6px;">
                    </div>
                    <button type="submit" class="btn btn-glass btn-sm" style="padding:6px 12px;"><i class="fas fa-plus"></i> ئەڵقە</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($seasons) && $movie): ?>
        <div class="panel-card glass" style="text-align:center;padding:50px;">
            <i class="fas fa-tv" style="font-size:2.5rem;color:var(--gray-dark);margin-bottom:16px;"></i>
            <p class="text-gray">هیچ وەرزێک نییە. لە سەرەوە وەرزێکی نوێ زیاد بکە.</p>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="panel-card glass" style="text-align:center;padding:60px;">
            <i class="fas fa-tv" style="font-size:3rem;color:var(--gray-dark);margin-bottom:20px;"></i>
            <p class="text-gray" style="font-size:1.05rem;">تکایە زنجیرەیەک هەڵبژێرە بۆ بەڕێوەبردنی وەرز و ئەڵقەکانی.</p>
            <?php if (empty($allSeries)): ?>
            <p style="margin-top:12px;"><a href="<?= SITE_URL ?>/admin/add-movie.php" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> زیادکردنی زنجیرەی نوێ</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>