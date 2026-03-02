<?php
/**
 * Public Rooms API - List active public watch parties
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Add is_public column if not exists
try {
    $pdo->exec("ALTER TABLE watch_parties ADD COLUMN is_public TINYINT(1) DEFAULT 1");
} catch (Exception $e) {}

// Get active public rooms (has members active in last 30 seconds)
try {
    $stmt = $pdo->query("
        SELECT wp.room_id, wp.created_at, m.title as movie_title, m.poster, m.slug, u.username as host_name,
            (SELECT COUNT(*) FROM watch_party_members wpm WHERE wpm.room_id = wp.room_id AND wpm.last_seen > DATE_SUB(NOW(), INTERVAL 30 SECOND)) as member_count
        FROM watch_parties wp
        JOIN movies m ON wp.movie_id = m.id
        JOIN users u ON wp.host_user_id = u.id
        WHERE wp.is_public = 1
        HAVING member_count > 0
        ORDER BY member_count DESC, wp.created_at DESC
        LIMIT 12
    ");
    $rooms = $stmt->fetchAll();
    echo json_encode(['success' => true, 'rooms' => $rooms]);
} catch (Exception $e) {
    echo json_encode(['success' => true, 'rooms' => []]);
}
