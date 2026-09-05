<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$actorName = clean($_GET['name'] ?? '');
if (!$actorName) { header('Location: ' . SITE_URL); exit; }

// Check if actor exists in actors table
$actorData = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM actors WHERE name = ? OR name_ku = ? LIMIT 1");
    $stmt->execute([$actorName, $actorName]);
    $actorData = $stmt->fetch();
} catch (Exception $e) {}

// Find movies with this actor (from movie_actors table or text field)
$movies = [];
if ($actorData) {
    try {
        $stmt = $pdo->prepare("SELECT m.*, GROUP_CONCAT(g.name) as genres 
            FROM movie_actors ma 
            JOIN movies m ON ma.movie_id = m.id
            LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
            LEFT JOIN genres g ON mg.genre_id = g.id 
            WHERE ma.actor_id = ? AND m.status = 'published'
            GROUP BY m.id 
            ORDER BY m.release_year DESC");
        $stmt->execute([$actorData['id']]);
        $movies = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// Fallback: also search in actors text field
if (empty($movies)) {
    $stmt = $pdo->prepare("SELECT m.*, GROUP_CONCAT(g.name) as genres 
        FROM movies m 
        LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
        LEFT JOIN genres g ON mg.genre_id = g.id 
        WHERE m.actors LIKE ? AND m.status = 'published'
        GROUP BY m.id 
        ORDER BY m.release_year DESC");
    $stmt->execute(['%' . $actorName . '%']);
    $movies = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<title><?= $actorName ?> - <?= SITE_NAME ?></title>
<meta name="description" content="هەموو فیلم و زنجیرەکانی <?= $actorName ?> لە <?= SITE_NAME ?>">

<!-- Actor Page -->
<div class="actor-page" style="padding-top: 120px; min-height: 80vh;">
    <div class="container">
        <div class="actor-header glass" style="padding: 40px; margin-bottom: 40px; text-align: center;">
            <?php if ($actorData && $actorData['profile_image']): ?>
                <img src="<?= SITE_URL ?>/uploads/actors/<?= $actorData['profile_image'] ?>" 
                    style="width:150px;height:200px;object-fit:cover;border-radius:14px;margin:0 auto 20px;display:block;box-shadow:0 15px 40px rgba(0,0,0,0.5);border:2px solid rgba(245,197,24,0.15);" alt="<?= $actorName ?>">
            <?php else: ?>
                <div class="actor-avatar" style="width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-dark));display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:3rem;">
                    <i class="fas fa-user-tie" style="color:var(--black);"></i>
                </div>
            <?php endif; ?>
            
            <h1 style="font-size:2rem;margin-bottom:8px;"><?= $actorName ?></h1>
            
            <?php if ($actorData): ?>
                <?php if ($actorData['known_for']): ?>
                    <span style="display:inline-block;padding:4px 14px;background:rgba(245,197,24,0.1);border:1px solid rgba(245,197,24,0.2);border-radius:20px;font-size:0.8rem;color:var(--gold);margin-bottom:10px;"><?= clean($actorData['known_for']) ?></span>
                <?php endif; ?>
                
                <?php if ($actorData['birthday']): ?>
                    <p style="color:var(--gray);font-size:0.85rem;"><i class="fas fa-birthday-cake"></i> <?= $actorData['birthday'] ?> 
                        <?php if ($actorData['place_of_birth']): ?> — <?= clean($actorData['place_of_birth']) ?><?php endif; ?>
                        <?php if ($actorData['deathday']): ?> <span style="color:var(--red);"> | ✝ <?= $actorData['deathday'] ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($actorData['biography_ku'] || $actorData['biography']): ?>
                    <p style="color:var(--gray-light);line-height:1.9;font-size:0.88rem;max-width:700px;margin:16px auto 0;text-align:justify;">
                        <?= nl2br(clean(mb_substr($actorData['biography_ku'] ?: $actorData['biography'], 0, 600))) ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            
            <p style="color:var(--gray);margin-top:10px;">
                <i class="fas fa-film"></i> <?= count($movies) ?> فیلم و زنجیرە
            </p>
        </div>

        <?php if (!empty($movies)): ?>
        <div class="section-title">
            <span class="gold-line"></span>
            <span>فیلمەکانی <?= $actorName ?></span>
        </div>
        <div class="movies-grid">
            <?php foreach ($movies as $m): ?>
            <a href="<?= SITE_URL ?>/movie.php?slug=<?= $m['slug'] ?>" class="movie-card">
                <div class="poster">
                    <img src="<?= SITE_URL ?>/uploads/posters/<?= $m['poster'] ?: 'default.jpg' ?>" alt="<?= clean($m['title']) ?>" loading="lazy">
                    <div class="overlay">
                        <div class="play-btn"><i class="fas fa-play"></i></div>
                    </div>
                    <span class="quality-badge"><?= $m['quality'] ?></span>
                    <?php if ($m['type'] === 'series'): ?>
                        <span class="series-badge">زنجیرە</span>
                    <?php endif; ?>
                </div>
                <div class="info">
                    <h3><?= clean($m['title']) ?></h3>
                    <div class="meta">
                        <span><?= $m['release_year'] ?></span>
                        <span class="rating"><i class="fas fa-star"></i> <?= $m['imdb_rate'] ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="glass" style="padding:60px;text-align:center;">
            <i class="fas fa-film" style="font-size:3rem;color:var(--gray);margin-bottom:15px;"></i>
            <p style="color:var(--gray);">هیچ فیلمێک نەدۆزرایەوە بۆ ئەم ئەکتەرە.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
