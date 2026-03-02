<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'online_users':
        // Users active in last 5 minutes
        try {
            $stmt = $pdo->query("
                SELECT u.id, u.username, u.avatar, u.last_seen,
                    (SELECT m.title FROM watch_history wh JOIN movies m ON wh.movie_id = m.id WHERE wh.user_id = u.id ORDER BY wh.watched_at DESC LIMIT 1) as watching,
                    (SELECT wp.room_id FROM watch_party_members wpm JOIN watch_parties wp ON wpm.room_id = wp.room_id WHERE wpm.user_id = u.id AND wpm.last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE) LIMIT 1) as in_room
                FROM users u 
                WHERE u.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY u.last_seen DESC
            ");
            $users = $stmt->fetchAll();
        } catch (Exception $e) {
            // last_seen column might not exist yet
            $users = [];
        }
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'active_rooms':
        try {
            $stmt = $pdo->query("
                SELECT wp.room_id, wp.playback_status, m.title as movie_title, u.username as host_name,
                    (SELECT COUNT(*) FROM watch_party_members wpm WHERE wpm.room_id = wp.room_id AND wpm.last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE)) as member_count
                FROM watch_parties wp
                JOIN movies m ON wp.movie_id = m.id
                JOIN users u ON wp.host_user_id = u.id
                ORDER BY wp.updated_at DESC
                LIMIT 20
            ");
            echo json_encode(['success' => true, 'rooms' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => true, 'rooms' => []]);
        }
        break;

    case 'stats':
        try {
            $onlineCount = $pdo->query("SELECT COUNT(*) FROM users WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
        } catch (Exception $e) { $onlineCount = 0; }
        try {
            $activeRooms = $pdo->query("SELECT COUNT(*) FROM watch_parties WHERE updated_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)")->fetchColumn();
        } catch (Exception $e) { $activeRooms = 0; }
        $totalMessages = 0;
        try { $totalMessages = $pdo->query("SELECT COUNT(*) FROM direct_messages WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(); } catch(Exception $e) {}
        
        echo json_encode(['success' => true, 'online_count' => (int)$onlineCount, 'active_rooms' => (int)$activeRooms, 'messages_24h' => (int)$totalMessages]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
