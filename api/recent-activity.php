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

// Stubbed recent activity for dashboard home
echo json_encode([
    'success' => true,
    'activity' => [
        [
            'action' => 'created',
            'target_type' => 'issue',
            'target_title' => 'Streetlight outage near park',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'action' => 'commented on',
            'target_type' => 'event',
            'target_title' => 'Community Cleanup Drive',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]
    ]
]);
