<?php
// api/get-items.php
require_once '../auth/config.php'; // This should handle session_start() if needed
// require_once '../auth/db.php'; // Include this if needed

// --- REMOVED: session_start(); --- 
// The session is already active from the parent context (dashboard.html's session)

// Optional: Log for debugging
// error_log("Get-Items: Session ID: " . session_id() . ". Session Data: " . print_r($_SESSION, true));

// Check if user is authenticated based on session data
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get filters from query parameters
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$severity = $_GET['severity'] ?? 'all';
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? 'all';

// Get database connection
$db = getDB(); // Assuming getDB() is defined in auth/db.php which is included via config.php

try {
    // Build query dynamically based on filters
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
    // Location filter example (requires user's location from session)
    $userCity = $_SESSION['city'] ?? '';
    if ($location === 'local' && $userCity) {
        $sql .= " AND i.city = ?";
        $params[] = $userCity;
    } elseif ($location === 'city' && $userCity) {
        $sql .= " AND i.city = ?";
        $params[] = $userCity;
    }
    // Add more location filters as needed (state, country, etc.)

    $sql .= " ORDER BY i.created_at DESC LIMIT 20"; // Adjust limit as needed

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Optional: Log the query results
    // error_log("Get-Items: Fetched " . count($items) . " items.");

    // Return success response with items
    echo json_encode(['success' => true, 'items' => $items]);

} catch (PDOException $e) {
    // Log error and return failure response
    error_log("Database error in get-items.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>