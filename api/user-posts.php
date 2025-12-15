<?php
require_once __DIR__ . '/../auth/config.php';
require_once __DIR__ . '/../auth/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

header('Content-Type: application/json');

$userId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);

try {
    $db = getDB();
        $stmt = $db->prepare("SELECT id, title, description, type, city, state, country, created_at, updated_at, media_urls 
                FROM items 
                WHERE user_id = ? 
                    AND id NOT IN (SELECT item_id FROM post_status_history WHERE new_status = 'deleted')
                ORDER BY created_at DESC
                LIMIT 100");
        $stmt->execute([$userId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'posts' => $posts
    ]);
} catch (PDOException $e) {
    error_log('user-posts error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load posts']);
}
