<?php
/**
 * Approval Workflow Helper
 * All sensitive operations (add/edit/delete: customers & loans) must go through this.
 * Only Director or MD can approve/reject requests.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/**
 * Submit an action for approval instead of executing it directly.
 * Returns true on success, false on failure.
 */
function submitForApproval($conn, $action_type, $entity_type, $entity_id = null, $action_data = [], $description = '') {
    $submitted_by      = $_SESSION['username']   ?? 'unknown';
    $submitted_by_role = $_SESSION['role']        ?? 'Secretary';
    $action_data_json  = json_encode($action_data, JSON_UNESCAPED_UNICODE);
    $description       = $description ?: buildDescription($action_type, $entity_type, $action_data);

    $sql = "INSERT INTO pending_approvals 
            (action_type, entity_type, entity_id, action_data, description, submitted_by, submitted_by_role, status, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    // Use 0 instead of null for entity_id since mysqli int binding doesn't handle null well
    $safe_entity_id = $entity_id !== null ? (int)$entity_id : 0;

    $stmt->bind_param('sssssss',
        $action_type,
        $entity_type,
        $safe_entity_id,
        $action_data_json,
        $description,
        $submitted_by,
        $submitted_by_role
    );

    $result = $stmt->execute();
    if ($result) {
        require_once __DIR__ . '/activity_logger.php';
        logActivity($conn, 'submit_approval', $entity_type, $safe_entity_id, "Submitted $action_type $entity_type for approval: $description");
    }
    $stmt->close();
    return $result;
}

/**
 * Build a human-readable description of the pending action.
 */
function buildDescription($action_type, $entity_type, $data) {
    $name = $data['customer_name'] ?? $data['loan_number'] ?? $data['name'] ?? '(unknown)';
    $labels = [
        'add'    => 'Add new',
        'edit'   => 'Edit',
        'delete' => 'Delete',
    ];
    $action_label = $labels[$action_type] ?? $action_type;
    return "$action_label $entity_type: $name";
}

/**
 * Count pending approvals (for the sidebar badge).
 */
function countPendingApprovals($conn) {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM pending_approvals WHERE status = 'pending'");
    if (!$result) return 0;
    return (int)($result->fetch_assoc()['cnt'] ?? 0);
}

/**
 * Check if current user can approve (Director or MD only).
 */
function canApprove() {
    $role = $_SESSION['role'] ?? '';
    return in_array($role, ['Director', 'MD']);
}

/**
 * Execute an approved action — actually writes to the database.
 */
function executeApproval($conn, $approval) {
    $data        = json_decode($approval['action_data'], true);
    $action_type = $approval['action_type'];
    $entity_type = $approval['entity_type'];
    $entity_id   = (int)$approval['entity_id'];

    switch ($entity_type . '.' . $action_type) {

        // ── CUSTOMER: ADD ────────────────────────────────────────────────────
        case 'customer.add': {
            $sql = "INSERT INTO customers (
                customer_code, customer_name, birth_place, id_number, account_number,
                occupation, gender, date_of_birth, record_date, phone, email,
                organization, father_name, mother_name, marriage_type, spouse,
                spouse_id, spouse_occupation, spouse_phone, address, location,
                project, project_location, caution_location, loan_type, created_by,
                has_guarantor, guarantor_name, guarantor_id, guarantor_phone, guarantor_occupation,
                created_at, updated_at, is_active, status
            ) VALUES (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),1,'Approved'
            )";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception($conn->error);
            $d = $data;
            $stmt->bind_param('sssssssssssssssssssssssssssssss',
                $d['customer_code'], $d['customer_name'], $d['birth_place'], $d['id_number'],
                $d['account_number'], $d['occupation'], $d['gender'], $d['date_of_birth'],
                $d['record_date'], $d['phone'], $d['email'], $d['organization'],
                $d['father_name'], $d['mother_name'], $d['marriage_type'], $d['spouse'],
                $d['spouse_id'], $d['spouse_occupation'], $d['spouse_phone'], $d['address'],
                $d['location'], $d['project'], $d['project_location'], $d['caution_location'],
                $d['loan_type'], $d['created_by'],
                $d['has_guarantor'], $d['guarantor_name'], $d['guarantor_id'],
                $d['guarantor_phone'], $d['guarantor_occupation']
            );
            if (!$stmt->execute()) throw new Exception($stmt->error);
            
            require_once __DIR__ . '/activity_logger.php';
            logActivity($conn, 'create', 'customer', $conn->insert_id, "Approved creation of customer: {$d['customer_name']}");
            
            $stmt->close();
            break;
        }

        // ── CUSTOMER: EDIT ───────────────────────────────────────────────────
        case 'customer.edit': {
            $d = $data;
            $is_active = 1;
            $sql = "UPDATE customers SET
                customer_code=?, customer_name=?, birth_place=?, id_number=?,
                account_number=?, occupation=?, gender=?, date_of_birth=?,
                phone=?, father_name=?, mother_name=?, spouse=?,
                spouse_occupation=?, spouse_phone=?, marriage_type=?, address=?,
                location=?, project=?, project_location=?, caution_location=?,
                email=?, organization=?, status='Approved', is_active=1, updated_at=NOW()
                WHERE customer_id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception($conn->error);
            $stmt->bind_param('ssssssssssssssssssssssi',
                $d['customer_code'], $d['customer_name'], $d['birth_place'], $d['id_number'],
                $d['account_number'], $d['occupation'], $d['gender'], $d['date_of_birth'],
                $d['phone'], $d['father_name'], $d['mother_name'], $d['spouse'],
                $d['spouse_occupation'], $d['spouse_phone'], $d['marriage_type'], $d['address'],
                $d['location'], $d['project'], $d['project_location'], $d['caution_location'],
                $d['email'], $d['organization'],
                $entity_id
            );
            if (!$stmt->execute()) throw new Exception($stmt->error);
            require_once __DIR__ . '/activity_logger.php';
            logActivity($conn, 'update', 'customer', $entity_id, "Approved update of customer: {$d['customer_name']}");
            $stmt->close();
            break;
        }

        // ── CUSTOMER: DELETE ─────────────────────────────────────────────────
        case 'customer.delete': {
            // Cascade delete: payments → loan docs → loans → customer docs → notes → customer
            foreach ([
                "DELETE FROM loan_payments WHERE loan_id IN (SELECT loan_id FROM loan_portfolio WHERE customer_id = $entity_id)",
                "DELETE FROM loan_instalments WHERE loan_id IN (SELECT loan_id FROM loan_portfolio WHERE customer_id = $entity_id)",
                "DELETE FROM loan_portfolio WHERE customer_id = $entity_id",
                "DELETE FROM customers WHERE customer_id = $entity_id"
            ] as $del_sql) {
                $conn->query($del_sql);
            }
            require_once __DIR__ . '/activity_logger.php';
            logActivity($conn, 'delete', 'customer', $entity_id, "Approved deletion of customer and all related data (ID: $entity_id)");
            break;
        }

        // ── LOAN: ADD ────────────────────────────────────────────────────────
        case 'loan.add': {
            $d = $data;
            $topup_type_sql = (isset($d['topup_type']) && $d['topup_type'] !== null)
                ? "'" . $conn->real_escape_string($d['topup_type']) . "'"
                : "NULL";

            $sql = "INSERT INTO loan_portfolio (
                customer_id, loan_number, loan_amount, 
                management_fee_rate, management_fee_amount, total_disbursed,
                interest_rate, number_of_instalments,
                disbursement_date, maturity_date,
                total_interest, total_management_fees, total_payment, monthly_payment,
                principal_outstanding, interest_outstanding, total_outstanding,
                cash_amount, bank_amount,
                collateral_type, collateral_description,
                collateral_value, collateral_net_value,
                provisional_rate, general_provision, net_book_value,
                accrued_days, loan_status,
                is_topup, topup_type,
                created_by, created_at, updated_at,
                deduct_fee_from_disbursed
            ) VALUES (
                " . intval($d['customer_id']) . ",
                '" . $conn->real_escape_string($d['loan_number']) . "',
                " . floatval($d['loan_amount']) . ",
                " . floatval($d['management_fee_rate']) . ",
                " . floatval($d['management_fee']) . ",
                " . floatval($d['total_disbursed']) . ",
                " . floatval($d['interest_rate']) . ",
                " . intval($d['number_of_instalments']) . ",
                '" . $conn->real_escape_string($d['disbursement_date']) . "',
                '" . $conn->real_escape_string($d['maturity_date']) . "',
                " . floatval($d['total_interest']) . ",
                " . floatval($d['total_management_fees']) . ",
                " . floatval($d['total_payment']) . ",
                " . floatval($d['monthly_payment']) . ",
                " . floatval($d['total_disbursed']) . ",  -- initial principal outstanding
                " . floatval($d['total_interest']) . ",
                " . floatval($d['total_disbursed'] + $d['total_interest']) . ",
                " . floatval($d['cash_amount']) . ",
                " . floatval($d['bank_amount']) . ",
                '" . $conn->real_escape_string($d['collateral_type']) . "',
                '" . $conn->real_escape_string($d['collateral_description']) . "',
                " . floatval($d['collateral_value']) . ",
                " . floatval($d['collateral_net_value']) . ",
                1.0,  
                " . floatval($d['total_disbursed'] * 0.01) . ", 
                " . floatval(($d['total_disbursed'] + $d['total_interest']) - ($d['total_disbursed'] * 0.01)) . ", 
                0, 
                'Active',
                " . intval($d['is_topup']) . ",
                $topup_type_sql,
                1,
                NOW(),
                NOW(),
                " . intval($d['deduct_fee']) . "
            )";
            
            if (!$conn->query($sql)) throw new Exception("Add loan failed: " . $conn->error);
            $new_loan_id = $conn->insert_id;

            // Update customer
            $conn->query("UPDATE customers SET 
                current_balance = current_balance + " . floatval($d['loan_amount']) . ",
                total_loans = total_loans + " . floatval($d['loan_amount']) . ",
                updated_at = NOW()
                WHERE customer_id = " . intval($d['customer_id']));

            // Installment schedule
            _helper_createInstallmentSchedule(
                $conn, $new_loan_id, $d['loan_number'], $d['disbursement_date'],
                $d['number_of_instalments'], 1,
                $d['total_disbursed'], $d['interest_rate'], $d['management_fee_rate'], (bool)$d['deduct_fee']
            );

            // Transaction
            _helper_createTransactionRecord(
                $conn, $new_loan_id, $d['loan_number'], 'Disbursement',
                $d['disbursement_date'], $d['total_disbursed'], "Loan disbursement", 1
            );
            require_once __DIR__ . '/activity_logger.php';
            logActivity($conn, 'create', 'loan', $new_loan_id, "Approved creation of loan: {$d['loan_number']} for customer ID: {$d['customer_id']}");
            break;
        }

        // ── LOAN: EDIT ───────────────────────────────────────────────────────
        case 'loan.edit': {
            $d = $data;
            // Update loan portfolio
            $sql = "UPDATE loan_portfolio SET
                customer_id = " . intval($d['customer_id']) . ",
                loan_number = '" . $conn->real_escape_string($d['loan_number']) . "',
                loan_amount = " . floatval($d['loan_amount']) . ",
                management_fee_rate = " . floatval($d['management_fee_rate']) . ",
                management_fee_amount = " . floatval($d['management_fee_amount']) . ",
                total_disbursed = " . floatval($d['total_disbursed']) . ",
                interest_rate = " . floatval($d['interest_rate']) . ",
                number_of_instalments = " . intval($d['number_of_instalments']) . ",
                disbursement_date = '" . $conn->real_escape_string($d['disbursement_date']) . "',
                maturity_date = '" . $conn->real_escape_string($d['maturity_date']) . "',
                total_interest = " . floatval($d['total_interest']) . ",
                total_management_fees = " . floatval($d['total_management_fees']) . ",
                total_payment = " . floatval($d['total_payment']) . ",
                monthly_payment = " . floatval($d['monthly_payment']) . ",
                principal_outstanding = " . floatval($d['principal_outstanding']) . ",
                interest_outstanding = " . floatval($d['interest_outstanding']) . ",
                total_outstanding = " . floatval($d['total_outstanding']) . ",
                cash_amount = " . floatval($d['cash_amount']) . ",
                bank_amount = " . floatval($d['bank_amount']) . ",
                collateral_type = '" . $conn->real_escape_string($d['collateral_type']) . "',
                collateral_description = '" . $conn->real_escape_string($d['collateral_description']) . "',
                collateral_value = " . floatval($d['collateral_value']) . ",
                collateral_net_value = " . floatval($d['collateral_net_value']) . ",
                provisional_rate = 1.0,
                general_provision = " . floatval($d['general_provision']) . ",
                net_book_value = " . floatval($d['net_book_value']) . ",
                accrued_days = " . intval($d['accrued_days']) . ",
                loan_status = '" . $conn->real_escape_string($d['loan_status']) . "',
                updated_at = NOW()
            WHERE loan_id = " . intval($entity_id);
            
            if (!$conn->query($sql)) throw new Exception("Edit loan failed: " . $conn->error);

            // Adjust customer balances
            if ($d['old_customer_id'] != $d['customer_id']) {
                $conn->query("UPDATE customers SET current_balance = current_balance - " . floatval($d['old_loan_amount']) . ", total_loans = total_loans - " . floatval($d['old_loan_amount']) . " WHERE customer_id = " . intval($d['old_customer_id']));
                $conn->query("UPDATE customers SET current_balance = current_balance + " . floatval($d['loan_amount']) . ", total_loans = total_loans + " . floatval($d['loan_amount']) . " WHERE customer_id = " . intval($d['customer_id']));
            } else {
                $balance_diff = $d['loan_amount'] - $d['old_loan_amount'];
                if ($balance_diff != 0) {
                    $conn->query("UPDATE customers SET current_balance = current_balance + " . floatval($balance_diff) . ", total_loans = total_loans + " . floatval($balance_diff) . " WHERE customer_id = " . intval($d['customer_id']));
                }
            }

            // Robust recreation of the instalment schedule
            // Step 1: Only delete instalments that DON'T have payments
            $conn->query("DELETE FROM loan_instalments WHERE loan_id = " . intval($entity_id) . " AND instalment_id NOT IN (SELECT loan_instalment_id FROM loan_payments)");

            // Step 2: Create/Update the schedule (helper handles skipping existing ones)
            _helper_createInstallmentSchedule(
                $conn, $entity_id, $d['loan_number'], $d['disbursement_date'],
                $d['number_of_instalments'], 1,
                $d['total_disbursed'], $d['interest_rate'], $d['management_fee_rate'], true
            );

            // Additional update transaction
            _helper_createTransactionRecord(
                $conn, $entity_id, $d['loan_number'], 'Update',
                date('Y-m-d'), $d['total_disbursed'], "Loan updated via approval", 1
            );
            require_once __DIR__ . '/activity_logger.php';
            logActivity($conn, 'update', 'loan', $entity_id, "Approved update of loan: {$d['loan_number']}");
            break;
        }

        // ── LOAN: DELETE ─────────────────────────────────────────────────────
        case 'loan.delete': {
            // Get loan amount before deleting
            $res = $conn->query("SELECT loan_amount, customer_id FROM loan_portfolio WHERE loan_id = $entity_id");
            if ($res && $row = $res->fetch_assoc()) {
                $amt = floatval($row['loan_amount']);
                $cid = intval($row['customer_id']);
                // Restore balance
                $conn->query("UPDATE customers SET current_balance = current_balance - $amt, total_loans = total_loans - $amt WHERE customer_id = $cid");
            }
            
            // Actual deletion
            $conn->query("DELETE FROM loan_payments WHERE loan_id = $entity_id");
            $conn->query("DELETE FROM loan_instalments WHERE loan_id = $entity_id");
            $conn->query("DELETE FROM loan_portfolio WHERE loan_id = $entity_id");

            require_once __DIR__ . '/activity_logger.php';
            logActivity($conn, 'delete', 'loan', $entity_id, "Approved deletion of loan ID: $entity_id");
            break;
        }

    }
}

