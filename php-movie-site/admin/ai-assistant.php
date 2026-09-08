<?php
/**
 * AI Assistant - یاریدەدەری زیرەک بۆ نووسینی کۆد لە پانێڵی ئادمین
 * Works with any OpenAI-compatible API (OpenAI, OpenRouter, Groq, ...)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

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

// AJAX chat endpoint
if (isset($_GET['ask'])) {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $messages = $body['messages'] ?? [];
    if (!$cfg['api_key']) { echo json_encode(['error' => 'کلیلی API دانەنراوە. لە خوارەوە دایبنێ.']); exit; }

    $system = "تۆ یاریدەدەرێکی زیرەکی پرۆگرامسازیت بۆ وێبسایتێکی فیلمی PHP/MySQL (XAMPP). "
        . "هەمیشە بە زمانی کوردی سۆرانی وەڵام بدەرەوە. "
        . "کاتێک کۆد دەنووسیت، کۆدی تەواو بنووسە لەناو بلۆکی ```php یان ```js یان ```css و ناوی فایلەکەش دیاری بکە. "
        . "کورت و ڕوون بە.";

    $payload = [
        'model' => $cfg['model'],
        'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
        'temperature' => 0.3,
    ];

    $ch = curl_init($cfg['base_url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cfg['api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) { echo json_encode(['error' => 'هەڵەی پەیوەندی: ' . $err]); exit; }
    $data = json_decode($res, true);
    if ($code >= 400) {
        echo json_encode(['error' => 'هەڵە (' . $code . '): ' . ($data['error']['message'] ?? substr((string)$res, 0, 300))]);
        exit;
    }
    echo json_encode(['reply' => $data['choices'][0]['message']['content'] ?? '(وەڵامی بەتاڵ)']);
    exit;
}
?>
<!DOCTYPE html><html lang="ku" dir="rtl"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>یاریدەدەری AI - <?= SITE_NAME ?></title>
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
<div class="admin-header"><h1><i class="fas fa-robot"></i> یاریدەدەری زیرەک (AI)</h1></div>
<?php if($notice): ?><div class="ai-note"><?= clean($notice) ?></div><?php endif; ?>

<div class="panel-card">
  <div class="ai-chat">
    <div class="ai-msgs" id="aiMsgs">
      <div class="ai-msg bot">سڵاو 👋 من یاریدەدەری زیرەکی تۆم. هەر پرسیارێک یان داواکاریەکی کۆد بنووسە — بە کوردی وەڵامت دەدەمەوە و کۆدەکەت بۆ دەنووسم.</div>
    </div>
    <form class="ai-form" id="aiForm">
      <textarea id="aiInput" placeholder="نموونە: کۆدێکم بۆ بنووسە کە فیلمەکان بە IMDb ڕیز بکات..."></textarea>
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
    <label>Model</label>
    <input type="text" name="model" value="<?= clean($cfg['model']) ?>" placeholder="gpt-4o-mini">
    <button name="save_cfg" value="1" class="btn btn-gold btn-sm"><i class="fas fa-save"></i> پاشەکەوت</button>
  </form>
  <p class="text-gray" style="font-size:.8rem;margin-top:10px;">کۆدی دروستکراو دەتوانیت لە پەڕەی «دەستکاری کۆد» دایبنێیت و پاشەکەوتی بکەیت.</p>
</div>
</main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
(function(){
  var msgs=document.getElementById('aiMsgs'), form=document.getElementById('aiForm'), input=document.getElementById('aiInput');
  var history=[];
  function esc(s){return s.replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];});}
  function render(t){
    return esc(t).replace(/```(\w+)?\n([\s\S]*?)```/g, function(_,l,code){ return '<pre><code>'+code+'</code></pre>'; });
  }
  function add(cls, text){
    var d=document.createElement('div'); d.className='ai-msg '+cls; d.innerHTML=render(text);
    msgs.appendChild(d); msgs.scrollTop=msgs.scrollHeight; return d;
  }
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var q=input.value.trim(); if(!q) return;
    input.value=''; add('me', q); history.push({role:'user',content:q});
    var typing=add('bot','⏳ بیر دەکاتەوە...'); typing.classList.add('ai-typing');
    fetch('?ask=1',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:history})})
      .then(function(r){return r.json();})
      .then(function(d){
        typing.remove();
        if(d.error){ add('bot','⚠️ '+d.error); return; }
        add('bot', d.reply); history.push({role:'assistant',content:d.reply});
        if(history.length>20) history=history.slice(-20);
      })
      .catch(function(){ typing.remove(); add('bot','⚠️ هەڵەی پەیوەندی ڕوویدا.'); });
  });
  input.addEventListener('keydown', function(e){ if(e.key==='Enter' && (e.ctrlKey||e.metaKey)) form.requestSubmit(); });
})();
</script>
</body></html>
