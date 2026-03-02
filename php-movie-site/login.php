<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'تکایە دووبارە هەوڵ بدەرەوە.';
    } else {
        $username = clean($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = 'ئەم هەژمارە چالاک نییە.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: ' . SITE_URL . '/index.php');
                exit;
            }
        } else {
            $error = 'ناوی بەکارهێنەر یان وشەی نهێنی هەڵەیە.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card glass-strong animate__animated animate__fadeInUp">
        <h2><span>Cine</span>Gold</h2>
        <h3 style="text-align:center; margin-bottom:28px; color:var(--gray-light); font-weight:400; font-size: 1rem;">چوونەژوورەوە</h3>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> ناوی بەکارهێنەر / ئیمەیڵ</label>
                <input type="text" name="username" class="form-control" placeholder="ناو یان ئیمەیڵ" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> وشەی نهێنی</label>
                <input type="password" name="password" class="form-control" placeholder="وشەی نهێنی" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn btn-gold btn-lg" style="width:100%; justify-content:center;">
                <i class="fas fa-sign-in-alt"></i> چوونەژوورەوە
            </button>
        </form>
        
        <p style="text-align:center; margin-top:16px;">
            <a href="<?= SITE_URL ?>/forgot-password.php" class="text-gray" style="font-size:0.85rem;"><i class="fas fa-key"></i> وشەی نهێنیت لەبیرکردووە؟</a>
        </p>
        <p style="text-align:center; margin-top:12px; color:var(--gray);">
            هەژمارت نییە؟ <a href="<?= SITE_URL ?>/register.php" class="text-gold" style="font-weight:600;">تۆمارکردن</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
