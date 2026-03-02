<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Auto-create staff table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        role_title VARCHAR(255) NOT NULL,
        bio TEXT,
        avatar VARCHAR(255),
        sort_order SMALLINT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = clean($_POST['name'] ?? '');
        $roleTitle = clean($_POST['role_title'] ?? '');
        $bio = clean($_POST['bio'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);
        
        $avatar = '';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatar = secureUpload($_FILES['avatar'], __DIR__ . '/../uploads/staff');
        }
        
        if ($name && $roleTitle) {
            $pdo->prepare("INSERT INTO staff (name, role_title, bio, avatar, sort_order) VALUES (?, ?, ?, ?, ?)")
                ->execute([$name, $roleTitle, $bio, $avatar, $sort]);
            $success = 'ستاف زیاد کرا!';
        }
    }
    
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([(int)$_POST['id']]);
        $success = 'سڕایەوە!';
    }
    
    if ($action === 'toggle') {
        $pdo->prepare("UPDATE staff SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_POST['id']]);
    }
    
    if ($success || $action) { header('Location: ' . $_SERVER['REQUEST_URI']); exit; }
}

$staffList = $pdo->query("SELECT * FROM staff ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ستافی وێبسایت - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>window.SITE_URL='<?= SITE_URL ?>';</script>
</head>
<body class="rtl">
<div class="admin-layout">
    <?php include __DIR__.'/sidebar.php'; ?>
    <button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>

    <main class="admin-main">
        <div class="admin-header"><h1><i class="fas fa-id-badge text-gold"></i> ستافی وێبسایت</h1></div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <!-- Add Staff Form -->
        <div class="panel-card glass" style="margin-bottom:20px;">
            <h3><i class="fas fa-plus"></i> زیادکردنی ستاف</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top:14px;">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group"><label>ناو</label><input type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label>ڕۆڵ / پلە</label><input type="text" name="role_title" class="form-control" required placeholder="بەڕێوەبەری گشتی"></div>
                </div>
                <div class="form-group"><label>باسکردن</label><textarea name="bio" class="form-control" rows="2"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>وێنە</label><input type="file" name="avatar" class="form-control" accept="image/*"></div>
                    <div class="form-group"><label>ڕیزبەندی</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                </div>
                <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> زیادکردن</button>
            </form>
        </div>

        <!-- Staff List -->
        <div class="panel-card">
            <h3><i class="fas fa-users-cog"></i> لیستی ستاف (<?= count($staffList) ?>)</h3>
            <?php if (empty($staffList)): ?>
                <p class="text-gray text-center" style="padding:30px;">هیچ ستافێک زیاد نەکراوە</p>
            <?php else: ?>
            <table class="admin-table">
                <thead><tr><th>وێنە</th><th>ناو</th><th>ڕۆڵ</th><th>بار</th><th>کردار</th></tr></thead>
                <tbody>
                <?php foreach ($staffList as $s): ?>
                <tr>
                    <td><?= $s['avatar'] ? '<img src="'.SITE_URL.'/uploads/staff/'.$s['avatar'].'" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">' : '<i class="fas fa-user" style="font-size:1.5rem;color:var(--gray);"></i>' ?></td>
                    <td><?= clean($s['name']) ?></td>
                    <td><?= clean($s['role_title']) ?></td>
                    <td><span class="status-badge <?= $s['is_active'] ? 'status-approved' : 'status-rejected' ?>"><?= $s['is_active'] ? 'چالاک' : 'ناچالاک' ?></span></td>
                    <td class="actions">
                        <form method="POST" style="display:inline;"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-glass btn-sm"><i class="fas fa-power-off"></i></button></form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('دڵنیایت؟')"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
