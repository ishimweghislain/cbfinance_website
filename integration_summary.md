### CB Finance - Customer Integration Updates

#### 1. Database Changes (CRITICAL)
Run the following SQL in your CPanel phpMyAdmin for the database:
```sql
ALTER TABLE customers ADD status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Approved' AFTER is_active;
UPDATE customers SET status = 'Approved' WHERE status IS NULL;
```

#### 2. Modified Files
The following files have been created or modified to support the new Customer Form and Approval Workflow:

**Website (Frontend):**
- `includes/db_connect.php`: Database connection configuration.
- `apply.php`: The new multi-step membership application form.

**System Portal (Backend - app.cbfinance.rw):**
- `app.cbfinance.rw/modules/customers.php`: Updated to show "Pending" applications with Approve/Reject buttons.
- `app.cbfinance.rw/modules/add_customer.php`: Updated to support the new status field and manual registration.
- `app.cbfinance.rw/modules/view_customer.php`: Updated to display all new member fields and approval status.
- `app.cbfinance.rw/modules/edit_customer.php`: Updated to allow editing of all new fields and status.

#### 3. New Workflow Summary
1. **User Application**: A person fills the form on the website (`apply.php`).
2. **Pending State**: The system saves them with `status = 'Pending'`, `is_active = 0`, and a temporary code (e.g., `PEND-123456`).
3. **Admin Review**: In the admin panel (`customers.php`), the admin sees the pending member (highlighted in yellow).
4. **Approval**: 
   - If **Approved**: The system generates a permanent ID (e.g., `C004`), sets `status = 'Approved'`, and `is_active = 1`. The member can now be used for loans.
   - If **Rejected**: The system sets `status = 'Rejected'` and `is_active = 0`.
5. **Manual Addition**: Admins can still add members manually through the system; these are set to `Approved` by default.
