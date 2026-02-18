/* 
  CB FINANCE - DATABASE MIGRATION V2
  Run this SQL in your CPanel phpMyAdmin to support the new loan application features.
*/

-- 1. Add supporting columns for loan applications and documents
ALTER TABLE customers 
ADD loan_type ENUM('Salary', 'Business') DEFAULT 'Salary' AFTER status,
ADD doc_id VARCHAR(255) COMMENT 'National ID Path',
ADD doc_contract VARCHAR(255) COMMENT 'Work Contract Path',
ADD doc_statement VARCHAR(255) COMMENT 'Bank Statement Path',
ADD doc_payslip VARCHAR(255) COMMENT 'Payslip Path',
ADD doc_marital VARCHAR(255) COMMENT 'Marital Status Cert Path',
ADD doc_rdb VARCHAR(255) COMMENT 'RDB Certificate Path';

-- 2. Ensure initial status for any existing nulls
UPDATE customers SET status = 'Approved' WHERE status IS NULL;
UPDATE customers SET is_active = 1 WHERE status = 'Approved';
UPDATE customers SET is_active = 0 WHERE status = 'Pending' OR status = 'Rejected';

-- 3. Optimization: Add index to email for fast status tracking
CREATE INDEX idx_customer_email ON customers(email);
