<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'تکایە دووبارە هەوڵ بدەرەوە.';
    } else {
        $displayName = clean($_POST['display_name'] ?? '');
        $username = strtolower(clean($_POST['username'] ?? ''));
        $email = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Auto-add display_name column
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
        
        // Validate display name
        if (mb_strlen($displayName) < 2) { $error = 'ناو لانیکەم ٢ پیت بێت.'; }
        // Validate username
        elseif (strlen($username) < 5) { $error = 'یوزەرنەیم لانیکەم ٥ پیت بێت.'; }
        elseif (!preg_match('/^[a-z0-9._-]+$/', $username)) { $error = 'یوزەرنەیم تەنها پیتی بچووکی ئینگلیزی، ژمارە، و (- _ .) بەکاربهێنە. پیتی گەورە نەنووسە.'; }
        // Validate email
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'ئیمەیڵ دروست نییە.'; }
        // Validate password
        elseif (strlen($password) < 8) { $error = 'وشەی نهێنی لانیکەم ٨ پیت بێت.'; }
        elseif (!preg_match('/[A-Z]/', $password)) { $error = 'وشەی نهێنی دەبێت لانیکەم یەک پیتی گەورە هەبێت.'; }
        elseif (!preg_match('/[0-9]/', $password)) { $error = 'وشەی نهێنی دەبێت لانیکەم یەک ژمارە هەبێت.'; }
        else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) { $error = 'ئەم یوزەرنەیمە یان ئیمەیڵە پێشتر تۆمارکراوە.'; }
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, display_name, email, password) VALUES (?, ?, ?, ?)")->execute([$username, $displayName, $email, $hash]);
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
        <form method="POST" id="register-form">
            <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
            <div class="form-group">
                <label><i class="fas fa-user"></i> ناو</label>
                <input type="text" name="display_name" class="form-control" placeholder="ناوی خۆت بنووسە" required minlength="2" value="<?= clean($_POST['display_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-at"></i> یوزەرنەیم</label>
                <input type="text" name="username" id="reg-username" class="form-control" placeholder="مشک: sardar.123" required pattern="[a-z0-9._-]+" title="تەنها پیتی بچووکی ئینگلیزی، ژمارە، و (- _ .)" value="<?= clean($_POST['username'] ?? '') ?>">
                <small class="text-gray" id="username-hint">لانیکەم ٥ پیت • تەنها پیتی بچووک، ژمارە، و (- _ .)</small>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> ئیمەیڵ</label>
                <input type="email" name="email" class="form-control" required value="<?= clean($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> وشەی نهێنی</label>
                <input type="password" name="password" id="reg-password" class="form-control" required minlength="8">
                <div id="reg-password-strength" style="margin-top:6px;font-size:0.78rem;"></div>
                <small class="text-gray">لانیکەم ٨ پیت • یەک پیتی گەورە • یەک ژمارە</small>
            </div>
            <button type="submit" class="btn btn-gold btn-lg" style="width:100%;justify-content:center;"><i class="fas fa-user-plus"></i> تۆمارکردن</button>
        </form>
        <p style="text-align:center;margin-top:22px;color:var(--gray);">هەژمارت هەیە؟ <a href="<?= SITE_URL ?>/login.php" class="text-gold">چوونەژوورەوە</a></p>
    </div>
</div>

<script>
// Username validation - force lowercase
document.getElementById('reg-username')?.addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '');
    const hint = document.getElementById('username-hint');
    if (this.value.length > 0 && this.value.length < 5) {
        hint.style.color = '#FF5252';
        hint.textContent = 'یوزەرنەیم لانیکەم ٥ پیت بێت (' + this.value.length + '/5)';
    } else if (this.value.length >= 5) {
        hint.style.color = '#4ADE80';
        hint.textContent = '✓ یوزەرنەیم گونجاوە';
    } else {
        hint.style.color = '';
        hint.textContent = 'لانیکەم ٥ پیت • تەنها پیتی بچووک، ژمارە، و (- _ .)';
    }
});

// Password strength
document.getElementById('reg-password')?.addEventListener('input', function() {
    const val = this.value;
    const el = document.getElementById('reg-password-strength');
    let strength = 0, msgs = [];
    if (val.length >= 8) strength++; else msgs.push('لانیکەم ٨ پیت');
    if (/[A-Z]/.test(val)) strength++; else msgs.push('یەک پیتی گەورە');
    if (/[0-9]/.test(val)) strength++; else msgs.push('یەک ژمارە');
    if (/[^A-Za-z0-9]/.test(val)) strength++;
    const colors = ['#FF5252','#FB923C','#F5C518','#4ADE80'];
    const labels = ['لاواز','مامناوەند','باش','بەهێز'];
    if (val.length > 0) {
        el.innerHTML = `<span style="color:${colors[strength-1] || colors[0]};">● ${labels[strength-1] || labels[0]}</span>${msgs.length ? ' <span style="color:var(--gray);">(' + msgs.join('، ') + ')</span>' : ''}`;
    } else el.innerHTML = '';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
