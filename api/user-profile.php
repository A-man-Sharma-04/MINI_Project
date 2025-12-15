<?php
require_once __DIR__ . '/../auth/config.php';

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

// Stubbed profile summary
echo json_encode([
    'success' => true,
    'profile' => [
        'bio' => 'Community member passionate about local improvements.',
        'profile_image' => null,
        'banner_image' => null
    ],
    'stats' => [
        'total_items' => 3,
        'followers_count' => 12,
        'following_count' => 8
    ]
]);
