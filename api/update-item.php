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
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$itemId = isset($payload['item_id']) ? (int)$payload['item_id'] : 0;
$title = trim($payload['title'] ?? '');
$description = trim($payload['description'] ?? '');
$type = $payload['type'] ?? '';
$city = trim($payload['city'] ?? '');
$state = trim($payload['state'] ?? '');
$country = trim($payload['country'] ?? '');

if ($itemId <= 0 || $title === '' || $description === '' || $type === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'item_id, title, description, and type are required']);
    exit;
}

$allowedTypes = ['event','issue','notice','report'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    $check = $db->prepare('SELECT id, user_id, title, description, type, city, state, country, status, created_at FROM items WHERE id = ? LIMIT 1');
    $check->execute([$itemId]);
    $item = $check->fetch(PDO::FETCH_ASSOC);
    if (!$item || (int)$item['user_id'] !== (int)$_SESSION['user_id']) {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not allowed']);
        exit;
    }

    $upd = $db->prepare('UPDATE items SET title = ?, description = ?, type = ?, city = ?, state = ?, country = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    $upd->execute([
        $title,
        $description,
        $type,
        $city !== '' ? $city : null,
        $state !== '' ? $state : null,
        $country !== '' ? $country : null,
        $itemId
    ]);

    // audit trail using post_status_history table (manual id to avoid missing AUTO_INCREMENT)
    $nextId = (int)$db->query('SELECT COALESCE(MAX(id),0)+1 AS next_id FROM post_status_history')->fetchColumn();
    $audit = $db->prepare('INSERT INTO post_status_history (id, item_id, old_status, new_status, changed_by, comment, changed_at) VALUES (?,?,?,?,?,?,NOW())');
    $audit->execute([
        $nextId,
        $itemId,
        $item['status'] ?? null,
        $item['status'] ?? null,
        $_SESSION['user_id'],
        'Post edited (title/description/type/location)'
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'item_id' => $itemId,
        'created_at' => $item['created_at'],
        'updated_at' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('update-item error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update item']);
}
