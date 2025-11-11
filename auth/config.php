<?php
// auth/config.php
date_default_timezone_set('UTC');
// Robust .env loader
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (trim($line) === '' || strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE
        $delimiter = strpos($line, '=') !== false ? '=' : ':';
        $parts = explode($delimiter, $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function env($key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

// Debug: Check if constants are loaded
error_log("GOOGLE_CLIENT_ID loaded: " . (env('GOOGLE_CLIENT_ID') ? 'yes' : 'no'));
error_log("GOOGLE_REDIRECT_URI loaded: " . (env('GOOGLE_REDIRECT_URI') ? 'yes' : 'no'));

// Constants
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'communityhub'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

define('BREVO_API_KEY', env('BREVO_API_KEY', ''));
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', env('GOOGLE_REDIRECT_URI', 'http://localhost/MINI_Project/auth/google.php'));

session_start();