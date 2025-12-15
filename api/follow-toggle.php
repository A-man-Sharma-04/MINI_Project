<?php
// api/follow-toggle.php
require_once __DIR__ . '/../auth/config.php';
require_once __DIR__ . '/../auth/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$targetId = (int)($input['target_user_id'] ?? 0);
$currentUserId = (int)$_SESSION['user_id'];

if ($targetId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing target_user_id']);
    exit;
}

if ($targetId === $currentUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot follow yourself']);
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();

    // Check existing follow
    $checkStmt = $db->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ? LIMIT 1");
    $checkStmt->execute([$currentUserId, $targetId]);
    $isFollowing = (bool)$checkStmt->fetchColumn();

    if ($isFollowing) {
        $deleteStmt = $db->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $deleteStmt->execute([$currentUserId, $targetId]);
        $action = 'unfollowed';
    } else {
        $insertStmt = $db->prepare("INSERT INTO user_follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())");
        $insertStmt->execute([$currentUserId, $targetId]);
        $action = 'followed';
    }

    // Counts
    $followersCountStmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = ?");
    $followersCountStmt->execute([$targetId]);
    $followersCount = (int)$followersCountStmt->fetchColumn();

    $followingCountStmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
    $followingCountStmt->execute([$currentUserId]);
    $followingCount = (int)$followingCountStmt->fetchColumn();

    $db->commit();

    echo json_encode([
        'success' => true,
        'action' => $action,
        'is_following' => $action === 'followed',
        'followers_count' => $followersCount,
        'following_count' => $followingCount
    ]);
} catch (PDOException $e) {
    $db->rollBack();
    error_log('follow-toggle error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update follow status']);
}
