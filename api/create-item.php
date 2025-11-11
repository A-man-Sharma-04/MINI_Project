<?php
// api/create-item.php
require_once '../auth/config.php';
require_once '../auth/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is verified (ID proof required)
if (!($_SESSION['id_proof_verified'] ?? false)) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Profile verification required. Please complete your profile with ID proof.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$db = getDB();

// Get form data
$type = $_POST['type'] ?? '';
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$severity = $_POST['severity'] ?? 'medium';

// Validate required fields
if (empty($title) || empty($description) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Title, description, and type are required']);
    exit;
}

// Handle image uploads
$media_urls = [];
if (isset($_FILES['images'])) {
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    foreach ($_FILES['images']['tmp_name'] as $index => $tmp_name) {
        if ($_FILES['images']['error'][$index] === UPLOAD_ERR_OK) {
            $file_name = uniqid() . '_' . $_FILES['images']['name'][$index];
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($tmp_name, $file_path)) {
                $media_urls[] = '/uploads/' . $file_name;
            }
        }
    }
}

// Prepare additional data based on type
$additional_data = [];
switch ($type) {
    case 'event':
        $additional_data['date'] = $_POST['date'] ?? null;
        $additional_data['link'] = $_POST['link'] ?? null;
        $additional_data['code_of_conduct'] = $_POST['code_of_conduct'] ?? null;
        break;
    case 'issue':
        $additional_data['category'] = $_POST['category'] ?? 'other';
        $additional_data['urgency'] = $_POST['urgency'] ?? 'medium';
        break;
    case 'notice':
        $additional_data['valid_until'] = $_POST['valid_until'] ?? null;
        $additional_data['contact_info'] = $_POST['contact_info'] ?? null;
        break;
    case 'report':
        $additional_data['confidential'] = isset($_POST['confidential']) ? 1 : 0;
        $additional_data['priority'] = $_POST['priority'] ?? 'medium';
        break;
}

// Insert into database
$stmt = $db->prepare("
    INSERT INTO items (user_id, type, title, description, location_lat, location_lng, 
    severity, status, media_urls, additional_data, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'reported', ?, ?, NOW())
");
$result = $stmt->execute([
    $_SESSION['user_id'],
    $type,
    $title,
    $description,
    $latitude,
    $longitude,
    $severity,
    json_encode($media_urls),
    json_encode($additional_data)
]);

if ($result) {
    // Update user's reputation
    $db->prepare("UPDATE users SET reputation_score = reputation_score + 1 WHERE id = ?")
       ->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Item created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create item']);
}