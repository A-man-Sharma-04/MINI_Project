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

$db = getDB();

try {
    // Basic user
    $userStmt = $db->prepare("SELECT id, name, email, phone, reputation_score FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    // Profile extras
    $profileStmt = $db->prepare("SELECT bio, profile_image, banner_image, followers_count, following_count FROM user_profiles WHERE user_id = ? LIMIT 1");
    $profileStmt->execute([$user_id]);
    $profile = $profileStmt->fetch();

    // Stats
    $statsStmt = $db->prepare("SELECT COUNT(*) AS total_items FROM items WHERE user_id = ?");
    $statsStmt->execute([$user_id]);
    $statsRow = $statsStmt->fetch();

    echo json_encode([
        'success' => true,
        'profile' => [
            'name' => $user['name'] ?? 'User',
            'email' => $user['email'] ?? '',
            'contact' => $user['phone'] ?? '',
            'bio' => $profile['bio'] ?? '',
            'profile_image' => $profile['profile_image'] ?? null,
            'banner_image' => $profile['banner_image'] ?? null,
        ],
        'stats' => [
            'total_items' => (int)($statsRow['total_items'] ?? 0),
            'followers_count' => (int)($profile['followers_count'] ?? 0),
            'following_count' => (int)($profile['following_count'] ?? 0),
            'reputation' => (int)($user['reputation_score'] ?? 0)
        ]
    ]);

} catch (PDOException $e) {
    error_log('user-profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load profile']);
}
