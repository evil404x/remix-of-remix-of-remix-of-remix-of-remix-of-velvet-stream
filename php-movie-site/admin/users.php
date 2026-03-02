<?php
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../includes/functions.php'; requireAdmin();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)$_POST['id']; $action=$_POST['action']??'';
    if ($action==='role') { $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$_POST['role'],$id]); }
    if ($action==='toggle') { $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?")->execute([$id]); }
    if ($action==='delete') { $pdo->prepare("DELETE FROM users WHERE id=? AND role!='admin'")->execute([$id]); }
    if ($action==='ban') {
        $pdo->prepare("UPDATE users SET is_active = 0 WHERE id=? AND role!='admin'")->execute([$id]);
    }
    if ($action==='ip_ban') {
        $ip = clean($_POST['ip'] ?? '');
        $hours = (int)($_POST['hours'] ?? 24);
        if ($ip) {
            $pdo->prepare("INSERT INTO ip_bans (ip_address, ban_until, reason) VALUES (?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?) ON DUPLICATE KEY UPDATE ban_until = DATE_ADD(NOW(), INTERVAL ? HOUR)")
                ->execute([$ip, $hours, 'بلۆکی دەستی لەلایەن ئادمین', $hours]);
        }
    }
    if ($action==='points') {
        $pts = (int)$_POST['points'];
        $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, ?) ON DUPLICATE KEY UPDATE points = points + ?")->execute([$id, max(0,$pts), $pts]);
        $pdo->prepare("UPDATE user_points SET points = GREATEST(points, 0) WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO point_activities (user_id, activity_type, points_earned, description) VALUES (?, 'admin_adjust', ?, 'دەستکاری ئادمین')")->execute([$id, $pts]);
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
}
$users = getUsers($pdo);
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>بەکارهێنەران - <?= SITE_NAME ?></title><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script>window.SITE_URL='<?= SITE_URL ?>';</script></head><body class="rtl">
<div class="admin-layout"><?php include __DIR__.'/sidebar.php'; ?>
<button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
<main class="admin-main">
<div class="admin-header"><h1><i class="fas fa-users"></i> بەکارهێنەران</h1></div>
<div class="panel-card"><table class="admin-table"><thead><tr><th>ناو</th><th>ئیمەیڵ</th><th>ڕۆڵ</th><th>بار</th><th>IP</th><th>خاڵ</th><th>تۆمار</th><th>کردار</th></tr></thead><tbody>
<?php foreach($users as $u): ?>
<tr><td><?= clean($u['username']) ?></td><td><?= clean($u['email']) ?></td>
<td><form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="role"><select name="role" onchange="this.form.submit()" class="filter-select" style="padding:4px 8px;font-size:0.8rem;"><option value="user" <?= $u['role']==='user'?'selected':'' ?>>User</option><option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option></select></form></td>
<td><span class="status-badge <?= $u['is_active']?'status-approved':'status-rejected' ?>"><?= $u['is_active']?'چالاک':'ناچالاک' ?></span></td>
<td><?php
// Get last IP from security logs
$ipStmt = $pdo->prepare("SELECT ip_address FROM security_logs WHERE request_uri LIKE ? ORDER BY created_at DESC LIMIT 1");
$ipStmt->execute(['%user_id=' . $u['id'] . '%']);
$userIp = $ipStmt->fetchColumn();
if (!$userIp) {
    // Fallback: check login patterns
    $ipStmt2 = $pdo->prepare("SELECT ip_address FROM security_logs WHERE user_agent LIKE ? ORDER BY created_at DESC LIMIT 1");
    $ipStmt2->execute(['%' . $u['username'] . '%']);
    $userIp = $ipStmt2->fetchColumn();
}
echo $userIp ? '<small style="font-size:0.72rem;color:var(--gray);">' . clean($userIp) . '</small>' : '<small class="text-gray">-</small>';
?></td>
<td>
<?php
$pts = 0;
try { $ptsStmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ?"); $ptsStmt->execute([$u['id']]); $ptsRow = $ptsStmt->fetch(); if ($ptsRow) $pts = $ptsRow['points']; } catch(Exception $e) {}
?>
<span class="text-gold"><?= $pts ?></span>
<form method="POST" style="display:inline;margin-right:4px;"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="points"><input type="number" name="points" value="10" style="width:50px;padding:2px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:4px;color:var(--white);font-size:0.75rem;"><button class="btn btn-glass btn-xs" title="زیادکردنی خاڵ"><i class="fas fa-plus"></i></button></form>
</td>
<td><?= date('Y/m/d',strtotime($u['created_at'])) ?></td>
<td class="actions">
<form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-glass btn-sm"><i class="fas fa-power-off"></i></button></form>
<?php if($u['role']!=='admin'): ?>
<form method="POST" style="display:inline;" onsubmit="return confirm('بلۆککردن؟')"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="ban"><button class="btn btn-glass btn-sm" title="بلۆک"><i class="fas fa-ban" style="color:var(--orange)"></i></button></form>
<?php if($userIp): ?>
<form method="POST" style="display:inline;" onsubmit="return confirm('بلۆکی IP: <?= clean($userIp) ?>؟')"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="ip_ban"><input type="hidden" name="ip" value="<?= clean($userIp) ?>"><input type="hidden" name="hours" value="24"><button class="btn btn-glass btn-sm" title="بلۆکی IP بۆ ٢٤ کاتژمێر"><i class="fas fa-network-wired" style="color:var(--red)"></i></button></form>
<?php endif; ?>
<form method="POST" style="display:inline;" onsubmit="return confirm('دڵنیایت؟')"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div></main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script></body></html>
