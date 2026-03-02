<?php
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../includes/functions.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['success'=>false]); exit; }
if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Login required']); exit; }
$movieId = (int)($_POST['movie_id']??0);
$content = clean($_POST['content']??'');
$isSpoiler = (int)($_POST['is_spoiler']??0);
if (!$movieId||!$content) { echo json_encode(['success'=>false,'message'=>'Missing data']); exit; }
try {
    // Ensure comments table has is_spoiler column
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        movie_id INT NOT NULL,
        content TEXT NOT NULL,
        is_approved TINYINT(1) DEFAULT 1,
        is_spoiler TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    // Add is_spoiler column if missing
    try { $pdo->exec("ALTER TABLE comments ADD COLUMN is_spoiler TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    $pdo->prepare("INSERT INTO comments (user_id,movie_id,content,is_spoiler) VALUES (?,?,?,?)")->execute([$_SESSION['user_id'],$movieId,$content,$isSpoiler]);
    
    // Award points for comment
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_points (user_id INT PRIMARY KEY, points INT DEFAULT 0, vip_until DATETIME DEFAULT NULL) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS point_activities (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, activity_type VARCHAR(50), points_earned INT, description VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $checkPts = $pdo->prepare("SELECT id FROM point_activities WHERE user_id = ? AND activity_type = 'comment' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $checkPts->execute([$_SESSION['user_id']]);
        if (!$checkPts->fetch()) {
            $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, 2) ON DUPLICATE KEY UPDATE points = points + 2")->execute([$_SESSION['user_id']]);
            $pdo->prepare("INSERT INTO point_activities (user_id, activity_type, points_earned, description) VALUES (?, 'comment', 2, ?)")->execute([$_SESSION['user_id'], "بۆچوون (movie:$movieId)"]);
        }
    } catch (Exception $e) {}
    
    echo json_encode(['success'=>true]);
} catch(Exception $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
