<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = '';
$step = 'email'; // email, code, reset

// Ensure password_resets table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

// Ensure smtp_settings table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS smtp_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        smtp_host VARCHAR(255) DEFAULT '',
        smtp_port INT DEFAULT 587,
        smtp_email VARCHAR(255) DEFAULT '',
        smtp_password VARCHAR(255) DEFAULT '',
        smtp_from_name VARCHAR(255) DEFAULT 'CineGold',
        smtp_encryption ENUM('tls','ssl','none') DEFAULT 'tls',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    // Insert default if not exists
    $check = $pdo->query("SELECT COUNT(*) FROM smtp_settings")->fetchColumn();
    if ($check == 0) {
        $pdo->exec("INSERT INTO smtp_settings (smtp_host) VALUES ('')");
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_code') {
        $email = clean($_POST['email'] ?? '');
        $user = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $user->execute([$email]);
        $user = $user->fetch();
        
        if (!$user) {
            $error = 'ئەم ئیمەیڵە تۆمارنەکراوە.';
        } else {
            // Generate 6-digit code
            $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            // Delete old tokens
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
            
            // Save token
            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$user['id'], password_hash($code, PASSWORD_DEFAULT), $expires]);
            
            // Get SMTP settings
            $smtp = $pdo->query("SELECT * FROM smtp_settings LIMIT 1")->fetch();
            
            if ($smtp && !empty($smtp['smtp_host']) && !empty($smtp['smtp_email'])) {
                // Send email via SMTP
                $subject = SITE_NAME . ' - کۆدی ڕیسێتکردن';
                $body = "
                <div style='font-family:Tahoma,sans-serif;max-width:500px;margin:0 auto;background:#0a0a0a;border:1px solid #222;border-radius:16px;padding:40px;direction:rtl;text-align:center;'>
                    <h2 style='color:#F5C518;margin-bottom:10px;'>👑 " . SITE_NAME . "</h2>
                    <p style='color:#ccc;font-size:14px;'>کۆدی ڕیسێتکردنی وشەی نهێنی</p>
                    <div style='background:#111;border:2px solid #F5C518;border-radius:12px;padding:20px;margin:20px 0;'>
                        <span style='font-size:32px;font-weight:bold;color:#F5C518;letter-spacing:8px;'>{$code}</span>
                    </div>
                    <p style='color:#888;font-size:12px;'>ئەم کۆدە لە ٣٠ خولەک دوای ئێستا بەسەر دەچێت.</p>
                </div>";
                
                $sent = sendSmtpEmail($smtp, $user['email'], $subject, $body);
                if ($sent) {
                    $_SESSION['reset_email'] = $email;
                    $success = 'کۆدی ڕیسێتکردن نێردرا بۆ ئیمەیڵەکەت!';
                    $step = 'code';
                } else {
                    $error = 'کێشە لە ناردنی ئیمەیڵ. تکایە ڕێکخستنەکانی SMTP لە ئادمین پانێڵ بپشکنە.';
                }
            } else {
                $error = 'سیستەمی ناردنی ئیمەیڵ ڕێکنەخراوە. پەیوەندی بە ئادمین بکە.';
            }
        }
    }
    
    if ($action === 'verify_code') {
        $email = $_SESSION['reset_email'] ?? '';
        $code = clean($_POST['code'] ?? '');
        
        $user = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $user->execute([$email]);
        $user = $user->fetch();
        
        if ($user) {
            $reset = $pdo->prepare("SELECT * FROM password_resets WHERE user_id = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $reset->execute([$user['id']]);
            $reset = $reset->fetch();
            
            if ($reset && password_verify($code, $reset['token'])) {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_token_id'] = $reset['id'];
                $step = 'reset';
                $success = 'کۆدەکە ڕاستە! وشەی نهێنی نوێ بنوسە.';
            } else {
                $error = 'کۆدەکە هەڵەیە یان بەسەرچووە.';
                $step = 'code';
            }
        }
    }
    
    if ($action === 'reset_password') {
        $userId = $_SESSION['reset_user_id'] ?? 0;
        $tokenId = $_SESSION['reset_token_id'] ?? 0;
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (!$userId) { $error = 'تکایە دووبارە هەوڵ بدەرەوە.'; }
        elseif (strlen($newPass) < 6) { $error = 'وشەی نهێنی لانیکەم ٦ پیت بێت.'; }
        elseif ($newPass !== $confirmPass) { $error = 'وشەی نهێنی وەک یەک نییە.'; }
        else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$tokenId]);
            unset($_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['reset_token_id']);
            $success = 'وشەی نهێنی بە سەرکەوتوویی گۆڕدرا! ئێستا بتوانیت بچیتە ژوورەوە.';
            $step = 'done';
        }
    }
}

