<?php
/**
 * AI Agent - یاریدەدەری زیرەکی ئۆتۆماتیک
 * دەتوانێت خۆی فایلەکان بخوێنێتەوە، بگەڕێت، بنووسێت و دروست بکات (Full-Stack Agent)
 * Works with any OpenAI-compatible API (OpenAI, OpenRouter, Groq, ...)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

@set_time_limit(0);

$ROOT = realpath(__DIR__ . '/..');
$CFG_FILE = __DIR__ . '/ai-config.json';
$cfg = ['api_key' => '', 'base_url' => 'https://api.openai.com/v1', 'model' => 'gpt-4o-mini'];
if (is_file($CFG_FILE)) {
    $saved = json_decode(file_get_contents($CFG_FILE), true);
    if (is_array($saved)) $cfg = array_merge($cfg, $saved);
}

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cfg'])) {
    $cfg['api_key']  = trim($_POST['api_key'] ?? '');
    $cfg['base_url'] = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $cfg['model']    = trim($_POST['model'] ?? '');
    file_put_contents($CFG_FILE, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    @chmod($CFG_FILE, 0600);
    $notice = 'ڕێکخستنەکان پاشەکەوت کران ✔';
}

/* ---------------- Agent file tools ---------------- */
$EDITABLE = ['php','js','css','html','htm','json','txt','sql','md','htaccess'];
$SKIP_DIRS = ['uploads', '.git', 'node_modules', 'vendor'];

function ai_safe($rel) {
    global $ROOT;
    $rel = str_replace('\\', '/', trim((string)$rel, '/'));
    if ($rel === '' ) return $ROOT;
    if (strpos($rel, '..') !== false) return false;
    $full = $ROOT . '/' . $rel;
    $real = realpath($full);
    if ($real === false) { // new file: parent must exist inside root
        $parent = realpath(dirname($full));
        if ($parent === false || strpos($parent, $ROOT) !== 0) return false;
        return $full;
    }
    if (strpos($real, $ROOT) !== 0) return false;
    return $real;
}

function ai_tree($dir = '', $depth = 0) {
    global $ROOT, $SKIP_DIRS;
    $out = [];
    $base = ai_safe($dir);
    if (!$base || !is_dir($base)) return $out;
    foreach (scandir($base) as $it) {
        if ($it === '.' || $it === '..') continue;
        if (in_array($it, $SKIP_DIRS)) continue;
        $rel = trim($dir . '/' . $it, '/');
        if (is_dir($base . '/' . $it)) {
            $out[] = $rel . '/';
            if ($depth < 3) $out = array_merge($out, ai_tree($rel, $depth + 1));
        } else {
            $out[] = $rel;
        }
    }
    return $out;
}

function ai_tool_run($name, $args) {
    global $ROOT, $EDITABLE;
    switch ($name) {
        case 'list_files':
            $files = ai_tree($args['dir'] ?? '');
            return "FILES:\n" . implode("\n", $files);

        case 'read_file':
            $p = ai_safe($args['path'] ?? '');
            if (!$p || !is_file($p)) return 'ERROR: file not found: ' . ($args['path'] ?? '');
            $c = file_get_contents($p);
            if (strlen($c) > 60000) $c = substr($c, 0, 60000) . "\n...[truncated]";
            return $c;

        case 'search_code':
            $q = (string)($args['query'] ?? '');
            if ($q === '') return 'ERROR: empty query';
            $hits = [];
            foreach (ai_tree('') as $rel) {
                if (substr($rel, -1) === '/') continue;
                $p = ai_safe($rel);
                if (!$p || !is_file($p) || filesize($p) > 2000000) continue;
                $lines = @file($p);
                if (!$lines) continue;
                foreach ($lines as $i => $line) {
                    if (stripos($line, $q) !== false) {
                        $hits[] = $rel . ':' . ($i + 1) . ': ' . trim(substr($line, 0, 200));
                        if (count($hits) >= 80) break 2;
                    }
                }
            }
            return $hits ? implode("\n", $hits) : 'NO MATCHES';

        case 'write_file':
            $rel = $args['path'] ?? '';
            $p = ai_safe($rel);
            if (!$p) return 'ERROR: invalid path';
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            if (!in_array($ext, $EDITABLE) && basename($p) !== '.htaccess') return 'ERROR: file type not allowed';
            if (is_file($p)) @copy($p, $p . '.bak');
            if (!is_dir(dirname($p))) @mkdir(dirname($p), 0777, true);
            $ok = @file_put_contents($p, (string)($args['content'] ?? ''));
            return $ok === false ? 'ERROR: write failed (permissions?)' : 'OK: saved ' . $rel . ' (' . $ok . ' bytes)';

        case 'replace_in_file':
            $p = ai_safe($args['path'] ?? '');
            if (!$p || !is_file($p)) return 'ERROR: file not found';
            $c = file_get_contents($p);
            $old = (string)($args['find'] ?? '');
            if ($old === '' || strpos($c, $old) === false) return 'ERROR: text not found in file';
            @copy($p, $p . '.bak');
            $c = str_replace($old, (string)($args['replace'] ?? ''), $c);
            $ok = @file_put_contents($p, $c);
            return $ok === false ? 'ERROR: write failed' : 'OK: replaced in ' . $args['path'];

        case 'db_schema':
            try {
                $pdo = db();
                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                $out = [];
                foreach ($tables as $t) {
                    $cols = $pdo->query('SHOW COLUMNS FROM `' . $t . '`')->fetchAll(PDO::FETCH_ASSOC);
                    $names = array_map(function ($c) { return $c['Field'] . ' ' . $c['Type']; }, $cols);
                    $out[] = $t . ': ' . implode(', ', $names);
                }
                return implode("\n", $out);
            } catch (Throwable $e) { return 'ERROR: ' . $e->getMessage(); }

        case 'run_sql':
            $sql = trim((string)($args['sql'] ?? ''));
            if ($sql === '') return 'ERROR: empty sql';
            if (preg_match('/^\s*(drop\s+database|drop\s+table\s+users)/i', $sql)) return 'ERROR: dangerous statement blocked';
            try {
                $pdo = db();
                if (preg_match('/^\s*select/i', $sql)) {
                    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                    return json_encode(array_slice($rows, 0, 50), JSON_UNESCAPED_UNICODE);
                }
                $n = $pdo->exec($sql);
                return 'OK: affected ' . $n;
            } catch (Throwable $e) { return 'ERROR: ' . $e->getMessage(); }
    }
    return 'ERROR: unknown tool';
}

