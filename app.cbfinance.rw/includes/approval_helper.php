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
            break;
        }

        // ── LOAN: ADD ────────────────────────────────────────────────────────
        case 'loan.add': {
            // Store the full POST data as JSON — forward it back to the loan insert logic
            // We store the loan data and insert it into loan_portfolio
            $d = $data;
            $sql = "INSERT INTO loan_portfolio (
                loan_number, customer_id, loan_type, disbursement_amount, disbursement_date,
                interest_rate, loan_term, loan_term_unit, loan_status, loan_purpose,
                monitoring_fee_rate, application_fee, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception($conn->error);
            $stmt->bind_param('sisdsddsssddi',
                $d['loan_number'], (int)$d['customer_id'], $d['loan_type'],
                (float)$d['disbursement_amount'], $d['disbursement_date'],
                (float)$d['interest_rate'], (float)$d['loan_term'], $d['loan_term_unit'],
                $d['loan_status'] ?? 'Pending', $d['loan_purpose'] ?? '',
                (float)($d['monitoring_fee_rate'] ?? 0),
                (int)($d['application_fee'] ?? 0)
            );
            if (!$stmt->execute()) throw new Exception($stmt->error);
            $stmt->close();
            break;
        }

        // ── LOAN: DELETE ─────────────────────────────────────────────────────
        case 'loan.delete': {
            $conn->query("DELETE FROM loan_payments WHERE loan_id = $entity_id");
            $conn->query("DELETE FROM loan_instalments WHERE loan_id = $entity_id");
            $conn->query("DELETE FROM loan_portfolio WHERE loan_id = $entity_id");
            break;
        }

        default:
            throw new Exception("Unknown action: $entity_type.$action_type");
    }
}
