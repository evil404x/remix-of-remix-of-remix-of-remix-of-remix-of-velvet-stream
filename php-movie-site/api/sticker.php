<?php
/**
 * Sticker API - List / create / delete stickers for chat, DM and rooms
 * Supports: GIF, PNG, WebP, JPEG, WebM, MP4
 * Admin stickers  -> user_id NULL  (visible to everyone)
 * User stickers   -> user_id = X   (visible to owner, and shareable in chat)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auto-create / upgrade stickers table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS stickers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        name VARCHAR(100) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        sort_order SMALLINT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    try { $pdo->exec("ALTER TABLE stickers ADD COLUMN user_id INT DEFAULT NULL"); } catch (Exception $e) {}
} catch (Exception $e) {}

$action = $_GET['action'] ?? '';
$me = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

const STICKER_MAX_BYTES = 5 * 1024 * 1024;
const STICKER_USER_LIMIT = 60;

function stickerExt(string $mime): ?string {
    return match ($mime) {
        'image/gif'  => 'gif',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/jpeg' => 'jpg',
        'video/webm' => 'webm',
        'video/mp4'  => 'mp4',
        default      => null,
    };
}

switch ($action) {

    case 'list':
        // Global (admin) stickers + the current user's own stickers
        if ($me) {
            $stmt = $pdo->prepare("SELECT id, name, file_name, category, user_id FROM stickers
                WHERE is_active = 1 AND (user_id IS NULL OR user_id = ?)
                ORDER BY (user_id IS NULL) DESC, category, sort_order ASC, id DESC");
            $stmt->execute([$me]);
            $stickers = $stmt->fetchAll();
        } else {
            $stickers = $pdo->query("SELECT id, name, file_name, category, user_id FROM stickers
                WHERE is_active = 1 AND user_id IS NULL
                ORDER BY category, sort_order ASC")->fetchAll();
        }
        foreach ($stickers as &$s) {
            $s['is_mine'] = ($me && (int)$s['user_id'] === $me);
            $s['url'] = SITE_URL . '/uploads/stickers/' . $s['file_name'];
        }
        echo json_encode(['success' => true, 'stickers' => $stickers, 'can_create' => $me > 0]);
        break;

    case 'upload':
        if (!$me) { echo json_encode(['success' => false, 'message' => 'تکایە سەرەتا بچۆژوورەوە']); exit; }

        $isAdminUpload = isAdmin() && (($_POST['scope'] ?? '') !== 'personal');

        $name = clean($_POST['name'] ?? '');
        if ($name === '') $name = 'ستیکەر';
        $category = $isAdminUpload ? clean($_POST['category'] ?? 'general') : 'mine';

        if (!isset($_FILES['sticker_file']) || $_FILES['sticker_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'هیچ فایلێک هەڵنەبژێردرا']);
            exit;
        }

        if (!$isAdminUpload) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM stickers WHERE user_id = ?");
            $cnt->execute([$me]);
            if ((int)$cnt->fetchColumn() >= STICKER_USER_LIMIT) {
                echo json_encode(['success' => false, 'message' => 'گەیشتویتە سنووری ' . STICKER_USER_LIMIT . ' ستیکەر']);
                exit;
            }
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['sticker_file']['tmp_name']);
        $ext = stickerExt((string)$mimeType);

        if (!$ext) {
            echo json_encode(['success' => false, 'message' => 'جۆری فایل پشتگیری ناکرێت (GIF, PNG, WebP, JPEG, WebM, MP4)']);
            exit;
        }
        if ($_FILES['sticker_file']['size'] > STICKER_MAX_BYTES) {
            echo json_encode(['success' => false, 'message' => 'قەبارەی فایل زۆر گەورەیە (حەدی ٥MB)']);
            exit;
        }

        $fileName = 'sticker_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = __DIR__ . '/../uploads/stickers';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (!move_uploaded_file($_FILES['sticker_file']['tmp_name'], $dir . '/' . $fileName)) {
            echo json_encode(['success' => false, 'message' => 'ئاپڵۆد سەرکەوتوو نەبوو']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO stickers (user_id, name, file_name, category) VALUES (?, ?, ?, ?)");
        $stmt->execute([$isAdminUpload ? null : $me, $name, $fileName, $category]);

        echo json_encode([
            'success'   => true,
            'id'        => $pdo->lastInsertId(),
            'file_name' => $fileName,
            'name'      => $name,
            'is_mine'   => !$isAdminUpload,
        ]);
        break;

    case 'delete':
        if (!$me) { echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($data['id'] ?? $_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false]); exit; }

        $row = $pdo->prepare("SELECT file_name, user_id FROM stickers WHERE id = ?");
        $row->execute([$id]);
        $sticker = $row->fetch();
        if (!$sticker) { echo json_encode(['success' => false]); exit; }

        $owns = ((int)$sticker['user_id'] === $me);
        if (!$owns && !isAdmin()) { echo json_encode(['success' => false, 'message' => 'مۆڵەتت نییە']); exit; }

        @unlink(__DIR__ . '/../uploads/stickers/' . $sticker['file_name']);
        $pdo->prepare("DELETE FROM stickers WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
