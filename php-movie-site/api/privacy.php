<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? $_GET['action'] ?? '';

// Auto-create/update table with new columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_privacy (
        user_id INT PRIMARY KEY,
        private_account TINYINT(1) DEFAULT 0,
        hide_from_search TINYINT(1) DEFAULT 0,
        disable_follow TINYINT(1) DEFAULT 0,
        hide_following TINYINT(1) DEFAULT 0,
        hide_points TINYINT(1) DEFAULT 0,
        chat_request_enabled TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    
    try { $pdo->exec("ALTER TABLE user_privacy ADD COLUMN hide_following TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE user_privacy ADD COLUMN hide_points TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE user_privacy ADD COLUMN chat_request_enabled TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
} catch (Exception $e) {}

switch ($action) {

    case 'get':
        $stmt = $pdo->prepare("SELECT * FROM user_privacy WHERE user_id = ?");
        $stmt->execute([$userId]);
        $privacy = $stmt->fetch();
        if (!$privacy) {
            $privacy = ['user_id' => $userId, 'private_account' => 0, 'hide_from_search' => 0, 'disable_follow' => 0, 'hide_following' => 0, 'hide_points' => 0, 'chat_request_enabled' => 0];
        }
        echo json_encode(['success' => true, 'privacy' => $privacy, 'settings' => $privacy]);
        break;

    case 'update':
        $privateAccount = (int)($data['private_account'] ?? 0);
        $hideFromSearch = (int)($data['hide_from_search'] ?? 0);
        $disableFollow = (int)($data['disable_follow'] ?? 0);
        $hideFollowing = (int)($data['hide_following'] ?? 0);
        $hidePoints = (int)($data['hide_points'] ?? 0);
        $chatRequestEnabled = (int)($data['chat_request_enabled'] ?? 0);

        $pdo->prepare("INSERT INTO user_privacy (user_id, private_account, hide_from_search, disable_follow, hide_following, hide_points, chat_request_enabled) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE private_account = ?, hide_from_search = ?, disable_follow = ?, hide_following = ?, hide_points = ?, chat_request_enabled = ?")
            ->execute([$userId, $privateAccount, $hideFromSearch, $disableFollow, $hideFollowing, $hidePoints, $chatRequestEnabled, $privateAccount, $hideFromSearch, $disableFollow, $hideFollowing, $hidePoints, $chatRequestEnabled]);

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
