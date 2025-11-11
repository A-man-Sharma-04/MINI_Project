<?php
// auth/login.php
require_once 'config.php';
require_once 'db.php';
require_once 'rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$rateLimiter = new RateLimiter();

// Get client IP
$ip = $_SERVER['REMOTE_ADDR'];

// Rate limit: max 5 login attempts per IP per 15 minutes
if ($rateLimiter->isRateLimited($ip, 'login', 5, 900)) {
    echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again later.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $data['password'] ?? '';

if (!$email || !$password) {
    $rateLimiter->recordRequest($ip, 'login');
    echo json_encode(['success' => false]);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
    $rateLimiter->recordRequest($ip, 'login');
    echo json_encode(['success' => false]);
    exit;
}

// Successful login - record it
$rateLimiter->recordRequest($ip, 'login_success');

// Login
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $email;
$_SESSION['name'] = $user['name'];

echo json_encode(['success' => true]);