<?php
/**
 * SHAH CINEMA - Stories API
 * Upload text/image stories, manage, view, auto-delete (24h expiry)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auto-create table with text story support
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS stories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        media_type ENUM('image','video','text') DEFAULT 'image',
        media_file VARCHAR(255) DEFAULT '',
        text_content VARCHAR(500) DEFAULT '',
        text_bg_color VARCHAR(20) DEFAULT '#F5C518',
        caption VARCHAR(500) DEFAULT '',
        views_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_expires (expires_at),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB");
    
    // Add text columns if missing (for existing tables)
    try { $pdo->exec("ALTER TABLE stories ADD COLUMN text_content VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE stories ADD COLUMN text_bg_color VARCHAR(20) DEFAULT '#F5C518'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE stories MODIFY COLUMN media_type ENUM('image','video','text') DEFAULT 'image'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE stories MODIFY COLUMN media_file VARCHAR(255) DEFAULT ''"); } catch (Exception $e) {}
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS story_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        viewer_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_view (story_id, viewer_id),
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

// Auto-delete expired stories + clean up files
try {
    $expired = $pdo->query("SELECT id, media_file, media_type FROM stories WHERE expires_at < NOW()");
    foreach ($expired as $s) {
        if ($s['media_type'] !== 'text' && !empty($s['media_file'])) {
            @unlink(__DIR__ . '/../uploads/stories/' . $s['media_file']);
        }
    }
    $pdo->exec("DELETE FROM stories WHERE expires_at < NOW()");
} catch (Exception $e) {}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];

// Support both JSON body and multipart form data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $data = $_POST;
}

// Get action from body OR query string
$action = $data['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'upload':
        // Check if it's a text story
        $storyType = $data['story_type'] ?? $_POST['story_type'] ?? 'image';
        
        if ($storyType === 'text') {
            // Text Story
            $textContent = clean($data['text_content'] ?? $_POST['text_content'] ?? '');
            $textBgColor = clean($data['text_bg_color'] ?? $_POST['text_bg_color'] ?? '#F5C518');
            
            if (empty($textContent) || mb_strlen($textContent) < 1) {
                echo json_encode(['success' => false, 'message' => 'تکایە دەقێک بنووسە']);
                exit;
            }
            if (mb_strlen($textContent) > 300) {
                $textContent = mb_substr($textContent, 0, 300);
            }
            
            // Validate color format
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $textBgColor)) {
                $textBgColor = '#F5C518';
            }
            
            $caption = clean($data['caption'] ?? $_POST['caption'] ?? '');
            
            $stmt = $pdo->prepare("INSERT INTO stories (user_id, media_type, media_file, text_content, text_bg_color, caption, expires_at) VALUES (?, 'text', '', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$userId, $textContent, $textBgColor, $caption]);
            
            echo json_encode(['success' => true, 'story_id' => $pdo->lastInsertId()]);
            break;
        }
        
        // Image/Video Story
        if (!isset($_FILES['story_media']) || $_FILES['story_media']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'هیچ فایلێک هەڵنەبژێردرا یان هەڵەیەک لە ئاپڵۆد ڕوویدا']);
            exit;
        }
        
        $file = $_FILES['story_media'];
        $allowedImage = ['image/jpeg','image/png','image/gif','image/webp'];
        $allowedVideo = ['video/mp4','video/webm'];
        $allowed = array_merge($allowedImage, $allowedVideo);
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'فۆرماتی فایل پشتگیری ناکرێت']);
            exit;
        }
        
        // Max 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'قەبارەی فایل زۆر گەورەیە (حەدی ١٠MB)']);
            exit;
        }
        
        $mediaType = in_array($mimeType, $allowedVideo) ? 'video' : 'image';
        $ext = match($mimeType) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
            'video/mp4' => 'mp4', 'video/webm' => 'webm', default => 'jpg'
        };
        
        $filename = 'story_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $dir = __DIR__ . '/../uploads/stories';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            echo json_encode(['success' => false, 'message' => 'هەڵەی ئاپڵۆد']);
            exit;
        }
        
        $caption = clean($data['caption'] ?? $_POST['caption'] ?? '');
        
        // Expires in 24 hours
        $stmt = $pdo->prepare("INSERT INTO stories (user_id, media_type, media_file, caption, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        $stmt->execute([$userId, $mediaType, $filename, $caption]);
        
        echo json_encode(['success' => true, 'story_id' => $pdo->lastInsertId()]);
        break;

    case 'get_user_stories':
        $targetUserId = (int)($_GET['user_id'] ?? 0);
        if (!$targetUserId) { echo json_encode(['success' => false]); exit; }
        
        $stmt = $pdo->prepare("SELECT s.*, u.username, u.avatar FROM stories s JOIN users u ON s.user_id = u.id WHERE s.user_id = ? AND s.expires_at > NOW() ORDER BY s.created_at ASC");
        $stmt->execute([$targetUserId]);
        $stories = $stmt->fetchAll();
        
        // Check which stories current user has viewed
        foreach ($stories as &$story) {
            $viewCheck = $pdo->prepare("SELECT id FROM story_views WHERE story_id = ? AND viewer_id = ?");
            $viewCheck->execute([$story['id'], $userId]);
            $story['is_viewed'] = (bool)$viewCheck->fetch();
            $story['time_ago'] = storyTimeAgo($story['created_at']);
        }
        
        echo json_encode(['success' => true, 'stories' => $stories]);
        break;

    case 'has_story':
        $targetUserId = (int)($_GET['user_id'] ?? 0);
        if (!$targetUserId) { echo json_encode(['success' => false]); exit; }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stories WHERE user_id = ? AND expires_at > NOW()");
        $stmt->execute([$targetUserId]);
        $count = (int)$stmt->fetchColumn();
        
        // Check if all viewed
        $allViewed = true;
        if ($count > 0) {
            $unviewed = $pdo->prepare("SELECT COUNT(*) FROM stories s WHERE s.user_id = ? AND s.expires_at > NOW() AND s.id NOT IN (SELECT story_id FROM story_views WHERE viewer_id = ?)");
            $unviewed->execute([$targetUserId, $userId]);
            $allViewed = ((int)$unviewed->fetchColumn() === 0);
        }
        
        echo json_encode(['success' => true, 'has_story' => $count > 0, 'count' => $count, 'all_viewed' => $allViewed]);
        break;

    case 'my_stories':
        // Get current user's active stories for management
        $stmt = $pdo->prepare("SELECT s.*, 
            (SELECT COUNT(*) FROM story_views WHERE story_id = s.id) as total_views
            FROM stories s WHERE s.user_id = ? AND s.expires_at > NOW() ORDER BY s.created_at DESC");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'stories' => $stmt->fetchAll()]);
        break;

    case 'edit':
        $storyId = (int)($data['story_id'] ?? 0);
        $caption = clean($data['caption'] ?? '');
        if (!$storyId) { echo json_encode(['success' => false]); exit; }
        
        // Only owner can edit
        $stmt = $pdo->prepare("UPDATE stories SET caption = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$caption, $storyId, $userId]);
        
        echo json_encode(['success' => true]);
        break;

    case 'view':
        $storyId = (int)($data['story_id'] ?? $_GET['story_id'] ?? 0);
        if (!$storyId) { echo json_encode(['success' => false]); exit; }
        
        try {
            $pdo->prepare("INSERT IGNORE INTO story_views (story_id, viewer_id) VALUES (?, ?)")->execute([$storyId, $userId]);
            $pdo->prepare("UPDATE stories SET views_count = views_count + 1 WHERE id = ?")->execute([$storyId]);
        } catch (Exception $e) {}
        
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $storyId = (int)($data['story_id'] ?? 0);
        if (!$storyId) { echo json_encode(['success' => false]); exit; }
        
        $stmt = $pdo->prepare("SELECT media_file, media_type FROM stories WHERE id = ? AND user_id = ?");
        $stmt->execute([$storyId, $userId]);
        $story = $stmt->fetch();
        
        if ($story) {
            if ($story['media_type'] !== 'text' && !empty($story['media_file'])) {
                @unlink(__DIR__ . '/../uploads/stories/' . $story['media_file']);
            }
            $pdo->prepare("DELETE FROM stories WHERE id = ? AND user_id = ?")->execute([$storyId, $userId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ستۆرییەکە نەدۆزرایەوە یان مۆڵەتت نییە']);
        }
        break;

    case 'viewers':
        $storyId = (int)($_GET['story_id'] ?? 0);
        if (!$storyId) { echo json_encode(['success' => false]); exit; }
        
        // Only story owner can see viewers
        $ownerCheck = $pdo->prepare("SELECT user_id FROM stories WHERE id = ?");
        $ownerCheck->execute([$storyId]);
        $owner = $ownerCheck->fetch();
        if (!$owner || $owner['user_id'] != $userId) {
            echo json_encode(['success' => false]); exit;
        }
        
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.avatar, sv.viewed_at FROM story_views sv JOIN users u ON sv.viewer_id = u.id WHERE sv.story_id = ? ORDER BY sv.viewed_at DESC");
        $stmt->execute([$storyId]);
        
        echo json_encode(['success' => true, 'viewers' => $stmt->fetchAll()]);
        break;

    case 'feed':
        // All users with active stories (me first, then followed users, then the rest)
        $q = trim($_GET['q'] ?? '');
        $params = [$userId, $userId, $userId];
        $where = '';
        if ($q !== '') {
            $where = " AND (u.username LIKE ? OR u.full_name LIKE ?) ";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sql = "SELECT u.id, u.username, u.avatar,
                    COUNT(s.id) AS story_count,
                    MAX(s.created_at) AS last_at,
                    SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) AS unseen
                FROM stories s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.viewer_id = ?
                WHERE s.expires_at > NOW() $where
                GROUP BY u.id, u.username, u.avatar
                ORDER BY (u.id = ?) DESC, unseen > 0 DESC, last_at DESC";
        // reorder params: viewer_id, [q..], me
        $bind = [$userId];
        if ($q !== '') { $bind[] = '%' . $q . '%'; $bind[] = '%' . $q . '%'; }
        $bind[] = $userId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['is_me']     = ((int)$r['id'] === (int)$userId);
            $r['all_viewed'] = ((int)$r['unseen'] === 0);
            $r['time_ago']  = storyTimeAgo($r['last_at']);
        }
        echo json_encode(['success' => true, 'users' => $rows, 'me' => (int)$userId]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function storyTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'ئێستا';
    if ($diff < 3600) return floor($diff / 60) . 'خ';
    if ($diff < 86400) return floor($diff / 3600) . 'ک';
    return floor($diff / 86400) . 'ڕ';
}
