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

$user_id = (int)($_GET['user_id'] ?? $_SESSION['user_id']);

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.id, u.name, u.email, u.city, f.created_at,
            1 AS is_following
        FROM user_follows f
        JOIN users u ON u.id = f.following_id
        WHERE f.follower_id = ?
        ORDER BY f.created_at DESC");
    $stmt->execute([$user_id]);
    $following = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'following' => $following]);
} catch (PDOException $e) {
    error_log('user-following error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load following list']);
}
