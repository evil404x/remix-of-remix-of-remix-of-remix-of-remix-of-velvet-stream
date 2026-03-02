<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';
$error = '';

// Get movie
$movieId = (int)($_GET['id'] ?? 0);
if (!$movieId) { header('Location: ' . adminUrl('movies.php')); exit; }

$movie = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$movie->execute([$movieId]);
$movie = $movie->fetch();
if (!$movie) { header('Location: ' . adminUrl('movies.php')); exit; }

// Get movie genres
$movieGenreIds = [];
$mgStmt = $pdo->prepare("SELECT genre_id FROM movie_genres WHERE movie_id = ?");
$mgStmt->execute([$movieId]);
$movieGenreIds = $mgStmt->fetchAll(PDO::FETCH_COLUMN);

// Get video links
$videoLinks = [];
$vlStmt = $pdo->prepare("SELECT * FROM video_links WHERE movie_id = ? ORDER BY language, sort_order");
$vlStmt->execute([$movieId]);
foreach ($vlStmt->fetchAll() as $vl) {
    $videoLinks[$vl['language']][] = $vl;
}

// Get seasons
$seasons = [];
if ($movie['type'] === 'series') {
    $sStmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM episodes WHERE season_id = s.id) as ep_count FROM seasons s WHERE s.movie_id = ? ORDER BY s.season_number");
    $sStmt->execute([$movieId]);
    $seasons = $sStmt->fetchAll();
}

