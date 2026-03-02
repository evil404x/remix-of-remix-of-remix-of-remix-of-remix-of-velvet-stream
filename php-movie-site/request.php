<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) { $error = 'تکایە دووبارە هەوڵ بدەرەوە.'; }
    else {
        $movieName = clean($_POST['movie_name'] ?? '');
        $imdbLink = clean($_POST['imdb_link'] ?? '');
        $message = clean($_POST['message'] ?? '');
        if (empty($movieName)) { $error = 'ناوی فیلم پێویستە.'; }
        else {
            $pdo->prepare("INSERT INTO movie_requests (user_id, movie_name, imdb_link, message) VALUES (?, ?, ?, ?)")
                ->execute([isLoggedIn() ? $_SESSION['user_id'] : null, $movieName, $imdbLink, $message]);
            $success = 'داواکاریەکەت نێردرا! سوپاس 🎬';
        }
    }
}
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-page">
    <div class="auth-card glass-strong animate__animated animate__fadeInUp" style="max-width:520px;">
        <h3 style="text-align:center;margin-bottom:28px;color:var(--gold);"><i class="fas fa-plus-circle"></i> داواکردنی فیلم</h3>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
            <div class="form-group"><label>ناوی فیلم *</label><input type="text" name="movie_name" class="form-control" required></div>
            <div class="form-group"><label>لینکی IMDb</label><input type="url" name="imdb_link" class="form-control"></div>
            <div class="form-group"><label>پەیام</label><textarea name="message" class="form-control" rows="3"></textarea></div>
            <button type="submit" class="btn btn-gold btn-lg" style="width:100%;justify-content:center;"><i class="fas fa-paper-plane"></i> ناردن</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