// ── ISOLATED HELPER FUNCTIONS FOR LOAN CALCULATIONS ──
if (!function_exists('_helper_PPMT')) {
    function _helper_PMT($rate, $nper, $pv) {
        if ($rate == 0) return -$pv / $nper;
        return -$pv * ($rate * pow(1 + $rate, $nper)) / (pow(1 + $rate, $nper) - 1);
    }
    function _helper_IPMT($rate, $period, $nper, $pv) {
        if ($period == 1) return -$pv * $rate;
        $pmt = _helper_PMT($rate, $nper, $pv);
        $remaining_balance = $pv;
        for ($i = 1; $i < $period; $i++) {
            $interest = -$remaining_balance * $rate;
            $principal = $pmt - $interest;
            $remaining_balance += $principal;
        }
        return -$remaining_balance * $rate;
    }
    function _helper_PPMT($rate, $period, $nper, $pv) {
        if ($rate == 0) return -$pv / $nper;
        return _helper_PMT($rate, $nper, $pv) - _helper_IPMT($rate, $period, $nper, $pv);
    }
    function _helper_generateLoanSchedule($total_disbursed, $interest_rate, $term, $management_fee_rate = 5.5, $deduct_fee = true) {
        $schedule = [];
        $monthly_rate = $interest_rate / 100;
        $management_fee_per_month = round($total_disbursed * ($management_fee_rate / 100), 2);
        $opening_balance = $total_disbursed;
        
        for ($i = 1; $i <= $term; $i++) {
            $interest = round($opening_balance * $monthly_rate, 2);
            $principal = round(-_helper_PPMT($monthly_rate, $i, $term, $total_disbursed), 2);
            $management_fee = ($i == 1 && $deduct_fee) ? 0 : $management_fee_per_month;
            
            $principal = round($principal / 10) * 10;
            $interest = round($interest / 10) * 10;
            $management_fee = round($management_fee / 10) * 10;
            
            $total_payment = $principal + $interest + $management_fee;
            $closing_balance = max(0, $opening_balance - $principal);
            
            $schedule[] = [
                'instalment_number' => $i,
                'opening_balance' => round($opening_balance, 2),
                'principal' => $principal,
                'interest' => $interest,
                'management_fee' => $management_fee,
                'total_payment' => $total_payment,
                'closing_balance' => round($closing_balance, 2)
            ];
            $opening_balance = $closing_balance;
        }
        return $schedule;
    }
    function _helper_createInstallmentSchedule($conn, $loan_id, $loan_number, $disbursement_date, $number_of_instalments, $user_id, $total_disbursed, $interest_rate, $management_fee_rate = 5.5, $deduct_fee = true) {
        $schedule = _helper_generateLoanSchedule($total_disbursed, $interest_rate, $number_of_instalments, $management_fee_rate, $deduct_fee);
        $disbursement_date_obj = new DateTime($disbursement_date);
        foreach ($schedule as $inst) {
            $i_num = $inst['instalment_number'];
            
            // Check if this instalment already exists (e.g. it was kept because it had payments)
            $check = $conn->query("SELECT instalment_id FROM loan_instalments WHERE loan_id = " . intval($loan_id) . " AND instalment_number = " . intval($i_num));
            if ($check && $check->num_rows > 0) {
                // Already exists, skip insertion to prevent duplication
                continue;
            }

            $due_date_obj = clone $disbursement_date_obj;
            $due_date_obj->modify("+$i_num months");
            $due_date = $due_date_obj->format('Y-m-d');
            
            $conn->query("INSERT INTO loan_instalments (
                loan_id, loan_number, instalment_number, due_date, opening_balance,
                principal_amount, interest_amount, management_fee, total_payment, closing_balance,
                paid_amount, principal_paid, interest_paid, management_fee_paid,
                balance_remaining, status, days_overdue, penalty_amount, created_by, created_at
            ) VALUES (
                ".intval($loan_id).", '".$conn->real_escape_string($loan_number)."', $i_num, '$due_date', ".floatval($inst['opening_balance']).",
                ".floatval($inst['principal']).", ".floatval($inst['interest']).", ".floatval($inst['management_fee']).", ".floatval($inst['total_payment']).", ".floatval($inst['closing_balance']).",
                0, 0, 0, 0, ".floatval($inst['total_payment']).", 'Pending', 0, 0, ".intval($user_id).", NOW()
            )");
        }
    }
    function _helper_createTransactionRecord($conn, $loan_id, $loan_number, $type, $date, $amount, $description, $user_id) {
        $conn->query("INSERT INTO loan_transactions (
            loan_id, loan_number, transaction_type, transaction_date, amount, description, created_by, created_at
        ) VALUES (
            ".intval($loan_id).", '".$conn->real_escape_string($loan_number)."', '".$conn->real_escape_string($type)."', '".$conn->real_escape_string($date)."',
            ".floatval($amount).", '".$conn->real_escape_string($description)."', ".intval($user_id).", NOW()
        )");
    }
}
