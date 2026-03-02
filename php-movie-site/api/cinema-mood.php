<?php
/**
 * SHAH CINEMA - Cinema Mood API
 * Users can set/get cinema mood status
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auto-add cinema_mood column
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN cinema_mood VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'get':
        $targetUserId = (int)($_GET['user_id'] ?? 0);
        if (!$targetUserId) { echo json_encode(['success' => false]); exit; }
        
        $stmt = $pdo->prepare("SELECT cinema_mood FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $mood = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'mood' => $mood ?: '']);
        break;

    case 'set':
        if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'login_required']); exit; }
        
        $mood = clean($data['mood'] ?? '');
        if (mb_strlen($mood) > 100) $mood = mb_substr($mood, 0, 100);
        
        $pdo->prepare("UPDATE users SET cinema_mood = ? WHERE id = ?")
            ->execute([$mood ?: null, $_SESSION['user_id']]);
        
        echo json_encode(['success' => true]);
        break;

    case 'clear':
        if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'login_required']); exit; }
        
        $pdo->prepare("UPDATE users SET cinema_mood = NULL WHERE id = ?")->execute([$_SESSION['user_id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
