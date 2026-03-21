<?php
// update_days_overdue.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?page=loans");
    exit();
}

$loan_id = intval($_POST['loan_id'] ?? 0);
$instalment_id = intval($_POST['instalment_id'] ?? 0);
$days_overdue = intval($_POST['days_overdue'] ?? 0);
$reason = $_POST['reason'] ?? '';

if ($loan_id <= 0 || $instalment_id <= 0) {
    $_SESSION['error_message'] = "Invalid request parameters";
    header("Location: index.php?page=recordpayment&loan_id=" . $loan_id);
    exit();
}

try {
    $conn = getConnection();

    // Update days overdue
    $query = "UPDATE loan_instalments 
              SET days_overdue = ?, 
                  updated_at = NOW() 
              WHERE instalment_id = ? AND loan_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $days_overdue, $instalment_id, $loan_id);

    if ($stmt->execute()) {
        // Optional: Log the change
        $log_query = "INSERT INTO instalment_audit_log 
                     (instalment_id, field_changed, old_value, new_value, reason, created_by, created_at)
                     VALUES (?, 'days_overdue', ?, ?, ?, ?, NOW())";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("iiisi", $instalment_id, $_POST['current_days'] ?? 0, $days_overdue, $reason, $_SESSION['user_id'] ?? 1);
        $log_stmt->execute();
        $log_stmt->close();

        $_SESSION['success_message'] = "Days overdue updated successfully to " . $days_overdue . " days";
    }
    else {
        $_SESSION['error_message'] = "Failed to update days overdue";
    }

    $stmt->close();
    $conn->close();


}
catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

exit();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <script>
        setInterval(() => {
            
            window.location.href="?page=recordpayment&loan_id=<? echo $loan_id ?>"
        }, 100);
    </script>
</body>
</html>
