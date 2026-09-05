<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = clean($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . SITE_URL); exit; }

$movie = getMovie($pdo, $slug);
if (!$movie) {
    // Try URL-decoded slug
    $decodedSlug = clean(urldecode($_GET['slug'] ?? ''));
    $movie = getMovie($pdo, $decodedSlug);
    if (!$movie) {
        // Try searching by similar slug
        $likeStmt = $pdo->prepare("SELECT * FROM movies WHERE slug LIKE ? AND status = 'published' LIMIT 1");
        $likeStmt->execute(['%' . $slug . '%']);
        $movie = $likeStmt->fetch();
        if (!$movie) {
            header('HTTP/1.0 404 Not Found');
            header('Location: ' . SITE_URL . '/404.php');
            exit;
        }
    }
}

// Increment views & history
incrementViews($pdo, $movie['id']);
if (isLoggedIn()) {
    addToHistory($pdo, $_SESSION['user_id'], $movie['id']);
}

// Video links grouped by language (encrypted)
$videoLinks = getVideoLinks($pdo, $movie['id']);
$encryptedLinks = getEncryptedVideoLinks($pdo, $movie['id']);
$linksByLang = [];
$encLinksByLang = [];
foreach ($videoLinks as $i => $link) {
    $linksByLang[$link['language']][] = $link;
    $encLinksByLang[$link['language']][] = $encryptedLinks[$i];
}

// Series: seasons & episodes
$seasons = [];
$episodes = [];
if ($movie['type'] === 'series') {
    $seasons = getSeasons($pdo, $movie['id']);
    foreach ($seasons as $s) {
        $episodes[$s['season_number']] = getEpisodes($pdo, $s['id']);
    }
}

// Favorites
$isFav = isLoggedIn() ? isFavorited($pdo, $_SESSION['user_id'], $movie['id']) : false;

// Suggested movies (same genre)
$suggested = getSuggestedMoviesByGenre($pdo, $movie);

// Comments
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        movie_id INT NOT NULL,
        content TEXT NOT NULL,
        is_approved TINYINT(1) DEFAULT 1,
        is_spoiler TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $commentsStmt = $pdo->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.movie_id = ? AND c.is_approved = 1 ORDER BY c.created_at DESC LIMIT 20");
    $commentsStmt->execute([$movie['id']]);
    $comments = $commentsStmt->fetchAll();
} catch (Exception $e) {
    $comments = [];
}

// Ads
$sidebarAds = getAds($pdo, 'sidebar');
$playerAds = getAds($pdo, 'player');

// Parse actors for linking
$actorsList = !empty($movie['actors']) ? parseActors($movie['actors']) : [];

// Parse directors (comma-separated)
$directorsList = [];
if (!empty($movie['director'])) {
    $directorsList = array_filter(array_map('trim', preg_split('/[,،]+/', $movie['director'])));
}

require_once __DIR__ . '/includes/header.php';

// Enhanced SEO
$seoSchema = generateMovieSchema($movie);
$seoTitle = clean($movie['meta_title'] ?: $movie['title'] . ' | ' . SITE_NAME);
$seoDesc = clean($movie['meta_description'] ?: mb_substr($movie['description'] ?? '', 0, 160));
$seoPoster = SITE_URL . '/uploads/posters/' . ($movie['poster'] ?? 'default.jpg');
$seoUrl = SITE_URL . '/movie.php?slug=' . $movie['slug'];
?>

<!-- SEO Meta -->
<title><?= $seoTitle ?></title>
<meta name="description" content="<?= $seoDesc ?>">
<meta property="og:title" content="<?= $seoTitle ?>">
<meta property="og:description" content="<?= $seoDesc ?>">
<meta property="og:image" content="<?= $seoPoster ?>">
<meta property="og:url" content="<?= $seoUrl ?>">
<meta property="og:type" content="video.movie">
<meta property="og:site_name" content="<?= SITE_NAME ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $seoTitle ?>">
<meta name="twitter:description" content="<?= $seoDesc ?>">
<meta name="twitter:image" content="<?= $seoPoster ?>">
<link rel="canonical" href="<?= $seoUrl ?>">
<script type="application/ld+json"><?= $seoSchema ?></script>

<style id="dynamic-theme"></style>

