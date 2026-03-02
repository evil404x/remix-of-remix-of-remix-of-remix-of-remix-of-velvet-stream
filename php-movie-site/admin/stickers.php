<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Auto-create stickers table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS stickers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        sort_order SMALLINT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $file = $pdo->prepare("SELECT file_name FROM stickers WHERE id = ?");
        $file->execute([$id]);
        $f = $file->fetchColumn();
        if ($f) @unlink(__DIR__ . '/../uploads/stickers/' . $f);
        $pdo->prepare("DELETE FROM stickers WHERE id = ?")->execute([$id]);
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

$stickers = $pdo->query("SELECT * FROM stickers ORDER BY category, sort_order ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بەڕێوەبردنی ستیکەرەکان - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>window.SITE_URL='<?= SITE_URL ?>';</script>
</head>
<body class="rtl">
<div class="admin-layout">
    <?php include __DIR__.'/sidebar.php'; ?>
    <button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>

    <main class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-smile text-gold"></i> بەڕێوەبردنی ستیکەرەکان</h1>
        </div>

        <!-- Upload Form -->
        <div class="panel-card glass" style="margin-bottom:20px;">
            <h3><i class="fas fa-upload"></i> زیادکردنی ستیکەری نوێ</h3>
            <form id="sticker-upload-form" enctype="multipart/form-data" style="margin-top:14px;">
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
                        <label>ناو</label>
                        <input type="text" name="name" class="form-control" placeholder="ناوی ستیکەر" required>
                    </div>
                    <div class="form-group" style="margin:0;min-width:120px;">
                        <label>کاتیگۆری</label>
                        <select name="category" class="form-control">
                            <option value="general">گشتی</option>
                            <option value="emoji">ئیمۆجی</option>
                            <option value="reaction">ڕیئاکشن</option>
                            <option value="funny">پێکەنینەوە</option>
                            <option value="animated">ئەنیمەیشن</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;min-width:200px;">
                        <label>فایل (GIF, PNG, WebP, WebM - حەدی 5MB)</label>
                        <input type="file" name="sticker_file" class="form-control" accept="image/gif,image/png,image/webp,image/jpeg,video/webm" required>
                    </div>
                    <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> زیادکردن</button>
                </div>
                <div id="sticker-upload-msg" style="margin-top:10px;"></div>
            </form>
        </div>

        <!-- Preview uploaded file -->
        <div class="panel-card glass" style="margin-bottom:20px;display:none;" id="sticker-preview-card">
            <h3><i class="fas fa-eye"></i> پێشبینین</h3>
            <div id="sticker-preview" style="text-align:center;padding:20px;"></div>
        </div>

        <!-- Stickers List -->
        <div class="panel-card">
            <h3 style="margin-bottom:14px;"><i class="fas fa-th"></i> هەموو ستیکەرەکان (<?= count($stickers) ?>)</h3>
            <?php if (empty($stickers)): ?>
                <p class="text-gray text-center" style="padding:30px;">هیچ ستیکەرێک زیاد نەکراوە</p>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:14px;">
                <?php foreach ($stickers as $s): ?>
                <div style="text-align:center;background:rgba(255,255,255,0.03);border-radius:12px;padding:12px;position:relative;">
                    <?php 
                    $ext = pathinfo($s['file_name'], PATHINFO_EXTENSION);
                    if ($ext === 'webm'): ?>
                        <video src="<?= SITE_URL ?>/uploads/stickers/<?= $s['file_name'] ?>" style="width:80px;height:80px;object-fit:contain;margin:0 auto 8px;" autoplay loop muted playsinline></video>
                    <?php else: ?>
                        <img src="<?= SITE_URL ?>/uploads/stickers/<?= $s['file_name'] ?>" style="width:80px;height:80px;object-fit:contain;margin:0 auto 8px;">
                    <?php endif; ?>
                    <div style="font-size:0.78rem;color:var(--gray-light);margin-bottom:4px;"><?= clean($s['name']) ?></div>
                    <div style="font-size:0.68rem;color:var(--gray);"><?= clean($s['category']) ?> <?= $ext === 'webm' ? '🎬' : '' ?></div>
                    <form method="POST" style="position:absolute;top:4px;left:4px;">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-danger btn-sm" style="padding:3px 6px;font-size:0.7rem;" onclick="return confirm('سڕینەوە؟')"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
// Preview file before upload
document.querySelector('input[name="sticker_file"]').addEventListener('change', function() {
    const card = document.getElementById('sticker-preview-card');
    const preview = document.getElementById('sticker-preview');
    if (!this.files[0]) { card.style.display = 'none'; return; }
    card.style.display = '';
    const file = this.files[0];
    const url = URL.createObjectURL(file);
    if (file.type === 'video/webm') {
        preview.innerHTML = `<video src="${url}" style="width:120px;height:120px;object-fit:contain;" autoplay loop muted playsinline></video><p style="margin-top:8px;font-size:0.75rem;color:var(--gray);">WebM ئەنیمەیشن</p>`;
    } else {
        preview.innerHTML = `<img src="${url}" style="width:120px;height:120px;object-fit:contain;"><p style="margin-top:8px;font-size:0.75rem;color:var(--gray);">${file.type}</p>`;
    }
});

document.getElementById('sticker-upload-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const msg = document.getElementById('sticker-upload-msg');
    msg.innerHTML = '<span class="text-gray"><i class="fas fa-spinner fa-spin"></i> بارکردن...</span>';
    
    fetch(`${window.SITE_URL}/api/sticker.php?action=upload`, {
        method: 'POST', body: formData, credentials: 'same-origin'
    }).then(r => r.json()).then(d => {
        if (d.success) {
            msg.innerHTML = '<span style="color:var(--green);"><i class="fas fa-check"></i> ستیکەر زیاد کرا!</span>';
            setTimeout(() => location.reload(), 1000);
        } else {
            msg.innerHTML = '<span style="color:var(--red);"><i class="fas fa-times"></i> ' + (d.message || 'هەڵە') + '</span>';
        }
    });
});
</script>
</body>
</html>
