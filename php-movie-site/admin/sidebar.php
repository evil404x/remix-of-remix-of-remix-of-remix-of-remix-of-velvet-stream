<!-- Admin Sidebar Component -->
<aside class="admin-sidebar">
    <div class="logo">🎬 <?= SITE_NAME ?></div>
    <ul class="admin-menu">
        <li><a href="<?= adminUrl() ?>" class="<?= basename($_SERVER['PHP_SELF'])==='index.php'?'active':'' ?>"><i class="fas fa-chart-line"></i> داشبۆرد</a></li>
        <li><a href="<?= adminUrl('movies.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='movies.php'?'active':'' ?>"><i class="fas fa-film"></i> فیلمەکان</a></li>
        <li><a href="<?= adminUrl('add-movie.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='add-movie.php'?'active':'' ?>"><i class="fas fa-plus"></i> زیادکردنی فیلم</a></li>
        <li><a href="<?= adminUrl('series.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='series.php'?'active':'' ?>"><i class="fas fa-tv"></i> زنجیرەکان</a></li>
        <li><a href="<?= adminUrl('requests.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='requests.php'?'active':'' ?>"><i class="fas fa-inbox"></i> داواکاریەکان</a></li>
        <li><a href="<?= adminUrl('users.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='users.php'?'active':'' ?>"><i class="fas fa-users"></i> بەکارهێنەران</a></li>
        <li><a href="<?= adminUrl('reports.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='reports.php'?'active':'' ?>"><i class="fas fa-flag"></i> ڕاپۆرتەکان</a></li>
        <li><a href="<?= adminUrl('comments.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='comments.php'?'active':'' ?>"><i class="fas fa-comments"></i> بۆچوونەکان</a></li>
        <li><a href="<?= adminUrl('ads.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='ads.php'?'active':'' ?>"><i class="fas fa-ad"></i> ڕیکلامەکان</a></li>
        <li><a href="<?= adminUrl('sliders.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='sliders.php'?'active':'' ?>"><i class="fas fa-images"></i> سڵایدەر</a></li>
        <li><a href="<?= adminUrl('smtp.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='smtp.php'?'active':'' ?>"><i class="fas fa-envelope"></i> SMTP ئیمەیڵ</a></li>
        <li><a href="<?= adminUrl('logs.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='logs.php'?'active':'' ?>"><i class="fas fa-shield-alt"></i> ئاسایش</a></li>
        <li><a href="<?= adminUrl('live-monitor.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='live-monitor.php'?'active':'' ?>"><i class="fas fa-satellite-dish"></i> مۆنیتۆری زیندوو</a></li>
        <li><a href="<?= adminUrl('point-settings.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='point-settings.php'?'active':'' ?>"><i class="fas fa-coins"></i> ڕێکخستنی خاڵ</a></li>
        <li><a href="<?= adminUrl('actors.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='actors.php'?'active':'' ?>"><i class="fas fa-user-tie"></i> ئەکتەران</a></li>
        <li><a href="<?= adminUrl('staff.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='staff.php'?'active':'' ?>"><i class="fas fa-id-badge"></i> ستاف</a></li>
        <li><a href="<?= adminUrl('spy-rooms.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='spy-rooms.php'?'active':'' ?>"><i class="fas fa-eye"></i> چاودێری ژوور</a></li>
        <li><a href="<?= adminUrl('user-reports.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='user-reports.php'?'active':'' ?>"><i class="fas fa-exclamation-triangle"></i> ڕاپۆرتی بەکارهێنەر</a></li>
        <li><a href="<?= adminUrl('stickers.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='stickers.php'?'active':'' ?>"><i class="fas fa-smile"></i> ستیکەرەکان</a></li>
        <li><a href="<?= adminUrl('code-editor.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='code-editor.php'?'active':'' ?>"><i class="fas fa-code"></i> دەستکاری کۆد</a></li>
        <li><a href="<?= adminUrl('settings.php') ?>" class="<?= basename($_SERVER['PHP_SELF'])==='settings.php'?'active':'' ?>"><i class="fas fa-cog"></i> ڕێکخستنەکان</a></li>
        <li><a href="<?= SITE_URL ?>"><i class="fas fa-globe"></i> بینینی سایت</a></li>
        <li><a href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> چوونەدەرەوە</a></li>
    </ul>
</aside>
