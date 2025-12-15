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
$db = getDB();
$db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES'");

$user_id = (int)($_GET['user_id'] ?? $_SESSION['user_id']);

try {
    // Basic user
    $userStmt = $db->prepare("SELECT id, name, email, phone, reputation_score FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    // Profile extras
    $profileStmt = $db->prepare("SELECT bio, profile_image, banner_image FROM user_profiles WHERE user_id = ? LIMIT 1");
    $profileStmt->execute([$user_id]);
    $profile = $profileStmt->fetch();

        $countsStmt = $db->prepare("SELECT 
            (SELECT COUNT(*) FROM user_follows WHERE following_id = ?) AS followers_count,
            (SELECT COUNT(*) FROM user_follows WHERE follower_id = ?) AS following_count,
            (SELECT COUNT(*) FROM reactions WHERE user_id = ? AND reaction_type = 'like') AS likes_given,
            (SELECT COUNT(*) FROM comments WHERE author_id = ?) AS comments_given,
            (SELECT COUNT(*) FROM user_engagement WHERE user_id = ? AND action = 'share') AS shares_given,
            (SELECT COUNT(*) FROM reactions r JOIN items it ON it.id = r.item_id WHERE it.user_id = ? AND r.reaction_type = 'like') AS likes_received,
            (SELECT COUNT(*) FROM comments c JOIN items it ON it.id = c.item_id WHERE it.user_id = ?) AS comments_received,
            (SELECT COUNT(*) FROM user_engagement ue JOIN items it ON it.id = ue.item_id WHERE it.user_id = ? AND ue.action = 'share') AS shares_received
            ");
        $countsStmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
        $counts = $countsStmt->fetch();

        // Stats
        $statsStmt = $db->prepare("SELECT 
                COUNT(*) AS total_items,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_items
            FROM items 
            WHERE user_id = ? 
              AND id NOT IN (SELECT item_id FROM post_status_history WHERE new_status = 'deleted')");
        $statsStmt->execute([$user_id]);
        $statsRow = $statsStmt->fetch();

        $totalItems = (int)($statsRow['total_items'] ?? 0);
        $resolvedItems = (int)($statsRow['resolved_items'] ?? 0);

        // Reputation: fixed baseline of 1 until we define the real model later.
        $computedRep = 1;

    echo json_encode([
        'success' => true,
        'profile' => [
            'name' => $user['name'] ?? 'User',
            'email' => $user['email'] ?? '',
            'contact' => $user['phone'] ?? '',
            'city' => $user['city'] ?? '',
            'bio' => $profile['bio'] ?? '',
            'profile_image' => $profile['profile_image'] ?? null,
            'banner_image' => $profile['banner_image'] ?? null,
        ],
        'stats' => [
            'total_items' => $totalItems,
            'followers_count' => (int)($counts['followers_count'] ?? 0),
            'following_count' => (int)($counts['following_count'] ?? 0),
            'reputation' => $computedRep,
            'likes_given' => (int)($counts['likes_given'] ?? 0),
            'comments_given' => (int)($counts['comments_given'] ?? 0),
            'shares_given' => (int)($counts['shares_given'] ?? 0),
            'likes_received' => (int)($counts['likes_received'] ?? 0),
            'comments_received' => (int)($counts['comments_received'] ?? 0),
            'shares_received' => (int)($counts['shares_received'] ?? 0)
        ]
    ]);

} catch (PDOException $e) {
    error_log('user-profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load profile']);
}
