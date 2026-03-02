<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Get movies
$latestMovies = getMovies($pdo, 1, 20, 'movie');
$latestSeries = getMovies($pdo, 1, 10, 'series');
$allMovies = getMovies($pdo, 1, 30);

// Hero slider
$sliders = getSliders($pdo);
$hero = null;
if (empty($sliders) && !empty($allMovies)) {
    $hero = $allMovies[0];
}

// Ads
$headerAds = getAds($pdo, 'header');

require_once __DIR__ . '/includes/header.php';
?>

<?php if (!empty($sliders)): ?>
<!-- Hero Slider -->
<section class="hero" id="hero-slider-section">
    <?php foreach ($sliders as $si => $slide): ?>
    <div class="hero-slide <?= $si === 0 ? 'active' : '' ?>" data-index="<?= $si ?>">
        <?php 
            $bgImg = '';
            if (!empty($slide['backdrop']) && file_exists(__DIR__ . '/uploads/backdrops/' . $slide['backdrop'])) {
                $bgImg = SITE_URL . '/uploads/backdrops/' . $slide['backdrop'];
            } elseif (!empty($slide['image_url'])) {
                $bgImg = clean($slide['image_url']);
            } elseif (!empty($slide['image'])) {
                $bgImg = SITE_URL . '/uploads/sliders/' . $slide['image'];
            } elseif (!empty($slide['poster'])) {
                $bgImg = SITE_URL . '/uploads/posters/' . $slide['poster'];
            } else {
                $bgImg = SITE_URL . '/uploads/posters/default.jpg';
            }
        ?>
        <div class="hero-backdrop" style="background-image: url('<?= $bgImg ?>')"></div>
        <div class="hero-gradient"></div>
        <div class="hero-content animate__animated animate__fadeInUp">
            <?php if (!empty($slide['imdb_rate'])): ?>
            <div class="hero-badge">
                <i class="fas fa-star"></i>
                <span>IMDb <?= $slide['imdb_rate'] ?></span>
            </div>
            <?php endif; ?>
            <h1 class="hero-title"><?= clean($slide['title'] ?: $slide['movie_title'] ?? '') ?></h1>
            <p class="hero-desc"><?= clean(mb_substr($slide['description'] ?: $slide['movie_desc'] ?? '', 0, 200)) ?>...</p>
            <div class="hero-meta">
                <?php if (!empty($slide['release_year'])): ?>
                <span><i class="fas fa-calendar"></i> <?= $slide['release_year'] ?></span>
                <?php endif; ?>
                <?php if (!empty($slide['quality'])): ?>
                <span class="tag"><?= $slide['quality'] ?></span>
                <?php endif; ?>
            </div>
            <div class="hero-actions">
                <?php if (!empty($slide['slug'])): ?>
                <a href="<?= SITE_URL ?>/movie/<?= $slide['slug'] ?>" class="btn btn-gold btn-lg">
                    <i class="fas fa-play"></i> سەیرکردن
                </a>
                <?php elseif (!empty($slide['link'])): ?>
                <a href="<?= clean($slide['link']) ?>" class="btn btn-gold btn-lg">
                    <i class="fas fa-play"></i> سەیرکردن
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (count($sliders) > 1): ?>
    <div class="hero-dots">
        <?php for ($i = 0; $i < count($sliders); $i++): ?>
        <button class="hero-dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></button>
        <?php endfor; ?>
    </div>
    <button class="hero-arrow hero-prev"><i class="fas fa-chevron-right"></i></button>
    <button class="hero-arrow hero-next"><i class="fas fa-chevron-left"></i></button>
    <?php endif; ?>
</section>

<style>
.hero { position: relative; overflow: hidden; }
.hero-slide { display: none; position: absolute; inset: 0; }
.hero-slide.active { display: block; position: relative; }
.hero-dots { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 5; }
.hero-dot { width: 10px; height: 10px; border-radius: 50%; border: none; background: rgba(255,255,255,0.3); cursor: pointer; transition: all 0.3s; }
.hero-dot.active { background: var(--gold); transform: scale(1.2); }
.hero-arrow { position: absolute; top: 50%; z-index: 5; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.08); color: #fff; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
.hero-arrow:hover { background: rgba(245,197,24,0.2); }
.hero-prev { right: 20px; transform: translateY(-50%); }
.hero-next { left: 20px; transform: translateY(-50%); }
@media (max-width: 768px) { .hero-arrow { display: none; } }
</style>

