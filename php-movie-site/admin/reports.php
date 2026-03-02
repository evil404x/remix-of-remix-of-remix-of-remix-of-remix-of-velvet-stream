<?php
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../includes/functions.php'; requireAdmin();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)$_POST['id']; $action=$_POST['action']??'';
    if ($action==='resolve') { $pdo->prepare("UPDATE reports SET status='resolved',admin_note=? WHERE id=?")->execute([$_POST['note']??'',$id]); }
    if ($action==='dismiss') { $pdo->prepare("UPDATE reports SET status='dismissed' WHERE id=?")->execute([$id]); }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
}
$reports = getReports($pdo);
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>ڕاپۆرتەکان - <?= SITE_NAME ?></title><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script>window.SITE_URL='<?= SITE_URL ?>';</script></head><body class="rtl">
<div class="admin-layout"><?php include __DIR__.'/sidebar.php'; ?>
<button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
<main class="admin-main">
<div class="admin-header"><h1><i class="fas fa-flag"></i> ڕاپۆرتی ڤیدیۆ</h1></div>
<?php if(empty($reports)): ?><div class="panel-card"><p class="text-center text-gray" style="padding:30px;">هیچ ڕاپۆرتێک نییە 🎉</p></div>
<?php else: ?>
<div class="panel-card"><table class="admin-table"><thead><tr><th>فیلم</th><th>بەکارهێنەر</th><th>هۆکار</th><th>بار</th><th>بەروار</th><th>کردار</th></tr></thead><tbody>
<?php foreach($reports as $r): ?>
<tr><td><?= clean($r['movie_title']??'') ?></td><td><?= clean($r['username']??'میوان') ?></td><td><?= clean($r['reason']??'') ?></td>
<td><span class="status-badge status-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
<td><?= date('Y/m/d',strtotime($r['created_at'])) ?></td>
<td class="actions">
<?php if($r['status']==='pending'): ?>
<form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="resolve"><button class="btn btn-gold btn-sm"><i class="fas fa-check"></i></button></form>
<form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="dismiss"><button class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button></form>
<?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script></body></html>
