<?php
// auth/google.php
require_once 'config.php'; // This should include session_start() or you need it here
require_once 'db.php';

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$auth_code = $_GET['code'] ?? null;

if (!$auth_code) {
    // Redirect to Google for authorization
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'scope' => 'openid email profile', // Adjust scopes as needed
        'response_type' => 'code', // This is critical
        'access_type' => 'offline',
        'prompt' => 'consent' // Forces user to see consent screen, useful for debugging
    ];
    $url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

// Exchange code for token (using cURL or Google Client Library)
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
    CURLOPT_SSL_VERIFYPEER => false, // Only for local dev!
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    // Log error and die
    error_log("Google token error: $response");
    die("Authentication failed. Please try again.");
}

$token_data = json_decode($response, true);

if (!isset($token_data['id_token'])) {
    die("Invalid token response from Google.");
}

// Decode ID token to get user info (simplified, consider using Google's library for production)
$payload = explode('.', $token_data['id_token'])[1];
$payload = json_decode(base64_decode(str_pad(strtr($payload, '-_', '+/'), strlen($payload) % 4, '=', STR_PAD_RIGHT)), true);

$email = $payload['email'] ?? '';
$name = $payload['name'] ?? strstr($email, '@', true); // Fallback to part before @

if (!$email) {
    die("Google did not provide an email address.");
}

// Check if user exists in DB, create if not
$db = getDB();
$stmt = $db->prepare("SELECT id, name, city, id_proof_verified, reputation_score FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // User doesn't exist, create them (adjust fields as needed)
    $stmt = $db->prepare("INSERT INTO users (email, name) VALUES (?, ?)");
    $stmt->execute([$email, $name]);
    $user_id = $db->lastInsertId();
    $name = $name; // Set name for session
    $city = null; // New users have no city initially
    $id_proof_verified = 0; // New users are not verified
    $reputation_score = 0; // New users start with 0
} else {
    $user_id = $user['id'];
    $name = $user['name'];
    $city = $user['city'] ?? null;
    $id_proof_verified = $user['id_proof_verified'] ?? 0;
    $reputation_score = $user['reputation_score'] ?? 0;
}

// Set session variables - this is what `check-session.php` will look for
$_SESSION['user_id'] = $user_id;
$_SESSION['name'] = $name;
$_SESSION['email'] = $email;
if ($city !== null) $_SESSION['city'] = $city; // Only set if it exists
$_SESSION['id_proof_verified'] = $id_proof_verified;
$_SESSION['reputation_score'] = $reputation_score;

// Redirect to dashboard (or wherever you want to go after login)
header('Location: ../dashboard.html'); // Adjust path if necessary
exit;
?>