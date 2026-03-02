<?php
/**
 * Run this file ONCE to create/reset the admin user with correct password hash.
 * Delete this file after running it!
 * 
 * Visit: http://localhost/php-movie-site/create-admin.php
 */

require_once __DIR__ . '/config.php';

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Delete existing admin
$pdo->prepare("DELETE FROM users WHERE username = 'admin'")->execute();

// Insert with correct hash
$stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
$stmt->execute(['admin', 'admin@cinegold.com', $hash]);

echo '<div style="text-align:center;padding:50px;font-family:sans-serif;color:#FFD700;background:#0a0a0a;min-height:100vh;">
    <h1>✅ Admin User Created!</h1>
    <p>Username: <b>admin</b></p>
    <p>Password: <b>admin123</b></p>
    <p style="color:#FF4444;margin-top:30px;">⚠️ DELETE this file (create-admin.php) after using it!</p>
    <p style="margin-top:20px;"><a href="' . SITE_URL . '/login.php" style="color:#FFD700;">Go to Login →</a></p>
</div>';
