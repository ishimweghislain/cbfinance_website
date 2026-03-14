<?php
require_once 'config/database.php';
$conn = getConnection();

$sql = "CREATE TABLE IF NOT EXISTS `ledger` (
  `ledger_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_date` date NOT NULL,
  `class` varchar(50) NOT NULL,
  `account_code` varchar(10) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `particular` varchar(255) NOT NULL,
  `voucher_number` varchar(50) DEFAULT NULL,
  `narration` text DEFAULT NULL,
  `beginning_balance` decimal(15,2) DEFAULT 0.00,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `movement` decimal(15,2) DEFAULT 0.00,
  `ending_balance` decimal(15,2) DEFAULT 0.00,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ledger_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_account_code` (`account_code`),
  KEY `idx_voucher_number` (`voucher_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql)) {
    echo "Success: Ledger table created successfully.";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