<!-- ==================== CINEMATIC HERO ==================== -->
<div class="mv-hero" id="movie-header">
    <!-- Multi-layer background -->
    <div class="mv-hero-bg">
        <?php 
            $backdropPath = '';
            if (!empty($movie['backdrop']) && file_exists(__DIR__ . '/uploads/backdrops/' . $movie['backdrop'])) {
                $backdropPath = SITE_URL . '/uploads/backdrops/' . $movie['backdrop'];
            } elseif (!empty($movie['poster'])) {
                $backdropPath = SITE_URL . '/uploads/posters/' . $movie['poster'];
            } else {
                $backdropPath = SITE_URL . '/uploads/posters/default.jpg';
            }
        ?>
        <img src="<?= $backdropPath ?>" alt="" class="mv-hero-backdrop">
        <div class="mv-hero-grain"></div>
        <div class="mv-hero-gradient"></div>
        <div class="mv-hero-vignette"></div>
    </div>
    
    <!-- Hero Content -->
    <div class="mv-hero-content">
        <div class="mv-hero-poster">
            <img id="movie-poster" src="<?= SITE_URL ?>/uploads/posters/<?= $movie['poster'] ?: 'default.jpg' ?>" alt="<?= clean($movie['title']) ?>" crossorigin="anonymous">
            <div class="mv-poster-glow"></div>
        </div>
        
        <div class="mv-hero-info">
            <div class="mv-hero-badges">
                <span class="mv-badge mv-badge-quality"><?= $movie['quality'] ?></span>
                <span class="mv-badge mv-badge-year"><?= $movie['release_year'] ?></span>
                <?php if ($movie['duration']): ?>
                <span class="mv-badge"><i class="fas fa-clock"></i> <?= clean($movie['duration']) ?></span>
                <?php endif; ?>
            </div>
            
            <h1 class="mv-title"><?= clean($movie['title']) ?></h1>
            
            <!-- Rating Bar -->
            <div class="mv-rating-bar">
                <div class="mv-imdb-score">
                    <div class="mv-score-ring">
                        <svg viewBox="0 0 36 36">
                            <path class="mv-score-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <path class="mv-score-fill" stroke-dasharray="<?= ($movie['imdb_rate'] * 10) ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        </svg>
                        <span class="mv-score-num"><?= $movie['imdb_rate'] ?></span>
                    </div>
                    <span class="mv-score-label">IMDb</span>
                </div>
                <div class="mv-stats">
                    <div class="mv-stat"><i class="fas fa-eye"></i> <?= number_format($movie['views']) ?></div>
                    <div class="mv-stat"><i class="fas fa-globe"></i> <?= clean($movie['country'] ?? '') ?></div>
                </div>
            </div>
            
            <!-- Genres -->
            <div class="mv-genres">
                <?php if ($movie['genres']): ?>
                    <?php foreach (explode(',', $movie['genres']) as $gi => $genre): ?>
                        <span class="mv-genre" style="animation-delay:<?= $gi * 0.06 ?>s"><?= clean(trim($genre)) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="mv-actions">
                <?php if (!empty($videoLinks)): ?>
                <a href="#player-section" class="mv-btn mv-btn-play" onclick="document.getElementById('player-section').scrollIntoView({behavior:'smooth'});return false;">
                    <i class="fas fa-play"></i> سەیرکردن
                </a>
                <?php endif; ?>
                
                <button class="mv-btn mv-btn-fav fav-btn <?= $isFav ? 'active' : '' ?>" data-movie-id="<?= $movie['id'] ?>">
                    <?= $isFav ? '<i class="fas fa-heart"></i>' : '<i class="far fa-heart"></i>' ?> دڵخواز
                </button>
                
                <button class="mv-btn mv-btn-room" id="wp-create-btn-top" data-movie-id="<?= $movie['id'] ?>" <?php if (!isLoggedIn()): ?>onclick="alert('تکایە سەرەتا لۆگین بکە!'); window.location.href='<?= SITE_URL ?>/login.php'; return false;"<?php endif; ?>>
                    <i class="fas fa-tv"></i> ژووری سەیرکردن
                </button>
                
                <button class="mv-btn mv-btn-report report-btn" data-movie-id="<?= $movie['id'] ?>">
                    <i class="fas fa-flag"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MOVIE DETAILS SECTION ==================== -->
