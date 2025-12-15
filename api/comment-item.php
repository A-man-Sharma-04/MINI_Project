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
$item_id = isset($input['item_id']) ? (int)$input['item_id'] : 0;
$body = trim($input['body'] ?? '');
$parent = isset($input['parent_comment']) ? (int)$input['parent_comment'] : null;

if ($item_id <= 0 || $body === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Item and comment text are required']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("INSERT INTO comments (item_id, author_id, parent_comment, body, created_at)
        VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$item_id, $_SESSION['user_id'], $parent, $body]);

    $countStmt = $db->prepare("SELECT COUNT(*) AS comments FROM comments WHERE item_id = ?");
    $countStmt->execute([$item_id]);
    $commentCount = (int)$countStmt->fetch()['comments'];

    echo json_encode([
        'success' => true,
        'comment_count' => $commentCount,
        'item_id' => $item_id
    ]);

} catch (PDOException $e) {
    error_log('comment-item error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
}
