<?php
/**
 * CineGold - Configuration File
 * Dynamic, Auto-detecting Configuration with Security Hardening
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'cinegold_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ============ ADMIN FOLDER NAME (auto-detect) ============
define('ADMIN_DIR', is_dir(__DIR__ . '/ShahCinema_Vault_2026') ? 'ShahCinema_Vault_2026' : 'admin');


// Dynamic Site URL Detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$siteRoot = $scriptDir;
$subfolders = [ADMIN_DIR, 'admin', 'api', 'includes'];
foreach ($subfolders as $sub) {
    if (preg_match('#/' . preg_quote($sub, '#') . '(/|$)#', $siteRoot)) {
        $siteRoot = preg_replace('#/' . preg_quote($sub, '#') . '(/.*)?$#', '', $siteRoot);
        break;
    }
}
$siteRoot = rtrim($siteRoot, '/');
define('SITE_URL', $protocol . '://' . $host . $siteRoot);
define('SITE_NAME', 'SHAH CINEMA');
define('UPLOAD_DIR', dirname(__FILE__) . '/uploads/');
define('ENCRYPTION_KEY', 'CineG0ld_S3cur3_K3y_2024!@#');

// PDO Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;color:#FFD700;background:#0a0a0a;min-height:100vh;">
        <h1>⚠️ Database Connection Error</h1>
        <p>Please make sure MySQL is running in XAMPP and the database <b>cinegold_db</b> exists.</p>
        <p>Run <code>setup.sql</code> in phpMyAdmin first.</p>
        <small>' . $e->getMessage() . '</small>
    </div>');
}

// ============ AUTO-CREATE ip_bans TABLE ============
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ip_bans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        ban_until DATETIME NOT NULL,
        reason VARCHAR(255) DEFAULT 'Auto-ban: suspicious activity',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_ip (ip_address)
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

// ============ AUTO-IP BAN SYSTEM ============
function isIPBanned(PDO $pdo): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    try {
        $stmt = $pdo->prepare("SELECT ban_until FROM ip_bans WHERE ip_address = ? AND ban_until > NOW()");
        $stmt->execute([$ip]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function checkAndBanIP(PDO $pdo): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    try {
        // Count suspicious requests in last hour
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$ip]);
        $count = (int)$stmt->fetchColumn();
        
        if ($count >= 10) {
            // Ban for 24 hours
            $stmt = $pdo->prepare("INSERT INTO ip_bans (ip_address, ban_until, reason) VALUES (?, DATE_ADD(NOW(), INTERVAL 24 HOUR), 'Auto-ban: 10+ suspicious requests in 1 hour') ON DUPLICATE KEY UPDATE ban_until = DATE_ADD(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute([$ip]);
        }
    } catch (Exception $e) {}
}

// Check if current IP is banned
if (isIPBanned($pdo)) {
    http_response_code(403);
    die('<div style="text-align:center;padding:100px;font-family:sans-serif;color:#FFD700;background:#0a0a0a;min-height:100vh;">
        <h1>🚫 ئایپی ئەدرەسەکەت بلۆک کراوە</h1>
        <p style="color:#aaa;">بەهۆی چالاکیی گومانلێکراو، ئایپی ئەدرەسەکەت بۆ ماوەی ٢٤ کاتژمێر بلۆک کراوە.</p>
        <p style="color:#666;font-size:0.85rem;">ئەگەر هەڵەیەکە، تکایە پەیوەندی بکە بە بەڕێوەبەرەوە.</p>
    </div>');
}

// Security: CSRF Token
function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Security: Sanitize Input
function clean(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Security: Log suspicious activity
function logAttack(PDO $pdo, string $type): void {
    $stmt = $pdo->prepare("INSERT INTO security_logs (ip_address, user_agent, request_uri, attack_type) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $_SERVER['REQUEST_URI'] ?? 'unknown',
        $type
    ]);
}

// Basic XSS/SQLi detection
function flattenInput(array $data): array {
    $flat = [];
    foreach ($data as $value) {
        if (is_array($value)) {
            $flat = array_merge($flat, flattenInput($value));
        } else {
            $flat[] = (string)$value;
        }
    }
    return $flat;
}

function detectAttack(): ?string {
    $allInputs = array_merge(flattenInput($_GET), flattenInput($_POST));
    $input = implode(' ', $allInputs);
    
    // Also check URL for SQL injection patterns
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $fullInput = $input . ' ' . urldecode($uri);
    
    $patterns = [
        '/(<script|javascript:|on\w+\s*=)/i' => 'XSS',
        '/(union\s+select|drop\s+table|insert\s+into|delete\s+from|select\s+\*|0x[0-9a-f]+|benchmark\s*\(|sleep\s*\(|load_file|into\s+outfile)/i' => 'SQL Injection',
        '/(\.\.\/|\.\.\\\\|%2e%2e|\/etc\/passwd|\/proc\/self|cmd\.exe|powershell)/i' => 'Path Traversal',
        '/(\'\s*or\s*[\'\"0-9]|[\'\"]\s*;\s*--|[\'\"]\s*;\s*drop|admin\s*\'--)/i' => 'SQL Injection',
    ];
    foreach ($patterns as $pattern => $type) {
        if (preg_match($pattern, $fullInput)) {
            return $type;
        }
    }
    return null;
}

// Run attack detection
$attackType = detectAttack();
if ($attackType) {
    logAttack($pdo, $attackType);
    checkAndBanIP($pdo); // Check if should auto-ban
    http_response_code(403);
    // Redirect to 404 for injection attempts
    header('Location: ' . SITE_URL . '/404.php');
    exit;
}

// ============ SECURE FILE UPLOAD HELPER ============
function secureUpload(array $file, string $targetDir, array $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp']): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    
    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) return null;
    
    // Verify it's actually an image using getimagesize
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) return null;
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) return null;
    
    // Generate random hash filename
    $ext = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg'
    };
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    
    // Ensure target directory exists
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $targetDir . '/' . $filename)) {
        return $filename;
    }
    return null;
}

// Auth helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

// Admin URL helper
function adminUrl(string $path = ''): string {
    return SITE_URL . '/' . ADMIN_DIR . '/' . ltrim($path, '/');
}

// Maintenance mode check
function checkMaintenance(PDO $pdo): void {
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptPath, '/' . ADMIN_DIR . '/') !== false || strpos($scriptPath, 'login.php') !== false || strpos($scriptPath, 'maintenance.php') !== false) {
        return;
    }
    if (isAdmin()) return;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $mode = $stmt->fetchColumn();
        if ($mode === '1') {
            $msgStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_message'");
            $msgStmt->execute();
            $msg = $msgStmt->fetchColumn() ?: '';
            header('Location: ' . SITE_URL . '/maintenance.php?msg=' . urlencode($msg));
            exit;
        }
    } catch (Exception $e) {}
}

checkMaintenance($pdo);
