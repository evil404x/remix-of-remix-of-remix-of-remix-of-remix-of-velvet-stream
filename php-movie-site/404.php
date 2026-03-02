<?php
require_once __DIR__ . '/config.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>٤٠٤ - لاپەڕە نەدۆزرایەوە | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
        
        * { margin:0; padding:0; box-sizing:border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #0a0a0a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(ellipse at 20% 50%, rgba(255, 215, 0, 0.03) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 50%, rgba(255, 215, 0, 0.02) 0%, transparent 50%);
            z-index: 0;
        }
        
        .container-404 {
            text-align: center;
            position: relative;
            z-index: 1;
            padding: 40px;
            max-width: 600px;
        }
        
        .error-code {
            font-size: clamp(8rem, 20vw, 14rem);
            font-weight: 900;
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 30%, #FFD700 50%, #B8860B 70%, #FFD700 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 10px;
            animation: shimmer 3s ease-in-out infinite;
            text-shadow: none;
            position: relative;
        }
        
        @keyframes shimmer {
            0%, 100% { background-position: 0% center; }
            50% { background-position: 200% center; }
        }
        
        .error-icon {
            font-size: 3rem;
            color: #FFD700;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
        }
        
        .error-desc {
            font-size: 1rem;
            color: #888;
            line-height: 1.8;
            margin-bottom: 35px;
        }
        
        .error-actions {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-404 {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-family: inherit;
            cursor: pointer;
            border: none;
        }
        
        .btn-gold-404 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
        }
        .btn-gold-404:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }
        
        .btn-glass-404 {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        .btn-glass-404:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        /* Floating particles */
        .particles {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(255, 215, 0, 0.3);
            border-radius: 50%;
            animation: float linear infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }
        
        .security-note {
            margin-top: 40px;
            padding: 14px 20px;
            background: rgba(255, 50, 50, 0.06);
            border: 1px solid rgba(255, 50, 50, 0.1);
            border-radius: 10px;
            color: #ff6b6b;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

<!-- Floating Particles -->
<div class="particles">
    <?php for ($i = 0; $i < 20; $i++): ?>
    <div class="particle" style="left:<?= rand(0,100) ?>%; animation-duration:<?= rand(8,20) ?>s; animation-delay:<?= rand(0,10) ?>s; width:<?= rand(2,4) ?>px; height:<?= rand(2,4) ?>px;"></div>
    <?php endfor; ?>
</div>

<div class="container-404">
    <div class="error-code">404</div>
    <div class="error-icon"><i class="fas fa-film"></i></div>
    <h1 class="error-title">ئەم لاپەڕەیە نەدۆزرایەوە!</h1>
    <p class="error-desc">
        ئەو لاپەڕەیەی بەدوایدا دەگەڕێیت بوونی نییە، یان لابراوە، یان ئەدرەسەکەت هەڵەیە.
        <br>تکایە بگەڕێوە بۆ لاپەڕەی سەرەکی.
    </p>
    
    <div class="error-actions">
        <a href="<?= SITE_URL ?>" class="btn-404 btn-gold-404">
            <i class="fas fa-home"></i> لاپەڕەی سەرەکی
        </a>
        <a href="<?= SITE_URL ?>/search.php" class="btn-404 btn-glass-404">
            <i class="fas fa-search"></i> گەڕان بۆ فیلم
        </a>
    </div>
    
    <div class="security-note">
        <i class="fas fa-shield-alt"></i>
        <span>ئەگەر هەوڵی دەستکاری URL دەدەیت، ئایپی ئەدرەسەکەت تۆمار دەکرێت.</span>
    </div>
</div>

</body>
</html>
