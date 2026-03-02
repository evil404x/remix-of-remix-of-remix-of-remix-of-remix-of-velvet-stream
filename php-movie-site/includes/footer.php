<?php
// Load dynamic footer settings
$footerSettings = [];
try {
    $fStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('footer_text','copyright_text','facebook_url','twitter_url','instagram_url','telegram_url','youtube_url','contact_email')");
    foreach ($fStmt->fetchAll() as $row) { $footerSettings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$footerCopyright = $footerSettings['copyright_text'] ?? ('&copy; ' . date('Y') . ' ' . SITE_NAME . '. هەموو مافەکان پارێزراون. ✨');
$footerCustomText = $footerSettings['footer_text'] ?? 'باشترین وێبسایتی فیلم و زنجیرە بە کوالیتیی بەرز و سێ زمانی جیاواز.';
?>
<!-- Footer -->
<footer class="footer">
    <div class="footer-content">
        <div class="footer-col">
            <h4>🎬 <?= SITE_NAME ?></h4>
            <p style="color: var(--gray); font-size: 0.88rem; line-height: 1.9;">
                <?= clean($footerCustomText) ?>
            </p>
        </div>
        <div class="footer-col">
            <h4>لینکە گرنگەکان</h4>
            <ul>
                <li><a href="<?= SITE_URL ?>"><i class="fas fa-home"></i> سەرەتا</a></li>
                <li><a href="<?= SITE_URL ?>/search.php?type=movie"><i class="fas fa-film"></i> فیلمەکان</a></li>
                <li><a href="<?= SITE_URL ?>/search.php?type=series"><i class="fas fa-tv"></i> زنجیرەکان</a></li>
                <li><a href="<?= SITE_URL ?>/request.php"><i class="fas fa-inbox"></i> داواکردنی فیلم</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>ژانراکان</h4>
            <ul>
                <?php $footerGenres = getGenres($pdo); ?>
                <?php foreach (array_slice($footerGenres, 0, 6) as $g): ?>
                    <li><a href="<?= SITE_URL ?>/search.php?genre=<?= $g['slug'] ?>"><?= clean($g['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="footer-col">
            <h4>پەیوەندی</h4>
            <ul>
                <?php if (!empty($footerSettings['contact_email'])): ?>
                <li><a href="mailto:<?= clean($footerSettings['contact_email']) ?>"><i class="fas fa-envelope"></i> <?= clean($footerSettings['contact_email']) ?></a></li>
                <?php endif; ?>
                <?php if (!empty($footerSettings['telegram_url'])): ?>
                <li><a href="<?= clean($footerSettings['telegram_url']) ?>" target="_blank"><i class="fab fa-telegram"></i> Telegram</a></li>
                <?php endif; ?>
                <?php if (!empty($footerSettings['instagram_url'])): ?>
                <li><a href="<?= clean($footerSettings['instagram_url']) ?>" target="_blank"><i class="fab fa-instagram"></i> Instagram</a></li>
                <?php endif; ?>
                <?php if (!empty($footerSettings['facebook_url'])): ?>
                <li><a href="<?= clean($footerSettings['facebook_url']) ?>" target="_blank"><i class="fab fa-facebook"></i> Facebook</a></li>
                <?php endif; ?>
                <?php if (!empty($footerSettings['youtube_url'])): ?>
                <li><a href="<?= clean($footerSettings['youtube_url']) ?>" target="_blank"><i class="fab fa-youtube"></i> YouTube</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p><?= $footerCopyright ?></p>
    </div>
</footer>

<?php
// Popup ads
$popupAds = getAds($pdo, 'popup');
if (!empty($popupAds)):
    $ad = $popupAds[0];
?>
<div class="ad-overlay"></div>
<div class="ad-popup glass-strong" data-ad-id="<?= $ad['id'] ?>">
    <button class="ad-close">&times;</button>
    <?php if ($ad['image_url']): ?>
        <a href="<?= clean($ad['target_url']) ?>" target="_blank">
            <img src="<?= clean($ad['image_url']) ?>" alt="<?= clean($ad['title']) ?>" style="width:100%; display:block;">
        </a>
    <?php else: ?>
        <div style="padding: 25px;"><?= $ad['content'] ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script src="<?= SITE_URL ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>