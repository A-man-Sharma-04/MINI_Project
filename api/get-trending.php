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

// Stubbed trending data
$items = [
    [
        'rank' => 1,
        'type' => 'issue',
        'title' => 'Water supply disruption in Sector 5',
        'description' => 'Municipal team ETA 3 hours.',
        'city' => $_SESSION['city'] ?? 'Local',
        'user_name' => 'Aman Sharma',
        'engagement_count' => 124
    ],
    [
        'rank' => 2,
        'type' => 'event',
        'title' => 'Park tree-planting drive',
        'description' => 'Volunteers needed this weekend.',
        'city' => $_SESSION['city'] ?? 'Local',
        'user_name' => 'Community Volunteers',
        'engagement_count' => 88
    ],
    [
        'rank' => 3,
        'type' => 'notice',
        'title' => 'Garbage pickup rescheduled',
        'description' => 'Pickup shifted to Friday morning.',
        'city' => $_SESSION['city'] ?? 'Local',
        'user_name' => 'Ward Office',
        'engagement_count' => 57
    ]
];

echo json_encode(['success' => true, 'items' => $items]);
