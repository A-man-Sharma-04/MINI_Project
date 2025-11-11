<?php
// api/check-session.php
// Add this line at the very beginning to handle potential issues with output buffering
if (ob_get_level()) {
    ob_end_clean();
}
// Start the session - this MUST be the first executable line after any <?php tag and before any HTML/output
session_start();
// Debug: Log session data to error log (optional, remove after confirming)
// error_log("Check-Session Session Data: " . print_r($_SESSION, true));
// error_log("Check-Session Session ID: " . session_id());

// Check if user is logged in based on session data
if (isset($_SESSION['user_id'])) {
    // User is logged in, return success and user data
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['name'],
            'email' => $_SESSION['email'],
            'city' => $_SESSION['city'] ?? null,
            'id_proof_verified' => $_SESSION['id_proof_verified'] ?? false,
            'reputation_score' => $_SESSION['reputation_score'] ?? 0
        ]
    ]);
} else {
    // User is NOT logged in, return failure
    echo json_encode(['authenticated' => false]);
}
// The script ends here. No closing ?> tag is strictly necessary,
// but if included, ensure there are NO spaces or newlines after it.
?>