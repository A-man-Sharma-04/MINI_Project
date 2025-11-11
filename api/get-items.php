<?php
// api/get-items.php
require_once '../auth/config.php';
require_once '../auth/db.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDB();

// Get filters
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$severity = $_GET['severity'] ?? 'all';
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? 'all';

// Build query
$sql = "SELECT i.*, u.name as user_name FROM items i JOIN users u ON i.user_id = u.id WHERE 1=1";
$params = [];

if ($type !== 'all') {
    $sql .= " AND i.type = ?";
    $params[] = $type;
}

if ($status !== 'all') {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

if ($severity !== 'all') {
    $sql .= " AND i.severity = ?";
    $params[] = $severity;
}

if ($search) {
    $sql .= " AND (i.title LIKE ? OR i.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add location filters based on user's city
$user_city = $_SESSION['city'] ?? '';
if ($location === 'local' && $user_city) {
    $sql .= " AND i.city = ?";
    $params[] = $user_city;
} elseif ($location === 'city' && $user_city) {
    $sql .= " AND i.city = ?";
    $params[] = $user_city;
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

echo json_encode(['success' => true, 'items' => $items]);