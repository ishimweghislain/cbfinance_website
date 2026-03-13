<?php
require_once __DIR__ . '/config/database.php';
$conn = getConnection();
if (!$conn) die("Connection failed");

echo "<h2>🔧 Fixing Local Database...</h2>";

// 1. Add column to loan_payments
$check = $conn->query("SHOW COLUMNS FROM loan_payments LIKE 'payment_evidence'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE loan_payments ADD COLUMN payment_evidence VARCHAR(255) DEFAULT NULL AFTER notes")) {
        echo "<p style='color:green;'>✅ Added 'payment_evidence' column to loan_payments table.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✅ Column 'payment_evidence' already exists.</p>";
}

// 2. Create uploads directory if missing
$upload_dir = __DIR__ . '/uploads/payments/';
if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        echo "<p style='color:green;'>✅ Created uploads/payments/ directory.</p>";
    } else {
        echo "<p style='color:red;'>❌ Failed to create uploads directory. Please create it manually.</p>";
    }
} else {
    echo "<p>✅ Uploads directory exists.</p>";
}

echo "<br><a href='index.php'>Return to Dashboard</a>";
$conn->close();
?>
