<?php
// Return plain text response
header('Content-Type: text/plain');

require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

try {
    // Check for overdue installments
    $query = "
        SELECT COUNT(*) as count 
        FROM loan_instalments 
        WHERE payment_date IS NULL 
          AND (overdue_ledger_recorded = FALSE OR overdue_ledger_recorded IS NULL)
          AND DATEDIFF(CURDATE(), due_date) = 30
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        $row = $result->fetch_assoc();
        $count = $row['count'] ?? 0;
        echo "SUCCESS:" . $count;
    } else {
        echo "ERROR:Query failed";
    }
    
} catch (Exception $e) {
    echo "ERROR:" . $e->getMessage();
}

$conn->close();
?>