// Restore step from session
if (isset($_SESSION['reset_email']) && $step === 'email' && empty($error)) {
    $step = 'code';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card glass-strong animate__animated animate__fadeInUp" style="max-width:440px;">
        <h2><span>Cine</span>Gold</h2>
        <h3 style="text-align:center;margin-bottom:28px;color:var(--gray-light);font-weight:400;font-size:1rem;">
            <i class="fas fa-key"></i> ڕیسێتکردنی وشەی نهێنی
        </h3>

        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>

        <?php if ($step === 'email'): ?>
        <form method="POST">
            <input type="hidden" name="action" value="send_code">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> ئیمەیڵ</label>
                <input type="email" name="email" class="form-control" placeholder="ئیمەیڵەکەت بنوسە" required>
            </div>
            <button type="submit" class="btn btn-gold btn-lg" style="width:100%;justify-content:center;">
                <i class="fas fa-paper-plane"></i> ناردنی کۆد
            </button>
        </form>
        <?php elseif ($step === 'code'): ?>
        <form method="POST">
            <input type="hidden" name="action" value="verify_code">
            <div class="form-group">
                <label><i class="fas fa-shield-alt"></i> کۆدی ڕیسێتکردن (٦ ژمارە)</label>
                <input type="text" name="code" class="form-control" placeholder="______" required maxlength="6" 
                       style="text-align:center;font-size:1.5rem;letter-spacing:8px;font-weight:700;">
            </div>
            <button type="submit" class="btn btn-gold btn-lg" style="width:100%;justify-content:center;">
                <i class="fas fa-check"></i> پشتڕاستکردنەوە
            </button>
        </form>
        <?php elseif ($step === 'reset'): ?>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <div class="form-group">
                <label><i class="fas fa-lock"></i> وشەی نهێنی نوێ</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> دووبارەکردنەوە</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-gold btn-lg" style="width:100%;justify-content:center;">
                <i class="fas fa-save"></i> گۆڕینی وشەی نهێنی
            </button>
        </form>
        <?php elseif ($step === 'done'): ?>
        <div style="text-align:center;padding:20px;">
            <i class="fas fa-check-circle" style="font-size:3rem;color:var(--green);margin-bottom:16px;"></i>
            <p style="margin-bottom:20px;">ئێستا بتوانیت بچیتە ژوورەوە:</p>
            <a href="<?= SITE_URL ?>/login.php" class="btn btn-gold btn-lg" style="justify-content:center;">
                <i class="fas fa-sign-in-alt"></i> چوونەژوورەوە
            </a>
        </div>
        <?php endif; ?>

        <p style="text-align:center;margin-top:22px;color:var(--gray);">
            <a href="<?= SITE_URL ?>/login.php" class="text-gold"><i class="fas fa-arrow-right"></i> گەڕانەوە بۆ چوونەژوورەوە</a>
        </p>
    </div>
</div>

<?php
// SMTP email sender function
function sendSmtpEmail($smtp, $to, $subject, $body) {
    try {
        $host = $smtp['smtp_host'];
        $port = (int)$smtp['smtp_port'];
        $user = $smtp['smtp_email'];
        $pass = $smtp['smtp_password'];
        $fromName = $smtp['smtp_from_name'] ?: 'CineGold';
        $encryption = $smtp['smtp_encryption'] ?? 'tls';
        
        // Use PHP mail() as fallback, or socket-based SMTP
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$user}>\r\n";
        $headers .= "Reply-To: {$user}\r\n";
        
        // Try socket SMTP
        $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        if (!$socket) {
            // Fallback to PHP mail
            return @mail($to, $subject, $body, $headers);
        }
        
        $response = fgets($socket, 515);
        
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        fgets($socket, 515);
        while (substr(fgets($socket, 515), 3, 1) === '-') {} // Read all EHLO responses
        
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            fgets($socket, 515);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO " . gethostname() . "\r\n");
            fgets($socket, 515);
            while (substr(fgets($socket, 515), 3, 1) === '-') {}
        }
        
        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket, 515);
        fputs($socket, base64_encode($user) . "\r\n");
        fgets($socket, 515);
        fputs($socket, base64_encode($pass) . "\r\n");
        $authResponse = fgets($socket, 515);
        if (substr($authResponse, 0, 3) !== '235') {
            fclose($socket);
            return false;
        }
        
        fputs($socket, "MAIL FROM:<{$user}>\r\n");
        fgets($socket, 515);
        fputs($socket, "RCPT TO:<{$to}>\r\n");
        fgets($socket, 515);
        fputs($socket, "DATA\r\n");
        fgets($socket, 515);
        
        $message = "From: {$fromName} <{$user}>\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $body . "\r\n.\r\n";
        
        fputs($socket, $message);
        fgets($socket, 515);
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>