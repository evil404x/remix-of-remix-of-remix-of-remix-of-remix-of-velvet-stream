<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$q = clean($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['results' => []]); exit; }

// Search users excluding hidden ones
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.avatar, u.last_seen
    FROM users u 
    LEFT JOIN user_privacy up ON u.id = up.user_id
    WHERE u.username LIKE ? 
    AND (up.hide_from_search IS NULL OR up.hide_from_search = 0)
    AND u.is_active = 1
    LIMIT 10
");
$stmt->execute(["%{$q}%"]);
$users = $stmt->fetchAll();

foreach ($users as &$u) {
    $u['is_online'] = $u['last_seen'] && (strtotime($u['last_seen']) > strtotime('-5 minutes'));
}

echo json_encode(['results' => $users]);
