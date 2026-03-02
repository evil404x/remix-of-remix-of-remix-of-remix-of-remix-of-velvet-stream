<?php
// Standalone maintenance page
$message = $_GET['msg'] ?? 'ئێستا چاکسازیمان هەیە. بەمزووانە دەگەڕێینەوە!';
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineGold - چاکسازی</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #050505;
            color: #f0f0f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction: rtl;
            background-image:
                radial-gradient(ellipse at 30% 40%, rgba(255,215,0,0.04) 0%, transparent 50%),
                radial-gradient(ellipse at 70% 60%, rgba(255,215,0,0.02) 0%, transparent 50%);
        }
        .maintenance-box {
            text-align: center;
            padding: 60px 40px;
            max-width: 550px;
            background: rgba(18,18,18,0.7);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255,215,0,0.12);
            border-radius: 16px;
        }
        .logo {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, #B8960C, #FFD700, #FFE44D);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 30px;
        }
        .icon { font-size: 4rem; margin-bottom: 20px; }
        h1 { font-size: 1.5rem; margin-bottom: 15px; color: #FFD700; }
        p { color: #aaa; font-size: 1rem; line-height: 1.8; }
        .spinner {
            width: 40px; height: 40px; margin: 25px auto 0;
            border: 3px solid #252525; border-top-color: #FFD700;
            border-radius: 50%; animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="maintenance-box">
        <div class="logo">👑 CineGold</div>
        <div class="icon">🔧</div>
        <h1>سایتەکە لە باری چاکسازیدایە</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <div class="spinner"></div>
    </div>
</body>
</html>
