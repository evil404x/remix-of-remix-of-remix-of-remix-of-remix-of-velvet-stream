<?php
/**
 * SHAH CINEMA - Emoji Reaction Pop API
 * Floating emoji reactions in watch party rooms (TikTok Live style)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auto-create table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS room_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(32) NOT NULL,
        user_id INT NOT NULL,
        emoji VARCHAR(10) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_room_time (room_id, created_at)
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'send':
        $roomId = clean($data['room_id'] ?? '');
        $emoji = $data['emoji'] ?? '';
        
        // Validate emoji (only allow specific emojis)
        $allowedEmojis = ['❤️','😂','😍','🔥','👏','😱','😢','🤩','💯','👑','🍿','🎬','💀','😎','🥰','👀','💔','🙌'];
        if (!in_array($emoji, $allowedEmojis)) {
            echo json_encode(['success' => false, 'message' => 'Invalid emoji']);
            exit;
        }
        
        if (!$roomId) { echo json_encode(['success' => false]); exit; }
        
        // Rate limit: max 5 reactions per 10 seconds
        $rateCheck = $pdo->prepare("SELECT COUNT(*) FROM room_reactions WHERE room_id = ? AND user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)");
        $rateCheck->execute([$roomId, $userId]);
        if ((int)$rateCheck->fetchColumn() >= 5) {
            echo json_encode(['success' => false, 'message' => 'Too fast']);
            exit;
        }
        
        $pdo->prepare("INSERT INTO room_reactions (room_id, user_id, emoji) VALUES (?, ?, ?)")
            ->execute([$roomId, $userId, $emoji]);
        
        echo json_encode(['success' => true]);
        break;

    case 'poll':
        $roomId = clean($_GET['room_id'] ?? '');
        $afterId = (int)($_GET['after_id'] ?? 0);
        
        if (!$roomId) { echo json_encode(['success' => false]); exit; }
        
        // Get reactions from last 10 seconds (or after specific ID)
        $stmt = $pdo->prepare("SELECT rr.id, rr.emoji, rr.user_id, u.username, rr.created_at 
            FROM room_reactions rr 
            JOIN users u ON rr.user_id = u.id 
            WHERE rr.room_id = ? AND rr.id > ? AND rr.created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
            ORDER BY rr.id ASC LIMIT 50");
        $stmt->execute([$roomId, $afterId]);
        
        echo json_encode(['success' => true, 'reactions' => $stmt->fetchAll()]);
        break;

    // Clean old reactions (called periodically)
    case 'cleanup':
        $pdo->exec("DELETE FROM room_reactions WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
