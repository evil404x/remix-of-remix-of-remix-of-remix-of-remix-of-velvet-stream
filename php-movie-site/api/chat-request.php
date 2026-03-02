<?php
/**
 * SHAH CINEMA - Chat Request & Mute System
 * Approval-based messaging + mute/block in chat
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_request (sender_id, receiver_id),
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_mutes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        muted_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_mute (user_id, muted_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (muted_id) REFERENCES users(id) ON DELETE CASCADE
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

    case 'send_request':
        $receiverId = (int)($data['receiver_id'] ?? 0);
        if (!$receiverId || $receiverId == $userId) {
            echo json_encode(['success' => false, 'message' => 'Invalid user']);
            exit;
        }
        
        // Check if already approved
        $check = $pdo->prepare("SELECT status FROM chat_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $check->execute([$userId, $receiverId, $receiverId, $userId]);
        $existing = $check->fetch();
        
        if ($existing && $existing['status'] === 'approved') {
            echo json_encode(['success' => true, 'already_approved' => true]);
            exit;
        }
        
        $pdo->prepare("INSERT INTO chat_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status = 'pending', created_at = NOW()")
            ->execute([$userId, $receiverId]);
        
        sendNotification($pdo, $receiverId, '💬 داواکاری چات', clean($_SESSION['username']) . ' داوای چاتی کردووە', SITE_URL . '/messages.php');
        
        echo json_encode(['success' => true]);
        break;

    case 'approve':
        $senderId = (int)($data['sender_id'] ?? 0);
        if (!$senderId) { echo json_encode(['success' => false]); exit; }
        
        $pdo->prepare("UPDATE chat_requests SET status = 'approved' WHERE sender_id = ? AND receiver_id = ?")
            ->execute([$senderId, $userId]);
        
        // Also create reverse approval
        $pdo->prepare("INSERT INTO chat_requests (sender_id, receiver_id, status) VALUES (?, ?, 'approved') ON DUPLICATE KEY UPDATE status = 'approved'")
            ->execute([$userId, $senderId]);
        
        sendNotification($pdo, $senderId, '✅ چاتت قبوڵ کرا', clean($_SESSION['username']) . ' داواکاری چاتی قبوڵ کرد', SITE_URL . '/messages.php?with=' . $userId);
        
        echo json_encode(['success' => true]);
        break;

    case 'reject':
        $senderId = (int)($data['sender_id'] ?? 0);
        if (!$senderId) { echo json_encode(['success' => false]); exit; }
        
        $pdo->prepare("UPDATE chat_requests SET status = 'rejected' WHERE sender_id = ? AND receiver_id = ?")
            ->execute([$senderId, $userId]);
        
        echo json_encode(['success' => true]);
        break;

    case 'check_status':
        $otherUserId = (int)($_GET['user_id'] ?? 0);
        if (!$otherUserId) { echo json_encode(['success' => false]); exit; }
        
        // Check if chat is approved between these two users
        $check = $pdo->prepare("SELECT status FROM chat_requests WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status = 'approved' LIMIT 1");
        $check->execute([$userId, $otherUserId, $otherUserId, $userId]);
        $approved = (bool)$check->fetch();
        
        // Check if request is pending
        $pendingCheck = $pdo->prepare("SELECT sender_id, status FROM chat_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $pendingCheck->execute([$userId, $otherUserId, $otherUserId, $userId]);
        $request = $pendingCheck->fetch();
        
        $status = 'none';
        if ($approved) $status = 'approved';
        elseif ($request && $request['status'] === 'pending') {
            $status = $request['sender_id'] == $userId ? 'sent' : 'received';
        }
        
        // Check mute status
        $muteCheck = $pdo->prepare("SELECT id FROM user_mutes WHERE user_id = ? AND muted_id = ?");
        $muteCheck->execute([$userId, $otherUserId]);
        $isMuted = (bool)$muteCheck->fetch();
        
        echo json_encode(['success' => true, 'status' => $status, 'is_approved' => $approved, 'is_muted' => $isMuted]);
        break;

    case 'pending_requests':
        $stmt = $pdo->prepare("SELECT cr.*, u.username, u.avatar FROM chat_requests cr JOIN users u ON cr.sender_id = u.id WHERE cr.receiver_id = ? AND cr.status = 'pending' ORDER BY cr.created_at DESC");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);
        break;

    case 'mute':
        $mutedId = (int)($data['muted_id'] ?? 0);
        if (!$mutedId) { echo json_encode(['success' => false]); exit; }
        
        $pdo->prepare("INSERT IGNORE INTO user_mutes (user_id, muted_id) VALUES (?, ?)")->execute([$userId, $mutedId]);
        echo json_encode(['success' => true]);
        break;

    case 'unmute':
        $mutedId = (int)($data['muted_id'] ?? 0);
        if (!$mutedId) { echo json_encode(['success' => false]); exit; }
        
        $pdo->prepare("DELETE FROM user_mutes WHERE user_id = ? AND muted_id = ?")->execute([$userId, $mutedId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
