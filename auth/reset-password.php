<?php
// auth/reset-password.php
require_once 'config.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$code = $data['code'] ?? '';
$new_password = $data['new_password'] ?? '';

if (!$email || strlen($code) !== 6 || !ctype_digit($code) || strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$db = getDB();

// Verify OTP is for password reset
$stmt = $db->prepare("SELECT code_hash FROM otps WHERE email = ? AND purpose = 'reset' AND expires_at > NOW()");
$stmt->execute([$email]);
$row = $stmt->fetch();

if (!$row || !password_verify($code, $row['code_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired code']);
    exit;
}

// Update password
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$updated = $stmt->execute([$password_hash, $email]);

if ($updated) {
    // Delete used OTP
    $db->prepare("DELETE FROM otps WHERE email = ? AND purpose = 'reset'")->execute([$email]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
}