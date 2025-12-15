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
$payload = json_decode(file_get_contents('php://input'), true);
$itemId = isset($payload['item_id']) ? (int)$payload['item_id'] : 0;

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'item_id is required']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    $check = $db->prepare('SELECT user_id, status FROM items WHERE id = ? LIMIT 1');
    $check->execute([$itemId]);
    $item = $check->fetch(PDO::FETCH_ASSOC);
    if (!$item || (int)$item['user_id'] !== (int)$_SESSION['user_id']) {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not allowed']);
        exit;
    }

    // Soft-delete without breaking enum: mark closed + timestamp
    $del = $db->prepare('UPDATE items SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    $del->execute(['closed', $itemId]);

    $nextId = (int)$db->query('SELECT COALESCE(MAX(id),0)+1 AS next_id FROM post_status_history')->fetchColumn();
    $audit = $db->prepare('INSERT INTO post_status_history (id, item_id, old_status, new_status, changed_by, comment, changed_at) VALUES (?,?,?,?,?,?,NOW())');
    $audit->execute([
        $nextId,
        $itemId,
        $item['status'] ?? null,
        'deleted',
        $_SESSION['user_id'],
        'Post marked deleted by owner'
    ]);

    $db->commit();

    echo json_encode(['success' => true, 'deleted' => $itemId]);
} catch (PDOException $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('delete-item error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
}
