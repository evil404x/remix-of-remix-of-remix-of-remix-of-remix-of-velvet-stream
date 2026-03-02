<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'unread_count':
        echo json_encode(['success' => true, 'count' => getUnreadCount($pdo, $userId)]);
        break;

    case 'latest':
        $notifs = getNotifications($pdo, $userId, 10);
        // Mark as read
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
        echo json_encode(['success' => true, 'notifications' => $notifs]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
