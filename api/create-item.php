<?php
// api/create-item.php
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

// Enforce profile verification before posting
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
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
$severity = $_POST['severity'] ?? 'medium';

// Validate required fields
if ($title === '' || $description === '' || $type === '') {
    echo json_encode(['success' => false, 'message' => 'Title, description, and type are required']);
    exit;
}

// Validate coordinates (required because location_point is NOT NULL)
if ($latitude === null || $longitude === null || !is_numeric($latitude) || !is_numeric($longitude)) {
    echo json_encode(['success' => false, 'message' => 'Latitude and longitude are required']);
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
            $file_name = uniqid() . '_' . basename($_FILES['images']['name'][$index]);
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($tmp_name, $file_path)) {
                $media_urls[] = '/uploads/' . $file_name;
            }
        }
    }
}

// Map type-specific fields into existing schema columns
$category = null;
$registration_link = null;
$contact_info = [];

switch ($type) {
    case 'event':
        $registration_link = $_POST['link'] ?? null;
        $contact_info['code_of_conduct'] = $_POST['code_of_conduct'] ?? null;
        $contact_info['date'] = $_POST['date'] ?? null;
        break;
    case 'issue':
        $category = $_POST['category'] ?? 'other';
        $contact_info['urgency'] = $_POST['urgency'] ?? 'medium';
        break;
    case 'notice':
        $contact_info['valid_until'] = $_POST['valid_until'] ?? null;
        $contact_info['contact'] = $_POST['contact_info'] ?? null;
        break;
    case 'report':
        $contact_info['confidential'] = isset($_POST['confidential']) ? 1 : 0;
        $contact_info['priority'] = $_POST['priority'] ?? 'medium';
        break;
}

$contact_info_json = json_encode($contact_info);
$media_json = json_encode($media_urls);

try {
    $stmt = $db->prepare("INSERT INTO items (
        user_id, type, category, title, description,
        location_lat, location_lng, location_point,
        city, state, country, severity, status,
        media_urls, registration_link, contact_info, created_at
    ) VALUES (
        :user_id, :type, :category, :title, :description,
        :lat, :lng, POINT(:lng_point, :lat_point),
        NULL, NULL, NULL, :severity, 'reported',
        :media_urls, :registration_link, :contact_info, NOW()
    )");

    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':type' => $type,
        ':category' => $category,
        ':title' => $title,
        ':description' => $description,
        ':lat' => $latitude,
        ':lng' => $longitude,
        ':lng_point' => $longitude,
        ':lat_point' => $latitude,
        ':severity' => $severity,
        ':media_urls' => $media_json,
        ':registration_link' => $registration_link,
        ':contact_info' => $contact_info_json,
    ]);

    // Update user's reputation
    $db->prepare("UPDATE users SET reputation_score = reputation_score + 1 WHERE id = ?")
       ->execute([$_SESSION['user_id']]);

    echo json_encode(['success' => true, 'message' => 'Item created successfully']);

} catch (PDOException $e) {
    error_log('create-item error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create item']);
}