function ai_tools_spec() {
    return [
        ['type'=>'function','function'=>['name'=>'list_files','description'=>'List all project files and folders','parameters'=>['type'=>'object','properties'=>['dir'=>['type'=>'string','description'=>'relative folder, empty for root']],'required'=>[]]]],
        ['type'=>'function','function'=>['name'=>'read_file','description'=>'Read the full contents of a project file','parameters'=>['type'=>'object','properties'=>['path'=>['type'=>'string']],'required'=>['path']]]],
        ['type'=>'function','function'=>['name'=>'search_code','description'=>'Search all project files for a text/keyword','parameters'=>['type'=>'object','properties'=>['query'=>['type'=>'string']],'required'=>['query']]]],
        ['type'=>'function','function'=>['name'=>'write_file','description'=>'Create or overwrite a file with full content (auto .bak backup)','parameters'=>['type'=>'object','properties'=>['path'=>['type'=>'string'],'content'=>['type'=>'string']],'required'=>['path','content']]]],
        ['type'=>'function','function'=>['name'=>'replace_in_file','description'=>'Replace an exact text snippet inside a file','parameters'=>['type'=>'object','properties'=>['path'=>['type'=>'string'],'find'=>['type'=>'string'],'replace'=>['type'=>'string']],'required'=>['path','find','replace']]]],
        ['type'=>'function','function'=>['name'=>'db_schema','description'=>'Get all database tables and columns','parameters'=>['type'=>'object','properties'=>new stdClass(),'required'=>[]]]],
        ['type'=>'function','function'=>['name'=>'run_sql','description'=>'Run an SQL statement (SELECT returns rows, others return affected count)','parameters'=>['type'=>'object','properties'=>['sql'=>['type'=>'string']],'required'=>['sql']]]],
    ];
}

function ai_call($cfg, $messages) {
    $payload = [
        'model' => $cfg['model'],
        'messages' => $messages,
        'tools' => ai_tools_spec(),
        'tool_choice' => 'auto',
    ];
    $ch = curl_init($cfg['base_url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json','Authorization: Bearer ' . $cfg['api_key']],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) return ['error' => 'هەڵەی پەیوەندی: ' . $err];
    $data = json_decode($res, true);
    if ($code >= 400) return ['error' => 'هەڵە (' . $code . '): ' . ($data['error']['message'] ?? substr((string)$res, 0, 300))];
    return ['data' => $data];
}

