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
$reaction_type = $input['reaction_type'] ?? 'like';
$allowed = ['like'];
if (!in_array($reaction_type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid reaction type']);
    exit;
}

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item id']);
    exit;
}

try {
    $db = getDB();

    // Upsert reaction
    $stmt = $db->prepare("INSERT INTO reactions (item_id, user_id, reaction_type, created_at)
        VALUES (:item_id, :user_id, :type, NOW())
        ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), created_at = NOW()");
    $stmt->execute([
        ':item_id' => $item_id,
        ':user_id' => $_SESSION['user_id'],
        ':type' => $reaction_type
    ]);

    // Return counts
    $countStmt = $db->prepare("SELECT COUNT(*) AS likes FROM reactions WHERE item_id = ? AND reaction_type = 'like'");
    $countStmt->execute([$item_id]);
    $likeCount = (int)$countStmt->fetch()['likes'];

    echo json_encode([
        'success' => true,
        'like_count' => $likeCount,
        'item_id' => $item_id
    ]);
} catch (PDOException $e) {
    error_log('react-item error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to react']);
}
