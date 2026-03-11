<?php
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();

// Helper functions (copied from your recordpayment.php)
function PMT($rate, $nper, $pv) {
    if ($rate == 0) return -$pv / $nper;
    return -$pv * ($rate * pow(1 + $rate, $nper)) / (pow(1 + $rate, $nper) - 1);
}
function IPMT($rate, $period, $nper, $pv) {
    if ($period == 1) return -$pv * $rate;
    $pmt = PMT($rate, $nper, $pv);
    $remaining_balance = $pv;
    for ($i = 1; $i < $period; $i++) {
        $interest = -$remaining_balance * $rate;
        $principal = $pmt - $interest;
        $remaining_balance += $principal;
    }
    return -$remaining_balance * $rate;
}
function PPMT($rate, $period, $nper, $pv) {
    return PMT($rate, $nper, $pv) - IPMT($rate, $period, $nper, $pv);
}

function recalculateRemainingSchedule($conn, $loan_id, $current_instalment_number, $new_closing_balance, $interest_rate) {
    echo "Recalculating for Loan $loan_id from Month $current_instalment_number, New CB: $new_closing_balance\n";
    $query = "SELECT * FROM loan_instalments WHERE loan_id = ? AND instalment_number > ? ORDER BY instalment_number ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $loan_id, $current_instalment_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $future_instalments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($future_instalments)) {
        echo "No future instalments found.\n";
        return;
    }

    $num_remaining = count($future_instalments);
    $opening_balance = $new_closing_balance;

    for ($i = 0; $i < $num_remaining; $i++) {
        $inst = $future_instalments[$i];
        $inst_id = $inst['instalment_id'];
        $inst_num = $i + 1; 
        
        $interest = round($opening_balance * $interest_rate, 2);
        $mgmt_fee = floatval($inst['management_fee']); 
        
        $principal = round(-PPMT($interest_rate, $inst_num, $num_remaining, $new_closing_balance), 2);
        
        if ($i == $num_remaining - 1 || ($opening_balance - $principal) < 1) {
            $principal = $opening_balance;
        }

        $interest = round($opening_balance * $interest_rate, 2);
        $total_payment = round($principal + $interest + $mgmt_fee, 2);
        $closing_balance = max(0, round($opening_balance - $principal, 2));
        
        echo "Updating Month " . ($current_instalment_number + $i + 1) . ": OB=$opening_balance, Princ=$principal, Int=$interest, Total=$total_payment, CB=$closing_balance\n";

        $update_query = "UPDATE loan_instalments SET 
            opening_balance = ?,
            principal_amount = ?,
            interest_amount = ?,
            management_fee = ?,
            total_payment = ?,
            closing_balance = ?,
            balance_remaining = ?,
            updated_at = NOW()
            WHERE instalment_id = ?";
        $upd_stmt = $conn->prepare($update_query);
        $upd_stmt->bind_param("dddddddi", 
            $opening_balance, $principal, $interest, $mgmt_fee, $total_payment, $closing_balance, $total_payment, $inst_id
        );
        $upd_stmt->execute();
        $upd_stmt->close();

        $opening_balance = $closing_balance;
    }
}

// Rutayisire Peter: Loan 260
// Month 1 actual CB: 890,000 (after he paid 1M)
recalculateRemainingSchedule($conn, 260, 1, 890000, 0.05);

echo "Correction complete!\n";
?>
