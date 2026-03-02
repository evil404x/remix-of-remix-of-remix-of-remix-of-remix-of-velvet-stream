<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';
$error = '';

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
    $check = $pdo->query("SELECT COUNT(*) FROM smtp_settings")->fetchColumn();
    if ($check == 0) {
        $pdo->exec("INSERT INTO smtp_settings (smtp_host) VALUES ('')");
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf'] ?? '')) {
    $host = clean($_POST['smtp_host'] ?? '');
    $port = (int)($_POST['smtp_port'] ?? 587);
    $email = clean($_POST['smtp_email'] ?? '');
    $password = $_POST['smtp_password'] ?? '';
    $fromName = clean($_POST['smtp_from_name'] ?? 'CineGold');
    $encryption = in_array($_POST['smtp_encryption'] ?? '', ['tls','ssl','none']) ? $_POST['smtp_encryption'] : 'tls';
    
    $pdo->prepare("UPDATE smtp_settings SET smtp_host=?, smtp_port=?, smtp_email=?, smtp_password=?, smtp_from_name=?, smtp_encryption=? WHERE id=1")
        ->execute([$host, $port, $email, $password, $fromName, $encryption]);
    $success = 'ڕێکخستنەکانی SMTP پاشەکەوت کران!';
}

$smtp = $pdo->query("SELECT * FROM smtp_settings LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ڕێکخستنەکانی SMTP - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>window.SITE_URL='<?= SITE_URL ?>';</script>
</head>
<body class="rtl">
<div class="admin-layout">
    <?php include __DIR__.'/sidebar.php'; ?>
    <button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>

    <main class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-envelope text-gold"></i> ڕێکخستنەکانی ئیمەیڵ (SMTP)</h1>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <div class="panel-card glass">
            <h3><i class="fas fa-server"></i> ڕێکخستنەکانی سێرڤەری ئیمەیڵ</h3>
            <p style="color:var(--gray);font-size:0.85rem;margin-bottom:20px;">
                ئەم ڕێکخستنانە بۆ ناردنی کۆدی ڕیسێتکردنی وشەی نهێنی بەکاردێت. بۆ Gmail: smtp.gmail.com / Port 587 / TLS
            </p>
            <form method="POST" class="admin-form">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-server"></i> SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= clean($smtp['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= $smtp['smtp_port'] ?? 587 ?>" placeholder="587">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> ئیمەیڵ</label>
                        <input type="email" name="smtp_email" class="form-control" value="<?= clean($smtp['smtp_email'] ?? '') ?>" placeholder="noreply@yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> وشەی نهێنی / App Password</label>
                        <input type="password" name="smtp_password" class="form-control" value="<?= $smtp['smtp_password'] ?? '' ?>" placeholder="••••••••">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> ناوی نێرەر</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?= clean($smtp['smtp_from_name'] ?? 'CineGold') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-shield-alt"></i> شفرکردن (Encryption)</label>
                        <select name="smtp_encryption" class="form-control">
                            <option value="tls" <?= ($smtp['smtp_encryption'] ?? 'tls')==='tls'?'selected':'' ?>>TLS</option>
                            <option value="ssl" <?= ($smtp['smtp_encryption'] ?? '')==='ssl'?'selected':'' ?>>SSL</option>
                            <option value="none" <?= ($smtp['smtp_encryption'] ?? '')==='none'?'selected':'' ?>>None</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> پاشەکەوتکردن</button>
            </form>
        </div>

        <div class="panel-card glass">
            <h3><i class="fas fa-info-circle"></i> ڕێنمایی</h3>
            <div style="color:var(--gray-light);font-size:0.88rem;line-height:2;">
                <p><strong class="text-gold">Gmail:</strong> smtp.gmail.com / Port: 587 / TLS</p>
                <p>بۆ Gmail دەبێت "App Password" دروست بکەیت لە Google Account Settings > Security > 2-Step Verification > App Passwords</p>
                <p style="margin-top:10px;"><strong class="text-gold">Outlook:</strong> smtp-mail.outlook.com / Port: 587 / TLS</p>
                <p style="margin-top:10px;"><strong class="text-gold">Yahoo:</strong> smtp.mail.yahoo.com / Port: 465 / SSL</p>
            </div>
        </div>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>