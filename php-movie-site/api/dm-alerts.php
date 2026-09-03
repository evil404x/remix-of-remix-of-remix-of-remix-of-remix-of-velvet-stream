<?php
/**
 * DM Alerts API - live incoming message alerts (for the global chat toast)
 * Returns unread, non-request messages newer than after_id.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId  = (int)$_SESSION['user_id'];
$afterId = (int)($_GET['after_id'] ?? 0);

try {
    $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);
} catch (Exception $e) {}

$alerts = [];
$maxId  = $afterId;

try {
    if ($afterId > 0) {
        $stmt = $pdo->prepare("SELECT dm.id, dm.sender_id, dm.message, dm.created_at, u.username, u.avatar
            FROM direct_messages dm JOIN users u ON dm.sender_id = u.id
            WHERE dm.receiver_id = ? AND dm.is_read = 0 AND dm.is_request = 0 AND dm.id > ?
            ORDER BY dm.id ASC LIMIT 10");
        $stmt->execute([$userId, $afterId]);
        $alerts = $stmt->fetchAll();
        foreach ($alerts as $a) { $maxId = max($maxId, (int)$a['id']); }
    } else {
        // First poll: don't spam old messages, just sync the cursor
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM direct_messages WHERE receiver_id = ?");
        $stmt->execute([$userId]);
        $maxId = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {}

$unread = 0;
try {
    $u = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0 AND is_request = 0");
    $u->execute([$userId]);
    $unread = (int)$u->fetchColumn();
} catch (Exception $e) {}

echo json_encode([
    'success'     => true,
    'alerts'      => $alerts,
    'last_id'     => $maxId,
    'unread'      => $unread,
    'current_user'=> $userId,
]);
