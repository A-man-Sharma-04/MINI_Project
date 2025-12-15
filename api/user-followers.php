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
            CASE WHEN uf2.follower_id IS NULL THEN 0 ELSE 1 END AS is_following
        FROM user_follows f
        JOIN users u ON u.id = f.follower_id
        LEFT JOIN user_follows uf2 ON uf2.follower_id = ? AND uf2.following_id = u.id
        WHERE f.following_id = ?
        ORDER BY f.created_at DESC");
    $stmt->execute([$_SESSION['user_id'], $user_id]);
    $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'followers' => $followers]);
} catch (PDOException $e) {
    error_log('user-followers error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load followers']);
}
