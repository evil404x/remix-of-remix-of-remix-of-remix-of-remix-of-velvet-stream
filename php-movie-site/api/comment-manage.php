<?php
/**
 * Comment Management API - Edit/Delete own comments
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';

switch ($action) {
    case 'delete':
        $commentId = (int)($data['comment_id'] ?? 0);
        if (!$commentId) { echo json_encode(['success' => false, 'message' => 'Missing comment_id']); exit; }
        
        // Only own comments or admin
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if (!$comment) { echo json_encode(['success' => false, 'message' => 'Comment not found']); exit; }
        if ($comment['user_id'] != $userId && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }
        
        $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$commentId]);
        echo json_encode(['success' => true]);
        break;

    case 'edit':
        $commentId = (int)($data['comment_id'] ?? 0);
        $content = clean($data['content'] ?? '');
        if (!$commentId || !$content) { echo json_encode(['success' => false, 'message' => 'Missing data']); exit; }
        
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if (!$comment || $comment['user_id'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }
        
        $pdo->prepare("UPDATE comments SET content = ? WHERE id = ? AND user_id = ?")->execute([$content, $commentId, $userId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
