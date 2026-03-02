<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'تکایە دووبارە هەوڵ بدەرەوە.';
    } else {
        $username = clean($_POST['username'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (strlen($username) < 3) { $error = 'ناوی بەکارهێنەر لانیکەم ٣ پیت بێت.'; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'ئیمەیڵ دروست نییە.'; }
        elseif (strlen($password) < 6) { $error = 'وشەی نهێنی لانیکەم ٦ پیت بێت.'; }
        else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) { $error = 'ئەم ناوە یان ئیمەیڵە پێشتر تۆمارکراوە.'; }
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)")->execute([$username, $email, $hash]);
                $success = 'هەژمارەکەت بە سەرکەوتوویی دروستکرا!';
            }
        }
    }
}
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-page">
    <div class="auth-card glass-strong animate__animated animate__fadeInUp">
        <h2><span>Cine</span>Gold</h2>
        <h3 style="text-align:center;margin-bottom:28px;color:var(--gray-light);font-weight:400;font-size:1rem;">دروستکردنی هەژمار</h3>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
            <div class="form-group"><label>ناوی بەکارهێنەر</label><input type="text" name="username" class="form-control" required></div>
            <div class="form-group"><label>ئیمەیڵ</label><input type="email" name="email" class="form-control" required></div>
            <div class="form-group"><label>وشەی نهێنی</label><input type="password" name="password" class="form-control" required></div>
            <button type="submit" class="btn btn-gold btn-lg" style="width:100%;justify-content:center;"><i class="fas fa-user-plus"></i> تۆمارکردن</button>
        </form>
        <p style="text-align:center;margin-top:22px;color:var(--gray);">هەژمارت هەیە؟ <a href="<?= SITE_URL ?>/login.php" class="text-gold">چوونەژوورەوە</a></p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
