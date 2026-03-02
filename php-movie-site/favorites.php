<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$stmt = $pdo->prepare("SELECT m.* FROM favorites f JOIN movies m ON f.movie_id = m.id WHERE f.user_id = ? ORDER BY f.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$favorites = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="search-page">
    <div class="section-title" style="padding-top: 10px;">
        <span class="gold-line"></span>
        <span><i class="fas fa-heart text-gold"></i> لیستی دڵخوازەکانم</span>
    </div>
    
    <div class="movies-grid">
        <?php foreach ($favorites as $movie): ?>
        <a href="<?= SITE_URL ?>/movie/<?= $movie['slug'] ?>" class="movie-card">
            <div class="poster">
                <img src="<?= SITE_URL ?>/uploads/posters/<?= $movie['poster'] ?: 'default.jpg' ?>" alt="<?= clean($movie['title']) ?>" loading="lazy">
                <div class="overlay">
                    <div class="play-btn"><i class="fas fa-play"></i></div>
                </div>
                <span class="quality-badge"><?= $movie['quality'] ?></span>
            </div>
            <div class="info">
                <h3><?= clean($movie['title']) ?></h3>
                <div class="meta">
                    <span><?= $movie['release_year'] ?></span>
                    <span class="rating"><i class="fas fa-star"></i> <?= $movie['imdb_rate'] ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($favorites)): ?>
        <div class="text-center" style="padding: 80px 0;">
            <i class="fas fa-heart" style="font-size: 3rem; color: var(--gray-dark); margin-bottom: 20px; display:block;"></i>
            <p class="text-gray">هیچ فیلمێکت لە دڵخوازەکان زیاد نەکردووە</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
