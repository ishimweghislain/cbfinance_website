<?php
// XAMPP Default MySQL Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'cbfinance_cbfinance');
define('DB_PASS', 'cbfinance#2026');  // XAMPP default is empty password
define('DB_NAME', 'cbfinance_accounting_loan_system');

// Create connection
function getConnection() {
    try {
        // For XAMPP, use 127.0.0.1 instead of localhost if having socket issues
        $host = '127.0.0.1'; // Alternative: use 'localhost:3306'
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

// Set default user for demo
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Administrator';
}
?>