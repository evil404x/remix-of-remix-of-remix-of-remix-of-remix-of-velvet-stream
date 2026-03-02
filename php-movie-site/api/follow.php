<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS follows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        follower_id INT NOT NULL,
        following_id INT NOT NULL,
        status ENUM('active','pending') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_follow (follower_id, following_id),
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_privacy (
        user_id INT PRIMARY KEY,
        private_account TINYINT(1) DEFAULT 0,
        hide_from_search TINYINT(1) DEFAULT 0,
        disable_follow TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'toggle':
        $targetId = (int)($data['user_id'] ?? 0);
        if (!$targetId || $targetId === $userId) {
            echo json_encode(['success' => false, 'message' => 'Invalid user']);
            exit;
        }

        // Check if target has disabled follow
        $privacy = $pdo->prepare("SELECT * FROM user_privacy WHERE user_id = ?");
        $privacy->execute([$targetId]);
        $priv = $privacy->fetch();

        if ($priv && $priv['disable_follow']) {
            echo json_encode(['success' => false, 'message' => 'فۆڵۆکردن لەلایەن ئەم بەکارهێنەرە ناچالاک کراوە']);
            exit;
        }

        // Check if already following
        $check = $pdo->prepare("SELECT id, status FROM follows WHERE follower_id = ? AND following_id = ?");
        $check->execute([$userId, $targetId]);
        $existing = $check->fetch();

        if ($existing) {
            // Unfollow
            $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?")->execute([$userId, $targetId]);
            echo json_encode(['success' => true, 'is_following' => false, 'status' => 'unfollowed']);
        } else {
            // Follow - check if private account
            $status = ($priv && $priv['private_account']) ? 'pending' : 'active';
            $pdo->prepare("INSERT INTO follows (follower_id, following_id, status) VALUES (?, ?, ?)")
                ->execute([$userId, $targetId, $status]);

            // Send notification
            $followerName = $_SESSION['username'] ?? 'کەسێک';
            if ($status === 'pending') {
                sendNotification($pdo, $targetId, 'داواکاری فۆڵۆ', "$followerName داوای فۆڵۆکردنی تۆی کردووە", SITE_URL . '/profile.php');
            } else {
                sendNotification($pdo, $targetId, 'فۆڵۆوەری نوێ', "$followerName فۆڵۆی تۆی کردووە", SITE_URL . '/profile.php');
            }

            echo json_encode(['success' => true, 'is_following' => true, 'status' => $status]);
        }
        break;

    case 'accept':
        $followerId = (int)($data['follower_id'] ?? 0);
        $pdo->prepare("UPDATE follows SET status = 'active' WHERE follower_id = ? AND following_id = ? AND status = 'pending'")
            ->execute([$followerId, $userId]);
        sendNotification($pdo, $followerId, 'فۆڵۆ قبوڵ کرا', ($_SESSION['username'] ?? 'کەسێک') . ' داواکاری فۆڵۆکەت قبوڵ کرد', SITE_URL . '/profile.php');
        echo json_encode(['success' => true]);
        break;

    case 'reject':
        $followerId = (int)($data['follower_id'] ?? 0);
        $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ? AND status = 'pending'")
            ->execute([$followerId, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'status':
        $targetId = (int)($data['user_id'] ?? $_GET['user_id'] ?? 0);
        $check = $pdo->prepare("SELECT status FROM follows WHERE follower_id = ? AND following_id = ?");
        $check->execute([$userId, $targetId]);
        $row = $check->fetch();

        $followersCount = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ? AND status = 'active'");
        $followersCount->execute([$targetId]);

        $followingCount = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND status = 'active'");
        $followingCount->execute([$targetId]);

        echo json_encode([
            'success' => true,
            'is_following' => (bool)$row,
            'status' => $row ? $row['status'] : 'none',
            'followers_count' => (int)$followersCount->fetchColumn(),
            'following_count' => (int)$followingCount->fetchColumn()
        ]);
        break;

    case 'followers':
        $targetId = (int)($data['user_id'] ?? $_GET['user_id'] ?? $userId);
        // No privacy restriction on followers list
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.avatar, f.created_at FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ? AND f.status = 'active' ORDER BY f.created_at DESC");
        $stmt->execute([$targetId]);
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);
        break;

    case 'following':
        $targetId = (int)($data['user_id'] ?? $_GET['user_id'] ?? $userId);
        // Check privacy: hide_following
        if ($targetId !== $userId) {
            try {
                $privCheck = $pdo->prepare("SELECT hide_following FROM user_privacy WHERE user_id = ?");
                $privCheck->execute([$targetId]);
                $privRow = $privCheck->fetch();
                if ($privRow && (int)$privRow['hide_following']) {
                    echo json_encode(['success' => true, 'users' => [], 'hidden' => true]);
                    break;
                }
            } catch (Exception $e) {}
        }
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.avatar, f.created_at FROM follows f JOIN users u ON f.following_id = u.id WHERE f.follower_id = ? AND f.status = 'active' ORDER BY f.created_at DESC");
        $stmt->execute([$targetId]);
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);
        break;

    case 'pending':
        // Get pending follow requests for current user
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.avatar, f.created_at FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ? AND f.status = 'pending' ORDER BY f.created_at DESC");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);
        break;

    case 'mark_followers_seen':
        // Mark all current followers as "seen" by updating the user's followers_seen_at timestamp
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN followers_seen_at TIMESTAMP NULL DEFAULT NULL");
        } catch (Exception $e) {}
        // Update seen timestamp to NOW so all current followers are considered "seen"
        $pdo->prepare("UPDATE users SET followers_seen_at = NOW() WHERE id = ?")->execute([$userId]);
        
        // Also accept all pending follow requests automatically when marking as read
        $pdo->prepare("UPDATE follows SET status = 'active' WHERE following_id = ? AND status = 'pending'")->execute([$userId]);
        
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
