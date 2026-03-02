<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$q = clean($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['results' => []]); exit; }

$stmt = $pdo->prepare("SELECT title, slug, poster, release_year, imdb_rate FROM movies WHERE status='published' AND title LIKE ? LIMIT 8");
$stmt->execute(["%{$q}%"]);
echo json_encode(['results' => $stmt->fetchAll()]);
