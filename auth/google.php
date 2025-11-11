<?php
// auth/google.php
require_once 'config.php';
require_once 'db.php';
require_once 'rate-limit.php';

$rateLimiter = new RateLimiter();
$ip = $_SERVER['REMOTE_ADDR'];

// Rate limit: max 10 Google OAuth requests per IP per hour
if ($rateLimiter->isRateLimited($ip, 'google_oauth', 10, 3600)) {
    die('Too many requests. Please try again later.');
}

$rateLimiter->recordRequest($ip, 'google_oauth');

// Get auth code
$auth_code = $_GET['code'] ?? null;

if (!$auth_code) {
    // Redirect to Google
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'scope' => 'openid email profile',
        'response_type' => 'code',
        'access_type' => 'offline'
    ];
    $url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

// Exchange code for token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://oauth2.googleapis.com/token',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $auth_code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => GOOGLE_REDIRECT_URI
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false, // Only for localhost
    CURLOPT_SSL_VERIFYHOST => false  // Only for localhost
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("Google token error: $response");
    die("Authentication failed. Please try again.");
}

$token_data = json_decode($response, true);
if (!isset($token_data['id_token'])) {
    die("Google auth failed.");
}

// Verify ID token (simplified)
$payload = explode('.', $token_data['id_token'])[1];
$payload = json_decode(base64_decode(str_pad(strtr($payload, '-_', '+/'), strlen($payload) % 4, '=', STR_PAD_RIGHT)), true);

$email = $payload['email'] ?? '';
$name = $payload['name'] ?? strstr($email, '@', true);

if (!$email) {
    die("Google email not received.");
}

// Save or get user
$db = getDB();
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $stmt = $db->prepare("INSERT INTO users (email, name) VALUES (?, ?)");
    $stmt->execute([$email, $name]);
    $user_id = $db->lastInsertId();
} else {
    $user_id = $user['id'];
}

$_SESSION['user_id'] = $user_id;
$_SESSION['email'] = $email;
$_SESSION['name'] = $name;

header('Location: ../dashboard.html');
exit;