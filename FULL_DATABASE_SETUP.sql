-- ======================================================
-- CAPITAL BRIDGE FINANCE - DATABASE SCHEMA
-- ======================================================
-- 1. Run this entire script in your cPanel PHPMyAdmin (SQL tab).
-- 2. This creates the 'customers' table which handles EVERYTHING.
-- 3. There is no separate table for "Rejected" or "Pending"—
--    the system just looks at the 'status' column in this table.

CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `loan_type` varchar(50) DEFAULT NULL,
  
  -- Documents
  `doc_id` text,
  `doc_contract` text,
  `doc_statement` text,
  `doc_payslip` text,
  `doc_marital` text,
  `doc_rdb` text,
  
  -- Personal Details
  `birth_place` varchar(100) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `spouse` varchar(100) DEFAULT NULL,
  `spouse_occupation` varchar(100) DEFAULT NULL,
  `spouse_phone` varchar(50) DEFAULT NULL,
  `marriage_type` varchar(100) DEFAULT NULL,
  `address` text,
  `location` varchar(100) DEFAULT NULL,
  
  -- Project Details
  `project` text,
  `project_location` varchar(100) DEFAULT NULL,
  `caution_location` varchar(100) DEFAULT NULL,
  
  -- System Tracking
  `is_active` tinyint(1) DEFAULT '0',
  `status` varchar(50) DEFAULT 'Pending', -- Can be: Pending, Approved, Rejected, Action Required
  `rejection_reason` text,
  `correction_fields` text,
  `admin_note` text,
  `client_resubmitted` tinyint(1) DEFAULT '0',
  `resubmitted_fields` text,
  `organization` varchar(100) DEFAULT 'Capital Bridge Finance',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