<div class="mv-details-section">
    <div class="mv-details-grid">
        <!-- Left: Info -->
        <div class="mv-info-col">
            <div class="mv-info-card">
                <h2 class="mv-section-heading"><i class="fas fa-film"></i> باسی فیلم</h2>
                <p class="mv-description"><?= nl2br(clean($movie['description'] ?? '')) ?></p>
            </div>
            
            <!-- Directors Section -->
            <?php if (!empty($directorsList)): ?>
            <div class="mv-info-card">
                <h2 class="mv-section-heading"><i class="fas fa-bullhorn"></i> دەرهێنەر<?= count($directorsList) > 1 ? 'ەکان' : '' ?></h2>
                <div class="mv-actors-list" style="margin-top:10px;">
                    <?php foreach ($directorsList as $dir): ?>
                        <a href="<?= SITE_URL ?>/actor.php?name=<?= urlencode($dir) ?>" class="mv-actor-chip" style="background:rgba(245,197,24,0.08);border-color:rgba(245,197,24,0.2);color:var(--gold);">
                            <i class="fas fa-megaphone" style="font-size:0.7rem;margin-left:4px;"></i> <?= clean($dir) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actors Section -->
            <?php if (!empty($actorsList)): ?>
            <div class="mv-info-card mv-cast-card">
                <h2 class="mv-section-heading"><i class="fas fa-users"></i> ئاکتەران</h2>
                <div class="mv-actors-list" style="margin-top:10px;">
                    <?php foreach ($actorsList as $i => $actor): ?>
                        <a href="<?= SITE_URL ?>/actor.php?name=<?= urlencode($actor) ?>" class="mv-actor-chip"><?= clean($actor) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right: Quick Info Sidebar -->
        <div class="mv-sidebar-col">
            <div class="mv-quick-info">
                <div class="mv-qi-item">
                    <span class="mv-qi-label">جۆر</span>
                    <span class="mv-qi-value"><?= $movie['type'] === 'series' ? 'زنجیرە' : 'فیلم' ?></span>
                </div>
                <div class="mv-qi-item">
                    <span class="mv-qi-label">کوالیتی</span>
                    <span class="mv-qi-value mv-qi-gold"><?= $movie['quality'] ?></span>
                </div>
                <div class="mv-qi-item">
                    <span class="mv-qi-label">ساڵ</span>
                    <span class="mv-qi-value"><?= $movie['release_year'] ?></span>
                </div>
                <?php if ($movie['duration']): ?>
                <div class="mv-qi-item">
                    <span class="mv-qi-label">ماوە</span>
                    <span class="mv-qi-value"><?= clean($movie['duration']) ?></span>
                </div>
                <?php endif; ?>
                <div class="mv-qi-item">
                    <span class="mv-qi-label">خاڵ</span>
                    <span class="mv-qi-value mv-qi-gold"><?= $movie['imdb_rate'] ?>/10</span>
                </div>
                <div class="mv-qi-item">
                    <span class="mv-qi-label">وڵات</span>
                    <span class="mv-qi-value"><?= clean($movie['country'] ?? '-') ?></span>
                </div>
                <?php if (!empty($directorsList)): ?>
                <div class="mv-qi-item">
                    <span class="mv-qi-label">دەرهێنەر</span>
                    <span class="mv-qi-value"><?= clean(implode('، ', $directorsList)) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== VIDEO PLAYER ==================== -->
