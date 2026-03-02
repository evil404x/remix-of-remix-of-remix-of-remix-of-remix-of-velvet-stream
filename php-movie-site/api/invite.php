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

// Auto-create invites table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS watch_party_invites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(32) NOT NULL,
        inviter_id INT NOT NULL,
        invitee_id INT NOT NULL,
        status ENUM('pending','accepted','declined') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_invite (room_id, invitee_id)
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

switch ($action) {

    case 'send':
        $roomId = clean($data['room_id'] ?? '');
        $inviteeId = (int)($data['invitee_id'] ?? 0);
        if (!$roomId || !$inviteeId) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }

        // Check room exists
        $room = $pdo->prepare("SELECT * FROM watch_parties WHERE room_id = ?");
        $room->execute([$roomId]);
        $roomData = $room->fetch();
        if (!$roomData) {
            echo json_encode(['success' => false, 'message' => 'Room not found']);
            exit;
        }

        // Insert invite
        $pdo->prepare("INSERT IGNORE INTO watch_party_invites (room_id, inviter_id, invitee_id) VALUES (?, ?, ?)")
            ->execute([$roomId, $userId, $inviteeId]);

        // Get movie slug for link
        $movie = $pdo->prepare("SELECT slug FROM movies WHERE id = ?");
        $movie->execute([$roomData['movie_id']]);
        $movieData = $movie->fetch();
        $partyLink = SITE_URL . '/movie.php?slug=' . ($movieData['slug'] ?? '') . '&party=' . $roomId;

        // Send notification
        $inviterName = $_SESSION['username'] ?? 'کەسێک';
        sendNotification($pdo, $inviteeId, 'بانگهێشتی Watch Party', "$inviterName بانگهێشتی کردوویت بۆ سەیرکردنی فیلم پێکەوە!", $partyLink);

        // Award points for invite
        try {
            $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, 10) ON DUPLICATE KEY UPDATE points = points + 10")->execute([$userId]);
            $pdo->prepare("INSERT INTO point_activities (user_id, activity_type, points_earned, description) VALUES (?, 'invite', 10, 'بانگهێشتکردنی هاوڕێ')")->execute([$userId]);
            // Check VIP threshold
            $ptsCheck = $pdo->prepare("SELECT points, vip_until FROM user_points WHERE user_id = ?");
            $ptsCheck->execute([$userId]);
            $ptsData = $ptsCheck->fetch();
            if ($ptsData && $ptsData['points'] >= 500 && (!$ptsData['vip_until'] || strtotime($ptsData['vip_until']) < time())) {
                $pdo->prepare("UPDATE user_points SET vip_until = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE user_id = ?")->execute([$userId]);
                sendNotification($pdo, $userId, '🎉 VIP بوویت!', 'پیرۆزە! گەیشتیتە ٥٠٠ خاڵ و VIP بوویت بۆ ٣٠ ڕۆژ.', SITE_URL . '/profile.php');
            }
        } catch (Exception $e) {}

        echo json_encode(['success' => true]);
        break;

    case 'followers_list':
        // Get followers who can be invited
        $roomId = clean($data['room_id'] ?? $_GET['room_id'] ?? '');
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.avatar FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ? AND f.status = 'active' ORDER BY u.username ASC");
        $stmt->execute([$userId]);
        $followers = $stmt->fetchAll();

        // Also get who's already in room
        $membersStmt = $pdo->prepare("SELECT user_id FROM watch_party_members WHERE room_id = ?");
        $membersStmt->execute([$roomId]);
        $memberIds = array_column($membersStmt->fetchAll(), 'user_id');

        // Mark who's already in room
        foreach ($followers as &$f) {
            $f['in_room'] = in_array($f['id'], $memberIds);
        }

        echo json_encode(['success' => true, 'followers' => $followers]);
        break;

    case 'check_invite':
        // Check if user is invited to a private room
        $roomId = clean($data['room_id'] ?? $_GET['room_id'] ?? '');
        $stmt = $pdo->prepare("SELECT id FROM watch_party_invites WHERE room_id = ? AND invitee_id = ?");
        $stmt->execute([$roomId, $userId]);
        echo json_encode(['success' => true, 'invited' => (bool)$stmt->fetch()]);
        break;

    case 'accept':
        $inviteId = (int)($data['invite_id'] ?? 0);
        if (!$inviteId) { echo json_encode(['success' => false, 'message' => 'Missing invite_id']); exit; }
        
        // Get invite info
        $inv = $pdo->prepare("SELECT * FROM watch_party_invites WHERE id = ? AND invitee_id = ?");
        $inv->execute([$inviteId, $userId]);
        $invData = $inv->fetch();
        if (!$invData) { echo json_encode(['success' => false, 'message' => 'Invite not found']); exit; }
        
        // Mark as accepted
        $pdo->prepare("UPDATE watch_party_invites SET status = 'accepted' WHERE id = ?")->execute([$inviteId]);
        
        // Join the room
        $pdo->prepare("INSERT INTO watch_party_members (room_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_seen = NOW()")
            ->execute([$invData['room_id'], $userId]);
        
        echo json_encode(['success' => true, 'room_id' => $invData['room_id']]);
        break;

    case 'decline':
        $inviteId = (int)($data['invite_id'] ?? 0);
        if (!$inviteId) { echo json_encode(['success' => false, 'message' => 'Missing invite_id']); exit; }
        
        $pdo->prepare("UPDATE watch_party_invites SET status = 'declined' WHERE id = ? AND invitee_id = ?")
            ->execute([$inviteId, $userId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
