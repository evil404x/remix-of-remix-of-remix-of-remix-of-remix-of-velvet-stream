<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'login_required' => true]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$movieId = (int)($data['movie_id'] ?? 0);
if (!$movieId) { echo json_encode(['success' => false]); exit; }

$isFav = toggleFavorite($pdo, $_SESSION['user_id'], $movieId);
echo json_encode(['success' => true, 'is_favorited' => $isFav]);