<?php if (!empty($videoLinks)): ?>
<div class="mv-player-section" id="player-section">
    <div class="mv-player-header">
        <h2 class="mv-section-heading"><i class="fas fa-play-circle"></i> <?= $movie['type'] === 'series' ? 'سەیرکردنی ئەڵقەکان' : 'سەیرکردنی فیلم' ?></h2>
    </div>
    
    <div class="mv-player-wrap">
        <!-- Language tabs -->
        <div class="mv-lang-tabs">
            <?php 
            $langs = ['kurdish' => '🇹🇯 کوردی', 'arabic' => '🇸🇦 عەرەبی', 'english' => '🇬🇧 ئینگلیزی'];
            $first = true;
            foreach ($langs as $code => $label):
                if (isset($linksByLang[$code])):
            ?>
                <button class="lang-tab <?= $first ? 'active' : '' ?>" data-lang="<?= $code ?>"><?= $label ?></button>
            <?php $first = false; endif; endforeach; ?>
        </div>
        
        <!-- Server buttons -->
        <?php $firstLang = true; ?>
        <?php foreach ($linksByLang as $lang => $links): ?>
            <div class="servers-group server-tabs" data-lang="<?= $lang ?>" style="<?= $firstLang ? '' : 'display:none;' ?>">
                <?php foreach ($links as $i => $link): ?>
                    <button class="server-btn <?= ($firstLang && $i === 0) ? 'active' : '' ?>" data-url="<?= clean($link['video_url']) ?>" data-token="<?= encryptVideoUrl($link['video_url']) ?>">
                        <i class="fas fa-server"></i> <?= clean($link['server_name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php $firstLang = false; ?>
        <?php endforeach; ?>
        
        <!-- Player -->
        <div class="video-wrapper" id="video-wrapper">
            <?php $firstLink = reset($videoLinks); ?>
            <iframe src="<?= clean($firstLink['video_url']) ?>" allowfullscreen allow="autoplay; encrypted-media" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" referrerpolicy="no-referrer"></iframe>
        </div>
        
        <div id="resume-bar" style="display:none;"></div>
    </div>
</div>
<?php endif; ?>

<!-- ==================== SEASONS & EPISODES ==================== -->
<?php if ($movie['type'] === 'series' && !empty($seasons)): ?>
<div class="mv-player-section mv-seasons">
    <h2 class="mv-section-heading"><i class="fas fa-layer-group"></i> وەرز و ئەڵقەکان</h2>
    
    <div class="season-selector">
        <?php foreach ($seasons as $i => $season): ?>
            <button class="season-btn <?= $i === 0 ? 'active' : '' ?>" data-season="<?= $season['season_number'] ?>">
                وەرزی <?= $season['season_number'] ?>
            </button>
        <?php endforeach; ?>
    </div>
    
    <?php foreach ($seasons as $i => $season): ?>
        <div class="episodes-container episodes-grid" data-season="<?= $season['season_number'] ?>" style="<?= $i === 0 ? '' : 'display:none;' ?>">
            <?php if (isset($episodes[$season['season_number']])): ?>
                <?php foreach ($episodes[$season['season_number']] as $ep): ?>
                    <div class="episode-card" data-movie-id="<?= $movie['id'] ?>" data-season="<?= $season['season_number'] ?>" data-episode="<?= $ep['episode_number'] ?>">
                        <div class="ep-number"><i class="fas fa-play-circle"></i> ئەڵقەی <?= $ep['episode_number'] ?></div>
                        <div class="ep-title"><?= clean($ep['title'] ?: 'ئەڵقەی ' . $ep['episode_number']) ?></div>
                        <?php if ($ep['duration']): ?>
                            <div style="font-size:0.78rem; color:var(--gray); margin-top:5px;"><i class="fas fa-clock"></i> <?= clean($ep['duration']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <div class="episode-navigation" id="episode-nav" style="display:none;">
        <button class="btn btn-glass btn-sm" id="prev-episode" disabled><i class="fas fa-arrow-right"></i> ئەڵقەی پێشوو</button>
        <span id="current-episode-info" class="text-gold" style="font-weight:600;font-size:0.9rem;"></span>
        <button class="btn btn-gold btn-sm" id="next-episode"><i class="fas fa-arrow-left"></i> ئەڵقەی دواتر</button>
    </div>
</div>
<?php endif; ?>

<!-- Player Ad -->
<?php if (!empty($playerAds)): ?>
<div class="container"><div class="ad-banner"><a href="<?= clean($playerAds[0]['target_url']) ?>" target="_blank"><img src="<?= clean($playerAds[0]['image_url']) ?>" alt="Ad"></a></div></div>
<?php endif; ?>

<!-- ==================== SUGGESTED MOVIES ==================== -->
<?php if (!empty($suggested)): ?>
<div class="mv-player-section">
    <h2 class="mv-section-heading"><i class="fas fa-fire"></i> فیلمە هاوشێوەکان</h2>
    <div class="movies-grid">
        <?php foreach ($suggested as $si => $s): ?>
        <a href="<?= SITE_URL ?>/movie.php?slug=<?= $s['slug'] ?>" class="movie-card" style="animation-delay:<?= ($si * 0.06) ?>s">
            <div class="poster">
                <img src="<?= SITE_URL ?>/uploads/posters/<?= $s['poster'] ?: 'default.jpg' ?>" alt="<?= clean($s['title']) ?>" loading="lazy">
                <div class="overlay"><div class="play-btn"><i class="fas fa-play"></i></div></div>
                <span class="quality-badge"><?= $s['quality'] ?></span>
            </div>
            <div class="info">
                <h3><?= clean($s['title']) ?></h3>
                <div class="meta"><span><?= $s['release_year'] ?></span><span class="rating"><i class="fas fa-star"></i> <?= $s['imdb_rate'] ?></span></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ==================== COMMENTS ==================== -->
<div class="mv-player-section mv-comments">
    <h2 class="mv-section-heading"><i class="fas fa-comments"></i> بۆچوونەکان (<?= count($comments) ?>)</h2>
    
    <?php if (isLoggedIn()): ?>
    <form class="comment-form mv-comment-form">
        <input type="hidden" name="movie_id" value="<?= $movie['id'] ?>">
        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
        <textarea name="content" placeholder="بۆچوونی خۆت بنوسە..." required style="font-size:16px !important;"></textarea>
        <div class="mv-comment-actions">
            <label class="mv-spoiler-check"><input type="checkbox" name="is_spoiler" value="1"><i class="fas fa-eye-slash"></i> سپۆیلەر</label>
            <button type="submit" class="mv-btn mv-btn-play" style="padding:8px 20px;font-size:0.85rem;"><i class="fas fa-paper-plane"></i> ناردن</button>
        </div>
    </form>
    <?php else: ?>
    <div class="mv-login-prompt">بۆ نوسینی بۆچوون <a href="<?= SITE_URL ?>/login.php">چوونەژوورەوە</a> پێویستە.</div>
    <?php endif; ?>
    
    <?php foreach ($comments as $ci => $c): ?>
    <div class="comment-card" style="animation-delay:<?= $ci * 0.05 ?>s" id="comment-<?= $c['id'] ?>">
        <div class="comment-header">
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $c['user_id'] ?>" class="comment-user"><i class="fas fa-user-circle"></i> <?= clean($c['username']) ?></a>
            <span class="comment-date"><?= date('Y/m/d H:i', strtotime($c['created_at'])) ?></span>
            <?php if (!empty($c['is_spoiler'])): ?><span class="spoiler-badge"><i class="fas fa-eye-slash"></i> سپۆیلەر</span><?php endif; ?>
            <?php if (isLoggedIn() && ($c['user_id'] == $_SESSION['user_id'] || isAdmin())): ?>
            <div class="comment-dots-menu" style="position:relative;margin-right:auto;">
                <button class="comment-dots-btn" onclick="toggleCommentMenu(this)" style="background:none;border:none;color:var(--gray);cursor:pointer;padding:4px 8px;font-size:0.85rem;transition:color 0.2s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--gray)'"><i class="fas fa-ellipsis-v"></i></button>
                <div class="comment-dropdown" style="display:none;position:absolute;top:100%;left:0;background:rgba(14,14,14,0.95);border:1px solid rgba(245,197,24,0.15);border-radius:10px;padding:4px;z-index:10;min-width:120px;backdrop-filter:blur(20px);box-shadow:0 8px 24px rgba(0,0,0,0.4);">
                    <?php if ($c['user_id'] == $_SESSION['user_id']): ?>
                    <button onclick="editComment(<?= $c['id'] ?>, this)" style="display:flex;align-items:center;gap:6px;width:100%;background:none;border:none;color:var(--gold);cursor:pointer;padding:8px 12px;font-size:0.78rem;border-radius:6px;font-family:inherit;transition:background 0.2s;" onmouseover="this.style.background='rgba(245,197,24,0.08)'" onmouseout="this.style.background=''"><i class="fas fa-edit"></i> دەستکاری</button>
                    <?php endif; ?>
                    <button onclick="deleteComment(<?= $c['id'] ?>)" style="display:flex;align-items:center;gap:6px;width:100%;background:none;border:none;color:var(--red);cursor:pointer;padding:8px 12px;font-size:0.78rem;border-radius:6px;font-family:inherit;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,82,82,0.08)'" onmouseout="this.style.background=''"><i class="fas fa-trash"></i> سڕینەوە</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($c['is_spoiler'])): ?>
        <div class="comment-text spoiler-blur" onclick="this.classList.remove('spoiler-blur');this.classList.add('spoiler-revealed');">
            <div class="spoiler-overlay"><i class="fas fa-eye-slash"></i> کلیک بکە بۆ بینین</div>
            <?= nl2br(clean($c['content'])) ?>
        </div>
        <?php else: ?>
        <div class="comment-text" id="comment-text-<?= $c['id'] ?>"><?= nl2br(clean($c['content'])) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($comments)): ?>
    <p class="text-center text-gray" style="padding: 30px 0;">هیچ بۆچوونێک نەنوسراوە. یەکەم کەس بە!</p>
    <?php endif; ?>
</div>

<!-- Scripts -->
<script>
// Toggle comment 3-dot menu
function toggleCommentMenu(btn) {
    document.querySelectorAll('.comment-dropdown').forEach(d => {
        if (d !== btn.nextElementSibling) d.style.display = 'none';
    });
    const dropdown = btn.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', e => {
    if (!e.target.closest('.comment-dots-menu')) {
        document.querySelectorAll('.comment-dropdown').forEach(d => d.style.display = 'none');
    }
});