// Handle season delete
if (isset($_GET['delete_season'])) {
    $pdo->prepare("DELETE FROM seasons WHERE id = ? AND movie_id = ?")->execute([(int)$_GET['delete_season'], $movieId]);
    header('Location: ' . adminUrl('edit-movie.php?id=' . $movieId)); exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'CSRF Error';
    } else {
        $title = clean($_POST['title'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['movie', 'series']) ? $_POST['type'] : 'movie';
        $release_year = (int)($_POST['release_year'] ?? 0);
        $imdb_rate = (float)($_POST['imdb_rate'] ?? 0);
        $quality = in_array($_POST['quality'] ?? '', ['CAM','HD','FHD','4K']) ? $_POST['quality'] : 'HD';
        $duration = clean($_POST['duration'] ?? '');
        $country = clean($_POST['country'] ?? '');
        $director = clean($_POST['director'] ?? '');
        $actors = clean($_POST['actors'] ?? '');
        $trailer_url = clean($_POST['trailer_url'] ?? '');
        $meta_title = clean($_POST['meta_title'] ?? '');
        $meta_description = clean($_POST['meta_description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : $movie['status'];
        $genres = $_POST['genres'] ?? [];

        // Poster upload
        $poster = $movie['poster'];
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === 0) {
            $ext = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
            $poster = $movie['slug'] . '-poster.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/posters/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            move_uploaded_file($_FILES['poster']['tmp_name'], $uploadDir . $poster);
        }

        // Backdrop upload
        $backdrop = $movie['backdrop'];
        if (isset($_FILES['backdrop']) && $_FILES['backdrop']['error'] === 0) {
            $ext = pathinfo($_FILES['backdrop']['name'], PATHINFO_EXTENSION);
            $backdrop = $movie['slug'] . '-backdrop.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/backdrops/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            move_uploaded_file($_FILES['backdrop']['tmp_name'], $uploadDir . $backdrop);
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE movies SET title=?, description=?, poster=?, backdrop=?, trailer_url=?, release_year=?, imdb_rate=?, quality=?, duration=?, country=?, director=?, actors=?, type=?, status=?, meta_title=?, meta_description=? WHERE id=?");
            $stmt->execute([$title, $description, $poster, $backdrop, $trailer_url, $release_year, $imdb_rate, $quality, $duration, $country, $director, $actors, $type, $status, $meta_title, $meta_description, $movieId]);

            // Update genres
            $pdo->prepare("DELETE FROM movie_genres WHERE movie_id = ?")->execute([$movieId]);
            foreach ($genres as $gid) {
                $pdo->prepare("INSERT INTO movie_genres (movie_id, genre_id) VALUES (?, ?)")->execute([$movieId, (int)$gid]);
            }

            // Update video links
            $pdo->prepare("DELETE FROM video_links WHERE movie_id = ?")->execute([$movieId]);
            $languages = ['kurdish', 'arabic', 'english'];
            foreach ($languages as $lang) {
                $urls = $_POST["video_{$lang}"] ?? [];
                $servers = $_POST["server_{$lang}"] ?? [];
                foreach ($urls as $i => $url) {
                    $url = clean($url);
                    if (!empty($url)) {
                        $serverName = clean($servers[$i] ?? "Server " . ($i + 1));
                        $pdo->prepare("INSERT INTO video_links (movie_id, language, server_name, video_url, sort_order) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$movieId, $lang, $serverName, $url, $i]);
                    }
                }
            }

            // Add new season if requested
            $newSeasonNum = (int)($_POST['new_season_number'] ?? 0);
            $newSeasonEps = (int)($_POST['new_season_episodes'] ?? 0);
            if ($newSeasonNum > 0 && $newSeasonEps > 0 && $type === 'series') {
                $sInsert = $pdo->prepare("INSERT IGNORE INTO seasons (movie_id, season_number, title) VALUES (?, ?, ?)");
                $sInsert->execute([$movieId, $newSeasonNum, "وەرزی $newSeasonNum"]);
                $newSeasonId = $pdo->lastInsertId();
                if ($newSeasonId) {
                    $epInsert = $pdo->prepare("INSERT INTO episodes (season_id, episode_number, title) VALUES (?, ?, ?)");
                    for ($ep = 1; $ep <= $newSeasonEps; $ep++) {
                        $epInsert->execute([$newSeasonId, $ep, "ئەڵقەی $ep"]);
                    }
                }
            }

            $pdo->commit();
            $success = "فیلمی «{$title}» بە سەرکەوتوویی نوێ کرایەوە!";

            // Refresh data
            $movie = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
            $movie->execute([$movieId]);
            $movie = $movie->fetch();

            $mgStmt = $pdo->prepare("SELECT genre_id FROM movie_genres WHERE movie_id = ?");
            $mgStmt->execute([$movieId]);
            $movieGenreIds = $mgStmt->fetchAll(PDO::FETCH_COLUMN);

            $videoLinks = [];
            $vlStmt = $pdo->prepare("SELECT * FROM video_links WHERE movie_id = ? ORDER BY language, sort_order");
            $vlStmt->execute([$movieId]);
            foreach ($vlStmt->fetchAll() as $vl) {
                $videoLinks[$vl['language']][] = $vl;
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'هەڵە: ' . $e->getMessage();
        }
    }
}

$allGenres = getGenres($pdo);
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دەستکاری: <?= clean($movie['title']) ?> - <?= SITE_NAME ?></title>
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
            <h1><i class="fas fa-edit text-gold"></i> دەستکاری: <?= clean($movie['title']) ?></h1>
            <a href="<?= SITE_URL ?>/admin/movies.php" class="btn btn-glass btn-sm"><i class="fas fa-arrow-right"></i> گەڕانەوە</a>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="admin-form glass" style="padding: 30px;">
            <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

            <!-- Basic Info -->
            <h3 class="text-gold" style="margin-bottom: 20px;"><i class="fas fa-info-circle"></i> زانیاری سەرەکی</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>ناوی فیلم *</label>
                    <input type="text" name="title" class="form-control" required maxlength="255" value="<?= clean($movie['title']) ?>">
                </div>
                <div class="form-group">
                    <label>جۆر *</label>
                    <select name="type" class="form-control" id="typeSelect">
                        <option value="movie" <?= $movie['type']==='movie'?'selected':'' ?>>فیلم</option>
                        <option value="series" <?= $movie['type']==='series'?'selected':'' ?>>زنجیرە</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>باسکردن</label>
                <textarea name="description" class="form-control" rows="4" maxlength="5000"><?= clean($movie['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>ساڵی دەرچوون</label>
                    <input type="number" name="release_year" class="form-control" min="1900" max="2030" value="<?= $movie['release_year'] ?>">
                </div>
                <div class="form-group">
                    <label>IMDb Rate</label>
                    <input type="number" name="imdb_rate" class="form-control" min="0" max="10" step="0.1" value="<?= $movie['imdb_rate'] ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>کوالیتی</label>
                    <select name="quality" class="form-control">
                        <?php foreach (['CAM','HD','FHD','4K'] as $q): ?>
                            <option value="<?= $q ?>" <?= $movie['quality']===$q?'selected':'' ?>><?= $q ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ماوە</label>
                    <input type="text" name="duration" class="form-control" value="<?= clean($movie['duration'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>وڵات</label>
                    <input type="text" name="country" class="form-control" value="<?= clean($movie['country'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>دەرهێنەر</label>
                    <input type="text" name="director" class="form-control" value="<?= clean($movie['director'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>ئاکتەرەکان (گەڕان لە داتابەیس)</label>
                <div style="position:relative;">
                    <input type="text" id="actor-search-input" class="form-control" placeholder="ناوی ئەکتەر بنووسە بۆ گەڕان..." autocomplete="off">
                    <div id="actor-search-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:50;max-height:250px;overflow-y:auto;background:rgba(14,14,14,0.95);border:1px solid rgba(245,197,24,0.15);border-radius:8px;margin-top:4px;"></div>
                </div>
                <div id="selected-actors" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
                <input type="hidden" name="actors" id="actors-hidden" value="<?= clean($movie['actors'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>بار (Status)</label>
                    <select name="status" class="form-control">
                        <option value="published" <?= $movie['status']==='published'?'selected':'' ?>>بڵاوکراوە</option>
                        <option value="draft" <?= $movie['status']==='draft'?'selected':'' ?>>ڕەشنووس</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>ژانرەکان</label>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($allGenres as $g): ?>
                        <label style="display:flex; align-items:center; gap:5px; cursor:pointer; color:var(--gray-light);">
                            <input type="checkbox" name="genres[]" value="<?= $g['id'] ?>" <?= in_array($g['id'], $movieGenreIds)?'checked':'' ?>> <?= clean($g['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Media -->
            <h3 class="text-gold" style="margin: 30px 0 20px;"><i class="fas fa-image"></i> وێنەکان</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>پۆستەر <?php if($movie['poster']): ?><small style="color:var(--gray);">(ئێستا: <?= $movie['poster'] ?>)</small><?php endif; ?></label>
                    <input type="file" name="poster" class="form-control" accept="image/*">
                </div>
                <div class="form-group">
                    <label>وێنەی پاشبنەما <?php if($movie['backdrop']): ?><small style="color:var(--gray);">(ئێستا: <?= $movie['backdrop'] ?>)</small><?php endif; ?></label>
                    <input type="file" name="backdrop" class="form-control" accept="image/*">
                </div>
            </div>

            <div class="form-group">
                <label>لینکی تریلەر</label>
                <input type="url" name="trailer_url" class="form-control" value="<?= clean($movie['trailer_url'] ?? '') ?>">
            </div>

            <!-- Video Links -->
            <h3 class="text-gold" style="margin: 30px 0 20px;"><i class="fas fa-play-circle"></i> لینکەکانی ڤیدیۆ (٣ زمان)</h3>

            <?php foreach (['kurdish' => '🇹🇯 کوردی', 'arabic' => '🇸🇦 عەرەبی', 'english' => '🇬🇧 ئینگلیزی'] as $code => $label): ?>
            <div class="glass" style="padding: 20px; margin-bottom: 15px;">
                <h4 style="margin-bottom: 15px;"><?= $label ?></h4>
                <div id="links-<?= $code ?>">
                    <?php if (!empty($videoLinks[$code])): ?>
                        <?php foreach ($videoLinks[$code] as $vl): ?>
                        <div class="form-row" style="margin-bottom:10px;">
                            <input type="text" name="server_<?= $code ?>[]" class="form-control" value="<?= clean($vl['server_name']) ?>">
                            <input type="url" name="video_<?= $code ?>[]" class="form-control" value="<?= clean($vl['video_url']) ?>">
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="form-row" style="margin-bottom:10px;">
                            <input type="text" name="server_<?= $code ?>[]" class="form-control" placeholder="Server 1" value="Server 1">
                            <input type="url" name="video_<?= $code ?>[]" class="form-control" placeholder="https://embed.example.com/...">
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-glass btn-sm" onclick="addLinkField('<?= $code ?>')">
                    <i class="fas fa-plus"></i> زیادکردنی سێرڤەر
                </button>
            </div>
            <?php endforeach; ?>

            <!-- SEO -->
            <h3 class="text-gold" style="margin: 30px 0 20px;"><i class="fas fa-search"></i> SEO</h3>
            <div class="form-group">
                <label>Meta Title</label>
                <input type="text" name="meta_title" class="form-control" value="<?= clean($movie['meta_title'] ?? '') ?>" maxlength="255">
            </div>
            <div class="form-group">
                <label>Meta Description</label>
                <textarea name="meta_description" class="form-control" rows="2" maxlength="500"><?= clean($movie['meta_description'] ?? '') ?></textarea>
            </div>

            <!-- Series Management -->
            <?php if ($movie['type'] === 'series'): ?>
            <h3 class="text-gold" style="margin: 30px 0 20px;"><i class="fas fa-layer-group"></i> بەڕێوەبردنی وەرز و ئەڵقەکان</h3>
            <div class="glass" style="padding: 20px;">
                <?php if (!empty($seasons)): ?>
                <table class="admin-table" style="margin-bottom:15px;">
                    <thead>
                        <tr><th>وەرز</th><th>ژمارەی ئەڵقەکان</th><th>کردار</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($seasons as $s): ?>
                        <tr>
                            <td>وەرزی <?= $s['season_number'] ?></td>
                            <td><?= $s['ep_count'] ?> ئەڵقە</td>
                            <td>
                                <a href="?id=<?= $movieId ?>&delete_season=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('دڵنیایت لە سڕینی ئەم وەرزە؟')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="color:var(--gray);margin-bottom:15px;">هیچ وەرزێک نییە. لە خوارەوە زیاد بکە.</p>
                <?php endif; ?>
                
                <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;">
                        <label>ژمارەی وەرزی نوێ</label>
                        <input type="number" name="new_season_number" class="form-control" min="1" placeholder="1" style="width:100px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>ژمارەی ئەڵقەکان</label>
                        <input type="number" name="new_season_episodes" class="form-control" min="1" placeholder="10" style="width:100px;">
                    </div>
                </div>
                <small style="color:var(--gray);margin-top:8px;display:block;">ئەگەر ژمارە بنوسیت، وەرزێکی نوێ زیاد دەکرێت کاتێک پاشەکەوت بکەیت.</small>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-gold btn-lg" style="margin-top: 20px;">
                <i class="fas fa-save"></i> نوێکردنەوە
            </button>
        </form>
    </main>
</div>

<script>
document.getElementById('typeSelect').addEventListener('change', function() {
    document.getElementById('seriesOptions')?.style.display = this.value === 'series' ? 'block' : 'none';
});

function addLinkField(lang) {
    const container = document.getElementById(`links-${lang}`);
    const count = container.children.length + 1;
    const row = document.createElement('div');
    row.className = 'form-row';
    row.style.marginBottom = '10px';
    row.innerHTML = `
        <input type="text" name="server_${lang}[]" class="form-control" placeholder="Server ${count}" value="Server ${count}">
        <input type="url" name="video_${lang}[]" class="form-control" placeholder="https://embed.example.com/...">
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()" style="align-self:center;"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(row);
}

// ============ ACTOR MULTI-SELECT SEARCH ============
let selectedActors = [];
const actorSearchInput = document.getElementById('actor-search-input');
const actorDropdown = document.getElementById('actor-search-dropdown');
const selectedActorsDiv = document.getElementById('selected-actors');
const actorsHidden = document.getElementById('actors-hidden');
let actorSearchTimeout;

// Pre-populate from existing actors
const existingActors = actorsHidden.value;
if (existingActors) {
    existingActors.split(',').forEach((name, i) => {
        name = name.trim();
        if (name) selectedActors.push({id: -(i+1), name: name});
    });
    renderSelectedActors();
}

function updateActorsHidden() {
    actorsHidden.value = selectedActors.map(a => a.name).join(', ');
}

function renderSelectedActors() {
    selectedActorsDiv.innerHTML = selectedActors.map((a, i) => `
        <span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;background:rgba(245,197,24,0.1);border:1px solid rgba(245,197,24,0.2);border-radius:20px;font-size:0.82rem;color:var(--gold);">
            ${a.name}
            <button type="button" onclick="removeActor(${i})" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:0.9rem;padding:0;">&times;</button>
        </span>
    `).join('');
    updateActorsHidden();
}

function removeActor(index) {
    selectedActors.splice(index, 1);
    renderSelectedActors();
}

function addActor(actor) {
    if (selectedActors.some(a => a.name === actor.name)) return;
    selectedActors.push(actor);
    renderSelectedActors();
    actorSearchInput.value = '';
    actorDropdown.style.display = 'none';
}

if (actorSearchInput) {
    actorSearchInput.addEventListener('input', function() {
        clearTimeout(actorSearchTimeout);
        const q = this.value.trim();
        if (q.length < 1) { actorDropdown.style.display = 'none'; return; }
        actorSearchTimeout = setTimeout(() => {
            fetch(`${window.SITE_URL}/api/actor-search.php?q=${encodeURIComponent(q)}`, {credentials:'same-origin'})
                .then(r => r.json())
                .then(d => {
                    if (!d.actors || d.actors.length === 0) {
                        actorDropdown.innerHTML = '<div style="padding:12px;color:var(--gray);font-size:0.82rem;text-align:center;">هیچ نەدۆزرایەوە</div>';
                    } else {
                        actorDropdown.innerHTML = d.actors.map(a => `
                            <div onclick="addActor({id:${a.id},name:'${a.name.replace(/'/g,"\\'")}'})" style="display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;transition:all 0.2s;border-bottom:1px solid rgba(255,255,255,0.03);" onmouseover="this.style.background='rgba(245,197,24,0.06)'" onmouseout="this.style.background=''">
                                ${a.profile_image ? `<img src="${window.SITE_URL}/uploads/actors/${a.profile_image}" style="width:32px;height:40px;border-radius:6px;object-fit:cover;">` : '<i class="fas fa-user" style="font-size:1.2rem;color:var(--gray);width:32px;text-align:center;"></i>'}
                                <div><div style="font-size:0.85rem;font-weight:600;">${a.name}</div></div>
                            </div>
                        `).join('');
                    }
                    actorDropdown.style.display = 'block';
                });
        }, 250);
    });
    document.addEventListener('click', e => { if (!e.target.closest('#actor-search-input') && !e.target.closest('#actor-search-dropdown')) actorDropdown.style.display = 'none'; });
}
</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>