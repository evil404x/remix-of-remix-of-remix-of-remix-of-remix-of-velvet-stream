<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS watch_parties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(32) NOT NULL UNIQUE,
        movie_id INT NOT NULL,
        host_user_id INT NOT NULL,
        playback_time DECIMAL(10,2) DEFAULT 0,
        playback_status ENUM('playing','paused','stopped') DEFAULT 'paused',
        is_public TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS watch_party_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(32) NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS watch_party_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(32) NOT NULL,
        user_id INT NOT NULL,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_member (room_id, user_id)
    ) ENGINE=InnoDB");
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB table error: ' . $e->getMessage()]);
    exit;
}

// Add is_public column if not exists
try { $pdo->exec("ALTER TABLE watch_parties ADD COLUMN is_public TINYINT(1) DEFAULT 1"); } catch (Exception $e) {}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? $_GET['action'] ?? '';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];

switch ($action) {

    // ============ CREATE ROOM ============
    case 'create':
        $movieId = (int)($data['movie_id'] ?? 0);
        $isPublic = (int)($data['is_public'] ?? 1);
        if (!$movieId) { echo json_encode(['success' => false, 'message' => 'Missing movie_id']); exit; }

        $roomId = bin2hex(random_bytes(8));

        $stmt = $pdo->prepare("INSERT INTO watch_parties (room_id, movie_id, host_user_id, is_public) VALUES (?, ?, ?, ?)");
        $stmt->execute([$roomId, $movieId, $userId, $isPublic]);

        // Auto-join host as member
        $pdo->prepare("INSERT IGNORE INTO watch_party_members (room_id, user_id) VALUES (?, ?)")
            ->execute([$roomId, $userId]);

        echo json_encode(['success' => true, 'room_id' => $roomId]);
        break;

    // ============ GET ROOM INFO ============
    case 'info':
        $roomId = clean($data['room_id'] ?? $_GET['room_id'] ?? '');
        if (!$roomId) { echo json_encode(['success' => false]); exit; }

        $stmt = $pdo->prepare("SELECT wp.*, m.title as movie_title, m.slug, m.poster, u.username as host_name 
            FROM watch_parties wp 
            JOIN movies m ON wp.movie_id = m.id 
            JOIN users u ON wp.host_user_id = u.id 
            WHERE wp.room_id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if (!$room) { echo json_encode(['success' => false, 'message' => 'Room not found']); exit; }

        echo json_encode([
            'success' => true,
            'room' => $room,
            'is_host' => ($room['host_user_id'] == $userId)
        ]);
        break;

    // ============ JOIN ROOM (heartbeat) ============
    case 'join':
        $roomId = clean($data['room_id'] ?? '');
        if (!$roomId) { echo json_encode(['success' => false]); exit; }

        $roomCheck = $pdo->prepare("SELECT host_user_id FROM watch_parties WHERE room_id = ?");
        $roomCheck->execute([$roomId]);
        $roomData = $roomCheck->fetch();
        if ($roomData && $roomData['host_user_id'] != $userId) {
            try {
                $privCheck = $pdo->prepare("SELECT private_account FROM user_privacy WHERE user_id = ?");
                $privCheck->execute([$roomData['host_user_id']]);
                $hostPrivacy = $privCheck->fetch();
                if ($hostPrivacy && $hostPrivacy['private_account']) {
                    $invCheck = $pdo->prepare("SELECT id FROM watch_party_invites WHERE room_id = ? AND invitee_id = ?");
                    $invCheck->execute([$roomId, $userId]);
                    if (!$invCheck->fetch()) {
                        $folCheck = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ? AND status = 'active'");
                        $folCheck->execute([$userId, $roomData['host_user_id']]);
                        if (!$folCheck->fetch()) {
                            echo json_encode(['success' => false, 'message' => 'ئەم ژوورە تایبەتە. بانگهێشت پێویستە.']);
                            exit;
                        }
                    }
                }
            } catch (Exception $e) {}
        }

        $pdo->prepare("INSERT INTO watch_party_members (room_id, user_id, last_seen) VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE last_seen = NOW()")
            ->execute([$roomId, $userId]);

        echo json_encode(['success' => true]);
        break;

    // ============ LEAVE ROOM ============
    case 'leave':
        $roomId = clean($data['room_id'] ?? '');
        
        $hostCheck = $pdo->prepare("SELECT host_user_id FROM watch_parties WHERE room_id = ?");
        $hostCheck->execute([$roomId]);
        $hostRow = $hostCheck->fetch();
        
        if ($hostRow && (int)$hostRow['host_user_id'] === $userId) {
            // Host leaving = room closed
            $pdo->prepare("UPDATE watch_parties SET playback_status = 'stopped' WHERE room_id = ?")->execute([$roomId]);
            $pdo->prepare("DELETE FROM watch_party_members WHERE room_id = ?")->execute([$roomId]);
            $pdo->prepare("DELETE FROM watch_parties WHERE room_id = ?")->execute([$roomId]);
        } else {
            $pdo->prepare("DELETE FROM watch_party_members WHERE room_id = ? AND user_id = ?")
                ->execute([$roomId, $userId]);
        }
        echo json_encode(['success' => true]);
        break;

    // ============ GET ONLINE MEMBERS ============
    case 'members':
        $roomId = clean($data['room_id'] ?? $_GET['room_id'] ?? '');
        if (!$roomId) { echo json_encode(['success' => false]); exit; }

        // Clean stale members (not seen in 15 seconds)
        $pdo->prepare("DELETE FROM watch_party_members WHERE room_id = ? AND last_seen < DATE_SUB(NOW(), INTERVAL 15 SECOND)")
            ->execute([$roomId]);

        $stmtRoom = $pdo->prepare("SELECT host_user_id FROM watch_parties WHERE room_id = ?");
        $stmtRoom->execute([$roomId]);
        $roomInfo = $stmtRoom->fetch();
        $hostId = $roomInfo ? (int)$roomInfo['host_user_id'] : 0;

        $stmt = $pdo->prepare("SELECT wpm.user_id, u.username, wpm.last_seen 
            FROM watch_party_members wpm 
            JOIN users u ON wpm.user_id = u.id 
            WHERE wpm.room_id = ?
            ORDER BY wpm.id ASC");
        $stmt->execute([$roomId]);
        $members = $stmt->fetchAll();

        foreach ($members as &$m) {
            $m['is_host'] = ((int)$m['user_id'] === $hostId);
            // Member is online if last_seen within 10 seconds
            $m['is_online'] = (strtotime($m['last_seen']) >= time() - 10);
        }

        echo json_encode([
            'success' => true,
            'members' => $members,
            'host_id' => $hostId,
            'current_user_id' => $userId
        ]);
        break;

    // ============ PROMOTE TO HOST ============
    case 'promote':
        $roomId = clean($data['room_id'] ?? '');
        $newHostId = (int)($data['new_host_id'] ?? 0);

        if (!$roomId || !$newHostId) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT host_user_id FROM watch_parties WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if (!$room || $room['host_user_id'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }

        $pdo->prepare("UPDATE watch_parties SET host_user_id = ? WHERE room_id = ?")
            ->execute([$newHostId, $roomId]);

        echo json_encode(['success' => true]);
        break;

    // ============ HOST: UPDATE PLAYBACK STATE ============
    case 'sync':
        $roomId = clean($data['room_id'] ?? '');
        $currentTime = (float)($data['current_time'] ?? 0);
        $status = in_array($data['status'] ?? '', ['playing', 'paused', 'stopped']) ? $data['status'] : 'paused';

        $stmt = $pdo->prepare("SELECT host_user_id FROM watch_parties WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if (!$room || $room['host_user_id'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }

        $pdo->prepare("UPDATE watch_parties SET playback_time = ?, playback_status = ?, updated_at = NOW() WHERE room_id = ?")
            ->execute([$currentTime, $status, $roomId]);

        echo json_encode(['success' => true]);
        break;

    // ============ GUEST: GET PLAYBACK STATE ============
    case 'poll':
        $roomId = clean($data['room_id'] ?? $_GET['room_id'] ?? '');
        
        $stmt = $pdo->prepare("SELECT playback_time, playback_status, updated_at, host_user_id FROM watch_parties WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $state = $stmt->fetch();

        if (!$state) { 
            echo json_encode(['success' => false, 'room_closed' => true]); 
            exit; 
        }

        echo json_encode([
            'success' => true,
            'current_time' => (float)$state['playback_time'],
            'status' => $state['playback_status'],
            'updated_at' => $state['updated_at'],
            'host_id' => (int)$state['host_user_id'],
            'is_host' => ((int)$state['host_user_id'] == $userId),
            'room_closed' => ($state['playback_status'] === 'stopped')
        ]);
        break;

    // ============ SEND CHAT MESSAGE ============
    case 'chat_send':
        $roomId = clean($data['room_id'] ?? '');
        $message = clean($data['message'] ?? '');

        if (!$roomId || !$message || mb_strlen($message) > 500) {
            echo json_encode(['success' => false, 'message' => 'Invalid message']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM watch_parties WHERE room_id = ?");
        $stmt->execute([$roomId]);
        if (!$stmt->fetch()) { echo json_encode(['success' => false]); exit; }

        $pdo->prepare("INSERT INTO watch_party_messages (room_id, user_id, message) VALUES (?, ?, ?)")
            ->execute([$roomId, $userId, $message]);

        echo json_encode(['success' => true]);
        break;

    // ============ GET CHAT MESSAGES ============
    case 'chat_poll':
        $roomId = clean($data['room_id'] ?? $_GET['room_id'] ?? '');
        $after = (int)($data['after'] ?? $_GET['after'] ?? 0);

        $stmt = $pdo->prepare("SELECT wpm.id, wpm.message, wpm.created_at, u.username, wpm.user_id
            FROM watch_party_messages wpm 
            JOIN users u ON wpm.user_id = u.id 
            WHERE wpm.room_id = ? AND wpm.id > ?
            ORDER BY wpm.id ASC 
            LIMIT 50");
        $stmt->execute([$roomId, $after]);

        echo json_encode([
            'success' => true,
            'messages' => $stmt->fetchAll(),
            'current_user_id' => $userId
        ]);
        break;

    // ============ DELETE ROOM ============
    case 'delete':
        $roomId = clean($data['room_id'] ?? '');
        $pdo->prepare("DELETE FROM watch_parties WHERE room_id = ? AND host_user_id = ?")
            ->execute([$roomId, $userId]);
        $pdo->prepare("DELETE FROM watch_party_messages WHERE room_id = ?")->execute([$roomId]);
        $pdo->prepare("DELETE FROM watch_party_members WHERE room_id = ?")->execute([$roomId]);
        echo json_encode(['success' => true]);
        break;

    // ============ TOGGLE PUBLIC/PRIVATE ============
    case 'toggle_public':
        $roomId = clean($data['room_id'] ?? '');
        $isPublic = (int)($data['is_public'] ?? 1);
        $stmt = $pdo->prepare("SELECT host_user_id FROM watch_parties WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
        if (!$room || $room['host_user_id'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }
        $pdo->prepare("UPDATE watch_parties SET is_public = ? WHERE room_id = ?")->execute([$isPublic, $roomId]);
        echo json_encode(['success' => true]);
        break;

    // ============ MY ACTIVE ROOM (Resume Session) ============
    case 'my_active_room':
        $stmt = $pdo->prepare("SELECT wp.room_id, m.title as movie_title, m.poster 
            FROM watch_parties wp 
            JOIN movies m ON wp.movie_id = m.id 
            WHERE wp.host_user_id = ? AND wp.playback_status != 'stopped'
            ORDER BY wp.created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $activeRoom = $stmt->fetch();
        
        if (!$activeRoom) {
            // Check if member of any active room
            $stmt2 = $pdo->prepare("SELECT wp.room_id, m.title as movie_title, m.poster 
                FROM watch_party_members wpm 
                JOIN watch_parties wp ON wpm.room_id = wp.room_id 
                JOIN movies m ON wp.movie_id = m.id 
                WHERE wpm.user_id = ? AND wpm.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY wpm.last_seen DESC LIMIT 1");
            $stmt2->execute([$userId]);
            $activeRoom = $stmt2->fetch();
        }
        
        echo json_encode(['success' => true, 'room' => $activeRoom ?: null]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