function deleteComment(commentId) {
    if (!confirm('دڵنیایت لە سڕینەوەی ئەم بۆچوونە؟')) return;
    fetch(`${window.SITE_URL}/api/comment-manage.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'delete', comment_id: commentId})
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            const el = document.getElementById('comment-' + commentId);
            if (el) { el.style.animation = 'fadeIn 0.3s reverse'; setTimeout(() => el.remove(), 300); }
            if (typeof CineSound !== 'undefined') CineSound.success();
        }
    });
}

function editComment(commentId, btn) {
    const textEl = document.getElementById('comment-text-' + commentId);
    if (!textEl) return;
    const oldText = textEl.innerText;
    textEl.innerHTML = `<textarea class="form-control" id="edit-comment-${commentId}" style="font-size:16px !important;min-height:60px;">${oldText}</textarea>
        <div style="margin-top:8px;display:flex;gap:6px;">
            <button class="btn btn-gold btn-sm" style="padding:4px 12px;font-size:0.78rem;" onclick="saveComment(${commentId})"><i class="fas fa-save"></i> پاشەکەوت</button>
            <button class="btn btn-glass btn-sm" style="padding:4px 12px;font-size:0.78rem;" onclick="location.reload()"><i class="fas fa-times"></i> پاشگەزبوونەوە</button>
        </div>`;
}

function saveComment(commentId) {
    const textarea = document.getElementById('edit-comment-' + commentId);
    if (!textarea) return;
    const content = textarea.value.trim();
    if (!content) return;
    fetch(`${window.SITE_URL}/api/comment-manage.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
        body: JSON.stringify({action:'edit', comment_id: commentId, content: content})
    }).then(r=>r.json()).then(d => {
        if (d.success) { if (typeof CineSound !== 'undefined') CineSound.success(); location.reload(); }
    });
}

