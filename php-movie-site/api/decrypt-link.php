<?php
/**
 * Link Decryption API
 * Decrypts encrypted video URLs for secure playback
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$token = $_GET['token'] ?? '';
if (!$token) {
    echo json_encode(['error' => 'Missing token']);
    exit;
}

// Check referer
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (empty($referer) || strpos($referer, $_SERVER['HTTP_HOST']) === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid referer']);
    exit;
}

// Decrypt
$url = decryptVideoUrl($token);
if (!$url) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

echo json_encode(['url' => $url]);
