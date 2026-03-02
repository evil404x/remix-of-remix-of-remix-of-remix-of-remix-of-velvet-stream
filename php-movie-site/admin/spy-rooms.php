<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Get active rooms
$rooms = [];
try {
    $rooms = $pdo->query("
        SELECT wp.*, m.title as movie_title, m.poster, u.username as host_name,
        (SELECT COUNT(*) FROM watch_party_members wpm WHERE wpm.room_id = wp.room_id AND wpm.last_seen > DATE_SUB(NOW(), INTERVAL 30 SECOND)) as online_count
        FROM watch_parties wp
        JOIN movies m ON wp.movie_id = m.id
        JOIN users u ON wp.host_user_id = u.id
        WHERE wp.updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY wp.updated_at DESC
    ")->fetchAll();
} catch (Exception $e) {}

// Get room chat if requested
$roomChat = [];
$spyRoomId = $_GET['room_id'] ?? '';
if ($spyRoomId) {
    try {
        $stmt = $pdo->prepare("
            SELECT wpm.message, u.username, wpm.created_at 
            FROM watch_party_messages wpm 
            JOIN users u ON wpm.user_id = u.id 
            WHERE wpm.room_id = ? 
            ORDER BY wpm.created_at DESC LIMIT 50
        ");
        $stmt->execute([$spyRoomId]);
        $roomChat = array_reverse($stmt->fetchAll());
    } catch (Exception $e) {}
    
    // Get members
    $members = [];
    try {
        $stmt = $pdo->prepare("SELECT u.username, wpm.last_seen FROM watch_party_members wpm JOIN users u ON wpm.user_id = u.id WHERE wpm.room_id = ? AND wpm.last_seen > DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        $stmt->execute([$spyRoomId]);
        $members = $stmt->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاودێری ژوورەکان - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>window.SITE_URL='<?= SITE_URL ?>';</script>
</head>
<body class="rtl">
<div class="admin-layout">
    <?php include __DIR__.'/sidebar.php'; ?>
    <button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>

    <main class="admin-main">
        <div class="admin-header"><h1><i class="fas fa-eye text-gold"></i> چاودێری ژوورەکان (Spy Mode)</h1></div>
        
        <?php if ($spyRoomId): ?>
        <!-- Spy View -->
        <a href="<?= adminUrl('spy-rooms.php') ?>" class="btn btn-glass btn-sm" style="margin-bottom:16px;"><i class="fas fa-arrow-right"></i> گەڕانەوە</a>
        
        <div class="panel-card glass" style="margin-bottom:16px;">
            <h3><i class="fas fa-users"></i> ئەندامانی ئۆنلاین (<?= count($members) ?>)</h3>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
                <?php foreach ($members as $m): ?>
                <span style="padding:6px 14px;background:rgba(74,222,128,0.08);border:1px solid rgba(74,222,128,0.2);border-radius:20px;font-size:0.82rem;">
                    <i class="fas fa-circle" style="font-size:6px;color:#4ade80;"></i> <?= clean($m['username']) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="panel-card glass">
            <h3><i class="fas fa-comments"></i> نامەکانی چات</h3>
            <div style="max-height:500px;overflow-y:auto;margin-top:10px;">
                <?php if (empty($roomChat)): ?>
                    <p class="text-gray text-center" style="padding:20px;">هیچ نامەیەک نییە</p>
                <?php else: ?>
                    <?php foreach ($roomChat as $msg): ?>
                    <div style="padding:8px 12px;background:rgba(255,255,255,0.03);border-radius:8px;margin-bottom:6px;font-size:0.85rem;">
                        <strong style="color:#00BCD4;"><?= clean($msg['username']) ?></strong>
                        <span style="color:var(--gray);font-size:0.7rem;margin:0 6px;"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                        <div style="color:var(--gray-light);margin-top:3px;"><?= clean($msg['message']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Rooms List -->
        <?php if (empty($rooms)): ?>
            <div class="panel-card"><p class="text-gray text-center" style="padding:40px;"><i class="fas fa-tv" style="font-size:2rem;display:block;margin-bottom:10px;"></i>هیچ ژوورێکی چالاک نییە</p></div>
        <?php else: ?>
        <div class="panel-card">
            <table class="admin-table">
                <thead><tr><th>فیلم</th><th>هۆست</th><th>ئۆنلاین</th><th>کاتی دروستکردن</th><th>کردار</th></tr></thead>
                <tbody>
                <?php foreach ($rooms as $r): ?>
                <tr>
                    <td><?= clean($r['movie_title']) ?></td>
                    <td><?= clean($r['host_name']) ?></td>
                    <td><span class="text-gold"><?= $r['online_count'] ?></span> کەس</td>
                    <td><?= date('H:i - Y/m/d', strtotime($r['created_at'])) ?></td>
                    <td>
                        <a href="<?= adminUrl('spy-rooms.php?room_id=' . $r['room_id']) ?>" class="btn btn-gold btn-sm"><i class="fas fa-eye"></i> چاودێری</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<?php if ($spyRoomId): ?>
<script>
// Auto-refresh spy view
setInterval(() => location.reload(), 10000);
</script>
<?php endif; ?>
</body>
</html>