// Create Room button - with Public/Private popup
const wpCreateBtnTop = document.getElementById('wp-create-btn-top');
if (wpCreateBtnTop) {
    wpCreateBtnTop.addEventListener('click', function() {
        const movieId = this.dataset.movieId;
        
        // Show public/private selection popup
        let modal = document.createElement('div');
        modal.className = 'room-create-modal';
        modal.innerHTML = `
            <div class="room-create-modal-content">
                <h3><i class="fas fa-tv" style="color:var(--gold);margin-left:8px;"></i> دروستکردنی ژووری سەیرکردن</h3>
                <p>جۆری ژوورەکە هەڵبژێرە</p>
                <div class="room-type-options">
                    <div class="room-type-option selected" data-type="public" onclick="selectRoomType(this,'public')">
                        <i class="fas fa-globe" style="color:var(--green);"></i>
                        <div class="type-label">گشتی</div>
                        <div class="type-desc">هەموو کەسێک دەتوانێت ببینێت</div>
                    </div>
                    <div class="room-type-option" data-type="private" onclick="selectRoomType(this,'private')">
                        <i class="fas fa-lock" style="color:#FF3B30;"></i>
                        <div class="type-label">تایبەت</div>
                        <div class="type-desc">تەنها بانگهێشتکراوان</div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-gold" style="flex:1;" onclick="createRoomWithType(${movieId})"><i class="fas fa-play"></i> دروستکردن</button>
                    <button class="btn btn-glass" style="flex:1;" onclick="this.closest('.room-create-modal').remove()"><i class="fas fa-times"></i> پاشگەزبوونەوە</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.querySelector('.room-create-modal-content').style.cssText += 'background:rgba(14,14,14,0.95);border:1px solid rgba(245,197,24,0.15);border-radius:16px;padding:30px;max-width:380px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.5);';
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.remove(); });
    });
}

let selectedRoomType = 'public';
function selectRoomType(el, type) {
    selectedRoomType = type;
    document.querySelectorAll('.room-type-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
}

function createRoomWithType(movieId) {
    const modal = document.querySelector('.room-create-modal');
    const btn = modal.querySelector('.btn-gold');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> دروستکردن...';
    
    fetch(`${window.SITE_URL}/api/watch-party.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'create', movie_id: movieId, is_public: selectedRoomType === 'public' ? 1 : 0 })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (typeof CineSound !== 'undefined') CineSound.success();
            window.location.href = `${window.SITE_URL}/room.php?id=${data.room_id}`;
        } else {
            alert(data.message === 'login_required' ? 'تکایە سەرەتا لۆگین بکە!' : 'هەڵە: ' + (data.message || ''));
            if (modal) modal.remove();
        }
    })
    .catch(() => {
        alert('هەڵە لە پەیوەندیکردن.');
        if (modal) modal.remove();
    });
}

