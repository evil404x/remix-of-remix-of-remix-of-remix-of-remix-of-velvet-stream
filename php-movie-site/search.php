<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
$filters = [
    'q' => clean($_GET['q'] ?? ''), 'type' => clean($_GET['type'] ?? ''),
    'genre' => clean($_GET['genre'] ?? ''), 'quality' => clean($_GET['quality'] ?? ''),
    'imdb_min' => clean($_GET['imdb_min'] ?? ''), 'year' => clean($_GET['year'] ?? ''),
    'sort' => clean($_GET['sort'] ?? 'newest'),
];
$results = searchMovies($pdo, $filters);
$genres = getGenres($pdo);
require_once __DIR__ . '/includes/header.php';
?>
<div class="search-page">
    <div class="section-title" style="padding-top:10px;"><span class="gold-line"></span>
        <span><?= $filters['type'] === 'movie' ? '<i class="fas fa-film"></i> فیلمەکان' : ($filters['type'] === 'series' ? '<i class="fas fa-tv"></i> زنجیرەکان' : '<i class="fas fa-search"></i> گەڕان') ?></span>
    </div>
    <form method="GET" class="filter-bar glass" style="padding:18px;margin-bottom:30px;">
        <input type="text" name="q" class="search-input-lg" placeholder="گەڕان..." value="<?= $filters['q'] ?>">
        <select name="type" class="filter-select"><option value="">هەموو</option><option value="movie" <?= $filters['type']==='movie'?'selected':'' ?>>فیلم</option><option value="series" <?= $filters['type']==='series'?'selected':'' ?>>زنجیرە</option></select>
        <select name="genre" class="filter-select"><option value="">ژانرا</option><?php foreach($genres as $g): ?><option value="<?= $g['slug'] ?>" <?= $filters['genre']===$g['slug']?'selected':'' ?>><?= clean($g['name']) ?></option><?php endforeach; ?></select>
        <select name="quality" class="filter-select"><option value="">کوالیتی</option><option value="4K" <?= $filters['quality']==='4K'?'selected':'' ?>>4K</option><option value="FHD" <?= $filters['quality']==='FHD'?'selected':'' ?>>FHD</option><option value="HD" <?= $filters['quality']==='HD'?'selected':'' ?>>HD</option></select>
        <select name="sort" class="filter-select"><option value="newest">نوێترین</option><option value="imdb" <?= $filters['sort']==='imdb'?'selected':'' ?>>IMDb</option><option value="views" <?= $filters['sort']==='views'?'selected':'' ?>>بینەر</option></select>
        <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-search"></i></button>
    </form>
    <div class="movies-grid">
        <?php foreach ($results as $movie): ?>
        <a href="<?= SITE_URL ?>/movie.php?slug=<?= $movie['slug'] ?>" class="movie-card">
            <div class="poster">
                <img src="<?= SITE_URL ?>/uploads/posters/<?= $movie['poster'] ?: 'default.jpg' ?>" alt="<?= clean($movie['title']) ?>" loading="lazy">
                <div class="overlay"><div class="play-btn"><i class="fas fa-play"></i></div></div>
                <span class="quality-badge"><?= $movie['quality'] ?></span>
            </div>
            <div class="info"><h3><?= clean($movie['title']) ?></h3><div class="meta"><span><?= $movie['release_year'] ?></span><span class="rating"><i class="fas fa-star"></i> <?= $movie['imdb_rate'] ?></span></div></div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($results)): ?><div class="text-center" style="padding:80px 0;"><p class="text-gray">هیچ ئەنجامێک نەدۆزرایەوە</p></div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
