<?php
// auth/db.php
require_once __DIR__ . '/config.php';

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            http_response_code(500);
            die("Database connection failed.");
        }
    }
    return $pdo;
}