// Dynamic color extraction
(function() {
    const poster = document.getElementById('movie-poster');
    if (!poster) return;
    function extractColor() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 50; canvas.height = 75;
            ctx.drawImage(poster, 0, 0, 50, 75);
            const data = ctx.getImageData(0, 0, 50, 75).data;
            let r=0,g=0,b=0,count=0;
            for (let i=0;i<data.length;i+=16) {
                const pr=data[i],pg=data[i+1],pb=data[i+2];
                const brightness=(pr+pg+pb)/3;
                if (brightness>30&&brightness<220){r+=pr;g+=pg;b+=pb;count++;}
            }
            if (count>0) {
                r=Math.round(r/count);g=Math.round(g/count);b=Math.round(b/count);
                document.getElementById('dynamic-theme').textContent = `
                    .mv-hero-gradient { background: linear-gradient(to top, var(--black) 0%, rgba(${r},${g},${b},0.08) 40%, transparent 100%) !important; }
                    .mv-score-fill { stroke: rgb(${r},${g},${b}) !important; }
                    .mv-poster-glow { background: radial-gradient(circle, rgba(${r},${g},${b},0.25) 0%, transparent 70%) !important; }
                    .mv-genre { border-color: rgba(${r},${g},${b},0.3) !important; color: rgb(${Math.min(255,r+60)},${Math.min(255,g+60)},${Math.min(255,b+60)}) !important; }
                    .mv-btn-play { background: linear-gradient(135deg, rgb(${r},${g},${b}), rgb(${Math.max(0,r-30)},${Math.max(0,g-30)},${Math.max(0,b-30)})) !important; }
                `;
            }
        } catch(e) {}
    }
    if (poster.complete) extractColor();
    else poster.addEventListener('load', extractColor);
})();
</script>

<!-- Room Create Modal Styles -->
<style>
.room-create-modal {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(10px);
    animation: fadeIn 0.3s ease;
}
.room-type-options { display: flex; gap: 12px; margin-bottom: 20px; }
.room-type-option {
    flex: 1; padding: 18px 12px; border-radius: 12px; cursor: pointer;
    border: 2px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02);
    transition: all 0.3s; text-align: center; color: #fff;
}
.room-type-option:hover { border-color: rgba(245,197,24,0.3); }
.room-type-option.selected { border-color: var(--gold); background: rgba(245,197,24,0.06); }
.room-type-option i { font-size: 1.5rem; display: block; margin-bottom: 8px; }
.room-type-option .type-label { font-size: 0.85rem; font-weight: 600; }
.room-type-option .type-desc { font-size: 0.72rem; color: var(--gray); margin-top: 4px; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
