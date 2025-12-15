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

$location = $_GET['location'] ?? 'global'; // local|city|state|country|global
$sort = $_GET['sort'] ?? 'popular'; // popular|recent|top
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(50, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$db = getDB();

try {
    $sql = "SELECT 
            i.id,
            i.user_id,
            i.type,
            i.title,
            i.description,
            i.city,
            i.state,
            i.country,
            i.created_at,
            i.media_urls,
            u.name AS user_name,
            COALESCE(r.likes, 0) AS like_count,
            COALESCE(c.comments, 0) AS comment_count,
            COALESCE(s.shares, 0) AS share_count,
            (COALESCE(r.likes,0)*2 + COALESCE(c.comments,0) + COALESCE(s.shares,0)*3) AS engagement_score
        FROM items i
        JOIN users u ON u.id = i.user_id
        LEFT JOIN (
            SELECT item_id, COUNT(*) AS likes FROM reactions WHERE reaction_type='like' GROUP BY item_id
        ) r ON r.item_id = i.id
        LEFT JOIN (
            SELECT item_id, COUNT(*) AS comments FROM comments GROUP BY item_id
        ) c ON c.item_id = i.id
        LEFT JOIN (
            SELECT item_id, COUNT(*) AS shares FROM user_engagement WHERE action='share' GROUP BY item_id
        ) s ON s.item_id = i.id
        WHERE 1=1";

    $params = [];
    // Location filter (simplified: use city if available)
    $userCity = $_SESSION['city'] ?? null;
    if ($location !== 'global' && $userCity) {
        $sql .= " AND i.city = ?";
        $params[] = $userCity;
    }

    // Sorting
    if ($sort === 'recent') {
        $sql .= " ORDER BY i.created_at DESC";
    } else { // popular or top
        $sql .= " ORDER BY engagement_score DESC, i.created_at DESC";
    }

    $sql .= " LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add rank for UI
    foreach ($items as $idx => &$item) {
        $item['rank'] = $idx + 1;
        $item['engagement_count'] = (int)$item['engagement_score'];
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'page' => $page,
        'limit' => $limit,
        'has_more' => count($items) === $limit
    ]);

} catch (PDOException $e) {
    error_log('get-trending error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
