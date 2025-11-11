<?php
// auth/login.php
require_once 'config.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $data['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false]);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false]);
    exit;
}

// Login
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $email;
$_SESSION['name'] = $user['name'];

echo json_encode(['success' => true]);