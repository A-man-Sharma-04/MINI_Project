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

$userId = (int)$_SESSION['user_id'];
$db = getDB();

try {
    $activities = [];

    // Items created
    $stmtItems = $db->prepare("SELECT id, title, created_at FROM items WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmtItems->execute([$userId]);
    foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'type' => 'posted',
            'target_type' => 'item',
            'target_id' => (int)$row['id'],
            'target_title' => $row['title'],
            'created_at' => $row['created_at']
        ];
    }

    // Comments made
    $stmtComments = $db->prepare("SELECT c.item_id, c.created_at, i.title FROM comments c JOIN items i ON i.id = c.item_id WHERE c.author_id = ? ORDER BY c.created_at DESC LIMIT 10");
    $stmtComments->execute([$userId]);
    foreach ($stmtComments->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'type' => 'commented',
            'target_type' => 'item',
            'target_id' => (int)$row['item_id'],
            'target_title' => $row['title'],
            'created_at' => $row['created_at']
        ];
    }

    // Likes
    $stmtLikes = $db->prepare("SELECT r.item_id, r.created_at, i.title FROM reactions r JOIN items i ON i.id = r.item_id WHERE r.user_id = ? AND r.reaction_type = 'like' ORDER BY r.created_at DESC LIMIT 10");
    $stmtLikes->execute([$userId]);
    foreach ($stmtLikes->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'type' => 'liked',
            'target_type' => 'item',
            'target_id' => (int)$row['item_id'],
            'target_title' => $row['title'],
            'created_at' => $row['created_at']
        ];
    }

    // Shares
    $stmtShares = $db->prepare("SELECT ue.item_id, ue.created_at, i.title FROM user_engagement ue JOIN items i ON i.id = ue.item_id WHERE ue.user_id = ? AND ue.action = 'share' ORDER BY ue.created_at DESC LIMIT 10");
    $stmtShares->execute([$userId]);
    foreach ($stmtShares->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'type' => 'shared',
            'target_type' => 'item',
            'target_id' => (int)$row['item_id'],
            'target_title' => $row['title'],
            'created_at' => $row['created_at']
        ];
    }

    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });
    $activities = array_slice($activities, 0, 20);

    echo json_encode(['success' => true, 'activity' => $activities]);
} catch (PDOException $e) {
    error_log('recent-activity error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load recent activity']);
}
