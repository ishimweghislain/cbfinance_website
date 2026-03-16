<?php
// Return plain text response
header('Content-Type: text/plain');

if (!isset($_GET['loan_id'])) {
    echo "ERROR:No loan ID provided";
    exit;
}

$loan_id = intval($_GET['loan_id']);

require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

try {
    // Get overdue installments for this loan
    $query = "
        SELECT li.id, li.loan_id, li.due_date, li.interest_amount, li.monitoring_fee
        FROM loan_instalments li
        WHERE li.loan_id = ? 
          AND li.payment_date IS NULL 
          AND (li.overdue_ledger_recorded = FALSE OR li.overdue_ledger_recorded IS NULL)
          AND DATEDIFF(CURDATE(), li.due_date) >= 30
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $processed = 0;
    
    while ($installment = $result->fetch_assoc()) {
        $vat_amount = $installment['monitoring_fee'] * 0.18;
        $journal_entry = 'JE-' . date('Ymd-His') . '-' . $installment['id'];
        
        $conn->begin_transaction();
        
        try {
            // Create ledger table if it doesn't exist
            $check_table = $conn->query("SHOW TABLES LIKE 'ledger'");
            if (!$check_table || $check_table->num_rows == 0) {
                $conn->query("
                    CREATE TABLE IF NOT EXISTS ledger (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        transaction_date DATE NOT NULL,
                        loan_id INT NOT NULL,
                        installment_id INT,
                        account_code VARCHAR(20),
                        account_name VARCHAR(100),
                        debit_amount DECIMAL(15,2) DEFAULT 0,
                        credit_amount DECIMAL(15,2) DEFAULT 0,
                        description VARCHAR(500),
                        journal_entry VARCHAR(100),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            }
            
            // Record all 6 ledger entries
            $entries = [
                ['1203', 'Interest Receivable', $installment['interest_amount'], 0],
                ['1204', 'Monitoring Fees Receivable', $installment['monitoring_fee'], 0],
                ['1206', 'VAT Receivable', $vat_amount, 0],
                ['4101', 'Interest on Loans – Income', 0, $installment['interest_amount']],
                ['4202', 'Monitoring Fee Income', 0, $installment['monitoring_fee']],
                ['2105', 'VAT Payable', 0, $vat_amount]
            ];
            
            $ledger_sql = "
                INSERT INTO ledger 
                (transaction_date, loan_id, installment_id, account_code, account_name, 
                 debit_amount, credit_amount, description, journal_entry)
                VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $ledger_stmt = $conn->prepare($ledger_sql);
            
            foreach ($entries as $entry) {
                $description = 'Manual 30-Day Overdue - Due ' . $installment['due_date'];
                $ledger_stmt->bind_param(
                    "iiissddss",
                    $installment['loan_id'],
                    $installment['id'],
                    $entry[0],
                    $entry[1],
                    $entry[2],
                    $entry[3],
                    $description,
                    $journal_entry
                );
                $ledger_stmt->execute();
            }
            $ledger_stmt->close();
            
            // Mark as processed
            $update_sql = "UPDATE loan_instalments SET overdue_ledger_recorded = TRUE WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $installment['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            $conn->commit();
            $processed++;
            
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
    
    echo "SUCCESS:" . $processed;
    
} catch (Exception $e) {
    echo "ERROR:" . $e->getMessage();
}

$conn->close();
?>
