<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_request TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Add is_request column if not exists
    try { $pdo->exec("ALTER TABLE direct_messages ADD COLUMN is_request TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}

    // Add last_seen to users if not exists
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (Exception $e) {}
} catch (Exception $e) {}

// Update last_seen
$pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? $_GET['action'] ?? '';

// Helper: check if receiver has chat_request enabled in privacy
function isChatRequestEnabled(PDO $pdo, int $receiverId): bool {
    try {
        $stmt = $pdo->prepare("SELECT chat_request_enabled FROM user_privacy WHERE user_id = ?");
        $stmt->execute([$receiverId]);
        $row = $stmt->fetch();
        // If no privacy row or column doesn't exist, default to false (open chat)
        if (!$row || !isset($row['chat_request_enabled'])) return false;
        return (bool)$row['chat_request_enabled'];
    } catch (Exception $e) {
        return false;
    }
}

// Helper: check if chat is approved between two users
function isChatApproved(PDO $pdo, int $userA, int $userB): bool {
    // Check mutual follow (either direction active)
    $stmt = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$userB, $userA]); // B follows A
    if ($stmt->fetch()) return true;
    
    // Check chat_requests approved
    $stmt2 = $pdo->prepare("SELECT status FROM chat_requests WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status = 'approved' LIMIT 1");
    $stmt2->execute([$userA, $userB, $userB, $userA]);
    if ($stmt2->fetch()) return true;
    
    // Check if they already have a conversation (both directions)
    $stmt3 = $pdo->prepare("SELECT id FROM direct_messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND is_request = 0 LIMIT 1");
    $stmt3->execute([$userA, $userB, $userB, $userA]);
    if ($stmt3->fetch()) return true;
    
    return false;
}

