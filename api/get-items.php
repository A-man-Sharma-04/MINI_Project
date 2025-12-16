<?php
// api/get-items.php
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ob_start();
header('Content-Type: application/json');

// Fatal handler to always emit JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Server error in get-items']);
    }
});

require_once '../auth/config.php';
require_once '../auth/db.php';

ob_clean();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

error_log('get-items: start user ' . ($_SESSION['user_id'] ?? 'unknown'));

$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$severity = $_GET['severity'] ?? 'all';
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? 'all';

try {
    $db = getDB();
    $sql = "SELECT i.*, u.name as user_name FROM items i JOIN users u ON i.user_id = u.id WHERE 1=1 AND i.id NOT IN (SELECT item_id FROM post_status_history WHERE new_status = 'deleted')";
    $params = [];
    if ($type !== 'all') { $sql .= " AND i.type = ?"; $params[] = $type; }
    if ($status !== 'all') { $sql .= " AND i.status = ?"; $params[] = $status; }
    if ($severity !== 'all') { $sql .= " AND i.severity = ?"; $params[] = $severity; }
    if ($search) { $sql .= " AND (i.title LIKE ? OR i.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $userCity = $_SESSION['city'] ?? '';
    if ($location === 'local' && $userCity) { $sql .= " AND i.city = ?"; $params[] = $userCity; }
    elseif ($location === 'city' && $userCity) { $sql .= " AND i.city = ?"; $params[] = $userCity; }
    $sql .= " ORDER BY i.created_at DESC LIMIT 20";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payload = json_encode(['success' => true, 'items' => $items], JSON_INVALID_UTF8_SUBSTITUTE);
    if ($payload === false) {
        throw new RuntimeException('json_encode failed: ' . json_last_error_msg());
    }
    error_log('get-items: rows ' . count($items) . ' payload bytes ' . strlen($payload));
    ob_clean();
    echo $payload;
    exit;
} catch (Throwable $e) {
    error_log('get-items fatal: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error in get-items']);
    exit;
}
