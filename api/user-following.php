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

$following = [
    ['name' => 'Ward Office', 'email' => 'wardoffice@example.com'],
    ['name' => 'Green Volunteers', 'email' => 'green@example.com']
];

echo json_encode(['success' => true, 'following' => $following]);