// AJAX agent endpoint
if (isset($_GET['ask'])) {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $history = $body['messages'] ?? [];
    if (!$cfg['api_key']) { echo json_encode(['error' => 'کلیلی API دانەنراوە. لە خوارەوە دایبنێ.']); exit; }

    $system = "تۆ ئەندازیارێکی Full-Stack ی زیرەکیت کە بەڕێوەبەری وێبسایتێکی فیلمی PHP/MySQL (XAMPP/KSWeb) ـیت.\n"
        . "زۆر گرنگ: بەکارهێنەر ناوی فایل یان شوێنی دەستکاری پێت نادات — تۆ خۆت دەبێت بگەڕێیت و بیدۆزیتەوە.\n"
        . "هەنگاوەکان: (1) list_files یان search_code بەکاربهێنە بۆ دۆزینەوەی فایلە پەیوەندیدارەکان، (2) read_file بۆ خوێندنەوەی کۆدی ئێستا، (3) db_schema ئەگەر پەیوەندی بە داتابەیس هەیە، (4) write_file یان replace_in_file بۆ ئەنجامدانی گۆڕانکاری، (5) run_sql بۆ گۆڕینی داتابەیس ئەگەر پێویست بوو.\n"
        . "هەمیشە گۆڕانکاری تەواو ئەنجام بدە: باکئێند + فرۆنتئێند + CSS + داتابەیس، هەموویان پێکەوە ببەستەوە تاکو تایبەتمەندییەکە بە تەواوی کار بکات.\n"
        . "هەرگیز داوا لە بەکارهێنەر مەکە کۆد کۆپی بکات — خۆت فایلەکان بنووسە.\n"
        . "کاتێک فایلێک دەنووسیت بە write_file، ناوەڕۆکی تەواوی فایلەکە بنووسە (نەک بەشێکی).\n"
        . "لە کۆتاییدا بە کوردی سۆرانی، بە کورتی، ڕوون بکەرەوە چیت کرد و کام فایلانەت گۆڕی.";

    $messages = array_merge([['role' => 'system', 'content' => $system]], $history);
    $actions = [];

    for ($step = 0; $step < 12; $step++) {
        $r = ai_call($cfg, $messages);
        if (isset($r['error'])) { echo json_encode(['error' => $r['error'], 'actions' => $actions]); exit; }
        $msg = $r['data']['choices'][0]['message'] ?? null;
        if (!$msg) { echo json_encode(['error' => 'وەڵامی نادروست', 'actions' => $actions]); exit; }

        $calls = $msg['tool_calls'] ?? [];
        if (!$calls) {
            echo json_encode(['reply' => $msg['content'] ?? '(وەڵامی بەتاڵ)', 'actions' => $actions]);
            exit;
        }

        $messages[] = ['role' => 'assistant', 'content' => $msg['content'] ?? '', 'tool_calls' => $calls];
        foreach ($calls as $c) {
            $fname = $c['function']['name'] ?? '';
            $fargs = json_decode($c['function']['arguments'] ?? '{}', true) ?: [];
            $result = ai_tool_run($fname, $fargs);
            $label = $fname . (isset($fargs['path']) ? ' → ' . $fargs['path'] : (isset($fargs['query']) ? ' → ' . $fargs['query'] : ''));
            $actions[] = $label . (strpos($result, 'ERROR') === 0 ? ' ✖' : ' ✔');
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $c['id'] ?? '',
                'content' => is_string($result) ? $result : json_encode($result),
            ];
        }
    }
    echo json_encode(['reply' => 'ژمارەی هەنگاوەکان زۆر بوو، تکایە داواکارییەکە بەش بەش بکە.', 'actions' => $actions]);
    exit;
}
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ئەیجێنتی AI - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script>window.SITE_URL='<?= SITE_URL ?>';</script>
<style>
.ai-chat{display:flex;flex-direction:column;height:66vh;}
.ai-msgs{flex:1;overflow:auto;padding:6px;display:flex;flex-direction:column;gap:12px;}
.ai-msg{max-width:88%;padding:12px 14px;border-radius:14px;font-size:.92rem;line-height:1.8;white-space:pre-wrap;word-break:break-word;}
.ai-msg.me{align-self:flex-start;background:rgba(212,175,55,.14);border:1px solid rgba(212,175,55,.25);}
.ai-msg.bot{align-self:flex-end;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);}
.ai-msg pre{direction:ltr;text-align:left;background:#0d0f14;padding:12px;border-radius:10px;overflow:auto;font-size:12.5px;margin:8px 0;}
.ai-acts{align-self:flex-end;max-width:88%;font-size:.78rem;color:var(--gray);direction:ltr;text-align:left;background:rgba(255,255,255,.03);border:1px dashed rgba(255,255,255,.12);border-radius:10px;padding:8px 10px;white-space:pre-wrap;}
.ai-form{display:flex;gap:8px;margin-top:12px;}
.ai-form textarea{flex:1;min-height:56px;max-height:180px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;color:var(--white);padding:12px;font-family:inherit;resize:vertical;}
.ai-note{padding:10px 14px;border-radius:8px;background:rgba(46,204,113,.12);color:#2ecc71;margin-bottom:12px;font-size:.9rem;}
.ai-cfg input{width:100%;padding:10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:var(--white);margin-bottom:10px;direction:ltr;text-align:left;}
.ai-cfg label{display:block;font-size:.82rem;color:var(--gray);margin-bottom:4px;}
.ai-typing{opacity:.6;font-size:.85rem;}
</style></head><body class="rtl">
<div class="admin-layout"><?php include __DIR__.'/sidebar.php'; ?>
<button class="admin-toggle btn btn-gold btn-sm"><i class="fas fa-bars"></i></button>
<main class="admin-main">
<div class="admin-header"><h1><i class="fas fa-robot"></i> ئەیجێنتی زیرەک (Full-Stack)</h1></div>
<?php if($notice): ?><div class="ai-note"><?= clean($notice) ?></div><?php endif; ?>

<div class="panel-card">
  <div class="ai-chat">
    <div class="ai-msgs" id="aiMsgs">
      <div class="ai-msg bot">سڵاو 👋 من ئەیجێنتی فول-ستاکی سایتەکەتم. پێویست ناکات ناوی فایل یان شوێن بڵێیت — تەنها بڵێ چیت دەوێت (نموونە: «بەشی دڵخوازەکان زیاد بکە» یان «دوگمەی داگرتن لە پەڕەی فیلم زیاد بکە»)، خۆم دەگەڕێم، کۆد دەنووسم و داتابەیسیش ڕێک دەخەم.</div>
    </div>
    <form class="ai-form" id="aiForm">
      <textarea id="aiInput" placeholder="نموونە: سیستەمی هەڵبژاردنی ژێرنووس زیاد بکە بۆ هەموو فیلمەکان..."></textarea>
      <button class="btn btn-gold" type="submit"><i class="fas fa-paper-plane"></i></button>
    </form>
  </div>
</div>

<div class="panel-card ai-cfg">
  <h3><i class="fas fa-key"></i> ڕێکخستنی AI</h3>
  <form method="POST">
    <label>API Key</label>
    <input type="password" name="api_key" value="<?= clean($cfg['api_key']) ?>" placeholder="sk-...">
    <label>Base URL (OpenAI / OpenRouter / Groq ...)</label>
    <input type="text" name="base_url" value="<?= clean($cfg['base_url']) ?>">
    <label>Model (پێویستە پشتگیری tool calling بکات، نموونە gpt-4o یان gpt-4o-mini)</label>
    <input type="text" name="model" value="<?= clean($cfg['model']) ?>" placeholder="gpt-4o-mini">
    <button name="save_cfg" value="1" class="btn btn-gold btn-sm"><i class="fas fa-save"></i> پاشەکەوت</button>
  </form>
  <p class="text-gray" style="font-size:.8rem;margin-top:10px;">هەر فایلێک بگۆڕدرێت، کۆپیەکی کۆنی بە <code>.bak</code> دەپارێزرێت و لە «دەستکاری کۆد» دەتوانیت بیگەڕێنیتەوە.</p>
</div>
</main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
(function(){
  var msgs=document.getElementById('aiMsgs'), form=document.getElementById('aiForm'), input=document.getElementById('aiInput');
  var history=[];
  function esc(s){return String(s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];});}
  function render(t){
    return esc(t).replace(/```(\w+)?\n([\s\S]*?)```/g, function(_,l,code){ return '<pre><code>'+code+'</code></pre>'; });
  }
  function add(cls, text){
    var d=document.createElement('div'); d.className='ai-msg '+cls; d.innerHTML=render(text);
    msgs.appendChild(d); msgs.scrollTop=msgs.scrollHeight; return d;
  }
  function addActions(list){
    if(!list || !list.length) return;
    var d=document.createElement('div'); d.className='ai-acts';
    d.textContent='🛠 '+list.join('\n🛠 ');
    msgs.appendChild(d); msgs.scrollTop=msgs.scrollHeight;
  }
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var q=input.value.trim(); if(!q) return;
    input.value=''; add('me', q); history.push({role:'user',content:q});
    var typing=add('bot','⏳ دەگەڕێم و کۆد دەنووسم... (لەوانەیە چەند خولەکێک بخایەنێت)'); typing.classList.add('ai-typing');
    fetch('?ask=1',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:history})})
      .then(function(r){return r.json();})
      .then(function(d){
        typing.remove();
        addActions(d.actions);
        if(d.error){ add('bot','⚠️ '+d.error); return; }
        add('bot', d.reply); history.push({role:'assistant',content:d.reply});
        if(history.length>16) history=history.slice(-16);
      })
      .catch(function(){ typing.remove(); add('bot','⚠️ هەڵەی پەیوەندی ڕوویدا.'); });
  });
  input.addEventListener('keydown', function(e){ if(e.key==='Enter' && (e.ctrlKey||e.metaKey)) form.requestSubmit(); });
})();
</script>
</body></html>
