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

// Stubbed posts
$posts = [
    [
        'title' => 'Streetlight repair request',
        'description' => 'Lamp post not working near the main gate.',
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'type' => 'issue',
        'city' => $_SESSION['city'] ?? 'Local'
    ],
    [
        'title' => 'Community cleanup this Sunday',
        'description' => 'Join us at 9 AM in the central park.',
        'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'type' => 'event',
        'city' => $_SESSION['city'] ?? 'Local'
    ]
];

echo json_encode(['success' => true, 'posts' => $posts]);