<script>
(function() {
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.hero-dot');
    if (slides.length <= 1) return;
    let current = 0;
    function goTo(i) {
        slides[current].classList.remove('active');
        dots[current].classList.remove('active');
        current = (i + slides.length) % slides.length;
        slides[current].classList.add('active');
        dots[current].classList.add('active');
    }
    dots.forEach((d, i) => d.addEventListener('click', () => goTo(i)));
    const prev = document.querySelector('.hero-prev');
    const next = document.querySelector('.hero-next');
    if (prev) prev.addEventListener('click', () => goTo(current - 1));
    if (next) next.addEventListener('click', () => goTo(current + 1));
    setInterval(() => goTo(current + 1), 6000);
})();
</script>

<?php elseif ($hero): ?>
<!-- Single Hero -->
<section class="hero">
    <?php
        $heroBg = '';
        if (!empty($hero['backdrop']) && file_exists(__DIR__ . '/uploads/backdrops/' . $hero['backdrop'])) {
            $heroBg = SITE_URL . '/uploads/backdrops/' . $hero['backdrop'];
        } elseif (!empty($hero['poster'])) {
            $heroBg = SITE_URL . '/uploads/posters/' . $hero['poster'];
        } else {
            $heroBg = SITE_URL . '/uploads/posters/default.jpg';
        }
    ?>
    <div class="hero-backdrop" style="background-image: url('<?= $heroBg ?>')"></div>
    <div class="hero-gradient"></div>
    <div class="hero-content animate__animated animate__fadeInUp">
        <div class="hero-badge">
            <i class="fas fa-star"></i>
            <span>IMDb <?= $hero['imdb_rate'] ?></span>
        </div>
        <h1 class="hero-title"><?= clean($hero['title']) ?></h1>
        <p class="hero-desc"><?= clean(mb_substr($hero['description'] ?? '', 0, 200)) ?>...</p>
        <div class="hero-meta">
            <span><i class="fas fa-calendar"></i> <?= $hero['release_year'] ?></span>
            <span><i class="fas fa-clock"></i> <?= $hero['duration'] ?></span>
            <span class="tag"><?= $hero['quality'] ?></span>
            <span><i class="fas fa-globe"></i> <?= clean($hero['country'] ?? '') ?></span>
            <?php if ($hero['genres']): ?>
                <span><i class="fas fa-tags"></i> <?= clean($hero['genres']) ?></span>
            <?php endif; ?>
        </div>
        <div class="hero-actions">
            <a href="<?= SITE_URL ?>/movie/<?= $hero['slug'] ?>" class="btn btn-gold btn-lg">
                <i class="fas fa-play"></i> سەیرکردن
            </a>
            <a href="<?= SITE_URL ?>/movie/<?= $hero['slug'] ?>#trailer" class="btn btn-glass btn-lg">
                <i class="fas fa-film"></i> تریلەر
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ==================== LIVE WATCH PARTIES - Cinematic Grid ==================== -->
<section class="section" id="live-rooms-section" style="display:none;">
    <div class="container">
        <div class="section-title">
            <span class="gold-line"></span>
            <span><i class="fas fa-satellite-dish" style="color:#FF3B30;"></i> ژوورەکانی زیندوو</span>
        </div>
        <div id="live-rooms-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;"></div>
    </div>
</section>
<script>
(function() {
    fetch(`${window.SITE_URL}/api/public-rooms.php`, {credentials:'same-origin'})
        .then(r => r.json()).then(d => {
            if (!d.success || !d.rooms || d.rooms.length === 0) return;
            const section = document.getElementById('live-rooms-section');
            const grid = document.getElementById('live-rooms-grid');
            section.style.display = '';
            grid.innerHTML = d.rooms.map(r => `
                <a href="${window.SITE_URL}/room.php?id=${r.room_id}" class="live-room-card" style="display:flex;gap:14px;padding:14px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:14px;text-decoration:none;color:#fff;transition:all 0.3s;position:relative;overflow:hidden;" onmouseover="this.style.borderColor='rgba(255,59,48,0.3)';this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.06)';this.style.background='rgba(255,255,255,0.03)'">
                    <div style="position:relative;flex-shrink:0;">
                        <img src="${window.SITE_URL}/uploads/posters/${r.poster || 'default.jpg'}" alt="${r.movie_title}" style="width:70px;height:100px;object-fit:cover;border-radius:10px;" loading="lazy">
                        <span style="position:absolute;top:4px;right:4px;background:rgba(255,59,48,0.85);color:#fff;font-size:0.58rem;padding:2px 5px;border-radius:4px;font-weight:700;display:flex;align-items:center;gap:3px;"><span style="width:4px;height:4px;border-radius:50%;background:#fff;animation:pulse-dot 1.5s infinite;"></span>LIVE</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:6px;min-width:0;">
                        <div style="font-weight:600;font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.movie_title}</div>
                        <div style="display:flex;align-items:center;gap:10px;font-size:0.78rem;color:var(--gray);">
                            <span style="display:flex;align-items:center;gap:4px;"><i class="fas fa-users" style="color:#00BCD4;"></i> ${r.member_count}</span>
                            <span style="display:flex;align-items:center;gap:4px;"><i class="fas fa-crown" style="color:var(--gold);"></i> ${r.host_name}</span>
                        </div>
                        <div style="margin-top:4px;"><span style="background:rgba(255,59,48,0.1);border:1px solid rgba(255,59,48,0.15);color:#FF3B30;font-size:0.7rem;padding:3px 10px;border-radius:12px;">پەیوەستبوون</span></div>
                    </div>
                </a>
            `).join('');
        }).catch(() => {});
})();
</script>

