<?php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item id']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // Toggle share (single share per user per item)
    $checkStmt = $db->prepare("SELECT id FROM user_engagement WHERE user_id = ? AND item_id = ? AND action = 'share' LIMIT 1");
    $checkStmt->execute([$_SESSION['user_id'], $itemId]);
    $existingId = $checkStmt->fetchColumn();

    $action = 'shared';
    if ($existingId) {
        $delStmt = $db->prepare("DELETE FROM user_engagement WHERE id = ?");
        $delStmt->execute([$existingId]);
        $action = 'unshared';
    } else {
        $insStmt = $db->prepare("INSERT INTO user_engagement (user_id, item_id, action, created_at) VALUES (?, ?, 'share', NOW())");
        $insStmt->execute([$_SESSION['user_id'], $itemId]);
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM user_engagement WHERE item_id = ? AND action = 'share'");
    $countStmt->execute([$itemId]);
    $shareCount = (int)$countStmt->fetchColumn();

    $db->commit();

    echo json_encode([
        'success' => true,
        'action' => $action,
        'share_count' => $shareCount,
        'item_id' => $itemId
    ]);
} catch (PDOException $e) {
    $db->rollBack();
    error_log('share-item error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to share item']);
}
