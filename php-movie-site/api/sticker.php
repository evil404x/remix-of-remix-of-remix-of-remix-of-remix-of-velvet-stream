<?php
/**
 * Sticker API - List stickers for chat/DM/room
 * Supports: GIF, PNG, WebP, JPEG, WebM
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auto-create stickers table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS stickers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        sort_order SMALLINT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $stickers = $pdo->query("SELECT id, name, file_name, category FROM stickers WHERE is_active = 1 ORDER BY category, sort_order ASC")->fetchAll();
        echo json_encode(['success' => true, 'stickers' => $stickers]);
        break;

    case 'upload':
        if (!isAdmin()) { echo json_encode(['success' => false, 'message' => 'Admin only']); exit; }
        
        $name = clean($_POST['name'] ?? '');
        $category = clean($_POST['category'] ?? 'general');
        
        if (!$name || !isset($_FILES['sticker_file'])) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }
        
        $allowedTypes = ['image/gif', 'image/png', 'image/webp', 'image/jpeg', 'video/webm'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['sticker_file']['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only GIF, PNG, WebP, JPEG, WebM allowed.']);
            exit;
        }
        
        if ($_FILES['sticker_file']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
            exit;
        }
        
        $ext = match($mimeType) {
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/webm' => 'webm',
            default => 'jpg'
        };
        
        $fileName = 'sticker_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = __DIR__ . '/../uploads/stickers';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        if (move_uploaded_file($_FILES['sticker_file']['tmp_name'], $dir . '/' . $fileName)) {
            $pdo->prepare("INSERT INTO stickers (name, file_name, category) VALUES (?, ?, ?)")
                ->execute([$name, $fileName, $category]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
        }
        break;

    case 'delete':
        if (!isAdmin()) { echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id) {
            $file = $pdo->prepare("SELECT file_name FROM stickers WHERE id = ?");
            $file->execute([$id]);
            $f = $file->fetchColumn();
            if ($f) @unlink(__DIR__ . '/../uploads/stickers/' . $f);
            $pdo->prepare("DELETE FROM stickers WHERE id = ?")->execute([$id]);
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
