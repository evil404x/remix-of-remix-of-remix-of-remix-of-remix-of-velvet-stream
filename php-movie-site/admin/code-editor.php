<?php
/**
 * Admin Code Editor - browse & edit project files directly from the admin panel
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$ROOT = realpath(__DIR__ . '/..');
$msg = '';
$msgType = 'ok';

function safePath($ROOT, $rel) {
    $rel = str_replace('\\', '/', trim($rel, '/'));
    if ($rel === '') return $ROOT;
    if (strpos($rel, '..') !== false) return false;
    $full = realpath($ROOT . '/' . $rel);
    if ($full === false) return false;
    if (strpos($full, $ROOT) !== 0) return false;
    return $full;
}

$editableExt = ['php','js','css','html','htm','json','txt','sql','md','htaccess'];

// Save file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_file'])) {
    $target = safePath($ROOT, $_POST['file'] ?? '');
    if ($target && is_file($target)) {
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        if (in_array($ext, $editableExt) || basename($target) === '.htaccess') {
            // backup
            @copy($target, $target . '.bak');
            $code = $_POST['code'] ?? '';
            if (@file_put_contents($target, $code) !== false) {
                $msg = 'پاشەکەوت کرا ✔ (کۆپیەکی کۆن بە .bak پاراسترا)';
            } else {
                $msg = 'نەتوانرا بنووسرێت — مۆڵەتی فایل بپشکنە'; $msgType = 'err';
            }
        } else { $msg = 'ئەم جۆرە فایلە دەستکاری ناکرێت'; $msgType = 'err'; }
    } else { $msg = 'فایل نەدۆزرایەوە'; $msgType = 'err'; }
}

// Restore backup
if (isset($_GET['restore'])) {
    $target = safePath($ROOT, $_GET['restore']);
    if ($target && is_file($target . '.bak')) {
        @copy($target . '.bak', $target);
        $msg = 'گەڕێنرایەوە بۆ وەشانی کۆن ✔';
    }
}

$dir = $_GET['dir'] ?? '';
$dirFull = safePath($ROOT, $dir);
if (!$dirFull || !is_dir($dirFull)) { $dirFull = $ROOT; $dir = ''; }

$file = $_GET['file'] ?? '';
$fileFull = $file ? safePath($ROOT, $file) : false;
$content = '';
if ($fileFull && is_file($fileFull)) {
    $content = file_get_contents($fileFull);
} else { $fileFull = false; }

$items = [];
foreach (scandir($dirFull) as $it) {
    if ($it === '.' ) continue;
    if ($it === '..' && $dirFull === $ROOT) continue;
    $items[] = $it;
}
usort($items, function($a,$b) use ($dirFull) {
    $ad = is_dir($dirFull.'/'.$a); $bd = is_dir($dirFull.'/'.$b);
    if ($ad !== $bd) return $ad ? -1 : 1;
    return strcasecmp($a,$b);
});
$relOf = function($name) use ($dir) { return trim($dir . '/' . $name, '/'); };
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>دەستکاری کۆد - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script>window.SITE_URL='<?= SITE_URL ?>';</script>
<style>
.ce-wrap{display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start;}
@media(max-width:900px){.ce-wrap{grid-template-columns:1fr;}}
.ce-list{max-height:70vh;overflow:auto;}
.ce-list a{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;color:var(--white);text-decoration:none;font-size:.85rem;}
.ce-list a:hover{background:rgba(255,255,255,.06);}
.ce-list a.active{background:rgba(212,175,55,.15);color:var(--gold);}
.ce-editor{width:100%;min-height:65vh;background:#0d0f14;color:#e6e6e6;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:14px;font-family:ui-monospace,Consolas,monospace;font-size:13px;line-height:1.6;direction:ltr;text-align:left;white-space:pre;overflow:auto;tab-size:4;}
.ce-msg{padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:.9rem;}
.ce-msg.ok{background:rgba(46,204,113,.12);color:#2ecc71;}
.ce-msg.err{background:rgba(231,76,60,.12);color:#e74c3c;}
.ce-path{font-family:monospace;direction:ltr;color:var(--gray);font-size:.8rem;}
</style></head><body class="rtl">
<div class="admin-layout"><?php include __DIR__.'/sidebar.php'; ?>
<button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
<main class="admin-main">
<div class="admin-header"><h1><i class="fas fa-code"></i> دەستکاری کۆد</h1><span class="ce-path"><?= clean('/'.$dir) ?></span></div>
<?php if($msg): ?><div class="ce-msg <?= $msgType ?>"><?= clean($msg) ?></div><?php endif; ?>
<div class="ce-wrap">
  <div class="panel-card ce-list">
    <h3 style="font-size:.95rem;"><i class="fas fa-folder-open"></i> فایلەکان</h3>
    <?php foreach($items as $it):
        $path = $dirFull.'/'.$it;
        if ($it === '..') { $rel = trim(dirname($dir) === '.' ? '' : dirname($dir), '/'); } else { $rel = $relOf($it); }
        $isDir = is_dir($path);
        $ext = strtolower(pathinfo($it, PATHINFO_EXTENSION));
        $canEdit = in_array($ext, $editableExt) || $it === '.htaccess';
    ?>
      <?php if($isDir): ?>
        <a href="?dir=<?= urlencode($rel) ?>"><i class="fas fa-folder text-gold"></i> <?= clean($it) ?></a>
      <?php elseif($canEdit): ?>
        <a href="?dir=<?= urlencode($dir) ?>&file=<?= urlencode($relOf($it)) ?>" class="<?= ($file===$relOf($it))?'active':'' ?>"><i class="fas fa-file-code"></i> <?= clean($it) ?></a>
      <?php else: ?>
        <a style="opacity:.4;cursor:default;" onclick="return false;"><i class="fas fa-file"></i> <?= clean($it) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <div class="panel-card">
    <?php if($fileFull): ?>
      <form method="POST">
        <input type="hidden" name="file" value="<?= clean($file) ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
          <strong class="ce-path"><?= clean($file) ?></strong>
          <div style="display:flex;gap:8px;">
            <?php if(is_file($fileFull.'.bak')): ?>
              <a href="?dir=<?= urlencode($dir) ?>&file=<?= urlencode($file) ?>&restore=<?= urlencode($file) ?>" class="btn btn-glass btn-sm" onclick="return confirm('گەڕاندنەوە بۆ وەشانی کۆن؟')"><i class="fas fa-undo"></i> گەڕاندنەوە</a>
            <?php endif; ?>
            <button name="save_file" value="1" class="btn btn-gold btn-sm"><i class="fas fa-save"></i> پاشەکەوت</button>
          </div>
        </div>
        <textarea name="code" class="ce-editor" spellcheck="false"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>
      </form>
    <?php else: ?>
      <p class="text-gray">فایلێک هەڵبژێرە بۆ دەستکاریکردن.</p>
    <?php endif; ?>
  </div>
</div>
</main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
// Tab key inserts a tab instead of moving focus
document.querySelectorAll('.ce-editor').forEach(function(ta){
  ta.addEventListener('keydown', function(e){
    if(e.key === 'Tab'){ e.preventDefault();
      var s=this.selectionStart, en=this.selectionEnd;
      this.value = this.value.substring(0,s) + '    ' + this.value.substring(en);
      this.selectionStart = this.selectionEnd = s + 4;
    }
    if((e.ctrlKey||e.metaKey) && e.key === 's'){ e.preventDefault(); this.form.querySelector('[name=save_file]').click(); }
  });
});
</script>
</body></html>
