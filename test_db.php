<?php
$host = 'localhost';
$db = 'communityhub';
$user = 'communityhub_user';
$pass = 'your_actual_password_here'; // ← Use real password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "✅ Connected successfully!";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}