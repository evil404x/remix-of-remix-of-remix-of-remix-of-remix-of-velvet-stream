<?php
/**
 * Inbox API - TikTok style inbox with tabs
 * Returns notifications, follow requests, and invites
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? '';

switch ($tab) {
    case 'notifications':
        $notifs = getNotifications($pdo, $userId, 20);
        // Mark as read
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
        echo json_encode(['success' => true, 'items' => $notifs]);
        break;

    case 'follows':
        // Pending follow requests
        $pending = [];
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.avatar, f.created_at 
                FROM follows f JOIN users u ON f.follower_id = u.id 
                WHERE f.following_id = ? AND f.status = 'pending' 
                ORDER BY f.created_at DESC LIMIT 30
            ");
            $stmt->execute([$userId]);
            $pending = $stmt->fetchAll();
        } catch (Exception $e) {}

        // Recent new followers - only show those AFTER followers_seen_at
        $recent = [];
        try {
            // Get user's followers_seen_at timestamp
            $seenStmt = $pdo->prepare("SELECT followers_seen_at FROM users WHERE id = ?");
            $seenStmt->execute([$userId]);
            $seenAt = $seenStmt->fetchColumn();
            
            if ($seenAt) {
                // Only show followers created AFTER the last "mark as read"
                $stmt2 = $pdo->prepare("
                    SELECT u.id, u.username, u.avatar, f.created_at 
                    FROM follows f JOIN users u ON f.follower_id = u.id 
                    WHERE f.following_id = ? AND f.status = 'active' 
                    AND f.created_at > ?
                    ORDER BY f.created_at DESC LIMIT 20
                ");
                $stmt2->execute([$userId, $seenAt]);
            } else {
                // Never marked as read - show last 48h
                $stmt2 = $pdo->prepare("
                    SELECT u.id, u.username, u.avatar, f.created_at 
                    FROM follows f JOIN users u ON f.follower_id = u.id 
                    WHERE f.following_id = ? AND f.status = 'active' 
                    AND f.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
                    ORDER BY f.created_at DESC LIMIT 20
                ");
                $stmt2->execute([$userId]);
            }
            $recent = $stmt2->fetchAll();
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'pending' => $pending, 'recent' => $recent]);
        break;

    case 'invites':
        $invites = [];
        try {
            $stmt = $pdo->prepare("
                SELECT wpi.*, u.username as inviter_name, m.title as movie_title, wp.room_id
                FROM watch_party_invites wpi
                JOIN users u ON wpi.inviter_id = u.id
                JOIN watch_parties wp ON wpi.room_id = wp.room_id
                JOIN movies m ON wp.movie_id = m.id
                WHERE wpi.invitee_id = ? AND wpi.status = 'pending'
                ORDER BY wpi.created_at DESC LIMIT 20
            ");
            $stmt->execute([$userId]);
            $invites = $stmt->fetchAll();
        } catch (Exception $e) {}
        echo json_encode(['success' => true, 'invites' => $invites]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid tab']);
}