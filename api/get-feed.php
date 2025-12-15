<?php
// api/get-feed.php
require_once '../auth/config.php';
require_once '../auth/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$view = $_GET['view'] ?? 'for-you';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(50, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$db = getDB();

try {
    $baseSelect = "SELECT i.id, i.user_id, i.type, i.title, i.description, i.city, i.state, i.country, i.created_at, i.media_urls,
        u.name AS user_name,
        COALESCE(r.likes, 0) AS like_count,
        COALESCE(c.comments, 0) AS comment_count,
        COALESCE(s.shares, 0) AS share_count,
        CASE WHEN uf.follower_id IS NULL THEN 0 ELSE 1 END AS is_following
        FROM items i
        JOIN users u ON i.user_id = u.id
        LEFT JOIN (
            SELECT item_id, COUNT(*) AS likes FROM reactions WHERE reaction_type = 'like' GROUP BY item_id
        ) r ON r.item_id = i.id
        LEFT JOIN (
            SELECT item_id, COUNT(*) AS comments FROM comments GROUP BY item_id
        ) c ON c.item_id = i.id
        LEFT JOIN (
            SELECT item_id, COUNT(*) AS shares FROM user_engagement WHERE action = 'share' GROUP BY item_id
        ) s ON s.item_id = i.id
        LEFT JOIN user_follows uf ON uf.follower_id = ? AND uf.following_id = i.user_id
                WHERE 1=1
                    AND (i.status IS NULL OR i.status != 'closed')
                    AND i.id NOT IN (SELECT item_id FROM post_status_history WHERE new_status = 'deleted')";

    $params = [$_SESSION['user_id']];

    if ($view === 'following') {
        $baseSelect .= " AND i.user_id IN (SELECT following_id FROM user_follows WHERE follower_id = ?)";
        $params[] = $_SESSION['user_id'];
    } else {
        // for-you: show global recent for now (optionally filter by city later)
    }

    $baseSelect .= " ORDER BY i.created_at DESC LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($baseSelect);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'items' => $items,
        'page' => $page,
        'limit' => $limit,
        'has_more' => count($items) === $limit // best-effort flag
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-feed.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>