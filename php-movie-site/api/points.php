<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT DEFAULT 0,
        vip_until DATETIME DEFAULT NULL,
        UNIQUE KEY unique_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        activity_type ENUM('watch','invite','comment','admin_adjust') NOT NULL,
        points_earned INT NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$data = json_decode(file_get_contents('php://input'), true) ?? [];
if (isset($data['action'])) $action = $data['action'];

switch ($action) {

    case 'get':
        if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'login_required']); exit; }
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT * FROM user_points WHERE user_id = ?");
        $stmt->execute([$userId]);
        $points = $stmt->fetch();
        if (!$points) $points = ['user_id' => $userId, 'points' => 0, 'vip_until' => null];
        $isVip = $points['vip_until'] && strtotime($points['vip_until']) > time();
        echo json_encode(['success' => true, 'points' => (int)$points['points'], 'is_vip' => $isVip, 'vip_until' => $points['vip_until']]);
        break;

    case 'history':
        if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'login_required']); exit; }
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT * FROM point_activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'activities' => $stmt->fetchAll()]);
        break;

    case 'award':
        // Called internally or via AJAX for watch/comment/invite
        if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'login_required']); exit; }
        $userId = $_SESSION['user_id'];
        $type = clean($data['type'] ?? '');
        
        // Load dynamic point settings from DB
        $pointsMap = ['watch' => 5, 'invite' => 10, 'comment' => 2];
        $vipThreshold = 500;
        try {
            $sStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $sStmt->execute(['points_watch']); $pw = $sStmt->fetchColumn();
            if ($pw !== false) $pointsMap['watch'] = (int)$pw;
            
            $sStmt->execute(['points_invite']); $pi = $sStmt->fetchColumn();
            if ($pi !== false) $pointsMap['invite'] = (int)$pi;
            
            $sStmt->execute(['points_comment']); $pc = $sStmt->fetchColumn();
            if ($pc !== false) $pointsMap['comment'] = (int)$pc;
            
            $sStmt->execute(['vip_threshold']); $vt = $sStmt->fetchColumn();
            if ($vt !== false) $vipThreshold = (int)$vt;
        } catch (Exception $e) {}
        
        $descMap = ['watch' => 'سەیرکردنی فیلم', 'invite' => 'بانگهێشتکردنی هاوڕێ', 'comment' => 'نووسینی بۆچوون'];
        
        if (!isset($pointsMap[$type])) { echo json_encode(['success' => false, 'message' => 'Invalid type']); exit; }
        
        // Rate limit: max 1 watch point per movie per day, 1 comment point per movie per hour
        if ($type === 'watch') {
            $movieId = (int)($data['movie_id'] ?? 0);
            $check = $pdo->prepare("SELECT id FROM point_activities WHERE user_id = ? AND activity_type = 'watch' AND description LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $check->execute([$userId, "%movie:$movieId%"]);
            if ($check->fetch()) { echo json_encode(['success' => true, 'already_awarded' => true]); exit; }
            $desc = $descMap[$type] . " (movie:$movieId)";
        } elseif ($type === 'comment') {
            $movieId = (int)($data['movie_id'] ?? 0);
            $check = $pdo->prepare("SELECT id FROM point_activities WHERE user_id = ? AND activity_type = 'comment' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $check->execute([$userId]);
            if ($check->fetch()) { echo json_encode(['success' => true, 'already_awarded' => true]); exit; }
            $desc = $descMap[$type] . " (movie:$movieId)";
        } else {
            $desc = $descMap[$type];
        }
        
        $earned = $pointsMap[$type];
        
        // Insert/update points
        $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, ?) ON DUPLICATE KEY UPDATE points = points + ?")->execute([$userId, $earned, $earned]);
        $pdo->prepare("INSERT INTO point_activities (user_id, activity_type, points_earned, description) VALUES (?, ?, ?, ?)")->execute([$userId, $type, $earned, $desc]);
        
        // Check VIP threshold (dynamic)
        $stmt = $pdo->prepare("SELECT points, vip_until FROM user_points WHERE user_id = ?");
        $stmt->execute([$userId]);
        $current = $stmt->fetch();
        $isVip = $current['vip_until'] && strtotime($current['vip_until']) > time();
        
        if ($current['points'] >= $vipThreshold && !$isVip) {
            $vipUntil = date('Y-m-d H:i:s', strtotime('+30 days'));
            $pdo->prepare("UPDATE user_points SET vip_until = ? WHERE user_id = ?")->execute([$vipUntil, $userId]);
            sendNotification($pdo, $userId, '🎉 VIP بوویت!', "پیرۆزە! گەیشتیتە {$vipThreshold} خاڵ و ئەکاونتەکەت بووە VIP بۆ ٣٠ ڕۆژ.", SITE_URL . '/profile.php');
        }
        
        echo json_encode(['success' => true, 'points_earned' => $earned, 'total_points' => (int)$current['points']]);
        break;

    case 'admin_adjust':
        if (!isAdmin()) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
        $targetUserId = (int)($data['user_id'] ?? 0);
        $adjustPoints = (int)($data['points'] ?? 0);
        if (!$targetUserId) { echo json_encode(['success' => false, 'message' => 'Missing user_id']); exit; }
        
        $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, ?) ON DUPLICATE KEY UPDATE points = points + ?")->execute([$targetUserId, max(0, $adjustPoints), $adjustPoints]);
        $pdo->prepare("INSERT INTO point_activities (user_id, activity_type, points_earned, description) VALUES (?, 'admin_adjust', ?, ?)")->execute([$targetUserId, $adjustPoints, 'دەستکاری ئادمین']);
        
        // Prevent negative
        $pdo->prepare("UPDATE user_points SET points = GREATEST(points, 0) WHERE user_id = ?")->execute([$targetUserId]);
        
        echo json_encode(['success' => true]);
        break;

    case 'leaderboard':
        $stmt = $pdo->query("SELECT up.*, u.username, u.avatar FROM user_points up JOIN users u ON up.user_id = u.id ORDER BY up.points DESC LIMIT 20");
        echo json_encode(['success' => true, 'leaderboard' => $stmt->fetchAll()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
