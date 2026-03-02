<?php
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../includes/functions.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['success'=>false]); exit; }
$data = json_decode(file_get_contents('php://input'), true);
$movieId = (int)($data['movie_id']??0);
$reason = clean($data['reason']??'');
if (!$movieId||!$reason) { echo json_encode(['success'=>false,'message'=>'Missing data']); exit; }
$userId = isLoggedIn() ? $_SESSION['user_id'] : null;
try {
    // Check if reports table exists
    $pdo->query("SELECT 1 FROM reports LIMIT 1");
    $pdo->prepare("INSERT INTO reports (user_id,movie_id,reason) VALUES (?,?,?)")->execute([$userId,$movieId,$reason]);
    echo json_encode(['success'=>true]);
} catch(Exception $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