switch ($action) {

    case 'all_users':
        // Get ALL users with conversation status
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.avatar, u.last_seen,
                (SELECT message FROM direct_messages dm2 
                 WHERE ((dm2.sender_id = u.id AND dm2.receiver_id = ?) OR (dm2.sender_id = ? AND dm2.receiver_id = u.id)) AND dm2.is_request = 0
                 ORDER BY dm2.created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM direct_messages dm3 
                 WHERE ((dm3.sender_id = u.id AND dm3.receiver_id = ?) OR (dm3.sender_id = ? AND dm3.receiver_id = u.id)) AND dm3.is_request = 0
                 ORDER BY dm3.created_at DESC LIMIT 1) as last_message_time,
                (SELECT COUNT(*) FROM direct_messages dm4 
                 WHERE dm4.sender_id = u.id AND dm4.receiver_id = ? AND dm4.is_read = 0 AND dm4.is_request = 0) as unread_count,
                (CASE WHEN u.id IN (SELECT sender_id FROM direct_messages WHERE receiver_id = ? AND is_request = 0)
                    OR u.id IN (SELECT receiver_id FROM direct_messages WHERE sender_id = ? AND is_request = 0)
                 THEN 1 ELSE 0 END) as has_conversation
            FROM users u
            WHERE u.id != ?
            AND u.id NOT IN (SELECT blocked_id FROM user_blocks WHERE blocker_id = ?)
            AND u.id NOT IN (SELECT blocker_id FROM user_blocks WHERE blocked_id = ?)
            ORDER BY has_conversation DESC, last_message_time DESC, u.username ASC
            LIMIT 100
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
        $users = $stmt->fetchAll();
        
        foreach ($users as &$u) {
            $u['is_online'] = $u['last_seen'] && (strtotime($u['last_seen']) > strtotime('-5 minutes'));
            $u['has_conversation'] = (bool)$u['has_conversation'];
        }
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'conversations':
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.username, u.avatar, u.last_seen,
                (SELECT message FROM direct_messages dm2 
                 WHERE ((dm2.sender_id = u.id AND dm2.receiver_id = ?) OR (dm2.sender_id = ? AND dm2.receiver_id = u.id)) AND dm2.is_request = 0
                 ORDER BY dm2.created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM direct_messages dm3 
                 WHERE ((dm3.sender_id = u.id AND dm3.receiver_id = ?) OR (dm3.sender_id = ? AND dm3.receiver_id = u.id)) AND dm3.is_request = 0
                 ORDER BY dm3.created_at DESC LIMIT 1) as last_message_time,
                (SELECT COUNT(*) FROM direct_messages dm4 
                 WHERE dm4.sender_id = u.id AND dm4.receiver_id = ? AND dm4.is_read = 0 AND dm4.is_request = 0) as unread_count
            FROM users u
            WHERE u.id != ? AND (
                u.id IN (SELECT sender_id FROM direct_messages WHERE receiver_id = ? AND is_request = 0)
                OR u.id IN (SELECT receiver_id FROM direct_messages WHERE sender_id = ? AND is_request = 0)
            )
            ORDER BY last_message_time DESC
            LIMIT 50
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
        $convos = $stmt->fetchAll();
        
        foreach ($convos as &$c) {
            $c['is_online'] = $c['last_seen'] && (strtotime($c['last_seen']) > strtotime('-5 minutes'));
        }
        
        echo json_encode(['success' => true, 'conversations' => $convos]);
        break;

    case 'pending_messages':
        // Get pending message requests grouped by sender - with first message preview
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.username, u.avatar, u.last_seen,
                (SELECT message FROM direct_messages dm2 
                 WHERE dm2.sender_id = u.id AND dm2.receiver_id = ? AND dm2.is_request = 1
                 ORDER BY dm2.created_at ASC LIMIT 1) as first_message,
                (SELECT message FROM direct_messages dm2b 
                 WHERE dm2b.sender_id = u.id AND dm2b.receiver_id = ? AND dm2b.is_request = 1
                 ORDER BY dm2b.created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM direct_messages dm3 
                 WHERE dm3.sender_id = u.id AND dm3.receiver_id = ? AND dm3.is_request = 1
                 ORDER BY dm3.created_at DESC LIMIT 1) as last_message_time,
                (SELECT COUNT(*) FROM direct_messages dm4 
                 WHERE dm4.sender_id = u.id AND dm4.receiver_id = ? AND dm4.is_request = 1) as request_count
            FROM users u
            WHERE u.id IN (SELECT DISTINCT sender_id FROM direct_messages WHERE receiver_id = ? AND is_request = 1)
            ORDER BY last_message_time DESC
            LIMIT 50
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
        $pending = $stmt->fetchAll();
        
        foreach ($pending as &$p) {
            $p['is_online'] = $p['last_seen'] && (strtotime($p['last_seen']) > strtotime('-5 minutes'));
        }
        
        echo json_encode(['success' => true, 'pending' => $pending]);
        break;

    case 'preview_request':
        // Allow viewing the request messages before accepting (read-only)
        $senderId = (int)($_GET['sender_id'] ?? 0);
        if (!$senderId) { echo json_encode(['success' => false]); exit; }
        
        $stmt = $pdo->prepare("SELECT dm.*, u.username as sender_name FROM direct_messages dm 
                JOIN users u ON dm.sender_id = u.id
                WHERE dm.sender_id = ? AND dm.receiver_id = ? AND dm.is_request = 1
                ORDER BY dm.created_at ASC LIMIT 50");
        $stmt->execute([$senderId, $userId]);
        $messages = $stmt->fetchAll();
        
        // Get sender info
        $senderInfo = $pdo->prepare("SELECT username, avatar, last_seen FROM users WHERE id = ?");
        $senderInfo->execute([$senderId]);
        $sender = $senderInfo->fetch();
        if ($sender) $sender['is_online'] = $sender['last_seen'] && (strtotime($sender['last_seen']) > strtotime('-5 minutes'));
        
        echo json_encode(['success' => true, 'messages' => $messages, 'sender' => $sender, 'current_user_id' => $userId]);
        break;

    case 'accept_request':
        $senderId = (int)($data['sender_id'] ?? 0);
        if (!$senderId) { echo json_encode(['success' => false]); exit; }
        
        // Mark all pending messages as normal
        $pdo->prepare("UPDATE direct_messages SET is_request = 0 WHERE sender_id = ? AND receiver_id = ? AND is_request = 1")
            ->execute([$senderId, $userId]);
        
        // Create chat approval
        $pdo->prepare("INSERT INTO chat_requests (sender_id, receiver_id, status) VALUES (?, ?, 'approved') ON DUPLICATE KEY UPDATE status = 'approved'")
            ->execute([$senderId, $userId]);
        $pdo->prepare("INSERT INTO chat_requests (sender_id, receiver_id, status) VALUES (?, ?, 'approved') ON DUPLICATE KEY UPDATE status = 'approved'")
            ->execute([$userId, $senderId]);
        
        echo json_encode(['success' => true]);
        break;

    case 'reject_request':
        $senderId = (int)($data['sender_id'] ?? 0);
        if (!$senderId) { echo json_encode(['success' => false]); exit; }
        
        $pdo->prepare("DELETE FROM direct_messages WHERE sender_id = ? AND receiver_id = ? AND is_request = 1")
            ->execute([$senderId, $userId]);
        
        echo json_encode(['success' => true]);
        break;

    case 'thread':
        $otherUserId = (int)($data['user_id'] ?? $_GET['user_id'] ?? 0);
        if (!$otherUserId) { echo json_encode(['success' => false]); exit; }
        
        $afterId = (int)($data['after_id'] ?? $_GET['after_id'] ?? 0);
        
        $sql = "SELECT dm.*, u.username as sender_name FROM direct_messages dm 
                JOIN users u ON dm.sender_id = u.id
                WHERE ((dm.sender_id = ? AND dm.receiver_id = ?) OR (dm.sender_id = ? AND dm.receiver_id = ?)) AND dm.is_request = 0";
        $params = [$userId, $otherUserId, $otherUserId, $userId];
        
        if ($afterId > 0) {
            $sql .= " AND dm.id > ?";
            $params[] = $afterId;
        }
        
        $sql .= " ORDER BY dm.created_at ASC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();
        
        // Mark as read
        $pdo->prepare("UPDATE direct_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")->execute([$otherUserId, $userId]);
        
        // Get other user's online status
        $otherUser = $pdo->prepare("SELECT username, avatar, last_seen FROM users WHERE id = ?");
        $otherUser->execute([$otherUserId]);
        $otherInfo = $otherUser->fetch();
        $otherInfo['is_online'] = $otherInfo['last_seen'] && (strtotime($otherInfo['last_seen']) > strtotime('-5 minutes'));
        
        // Check if there's a pending request status 
        $chatStatus = 'approved';
        if (!isChatApproved($pdo, $userId, $otherUserId)) {
            // Check if we sent a request
            $reqCheck = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE sender_id = ? AND receiver_id = ? AND is_request = 1");
            $reqCheck->execute([$userId, $otherUserId]);
            if ((int)$reqCheck->fetchColumn() > 0) $chatStatus = 'request_sent';
            else $chatStatus = 'none';
        }
        
        echo json_encode(['success' => true, 'messages' => $messages, 'other_user' => $otherInfo, 'current_user_id' => $userId, 'chat_status' => $chatStatus]);
        break;

    case 'send':
        $receiverId = (int)($data['receiver_id'] ?? 0);
        $message = clean($data['message'] ?? '');
        if (!$receiverId || !$message) { echo json_encode(['success' => false, 'message' => 'Missing data']); exit; }
        
        // Check privacy: can't message if blocked
        $blockCheck = $pdo->prepare("SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
        $blockCheck->execute([$userId, $receiverId, $receiverId, $userId]);
        if ($blockCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'ناتوانیت نامە بنێریت']);
            exit;
        }
        
        // Check if chat is approved
        $isApproved = isChatApproved($pdo, $userId, $receiverId);
        $isRequest = 0;
        
        if (!$isApproved) {
            // Check if receiver has chat_request privacy enabled
            $chatReqEnabled = isChatRequestEnabled($pdo, $receiverId);
            
            if ($chatReqEnabled) {
                // Chat request enabled - check how many request messages sender already sent
                $reqCountCheck = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE sender_id = ? AND receiver_id = ? AND is_request = 1");
                $reqCountCheck->execute([$userId, $receiverId]);
                $existingReqs = (int)$reqCountCheck->fetchColumn();
                
                if ($existingReqs >= 1) {
                    // Already sent 1 request message, can't send more until accepted
                    echo json_encode(['success' => false, 'message' => 'تەنها یەک نامە دەتوانیت بنێریت هەتا قبوڵ دەکرێت', 'is_request_limit' => true]);
                    exit;
                }
                $isRequest = 1;
            }
            // If chat_request NOT enabled, treat as normal open chat (no request needed)
        }
        
        $pdo->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message, is_request) VALUES (?, ?, ?, ?)")
            ->execute([$userId, $receiverId, $message, $isRequest]);
        
        if ($isRequest) {
            sendNotification($pdo, $receiverId, '💬 داواکاری نامە', clean($_SESSION['username']) . ' نامەیەکی نێردووە (چاوەڕوان)', SITE_URL . '/messages.php?tab=requests');
            echo json_encode(['success' => true, 'is_request' => true, 'message' => 'نامەکەت بۆ بەشی داواکاریەکان نێردرا']);
        } else {
            sendNotification($pdo, $receiverId, '💬 نامەی نوێ', clean($_SESSION['username']) . ': ' . mb_substr($message, 0, 50), SITE_URL . '/messages.php?with=' . $userId);
            echo json_encode(['success' => true, 'is_request' => false]);
        }
        break;

    case 'unread_total':
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0 AND is_request = 0");
        $stmt->execute([$userId]);
        $normalUnread = (int)$stmt->fetchColumn();
        
        $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT sender_id) FROM direct_messages WHERE receiver_id = ? AND is_request = 1");
        $stmt2->execute([$userId]);
        $pendingCount = (int)$stmt2->fetchColumn();
        
        echo json_encode(['success' => true, 'count' => $normalUnread, 'pending_count' => $pendingCount]);
        break;

    case 'heartbeat':
        echo json_encode(['success' => true]);
        break;

    case 'typing':
        $receiverId = (int)($data['receiver_id'] ?? 0);
        if (!$receiverId) { echo json_encode(['success' => false]); exit; }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS dm_typing (
                user_id INT NOT NULL,
                target_id INT NOT NULL,
                typed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, target_id)
            ) ENGINE=InnoDB");
            $pdo->prepare("INSERT INTO dm_typing (user_id, target_id, typed_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE typed_at = NOW()")
                ->execute([$userId, $receiverId]);
        } catch (Exception $e) {}
        echo json_encode(['success' => true]);
        break;

    case 'typing_status':
        $otherUserId = (int)($_GET['user_id'] ?? 0);
        if (!$otherUserId) { echo json_encode(['success' => true, 'is_typing' => false]); exit; }
        $isTyping = false;
        try {
            $stmt = $pdo->prepare("SELECT typed_at FROM dm_typing WHERE user_id = ? AND target_id = ? AND typed_at > DATE_SUB(NOW(), INTERVAL 3 SECOND)");
            $stmt->execute([$otherUserId, $userId]);
            $isTyping = (bool)$stmt->fetch();
        } catch (Exception $e) {}
        echo json_encode(['success' => true, 'is_typing' => $isTyping]);
        break;

    case 'delete_message':
        $messageId = (int)($data['message_id'] ?? 0);
        if (!$messageId) { echo json_encode(['success' => false]); exit; }
        $pdo->prepare("DELETE FROM direct_messages WHERE id = ? AND sender_id = ?")->execute([$messageId, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'edit_message':
        $messageId = (int)($data['message_id'] ?? 0);
        $newMessage = clean($data['message'] ?? '');
        if (!$messageId || !$newMessage) { echo json_encode(['success' => false]); exit; }
        $pdo->prepare("UPDATE direct_messages SET message = ? WHERE id = ? AND sender_id = ?")->execute([$newMessage, $messageId, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'mark_seen':
        $otherUserId = (int)($data['other_user_id'] ?? $data['user_id'] ?? 0);
        if ($otherUserId) {
            $pdo->prepare("UPDATE direct_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
                ->execute([$otherUserId, $userId]);
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
