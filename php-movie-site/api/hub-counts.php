<?php
/**
 * Smart Hub API - Returns all badge counts in one request
 * For real-time badge updates without page refresh
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$userId = $_SESSION['user_id'];

// 1. Unread notifications count
$notifCount = getUnreadCount($pdo, $userId);

// 2. Unread DM count
$dmCount = 0;
try {
    $dmStmt = $pdo->prepare("
        SELECT COUNT(*) FROM direct_messages 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $dmStmt->execute([$userId]);
    $dmCount = (int)$dmStmt->fetchColumn();
} catch (Exception $e) {}

// 3. New followers count - only count those after user's last "seen" time
$followCount = 0;
try {
    // Auto-create followers_seen_at column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN followers_seen_at TIMESTAMP NULL DEFAULT NULL");
    } catch (Exception $e) {}
    
    // Get user's last seen time for followers
    $seenStmt = $pdo->prepare("SELECT followers_seen_at FROM users WHERE id = ?");
    $seenStmt->execute([$userId]);
    $seenAt = $seenStmt->fetchColumn();
    
    if ($seenAt) {
        // Count new followers AFTER the last time user clicked "mark as read"
        $fStmt = $pdo->prepare("
            SELECT COUNT(*) FROM follows 
            WHERE following_id = ? AND status = 'active' 
            AND created_at > ?
        ");
        $fStmt->execute([$userId, $seenAt]);
    } else {
        // Never seen - count last 24h
        $fStmt = $pdo->prepare("
            SELECT COUNT(*) FROM follows 
            WHERE following_id = ? AND status = 'active' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $fStmt->execute([$userId]);
    }
    $followCount = (int)$fStmt->fetchColumn();
    
    // Also count pending requests (always show these)
    $pStmt = $pdo->prepare("
        SELECT COUNT(*) FROM follows 
        WHERE following_id = ? AND status = 'pending'
    ");
    $pStmt->execute([$userId]);
    $followCount += (int)$pStmt->fetchColumn();
} catch (Exception $e) {}

// 4. Pending invites count
$inviteCount = 0;
try {
    $iStmt = $pdo->prepare("
        SELECT COUNT(*) FROM watch_party_invites 
        WHERE invitee_id = ? AND status = 'pending'
    ");
    $iStmt->execute([$userId]);
    $inviteCount = (int)$iStmt->fetchColumn();
} catch (Exception $e) {}

echo json_encode([
    'success' => true,
    'notifications' => $notifCount,
    'messages' => $dmCount,
    'followers' => $followCount,
    'invites' => $inviteCount,
    'total' => $notifCount + $inviteCount
]);
