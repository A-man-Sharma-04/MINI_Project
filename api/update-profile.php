<?php
require_once __DIR__ . '/../auth/config.php';
require_once __DIR__ . '/../auth/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$name = trim($input['name'] ?? '');
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$contact = trim($input['contact'] ?? '');
$bio = trim($input['bio'] ?? '');
$profile_image = trim($input['profile_image'] ?? '');
$banner_image = trim($input['banner_image'] ?? '');

if ($name === '' || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name and valid email are required']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // Update users
    $userStmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $userStmt->execute([$name, $email, $contact, $_SESSION['user_id']]);

    // Upsert into user_profiles
    $profileStmt = $db->prepare("INSERT INTO user_profiles (user_id, bio, profile_image, banner_image)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE bio = VALUES(bio), profile_image = VALUES(profile_image), banner_image = VALUES(banner_image)");
    $profileStmt->execute([$_SESSION['user_id'], $bio, $profile_image ?: null, $banner_image ?: null]);

    $db->commit();

    // Keep session in sync
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('update-profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}