<!-- Header Ad -->
<?php if (!empty($headerAds)): ?>
<div class="container">
    <div class="ad-banner">
        <?php $ad = $headerAds[0]; ?>
        <?php if ($ad['image_url']): ?>
            <a href="<?= clean($ad['target_url']) ?>" target="_blank">
                <img src="<?= clean($ad['image_url']) ?>" alt="<?= clean($ad['title']) ?>">
            </a>
        <?php else: ?>
            <?= $ad['content'] ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Latest Movies -->
<section class="section">
    <div class="container">
        <div class="section-title">
            <span class="gold-line"></span>
            <span>نوێترین فیلمەکان</span>
            <a href="<?= SITE_URL ?>/search.php?type=movie" class="btn btn-glass btn-sm" style="margin-right:auto;">هەمووی ببینە <i class="fas fa-arrow-left"></i></a>
        </div>
        <div class="movies-grid">
            <?php foreach ($latestMovies as $movie): ?>
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
        <?php if (empty($latestMovies)): ?>
            <div class="text-center" style="padding: 80px 0;">
                <i class="fas fa-film" style="font-size: 3rem; color: var(--gray-dark); margin-bottom: 20px; display:block;"></i>
                <p class="text-gray">هیچ فیلمێک نەدۆزرایەوە. لە پانێڵی ئادمینەوە فیلم زیاد بکە!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Latest Series -->
<?php if (!empty($latestSeries)): ?>
<section class="section">
    <div class="container">
        <div class="section-title">
            <span class="gold-line"></span>
            <span>نوێترین زنجیرەکان</span>
            <a href="<?= SITE_URL ?>/search.php?type=series" class="btn btn-glass btn-sm" style="margin-right:auto;">هەمووی ببینە <i class="fas fa-arrow-left"></i></a>
        </div>
        <div class="movies-grid">
            <?php foreach ($latestSeries as $series): ?>
            <a href="<?= SITE_URL ?>/movie/<?= $series['slug'] ?>" class="movie-card">
                <div class="poster">
                    <img src="<?= SITE_URL ?>/uploads/posters/<?= $series['poster'] ?: 'default.jpg' ?>" alt="<?= clean($series['title']) ?>" loading="lazy">
                    <div class="overlay">
                        <div class="play-btn"><i class="fas fa-play"></i></div>
                    </div>
                    <span class="quality-badge"><?= $series['quality'] ?></span>
                    <span class="type-badge"><i class="fas fa-tv"></i> زنجیرە</span>
                </div>
                <div class="info">
                    <h3><?= clean($series['title']) ?></h3>
                    <div class="meta">
                        <span><?= $series['release_year'] ?></span>
                        <span class="rating"><i class="fas fa-star"></i> <?= $series['imdb_rate'] ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Resume Session Banner -->
<?php if (isLoggedIn()): ?>
<script>
(function() {
    fetch(`${window.SITE_URL}/api/watch-party.php?action=my_active_room`, {credentials:'same-origin'})
        .then(r => r.json()).then(d => {
            if (!d.success || !d.room) return;
            const banner = document.createElement('div');
            banner.className = 'room-resume-banner';
            banner.innerHTML = `
                <div class="resume-info">
                    <div class="resume-title"><i class="fas fa-tv" style="color:var(--gold);margin-left:6px;"></i> ژوورێکی چالاکت هەیە</div>
                    <div class="resume-sub">${d.room.movie_title}</div>
                </div>
                <button class="room-resume-btn resume-go" onclick="window.location.href='${window.SITE_URL}/room.php?id=${d.room.room_id}'">گەڕانەوە</button>
                <button class="room-resume-btn resume-dismiss" onclick="this.parentElement.remove()">داخستن</button>
            `;
            document.body.appendChild(banner);
            setTimeout(() => { if (banner.parentElement) banner.remove(); }, 15000);
        }).catch(() => {});
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
