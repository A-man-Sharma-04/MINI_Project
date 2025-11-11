<?php
// api/user-data.php
require_once '../auth/config.php';
require_once '../auth/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get user stats
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_items
    FROM items 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

echo json_encode([
    'success' => true,
    'stats' => [
        'total_items' => $stats['total_items'] ?? 0,
        'resolved_items' => $stats['resolved_items'] ?? 0,
        'reputation' => $_SESSION['reputation_score'] ?? 0
    ]
]);