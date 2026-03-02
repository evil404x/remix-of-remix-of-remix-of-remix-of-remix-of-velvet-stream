<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$movieId = (int)($_GET['movie_id'] ?? 0);
$season = isset($_GET['season']) ? (int)$_GET['season'] : null;
$episode = isset($_GET['episode']) ? (int)$_GET['episode'] : null;

// Return both encrypted and raw links (raw for initial player load, encrypted for security)
$links = getVideoLinks($pdo, $movieId, $season, $episode);
$encLinks = [];
foreach ($links as $link) {
    $encLinks[] = [
        'id' => $link['id'],
        'language' => $link['language'],
        'server_name' => $link['server_name'],
        'video_url' => $link['video_url'], // For immediate playback
        'encrypted_url' => encryptVideoUrl($link['video_url']),
        'sort_order' => $link['sort_order'],
    ];
}

echo json_encode(['success' => true, 'links' => $encLinks]);
