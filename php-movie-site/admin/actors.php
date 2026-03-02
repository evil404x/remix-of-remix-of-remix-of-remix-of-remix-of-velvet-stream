<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Auto-create actors tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS actors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tmdb_id INT DEFAULT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        name_ku VARCHAR(255),
        slug VARCHAR(255) NOT NULL,
        biography TEXT,
        biography_ku TEXT,
        birthday DATE DEFAULT NULL,
        deathday DATE DEFAULT NULL,
        place_of_birth VARCHAR(255),
        profile_image VARCHAR(255),
        known_for VARCHAR(100) DEFAULT 'Acting',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_slug (slug)
    ) ENGINE=InnoDB");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS movie_actors (
        movie_id INT NOT NULL,
        actor_id INT NOT NULL,
        role_name VARCHAR(255) DEFAULT '',
        sort_order SMALLINT DEFAULT 0,
        PRIMARY KEY (movie_id, actor_id),
        FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
        FOREIGN KEY (actor_id) REFERENCES actors(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM actors WHERE id = ?")->execute([$id]);
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Get all actors
$actors = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM movie_actors ma WHERE ma.actor_id = a.id) as movie_count FROM actors a ORDER BY a.name ASC")->fetchAll();

// Get TMDB key status
$tmdbKey = '';
try { $tmdbKey = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tmdb_api_key'")->fetchColumn() ?: ''; } catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بەڕێوەبردنی ئەکتەران - <?= SITE_NAME ?></title>
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
            <h1><i class="fas fa-user-tie text-gold"></i> بەڕێوەبردنی ئەکتەران</h1>
        </div>

        <?php if (!$tmdbKey): ?>
        <div class="alert alert-error" style="margin-bottom:20px;">
            <i class="fas fa-exclamation-triangle"></i> TMDB API Key تەنظیم نەکراوە. 
            <a href="<?= adminUrl('settings.php') ?>" style="color:var(--gold);text-decoration:underline;">لێرە دایبنێ</a>
        </div>
        <?php endif; ?>

        <!-- Add Actor via TMDB -->
        <div class="panel-card glass" style="margin-bottom:20px;">
            <h3><i class="fas fa-search"></i> گەڕان لە TMDB و زیادکردنی ئەکتەر</h3>
            <div style="display:flex;gap:10px;margin-top:14px;">
                <input type="text" id="tmdb-actor-search" class="form-control" placeholder="ناوی ئەکتەر بنووسە (ئینگلیزی)..." style="flex:1;font-size:16px !important;">
                <button class="btn btn-gold btn-sm" onclick="searchTMDBActor()"><i class="fas fa-search"></i> گەڕان</button>
            </div>
            <div id="tmdb-search-results" style="margin-top:14px;"></div>
        </div>

        <!-- Actors List -->
        <div class="panel-card">
            <h3 style="margin-bottom:14px;"><i class="fas fa-users"></i> هەموو ئەکتەرەکان (<?= count($actors) ?>)</h3>
            <?php if (empty($actors)): ?>
                <p class="text-gray text-center" style="padding:30px;">هیچ ئەکتەرێک زیاد نەکراوە</p>
            <?php else: ?>
            <table class="admin-table">
                <thead><tr><th>وێنە</th><th>ناو</th><th>ناوی کوردی</th><th>TMDB</th><th>فیلمەکان</th><th>کردار</th></tr></thead>
                <tbody>
                <?php foreach ($actors as $a): ?>
                <tr>
                    <td>
                        <?php if ($a['profile_image']): ?>
                            <img src="<?= SITE_URL ?>/uploads/actors/<?= $a['profile_image'] ?>" style="width:40px;height:55px;object-fit:cover;border-radius:6px;">
                        <?php else: ?>
                            <i class="fas fa-user" style="font-size:1.5rem;color:var(--gray);"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= clean($a['name']) ?></td>
                    <td><?= clean($a['name_ku'] ?: '-') ?></td>
                    <td><?= $a['tmdb_id'] ?: '-' ?></td>
                    <td><span class="text-gold"><?= $a['movie_count'] ?></span></td>
                    <td class="actions">
                        <a href="<?= SITE_URL ?>/actor.php?name=<?= urlencode($a['name']) ?>" class="btn btn-glass btn-sm" target="_blank"><i class="fas fa-eye"></i></a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('دڵنیایت لە سڕینەوە؟')">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
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
<script>
function searchTMDBActor() {
    const q = document.getElementById('tmdb-actor-search').value.trim();
    if (q.length < 2) return;
    const results = document.getElementById('tmdb-search-results');
    results.innerHTML = '<p class="text-gray"><i class="fas fa-spinner fa-spin"></i> گەڕان...</p>';
    
    fetch(`${window.SITE_URL}/api/tmdb.php?action=search_actor&q=${encodeURIComponent(q)}`, {credentials:'same-origin'})
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.results.length) {
                results.innerHTML = '<p class="text-gray">هیچ نەدۆزرایەوە</p>';
                return;
            }
            results.innerHTML = d.results.map(a => `
                <div style="display:flex;align-items:center;gap:12px;padding:10px;background:rgba(255,255,255,0.03);border-radius:8px;margin-bottom:8px;">
                    ${a.profile_path ? `<img src="${a.profile_path}" style="width:45px;height:60px;object-fit:cover;border-radius:6px;">` : '<i class="fas fa-user" style="font-size:2rem;color:var(--gray);width:45px;text-align:center;"></i>'}
                    <div style="flex:1;">
                        <strong>${a.name}</strong>
                        <div class="text-gray" style="font-size:0.8rem;">${a.known_for_department}</div>
                    </div>
                    <button class="btn btn-gold btn-xs" onclick="fetchAndSaveActor(${a.tmdb_id}, this)"><i class="fas fa-plus"></i> زیادکردن</button>
                </div>
            `).join('');
        });
}

function fetchAndSaveActor(tmdbId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch(`${window.SITE_URL}/api/tmdb.php?action=get_actor&tmdb_id=${tmdbId}`, {credentials:'same-origin'})
        .then(r => r.json())
        .then(d => {
            if (!d.success) { btn.innerHTML = '<i class="fas fa-times"></i> هەڵە'; return; }
            const actor = d.actor;
            
            // Save actor
            fetch(`${window.SITE_URL}/api/tmdb.php`, {
                method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
                body: JSON.stringify({
                    action: 'save_actor',
                    tmdb_id: actor.tmdb_id,
                    name: actor.name,
                    name_ku: actor.name,
                    biography: actor.biography,
                    biography_ku: actor.biography_ku,
                    birthday: actor.birthday,
                    deathday: actor.deathday,
                    place_of_birth: actor.place_of_birth,
                    profile_url: actor.profile_path,
                    known_for: actor.known_for_department
                })
            }).then(r => r.json()).then(s => {
                if (s.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> زیاد کرا';
                    btn.style.background = 'rgba(74,222,128,0.15)';
                    btn.style.color = '#4ade80';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    btn.innerHTML = '<i class="fas fa-times"></i> ' + (s.message || 'هەڵە');
                }
            });
        });
}
</script>
</body>
</html>
