<?php
// api/update-status.php
ini_set('display_errors', '0');
ini_set('html_errors', '0');
header('Content-Type: application/json');

require_once '../auth/config.php';
require_once '../auth/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];
$itemId = isset($data['item_id']) ? (int)$data['item_id'] : 0;
$newStatus = $data['status'] ?? '';

$allowed = ['reported', 'in_progress', 'resolved', 'closed'];
if (!$itemId || !in_array($newStatus, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status or item']);
    exit;
}

try {
    $db = getDB();
    // Only owner can change status
    $stmt = $db->prepare('UPDATE items SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([$newStatus, $itemId, $_SESSION['user_id']]);
    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not allowed or item not found']);
        exit;
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('update-status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error updating status']);
}
