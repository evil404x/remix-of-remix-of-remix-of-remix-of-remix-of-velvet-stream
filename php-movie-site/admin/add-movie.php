<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'CSRF Error';
    } else {
        $title = clean($_POST['title'] ?? '');
        $slug = createSlug($title);
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
        $genres = $_POST['genres'] ?? [];

        // Handle poster upload
        $poster = '';
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === 0) {
            $ext = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
            $poster = $slug . '-poster.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/posters/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            move_uploaded_file($_FILES['poster']['tmp_name'], $uploadDir . $poster);
        }

        // Handle backdrop upload
        $backdrop = '';
        if (isset($_FILES['backdrop']) && $_FILES['backdrop']['error'] === 0) {
            $ext = pathinfo($_FILES['backdrop']['name'], PATHINFO_EXTENSION);
            $backdrop = $slug . '-backdrop.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/backdrops/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            move_uploaded_file($_FILES['backdrop']['tmp_name'], $uploadDir . $backdrop);
        }

        // Ensure unique slug
        $checkSlug = $pdo->prepare("SELECT id FROM movies WHERE slug = ?");
        $checkSlug->execute([$slug]);
        if ($checkSlug->fetch()) {
            $slug .= '-' . time();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO movies (title, slug, description, poster, backdrop, trailer_url, release_year, imdb_rate, quality, duration, country, director, actors, type, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $description, $poster, $backdrop, $trailer_url, $release_year, $imdb_rate, $quality, $duration, $country, $director, $actors, $type, $meta_title, $meta_description]);
            $movieId = $pdo->lastInsertId();

            // Genres
            foreach ($genres as $gid) {
                $pdo->prepare("INSERT INTO movie_genres (movie_id, genre_id) VALUES (?, ?)")->execute([$movieId, (int)$gid]);
            }

            // Video links (3 languages)
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

            // Series: seasons & episodes
            if ($type === 'series') {
                $seasonCount = (int)($_POST['season_count'] ?? 0);
                for ($s = 1; $s <= $seasonCount; $s++) {
                    $pdo->prepare("INSERT INTO seasons (movie_id, season_number) VALUES (?, ?)")->execute([$movieId, $s]);
                    $seasonId = $pdo->lastInsertId();
                    
                    $epCount = (int)($_POST["episodes_s{$s}"] ?? 0);
                    for ($e = 1; $e <= $epCount; $e++) {
                        $pdo->prepare("INSERT INTO episodes (season_id, episode_number, title) VALUES (?, ?, ?)")
                            ->execute([$seasonId, $e, "ئەڵقەی $e"]);
                    }
                }
            }

            $pdo->commit();
            $success = "فیلمی «{$title}» بە سەرکەوتوویی زیاد کرا!";

            // Notify users who requested this movie
            $reqStmt = $pdo->prepare("SELECT user_id FROM movie_requests WHERE movie_name LIKE ? AND user_id IS NOT NULL AND status = 'pending'");
            $reqStmt->execute(['%' . $title . '%']);
            foreach ($reqStmt->fetchAll() as $req) {
                sendNotification($pdo, $req['user_id'], 'فیلمەکەت دانرا! 🎬', "فیلمی «{$title}» لە سایتەکەوە بەردەستە.", SITE_URL . "/movie.php?slug={$slug}");
            }
            $pdo->prepare("UPDATE movie_requests SET status = 'completed' WHERE movie_name LIKE ? AND status = 'pending'")->execute(['%' . $title . '%']);

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
    <title>زیادکردنی فیلم - <?= SITE_NAME ?></title>
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
            <h1><i class="fas fa-plus-circle text-gold"></i> زیادکردنی فیلم / زنجیرە</h1>
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
                    <input type="text" name="title" class="form-control" required maxlength="255" placeholder="The Dark Knight">
                </div>
                <div class="form-group">
                    <label>جۆر *</label>
                    <select name="type" class="form-control" id="typeSelect">
                        <option value="movie">فیلم</option>
                        <option value="series">زنجیرە</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>باسکردن</label>
                <textarea name="description" class="form-control" rows="4" placeholder="چیرۆکی فیلمەکە..." maxlength="5000"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>ساڵی دەرچوون</label>
                    <input type="number" name="release_year" class="form-control" min="1900" max="2030" placeholder="2024">
                </div>
                <div class="form-group">
                    <label>IMDb Rate</label>
                    <input type="number" name="imdb_rate" class="form-control" min="0" max="10" step="0.1" placeholder="8.5">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>کوالیتی</label>
                    <select name="quality" class="form-control">
                        <option value="HD">HD</option>
                        <option value="FHD">FHD (1080p)</option>
                        <option value="4K">4K</option>
                        <option value="CAM">CAM</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ماوە</label>
                    <input type="text" name="duration" class="form-control" placeholder="120 min">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>وڵات</label>
                    <input type="text" name="country" class="form-control" placeholder="USA">
                </div>
                <div class="form-group">
                    <label>دەرهێنەر</label>
                    <input type="text" name="director" class="form-control" placeholder="Christopher Nolan">
                </div>
            </div>
            
            <div class="form-group">
                <label>ئاکتەرەکان (گەڕان لە داتابەیس)</label>
                <div style="position:relative;">
                    <input type="text" id="actor-search-input" class="form-control" placeholder="ناوی ئەکتەر بنووسە بۆ گەڕان..." autocomplete="off">
                    <div id="actor-search-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:50;max-height:250px;overflow-y:auto;background:rgba(14,14,14,0.95);border:1px solid rgba(245,197,24,0.15);border-radius:8px;margin-top:4px;"></div>
                </div>
                <div id="selected-actors" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
                <input type="hidden" name="actors" id="actors-hidden" value="">
                <small class="text-gray">ئەکتەرەکان لە بەشی "بەڕێوەبردنی ئەکتەران" زیاد بکە، پاشان لێرە گەڕان بکە</small>
            </div>
            
            <div class="form-group">
                <label>ژانرەکان</label>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($allGenres as $g): ?>
                        <label style="display:flex; align-items:center; gap:5px; cursor:pointer; color:var(--gray-light);">
                            <input type="checkbox" name="genres[]" value="<?= $g['id'] ?>"> <?= clean($g['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Media -->
            <h3 class="text-gold" style="margin: 30px 0 20px;"><i class="fas fa-image"></i> وێنەکان</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>پۆستەر</label>
                    <input type="file" name="poster" class="form-control" accept="image/*">
                </div>
                <div class="form-group">
                    <label>وێنەی پاشبنەما (Backdrop)</label>
                    <input type="file" name="backdrop" class="form-control" accept="image/*">
                </div>
            </div>
            
            <div class="form-group">
                <label>لینکی تریلەر (YouTube/Embed)</label>
                <input type="url" name="trailer_url" class="form-control" placeholder="https://www.youtube.com/embed/...">
            </div>

            <!-- Video Links -->
            <h3 class="text-gold" style="margin: 30px 0 20px;"><i class="fas fa-play-circle"></i> لینکەکانی ڤیدیۆ (٣ زمان)</h3>
            
            <?php foreach (['kurdish' => '🇹🇯 کوردی', 'arabic' => '🇸🇦 عەرەبی', 'english' => '🇬🇧 ئینگلیزی'] as $code => $label): ?>
            <div class="glass" style="padding: 20px; margin-bottom: 15px;">
                <h4 style="margin-bottom: 15px;"><?= $label ?></h4>
                <div id="links-<?= $code ?>">
                    <div class="form-row" style="margin-bottom:10px;">
                        <input type="text" name="server_<?= $code ?>[]" class="form-control" placeholder="Server 1" value="Server 1">
                        <input type="url" name="video_<?= $code ?>[]" class="form-control" placeholder="https://embed.example.com/...">
                    </div>
                    <div class="form-row" style="margin-bottom:10px;">
                        <input type="text" name="server_<?= $code ?>[]" class="form-control" placeholder="Server 2" value="Server 2">
                        <input type="url" name="video_<?= $code ?>[]" class="form-control" placeholder="https://embed.example.com/...">
                    </div>
                </div>
                <button type="button" class="btn btn-glass btn-sm" onclick="addLinkField('<?= $code ?>')">
                    <i class="fas fa-plus"></i> زیادکردنی سێرڤەر
                </button>
            </div>
            <?php endforeach; ?>

            <!-- Series Options -->
            <div id="seriesOptions" style="display:none;">
                <h3 class="text-gold" style="margin: 30px 0 20px;"><i class="fas fa-list-ol"></i> وەرز و ئەڵقە</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>ژمارەی وەرزەکان</label>
                        <input type="number" name="season_count" class="form-control" min="1" max="50" value="1" id="seasonCount">
                    </div>
                </div>
                <div id="episodeInputs"></div>
            </div>

            <!-- SEO -->
            <h3 class="text-gold" style="margin: 30px 0 20px;"><i class="fas fa-search"></i> SEO</h3>
            <div class="form-group">
                <label>Meta Title</label>
                <input type="text" name="meta_title" class="form-control" placeholder="ئیختیاری - بۆ SEO" maxlength="255">
            </div>
            <div class="form-group">
                <label>Meta Description</label>
                <textarea name="meta_description" class="form-control" rows="2" placeholder="ئیختیاری - بۆ SEO" maxlength="500"></textarea>
            </div>

            <button type="submit" class="btn btn-gold btn-lg" style="margin-top: 20px;">
                <i class="fas fa-save"></i> پاشەکەوتکردن
            </button>
        </form>
    </main>
</div>

<script>
// Toggle series options
document.getElementById('typeSelect').addEventListener('change', function() {
    document.getElementById('seriesOptions').style.display = this.value === 'series' ? 'block' : 'none';
});

// Dynamic season episode inputs
document.getElementById('seasonCount')?.addEventListener('change', function() {
    const count = parseInt(this.value);
    const container = document.getElementById('episodeInputs');
    container.innerHTML = '';
    for (let i = 1; i <= count; i++) {
        container.innerHTML += `
            <div class="form-group">
                <label>ژمارەی ئەڵقەکانی وەرزی ${i}</label>
                <input type="number" name="episodes_s${i}" class="form-control" min="1" max="100" value="10" placeholder="ژمارەی ئەڵقەکان">
            </div>
        `;
    }
});

// Add video link field
function addLinkField(lang) {
    const container = document.getElementById(`links-${lang}`);
    const count = container.children.length + 1;
    container.insertAdjacentHTML('beforeend', `
        <div class="form-row" style="margin-bottom:10px;">
            <input type="text" name="server_${lang}[]" class="form-control" placeholder="Server ${count}" value="Server ${count}">
            <input type="url" name="video_${lang}[]" class="form-control" placeholder="https://embed.example.com/...">
        </div>
    `);
}

// ============ ACTOR MULTI-SELECT SEARCH ============
let selectedActors = [];
const actorSearchInput = document.getElementById('actor-search-input');
const actorDropdown = document.getElementById('actor-search-dropdown');
const selectedActorsDiv = document.getElementById('selected-actors');
const actorsHidden = document.getElementById('actors-hidden');
let actorSearchTimeout;

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
    if (selectedActors.some(a => a.id === actor.id)) return;
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
                                <div>
                                    <div style="font-size:0.85rem;font-weight:600;">${a.name}</div>
                                    ${a.name_ku && a.name_ku !== a.name ? `<div style="font-size:0.72rem;color:var(--gray);">${a.name_ku}</div>` : ''}
                                </div>
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
