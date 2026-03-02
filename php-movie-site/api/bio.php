<?php
/**
 * User Bio API
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

// Auto-add bio column
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL");
} catch (Exception $e) {}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'update':
        $bio = clean($data['bio'] ?? '');
        if (mb_strlen($bio) > 300) {
            echo json_encode(['success' => false, 'message' => 'Bio too long (max 300 chars)']);
            exit;
        }
        $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?")->execute([$bio, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'get':
        $targetId = (int)($_GET['user_id'] ?? $userId);
        $stmt = $pdo->prepare("SELECT bio FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $bio = $stmt->fetchColumn();
        echo json_encode(['success' => true, 'bio' => $bio ?: '']);
        break;

    case 'set_premium_emoji':
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS premium_emoji VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}
        $emoji = $data['emoji'] ?? '';
        $pdo->prepare("UPDATE users SET premium_emoji = ? WHERE id = ?")->execute([$emoji ?: null, $userId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
