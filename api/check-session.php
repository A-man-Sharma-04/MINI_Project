<?php
// api/check-session.php

// Start the session - MUST be the very first executable line
session_start();

// Optional: Log for debugging (check PHP error log)
// error_log("Check-Session: Started. Session ID: " . session_id() . ". Data: " . print_r($_SESSION, true));

// Check if the required session variable (user_id) exists
if (isset($_SESSION['user_id'])) {
    // User is authenticated, prepare user data
    // Use null coalescing operator (??) for safe array access
    $responseData = [
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'], // This should exist if authenticated
            'name' => $_SESSION['name'] ?? 'Unknown', // Provide default if missing
            'email' => $_SESSION['email'] ?? 'Unknown', // Provide default if missing
            'city' => $_SESSION['city'] ?? null, // OK to be null
            'id_proof_verified' => $_SESSION['id_proof_verified'] ?? false, // Provide default
            'reputation_score' => $_SESSION['reputation_score'] ?? 0 // Provide default
        ]
    ];
} else {
    // User is NOT authenticated
    $responseData = [
        'authenticated' => false
    ];
}

// Set the correct content type header
header('Content-Type: application/json');

// Output the JSON response
echo json_encode($responseData);

// Optional: Log for debugging
// error_log("Check-Session: Responded with: " . json_encode($responseData));

// End the script explicitly (optional, but good practice for API endpoints)
exit;

?>