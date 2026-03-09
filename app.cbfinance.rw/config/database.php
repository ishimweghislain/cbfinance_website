<?php
// Detect environment
$is_local = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1' || $_SERVER['SERVER_NAME'] === 'localhost');

if ($is_local) {
    // Local XAMPP MySQL Configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'cbfinance_accounting_loan_system');
} else {
    // Hosted cPanel MySQL Configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'cbfinance_cbfinance');
    define('DB_PASS', 'cbfinance#2026');
    define('DB_NAME', 'cbfinance_accounting_loan_system');
}

// Create connection
function getConnection() {
    try {
        $host = '127.0.0.1'; 
        $conn = new mysqli($host, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure session keys exist to prevent warnings
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'Administrator';
}
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'admin';
}
if (!isset($_SESSION['full_name'])) {
    $_SESSION['full_name'] = 'Administrator';
}
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'admin@example.com';
}
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'Administrator';
}
?>