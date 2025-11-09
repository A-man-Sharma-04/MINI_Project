<?php
// auth/google.php
require_once 'config.php';
require_once 'db.php';

// Use Google API Client (install via: composer require google/apiclient)
// If you don't want Composer, use manual OAuth flow (simpler below)

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
    CURLOPT_RETURNTRANSFER => true
]);
$response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($response, true);
if (!isset($token_data['id_token'])) {
    die("Google auth failed.");
}

// Verify ID token (simplified — in prod, verify signature)
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
    $db->prepare("INSERT INTO users (email, name) VALUES (?, ?)")->execute([$email, $name]);
    $user_id = $db->lastInsertId();
} else {
    $user_id = $user['id'];
}

$_SESSION['user_id'] = $user_id;
$_SESSION['email'] = $email;
$_SESSION['name'] = $name;

header('Location: /dashboard.html');
exit;