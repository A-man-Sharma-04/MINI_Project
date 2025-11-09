<?php
// auth/otp.php
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

$db = getDB();
$db->exec("DELETE FROM otps WHERE expires_at < NOW()");

$action = $_GET['action'] ?? '';

if ($action === 'send') {
    $otp = random_int(100000, 999999);
    $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    $stmt = $db->prepare("REPLACE INTO otps (email, code_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $otp_hash, $expires]);

    $stmt = $db->prepare("SELECT name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $name = $user ? $user['name'] : strstr($email, '@', true);

    // Send email via Brevo
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.brevo.com/v3/smtp/email",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "api-key: " . BREVO_API_KEY,
            "accept: application/json",
            "content-type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "to" => [["email" => $email, "name" => $name]],
            "sender" => ["email" => "no-reply@yourdomain.com", "name" => "CommunityHub"],
            "subject" => "Your Login Code",
            "htmlContent" => "<h2>Hello $name,</h2><p>Your one-time code is: <strong>$otp</strong></p><p>Valid for 5 minutes.</p>"
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        echo json_encode(['success' => true]);
    } else {
        error_log("Brevo error: $response");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }

} elseif ($action === 'verify') {
    $code = $data['code'] ?? '';
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid code']);
        exit;
    }

    $stmt = $db->prepare("SELECT code_hash FROM otps WHERE email = ? AND expires_at > NOW()");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($code, $row['code_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired code']);
        exit;
    }

    $db->prepare("DELETE FROM otps WHERE email = ?")->execute([$email]);

    $stmt = $db->prepare("SELECT name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $name = strstr($email, '@', true);
        $db->prepare("INSERT INTO users (email, name) VALUES (?, ?)")->execute([$email, $name]);
        $user_id = $db->lastInsertId();
    } else {
        $user_id = $user['id'];
        $name = $user['name'];
    }

    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['name'] = $name;

    echo json_encode(['success' => true]);

} 
 elseif ($action === 'send_reset') {
    // Password reset OTP
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }

    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        // Don't reveal if email exists - still send "success" to prevent enumeration
        echo json_encode(['success' => true]);
        exit;
    }

    // Generate OTP
    $otp = random_int(100000, 999999);
    $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Save with purpose=reset
    $stmt = $db->prepare("REPLACE INTO otps (email, code_hash, expires_at, purpose) VALUES (?, ?, ?, 'reset')");
    $stmt->execute([$email, $otp_hash, $expires]);

    // Send email
    $name = strstr($email, '@', true);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.brevo.com/v3/smtp/email",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "api-key: " . BREVO_API_KEY,
            "accept: application/json",
            "content-type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "to" => [["email" => $email, "name" => $name]],
            "sender" => ["email" => "no-reply@yourdomain.com", "name" => "CommunityHub"],
            "subject" => "Password Reset Code",
            "htmlContent" => "<h2>Password Reset Request</h2><p>Your code: <strong>$otp</strong></p><p>Valid for 5 minutes.</p>"
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Always return success (security best practice)
    echo json_encode(['success' => true]);

} 
else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}