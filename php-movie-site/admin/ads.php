<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Handle ad actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO ads (title, type, position, content, image_url, target_url, is_active, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            clean($_POST['title'] ?? ''),
            in_array($_POST['type'] ?? '', ['banner','popup']) ? $_POST['type'] : 'banner',
            clean($_POST['position'] ?? 'sidebar'),
            $_POST['content'] ?? '',
            clean($_POST['image_url'] ?? ''),
            clean($_POST['target_url'] ?? ''),
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['start_date'] ?: null,
            $_POST['end_date'] ?: null
        ]);
        $success = 'ڕیکلام زیاد کرا!';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM ads WHERE id = ?")->execute([(int)$_POST['ad_id']]);
        $success = 'ڕیکلام سڕایەوە!';
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE ads SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_POST['ad_id']]);
    }
}

$ads = $pdo->query("SELECT * FROM ads ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بەڕێوەبردنی ڕیکلام - <?= SITE_NAME ?></title>
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
            <h1><i class="fas fa-ad text-gold"></i> بەڕێوەبردنی ڕیکلامەکان</h1>
        </div>

        <!-- Add Ad Form -->
        <div class="glass" style="padding: 25px; margin-bottom: 25px;">
            <h3 style="margin-bottom: 20px; color: var(--gold);">زیادکردنی ڕیکلامی نوێ</h3>
            <form method="POST" class="admin-form">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>ناو</label>
                        <input type="text" name="title" class="form-control" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label>جۆر</label>
                        <select name="type" class="form-control">
                            <option value="banner">Banner</option>
                            <option value="popup">Pop-up</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>شوێن</label>
                        <select name="position" class="form-control">
                            <option value="header">سەرەوە</option>
                            <option value="sidebar">لاتەرەف</option>
                            <option value="footer">خوارەوە</option>
                            <option value="player">لای پلەیەر</option>
                            <option value="popup">پۆپ-ئەپ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>لینکی وێنە</label>
                        <input type="url" name="image_url" class="form-control" placeholder="https://...">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>لینکی ئامانج</label>
                        <input type="url" name="target_url" class="form-control" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; margin-top:25px;">
                            <input type="checkbox" name="is_active" checked> چالاک
                        </label>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>بەرواری دەستپێک</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>بەرواری کۆتایی</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>ناوەڕۆکی HTML (ئیختیاری)</label>
                    <textarea name="content" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-gold"><i class="fas fa-plus"></i> زیادکردن</button>
            </form>
        </div>

        <!-- Ads List -->
        <div class="glass" style="padding: 25px;">
            <h3 style="margin-bottom: 20px; color: var(--gold);">ڕیکلامە بەردەستەکان</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ناو</th>
                        <th>جۆر</th>
                        <th>شوێن</th>
                        <th>بینین</th>
                        <th>کلیک</th>
                        <th>بار</th>
                        <th>کردار</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ads as $ad): ?>
                    <tr>
                        <td><?= clean($ad['title']) ?></td>
                        <td><span class="tag"><?= $ad['type'] ?></span></td>
                        <td><?= $ad['position'] ?></td>
                        <td><?= number_format($ad['impressions']) ?></td>
                        <td><?= number_format($ad['clicks']) ?></td>
                        <td>
                            <span style="color: <?= $ad['is_active'] ? 'var(--green)' : 'var(--red)' ?>">
                                <?= $ad['is_active'] ? 'چالاک' : 'ناچالاک' ?>
                            </span>
                        </td>
                        <td style="display:flex; gap:5px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                <button class="btn btn-glass btn-sm"><i class="fas fa-toggle-on"></i></button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('دڵنیایت؟')">
                                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                <button class="btn btn-glass btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
