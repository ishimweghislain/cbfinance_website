<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'cbfinance_cbfinance');
define('DB_PASS', 'cbfinance#2026');
define('DB_NAME', 'cbfinance_accounting_loan_system');

/**
 * Get database connection
 */
function getWebsiteConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        // Log error and return null
        error_log("Website DB Connection Error: " . $e->getMessage());
        return null;
    }
}
?>
