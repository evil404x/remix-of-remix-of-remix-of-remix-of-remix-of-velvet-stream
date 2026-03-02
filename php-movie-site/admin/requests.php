<?php
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../includes/functions.php'; requireAdmin();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)$_POST['id']; $action=$_POST['action']??'';
    if ($action==='status') { $pdo->prepare("UPDATE movie_requests SET status=?,admin_reply=? WHERE id=?")->execute([$_POST['status'],$_POST['reply']??'',$id]); }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
}
$requests = getRequests($pdo);
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>داواکاریەکان - <?= SITE_NAME ?></title><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script>window.SITE_URL='<?= SITE_URL ?>';</script></head><body class="rtl">
<div class="admin-layout"><?php include __DIR__.'/sidebar.php'; ?>
<button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
<main class="admin-main">
<div class="admin-header"><h1><i class="fas fa-inbox"></i> داواکاریەکان</h1></div>
<?php foreach($requests as $r): ?>
<div class="panel-card">
<div class="flex-between" style="flex-wrap:wrap;gap:10px;">
<div><strong style="font-size:1.05rem;"><?= clean($r['movie_name']) ?></strong>
<span class="status-badge status-<?= $r['status'] ?>" style="margin-right:10px;"><?= $r['status'] ?></span>
<br><small class="text-gray">لەلایەن: <?= clean($r['username']??'میوان') ?> • <?= date('Y/m/d',strtotime($r['created_at'])) ?></small>
<?php if($r['imdb_link']): ?><br><a href="<?= clean($r['imdb_link']) ?>" target="_blank" class="text-gold" style="font-size:0.85rem;">IMDb Link</a><?php endif; ?>
<?php if($r['message']): ?><p style="margin-top:8px;color:var(--gray-light);font-size:0.9rem;"><?= clean($r['message']) ?></p><?php endif; ?>
</div>
<form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
<input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="status">
<select name="status" class="filter-select" style="padding:6px 10px;"><option value="pending">pending</option><option value="approved">approved</option><option value="completed">completed</option><option value="rejected">rejected</option></select>
<input type="text" name="reply" class="form-control" placeholder="وەڵام..." style="width:200px;padding:6px 10px;">
<button class="btn btn-gold btn-sm"><i class="fas fa-check"></i></button>
</form></div></div>
<?php endforeach; ?>
</main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script></body></html>
