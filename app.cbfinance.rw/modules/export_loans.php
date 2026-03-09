<?php
require_once __DIR__ . '/config/database.php';
$conn = getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query
$query = "SELECT 
    l.loan_number,
    c.customer_name,
    c.id_number,
    c.phone,
    l.disbursement_amount,
    l.interest_rate,
    l.disbursement_date,
    l.maturity_date,
    l.number_of_instalments,
    l.instalment_amount,
    l.loan_status,
    l.principal_outstanding,
    l.interest_outstanding,
    l.total_outstanding,
    l.days_overdue,
    l.penalties,
    l.collateral_type,
    l.collateral_value,
    l.created_at
    FROM loan_portfolio l
    JOIN customers c ON l.customer_id = c.customer_id
    WHERE 1=1";

if ($search) {
    $query .= " AND (l.loan_number LIKE '%$search%' OR c.customer_name LIKE '%$search%')";
}

if ($status_filter) {
    $query .= " AND l.loan_status = '$status_filter'";
}

if ($start_date && $end_date) {
    $query .= " AND l.disbursement_date BETWEEN '$start_date' AND '$end_date'";
}

$query .= " ORDER BY l.created_at DESC";

$result = $conn->query($query);
$loans = $result->fetch_all(MYSQLI_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=loans_export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
$headers = [
    'Loan Number',
    'Customer Name',
    'ID Number',
    'Phone',
    'Disbursement Amount',
    'Interest Rate (%)',
    'Disbursement Date',
    'Maturity Date',
    'Number of Instalments',
    'Instalment Amount',
    'Loan Status',
    'Principal Outstanding',
    'Interest Outstanding',
    'Total Outstanding',
    'Days Overdue',
    'Penalties',
    'Collateral Type',
    'Collateral Value',
    'Created Date'
];
fputcsv($output, $headers);

// Add data rows
foreach ($loans as $loan) {
    $row = [
        $loan['loan_number'],
        $loan['customer_name'],
        $loan['id_number'],
        $loan['phone'],
        $loan['disbursement_amount'],
        $loan['interest_rate'],
        $loan['disbursement_date'],
        $loan['maturity_date'],
        $loan['number_of_instalments'],
        $loan['instalment_amount'],
        $loan['loan_status'],
        $loan['principal_outstanding'],
        $loan['interest_outstanding'],
        $loan['total_outstanding'],
        $loan['days_overdue'],
        $loan['penalties'],
        $loan['collateral_type'],
        $loan['collateral_value'],
        $loan['created_at']
    ];
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
