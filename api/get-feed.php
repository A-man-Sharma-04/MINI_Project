<?php
// api/get-feed.php
require_once '../auth/config.php'; // Adjust path if necessary
require_once '../auth/db.php';

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get the view type from the request
$view = $_GET['view'] ?? 'for-you'; // Default to 'for-you'

$db = getDB();

try {
    // Base query - adjust table/column names as needed
    $sql = "SELECT i.*, u.name as user_name FROM items i JOIN users u ON i.user_id = u.id WHERE ";

    if ($view === 'for-you') {
        // Example: Items from user's city, or items with high engagement, etc.
        // You might need more complex logic here based on user preferences
        $userCity = $_SESSION['city'] ?? '';
        if ($userCity) {
            $sql .= "i.city = ? ";
            $params = [$userCity];
        } else {
            // If no city, maybe show recent items globally
            $sql .= "1=1 "; // Dummy condition
            $params = [];
        }
    } elseif ($view === 'following') {
        // Example: Items from users the current user is following
        // This requires a user_follows table
        $userId = $_SESSION['user_id'];
        $sql .= "i.user_id IN (SELECT following_id FROM user_follows WHERE follower_id = ?) ";
        $params = [$userId];
    } else {
        // Invalid view type
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Invalid view type']);
        exit;
    }

    $sql .= "ORDER BY i.created_at DESC LIMIT 20"; // Adjust limit as needed

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return success response with items
    echo json_encode(['success' => true, 'items' => $items]);

} catch (PDOException $e) {
    // Log error and return failure response
    error_log("Database error in get-feed.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>