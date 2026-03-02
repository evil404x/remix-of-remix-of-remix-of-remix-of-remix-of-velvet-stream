<?php
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../includes/functions.php'; requireAdmin();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';
    
    if ($action==='add') {
        // Handle image upload
        $imageUrl = clean($_POST['image_url'] ?? '');
        if (!empty($_FILES['image_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext, $allowed)) {
                $filename = 'slider_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $uploadDir = dirname(__DIR__) . '/uploads/sliders/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename)) {
                    $imageUrl = SITE_URL . '/uploads/sliders/' . $filename;
                }
            }
        }
        
        $pdo->prepare("INSERT INTO sliders (movie_id,title,description,image_url,link_url,sort_order) VALUES (?,?,?,?,?,?)")
            ->execute([
                $_POST['movie_id'] ?: null,
                clean($_POST['title'] ?? ''),
                clean($_POST['description'] ?? ''),
                $imageUrl,
                clean($_POST['link_url'] ?? ''),
                (int)($_POST['sort_order'] ?? 0)
            ]);
    }
    if ($action==='delete') { $pdo->prepare("DELETE FROM sliders WHERE id=?")->execute([(int)$_POST['id']]); }
    if ($action==='toggle') { $pdo->prepare("UPDATE sliders SET is_active = NOT is_active WHERE id=?")->execute([(int)$_POST['id']]); }
    if ($action==='edit') {
        $imageUrl = clean($_POST['image_url'] ?? '');
        if (!empty($_FILES['image_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext, $allowed)) {
                $filename = 'slider_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $uploadDir = dirname(__DIR__) . '/uploads/sliders/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename)) {
                    $imageUrl = SITE_URL . '/uploads/sliders/' . $filename;
                }
            }
        }
        $pdo->prepare("UPDATE sliders SET title=?, description=?, image_url=?, link_url=?, movie_id=?, sort_order=? WHERE id=?")
            ->execute([
                clean($_POST['title'] ?? ''),
                clean($_POST['description'] ?? ''),
                $imageUrl,
                clean($_POST['link_url'] ?? ''),
                $_POST['movie_id'] ?: null,
                (int)($_POST['sort_order'] ?? 0),
                (int)$_POST['id']
            ]);
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
}

try { $sliders = $pdo->query("SELECT s.*,m.title as movie_title FROM sliders s LEFT JOIN movies m ON s.movie_id=m.id ORDER BY s.sort_order")->fetchAll(); } catch(Exception $e) { $sliders=[]; }
$movies = $pdo->query("SELECT id,title FROM movies ORDER BY title")->fetchAll();
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>سڵایدەر - <?= SITE_NAME ?></title><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script>window.SITE_URL='<?= SITE_URL ?>';</script></head><body class="rtl">
<div class="admin-layout"><?php include __DIR__.'/sidebar.php'; ?>
<button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
<main class="admin-main">
<div class="admin-header"><h1><i class="fas fa-images"></i> سڵایدەر</h1></div>

<!-- Add Slider -->
<div class="panel-card"><h3><i class="fas fa-plus"></i> زیادکردنی سڵاید</h3>
<form method="POST" class="admin-form" enctype="multipart/form-data">
<input type="hidden" name="action" value="add">
<div class="form-row">
    <div class="form-group"><label>سەرناو</label><input type="text" name="title" class="form-control" required></div>
    <div class="form-group"><label>فیلم (ئارەزوومەندانە)</label><select name="movie_id" class="form-control"><option value="">هیچ</option><?php foreach($movies as $m): ?><option value="<?= $m['id'] ?>"><?= clean($m['title']) ?></option><?php endforeach; ?></select></div>
</div>
<div class="form-group"><label>وەسف</label><textarea name="description" class="form-control" rows="2" placeholder="وەسفی سڵایدەکە..."></textarea></div>
<div class="form-row">
    <div class="form-group"><label>وێنە (فایل)</label><input type="file" name="image_file" class="form-control" accept="image/*"></div>
    <div class="form-group"><label>یان وێنە URL</label><input type="url" name="image_url" class="form-control" placeholder="https://..."></div>
</div>
<div class="form-row">
    <div class="form-group"><label>لینک</label><input type="url" name="link_url" class="form-control"></div>
    <div class="form-group"><label>ڕیزبەندی</label><input type="number" name="sort_order" class="form-control" value="0"></div>
</div>
<button class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> زیادکردن</button>
</form></div>

<!-- Slider List -->
<div class="panel-card"><h3><i class="fas fa-list"></i> سڵایدەکان (<?= count($sliders) ?>)</h3>
<?php if(empty($sliders)): ?>
<p class="text-center text-gray" style="padding:20px;">هیچ سڵایدێک نییە</p>
<?php else: ?>
<div style="display:grid;gap:16px;">
<?php foreach($sliders as $s): ?>
<div style="display:flex;gap:16px;align-items:center;padding:16px;border-radius:var(--radius-sm);background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
    <?php if($s['image_url']): ?>
    <img src="<?= clean($s['image_url']) ?>" alt="" style="width:120px;height:68px;object-fit:cover;border-radius:8px;flex-shrink:0;">
    <?php endif; ?>
    <div style="flex:1;min-width:0;">
        <strong style="color:var(--white);font-size:0.95rem;"><?= clean($s['title'] ?? 'بێ سەرناو') ?></strong>
        <?php if($s['description']): ?><p style="color:var(--gray);font-size:0.82rem;margin-top:4px;"><?= clean($s['description']) ?></p><?php endif; ?>
        <div style="display:flex;gap:10px;margin-top:6px;font-size:0.78rem;color:var(--gray);">
            <span><i class="fas fa-film"></i> <?= clean($s['movie_title'] ?? '-') ?></span>
            <span><i class="fas fa-sort"></i> ڕیزبەندی: <?= $s['sort_order'] ?></span>
        </div>
    </div>
    <div style="display:flex;gap:6px;flex-shrink:0;">
        <span class="status-badge <?= $s['is_active'] ? 'status-approved' : 'status-rejected' ?>"><?= $s['is_active'] ? 'چالاک' : 'ناچالاک' ?></span>
        <form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-glass btn-sm" title="چالاک/ناچالاک"><i class="fas fa-power-off"></i></button></form>
        <form method="POST" style="display:inline;" onsubmit="return confirm('دڵنیایت لە سڕینەوە؟')"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

</main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script></body></html>
