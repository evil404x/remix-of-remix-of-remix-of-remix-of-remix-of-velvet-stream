<?php
/**
 * Actor Search API - For multi-select in add/edit movie
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Admin only']);
    exit;
}

$q = clean($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode(['success' => true, 'actors' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, name_ku, profile_image, tmdb_id FROM actors WHERE name LIKE ? OR name_ku LIKE ? ORDER BY name ASC LIMIT 15");
    $stmt->execute(["%{$q}%", "%{$q}%"]);
    $actors = $stmt->fetchAll();
    echo json_encode(['success' => true, 'actors' => $actors]);
} catch (Exception $e) {
    echo json_encode(['success' => true, 'actors' => []]);
}
