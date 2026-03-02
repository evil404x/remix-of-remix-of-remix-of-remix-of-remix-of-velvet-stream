<?php
/**
 * Block & Report Users API
 */
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

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL,
        blocked_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_id, blocked_id),
        FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        reported_id INT NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
        admin_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

switch ($action) {

    case 'block':
        $targetId = (int)($data['user_id'] ?? 0);
        if (!$targetId || $targetId == $userId) {
            echo json_encode(['success' => false, 'message' => 'Invalid user']);
            exit;
        }
        
        try {
            $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)")
                ->execute([$userId, $targetId]);
            // Also remove any follow relationships
            $pdo->prepare("DELETE FROM follows WHERE (follower_id = ? AND following_id = ?) OR (follower_id = ? AND following_id = ?)")
                ->execute([$userId, $targetId, $targetId, $userId]);
            echo json_encode(['success' => true, 'message' => 'بلۆک کرا']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'unblock':
        $targetId = (int)($data['user_id'] ?? 0);
        if (!$targetId) {
            echo json_encode(['success' => false, 'message' => 'Invalid user']);
            exit;
        }
        
        $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?")
            ->execute([$userId, $targetId]);
        echo json_encode(['success' => true, 'message' => 'بلۆک لابرا']);
        break;

    case 'check':
        $targetId = (int)($_GET['user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$userId, $targetId]);
        $isBlocked = (bool)$stmt->fetch();
        
        // Also check if we are blocked by them
        $stmt2 = $pdo->prepare("SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt2->execute([$targetId, $userId]);
        $blockedByThem = (bool)$stmt2->fetch();
        
        echo json_encode(['success' => true, 'is_blocked' => $isBlocked, 'blocked_by_them' => $blockedByThem]);
        break;

    case 'report_user':
        $targetId = (int)($data['user_id'] ?? 0);
        $reason = clean($data['reason'] ?? '');
        if (!$targetId || !$reason) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }
        
        try {
            $pdo->prepare("INSERT INTO user_reports (reporter_id, reported_id, reason) VALUES (?, ?, ?)")
                ->execute([$userId, $targetId, $reason]);
            echo json_encode(['success' => true, 'message' => 'ڕاپۆرت نێردرا']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'blocked_list':
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.avatar, ub.created_at 
            FROM user_blocks ub JOIN users u ON ub.blocked_id = u.id 
            WHERE ub.blocker_id = ? ORDER BY ub.created_at DESC");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'blocked' => $stmt->fetchAll()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
