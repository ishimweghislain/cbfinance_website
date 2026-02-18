-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 18, 2026 at 01:18 PM
-- Server version: 5.7.44
-- PHP Version: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cbfinance_accounting_loan_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `application_fees`
--

CREATE TABLE `application_fees` (
  `application_fee_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `fee_reference` varchar(50) NOT NULL,
  `fee_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `income_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT 'Cash',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL,
  `asset_number` varchar(20) NOT NULL,
  `category` varchar(50) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text,
  `serial_number` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `assigned_user` varchar(100) DEFAULT NULL,
  `acquisition_date` date NOT NULL,
  `acquisition_value` decimal(15,2) NOT NULL,
  `supplier` varchar(200) DEFAULT NULL,
  `additions` decimal(15,2) DEFAULT '0.00',
  `lifespan_years` int(11) NOT NULL,
  `depreciation_rate` decimal(5,2) NOT NULL,
  `asset_condition` varchar(1000) DEFAULT NULL,
  `monthly_depreciation` decimal(15,2) DEFAULT NULL,
  `daily_depreciation` decimal(15,2) DEFAULT NULL,
  `accumulated_depreciation` decimal(15,2) DEFAULT '0.00',
  `reporting_date` date DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_value` decimal(15,2) DEFAULT NULL,
  `disposal_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `chart_of_accounts`
--

CREATE TABLE `chart_of_accounts` (
  `account_id` int(11) NOT NULL,
  `class` varchar(50) NOT NULL,
  `account_code` varchar(10) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_type` varchar(50) DEFAULT NULL,
  `sub_type` varchar(50) DEFAULT NULL,
  `normal_balance` varchar(10) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `chart_of_accounts`
--

INSERT INTO `chart_of_accounts` (`account_id`, `class`, `account_code`, `account_name`, `account_type`, `sub_type`, `normal_balance`, `is_active`, `created_at`, `updated_at`) VALUES
(19, 'Balance Sheet', '1250', 'Loan Offset Control Account', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(20, 'Balance Sheet', '1301', 'Prepaid Interest Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(21, 'Balance Sheet', '1302', 'Prepaid Monitoring Fees', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(22, 'Balance Sheet', '1303', 'Prepaid Monitoring Fees VAT', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(23, 'Balance Sheet', '1304', 'Prepaid Rent', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(24, 'Balance Sheet', '1305', 'Prepaid Insurance', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(25, 'Balance Sheet', '1306', 'Due from Shareholders', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(26, 'Balance Sheet', '1401', 'Office Furniture', 'Asset', 'Fixed Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(29, 'Balance Sheet', '1404', 'Motor Vehicle', 'Asset', 'Fixed Asset', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(30, 'Balance Sheet', '1405', 'Accumulated Depreciation', 'Asset', 'Fixed Asset', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(31, 'Balance Sheet', '2101', 'Accounts Payable', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(33, 'Balance Sheet', '2103', 'Accrued Salaries', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(34, 'Balance Sheet', '2104', 'Accrued Withholding Tax Payable', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(37, 'Balance Sheet', '2107', 'Acrrued Pension', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(38, 'Balance Sheet', '2108', 'Accrued Maternity Leave', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(39, 'Balance Sheet', '2109', 'Accrued Mutuel', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(40, 'Balance Sheet', '2201', 'Loan Payable – Banks', 'Liability', 'Long-term Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(41, 'Balance Sheet', '2202', 'Loan Payable – Other Institutions', 'Liability', 'Long-term Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(45, 'Balance Sheet', '2402', 'Deferred Monitoring Fees', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(46, 'Balance Sheet', '2403', 'Deferred VAT', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(50, 'Balance Sheet', '2408', 'Loan Overpayment Liability', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(51, 'Balance Sheet', '2409', 'Refunds Payable', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(54, 'Balance Sheet', '3103', 'Current Year Earnings/Loss', 'Equity', 'Retained Earnings', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(55, 'Balance Sheet', '3104', 'Capital Reserve', 'Equity', 'Other Equity', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(56, 'Income Statement', '4101', 'Interest on Loans', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(58, 'Income Statement', '4201', 'Disbursement Fee Income', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(61, 'Income Statement', '4204', 'Application Fees', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(62, 'Income Statement', '4205', 'Other Operating Income', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(63, 'Income Statement', '4301', 'Impairment Recovery (Provision Reversal Income)', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(64, 'Income Statement', '5101', 'Salaries & Wages', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(65, 'Income Statement', '5102', 'Staff Training & Development', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(66, 'Income Statement', '5103', 'Transport & Travel', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(67, 'Income Statement', '5104', 'Rent', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(70, 'Income Statement', '5107', 'Communication (Internet, Phone)', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(71, 'Income Statement', '5108', 'Insurance', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(77, 'Income Statement', '5202', 'Consulting Services', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(78, 'Income Statement', '5203', 'Audit & Accounting Services', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(79, 'Income Statement', '5250', 'IT and Communication', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(80, 'Income Statement', '5261', 'Office Equipment Repairs', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(81, 'Income Statement', '5262', 'Building Maintenance', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(82, 'Income Statement', '5263', 'Vehicle Maintenance', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(83, 'Income Statement', '5264', 'Office Partition', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(84, 'Income Statement', '5265', 'Office Branding', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(85, 'Income Statement', '5270', 'Marketing & Advertising Expense', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(86, 'Income Statement', '5275', 'Branding and Design Expenses', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(87, 'Income Statement', '5301', 'Loan Interest Expense', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(88, 'Income Statement', '5302', 'Bank Charges', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(90, 'Income Statement', '5401', 'Depreciation – Furniture', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(91, 'Income Statement', '5402', 'Depreciation – Equipment', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(92, 'Income Statement', '5403', 'Loan Loss Provision Expense', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22'),
(93, 'Income Statement', '5501', 'Loan Loss Expense – Principal', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 11:30:22', '2026-01-08 11:30:22');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `occupation` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `date_of_birth` date DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `organization` varchar(255) DEFAULT 'Capital Bridge Finance',
  `phone` varchar(50) NOT NULL,
  `father_name` varchar(255) DEFAULT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `spouse` varchar(255) DEFAULT NULL,
  `spouse_occupation` varchar(255) DEFAULT NULL,
  `spouse_phone` varchar(50) DEFAULT NULL,
  `marriage_type` enum('Ivanga mutungo','Ivangura mutungo','Muhahano','Single') DEFAULT 'Single',
  `address` text,
  `location` varchar(255) DEFAULT NULL,
  `project` varchar(255) DEFAULT NULL,
  `project_location` varchar(255) DEFAULT NULL,
  `caution_location` varchar(255) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `risk_rating` enum('Low','Medium','High') DEFAULT 'Medium',
  `current_balance` decimal(15,2) DEFAULT '0.00',
  `total_loans` decimal(15,2) DEFAULT '0.00',
  `total_paid` decimal(15,2) DEFAULT '0.00',
  `is_active` tinyint(1) DEFAULT '1',
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Approved',
  `loan_type` enum('Salary','Business') DEFAULT 'Salary',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `doc_id` varchar(255) DEFAULT NULL COMMENT 'National ID Path',
  `doc_contract` varchar(255) DEFAULT NULL COMMENT 'Work Contract Path',
  `doc_statement` varchar(255) DEFAULT NULL COMMENT 'Bank Statement Path',
  `doc_payslip` varchar(255) DEFAULT NULL COMMENT 'Payslip Path',
  `doc_marital` varchar(255) DEFAULT NULL COMMENT 'Marital Status Cert Path',
  `doc_rdb` varchar(255) DEFAULT NULL COMMENT 'RDB Certificate Path',
  `rejection_reason` text,
  `correction_fields` text,
  `admin_note` text,
  `client_resubmitted` tinyint(1) DEFAULT '0',
  `resubmitted_fields` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_code`, `customer_name`, `birth_place`, `id_number`, `account_number`, `occupation`, `gender`, `date_of_birth`, `contact_person`, `email`, `organization`, `phone`, `father_name`, `mother_name`, `spouse`, `spouse_occupation`, `spouse_phone`, `marriage_type`, `address`, `location`, `project`, `project_location`, `caution_location`, `tin_number`, `risk_rating`, `current_balance`, `total_loans`, `total_paid`, `is_active`, `status`, `loan_type`, `created_by`, `created_at`, `updated_at`, `doc_id`, `doc_contract`, `doc_statement`, `doc_payslip`, `doc_marital`, `doc_rdb`, `rejection_reason`, `correction_fields`, `admin_note`, `client_resubmitted`, `resubmitted_fields`) VALUES
(63, '', 'MUGABO Jean Paul', 'n/a', '1198680029041091', 'n/a', 'n/a', 'Male', '1986-02-01', NULL, 'mugabojapaul@gmail.com', 'Capital Bridge Finance', '0788692380', 'Makuza Godefroid', 'Niyonabira Marie Therese', 'NGIRINSHUTI Laetitia', 'NGIRISHUTI Laetitia', '0', 'Ivanga mutungo', 'Akarare:Gasabo,\r\nUmurenge:Bumbogo\r\nAkagari:Kinyaga\r\nUmudugudu:Zi', 'Gasabo', 'Supplier', 'n/a', 'N/a', NULL, 'Medium', 9450000.00, 9450000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-04 21:13:40', '2026-02-10 04:42:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(64, 'C002', 'NSHIMIYIMANA ALEXIS', 'gasabo', '1199480011088047', 'N/A', 'Business', 'Male', NULL, NULL, 'alexnshimiyimana226@gmail.com', 'Capital Bridge Finance', '0783904850', 'N/A', 'N/A', 'MUHAWENIMANA ROSINE', 'BUSINESS WOMAN', '0788266355', 'Ivanga mutungo', 'GASABO-GISOZI-MUSEZERA-MUSEZERA', 'GASABO', 'n/a', 'GASABO-KUMUCYO ESTATE-ALOGAN BAR', 'GASABO-KUMUCYO ESTATE-ALOGAN BAR', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-04 21:17:23', '2026-02-04 21:17:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(65, 'C003', 'BURASA Gloria', 'N/A', '1198770006475008', 'N/A', 'Business', 'Female', NULL, NULL, 'gloria@gmail.com', 'Capital Bridge Finance', '0788306042', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 1140000.00, 1140000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 03:46:41', '2026-02-13 09:55:02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(66, 'C004', 'BUTARE GEORGE BONNE ANNEE', 'Kasese-Uganda, , Uganda', '1199080231184160', 'N/A', 'BRALIRWA', 'Male', NULL, NULL, 'georgebonneannee@gmail.com', 'Capital Bridge Finance', '0788314205', 'Butare', 'Uwayezu Mary', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'KICUKIRO-NYARUGUNGA-KAMASHASHI-KABAGENDWA', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 1606500.00, 1606500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 03:49:18', '2026-02-10 04:52:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(67, 'C005', 'NZARAMBA Steevenson', 'N/A', '1197880014766164', 'N/A', 'consultant', 'Male', NULL, NULL, 'nzaste11@gmail.com', 'Capital Bridge Finance', '0788302685', 'N/A', 'N/A', '', '', '', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 3260250.00, 3260250.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 03:50:45', '2026-02-10 04:58:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(68, 'C006', 'HABIMANA Clement', 'Kicukiro-Gikondo', '1189380186450203', 'N/A', 'Salary Earner', 'Male', NULL, NULL, 'clement@gmail.com', 'Capital Bridge Finance', '0788352188', 'HITIMANA Claver', 'MUKAGASANA Laurence', '', '', '', 'Ivanga mutungo', 'N/A', 'Gasabo', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 1890000.00, 1890000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 03:53:02', '2026-02-10 05:02:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(69, 'C007', 'NSENGIYUMVA JACQUES', 'KAYOVE/RWANDA', '1198380161165227', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'jacque@gmail.com', 'Capital Bridge Finance', '0788308876', 'HATEGEKIMANA NELSON', 'MUKANTABANA RACHEL', 'NZAMUKOSHA OLIVE', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 1020600.00, 1020600.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 03:54:57', '2026-02-10 06:42:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(70, 'C008', 'UWIRAGIYE Valentine', 'N/A', '1199480171490033', 'N/A', 'coiffeur', 'Female', NULL, NULL, 'valentine@gmail.com', 'Capital Bridge Finance', '0786968190', 'N/A', 'N/A', '', '', '', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 03:56:26', '2026-02-05 03:56:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(71, 'C009', 'BAYIKARE Hawanawema', 'congo', '1196770091705103', 'N/A', 'coiffeur', 'Male', NULL, NULL, 'bayikare@gmail.com', 'Capital Bridge Finance', '0788538301', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 300000.00, 300000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 03:58:04', '2026-02-13 15:00:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(72, 'C010', 'ASIIMWE JOSEPHINE', 'GASABO/RWANDA', '1198770171189142', 'N/A', 'entrepreneur', 'Female', NULL, NULL, 'asimwe@gmail.com', 'Capital Bridge Finance', '0788273071', 'MUGABO', 'MUGABO', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 1890000.00, 1890000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 03:59:59', '2026-02-10 20:18:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(73, 'C011', 'IMANISHIMWE Cyrille', 'BURUNDI', '1196680063501102', '4028200059590', 'ENTREPRENEUR', 'Male', NULL, NULL, 'imanishimwe@gmail.com', 'Capital Bridge Finance', '0788471030', 'N/A', 'N/A', 'UMULISA MARY', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'KICUKIRO-GATENGA-KAMUBO-SAYENZI', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 472500.00, 472500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:03:15', '2026-02-10 20:19:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(74, 'C012', 'GASORE Regis', 'Gitega, Burundi', '1199080185966365', '100247249278', 'Business owner', 'Male', NULL, NULL, 'regis@gmail.com', 'Capital Bridge Finance', '0782877169', 'RWIGEMA Gonzagwe', 'MWITEGEREZE', 'MUNYANSHONGORE ISUGI CLARISSE RECONFORT', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 11340000.00, 11340000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:05:30', '2026-02-13 08:27:59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(75, 'C013', 'Kazayire Theopiste Higiro', 'N/A', '1197270005555232', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'ktheopiste72@gmail.com', 'Capital Bridge Finance', '0788305767', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'Gasabo/Kimironko/Bibare 2/Inyange', 'Gasabo/Kimironko/BibareGasabo/Kimironko/Bibare 2/Inyange 2/Inyange', 'Garment', 'Gasabo/Kimironko/Bibare 2/Inyange', 'Gasabo/Kimironko/Bibare 2/Inyange', NULL, 'Medium', 3780000.00, 3780000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:12:18', '2026-02-10 20:29:20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(76, 'C014', 'TUYISHIME MOISE', 'N/A', '1199480200723010', 'N/A', 'salary annual', 'Male', NULL, NULL, 'Ktuyishimemoise@gmail.com', 'Capital Bridge Finance', '0781523904', 'n/a', 'n/a', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'KICUKIRO,GATENGA,NYANZA,MAREMBO', 'N/A', 'KICUKIRO,GATENGA,NYANZA,MAREMBO', 'N/A', NULL, 'Medium', 283500.00, 283500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:14:23', '2026-02-10 20:45:17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(77, 'C015', 'MUSINGUZI Geoffrey', 'mbarara/uganda', '1197880124655183', 'n/a', 'SNV', 'Male', NULL, NULL, 'musinguzi@gmail.com', 'Capital Bridge Finance', '0788300570', 'GATSINZI', 'MUKANYONGA', 'Judith MWIHOREZE', 'n/a', '0788258729', 'Ivanga mutungo', 'n/a', 'GASABO/KIMIHURURA/KIMIHURURA', 'n/a', 'n/a', 'n/a', NULL, 'Medium', 1890000.00, 1890000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:16:54', '2026-02-10 20:55:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(78, 'C016', 'INGABIRE Sophie', 'N/A', '1198070005864034', 'N/A', 'Rwandair', 'Female', NULL, NULL, 'sophie@gmail.com', 'Capital Bridge Finance', '0788529481', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 2463500.00, 2463500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:18:37', '2026-02-11 13:19:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(79, 'C017', 'KIZIHIRA Jones', 'N/A', '1198480013041070', 'N/A', 'N/A', 'Male', NULL, NULL, 'jkizihira@gmail.com', 'Capital Bridge Finance', '0788532402', 'N/A', 'N/A', 'N/A', 'N/A', '0789211488', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 20790000.00, 20790000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:20:11', '2026-02-11 12:53:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(80, 'C018', 'MURENGEZI Aime Aimable', 'N/A', '1198980161194002', 'N/A', 'PHARO FOUNDATION-SENIOR FINANCE OFFICER', 'Male', NULL, NULL, 'aime@gmail.com', 'Capital Bridge Finance', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 1890000.00, 1890000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:22:01', '2026-02-18 10:53:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(81, 'C019', 'MUNYESHULI Denis Mupenzi', 'N/A', '1198180144406165', 'N/A', 'Business', 'Male', NULL, NULL, 'denis.mupenzi@gmail.com', 'Capital Bridge Finance', '0788308132', 'N/A', 'N/A', 'KANANGA PHIONA', 'N/A', '0788313310', 'Ivanga mutungo', 'N/A', 'GASAB0-KACYIRU-KAMATAMU-RWINZOVU', 'AVOCADO EXPORTATION', 'N/A', 'N/A', NULL, 'Medium', 25785000.00, 25785000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:24:19', '2026-02-13 08:30:37', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(82, 'C020', 'Editha Elias  Lugaju', 'n/a', '1198970221444235', 'n/a', 'Salary Earner', 'Male', NULL, NULL, 'edithaelias12@gmail.com', 'Capital Bridge Finance', '250782367365', 'n/a', 'n/a', 'KALISA EMMERY', '', '0781141919', 'Ivanga mutungo', 'n/a', 'Southern/kamonyi/runda/kagina/kagina', 'salary loan', 'kamonyi', 'n/a', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:26:47', '2026-02-10 06:56:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(83, 'C021', 'Joselyne Rukundo', 'Muhanga-Rwanda', '1199370176587190', 'n/a', 'Entrepreneur', 'Male', NULL, NULL, 'rukundojoselyne4@gmail.com', 'Capital Bridge Finance', '0788711761', 'Mudaheranwa Pierre', 'Muhayimana Josephine', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', 'n/a', 'kigali/kicukiro/kanombe/rubirizi/beninka', 'business loan', 'kicukiro', 'n/a', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:28:51', '2026-02-10 07:13:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(84, 'C022', 'Nyiringabo lourent', 'N/A', '1198280206677235', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'nyiringabo@gmail.com', 'Capital Bridge Finance', '250788301015', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali/Gasabo/Jabana/Ngiryi/Nyakirehe', 'Business loan', 'Gasabo', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:30:36', '2026-02-05 04:30:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(85, 'C023', 'HABINEZA Moses', 'N/A', '1198680005520117', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'mosehab@gmail.com', 'Capital Bridge Finance', '250788504992', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali/Kicukiro/Kanombe/Busanza/Amahoro', 'Business Loan', 'N/A', 'N/A', NULL, 'Medium', 2835000.00, 2835000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:32:27', '2026-02-10 05:13:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(86, 'C024', 'MURENZI EMMANUEL', 'n/a', '1197980013816390', 'n/a', 'entrepreneur', 'Male', NULL, NULL, 'emurenzi@gmail.com', 'Capital Bridge Finance', '250788309946', 'n/a', 'n/a', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', 'n/a', 'kigali/gasao/bumbogo/kinyaga/rubungo', 'General supply', 'n/a', 'n/a', NULL, 'Medium', 14442749.68, 14442749.68, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:34:07', '2026-02-10 19:59:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(87, 'C025', 'MUGERWA George', 'N/A', '1198880172086085', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'mugerwageorge0@gmail.com', 'Capital Bridge Finance', '0785102400', 'N/A', 'N/A', 'Annet KAGOYIRE', 'Finance', '0786539249', 'Ivanga mutungo', 'n/a', 'kigali/kicukiro/gahangakaremburekarembure', 'business loan', 'kicukiro', 'n/a', NULL, 'Medium', 19939500.00, 19939500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:36:37', '2026-02-10 21:01:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(88, 'C028', 'Imanishimwe Joshua', 'N/A', '1200080190519160', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'imanishimwe@gmail.com', 'Capital Bridge Finance', '250785757825', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali/Gasabo/Gisozi/Munezero/Amarembo', 'Business Loan', 'Gasabo', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:38:50', '2026-02-05 04:38:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(89, 'C029', 'HITIMANA Pierre', 'n/a', '1198780068612103', 'n/a', 'entrepreneur', 'Male', NULL, NULL, 'hitimanapeter7@gmail.com', 'Capital Bridge Finance', '250785325729', 'n/a', 'n/a', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', 'n/a', 'kamonyi;/rugarika/kigese/rugarama', 'business loan', 'kamonyi', 'n/a', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:40:42', '2026-02-10 20:24:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(90, 'C030', 'Mihigo Kayitera Jerome', 'N/A', '1196080068831299', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'mihigokayitera@gmail.com', 'Capital Bridge Finance', '250788307717', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', '', 'Kigali/Gasabo/Kimironko/Kibagabaga/Nyirabwana', 'Business Loan', 'Gasabo', 'N/A', NULL, 'Medium', 6615000.00, 6615000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:42:39', '2026-02-10 06:54:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(91, 'C031', 'Musabende  Marrie Jeanne', 'NYANZA/RWANDA', '1198370018850044', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'musabende@gmail.com', 'Capital Bridge Finance', '250788886015', 'NYAKABAKA', 'MUKAMUSONERA', 'NSHIZIRUNGU VINCENT', 'BUSINESS', '0788490250', 'Ivanga mutungo', '', 'kigali/kicukiro/gatenga/nyarurama/bisambu', 'business loan', 'kicukiro', 'KICUKIRO,GATENGA,NYARURAMA,BISAMBU', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:45:29', '2026-02-05 04:45:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(92, 'C032', 'NIYONGABO Damien', 'N/A', '1198080095509173', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'niyongabodamien@gmail.com', 'Capital Bridge Finance', '0788317541', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali/Kicukiro/kanombe/Rubirizi/Beninka', 'Business Loan', 'Kicukiro', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:47:53', '2026-02-05 04:47:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(93, 'C033', 'GASORE Christian', 'KICUKIRO/RWANDA', '1199880103623165', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'gasorechristian@gmail.com', 'Capital Bridge Finance', '250788595731', 'MUHAWENIMANA MATHIAS', 'MUHAWENIMANA MATHIAS', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali Kicukiro/Gikondo/Kanserege/Kanserege I', 'business loan', 'Kicukiro', 'N/A', NULL, 'Medium', 12001500.00, 12001500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:50:17', '2026-02-10 20:40:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(94, 'C034', 'NIYOMUGABO Innocent', 'N/A', '1199280123237243', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'niyomugaboinnoc@gamil.com', 'Capital Bridge Finance', '250788442825', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali/Gasabo/Kimihurura/Kamukina/izuba', 'Business Loan', 'Gasabo', 'N/A', NULL, 'Medium', 661500.00, 661500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:52:15', '2026-02-10 20:33:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(95, 'C035', 'Niyonkuru  Aime', 'N/A', '1199970185339031', 'N/A', 'Business', 'Male', NULL, NULL, 'niyonkuru@gmail.com', 'Capital Bridge Finance', '250788657308', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', '', 'kicukiro/kigarama/kigarama/kabeza', 'N/A', 'N/A', 'N/A', NULL, 'Medium', 4725000.00, 4725000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:54:02', '2026-02-10 20:35:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(96, 'C036', 'SHEMA Josbert', 'N/A', '1199680147706087', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'jshema@gmail.com', 'Capital Bridge Finance', '0788205849', 'N/A', 'N/A', 'Shema Eric', '', '0788376010', 'Ivanga mutungo', '', 'Kigali/Nyarugenge/Kigali/Ruriba/Misibya', 'Business Loan', 'Nyarugenge', 'N/A', NULL, 'Medium', 756000.00, 756000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:56:28', '2026-02-10 20:49:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(97, 'C037', 'Fabrice  MUKWIYE', 'GITEGA/NYARUGENGE', '1199780110955010', 'n/a', 'entrepreneur', 'Male', NULL, NULL, 'fabrice@gmail.com', 'Capital Bridge Finance', '250782246264', 'NYABYENDA VINCENT', 'MUKAMANA LEONILLA', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', 'n/a', 'kigali/nyarugenge/gitega/kora/kanunga', 'business loan', 'nyarugenge', 'n/a', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 04:58:37', '2026-02-10 20:43:19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(98, 'C038', 'Bayera  Ruth', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'bayeraruth22@gmail.com', 'Capital Bridge Finance', '250783564098', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali/Gasabo/Kinyinya/Kagugu/Nyakabungo', 'Business Loan', 'Gasabo', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:00:24', '2026-02-05 05:00:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(99, 'C039', 'JACQUELINE TESIRE', 'N/A', '1198170010801253', 'N/A', 'salary annual', 'Male', NULL, NULL, 'jackelinetesire@gmail.com', 'Capital Bridge Finance', '250788445317', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'kigali/kicukiro/nyarugumga/kamashashi/nyabagendwa', 'salary loan', 'kicukiro', 'N/A', NULL, 'Medium', 661500.00, 661500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:02:30', '2026-02-10 07:08:59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(100, 'C040', 'Vincent GAKWANDI', 'Gatenga-Kicukiro', '1197180005975177', 'N/A', 'Business owner', 'Male', NULL, NULL, 'vincent@gmail.com', 'Capital Bridge Finance', '0788307252', 'BYARUGABA NAZARI', 'MUKANDUTIYE Speciose', 'UWAMWEZI Joy', '', '0788777721', 'Ivanga mutungo', 'N/A', 'Kigali/Kicukiro/Kanombe/Busanza/Rukore', 'Business Loan', 'Kicukiro', 'N/A', NULL, 'Medium', 2835000.00, 2835000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:05:02', '2026-02-10 20:16:28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(101, 'C041', 'Mutangana Prosper', 'Mukarange-Rwanda', '1200170031046171', 'N/A', 'salary annual', 'Male', NULL, NULL, 'mutangana@gmail.com', 'Capital Bridge Finance', '0724147584', 'n/a', 'n/a', 'MATSIKO Manasseh', 'n/a', 'n/a', 'Ivanga mutungo', '', 'kayonza/murundi/buhabwa/miyaga', 'salary loan', 'kayonza', 'n/a', NULL, 'Medium', 2835000.00, 2835000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:07:10', '2026-02-11 14:25:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(102, 'C042', 'Muhawenimana  Rosine', 'N/A', '118970014015111', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'muhawenimana@gmail.com', 'Capital Bridge Finance', '0788266355', 'N/A', 'N/A', 'NSHIMIYIMANA Alexis', 'Soldier', '', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Niboye/Niboye/Mwijuto', 'Business Loan', 'kicukiro', 'N/A', NULL, 'Medium', 3844273.23, 3844273.23, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:09:48', '2026-02-10 20:26:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(103, 'C043', 'Byukusenge Theophile', 'n/a', '1198780157110281', 'n/a', 'salary annual', 'Male', NULL, NULL, 'byuthe@gmail.com', 'Capital Bridge Finance', '250788440577', 'n/a', 'n/a', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', 'n/a', 'kigali/kicukiro/niboye/niboye/kinunga', 'salary loan', 'kicukiro', 'n/a', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:12:52', '2026-02-10 05:12:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(104, 'C044', 'Editha Elias  Lugaju', 'N/A', 'N/A', 'N/A', 'Salary Earner', 'Male', NULL, NULL, 'edithaelias12@gmail.com', 'Capital Bridge Finance', '250782367365', 'N/A', 'N/A', '', '', '', 'Single', '', 'Southern/Kamonyi/Runda/Kagina/Kagina', 'Salary Loan', 'kamonyi', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:15:21', '2026-02-05 05:15:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(105, 'C045', 'Rukundo  Jean', 'n/a', '11980012141044', 'n/a', 'salary annual', 'Male', NULL, NULL, 'rukundoj@gmail.com', 'Capital Bridge Finance', '250788689611', 'n/a', 'n/a', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', '', 'kigali/kicukiro/kicukiro/kagina/iriba', 'salary loan', 'kicukiro', 'n/a', NULL, 'Medium', 567000.00, 567000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:17:20', '2026-02-13 11:52:28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(106, 'C046', 'GEOFFREY KAYIGAMBA', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'geoffrey@gmail.com', 'Capital Bridge Finance', '250783716095', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali/Gasabo/Remera/Rukiri II/Ruturusu I', 'Business loan', 'Gasabo', 'N/A', NULL, 'Medium', 2220750.00, 2220750.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:19:28', '2026-02-13 12:00:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(107, 'C047', 'Ndungutse Gilbert', 'n/a', 'n/a', 'n/a', 'entrepreneur', 'Male', NULL, NULL, 'Ndungutse@gmail.com', 'Capital Bridge Finance', '250788479680', 'n/a', 'n/a', '', '', '', 'Single', '', 'huye/kinazi/gitovu/nyarusange', 'business loan', 'huye', 'n/a', NULL, 'Medium', 472500.00, 472500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:20:54', '2026-02-10 19:58:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(108, 'C048', 'JEAN PAUL KABALISA', 'N/A', '1198480048530149', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'Kjeanpaul@gmail.com', 'Capital Bridge Finance', '0788520201', 'N/A', 'N/A', 'GAKUMBA RUGWIRO MARIE BONNE', 'Civil Servant', '', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Niboye/Niboye/Buhoro', 'Business Loan', 'Kicukiro', 'N/A', NULL, 'Medium', 1417500.00, 1417500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:22:51', '2026-02-10 20:10:46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(109, 'C049', 'NIYONGABO Damien', 'n/a', '1198080095509173', 'n/a', 'entrepreneur', 'Male', NULL, NULL, 'niyongabodamien@gmail.com', 'Capital Bridge Finance', '2500788317541', 'n/a', 'n/a', '', '', '', 'Single', '', 'kigali/kicukiro/kanombe/rubirizi/beninka/', 'business loan', 'kicukiro', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:26:10', '2026-02-05 05:26:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(110, 'C050', 'EMMANUEL MURENZI', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'Emurenzi@gmail.com', 'Capital Bridge Finance', '250788309946', 'N/A', 'N/A', '', '', '', 'Single', '', 'Kigali/Gasabo/Bumbogo/Kinyaga/Rubungo', 'Business Loan', 'Gasabo', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 05:28:43', '2026-02-05 05:28:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(112, 'C051', 'KABATESI  Sarah', 'N/A', '1198970172213173', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'kabatesisara@gimal.com', 'Capital Bridge Finance', '250788980540', 'n/a', 'n/a', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', '', 'kigali/kicukiro/nyarugunga/kamashashi/kibaya', 'business loan', 'kicukiro', 'n/a', NULL, 'Medium', 661500.00, 661500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 07:45:45', '2026-02-10 06:57:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(113, 'C052', 'ERIC NSHIMIYIMANA', 'N/A', '1198380020304072', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'ericn@gmail.com', 'Capital Bridge Finance', '250788318707', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Niboye/Niboye/Gaseke', 'Business Loan', 'Kicukiro/Niboye/Niboye/Gaseke', 'N/A', NULL, 'Medium', 2995650.00, 2995650.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 08:02:32', '2026-02-10 19:52:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(114, 'C053', 'Aimable Malaala', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'malaala@gmail.com', 'Capital Bridge Finance', '250788319121', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', 'N/A', 'Kigali/Kicukiro/Kagarama/Muyange/Muyange', 'Business Loan', 'Kicukiro', 'N/A', NULL, 'Medium', 1890000.00, 1890000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 08:06:29', '2026-02-10 20:55:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(115, 'C054', 'Munyazikwiye Narcisse', 'NYARUGURU/RWANDA', '1197380009347017', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'munyanarci@gmail.com', 'Capital Bridge Finance', '250788299424', 'KANANI PAUL', 'NYIRABITITAWEHO XAVERIE', 'n/a', 'n/a', '0783437342', 'Ivanga mutungo', '', 'KICUKIRO,NYARUGUNGA,KAMASHASHI,UMUCYO', 'business loan', 'kigali', 'N/A', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 08:09:08', '2026-02-10 20:40:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(116, 'C055', 'Tuyizere Maxime', 'N/A', '1198980196560069', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'tuyizeremaxine@gmail.com', 'Capital Bridge Finance', '0784816275', 'n/a', 'n/a', 'UMURUNGI Doreen', 'n/a', '0786353788', 'Ivanga mutungo', 'n/a', 'kigali/gasabo/bumbogo/kinyaga/ryakigogo', 'business loan', 'n/a', 'N/A', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 08:12:55', '2026-02-10 20:11:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(117, 'C056', 'Charles  NDAHIMANA', 'N/A', 'N/A', 'N/A', 'Salary Earner', 'Male', NULL, NULL, 'Ndahimana@gmal.com', 'Capital Bridge Finance', '250788309216', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Nyarugunga/Kamashashi/Kibaya', 'Salary Loan', 'Kicukiro', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 08:15:50', '2026-02-05 08:15:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(118, '057', 'JEAN NDAGIJIMANA', 'NYAMASHEKE/RWANDA', '1197480000958011', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'jeanndag@gmail.com', 'Capital Bridge Finance', '250788833776', 'HANYURWIMFURA', 'NISHYIREMBERE', 'UZAMUSHAKA MARGUERITE', 'AGRICULTURE', '782220933', 'Ivanga mutungo', '', 'kigali/kicukiro/masaka/mbabe/kabeza', 'business loan', 'kicukiro', 'KICUKIRO,MASAKA,MBARE,KABEZA', NULL, 'Medium', 9450000.00, 9450000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 08:48:34', '2026-02-10 04:45:07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(120, 'C058', 'Uwase Shyaka Tatiana', 'N/A', 'N/A', 'N/A', 'Salary Earner', 'Female', NULL, NULL, 'tatianashyaka@gmail.com', 'Capital Bridge Finance', '250787613102', 'N/A', 'N/A', 'N/A', 'N/A', '0', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Kagarama/Muyange/Kamuna', 'Salary Loan', 'kicukiro', 'N/A', NULL, 'Medium', 1701000.00, 1701000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 09:03:11', '2026-02-10 06:58:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(121, 'C59', 'Elvis Blaise NKUNDANYIRAZO', 'N/A', '1198180003828039', 'N/A', 'Salary earner', 'Male', NULL, NULL, 'nkundanyirazo@gmail.com', 'Capital Bridge Finance', '0788473873', 'N/A', 'N/A', 'CYUZUZO Diane', 'N/A', '0', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Kagarama/Muyange/Muyange', 'business loan', 'Kicukiro', 'N/A', NULL, 'Medium', 2835000.00, 2835000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 09:10:21', '2026-02-10 20:47:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(122, 'C60', 'NTUKANYAGWE Rugwiza Emile', 'nairobi', '1199080023976077', 'N/A', 'Salary Earner', 'Male', NULL, NULL, 'emilerugwizasub@gmai.com', 'Capital Bridge Finance', '250781463394', 'n/a', 'n/a', 'UWERA Nathalie', 'N/A', '786230225', 'Ivanga mutungo', '', 'bugesera/ntarama/cyugaro/kingabo', 'salary loan', 'bugesera', 'n/a', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 09:37:11', '2026-02-10 05:11:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(123, 'C61', 'NTIRUSHWA Alexis', 'N/A', '119980084151080', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'ntirushwa@gmail.com', 'Capital Bridge Finance', '250788222099', 'n/a', 'n/a', 'Uwera Jssica', 'n/a', '0', 'Ivanga mutungo', '', 'kigali/gasabo/kimironko/kibagabaga/rindiro', 'Personal loan', 'gasabo', 'n/a', NULL, 'Medium', 4725000.00, 4725000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 10:03:50', '2026-02-10 20:23:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(124, 'C62', 'KAZUNGU Patrick', 'N/A', '&#39;1198180042963377', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'kazungu@gmail.com', 'Capital Bridge Finance', '&#39;250788387868', 'N/A', 'N/A', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Masaka/cyimo/Cyimo', 'Business Loan', 'KICUKIRO', 'n/a', NULL, 'Medium', 1890000.00, 1890000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 10:17:24', '2026-02-10 07:11:49', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(125, 'C63', 'Fabrice  MUKWIYE', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'fabrice@gmail.com', 'Capital Bridge Finance', '&#39;250782246264', 'N/A', 'N/A', 'N/A', 'N/A', '0', 'Ivanga mutungo', '', '&#39;250782246264', 'Business loan', 'NYARUGENGE', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 10:24:35', '2026-02-05 10:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(126, 'C64', 'MARIE CLAUDE UMWALI', 'N/A', '1198770009625288', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'umariec@gmail.com', 'Capital Bridge Finance', '250785150214', 'n/a', 'n/a', 'n/a', 'n/a', '0', 'Ivanga mutungo', '', 'kigali/nyarugenge/kigali/nyabugogo/gakoni', 'business loan', 'nyarugenge', 'n/a', NULL, 'Medium', 170100.00, 170100.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 10:32:08', '2026-02-10 19:57:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(127, 'C68', 'NDUNGUTSE Gilbert', 'N/A', 'N/A', 'N/A', 'Salary Earner', 'Male', NULL, NULL, 'Ndungutse@gmail.com', 'Capital Bridge Finance', '0788479680', 'N/A', 'N/A', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'Southern/Huye/Kinazi/Gitovu/Nyarusange', 'Salary Loan', 'Huye', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 10:41:19', '2026-02-05 10:41:19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(128, 'C65', 'tmoise@gmail.com', 'N/A', '1199480200723010', '1199480200723010', 'salary annual', 'Male', NULL, NULL, 'tmoise@gmail.com', 'Capital Bridge Finance', '250781523904', 'n/a', 'n/a', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'kigali/kicukiro/gatenga/nyanza/marembo', 'salary loan', 'kicukiro', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 10:51:25', '2026-02-05 10:51:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(129, 'C66', 'Joyeuse  Uwatuje', 'N/A', '11988700145198', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'uwatuje@gmail.com', 'Capital Bridge Finance', '0783254629', 'N/A', 'N/A', 'NIYOMUGABO Nathan', 'N/A', '789227969', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Gatenga/Gatenga/Ihuriro', 'business loan', 'kicukiro', 'N/A', NULL, 'Medium', 4252500.00, 4252500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 11:02:13', '2026-02-10 19:55:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(130, 'C67', 'NELSON NSENGA', 'N/A', '1198680200290014', 'N/A', 'salary annual', 'Male', NULL, NULL, 'nnelson@gmail.com', 'Capital Bridge Finance', '250250781523852', 'n/a', 'n/a', 'n/a', 'n/a', '0', 'Ivanga mutungo', '', 'kigali/kicukiro/nyarugunga/nonko/gasaraba', 'salary loan', 'kicukiro', 'n/a', NULL, 'Medium', 472500.00, 472500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 11:06:07', '2026-02-13 15:57:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(131, 'C69', 'NAAMANYA Benon', 'N/A', '1199380184126210', 'N/A', 'salary annual', 'Male', NULL, NULL, 'bennie20nda@gimal.com', 'Capital Bridge Finance', '250788356398', 'n/a', 'n/a', 'n/a', 'n/a', '0', 'Ivanga mutungo', '', 'bugesera/ntarama/cyugaro/kayenzi', 'salary loan', 'bugesera', 'n/a', NULL, 'Medium', 1134000.00, 1134000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 11:17:44', '2026-02-10 20:46:20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(132, 'C70', 'NTIRUSHWA Alexis', 'N/A', '1199280084151166', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'ntirushwa@gmail.com', 'Capital Bridge Finance', '250788222099', 'N/A', 'N/A', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'Kigali/Gasabo/Kimironko/Kibagabaga/Rindiro', 'Business Loan', 'Gasabo', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 11:25:56', '2026-02-05 11:25:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(133, 'C71', 'Innocent Tumusifu', 'MASISI/DRC', '1198380016348107', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'tumusifuinn@gmail.com', 'Capital Bridge Finance', '250788309538', 'KAYIBANDA ANTOINE', 'KANTARAMA AURELIE', 'MUKANKUBITO JULIENNE', 'n/a', '788403441', 'Ivanga mutungo', '', 'kigali/gasabo/ndera/kibenga/rugazi', 'business loan', 'gasabo', 'kigali/gasabo/ndera/kibenga/rugazi', NULL, 'Medium', 7560000.00, 7560000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 11:32:37', '2026-02-10 20:42:07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(134, 'C72', 'Silas MBONYIMANA', 'N/A', '1199180012953054', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'silasmbonyimana@gmail.com', 'Capital Bridge Finance', '250783265401', 'Nteziryayo Laurent', 'Mukayigire odette', 'TUYISENGE GRACE', 'BUSINESS WOMAN', '789407311', 'Ivanga mutungo', '', 'Southern/Kamonyi/Runda/Ruyenzi/Nyagacaca', 'Business Loan', 'Kamonyi', 'N/A', NULL, 'Medium', 4725000.00, 4725000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 12:03:58', '2026-02-10 20:15:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(135, 'C073', 'Asimwe Josephine', 'N/A', 'N/A', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'asimwe@gmal.com', 'Capital Bridge Finance', '250788273071', 'n/a', 'n/a', 'n/a', 'n/a', '0', 'Ivanga mutungo', '', 'kigali/kicukiro/kicukiro/ubumwe', 'business loan', 'kicukiro', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 12:08:32', '2026-02-05 12:08:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(136, 'C074', 'THOMAS  NKOTANYI', 'N/A', '1196478000539618', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'TNKOTANYI@gmail.com', 'Capital Bridge Finance', '250788305737', 'N/A', 'N/A', 'Mutoni Francoise', 'N/A', '788643855', 'Ivanga mutungo', '', 'Kigali/Gasabo/Kinyinya/Kagugu/Giheka', 'Working capital', 'Gasabo', 'N/A', NULL, 'Medium', 5197500.00, 5197500.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 12:34:57', '2026-02-10 07:10:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(137, 'C075', 'Diana Nancy KAREBA', 'Uganda', '1198770199328021', 'N/A', 'Salary Earner', 'Male', NULL, NULL, 'diana@gmail.com', 'Capital Bridge Finance', '250786159443', 'N/A', 'N/A', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'Kigali/Gasabo/Kimironko/Bibare/Ingenzi', 'Salary Loan', 'Gasabo', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 12:44:57', '2026-02-05 12:44:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(138, 'C76', 'Eric  UWITONZE', 'Nduba-Rwanda', '1198580014734051', 'N/A', 'salary annual', 'Male', NULL, NULL, 'uwitonzeric@gmail.com', 'Capital Bridge Finance', '0788622049', 'KANYAMITARI Augustin', 'MUKAGAKWISI Laurence', 'NYIRANSABIMANA Vestine', 'n/a', '0', 'Ivanga mutungo', '', 'kicukiro/kigarama/kigarama/indatwa', 'salary loan', 'kicukiro', 'n/a', NULL, 'Medium', 1890000.00, 1890000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 12:59:12', '2026-02-10 07:00:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(139, 'C77', 'Annet MUKAMISHA', 'N/A', '1199070151831092', 'N/A', 'Entrepreneur', 'Female', NULL, NULL, 'mukamishaa6@gmail.com', 'Capital Bridge Finance', '250788259782', 'N/A', 'N/A', 'Fabrice Mwerekande', 'Business partner', '783850293', 'Ivanga mutungo', '', 'Eastern/Bugesera/Nyamata/Nyamata y Umujyi/Rwakibirizi I', 'Business Loan', 'Bugesera', 'N/A', NULL, 'Medium', 4725000.00, 4725000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:05:21', '2026-02-10 20:31:59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(140, 'C078', 'KAZUNGU Patrick', 'N/A', '1198180042963377', 'N/A', 'RALGA', 'Male', NULL, NULL, 'kazungu@gmail.com', 'Capital Bridge Finance', '0788387868', 'N/A', 'N/A', 'NAMAHORO Yvonne', 'n/a', '782412084', 'Ivanga mutungo', '', 'kicukiro/masaka/cyimo/cyimo', 'business loan', 'kicukiro', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:15:31', '2026-02-05 13:15:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(141, 'C079', 'NDAMIRA Jean Bosco', 'N/A', '1198970183302168', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'ndajambo@gmail.com', 'Capital Bridge Finance', '0788308009', 'Tungambure Augustin', 'Ugiriwabo Beath', 'Ugirase claudine', 'saloon owner', '781419679', 'Ivanga mutungo', '', 'kigali/kicukiro/kanombe/rubirizi/beninka/', 'bussiness loan', 'kicukiro', 'n/a', NULL, 'Medium', 3780000.00, 3780000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:20:28', '2026-02-10 20:53:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(142, 'C080', 'JEAN PAUL KABALISA', 'N/A', 'N/A', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'Kjeanpaul@gmail.com', 'Capital Bridge Finance', '250788520201', 'n/a', 'n/a', 'n/a', 'n/a', '0', 'Ivanga mutungo', '', 'kigali/kicukiro/niboye/niboye', 'bussiness loan', 'kicukiro', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:24:16', '2026-02-05 13:24:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(143, 'C081', 'SERGE  ZIMULINDA', 'N/A', '1199080011548060', 'N/A', 'salary annual', 'Male', NULL, NULL, 'zserge@gmail.com', 'Capital Bridge Finance', '0788740179', 'n/a', 'n/a', 'BIZUMUREMYI Joselyne', 'n/a', '788310603', 'Ivanga mutungo', '', 'bugesera/ntarama/kanzenze/cyeru', 'salary loan', 'bugesera', 'n/a', NULL, 'Medium', 567000.00, 567000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:29:23', '2026-02-10 19:54:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(144, 'C082', 'KAYIREBWA Claire', 'N/A', '1197770009951003', 'N/A', 'entrepreneur', 'Male', NULL, NULL, 'kayirebwa@gmail.com', 'Capital Bridge Finance', '250781918993', 'N/A', 'N/A', 'n/a', 'N/A', 'N/A', 'Ivanga mutungo', '', 'bugesera/ nyamata/ nyamata y&#39;umujyi/nyamata li', 'business loan', 'bugesera', 'n/a', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:36:43', '2026-02-10 07:07:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(145, 'C083', 'Emmelyne MWIZERWA', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'EMwizerwa@gmail.com', 'Capital Bridge Finance', '250788734480', 'N/A', 'N/A', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'Eastern/Bugesera/Ntarama/Cyugaro/Rugarama', 'Business Loan', 'Bugesera', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:43:09', '2026-02-05 13:43:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(146, 'C084', 'vedastine  izere mugeni', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'izereveda@gmail.com', 'Capital Bridge Finance', '250788304639', 'N/A', 'N/A', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Kanombe/Karama/Gikundiro', 'Business Loan', 'Kicikiro', 'N/A', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:52:30', '2026-02-05 13:52:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(147, 'C085', 'fred KAMUSHANA', 'N/A', '1198380018865061', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'kamushana@gmail.com', 'Capital Bridge Finance', '250785033021', 'N/A', 'N/A', 'n/a', 'n/a', '0', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Kanombe/Kabeza/Mulindi', 'Business Loan', 'Kicukiro', 'n/a', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 13:59:26', '2026-02-10 20:14:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(148, 'C086', 'Rwagatare Ruhiryi Kamali', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Male', NULL, NULL, 'rwagatare@gmil.com', 'Capital Bridge Finance', '250785248106', 'N/A', 'N/A', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Niboye/Niboye/kigabiro', 'Business Loan', 'Kicukiro', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 14:04:30', '2026-02-05 14:04:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(149, 'C087', 'Mukakamanzi Alphonsine', 'N/A', 'N/A', 'N/A', 'Entrepreneur', 'Female', NULL, NULL, 'mukakamanzi@gmail.com', 'Capital Bridge Finance', '250788547904', 'N/A', 'n/a', 'n/a', 'N/A', '0', 'Ivanga mutungo', '', 'Kigali/Kicukiro/Nyarugunga/Rwimbogo/Nyandungu', 'Business Loan', 'kicukiro', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 14:08:18', '2026-02-05 14:08:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(150, 'C088', 'Iradukunda Sabbato', 'N/A', 'N/A', 'N/A', 'Salary Earner', 'Male', NULL, NULL, 'iradukunda@gmail.com', 'Capital Bridge Finance', '250788433158', 'N/A', 'N/A', 'N/A', 'n/a', '0', 'Ivanga mutungo', '', 'Kigali/Gasabo/Gisozi/Munezero/NA', 'Personal Loan', 'Gasabo', 'n/a', NULL, 'Medium', 0.00, 0.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-05 14:12:53', '2026-02-05 14:12:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(151, 'cbf100', 'cbf test', 'kigali', 'n/a', 'n/a', 'teacher', 'Male', '2026-02-06', NULL, 'example@gmail.com', 'Capital Bridge Finance', 'n/a', 'n/a', 'n/a', '', '', '', 'Single', 'n.a', 'ma', 'ma', 'ma', 'ma', NULL, 'Medium', 57111075.00, 57111075.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-06 11:42:38', '2026-02-18 14:53:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(152, 'C090', 'Patrick Gakuba', 'n/a', '119808001043049', 'n/a', 'Entrepreneur', 'Male', NULL, NULL, 'example@gmail.com', 'Capital Bridge Finance', '+25078706940', 'n/a', 'n/a', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', 'n/a', 'Kigali/Nyarugenge/Kiyovu/Ingenzi', 'Salary loan', 'Nyarugenge', 'n/a', NULL, 'Medium', 2295405.00, 2295405.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-10 05:04:59', '2026-02-10 05:08:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(153, 'C091', 'Rutayisire Peter', 'kicukiro kigali', '119838001885907', 'n/a', 'salary earner', 'Male', NULL, NULL, 'example@gmail.co', 'Capital Bridge Finance', '0788598061', 'n/a', 'n/a', 'n/a', 'n/a', 'n/a', 'Ivanga mutungo', 'n/a', 'Kigali/Kicukiro/Gahanga/Kagasa/Nyakuguman/a', 'Personal Loan', 'kicukiro', 'n/a', NULL, 'Medium', 1701000.00, 1701000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-10 06:47:53', '2026-02-10 06:53:07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(154, 'CBF', 'UMUTONI Farida', 'N/A', '1199670056518086', 'NA', 'ENTREPRENEUR', 'Female', '1996-06-03', NULL, 'umutonifalida23@gmail.com', 'Capital Bridge Finance', '0788239356', 'KATARENGUKA IDRISSA', 'NIWEMUGENI HASSINA', '', '', '', 'Single', 'GASABO-KIMIRONKO-NYAGATOVU-ISANGANO', 'GASABO-KIMIRONKO-NYAGATOVU-ISANGANO', 'kwagura ubucuruzi', 'DOWN TOWN', 'BUGESERA-NTARAMA-CYUGARO-KINGABO', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-11 15:27:13', '2026-02-13 07:51:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(155, 'CBF2', 'NAHIMANA SAIDI', 'N/A', '1198980195325084', '4002100565047', 'EQUITY BANKER', 'Male', '1989-11-11', NULL, 'nahimana@equitybank.co.rw', 'Capital Bridge Finance', '0788621462', 'N/A', 'N/A', '', '', '', 'Single', '', 'NYARUGENGE-RWEZAMENYO-RWEZAMENYO1-ABATARUSHWA', 'PERSONAL', 'GASABO', 'GASABO', NULL, 'Medium', 945000.00, 945000.00, 0.00, 1, 'Approved', 'Salary', NULL, '2026-02-13 08:39:20', '2026-02-13 08:41:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(157, 'PEND-506174', 'ISHIMWE GHISLAIN', 'i dont know', '111', '111', '11', 'Female', '2026-02-02', NULL, 'ishimweghislain82@gmail.com', 'Capital Bridge Finance', '+250781262526', 'd', 'd', '', '', '', 'Single', 'ruyenzi', 'KN6GH', 'kk', 'iij', 'kkkkl', NULL, 'Medium', 0.00, 0.00, 0.00, 0, '', 'Salary', NULL, '2026-02-18 11:34:13', '2026-02-18 13:54:30', 'uploads/documents/doc_id_1771414453_888.jpeg', 'uploads/documents/doc_contract_1771414453_815.jpg', 'uploads/documents/doc_statement_1771414453_863.jpeg', 'uploads/documents/doc_payslip_1771414453_321.jpeg', '', '', 'kilo', 'customer_name,id_number', '', 0, NULL),
(164, 'PEND-769688', 'Ineza Linda', 'i dont know', '111', '111', '11', 'Male', '2026-02-10', NULL, 'linda@gmail.com', 'Capital Bridge Finance', '+250781262526', 'd', 'd', '', '', '', 'Single', 'ruyenzi', 'Kiyovu, Kigali', 'kk', 'iij', 'kkkkl', NULL, 'Medium', 0.00, 0.00, 0.00, 0, '', 'Salary', NULL, '2026-02-18 14:11:08', '2026-02-18 14:13:52', 'uploads/documents/doc_id_1771423868_8483.jpg', 'uploads/documents/doc_contract_1771423868_7186.png', 'uploads/documents/doc_statement_1771423868_8210.jpg', 'uploads/documents/doc_payslip_1771423868_4016.jpg', 'uploads/documents/doc_marital_1771423868_7908.jpg', '', 'she is bad', 'customer_name,id_number,doc_rdb', 'frogery', 0, NULL),
(165, 'PEND-438943', 'ISHIMWE GHISLAIN', 'i dont know', '111', '111', 'G-TECH-C-LTD', 'Male', '2026-02-19', NULL, 'ishimweghislain8299@gmail.com', 'Capital Bridge Finance', '0781262526', 'd', 'd', '', '', '', 'Single', 'ruyenzi', 'kigali', 'kk', 'iij', 'kkkkl', NULL, 'Medium', 0.00, 0.00, 0.00, 0, '', 'Salary', NULL, '2026-02-18 15:00:59', '2026-02-18 15:01:40', 'uploads/documents/doc_id_1771426859_379.jpg', 'uploads/documents/doc_contract_1771426859_936.jpg', 'uploads/documents/doc_statement_1771426859_397.jpg', 'uploads/documents/doc_payslip_1771426859_183.jpg', 'uploads/documents/doc_marital_1771426859_628.jpg', '', NULL, 'phone,email,doc_rdb', '', 0, NULL),
(166, 'PEND-909242', 'John doe', 'i dont know', '111', 'ss', '11', 'Male', '2026-02-19', NULL, 'ghislainishimwe22@gmail.com', 'Capital Bridge Finance', '0781262526', 'd', 'd', '', '', '', 'Single', 'ruyenzi', 'kigali', 'kk', 'iij', 'kkkkl', NULL, 'Medium', 0.00, 0.00, 0.00, 1, '', 'Salary', NULL, '2026-02-18 15:17:18', '2026-02-18 15:19:56', 'uploads/documents/doc_id_1771427838_67.jpg', 'uploads/documents/doc_contract_1771427838_21.jpg', 'uploads/documents/doc_statement_1771427838_31.jpg', 'uploads/documents/doc_payslip_1771427838_71.jpg', 'uploads/documents/doc_marital_1771427838_39.jpg', '', NULL, 'doc_marital', '', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `expense_reference` varchar(50) NOT NULL,
  `expense_date` date NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `expense_amount` decimal(15,2) NOT NULL,
  `payment_type` enum('cash','bank') DEFAULT 'bank',
  `description` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `expense_reference`, `expense_date`, `account_code`, `account_name`, `expense_amount`, `payment_type`, `description`, `created_by`, `created_at`) VALUES
(28, 'EXP-20260204-152322', '2026-02-04', '5101', 'Salaries & Wages', 300000.00, 'bank', '', 1, '2026-02-04 14:23:22');

-- --------------------------------------------------------

--
-- Table structure for table `ledger`
--

CREATE TABLE `ledger` (
  `ledger_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `class` varchar(50) NOT NULL,
  `account_code` varchar(10) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `particular` varchar(255) NOT NULL,
  `voucher_number` varchar(50) DEFAULT NULL,
  `narration` text,
  `beginning_balance` decimal(15,2) DEFAULT '0.00',
  `debit_amount` decimal(15,2) DEFAULT '0.00',
  `credit_amount` decimal(15,2) DEFAULT '0.00',
  `movement` decimal(15,2) DEFAULT '0.00',
  `ending_balance` decimal(15,2) DEFAULT '0.00',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sequence_number` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `ledger`
--

INSERT INTO `ledger` (`ledger_id`, `transaction_date`, `class`, `account_code`, `account_name`, `particular`, `voucher_number`, `narration`, `beginning_balance`, `debit_amount`, `credit_amount`, `movement`, `ending_balance`, `reference_type`, `reference_id`, `created_by`, `created_at`, `updated_at`, `sequence_number`) VALUES
(13453, '2026-02-05', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Deferred Disbursement VAT', 'C0060', 'Loan #LN-20260205-071053 to test', 0.00, 0.00, 18000.00, 18000.00, 18000.00, 'loan', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 4),
(13457, '2026-02-28', 'Revenue', '4101', 'Interest on Loans', 'Accrual - Interest (Instalment 1, Part 1)', 'C0060', 'Accruals for C0060', 0.00, 0.00, 84720.00, 84720.00, 84720.00, 'loan_accrual', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 4),
(13463, '2026-03-07', 'Revenue', '4101', 'Interest on Loans', 'Accrual - Interest (Instalment 1, Part 2)', 'C0060', 'Accruals for C0060', 84720.00, 0.00, 21180.00, 21180.00, 105900.00, 'loan_accrual', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 4),
(13467, '2026-02-28', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Fee Recognition - VAT (Instalment 1, Part 1)', 'C0060', 'Deferred fees recognition for C0060', 18000.00, 4800.00, 0.00, -4800.00, 13200.00, 'loan_fee_recognition', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 2),
(13468, '2026-02-28', 'Fee Income', '4201', 'Disbursement Fee Income', 'Fee Recognition - Disbursement Fees (Instalment 1, Part 1)', 'C0060', 'Deferred fees recognition for C0060', 0.00, 0.00, 26666.67, 26666.67, 26666.67, 'loan_fee_recognition', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 3),
(13471, '2026-03-07', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Fee Recognition - VAT (Instalment 1, Part 2)', 'C0060', 'Deferred fees recognition for C0060', 13200.00, 1200.00, 0.00, -1200.00, 12000.00, 'loan_fee_recognition', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 2),
(13472, '2026-03-07', 'Fee Income', '4201', 'Disbursement Fee Income', 'Fee Recognition - Disbursement Fees (Instalment 1, Part 2)', 'C0060', 'Deferred fees recognition for C0060', 26666.67, 0.00, 6666.67, 6666.67, 33333.34, 'loan_fee_recognition', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 3),
(13475, '2026-04-06', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Fee Recognition - Installment #2', 'C0060', 'Deferred fees recognition for C0060', 12000.00, 6000.00, 0.00, -6000.00, 6000.00, 'loan_fee_recognition', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 2),
(13476, '2026-04-06', 'Fee Income', '4201', 'Disbursement Fee Income', 'Fee Recognition - Installment #2', 'C0060', 'Deferred fees recognition for C0060', 33333.34, 0.00, 33333.33, 33333.33, 66666.67, 'loan_fee_recognition', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 3),
(13479, '2026-05-06', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Fee Recognition - Installment #3', 'C0060', 'Deferred fees recognition for C0060', 6000.00, 6000.00, 0.00, -6000.00, 0.00, 'loan_fee_recognition', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 2),
(13480, '2026-05-06', 'Fee Income', '4201', 'Disbursement Fee Income', 'Fee Recognition - Installment #3', 'C0060', 'Deferred fees recognition for C0060', 66666.67, 0.00, 33333.33, 33333.33, 100000.00, 'loan_fee_recognition', '316', 1, '2026-02-05 07:11:09', '2026-02-05 07:11:09', 3),
(13485, '2026-02-05', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Deferred Disbursement VAT', 'C100000', 'Loan #LN-20260205-085709 to ndiku', 0.00, 0.00, 13500.00, 13500.00, 13500.00, 'loan', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 4),
(13489, '2026-02-28', 'Revenue', '4101', 'Interest on Loans', 'Accrual - Interest (Instalment 1, Part 1)', 'C100000', 'Accruals for C100000', 0.00, 0.00, 63540.00, 63540.00, 63540.00, 'loan_accrual', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 4),
(13495, '2026-03-07', 'Revenue', '4101', 'Interest on Loans', 'Accrual - Interest (Instalment 1, Part 2)', 'C100000', 'Accruals for C100000', 63540.00, 0.00, 15885.00, 15885.00, 79425.00, 'loan_accrual', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 4),
(13499, '2026-02-28', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Fee Recognition - VAT (Instalment 1, Part 1)', 'C100000', 'Deferred fees recognition for C100000', 13500.00, 3600.00, 0.00, -3600.00, 9900.00, 'loan_fee_recognition', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 2),
(13500, '2026-02-28', 'Fee Income', '4201', 'Disbursement Fee Income', 'Fee Recognition - Disbursement Fees (Instalment 1, Part 1)', 'C100000', 'Deferred fees recognition for C100000', 0.00, 0.00, 20000.00, 20000.00, 20000.00, 'loan_fee_recognition', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 3),
(13503, '2026-03-07', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Fee Recognition - VAT (Instalment 1, Part 2)', 'C100000', 'Deferred fees recognition for C100000', 9900.00, 900.00, 0.00, -900.00, 9000.00, 'loan_fee_recognition', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 2),
(13504, '2026-03-07', 'Fee Income', '4201', 'Disbursement Fee Income', 'Fee Recognition - Disbursement Fees (Instalment 1, Part 2)', 'C100000', 'Deferred fees recognition for C100000', 20000.00, 0.00, 5000.00, 5000.00, 25000.00, 'loan_fee_recognition', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 3),
(13507, '2026-04-06', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Fee Recognition - Installment #2', 'C100000', 'Deferred fees recognition for C100000', 9000.00, 4500.00, 0.00, -4500.00, 4500.00, 'loan_fee_recognition', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 2),
(13508, '2026-04-06', 'Fee Income', '4201', 'Disbursement Fee Income', 'Fee Recognition - Installment #2', 'C100000', 'Deferred fees recognition for C100000', 25000.00, 0.00, 25000.00, 25000.00, 50000.00, 'loan_fee_recognition', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 3),
(13511, '2026-05-06', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Fee Recognition - Installment #3', 'C100000', 'Deferred fees recognition for C100000', 4500.00, 4500.00, 0.00, -4500.00, 0.00, 'loan_fee_recognition', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 2),
(13512, '2026-05-06', 'Fee Income', '4201', 'Disbursement Fee Income', 'Fee Recognition - Installment #3', 'C100000', 'Deferred fees recognition for C100000', 50000.00, 0.00, 25000.00, 25000.00, 75000.00, 'loan_fee_recognition', '317', 1, '2026-02-05 08:58:06', '2026-02-05 08:58:06', 3),
(13519, '2026-02-05', 'Revenue', '4205', 'Penalty Charges', 'First Payment Logic - Penalty Charges', 'C100000', 'Loan payment from ndiku - Instalment #1 - Loan #LN-20260205-085709 - Reference:  - First payment ever for loan', 0.00, 0.00, 7139.72, 7139.72, 7139.72, 'loan_payment', '1031', 1, '2026-02-05 09:11:39', '2026-02-05 09:11:39', 0),
(13520, '2026-02-05', 'Expense', '5403', 'Depreciation & Provisions Expense', 'Loan Loss Provision Expense', 'PROV-LN-20260205-085709-20260205', 'Loan Loss Provision - Loan #LN-20260205-085709 - 2 days overdue (Outstanding: $1,749,932.41, Collateral: $0.00, Provision: $5,833.11)', 0.00, 5833.11, 0.00, 5833.11, 5833.11, 'loan_portfolio', '317', NULL, '2026-02-05 09:30:51', '2026-02-05 09:30:51', 1),
(13527, '2026-02-28', 'Revenue', '4101', 'Interest on Loans', 'Accrual - Interest (Instalment 1, Part 1)', 'C100000', 'Accruals for C100000', 0.00, 0.00, 80000.00, 80000.00, 80000.00, 'loan_accrual', '318', 1, '2026-02-05 12:40:03', '2026-02-05 12:40:03', 3),
(13528, '2026-02-28', 'Fee Income', '4201', 'Management Fee Income', 'Accrual - Management Fees (Instalment 1, Part 1)', 'C100000', 'Accruals for C100000', 20000.00, 0.00, 32314.35, 32314.35, 52314.35, 'loan_accrual', '318', 1, '2026-02-05 12:40:03', '2026-02-05 12:40:03', 4),
(13531, '2026-03-07', 'Revenue', '4101', 'Interest on Loans', 'Accrual - Interest (Instalment 1, Part 2)', 'C100000', 'Accruals for C100000', 80000.00, 0.00, 20000.00, 20000.00, 100000.00, 'loan_accrual', '318', 1, '2026-02-05 12:40:03', '2026-02-05 12:40:03', 3),
(13532, '2026-03-07', 'Fee Income', '4201', 'Management Fee Income', 'Accrual - Management Fees (Instalment 1, Part 2)', 'C100000', 'Accruals for C100000', 25000.00, 0.00, 8078.59, 8078.59, 33078.59, 'loan_accrual', '318', 1, '2026-02-05 12:40:03', '2026-02-05 12:40:03', 4),
(13534, '2026-02-28', 'Fee Income', '4201', 'Management Fee Income', 'Fee Recognition - Management Fees (Instalment 1, Part 1)', 'C100000', 'Management fees recognition for C100000', 0.00, 0.00, 29333.33, 29333.33, 29333.33, 'loan_fee_recognition', '318', 1, '2026-02-05 12:40:03', '2026-02-05 12:40:03', 2),
(13536, '2026-03-07', 'Fee Income', '4201', 'Management Fee Income', 'Fee Recognition - Management Fees (Instalment 1, Part 2)', 'C100000', 'Management fees recognition for C100000', 29333.33, 0.00, 7333.33, 7333.33, 36666.66, 'loan_fee_recognition', '318', 1, '2026-02-05 12:40:03', '2026-02-05 12:40:03', 2),
(13538, '2026-04-06', 'Fee Income', '4201', 'Management Fee Income', 'Fee Recognition - Installment #2', 'C100000', 'Management fees recognition for C100000', 36666.66, 0.00, 36666.67, 36666.67, 73333.33, 'loan_fee_recognition', '318', 1, '2026-02-05 12:40:03', '2026-02-05 12:40:03', 2),
(13540, '2026-05-06', 'Fee Income', '4201', 'Management Fee Income', 'Fee Recognition - Installment #3', 'C100000', 'Management fees recognition for C100000', 73333.33, 0.00, 36666.67, 36666.67, 110000.00, 'loan_fee_recognition', '318', 1, '2026-02-05 12:40:03', '2026-02-05 12:40:03', 2),
(13546, '2026-02-28', 'Revenue', '4101', 'Interest on Loans', 'Accrual - Interest (Instalment 1, Part 1)', 'C100000', 'Accruals for C100000', 0.00, 0.00, 80000.00, 80000.00, 80000.00, 'loan_accrual', '319', 1, '2026-02-05 13:13:25', '2026-02-05 13:13:25', 3),
(13547, '2026-02-28', 'Fee Income', '4201', 'Management Fee Income', 'Accrual - Management Fees (Instalment 1, Part 1)', 'C100000', 'Accruals for C100000', 20000.00, 0.00, 32314.35, 32314.35, 52314.35, 'loan_accrual', '319', 1, '2026-02-05 13:13:25', '2026-02-05 13:13:25', 4),
(13550, '2026-03-07', 'Revenue', '4101', 'Interest on Loans', 'Accrual - Interest (Instalment 1, Part 2)', 'C100000', 'Accruals for C100000', 80000.00, 0.00, 20000.00, 20000.00, 100000.00, 'loan_accrual', '319', 1, '2026-02-05 13:13:25', '2026-02-05 13:13:25', 3),
(13551, '2026-03-07', 'Fee Income', '4201', 'Management Fee Income', 'Accrual - Management Fees (Instalment 1, Part 2)', 'C100000', 'Accruals for C100000', 25000.00, 0.00, 8078.59, 8078.59, 33078.59, 'loan_accrual', '319', 1, '2026-02-05 13:13:25', '2026-02-05 13:13:25', 4),
(13553, '2026-02-28', 'Fee Income', '4201', 'Management Fee Income', 'Fee Recognition - Management Fees (Instalment 1, Part 1)', 'C100000', 'Management fees recognition for C100000', 0.00, 0.00, 29333.33, 29333.33, 29333.33, 'loan_fee_recognition', '319', 1, '2026-02-05 13:13:26', '2026-02-05 13:13:26', 2),
(13555, '2026-03-07', 'Fee Income', '4201', 'Management Fee Income', 'Fee Recognition - Management Fees (Instalment 1, Part 2)', 'C100000', 'Management fees recognition for C100000', 29333.33, 0.00, 7333.33, 7333.33, 36666.66, 'loan_fee_recognition', '319', 1, '2026-02-05 13:13:26', '2026-02-05 13:13:26', 2),
(13557, '2026-04-06', 'Fee Income', '4201', 'Management Fee Income', 'Fee Recognition - Installment #2', 'C100000', 'Management fees recognition for C100000', 36666.66, 0.00, 36666.67, 36666.67, 73333.33, 'loan_fee_recognition', '319', 1, '2026-02-05 13:13:26', '2026-02-05 13:13:26', 2),
(13559, '2026-05-06', 'Fee Income', '4201', 'Management Fee Income', 'Fee Recognition - Installment #3', 'C100000', 'Management fees recognition for C100000', 73333.33, 0.00, 36666.67, 36666.67, 110000.00, 'loan_fee_recognition', '319', 1, '2026-02-05 13:13:26', '2026-02-05 13:13:26', 2),
(13642, '2026-02-09', 'Asset', '1201', 'Loans to Customers', 'Loan Disbursement', 'cbf100', 'Loan #LN-20260209-143850 to cbf test', 0.00, 1417500.00, 0.00, 1417500.00, 1417500.00, 'loan', '19', 1, '2026-02-09 14:41:14', '2026-02-09 14:41:14', 1),
(13643, '2026-02-09', 'Asset', '1102', 'Bank Account', 'Bank Transfer', 'cbf100', 'Loan #LN-20260209-143850 to cbf test', 0.00, 0.00, 1500000.00, -1500000.00, -1500000.00, 'loan', '19', 1, '2026-02-09 14:41:14', '2026-02-09 14:41:14', 2),
(13644, '2026-02-09', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan #LN-20260209-143850 to cbf test', 0.00, 0.00, 82500.00, 82500.00, 82500.00, 'loan', '19', 1, '2026-02-09 14:41:14', '2026-02-09 14:41:14', 3),
(13645, '2026-02-09', 'Asset', '1201', 'Loans to Customers', 'Loan Disbursement', 'cbf100', 'Loan #LN-20260209-151040 to cbf test', 0.00, 945000.00, 0.00, 945000.00, 945000.00, 'loan', '20', 1, '2026-02-09 15:11:35', '2026-02-09 15:11:35', 1),
(13646, '2026-02-09', 'Asset', '1102', 'Bank Account', 'Bank Transfer', 'cbf100', 'Loan #LN-20260209-151040 to cbf test', 0.00, 0.00, 1000000.00, -1000000.00, -1000000.00, 'loan', '20', 1, '2026-02-09 15:11:35', '2026-02-09 15:11:35', 2),
(13647, '2026-02-09', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan #LN-20260209-151040 to cbf test', 0.00, 0.00, 55000.00, 55000.00, 55000.00, 'loan', '20', 1, '2026-02-09 15:11:35', '2026-02-09 15:11:35', 3),
(13648, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-143850 - Reference: ', -1000000.00, 600000.00, 0.00, 600000.00, -400000.00, 'loan_payment', '98', 1, '2026-02-09 15:51:28', '2026-02-09 15:51:28', 0),
(13649, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-143850 - Reference: ', 945000.00, 0.00, 525000.00, -525000.00, 420000.00, 'loan_payment', '98', 1, '2026-02-09 15:51:28', '2026-02-09 15:51:28', 0),
(13650, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-143850 - Reference: ', 0.00, 0.00, 75000.00, 75000.00, 75000.00, 'loan_payment', '98', 1, '2026-02-09 15:51:28', '2026-02-09 15:51:28', 0),
(13651, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-143850 - Reference: ', -400000.00, 206707.00, 0.00, 206707.00, -193293.00, 'loan_payment', '98', 1, '2026-02-09 15:52:40', '2026-02-09 15:52:40', 0),
(13652, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-143850 - Reference: ', 420000.00, 0.00, 131707.00, -131707.00, 288293.00, 'loan_payment', '98', 1, '2026-02-09 15:52:40', '2026-02-09 15:52:40', 0),
(13653, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-143850 - Reference: ', 75000.00, 0.00, 75000.00, 75000.00, 150000.00, 'loan_payment', '98', 1, '2026-02-09 15:52:40', '2026-02-09 15:52:40', 0),
(13654, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-143850 - Reference: ', -193293.00, 0.32, 0.00, 0.32, -193292.68, 'loan_payment', '98', 1, '2026-02-09 15:53:07', '2026-02-09 15:53:07', 0),
(13655, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-143850 - Reference: ', 150000.00, 0.00, 0.32, 0.32, 150000.32, 'loan_payment', '98', 1, '2026-02-09 15:53:07', '2026-02-09 15:53:07', 0),
(13656, '2026-02-09', 'Assets', '1101', 'Cash on Hand', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260209-143850 - Reference: ', 0.00, 600000.00, 0.00, 600000.00, 600000.00, 'loan_payment', '99', 1, '2026-02-09 15:55:56', '2026-02-09 15:55:56', 0),
(13657, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260209-143850 - Reference: ', 288293.00, 0.00, 479085.37, -479085.37, -190792.37, 'loan_payment', '99', 1, '2026-02-09 15:55:56', '2026-02-09 15:55:56', 0),
(13658, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260209-143850 - Reference: ', 150000.32, 0.00, 38414.63, 38414.63, 188414.95, 'loan_payment', '99', 1, '2026-02-09 15:55:56', '2026-02-09 15:55:56', 0),
(13659, '2026-02-09', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260209-143850 - Reference: ', 55000.00, 0.00, 82500.00, 82500.00, 137500.00, 'loan_payment', '99', 1, '2026-02-09 15:55:56', '2026-02-09 15:55:56', 0),
(13660, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260209-143850 - Reference: ', -193292.68, 289207.00, 0.00, 289207.00, 95914.32, 'loan_payment', '99', 1, '2026-02-09 15:56:38', '2026-02-09 15:56:38', 0),
(13661, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260209-143850 - Reference: ', -190792.37, 0.00, 168292.37, -168292.37, -359084.74, 'loan_payment', '99', 1, '2026-02-09 15:56:38', '2026-02-09 15:56:38', 0),
(13662, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260209-143850 - Reference: ', 188414.95, 0.00, 38414.63, 38414.63, 226829.58, 'loan_payment', '99', 1, '2026-02-09 15:56:38', '2026-02-09 15:56:38', 0),
(13663, '2026-02-09', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260209-143850 - Reference: ', 137500.00, 0.00, 82500.00, 82500.00, 220000.00, 'loan_payment', '99', 1, '2026-02-09 15:56:38', '2026-02-09 15:56:38', 0),
(13664, '2026-02-09', 'Asset', '1201', 'Loans to Customers', 'Loan Disbursement', 'cbf100', 'Loan #LN-20260209-155741 to cbf test', -359084.74, 1417500.00, 0.00, 1417500.00, 1058415.26, 'loan', '21', 1, '2026-02-09 15:57:58', '2026-02-09 15:57:58', 1),
(13665, '2026-02-09', 'Asset', '1102', 'Bank Account', 'Bank Transfer', 'cbf100', 'Loan #LN-20260209-155741 to cbf test', 95914.32, 0.00, 1500000.00, -1500000.00, -1404085.68, 'loan', '21', 1, '2026-02-09 15:57:58', '2026-02-09 15:57:58', 2),
(13666, '2026-02-09', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan #LN-20260209-155741 to cbf test', 220000.00, 0.00, 82500.00, 82500.00, 302500.00, 'loan', '21', 1, '2026-02-09 15:57:58', '2026-02-09 15:57:58', 3),
(13667, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-155741 - Reference: ', -1404085.68, 600000.00, 0.00, 600000.00, -804085.68, 'loan_payment', '103', 1, '2026-02-09 15:58:24', '2026-02-09 15:58:24', 0),
(13668, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-155741 - Reference: ', 1058415.26, 0.00, 525000.00, -525000.00, 533415.26, 'loan_payment', '103', 1, '2026-02-09 15:58:24', '2026-02-09 15:58:24', 0),
(13669, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-155741 - Reference: ', 226829.58, 0.00, 75000.00, 75000.00, 301829.58, 'loan_payment', '103', 1, '2026-02-09 15:58:24', '2026-02-09 15:58:24', 0),
(13670, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-155741 - Reference: ', -804085.68, 206707.00, 0.00, 206707.00, -597378.68, 'loan_payment', '103', 1, '2026-02-09 15:58:48', '2026-02-09 15:58:48', 0),
(13671, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-155741 - Reference: ', 533415.26, 0.00, 131707.00, -131707.00, 401708.26, 'loan_payment', '103', 1, '2026-02-09 15:58:48', '2026-02-09 15:58:48', 0),
(13672, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-155741 - Reference: ', 301829.58, 0.00, 75000.00, 75000.00, 376829.58, 'loan_payment', '103', 1, '2026-02-09 15:58:48', '2026-02-09 15:58:48', 0),
(13673, '2026-02-09', 'Assets', '1101', 'Cash on Hand', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-155741 - Reference: ', 600000.00, 0.32, 0.00, 0.32, 600000.32, 'loan_payment', '103', 1, '2026-02-09 16:01:26', '2026-02-09 16:01:26', 0),
(13674, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-155741 - Reference: ', 376829.58, 0.00, 0.32, 0.32, 376829.90, 'loan_payment', '103', 1, '2026-02-09 16:01:26', '2026-02-09 16:01:26', 0),
(13675, '2026-02-09', 'Asset', '1201', 'Loans to Customers', 'Loan Disbursement', 'cbf100', 'Loan #LN-20260209-161242 to cbf test', 401708.26, 1993950.00, 0.00, 1993950.00, 2395658.26, 'loan', '22', 1, '2026-02-09 16:12:53', '2026-02-09 16:12:53', 1),
(13676, '2026-02-09', 'Asset', '1102', 'Bank Account', 'Bank Transfer', 'cbf100', 'Loan #LN-20260209-161242 to cbf test', -597378.68, 0.00, 2110000.00, -2110000.00, -2707378.68, 'loan', '22', 1, '2026-02-09 16:12:53', '2026-02-09 16:12:53', 2),
(13677, '2026-02-09', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan #LN-20260209-161242 to cbf test', 220000.00, 0.00, 116050.00, 116050.00, 336050.00, 'loan', '22', 1, '2026-02-09 16:12:53', '2026-02-09 16:12:53', 3),
(13678, '2026-02-09', 'Asset', '1201', 'Loans to Customers', 'Loan Disbursement', 'cbf100', 'Loan #LN-20260209-161510 to cbf test', 401708.26, 1890000.00, 0.00, 1890000.00, 2291708.26, 'loan', '23', 1, '2026-02-09 16:15:18', '2026-02-09 16:15:18', 1),
(13679, '2026-02-09', 'Asset', '1102', 'Bank Account', 'Bank Transfer', 'cbf100', 'Loan #LN-20260209-161510 to cbf test', -597378.68, 0.00, 2000000.00, -2000000.00, -2597378.68, 'loan', '23', 1, '2026-02-09 16:15:18', '2026-02-09 16:15:18', 2),
(13680, '2026-02-09', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan #LN-20260209-161510 to cbf test', 220000.00, 0.00, 110000.00, 110000.00, 330000.00, 'loan', '23', 1, '2026-02-09 16:15:18', '2026-02-09 16:15:18', 3),
(13681, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-161510 - Reference: 1122222', -2597378.68, 394034.94, 0.00, 394034.94, -2203343.74, 'loan_payment', '111', 1, '2026-02-09 16:17:06', '2026-02-09 16:17:06', 0),
(13682, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-161510 - Reference: 1122222', 2291708.26, 0.00, 294034.94, -294034.94, 1997673.32, 'loan_payment', '111', 1, '2026-02-09 16:17:06', '2026-02-09 16:17:06', 0),
(13683, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260209-161510 - Reference: 1122222', 376829.90, 0.00, 100000.00, 100000.00, 476829.90, 'loan_payment', '111', 1, '2026-02-09 16:17:06', '2026-02-09 16:17:06', 0),
(13684, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #3 - Loan #LN-20260209-161510 - Reference: ', -2203343.74, 195298.00, 0.00, 195298.00, -2008045.74, 'loan_payment', '113', 1, '2026-02-09 16:22:56', '2026-02-09 16:22:56', 0),
(13685, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #3 - Loan #LN-20260209-161510 - Reference: ', 1997673.32, 0.00, 15436.58, -15436.58, 1982236.74, 'loan_payment', '113', 1, '2026-02-09 16:22:56', '2026-02-09 16:22:56', 0),
(13686, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #3 - Loan #LN-20260209-161510 - Reference: ', 476829.90, 0.00, 69861.42, 69861.42, 546691.32, 'loan_payment', '113', 1, '2026-02-09 16:22:56', '2026-02-09 16:22:56', 0),
(13687, '2026-02-09', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan payment - Instalment #3 - Loan #LN-20260209-161510 - Reference: ', 330000.00, 0.00, 110000.00, 110000.00, 440000.00, 'loan_payment', '113', 1, '2026-02-09 16:22:56', '2026-02-09 16:22:56', 0),
(13688, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C76', 'Loan payment - Instalment #1 - Loan #LN-20260210-065857 - Reference: ', -2008045.74, 367210.00, 0.00, 367210.00, -1640835.74, 'loan_payment', '194', 1, '2026-02-11 07:42:07', '2026-02-11 07:42:07', 0),
(13689, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C76', 'Loan payment - Instalment #1 - Loan #LN-20260210-065857 - Reference: ', 1982236.74, 0.00, 317210.00, -317210.00, 1665026.74, 'loan_payment', '194', 1, '2026-02-11 07:42:07', '2026-02-11 07:42:07', 0),
(13690, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C76', 'Loan payment - Instalment #1 - Loan #LN-20260210-065857 - Reference: ', 546691.32, 0.00, 50000.00, 50000.00, 596691.32, 'loan_payment', '194', 1, '2026-02-11 07:42:07', '2026-02-11 07:42:07', 0),
(13691, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C76', 'Loan payment - Instalment #2 - Loan #LN-20260210-065857 - Reference: ', -1640835.74, 422210.00, 0.00, 422210.00, -1218625.74, 'loan_payment', '195', 1, '2026-02-11 07:42:31', '2026-02-11 07:42:31', 0),
(13692, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C76', 'Loan payment - Instalment #2 - Loan #LN-20260210-065857 - Reference: ', 1665026.74, 0.00, 333070.00, -333070.00, 1331956.74, 'loan_payment', '195', 1, '2026-02-11 07:42:31', '2026-02-11 07:42:31', 0),
(13693, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C76', 'Loan payment - Instalment #2 - Loan #LN-20260210-065857 - Reference: ', 596691.32, 0.00, 34140.00, 34140.00, 630831.32, 'loan_payment', '195', 1, '2026-02-11 07:42:31', '2026-02-11 07:42:31', 0),
(13694, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C76', 'Loan payment - Instalment #2 - Loan #LN-20260210-065857 - Reference: ', 440000.00, 0.00, 55000.00, 55000.00, 495000.00, 'loan_payment', '195', 1, '2026-02-11 07:42:31', '2026-02-11 07:42:31', 0),
(13695, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C76', 'Loan payment - Instalment #3 - Loan #LN-20260210-065857 - Reference: ', -1218625.74, 422210.00, 0.00, 422210.00, -796415.74, 'loan_payment', '196', 1, '2026-02-11 07:44:03', '2026-02-11 07:44:03', 0),
(13696, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C76', 'Loan payment - Instalment #3 - Loan #LN-20260210-065857 - Reference: ', 1331956.74, 0.00, 349720.00, -349720.00, 982236.74, 'loan_payment', '196', 1, '2026-02-11 07:44:03', '2026-02-11 07:44:03', 0),
(13697, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C76', 'Loan payment - Instalment #3 - Loan #LN-20260210-065857 - Reference: ', 630831.32, 0.00, 17490.00, 17490.00, 648321.32, 'loan_payment', '196', 1, '2026-02-11 07:44:03', '2026-02-11 07:44:03', 0),
(13698, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C76', 'Loan payment - Instalment #3 - Loan #LN-20260210-065857 - Reference: ', 495000.00, 0.00, 55000.00, 55000.00, 550000.00, 'loan_payment', '196', 1, '2026-02-11 07:44:03', '2026-02-11 07:44:03', 0),
(13699, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C025', 'Loan payment - Instalment #1 - Loan #LN-20260210-205936 - Reference: ', -796415.74, 22155000.00, 0.00, 22155000.00, 21358584.26, 'loan_payment', '337', 1, '2026-02-11 08:07:10', '2026-02-11 08:07:10', 0),
(13700, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C025', 'Loan payment - Instalment #1 - Loan #LN-20260210-205936 - Reference: ', 982236.74, 0.00, 21100000.00, -21100000.00, -20117763.26, 'loan_payment', '337', 1, '2026-02-11 08:07:10', '2026-02-11 08:07:10', 0),
(13701, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C025', 'Loan payment - Instalment #1 - Loan #LN-20260210-205936 - Reference: ', 648321.32, 0.00, 1055000.00, 1055000.00, 1703321.32, 'loan_payment', '337', 1, '2026-02-11 08:07:10', '2026-02-11 08:07:10', 0),
(13702, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C017', 'Loan payment - Instalment #1 - Loan #LN-20260211-121925 - Reference: ', 21358584.26, 7344170.00, 0.00, 7344170.00, 28702754.26, 'loan_payment', '342', 1, '2026-02-11 12:55:11', '2026-02-11 12:55:11', 0),
(13703, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C017', 'Loan payment - Instalment #1 - Loan #LN-20260211-121925 - Reference: ', -20117763.26, 0.00, 6344170.00, -6344170.00, -26461933.26, 'loan_payment', '342', 1, '2026-02-11 12:55:11', '2026-02-11 12:55:11', 0),
(13704, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C017', 'Loan payment - Instalment #1 - Loan #LN-20260211-121925 - Reference: ', 1703321.32, 0.00, 1000000.00, 1000000.00, 2703321.32, 'loan_payment', '342', 1, '2026-02-11 12:55:11', '2026-02-11 12:55:11', 0),
(13705, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C017', 'Loan payment - Instalment #2 - Loan #LN-20260211-121925 - Reference: ', 28702754.26, 1555830.00, 0.00, 1555830.00, 30258584.26, 'loan_payment', '343', 1, '2026-02-11 12:56:48', '2026-02-11 12:56:48', 0),
(13706, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C017', 'Loan payment - Instalment #2 - Loan #LN-20260211-121925 - Reference: ', 2703321.32, 0.00, 682790.00, 682790.00, 3386111.32, 'loan_payment', '343', 1, '2026-02-11 12:56:48', '2026-02-11 12:56:48', 0),
(13707, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C017', 'Loan payment - Instalment #2 - Loan #LN-20260211-121925 - Reference: ', 550000.00, 0.00, 873040.00, 873040.00, 1423040.00, 'loan_payment', '343', 1, '2026-02-11 12:56:48', '2026-02-11 12:56:48', 0),
(13708, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C016', 'Loan payment - Instalment #1 - Loan #LN-20260210-205608 - Reference: ', 30258584.26, 300000.00, 0.00, 300000.00, 30558584.26, 'loan_payment', '329', 1, '2026-02-11 13:12:59', '2026-02-11 13:12:59', 0),
(13709, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C016', 'Loan payment - Instalment #1 - Loan #LN-20260210-205608 - Reference: ', -26461933.26, 0.00, 235000.00, -235000.00, -26696933.26, 'loan_payment', '329', 1, '2026-02-11 13:12:59', '2026-02-11 13:12:59', 0),
(13710, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C016', 'Loan payment - Instalment #1 - Loan #LN-20260210-205608 - Reference: ', 3386111.32, 0.00, 65000.00, 65000.00, 3451111.32, 'loan_payment', '329', 1, '2026-02-11 13:12:59', '2026-02-11 13:12:59', 0),
(13711, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C016', 'Loan payment - Instalment #1 - Loan #LN-20260211-131649 - Reference: ', 30558584.26, 300000.00, 0.00, 300000.00, 30858584.26, 'loan_payment', '345', 1, '2026-02-11 13:23:27', '2026-02-11 13:23:27', 0),
(13712, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C016', 'Loan payment - Instalment #1 - Loan #LN-20260211-131649 - Reference: ', -26696933.26, 0.00, 235000.00, -235000.00, -26931933.26, 'loan_payment', '345', 1, '2026-02-11 13:23:27', '2026-02-11 13:23:27', 0),
(13713, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C016', 'Loan payment - Instalment #1 - Loan #LN-20260211-131649 - Reference: ', 3451111.32, 0.00, 65000.00, 65000.00, 3516111.32, 'loan_payment', '345', 1, '2026-02-11 13:23:27', '2026-02-11 13:23:27', 0),
(13714, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C014', 'Loan payment - Instalment #1 - Loan #LN-20260210-204420 - Reference: ', 30858584.26, 84600.00, 0.00, 84600.00, 30943184.26, 'loan_payment', '310', 1, '2026-02-11 13:51:10', '2026-02-11 13:51:10', 0),
(13715, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C014', 'Loan payment - Instalment #1 - Loan #LN-20260210-204420 - Reference: ', -26931933.26, 0.00, 69600.00, -69600.00, -27001533.26, 'loan_payment', '310', 1, '2026-02-11 13:51:10', '2026-02-11 13:51:10', 0),
(13716, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C014', 'Loan payment - Instalment #1 - Loan #LN-20260210-204420 - Reference: ', 3516111.32, 0.00, 15000.00, 15000.00, 3531111.32, 'loan_payment', '310', 1, '2026-02-11 13:51:10', '2026-02-11 13:51:10', 0),
(13717, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C014', 'Loan payment - Instalment #2 - Loan #LN-20260210-204420 - Reference: ', 30943184.26, 101100.00, 0.00, 101100.00, 31044284.26, 'loan_payment', '311', 1, '2026-02-11 13:51:53', '2026-02-11 13:51:53', 0),
(13718, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C014', 'Loan payment - Instalment #2 - Loan #LN-20260210-204420 - Reference: ', -27001533.26, 0.00, 73080.00, -73080.00, -27074613.26, 'loan_payment', '311', 1, '2026-02-11 13:51:53', '2026-02-11 13:51:53', 0),
(13719, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C014', 'Loan payment - Instalment #2 - Loan #LN-20260210-204420 - Reference: ', 3531111.32, 0.00, 11520.00, 11520.00, 3542631.32, 'loan_payment', '311', 1, '2026-02-11 13:51:53', '2026-02-11 13:51:53', 0),
(13720, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C014', 'Loan payment - Instalment #2 - Loan #LN-20260210-204420 - Reference: ', 1423040.00, 0.00, 16500.00, 16500.00, 1439540.00, 'loan_payment', '311', 1, '2026-02-11 13:51:53', '2026-02-11 13:51:53', 0),
(13721, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C014', 'Loan payment - Instalment #3 - Loan #LN-20260210-204420 - Reference: ', 31044284.26, 101110.00, 0.00, 101110.00, 31145394.26, 'loan_payment', '312', 1, '2026-02-11 13:52:28', '2026-02-11 13:52:28', 0),
(13722, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C014', 'Loan payment - Instalment #3 - Loan #LN-20260210-204420 - Reference: ', -27074613.26, 0.00, 76740.00, -76740.00, -27151353.26, 'loan_payment', '312', 1, '2026-02-11 13:52:28', '2026-02-11 13:52:28', 0),
(13723, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C014', 'Loan payment - Instalment #3 - Loan #LN-20260210-204420 - Reference: ', 3542631.32, 0.00, 7870.00, 7870.00, 3550501.32, 'loan_payment', '312', 1, '2026-02-11 13:52:28', '2026-02-11 13:52:28', 0),
(13724, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C014', 'Loan payment - Instalment #3 - Loan #LN-20260210-204420 - Reference: ', 1439540.00, 0.00, 16500.00, 16500.00, 1456040.00, 'loan_payment', '312', 1, '2026-02-11 13:52:28', '2026-02-11 13:52:28', 0),
(13725, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C079', 'Loan payment - Instalment #1 - Loan #LN-20260210-204952 - Reference: ', 31145394.26, 2151220.00, 0.00, 2151220.00, 33296614.26, 'loan_payment', '321', 1, '2026-02-11 13:57:21', '2026-02-11 13:57:21', 0),
(13726, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C079', 'Loan payment - Instalment #1 - Loan #LN-20260210-204952 - Reference: ', -27151353.26, 0.00, 1951220.00, -1951220.00, -29102573.26, 'loan_payment', '321', 1, '2026-02-11 13:57:21', '2026-02-11 13:57:21', 0),
(13727, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C079', 'Loan payment - Instalment #1 - Loan #LN-20260210-204952 - Reference: ', 3550501.32, 0.00, 200000.00, 200000.00, 3750501.32, 'loan_payment', '321', 1, '2026-02-11 13:57:21', '2026-02-11 13:57:21', 0),
(13728, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C079', 'Loan payment - Instalment #2 - Loan #LN-20260210-204952 - Reference: ', 33296614.26, 2371220.00, 0.00, 2371220.00, 35667834.26, 'loan_payment', '322', 1, '2026-02-11 13:57:46', '2026-02-11 13:57:46', 0),
(13729, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C079', 'Loan payment - Instalment #2 - Loan #LN-20260210-204952 - Reference: ', -29102573.26, 0.00, 2048780.00, -2048780.00, -31151353.26, 'loan_payment', '322', 1, '2026-02-11 13:57:46', '2026-02-11 13:57:46', 0),
(13730, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C079', 'Loan payment - Instalment #2 - Loan #LN-20260210-204952 - Reference: ', 3750501.32, 0.00, 102440.00, 102440.00, 3852941.32, 'loan_payment', '322', 1, '2026-02-11 13:57:46', '2026-02-11 13:57:46', 0),
(13731, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C079', 'Loan payment - Instalment #2 - Loan #LN-20260210-204952 - Reference: ', 1456040.00, 0.00, 220000.00, 220000.00, 1676040.00, 'loan_payment', '322', 1, '2026-02-11 13:57:46', '2026-02-11 13:57:46', 0),
(13732, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C59', 'Loan payment - Instalment #1 - Loan #LN-20260210-204627 - Reference: ', 35667834.26, 1575000.00, 0.00, 1575000.00, 37242834.26, 'loan_payment', '319', 1, '2026-02-11 14:02:42', '2026-02-11 14:02:42', 0),
(13733, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C59', 'Loan payment - Instalment #1 - Loan #LN-20260210-204627 - Reference: ', -31151353.26, 0.00, 1425000.00, -1425000.00, -32576353.26, 'loan_payment', '319', 1, '2026-02-11 14:02:42', '2026-02-11 14:02:42', 0),
(13734, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C59', 'Loan payment - Instalment #1 - Loan #LN-20260210-204627 - Reference: ', 3852941.32, 0.00, 150000.00, 150000.00, 4002941.32, 'loan_payment', '319', 1, '2026-02-11 14:02:42', '2026-02-11 14:02:42', 0),
(13735, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C035', 'Loan payment - Instalment #1 - Loan #LN-20260210-203413 - Reference: ', 37242834.26, 1410060.00, 0.00, 1410060.00, 38652894.26, 'loan_payment', '289', 1, '2026-02-11 14:04:14', '2026-02-11 14:04:14', 0),
(13736, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C035', 'Loan payment - Instalment #1 - Loan #LN-20260210-203413 - Reference: ', -32576353.26, 0.00, 1160060.00, -1160060.00, -33736413.26, 'loan_payment', '289', 1, '2026-02-11 14:04:14', '2026-02-11 14:04:14', 0),
(13737, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C035', 'Loan payment - Instalment #1 - Loan #LN-20260210-203413 - Reference: ', 4002941.32, 0.00, 250000.00, 250000.00, 4252941.32, 'loan_payment', '289', 1, '2026-02-11 14:04:14', '2026-02-11 14:04:14', 0),
(13738, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C035', 'Loan payment - Instalment #2 - Loan #LN-20260210-203413 - Reference: ', 38652894.26, 1685060.00, 0.00, 1685060.00, 40337954.26, 'loan_payment', '290', 1, '2026-02-11 14:04:26', '2026-02-11 14:04:26', 0),
(13739, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C035', 'Loan payment - Instalment #2 - Loan #LN-20260210-203413 - Reference: ', -33736413.26, 0.00, 1218060.00, -1218060.00, -34954473.26, 'loan_payment', '290', 1, '2026-02-11 14:04:26', '2026-02-11 14:04:26', 0),
(13740, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C035', 'Loan payment - Instalment #2 - Loan #LN-20260210-203413 - Reference: ', 4252941.32, 0.00, 192000.00, 192000.00, 4444941.32, 'loan_payment', '290', 1, '2026-02-11 14:04:26', '2026-02-11 14:04:26', 0),
(13741, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C035', 'Loan payment - Instalment #2 - Loan #LN-20260210-203413 - Reference: ', 1676040.00, 0.00, 275000.00, 275000.00, 1951040.00, 'loan_payment', '290', 1, '2026-02-11 14:04:26', '2026-02-11 14:04:26', 0),
(13742, '2026-01-30', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C69', 'Loan payment - Instalment #2 - Loan #LN-20260210-204526 - Reference: ', 0.00, 360328.50, 0.00, 360328.50, 360328.50, 'loan_payment', '315', 1, '2026-02-11 14:17:45', '2026-02-11 14:17:45', 0),
(13743, '2026-01-30', 'Revenue', '4205', 'Penalty Charges', 'Penalty for Late Payment', 'C69', 'Loan payment - Instalment #2 - Loan #LN-20260210-204526 - Reference: ', 0.00, 0.00, 17158.50, 17158.50, 17158.50, 'loan_payment', '315', 1, '2026-02-11 14:17:45', '2026-02-11 14:17:45', 0),
(13744, '2026-01-30', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C69', 'Loan payment - Instalment #2 - Loan #LN-20260210-204526 - Reference: ', 0.00, 0.00, 228030.00, -228030.00, -228030.00, 'loan_payment', '315', 1, '2026-02-11 14:17:45', '2026-02-11 14:17:45', 0),
(13745, '2026-01-30', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C69', 'Loan payment - Instalment #2 - Loan #LN-20260210-204526 - Reference: ', 0.00, 0.00, 49140.00, 49140.00, 49140.00, 'loan_payment', '315', 1, '2026-02-11 14:17:45', '2026-02-11 14:17:45', 0),
(13746, '2026-01-30', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C69', 'Loan payment - Instalment #2 - Loan #LN-20260210-204526 - Reference: ', 0.00, 0.00, 66000.00, 66000.00, 66000.00, 'loan_payment', '315', 1, '2026-02-11 14:17:45', '2026-02-11 14:17:45', 0),
(13747, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C69', 'Loan payment - Instalment #1 - Loan #LN-20260210-204526 - Reference: ', 40337954.26, 277170.00, 0.00, 277170.00, 40615124.26, 'loan_payment', '314', 1, '2026-02-11 14:18:37', '2026-02-11 14:18:37', 0),
(13748, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C69', 'Loan payment - Instalment #1 - Loan #LN-20260210-204526 - Reference: ', -34954473.26, 0.00, 217170.00, -217170.00, -35171643.26, 'loan_payment', '314', 1, '2026-02-11 14:18:37', '2026-02-11 14:18:37', 0),
(13749, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C69', 'Loan payment - Instalment #1 - Loan #LN-20260210-204526 - Reference: ', 4444941.32, 0.00, 60000.00, 60000.00, 4504941.32, 'loan_payment', '314', 1, '2026-02-11 14:18:37', '2026-02-11 14:18:37', 0),
(13750, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C041', 'Loan payment - Instalment #1 - Loan #LN-20260210-071329 - Reference: ', 40615124.26, 806710.00, 0.00, 806710.00, 41421834.26, 'loan_payment', '212', 1, '2026-02-11 14:22:13', '2026-02-11 14:22:13', 0),
(13751, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C041', 'Loan payment - Instalment #1 - Loan #LN-20260210-071329 - Reference: ', -35171643.26, 0.00, 731710.00, -731710.00, -35903353.26, 'loan_payment', '212', 1, '2026-02-11 14:22:13', '2026-02-11 14:22:13', 0),
(13752, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C041', 'Loan payment - Instalment #1 - Loan #LN-20260210-071329 - Reference: ', 4504941.32, 0.00, 75000.00, 75000.00, 4579941.32, 'loan_payment', '212', 1, '2026-02-11 14:22:13', '2026-02-11 14:22:13', 0),
(13753, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C041', 'Loan payment - Instalment #2 - Loan #LN-20260210-071329 - Reference: ', 41421834.26, 889200.00, 0.00, 889200.00, 42311034.26, 'loan_payment', '213', 1, '2026-02-11 14:22:26', '2026-02-11 14:22:26', 0),
(13754, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C041', 'Loan payment - Instalment #2 - Loan #LN-20260210-071329 - Reference: ', -35903353.26, 0.00, 768290.00, -768290.00, -36671643.26, 'loan_payment', '213', 1, '2026-02-11 14:22:26', '2026-02-11 14:22:26', 0),
(13755, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C041', 'Loan payment - Instalment #2 - Loan #LN-20260210-071329 - Reference: ', 4579941.32, 0.00, 38410.00, 38410.00, 4618351.32, 'loan_payment', '213', 1, '2026-02-11 14:22:26', '2026-02-11 14:22:26', 0),
(13756, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C041', 'Loan payment - Instalment #2 - Loan #LN-20260210-071329 - Reference: ', 1951040.00, 0.00, 82500.00, 82500.00, 2033540.00, 'loan_payment', '213', 1, '2026-02-11 14:22:26', '2026-02-11 14:22:26', 0),
(13757, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C017', 'Loan payment - Instalment #1 - Loan #LN-20260211-121925 - Reference: ', 42311034.26, 7344171.29, 0.00, 7344171.29, 49655205.55, 'loan_payment', '350', 1, '2026-02-11 14:29:53', '2026-02-11 14:29:53', 0),
(13758, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C017', 'Loan payment - Instalment #1 - Loan #LN-20260211-121925 - Reference: ', -36671643.26, 0.00, 6344171.29, -6344171.29, -43015814.55, 'loan_payment', '350', 1, '2026-02-11 14:29:53', '2026-02-11 14:29:53', 0),
(13759, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C017', 'Loan payment - Instalment #1 - Loan #LN-20260211-121925 - Reference: ', 4618351.32, 0.00, 1000000.00, 1000000.00, 5618351.32, 'loan_payment', '350', 1, '2026-02-11 14:29:53', '2026-02-11 14:29:53', 0),
(13760, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C017', 'Loan payment - Instalment #2 - Loan #LN-20260211-121925 - Reference: ', 49655205.55, 1555829.00, 0.00, 1555829.00, 51211034.55, 'loan_payment', '351', 1, '2026-02-11 14:31:02', '2026-02-11 14:31:02', 0),
(13761, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C017', 'Loan payment - Instalment #2 - Loan #LN-20260211-121925 - Reference: ', 5618351.32, 0.00, 682791.44, 682791.44, 6301142.76, 'loan_payment', '351', 1, '2026-02-11 14:31:02', '2026-02-11 14:31:02', 0),
(13762, '2026-02-11', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C017', 'Loan payment - Instalment #2 - Loan #LN-20260211-121925 - Reference: ', 2033540.00, 0.00, 873037.56, 873037.56, 2906577.56, 'loan_payment', '351', 1, '2026-02-11 14:31:02', '2026-02-11 14:31:02', 0),
(13763, '2026-02-12', 'Assets', '1101', 'Cash on Hand', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260211-182516 - Reference: ', 600000.32, 734420.00, 0.00, 734420.00, 1334420.32, 'loan_payment', '357', 1, '2026-02-12 06:00:40', '2026-02-12 06:00:40', 0),
(13764, '2026-02-12', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260211-182516 - Reference: ', -43015814.55, 0.00, 634420.00, -634420.00, -43650234.55, 'loan_payment', '357', 1, '2026-02-12 06:00:40', '2026-02-12 06:00:40', 0),
(13765, '2026-02-12', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260211-182516 - Reference: ', 6301142.76, 0.00, 100000.00, 100000.00, 6401142.76, 'loan_payment', '357', 1, '2026-02-12 06:00:40', '2026-02-12 06:00:40', 0),
(13766, '2026-02-12', 'Assets', '1101', 'Cash on Hand', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260211-182516 - Reference: ', 1334420.32, 444420.00, 0.00, 444420.00, 1778840.32, 'loan_payment', '358', 1, '2026-02-12 06:00:55', '2026-02-12 06:00:55', 0),
(13767, '2026-02-12', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260211-182516 - Reference: ', -43650234.55, 0.00, 266140.00, -266140.00, -43916374.55, 'loan_payment', '358', 1, '2026-02-12 06:00:55', '2026-02-12 06:00:55', 0),
(13768, '2026-02-12', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260211-182516 - Reference: ', 6401142.76, 0.00, 68280.00, 68280.00, 6469422.76, 'loan_payment', '358', 1, '2026-02-12 06:00:55', '2026-02-12 06:00:55', 0),
(13769, '2026-02-12', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260211-182516 - Reference: ', 2906577.56, 0.00, 110000.00, 110000.00, 3016577.56, 'loan_payment', '358', 1, '2026-02-12 06:00:55', '2026-02-12 06:00:55', 0),
(13770, '2025-12-10', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C71', 'Loan payment - Instalment #1 - Loan #LN-20260210-204109 - Reference: ', 0.00, 1847800.00, 0.00, 1847800.00, 1847800.00, 'loan_payment', '302', 1, '2026-02-13 08:01:55', '2026-02-13 08:01:55', 0),
(13771, '2025-12-10', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C71', 'Loan payment - Instalment #1 - Loan #LN-20260210-204109 - Reference: ', 0.00, 0.00, 1447800.00, -1447800.00, -1447800.00, 'loan_payment', '302', 1, '2026-02-13 08:01:55', '2026-02-13 08:01:55', 0),
(13772, '2025-12-10', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C71', 'Loan payment - Instalment #1 - Loan #LN-20260210-204109 - Reference: ', 0.00, 0.00, 400000.00, 400000.00, 400000.00, 'loan_payment', '302', 1, '2026-02-13 08:01:55', '2026-02-13 08:01:55', 0),
(13773, '2026-01-27', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C71', 'Loan payment - Instalment #2 - Loan #LN-20260210-204109 - Reference: ', 1847800.00, 2288798.00, 0.00, 2288798.00, 4136598.00, 'loan_payment', '303', 1, '2026-02-13 08:06:39', '2026-02-13 08:06:39', 0),
(13774, '2026-01-27', 'Revenue', '4205', 'Penalty Charges', 'Penalty for Late Payment', 'C71', 'Loan payment - Instalment #2 - Loan #LN-20260210-204109 - Reference: ', 0.00, 0.00, 64821.00, 64821.00, 64821.00, 'loan_payment', '303', 1, '2026-02-13 08:06:39', '2026-02-13 08:06:39', 0),
(13775, '2026-01-27', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C71', 'Loan payment - Instalment #2 - Loan #LN-20260210-204109 - Reference: ', -1447800.00, 0.00, 1456367.00, -1456367.00, -2904167.00, 'loan_payment', '303', 1, '2026-02-13 08:06:39', '2026-02-13 08:06:39', 0);
INSERT INTO `ledger` (`ledger_id`, `transaction_date`, `class`, `account_code`, `account_name`, `particular`, `voucher_number`, `narration`, `beginning_balance`, `debit_amount`, `credit_amount`, `movement`, `ending_balance`, `reference_type`, `reference_id`, `created_by`, `created_at`, `updated_at`, `sequence_number`) VALUES
(13776, '2026-01-27', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C71', 'Loan payment - Instalment #2 - Loan #LN-20260210-204109 - Reference: ', 400000.00, 0.00, 327610.00, 327610.00, 727610.00, 'loan_payment', '303', 1, '2026-02-13 08:06:39', '2026-02-13 08:06:39', 0),
(13777, '2026-01-27', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C71', 'Loan payment - Instalment #2 - Loan #LN-20260210-204109 - Reference: ', 0.00, 0.00, 440000.00, 440000.00, 440000.00, 'loan_payment', '303', 1, '2026-02-13 08:06:39', '2026-02-13 08:06:39', 0),
(13778, '2026-01-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C033', 'Loan payment - Instalment #1 - Loan #LN-20260210-204014 - Reference: ', 1847800.00, 197410.00, 0.00, 197410.00, 2045210.00, 'loan_payment', '298', 1, '2026-02-13 08:24:41', '2026-02-13 08:24:41', 0),
(13779, '2026-01-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C033', 'Loan payment - Instalment #1 - Loan #LN-20260210-204014 - Reference: ', -1447800.00, 0.00, 162410.00, -162410.00, -1610210.00, 'loan_payment', '298', 1, '2026-02-13 08:24:41', '2026-02-13 08:24:41', 0),
(13780, '2026-01-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C033', 'Loan payment - Instalment #1 - Loan #LN-20260210-204014 - Reference: ', 400000.00, 0.00, 35000.00, 35000.00, 435000.00, 'loan_payment', '298', 1, '2026-02-13 08:24:41', '2026-02-13 08:24:41', 0),
(13781, '2025-10-10', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C66', 'Loan payment - Instalment #1 - Loan #LN-20260210-195457 - Reference: ', 0.00, 886580.00, 0.00, 886580.00, 886580.00, 'loan_payment', '220', 1, '2026-02-13 08:46:02', '2026-02-13 08:46:02', 0),
(13782, '2025-10-10', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C66', 'Loan payment - Instalment #1 - Loan #LN-20260210-195457 - Reference: ', 0.00, 0.00, 661580.00, -661580.00, -661580.00, 'loan_payment', '220', 1, '2026-02-13 08:46:02', '2026-02-13 08:46:02', 0),
(13783, '2025-10-10', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C66', 'Loan payment - Instalment #1 - Loan #LN-20260210-195457 - Reference: ', 0.00, 0.00, 225000.00, 225000.00, 225000.00, 'loan_payment', '220', 1, '2026-02-13 08:46:02', '2026-02-13 08:46:02', 0),
(13784, '2025-11-10', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C66', 'Loan payment - Instalment #2 - Loan #LN-20260210-195457 - Reference: ', 886580.00, 1134080.00, 0.00, 1134080.00, 2020660.00, 'loan_payment', '221', 1, '2026-02-13 08:46:21', '2026-02-13 08:46:21', 0),
(13785, '2025-11-10', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C66', 'Loan payment - Instalment #2 - Loan #LN-20260210-195457 - Reference: ', -661580.00, 0.00, 694660.00, -694660.00, -1356240.00, 'loan_payment', '221', 1, '2026-02-13 08:46:21', '2026-02-13 08:46:21', 0),
(13786, '2025-11-10', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C66', 'Loan payment - Instalment #2 - Loan #LN-20260210-195457 - Reference: ', 225000.00, 0.00, 191920.00, 191920.00, 416920.00, 'loan_payment', '221', 1, '2026-02-13 08:46:21', '2026-02-13 08:46:21', 0),
(13787, '2025-11-10', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C66', 'Loan payment - Instalment #2 - Loan #LN-20260210-195457 - Reference: ', 0.00, 0.00, 247500.00, 247500.00, 247500.00, 'loan_payment', '221', 1, '2026-02-13 08:46:21', '2026-02-13 08:46:21', 0),
(13788, '2025-11-10', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C66', 'Loan payment - Instalment #3 - Loan #LN-20260210-195457 - Reference: ', 2020660.00, 1134080.00, 0.00, 1134080.00, 3154740.00, 'loan_payment', '222', 1, '2026-02-13 08:46:39', '2026-02-13 08:46:39', 0),
(13789, '2025-11-10', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C66', 'Loan payment - Instalment #3 - Loan #LN-20260210-195457 - Reference: ', -1356240.00, 0.00, 729390.00, -729390.00, -2085630.00, 'loan_payment', '222', 1, '2026-02-13 08:46:39', '2026-02-13 08:46:39', 0),
(13790, '2025-11-10', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C66', 'Loan payment - Instalment #3 - Loan #LN-20260210-195457 - Reference: ', 416920.00, 0.00, 157190.00, 157190.00, 574110.00, 'loan_payment', '222', 1, '2026-02-13 08:46:39', '2026-02-13 08:46:39', 0),
(13791, '2025-11-10', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C66', 'Loan payment - Instalment #3 - Loan #LN-20260210-195457 - Reference: ', 247500.00, 0.00, 247500.00, 247500.00, 495000.00, 'loan_payment', '222', 1, '2026-02-13 08:46:39', '2026-02-13 08:46:39', 0),
(13792, '2026-01-20', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C66', 'Loan payment - Instalment #4 - Loan #LN-20260210-195457 - Reference: ', 2045210.00, 1150000.00, 0.00, 1150000.00, 3195210.00, 'loan_payment', '223', 1, '2026-02-13 09:01:26', '2026-02-13 09:01:26', 0),
(13793, '2026-01-20', 'Revenue', '4205', 'Penalty Charges', 'Penalty for Late Payment', 'C66', 'Loan payment - Instalment #4 - Loan #LN-20260210-195457 - Reference: ', 0.00, 0.00, 15121.07, 15121.07, 15121.07, 'loan_payment', '223', 1, '2026-02-13 09:01:26', '2026-02-13 09:01:26', 0),
(13794, '2026-01-20', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C66', 'Loan payment - Instalment #4 - Loan #LN-20260210-195457 - Reference: ', -1610210.00, 0.00, 766658.93, -766658.93, -2376868.93, 'loan_payment', '223', 1, '2026-02-13 09:01:26', '2026-02-13 09:01:26', 0),
(13795, '2026-01-20', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C66', 'Loan payment - Instalment #4 - Loan #LN-20260210-195457 - Reference: ', 435000.00, 0.00, 120720.00, 120720.00, 555720.00, 'loan_payment', '223', 1, '2026-02-13 09:01:26', '2026-02-13 09:01:26', 0),
(13796, '2026-01-20', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C66', 'Loan payment - Instalment #4 - Loan #LN-20260210-195457 - Reference: ', 495000.00, 0.00, 247500.00, 247500.00, 742500.00, 'loan_payment', '223', 1, '2026-02-13 09:01:26', '2026-02-13 09:01:26', 0),
(13797, '2026-02-13', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C66', 'Loan payment - Instalment #5 - Loan #LN-20260210-195457 - Reference: ', 51211034.55, 1166212.27, 0.00, 1166212.27, 52377246.82, 'loan_payment', '224', 1, '2026-02-13 09:08:40', '2026-02-13 09:08:40', 0),
(13798, '2026-02-13', 'Revenue', '4205', 'Penalty Charges', 'Penalty for Late Payment', 'C66', 'Loan payment - Instalment #5 - Loan #LN-20260210-195457 - Reference: ', 7139.72, 0.00, 32132.27, 32132.27, 39271.99, 'loan_payment', '224', 1, '2026-02-13 09:08:40', '2026-02-13 09:08:40', 0),
(13799, '2026-02-13', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C66', 'Loan payment - Instalment #5 - Loan #LN-20260210-195457 - Reference: ', -43916374.55, 0.00, 804150.00, -804150.00, -44720524.55, 'loan_payment', '224', 1, '2026-02-13 09:08:40', '2026-02-13 09:08:40', 0),
(13800, '2026-02-13', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C66', 'Loan payment - Instalment #5 - Loan #LN-20260210-195457 - Reference: ', 6469422.76, 0.00, 82430.00, 82430.00, 6551852.76, 'loan_payment', '224', 1, '2026-02-13 09:08:40', '2026-02-13 09:08:40', 0),
(13801, '2026-02-13', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C66', 'Loan payment - Instalment #5 - Loan #LN-20260210-195457 - Reference: ', 3016577.56, 0.00, 247500.00, 247500.00, 3264077.56, 'loan_payment', '224', 1, '2026-02-13 09:08:40', '2026-02-13 09:08:40', 0),
(13802, '2026-02-13', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C015', 'Loan payment - Instalment #1 - Loan #LN-20260210-205440 - Reference: ', 52377246.82, 734420.00, 0.00, 734420.00, 53111666.82, 'loan_payment', '323', 1, '2026-02-13 09:11:52', '2026-02-13 09:11:52', 0),
(13803, '2026-02-13', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C015', 'Loan payment - Instalment #1 - Loan #LN-20260210-205440 - Reference: ', -44720524.55, 0.00, 634420.00, -634420.00, -45354944.55, 'loan_payment', '323', 1, '2026-02-13 09:11:52', '2026-02-13 09:11:52', 0),
(13804, '2026-02-13', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C015', 'Loan payment - Instalment #1 - Loan #LN-20260210-205440 - Reference: ', 6551852.76, 0.00, 100000.00, 100000.00, 6651852.76, 'loan_payment', '323', 1, '2026-02-13 09:11:52', '2026-02-13 09:11:52', 0),
(13805, '2026-01-14', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C029', 'Loan payment - Instalment #1 - Loan #LN-20260210-202436 - Reference: ', 1847800.00, 367210.00, 0.00, 367210.00, 2215010.00, 'loan_payment', '264', 1, '2026-02-13 09:15:27', '2026-02-13 09:15:27', 0),
(13806, '2026-01-14', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C029', 'Loan payment - Instalment #1 - Loan #LN-20260210-202436 - Reference: ', -1447800.00, 0.00, 317210.00, -317210.00, -1765010.00, 'loan_payment', '264', 1, '2026-02-13 09:15:27', '2026-02-13 09:15:27', 0),
(13807, '2026-01-14', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C029', 'Loan payment - Instalment #1 - Loan #LN-20260210-202436 - Reference: ', 400000.00, 0.00, 50000.00, 50000.00, 450000.00, 'loan_payment', '264', 1, '2026-02-13 09:15:27', '2026-02-13 09:15:27', 0),
(13808, '2026-02-10', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C016', 'Loan payment - Instalment #1 - Loan #LN-20260211-131649 - Reference: ', -2008045.74, 270.00, 0.00, 270.00, -2007775.74, 'loan_payment', '345', 1, '2026-02-13 10:23:01', '2026-02-13 10:23:01', 0),
(13809, '2026-02-10', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C016', 'Loan payment - Instalment #1 - Loan #LN-20260211-131649 - Reference: ', 546691.32, 0.00, 270.00, 270.00, 546961.32, 'loan_payment', '345', 1, '2026-02-13 10:23:01', '2026-02-13 10:23:01', 0),
(13810, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C053', 'Loan payment - Instalment #1 - Loan #LN-20260210-205512 - Reference: ', -2008045.74, 367206.00, 0.00, 367206.00, -1640839.74, 'loan_payment', '326', 1, '2026-02-13 10:32:04', '2026-02-13 10:32:04', 0),
(13811, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C053', 'Loan payment - Instalment #1 - Loan #LN-20260210-205512 - Reference: ', 1982236.74, 0.00, 267206.00, -267206.00, 1715030.74, 'loan_payment', '326', 1, '2026-02-13 10:32:04', '2026-02-13 10:32:04', 0),
(13812, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C053', 'Loan payment - Instalment #1 - Loan #LN-20260210-205512 - Reference: ', 546691.32, 0.00, 100000.00, 100000.00, 646691.32, 'loan_payment', '326', 1, '2026-02-13 10:32:04', '2026-02-13 10:32:04', 0),
(13813, '2026-01-26', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C036', 'Loan payment - Instalment #1 - Loan #LN-20260210-204759 - Reference: ', 3195210.00, 40000.00, 0.00, 40000.00, 3235210.00, 'loan_payment', '320', 1, '2026-02-13 10:42:40', '2026-02-13 10:42:40', 0),
(13814, '2026-01-26', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C036', 'Loan payment - Instalment #1 - Loan #LN-20260210-204759 - Reference: ', 555720.00, 0.00, 40000.00, 40000.00, 595720.00, 'loan_payment', '320', 1, '2026-02-13 10:42:40', '2026-02-13 10:42:40', 0),
(13815, '2026-01-05', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C037', 'Loan payment - Instalment #1 - Loan #LN-20260210-204222 - Reference: ', 1847800.00, 367210.00, 0.00, 367210.00, 2215010.00, 'loan_payment', '307', 1, '2026-02-13 11:15:27', '2026-02-13 11:15:27', 0),
(13816, '2026-01-05', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C037', 'Loan payment - Instalment #1 - Loan #LN-20260210-204222 - Reference: ', -1447800.00, 0.00, 317210.00, -317210.00, -1765010.00, 'loan_payment', '307', 1, '2026-02-13 11:15:27', '2026-02-13 11:15:27', 0),
(13817, '2026-01-05', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C037', 'Loan payment - Instalment #1 - Loan #LN-20260210-204222 - Reference: ', 400000.00, 0.00, 50000.00, 50000.00, 450000.00, 'loan_payment', '307', 1, '2026-02-13 11:15:27', '2026-02-13 11:15:27', 0),
(13818, '2026-02-05', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C037', 'Loan payment - Instalment #2 - Loan #LN-20260210-204222 - Reference: ', 360328.50, 422210.00, 0.00, 422210.00, 782538.50, 'loan_payment', '308', 1, '2026-02-13 11:15:44', '2026-02-13 11:15:44', 0),
(13819, '2026-02-05', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C037', 'Loan payment - Instalment #2 - Loan #LN-20260210-204222 - Reference: ', -228030.00, 0.00, 333070.00, -333070.00, -561100.00, 'loan_payment', '308', 1, '2026-02-13 11:15:44', '2026-02-13 11:15:44', 0),
(13820, '2026-02-05', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C037', 'Loan payment - Instalment #2 - Loan #LN-20260210-204222 - Reference: ', 49140.00, 0.00, 34140.00, 34140.00, 83280.00, 'loan_payment', '308', 1, '2026-02-13 11:15:44', '2026-02-13 11:15:44', 0),
(13821, '2026-02-05', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C037', 'Loan payment - Instalment #2 - Loan #LN-20260210-204222 - Reference: ', 66000.00, 0.00, 55000.00, 55000.00, 121000.00, 'loan_payment', '308', 1, '2026-02-13 11:15:44', '2026-02-13 11:15:44', 0),
(13822, '2025-12-20', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C054', 'Loan payment - Instalment #1 - Loan #LN-20260210-203750 - Reference: ', 1847800.00, 367210.00, 0.00, 367210.00, 2215010.00, 'loan_payment', '295', 1, '2026-02-13 11:48:38', '2026-02-13 11:48:38', 0),
(13823, '2025-12-20', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C054', 'Loan payment - Instalment #1 - Loan #LN-20260210-203750 - Reference: ', -1447800.00, 0.00, 317210.00, -317210.00, -1765010.00, 'loan_payment', '295', 1, '2026-02-13 11:48:38', '2026-02-13 11:48:38', 0),
(13824, '2025-12-20', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C054', 'Loan payment - Instalment #1 - Loan #LN-20260210-203750 - Reference: ', 400000.00, 0.00, 50000.00, 50000.00, 450000.00, 'loan_payment', '295', 1, '2026-02-13 11:48:38', '2026-02-13 11:48:38', 0),
(13825, '2026-01-20', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C054', 'Loan payment - Instalment #2 - Loan #LN-20260210-203750 - Reference: ', 3195210.00, 422210.00, 0.00, 422210.00, 3617420.00, 'loan_payment', '296', 1, '2026-02-13 11:49:01', '2026-02-13 11:49:01', 0),
(13826, '2026-01-20', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C054', 'Loan payment - Instalment #2 - Loan #LN-20260210-203750 - Reference: ', -2376868.93, 0.00, 333070.00, -333070.00, -2709938.93, 'loan_payment', '296', 1, '2026-02-13 11:49:01', '2026-02-13 11:49:01', 0),
(13827, '2026-01-20', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C054', 'Loan payment - Instalment #2 - Loan #LN-20260210-203750 - Reference: ', 555720.00, 0.00, 34140.00, 34140.00, 589860.00, 'loan_payment', '296', 1, '2026-02-13 11:49:01', '2026-02-13 11:49:01', 0),
(13828, '2026-01-20', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C054', 'Loan payment - Instalment #2 - Loan #LN-20260210-203750 - Reference: ', 742500.00, 0.00, 55000.00, 55000.00, 797500.00, 'loan_payment', '296', 1, '2026-02-13 11:49:01', '2026-02-13 11:49:01', 0),
(13829, '2026-01-01', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C045', 'Loan payment - Instalment #1 - Loan #LN-20260210-203513 - Reference: ', 2215010.00, 161340.00, 0.00, 161340.00, 2376350.00, 'loan_payment', '293', 1, '2026-02-13 11:50:14', '2026-02-13 11:50:14', 0),
(13830, '2026-01-01', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C045', 'Loan payment - Instalment #1 - Loan #LN-20260210-203513 - Reference: ', -1765010.00, 0.00, 146340.00, -146340.00, -1911350.00, 'loan_payment', '293', 1, '2026-02-13 11:50:14', '2026-02-13 11:50:14', 0),
(13831, '2026-01-01', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C045', 'Loan payment - Instalment #1 - Loan #LN-20260210-203513 - Reference: ', 450000.00, 0.00, 15000.00, 15000.00, 465000.00, 'loan_payment', '293', 1, '2026-02-13 11:50:14', '2026-02-13 11:50:14', 0),
(13832, '2026-02-01', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C045', 'Loan payment - Instalment #2 - Loan #LN-20260210-203513 - Reference: ', 360328.50, 177840.00, 0.00, 177840.00, 538168.50, 'loan_payment', '294', 1, '2026-02-13 11:50:30', '2026-02-13 11:50:30', 0),
(13833, '2026-02-01', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C045', 'Loan payment - Instalment #2 - Loan #LN-20260210-203513 - Reference: ', -228030.00, 0.00, 153660.00, -153660.00, -381690.00, 'loan_payment', '294', 1, '2026-02-13 11:50:30', '2026-02-13 11:50:30', 0),
(13834, '2026-02-01', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C045', 'Loan payment - Instalment #2 - Loan #LN-20260210-203513 - Reference: ', 49140.00, 0.00, 7680.00, 7680.00, 56820.00, 'loan_payment', '294', 1, '2026-02-13 11:50:30', '2026-02-13 11:50:30', 0),
(13835, '2026-02-01', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C045', 'Loan payment - Instalment #2 - Loan #LN-20260210-203513 - Reference: ', 66000.00, 0.00, 16500.00, 16500.00, 82500.00, 'loan_payment', '294', 1, '2026-02-13 11:50:30', '2026-02-13 11:50:30', 0),
(13836, '2026-02-05', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C77', 'Loan payment - Instalment #1 - Loan #LN-20260210-203110 - Reference: ', 782538.50, 1836043.00, 0.00, 1836043.00, 2618581.50, 'loan_payment', '282', 1, '2026-02-13 11:55:48', '2026-02-13 11:55:48', 0),
(13837, '2026-02-05', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C77', 'Loan payment - Instalment #1 - Loan #LN-20260210-203110 - Reference: ', -561100.00, 0.00, 1586043.00, -1586043.00, -2147143.00, 'loan_payment', '282', 1, '2026-02-13 11:55:48', '2026-02-13 11:55:48', 0),
(13838, '2026-02-05', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C77', 'Loan payment - Instalment #1 - Loan #LN-20260210-203110 - Reference: ', 83280.00, 0.00, 250000.00, 250000.00, 333280.00, 'loan_payment', '282', 1, '2026-02-13 11:55:48', '2026-02-13 11:55:48', 0),
(13839, '2025-10-26', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C046', 'Loan payment - Instalment #1 - Loan #LN-20260210-202931 - Reference: ', 886580.00, 348850.00, 0.00, 348850.00, 1235430.00, 'loan_payment', '279', 1, '2026-02-13 11:57:24', '2026-02-13 11:57:24', 0),
(13840, '2025-10-26', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C046', 'Loan payment - Instalment #1 - Loan #LN-20260210-202931 - Reference: ', -661580.00, 0.00, 301350.00, -301350.00, -962930.00, 'loan_payment', '279', 1, '2026-02-13 11:57:24', '2026-02-13 11:57:24', 0),
(13841, '2025-10-26', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C046', 'Loan payment - Instalment #1 - Loan #LN-20260210-202931 - Reference: ', 225000.00, 0.00, 47500.00, 47500.00, 272500.00, 'loan_payment', '279', 1, '2026-02-13 11:57:24', '2026-02-13 11:57:24', 0),
(13842, '2025-11-26', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C046', 'Loan payment - Instalment #2 - Loan #LN-20260210-202931 - Reference: ', 3154740.00, 401100.00, 0.00, 401100.00, 3555840.00, 'loan_payment', '280', 1, '2026-02-13 11:57:51', '2026-02-13 11:57:51', 0),
(13843, '2025-11-26', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C046', 'Loan payment - Instalment #2 - Loan #LN-20260210-202931 - Reference: ', -2085630.00, 0.00, 316420.00, -316420.00, -2402050.00, 'loan_payment', '280', 1, '2026-02-13 11:57:51', '2026-02-13 11:57:51', 0),
(13844, '2025-11-26', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C046', 'Loan payment - Instalment #2 - Loan #LN-20260210-202931 - Reference: ', 574110.00, 0.00, 32430.00, 32430.00, 606540.00, 'loan_payment', '280', 1, '2026-02-13 11:57:51', '2026-02-13 11:57:51', 0),
(13845, '2025-11-26', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C046', 'Loan payment - Instalment #2 - Loan #LN-20260210-202931 - Reference: ', 495000.00, 0.00, 52250.00, 52250.00, 547250.00, 'loan_payment', '280', 1, '2026-02-13 11:57:51', '2026-02-13 11:57:51', 0),
(13846, '2025-12-26', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C046', 'Loan payment - Instalment #3 - Loan #LN-20260210-202931 - Reference: ', 2215010.00, 401100.00, 0.00, 401100.00, 2616110.00, 'loan_payment', '281', 1, '2026-02-13 11:58:44', '2026-02-13 11:58:44', 0),
(13847, '2025-12-26', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C046', 'Loan payment - Instalment #3 - Loan #LN-20260210-202931 - Reference: ', -1765010.00, 0.00, 332240.00, -332240.00, -2097250.00, 'loan_payment', '281', 1, '2026-02-13 11:58:44', '2026-02-13 11:58:44', 0),
(13848, '2025-12-26', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C046', 'Loan payment - Instalment #3 - Loan #LN-20260210-202931 - Reference: ', 450000.00, 0.00, 16610.00, 16610.00, 466610.00, 'loan_payment', '281', 1, '2026-02-13 11:58:44', '2026-02-13 11:58:44', 0),
(13849, '2025-12-26', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C046', 'Loan payment - Instalment #3 - Loan #LN-20260210-202931 - Reference: ', 547250.00, 0.00, 52250.00, 52250.00, 599500.00, 'loan_payment', '281', 1, '2026-02-13 11:58:44', '2026-02-13 11:58:44', 0),
(13850, '2026-02-11', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C046', 'Loan payment - Instalment #1 - Loan #LN-20260213-115907 - Reference: ', 51211034.55, 120000.00, 0.00, 120000.00, 51331034.55, 'loan_payment', '392', 1, '2026-02-13 12:01:42', '2026-02-13 12:01:42', 0),
(13851, '2026-02-11', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C046', 'Loan payment - Instalment #1 - Loan #LN-20260213-115907 - Reference: ', -43015814.55, 0.00, 85000.00, -85000.00, -43100814.55, 'loan_payment', '392', 1, '2026-02-13 12:01:42', '2026-02-13 12:01:42', 0),
(13852, '2026-02-11', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C046', 'Loan payment - Instalment #1 - Loan #LN-20260213-115907 - Reference: ', 6301142.76, 0.00, 35000.00, 35000.00, 6336142.76, 'loan_payment', '392', 1, '2026-02-13 12:01:42', '2026-02-13 12:01:42', 0),
(13853, '2025-12-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C61', 'Loan payment - Instalment #1 - Loan #LN-20260210-201919 - Reference: ', 1847800.00, 1410060.00, 0.00, 1410060.00, 3257860.00, 'loan_payment', '260', 1, '2026-02-13 12:06:46', '2026-02-13 12:06:46', 0),
(13854, '2025-12-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C61', 'Loan payment - Instalment #1 - Loan #LN-20260210-201919 - Reference: ', -1447800.00, 0.00, 1160060.00, -1160060.00, -2607860.00, 'loan_payment', '260', 1, '2026-02-13 12:06:46', '2026-02-13 12:06:46', 0),
(13855, '2025-12-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C61', 'Loan payment - Instalment #1 - Loan #LN-20260210-201919 - Reference: ', 400000.00, 0.00, 250000.00, 250000.00, 650000.00, 'loan_payment', '260', 1, '2026-02-13 12:06:46', '2026-02-13 12:06:46', 0),
(13856, '2026-01-20', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C61', 'Loan payment - Instalment #2 - Loan #LN-20260210-201919 - Reference: ', 3617420.00, 466997.00, 0.00, 466997.00, 4084417.00, 'loan_payment', '261', 1, '2026-02-13 12:08:15', '2026-02-13 12:08:15', 0),
(13857, '2026-01-20', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C61', 'Loan payment - Instalment #2 - Loan #LN-20260210-201919 - Reference: ', 589860.00, 0.00, 192000.00, 192000.00, 781860.00, 'loan_payment', '261', 1, '2026-02-13 12:08:15', '2026-02-13 12:08:15', 0),
(13858, '2026-01-20', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C61', 'Loan payment - Instalment #2 - Loan #LN-20260210-201919 - Reference: ', 797500.00, 0.00, 274997.00, 274997.00, 1072497.00, 'loan_payment', '261', 1, '2026-02-13 12:08:15', '2026-02-13 12:08:15', 0),
(13859, '2026-01-12', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C010', 'Loan payment - Instalment #1 - Loan #LN-20260210-201640 - Reference: ', 2215010.00, 367210.00, 0.00, 367210.00, 2582220.00, 'loan_payment', '254', 1, '2026-02-13 12:10:17', '2026-02-13 12:10:17', 0),
(13860, '2026-01-12', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C010', 'Loan payment - Instalment #1 - Loan #LN-20260210-201640 - Reference: ', -1765010.00, 0.00, 317210.00, -317210.00, -2082220.00, 'loan_payment', '254', 1, '2026-02-13 12:10:17', '2026-02-13 12:10:17', 0),
(13861, '2026-01-12', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C010', 'Loan payment - Instalment #1 - Loan #LN-20260210-201640 - Reference: ', 450000.00, 0.00, 50000.00, 50000.00, 500000.00, 'loan_payment', '254', 1, '2026-02-13 12:10:17', '2026-02-13 12:10:17', 0),
(13862, '2026-01-22', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C72', 'Loan payment - Instalment #1 - Loan #LN-20260210-201441 - Reference: ', 4084417.00, 500000.00, 0.00, 500000.00, 4584417.00, 'loan_payment', '248', 1, '2026-02-13 12:14:22', '2026-02-13 12:14:22', 0),
(13863, '2026-01-22', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C72', 'Loan payment - Instalment #1 - Loan #LN-20260210-201441 - Reference: ', -2709938.93, 0.00, 250000.00, -250000.00, -2959938.93, 'loan_payment', '248', 1, '2026-02-13 12:14:22', '2026-02-13 12:14:22', 0),
(13864, '2026-01-22', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C72', 'Loan payment - Instalment #1 - Loan #LN-20260210-201441 - Reference: ', 781860.00, 0.00, 250000.00, 250000.00, 1031860.00, 'loan_payment', '248', 1, '2026-02-13 12:14:22', '2026-02-13 12:14:22', 0),
(13865, '2025-12-19', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C055', 'Loan payment - Instalment #1 - Loan #LN-20260210-201109 - Reference: ', 3257860.00, 282010.00, 0.00, 282010.00, 3539870.00, 'loan_payment', '239', 1, '2026-02-13 12:18:30', '2026-02-13 12:18:30', 0),
(13866, '2025-12-19', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C055', 'Loan payment - Instalment #1 - Loan #LN-20260210-201109 - Reference: ', -2607860.00, 0.00, 232010.00, -232010.00, -2839870.00, 'loan_payment', '239', 1, '2026-02-13 12:18:30', '2026-02-13 12:18:30', 0),
(13867, '2025-12-19', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C055', 'Loan payment - Instalment #1 - Loan #LN-20260210-201109 - Reference: ', 650000.00, 0.00, 50000.00, 50000.00, 700000.00, 'loan_payment', '239', 1, '2026-02-13 12:18:30', '2026-02-13 12:18:30', 0),
(13868, '2026-01-19', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C055', 'Loan payment - Instalment #2 - Loan #LN-20260210-201109 - Reference: ', 2045210.00, 337010.00, 0.00, 337010.00, 2382220.00, 'loan_payment', '240', 1, '2026-02-13 12:19:09', '2026-02-13 12:19:09', 0),
(13869, '2026-01-19', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C055', 'Loan payment - Instalment #2 - Loan #LN-20260210-201109 - Reference: ', -1610210.00, 0.00, 243610.00, -243610.00, -1853820.00, 'loan_payment', '240', 1, '2026-02-13 12:19:09', '2026-02-13 12:19:09', 0),
(13870, '2026-01-19', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C055', 'Loan payment - Instalment #2 - Loan #LN-20260210-201109 - Reference: ', 435000.00, 0.00, 38400.00, 38400.00, 473400.00, 'loan_payment', '240', 1, '2026-02-13 12:19:09', '2026-02-13 12:19:09', 0),
(13871, '2026-01-19', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C055', 'Loan payment - Instalment #2 - Loan #LN-20260210-201109 - Reference: ', 599500.00, 0.00, 55000.00, 55000.00, 654500.00, 'loan_payment', '240', 1, '2026-02-13 12:19:09', '2026-02-13 12:19:09', 0),
(13872, '2025-12-27', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C047', 'Loan payment - Instalment #1 - Loan #LN-20260210-195746 - Reference: ', 2616110.00, 183600.00, 0.00, 183600.00, 2799710.00, 'loan_payment', '231', 1, '2026-02-13 12:21:13', '2026-02-13 12:21:13', 0),
(13873, '2025-12-27', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C047', 'Loan payment - Instalment #1 - Loan #LN-20260210-195746 - Reference: ', -2097250.00, 0.00, 158600.00, -158600.00, -2255850.00, 'loan_payment', '231', 1, '2026-02-13 12:21:13', '2026-02-13 12:21:13', 0),
(13874, '2025-12-27', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C047', 'Loan payment - Instalment #1 - Loan #LN-20260210-195746 - Reference: ', 466610.00, 0.00, 25000.00, 25000.00, 491610.00, 'loan_payment', '231', 1, '2026-02-13 12:21:13', '2026-02-13 12:21:13', 0),
(13875, '2026-01-27', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C047', 'Loan payment - Instalment #2 - Loan #LN-20260210-195746 - Reference: ', 4136598.00, 211100.00, 0.00, 211100.00, 4347698.00, 'loan_payment', '232', 1, '2026-02-13 12:21:56', '2026-02-13 12:21:56', 0),
(13876, '2026-01-27', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C047', 'Loan payment - Instalment #2 - Loan #LN-20260210-195746 - Reference: ', -2904167.00, 0.00, 166530.00, -166530.00, -3070697.00, 'loan_payment', '232', 1, '2026-02-13 12:21:56', '2026-02-13 12:21:56', 0),
(13877, '2026-01-27', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C047', 'Loan payment - Instalment #2 - Loan #LN-20260210-195746 - Reference: ', 727610.00, 0.00, 17070.00, 17070.00, 744680.00, 'loan_payment', '232', 1, '2026-02-13 12:21:56', '2026-02-13 12:21:56', 0),
(13878, '2026-01-27', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C047', 'Loan payment - Instalment #2 - Loan #LN-20260210-195746 - Reference: ', 440000.00, 0.00, 27500.00, 27500.00, 467500.00, 'loan_payment', '232', 1, '2026-02-13 12:21:56', '2026-02-13 12:21:56', 0),
(13879, '2025-11-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C081', 'Loan payment - Instalment #1 - Loan #LN-20260210-195307 - Reference: ', 3154740.00, 169210.00, 0.00, 169210.00, 3323950.00, 'loan_payment', '216', 1, '2026-02-13 12:43:51', '2026-02-13 12:43:51', 0),
(13880, '2025-11-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C081', 'Loan payment - Instalment #1 - Loan #LN-20260210-195307 - Reference: ', -2085630.00, 0.00, 139210.00, -139210.00, -2224840.00, 'loan_payment', '216', 1, '2026-02-13 12:43:51', '2026-02-13 12:43:51', 0),
(13881, '2025-11-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C081', 'Loan payment - Instalment #1 - Loan #LN-20260210-195307 - Reference: ', 574110.00, 0.00, 30000.00, 30000.00, 604110.00, 'loan_payment', '216', 1, '2026-02-13 12:43:51', '2026-02-13 12:43:51', 0),
(13882, '2025-12-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C081', 'Loan payment - Instalment #2 - Loan #LN-20260210-195307 - Reference: ', 3257860.00, 202210.00, 0.00, 202210.00, 3460070.00, 'loan_payment', '217', 1, '2026-02-13 12:44:18', '2026-02-13 12:44:18', 0),
(13883, '2025-12-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C081', 'Loan payment - Instalment #2 - Loan #LN-20260210-195307 - Reference: ', -2607860.00, 0.00, 146170.00, -146170.00, -2754030.00, 'loan_payment', '217', 1, '2026-02-13 12:44:18', '2026-02-13 12:44:18', 0),
(13884, '2025-12-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C081', 'Loan payment - Instalment #2 - Loan #LN-20260210-195307 - Reference: ', 650000.00, 0.00, 23040.00, 23040.00, 673040.00, 'loan_payment', '217', 1, '2026-02-13 12:44:18', '2026-02-13 12:44:18', 0),
(13885, '2025-12-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C081', 'Loan payment - Instalment #2 - Loan #LN-20260210-195307 - Reference: ', 547250.00, 0.00, 33000.00, 33000.00, 580250.00, 'loan_payment', '217', 1, '2026-02-13 12:44:18', '2026-02-13 12:44:18', 0),
(13886, '2026-01-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C081', 'Loan payment - Instalment #3 - Loan #LN-20260210-195307 - Reference: ', 2045210.00, 202210.00, 0.00, 202210.00, 2247420.00, 'loan_payment', '218', 1, '2026-02-13 12:44:35', '2026-02-13 12:44:35', 0),
(13887, '2026-01-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C081', 'Loan payment - Instalment #3 - Loan #LN-20260210-195307 - Reference: ', -1610210.00, 0.00, 153480.00, -153480.00, -1763690.00, 'loan_payment', '218', 1, '2026-02-13 12:44:35', '2026-02-13 12:44:35', 0),
(13888, '2026-01-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C081', 'Loan payment - Instalment #3 - Loan #LN-20260210-195307 - Reference: ', 435000.00, 0.00, 15730.00, 15730.00, 450730.00, 'loan_payment', '218', 1, '2026-02-13 12:44:35', '2026-02-13 12:44:35', 0),
(13889, '2026-01-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C081', 'Loan payment - Instalment #3 - Loan #LN-20260210-195307 - Reference: ', 599500.00, 0.00, 33000.00, 33000.00, 632500.00, 'loan_payment', '218', 1, '2026-02-13 12:44:35', '2026-02-13 12:44:35', 0),
(13890, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C082', 'Loan payment - Instalment #1 - Loan #LN-20260210-070626 - Reference: ', -1640839.74, 367210.00, 0.00, 367210.00, -1273629.74, 'loan_payment', '197', 1, '2026-02-13 12:55:49', '2026-02-13 12:55:49', 0),
(13891, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C082', 'Loan payment - Instalment #1 - Loan #LN-20260210-070626 - Reference: ', 1715030.74, 0.00, 317210.00, -317210.00, 1397820.74, 'loan_payment', '197', 1, '2026-02-13 12:55:49', '2026-02-13 12:55:49', 0),
(13892, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C082', 'Loan payment - Instalment #1 - Loan #LN-20260210-070626 - Reference: ', 646691.32, 0.00, 50000.00, 50000.00, 696691.32, 'loan_payment', '197', 1, '2026-02-13 12:55:49', '2026-02-13 12:55:49', 0),
(13893, '2025-12-24', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C051', 'Loan payment - Instalment #1 - Loan #LN-20260210-065730 - Reference: ', 2215010.00, 137910.00, 0.00, 137910.00, 2352920.00, 'loan_payment', '186', 1, '2026-02-13 13:03:17', '2026-02-13 13:03:17', 0),
(13894, '2025-12-24', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C051', 'Loan payment - Instalment #1 - Loan #LN-20260210-065730 - Reference: ', -1765010.00, 0.00, 102910.00, -102910.00, -1867920.00, 'loan_payment', '186', 1, '2026-02-13 13:03:17', '2026-02-13 13:03:17', 0),
(13895, '2025-12-24', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C051', 'Loan payment - Instalment #1 - Loan #LN-20260210-065730 - Reference: ', 450000.00, 0.00, 35000.00, 35000.00, 485000.00, 'loan_payment', '186', 1, '2026-02-13 13:03:17', '2026-02-13 13:03:17', 0),
(13896, '2025-12-04', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C051', 'Loan payment - Instalment #1 - Loan #LN-20260210-065730 - Reference: ', 3555840.00, 197408.28, 0.00, 197408.28, 3753248.28, 'loan_payment', '395', 1, '2026-02-13 13:07:28', '2026-02-13 13:07:28', 0),
(13897, '2025-12-04', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C051', 'Loan payment - Instalment #1 - Loan #LN-20260210-065730 - Reference: ', -2402050.00, 0.00, 162408.28, -162408.28, -2564458.28, 'loan_payment', '395', 1, '2026-02-13 13:07:28', '2026-02-13 13:07:28', 0),
(13898, '2025-12-04', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C051', 'Loan payment - Instalment #1 - Loan #LN-20260210-065730 - Reference: ', 606540.00, 0.00, 35000.00, 35000.00, 641540.00, 'loan_payment', '395', 1, '2026-02-13 13:07:28', '2026-02-13 13:07:28', 0),
(13899, '2026-01-30', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C051', 'Loan payment - Instalment #2 - Loan #LN-20260210-065730 - Reference: ', 360328.50, 238267.37, 0.00, 238267.37, 598595.87, 'loan_payment', '396', 1, '2026-02-13 13:08:45', '2026-02-13 13:08:45', 0),
(13900, '2026-01-30', 'Revenue', '4205', 'Penalty Charges', 'Penalty for Late Payment', 'C051', 'Loan payment - Instalment #2 - Loan #LN-20260210-065730 - Reference: ', 17158.50, 0.00, 2359.08, 2359.08, 19517.58, 'loan_payment', '396', 1, '2026-02-13 13:08:45', '2026-02-13 13:08:45', 0),
(13901, '2026-01-30', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C051', 'Loan payment - Instalment #2 - Loan #LN-20260210-065730 - Reference: ', -228030.00, 0.00, 170528.70, -170528.70, -398558.70, 'loan_payment', '396', 1, '2026-02-13 13:08:45', '2026-02-13 13:08:45', 0),
(13902, '2026-01-30', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C051', 'Loan payment - Instalment #2 - Loan #LN-20260210-065730 - Reference: ', 49140.00, 0.00, 26879.59, 26879.59, 76019.59, 'loan_payment', '396', 1, '2026-02-13 13:08:45', '2026-02-13 13:08:45', 0),
(13903, '2026-01-30', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C051', 'Loan payment - Instalment #2 - Loan #LN-20260210-065730 - Reference: ', 66000.00, 0.00, 38500.00, 38500.00, 104500.00, 'loan_payment', '396', 1, '2026-02-13 13:08:45', '2026-02-13 13:08:45', 0),
(13904, '2026-01-15', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C020', 'Loan payment - Instalment #1 - Loan #LN-20260210-065458 - Reference: ', 2215010.00, 367210.00, 0.00, 367210.00, 2582220.00, 'loan_payment', '183', 1, '2026-02-13 13:12:35', '2026-02-13 13:12:35', 0),
(13905, '2026-01-15', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C020', 'Loan payment - Instalment #1 - Loan #LN-20260210-065458 - Reference: ', -1765010.00, 0.00, 317210.00, -317210.00, -2082220.00, 'loan_payment', '183', 1, '2026-02-13 13:12:35', '2026-02-13 13:12:35', 0),
(13906, '2026-01-15', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C020', 'Loan payment - Instalment #1 - Loan #LN-20260210-065458 - Reference: ', 450000.00, 0.00, 50000.00, 50000.00, 500000.00, 'loan_payment', '183', 1, '2026-02-13 13:12:35', '2026-02-13 13:12:35', 0),
(13907, '2026-01-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C030', 'Loan payment - Instalment #1 - Loan #LN-20260210-065311 - Reference: ', 2247420.00, 2570460.00, 0.00, 2570460.00, 4817880.00, 'loan_payment', '180', 1, '2026-02-13 13:22:43', '2026-02-13 13:22:43', 0),
(13908, '2026-01-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C030', 'Loan payment - Instalment #1 - Loan #LN-20260210-065311 - Reference: ', -1763690.00, 0.00, 2220460.00, -2220460.00, -3984150.00, 'loan_payment', '180', 1, '2026-02-13 13:22:43', '2026-02-13 13:22:43', 0),
(13909, '2026-01-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C030', 'Loan payment - Instalment #1 - Loan #LN-20260210-065311 - Reference: ', 450730.00, 0.00, 350000.00, 350000.00, 800730.00, 'loan_payment', '180', 1, '2026-02-13 13:22:43', '2026-02-13 13:22:43', 0),
(13910, '2026-01-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C030', 'Loan payment - Instalment #2 - Loan #LN-20260210-065311 - Reference: ', 4817880.00, 2955460.00, 0.00, 2955460.00, 7773340.00, 'loan_payment', '181', 1, '2026-02-13 13:23:09', '2026-02-13 13:23:09', 0),
(13911, '2026-01-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C030', 'Loan payment - Instalment #2 - Loan #LN-20260210-065311 - Reference: ', -3984150.00, 0.00, 2331480.00, -2331480.00, -6315630.00, 'loan_payment', '181', 1, '2026-02-13 13:23:09', '2026-02-13 13:23:09', 0),
(13912, '2026-01-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C030', 'Loan payment - Instalment #2 - Loan #LN-20260210-065311 - Reference: ', 800730.00, 0.00, 238980.00, 238980.00, 1039710.00, 'loan_payment', '181', 1, '2026-02-13 13:23:09', '2026-02-13 13:23:09', 0),
(13913, '2026-01-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C030', 'Loan payment - Instalment #2 - Loan #LN-20260210-065311 - Reference: ', 632500.00, 0.00, 385000.00, 385000.00, 1017500.00, 'loan_payment', '181', 1, '2026-02-13 13:23:09', '2026-02-13 13:23:09', 0),
(13914, '2026-02-05', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C091', 'Loan payment - Instalment #1 - Loan #LN-20260210-065225 - Reference: ', 2618581.50, 660980.00, 0.00, 660980.00, 3279561.50, 'loan_payment', '177', 1, '2026-02-13 13:35:18', '2026-02-13 13:35:18', 0),
(13915, '2026-02-05', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C091', 'Loan payment - Instalment #1 - Loan #LN-20260210-065225 - Reference: ', -2147143.00, 0.00, 570980.00, -570980.00, -2718123.00, 'loan_payment', '177', 1, '2026-02-13 13:35:18', '2026-02-13 13:35:18', 0),
(13916, '2026-02-05', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C091', 'Loan payment - Instalment #1 - Loan #LN-20260210-065225 - Reference: ', 333280.00, 0.00, 90000.00, 90000.00, 423280.00, 'loan_payment', '177', 1, '2026-02-13 13:35:18', '2026-02-13 13:35:18', 0),
(13917, '2026-02-13', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C091', 'Loan payment - Instalment #2 - Loan #LN-20260210-065225 - Reference: ', 53111666.82, 364025.00, 0.00, 364025.00, 53475691.82, 'loan_payment', '178', 1, '2026-02-13 13:36:47', '2026-02-13 13:36:47', 0),
(13918, '2026-02-13', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C091', 'Loan payment - Instalment #2 - Loan #LN-20260210-065225 - Reference: ', -45354944.55, 0.00, 203575.00, -203575.00, -45558519.55, 'loan_payment', '178', 1, '2026-02-13 13:36:47', '2026-02-13 13:36:47', 0),
(13919, '2026-02-13', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C091', 'Loan payment - Instalment #2 - Loan #LN-20260210-065225 - Reference: ', 6651852.76, 0.00, 61450.00, 61450.00, 6713302.76, 'loan_payment', '178', 1, '2026-02-13 13:36:47', '2026-02-13 13:36:47', 0),
(13920, '2026-02-13', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C091', 'Loan payment - Instalment #2 - Loan #LN-20260210-065225 - Reference: ', 3264077.56, 0.00, 99000.00, 99000.00, 3363077.56, 'loan_payment', '178', 1, '2026-02-13 13:36:47', '2026-02-13 13:36:47', 0),
(13921, '2025-10-03', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C007', 'Loan payment - Instalment #1 - Loan #LN-20260210-064151 - Reference: ', 0.00, 580830.00, 0.00, 580830.00, 580830.00, 'loan_payment', '175', 1, '2026-02-13 14:12:42', '2026-02-13 14:12:42', 0),
(13922, '2025-10-03', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C007', 'Loan payment - Instalment #1 - Loan #LN-20260210-064151 - Reference: ', 0.00, 0.00, 526830.00, -526830.00, -526830.00, 'loan_payment', '175', 1, '2026-02-13 14:12:42', '2026-02-13 14:12:42', 0),
(13923, '2025-10-03', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C007', 'Loan payment - Instalment #1 - Loan #LN-20260210-064151 - Reference: ', 0.00, 0.00, 54000.00, 54000.00, 54000.00, 'loan_payment', '175', 1, '2026-02-13 14:12:42', '2026-02-13 14:12:42', 0),
(13924, '2025-11-08', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C007', 'Loan payment - Instalment #1 - Loan #LN-20260210-064151 - Reference: ', 1235430.00, 580829.27, 0.00, 580829.27, 1816259.27, 'loan_payment', '399', 1, '2026-02-13 14:16:22', '2026-02-13 14:16:22', 0),
(13925, '2025-11-08', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C007', 'Loan payment - Instalment #1 - Loan #LN-20260210-064151 - Reference: ', -962930.00, 0.00, 526829.27, -526829.27, -1489759.27, 'loan_payment', '399', 1, '2026-02-13 14:16:22', '2026-02-13 14:16:22', 0),
(13926, '2025-11-08', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C007', 'Loan payment - Instalment #1 - Loan #LN-20260210-064151 - Reference: ', 272500.00, 0.00, 54000.00, 54000.00, 326500.00, 'loan_payment', '399', 1, '2026-02-13 14:16:22', '2026-02-13 14:16:22', 0),
(13927, '2025-12-08', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C007', 'Loan payment - Instalment #2 - Loan #LN-20260210-064151 - Reference: ', 3753248.28, 640229.27, 0.00, 640229.27, 4393477.55, 'loan_payment', '400', 1, '2026-02-13 14:16:42', '2026-02-13 14:16:42', 0),
(13928, '2025-12-08', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C007', 'Loan payment - Instalment #2 - Loan #LN-20260210-064151 - Reference: ', -2564458.28, 0.00, 553170.73, -553170.73, -3117629.01, 'loan_payment', '400', 1, '2026-02-13 14:16:42', '2026-02-13 14:16:42', 0),
(13929, '2025-12-08', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C007', 'Loan payment - Instalment #2 - Loan #LN-20260210-064151 - Reference: ', 641540.00, 0.00, 27658.54, 27658.54, 669198.54, 'loan_payment', '400', 1, '2026-02-13 14:16:42', '2026-02-13 14:16:42', 0),
(13930, '2025-12-08', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C007', 'Loan payment - Instalment #2 - Loan #LN-20260210-064151 - Reference: ', 547250.00, 0.00, 59400.00, 59400.00, 606650.00, 'loan_payment', '400', 1, '2026-02-13 14:16:42', '2026-02-13 14:16:42', 0),
(13931, '2026-01-22', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C023', 'Loan payment - Instalment #1 - Loan #LN-20260210-051232 - Reference: ', 4584417.00, 1101630.00, 0.00, 1101630.00, 5686047.00, 'loan_payment', '172', 1, '2026-02-13 14:18:38', '2026-02-13 14:18:38', 0),
(13932, '2026-01-22', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C023', 'Loan payment - Instalment #1 - Loan #LN-20260210-051232 - Reference: ', -2959938.93, 0.00, 951630.00, -951630.00, -3911568.93, 'loan_payment', '172', 1, '2026-02-13 14:18:38', '2026-02-13 14:18:38', 0),
(13933, '2026-01-22', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C023', 'Loan payment - Instalment #1 - Loan #LN-20260210-051232 - Reference: ', 1031860.00, 0.00, 150000.00, 150000.00, 1181860.00, 'loan_payment', '172', 1, '2026-02-13 14:18:38', '2026-02-13 14:18:38', 0),
(13934, '2026-01-01', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C009', 'Loan payment - Instalment #1 - Loan #LN-20260210-045901 - Reference: ', 2376350.00, 110162.57, 0.00, 110162.57, 2486512.57, 'loan_payment', '401', 1, '2026-02-13 15:01:53', '2026-02-13 15:01:53', 0),
(13935, '2026-01-01', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C009', 'Loan payment - Instalment #1 - Loan #LN-20260210-045901 - Reference: ', -1911350.00, 0.00, 95162.57, -95162.57, -2006512.57, 'loan_payment', '401', 1, '2026-02-13 15:01:53', '2026-02-13 15:01:53', 0),
(13936, '2026-01-01', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C009', 'Loan payment - Instalment #1 - Loan #LN-20260210-045901 - Reference: ', 465000.00, 0.00, 15000.00, 15000.00, 480000.00, 'loan_payment', '401', 1, '2026-02-13 15:01:53', '2026-02-13 15:01:53', 0),
(13937, '2026-02-01', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C009', 'Loan payment - Instalment #2 - Loan #LN-20260210-045901 - Reference: ', 538168.50, 110162.57, 0.00, 110162.57, 648331.07, 'loan_payment', '402', 1, '2026-02-13 15:02:19', '2026-02-13 15:02:19', 0),
(13938, '2026-02-01', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C009', 'Loan payment - Instalment #2 - Loan #LN-20260210-045901 - Reference: ', -381690.00, 0.00, 99920.70, -99920.70, -481610.70, 'loan_payment', '402', 1, '2026-02-13 15:02:19', '2026-02-13 15:02:19', 0),
(13939, '2026-02-01', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C009', 'Loan payment - Instalment #2 - Loan #LN-20260210-045901 - Reference: ', 56820.00, 0.00, 10241.87, 10241.87, 67061.87, 'loan_payment', '402', 1, '2026-02-13 15:02:19', '2026-02-13 15:02:19', 0),
(13940, '2025-12-18', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', '057', 'Loan payment - Instalment #1 - Loan #LN-20260210-044355 - Reference: ', 3460070.00, 2820120.00, 0.00, 2820120.00, 6280190.00, 'loan_payment', '132', 1, '2026-02-13 15:04:49', '2026-02-13 15:04:49', 0),
(13941, '2025-12-18', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', '057', 'Loan payment - Instalment #1 - Loan #LN-20260210-044355 - Reference: ', -2754030.00, 0.00, 2320120.00, -2320120.00, -5074150.00, 'loan_payment', '132', 1, '2026-02-13 15:04:49', '2026-02-13 15:04:49', 0),
(13942, '2025-12-18', 'Revenue', '4101', 'Interest Income', 'Interest Income', '057', 'Loan payment - Instalment #1 - Loan #LN-20260210-044355 - Reference: ', 673040.00, 0.00, 500000.00, 500000.00, 1173040.00, 'loan_payment', '132', 1, '2026-02-13 15:04:49', '2026-02-13 15:04:49', 0),
(13943, '2026-01-18', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', '057', 'Loan payment - Instalment #2 - Loan #LN-20260210-044355 - Reference: ', 7773340.00, 3370110.00, 0.00, 3370110.00, 11143450.00, 'loan_payment', '133', 1, '2026-02-13 15:05:08', '2026-02-13 15:05:08', 0),
(13944, '2026-01-18', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', '057', 'Loan payment - Instalment #2 - Loan #LN-20260210-044355 - Reference: ', -6315630.00, 0.00, 2436120.00, -2436120.00, -8751750.00, 'loan_payment', '133', 1, '2026-02-13 15:05:08', '2026-02-13 15:05:08', 0),
(13945, '2026-01-18', 'Revenue', '4101', 'Interest Income', 'Interest Income', '057', 'Loan payment - Instalment #2 - Loan #LN-20260210-044355 - Reference: ', 1039710.00, 0.00, 383990.00, 383990.00, 1423700.00, 'loan_payment', '133', 1, '2026-02-13 15:05:08', '2026-02-13 15:05:08', 0),
(13946, '2026-01-18', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', '057', 'Loan payment - Instalment #2 - Loan #LN-20260210-044355 - Reference: ', 1017500.00, 0.00, 550000.00, 550000.00, 1567500.00, 'loan_payment', '133', 1, '2026-02-13 15:05:08', '2026-02-13 15:05:08', 0),
(13947, '2025-12-13', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C67', 'Loan payment - Instalment #1 - Loan #LN-20260213-155133 - Reference: ', 1847800.00, 183600.00, 0.00, 183600.00, 2031400.00, 'loan_payment', '404', 1, '2026-02-13 16:07:03', '2026-02-13 16:07:03', 0);
INSERT INTO `ledger` (`ledger_id`, `transaction_date`, `class`, `account_code`, `account_name`, `particular`, `voucher_number`, `narration`, `beginning_balance`, `debit_amount`, `credit_amount`, `movement`, `ending_balance`, `reference_type`, `reference_id`, `created_by`, `created_at`, `updated_at`, `sequence_number`) VALUES
(13948, '2025-12-13', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C67', 'Loan payment - Instalment #1 - Loan #LN-20260213-155133 - Reference: ', -1447800.00, 0.00, 158600.00, -158600.00, -1606400.00, 'loan_payment', '404', 1, '2026-02-13 16:07:03', '2026-02-13 16:07:03', 0),
(13949, '2025-12-13', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C67', 'Loan payment - Instalment #1 - Loan #LN-20260213-155133 - Reference: ', 400000.00, 0.00, 25000.00, 25000.00, 425000.00, 'loan_payment', '404', 1, '2026-02-13 16:07:03', '2026-02-13 16:07:03', 0),
(13950, '2026-01-23', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C67', 'Loan payment - Instalment #2 - Loan #LN-20260213-155133 - Reference: ', 5686047.00, 214970.17, 0.00, 214970.17, 5901017.17, 'loan_payment', '405', 1, '2026-02-13 16:08:32', '2026-02-13 16:08:32', 0),
(13951, '2026-01-23', 'Revenue', '4205', 'Penalty Charges', 'Penalty for Late Payment', 'C67', 'Loan payment - Instalment #2 - Loan #LN-20260213-155133 - Reference: ', 15121.07, 0.00, 3870.17, 3870.17, 18991.24, 'loan_payment', '405', 1, '2026-02-13 16:08:32', '2026-02-13 16:08:32', 0),
(13952, '2026-01-23', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C67', 'Loan payment - Instalment #2 - Loan #LN-20260213-155133 - Reference: ', -3911568.93, 0.00, 166530.00, -166530.00, -4078098.93, 'loan_payment', '405', 1, '2026-02-13 16:08:32', '2026-02-13 16:08:32', 0),
(13953, '2026-01-23', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C67', 'Loan payment - Instalment #2 - Loan #LN-20260213-155133 - Reference: ', 1181860.00, 0.00, 17070.00, 17070.00, 1198930.00, 'loan_payment', '405', 1, '2026-02-13 16:08:32', '2026-02-13 16:08:32', 0),
(13954, '2026-01-23', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C67', 'Loan payment - Instalment #2 - Loan #LN-20260213-155133 - Reference: ', 1072497.00, 0.00, 27500.00, 27500.00, 1099997.00, 'loan_payment', '405', 1, '2026-02-13 16:08:32', '2026-02-13 16:08:32', 0),
(13955, '2026-01-23', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C043', 'Loan payment - Instalment #1 - Loan #LN-20260210-051123 - Reference: ', 5901017.17, 291880.35, 0.00, 291880.35, 6192897.52, 'loan_payment', '168', 1, '2026-02-13 17:16:38', '2026-02-13 17:16:38', 0),
(13956, '2026-01-23', 'Revenue', '4205', 'Penalty Charges', 'Penalty for Late Payment', 'C043', 'Loan payment - Instalment #1 - Loan #LN-20260210-051123 - Reference: ', 18991.24, 0.00, 9870.35, 9870.35, 28861.59, 'loan_payment', '168', 1, '2026-02-13 17:16:38', '2026-02-13 17:16:38', 0),
(13957, '2026-01-23', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C043', 'Loan payment - Instalment #1 - Loan #LN-20260210-051123 - Reference: ', -4078098.93, 0.00, 232010.00, -232010.00, -4310108.93, 'loan_payment', '168', 1, '2026-02-13 17:16:38', '2026-02-13 17:16:38', 0),
(13958, '2026-01-23', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C043', 'Loan payment - Instalment #1 - Loan #LN-20260210-051123 - Reference: ', 1198930.00, 0.00, 50000.00, 50000.00, 1248930.00, 'loan_payment', '168', 1, '2026-02-13 17:16:38', '2026-02-13 17:16:38', 0),
(13959, '2026-02-15', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C59', 'Loan payment - Instalment #1 - Loan #LN-20260210-204627 - Reference: ', 53475691.82, 1575000.00, 0.00, 1575000.00, 55050691.82, 'loan_payment', '319', 1, '2026-02-16 07:36:05', '2026-02-16 07:36:05', 0),
(13960, '2026-02-15', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C59', 'Loan payment - Instalment #1 - Loan #LN-20260210-204627 - Reference: ', -45558519.55, 0.00, 1425000.00, -1425000.00, -46983519.55, 'loan_payment', '319', 1, '2026-02-16 07:36:05', '2026-02-16 07:36:05', 0),
(13961, '2026-02-15', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C59', 'Loan payment - Instalment #1 - Loan #LN-20260210-204627 - Reference: ', 6713302.76, 0.00, 150000.00, 150000.00, 6863302.76, 'loan_payment', '319', 1, '2026-02-16 07:36:05', '2026-02-16 07:36:05', 0),
(13962, '2026-02-14', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C006', 'Loan payment - Instalment #1 - Loan #LN-20260210-050013 - Reference: ', 53475691.82, 560000.00, 0.00, 560000.00, 54035691.82, 'loan_payment', '159', 1, '2026-02-16 09:42:02', '2026-02-16 09:42:02', 0),
(13963, '2026-02-14', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C006', 'Loan payment - Instalment #1 - Loan #LN-20260210-050013 - Reference: ', -45558519.55, 0.00, 460000.00, -460000.00, -46018519.55, 'loan_payment', '159', 1, '2026-02-16 09:42:02', '2026-02-16 09:42:02', 0),
(13964, '2026-02-14', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C006', 'Loan payment - Instalment #1 - Loan #LN-20260210-050013 - Reference: ', 6713302.76, 0.00, 100000.00, 100000.00, 6813302.76, 'loan_payment', '159', 1, '2026-02-16 09:42:02', '2026-02-16 09:42:02', 0),
(13965, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', 55050691.82, 2099990.00, 0.00, 2099990.00, 57150681.82, 'loan_prepayment', '407', 1, '2026-02-17 11:04:58', '2026-02-17 11:04:58', 0),
(13966, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', -46983519.55, 0.00, 1999990.00, -1999990.00, -48983509.55, 'loan_prepayment', '407', 1, '2026-02-17 11:04:58', '2026-02-17 11:04:58', 0),
(13967, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income - Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', 6863302.76, 0.00, 100000.00, 100000.00, 6963302.76, 'loan_prepayment', '407', 1, '2026-02-17 11:04:58', '2026-02-17 11:04:58', 0),
(13968, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', 57150681.82, 195300.00, 0.00, 195300.00, 57345981.82, 'loan_prepayment', '408', 1, '2026-02-17 11:20:51', '2026-02-17 11:20:51', 0),
(13969, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income - Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', 6963302.76, 0.00, 85300.00, 85300.00, 7048602.76, 'loan_prepayment', '408', 1, '2026-02-17 11:20:51', '2026-02-17 11:20:51', 0),
(13970, '2026-02-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee - Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', 3363077.56, 0.00, 110000.00, 110000.00, 3473077.56, 'loan_prepayment', '408', 1, '2026-02-17 11:20:51', '2026-02-17 11:20:51', 0),
(13971, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', 57345981.82, 618900.00, 0.00, 618900.00, 57964881.82, 'loan_prepayment', '409', 1, '2026-02-17 11:21:07', '2026-02-17 11:21:07', 0),
(13972, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', -48983509.55, 0.00, 1073050.00, -1073050.00, -50056559.55, 'loan_prepayment', '409', 1, '2026-02-17 11:21:07', '2026-02-17 11:21:07', 0),
(13973, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income - Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', 7048602.76, 0.00, 178900.00, 178900.00, 7227502.76, 'loan_prepayment', '409', 1, '2026-02-17 11:21:07', '2026-02-17 11:21:07', 0),
(13974, '2026-02-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee - Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-110334 - Reference: ', 3473077.56, 0.00, 440000.00, 440000.00, 3913077.56, 'loan_prepayment', '409', 1, '2026-02-17 11:21:07', '2026-02-17 11:21:07', 0),
(13975, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-112126 - Reference: ', 57964881.82, 3074490.00, 0.00, 3074490.00, 61039371.82, 'loan_prepayment', '413', 1, '2026-02-17 11:24:39', '2026-02-17 11:24:39', 0),
(13976, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-112126 - Reference: ', -50056559.55, 0.00, 2110000.00, -2110000.00, -52166559.55, 'loan_prepayment', '413', 1, '2026-02-17 11:24:39', '2026-02-17 11:24:39', 0),
(13977, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income - Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-112126 - Reference: ', 7227502.76, 0.00, 384240.00, 384240.00, 7611742.76, 'loan_prepayment', '413', 1, '2026-02-17 11:24:39', '2026-02-17 11:24:39', 0),
(13978, '2026-02-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee - Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-112126 - Reference: ', 3913077.56, 0.00, 580250.00, 580250.00, 4493327.56, 'loan_prepayment', '413', 1, '2026-02-17 11:24:39', '2026-02-17 11:24:39', 0),
(13979, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-135109 - Reference: ', 61039371.82, 98510.00, 0.00, 98510.00, 61137881.82, 'loan_payment', '423', 1, '2026-02-17 13:58:25', '2026-02-17 13:58:25', 0),
(13980, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-135109 - Reference: ', -52166559.55, 0.00, 73510.00, -73510.00, -52240069.55, 'loan_payment', '423', 1, '2026-02-17 13:58:25', '2026-02-17 13:58:25', 0),
(13981, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-135109 - Reference: ', 7611742.76, 0.00, 25000.00, 25000.00, 7636742.76, 'loan_payment', '423', 1, '2026-02-17 13:58:25', '2026-02-17 13:58:25', 0),
(13982, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260217-135109 - Reference: ', 61137881.82, 98500.00, 0.00, 98500.00, 61236381.82, 'loan_payment', '424', 1, '2026-02-17 13:58:37', '2026-02-17 13:58:37', 0),
(13983, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260217-135109 - Reference: ', -52240069.55, 0.00, 77180.00, -77180.00, -52317249.55, 'loan_payment', '424', 1, '2026-02-17 13:58:37', '2026-02-17 13:58:37', 0),
(13984, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #2 - Loan #LN-20260217-135109 - Reference: ', 7636742.76, 0.00, 21320.00, 21320.00, 7658062.76, 'loan_payment', '424', 1, '2026-02-17 13:58:37', '2026-02-17 13:58:37', 0),
(13985, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-135109 - Reference: ', 61236381.82, 366780.00, 0.00, 366780.00, 61603161.82, 'loan_prepayment', '425', 1, '2026-02-17 15:03:17', '2026-02-17 15:03:17', 0),
(13986, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-135109 - Reference: ', 61603161.82, 98508.73, 0.00, 98508.73, 61701670.55, 'loan_payment', '429', 1, '2026-02-17 15:03:55', '2026-02-17 15:03:55', 0),
(13987, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-135109 - Reference: ', -52317249.55, 0.00, 73508.73, -73508.73, -52390758.28, 'loan_payment', '429', 1, '2026-02-17 15:03:55', '2026-02-17 15:03:55', 0),
(13988, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-135109 - Reference: ', 7658062.76, 0.00, 25000.00, 25000.00, 7683062.76, 'loan_payment', '429', 1, '2026-02-17 15:03:55', '2026-02-17 15:03:55', 0),
(13989, '2026-02-17', 'Assets', '1101', 'Cash on Hand', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-135109 - Reference: ', 1778840.32, 475315.83, 0.00, 475315.83, 2254156.15, 'loan_prepayment', '430', 1, '2026-02-17 15:06:45', '2026-02-17 15:06:45', 0),
(13990, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-135109 - Reference:  (Future interest waived: 44,728, Future fees waived: 110,000)', -52390758.28, 0.00, 426491.27, -426491.27, -52817249.55, 'loan_prepayment', '430', 1, '2026-02-17 15:06:45', '2026-02-17 15:06:45', 0),
(13991, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income - Current Instalment Only', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-135109 - Reference:  (Future interest waived: 44,728, Future fees waived: 110,000)', 7683062.76, 0.00, 21324.56, 21324.56, 7704387.32, 'loan_prepayment', '430', 1, '2026-02-17 15:06:45', '2026-02-17 15:06:45', 0),
(13992, '2026-02-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee - Current Instalment Only', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-135109 - Reference:  (Future interest waived: 44,728, Future fees waived: 110,000)', 4493327.56, 0.00, 27500.00, 27500.00, 4520827.56, 'loan_prepayment', '430', 1, '2026-02-17 15:06:45', '2026-02-17 15:06:45', 0),
(13993, '2026-02-17', 'Assets', '1101', 'Cash on Hand', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-152244 - Reference: ', 2254156.15, 98510.00, 0.00, 98510.00, 2352666.15, 'loan_payment', '435', 1, '2026-02-17 15:24:02', '2026-02-17 15:24:02', 0),
(13994, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-152244 - Reference: ', -52817249.55, 0.00, 73510.00, -73510.00, -52890759.55, 'loan_payment', '435', 1, '2026-02-17 15:24:02', '2026-02-17 15:24:02', 0),
(13995, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-152244 - Reference: ', 7704387.32, 0.00, 25000.00, 25000.00, 7729387.32, 'loan_payment', '435', 1, '2026-02-17 15:24:02', '2026-02-17 15:24:02', 0),
(13996, '2026-02-17', 'Assets', '1101', 'Cash on Hand', 'Loan Payment Received', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-152537 - Reference: ', 2352666.15, 394030.00, 0.00, 394030.00, 2746696.15, 'loan_payment', '441', 1, '2026-02-17 15:26:32', '2026-02-17 15:26:32', 0),
(13997, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-152537 - Reference: ', -52890759.55, 0.00, 294030.00, -294030.00, -53184789.55, 'loan_payment', '441', 1, '2026-02-17 15:26:32', '2026-02-17 15:26:32', 0),
(13998, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'cbf100', 'Loan payment - Instalment #1 - Loan #LN-20260217-152537 - Reference: ', 7729387.32, 0.00, 100000.00, 100000.00, 7829387.32, 'loan_payment', '441', 1, '2026-02-17 15:26:32', '2026-02-17 15:26:32', 0),
(13999, '2026-02-17', 'Assets', '1101', 'Cash on Hand', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-152537 - Reference:  (Future Interest Waived: 178,900, Future Fees Waived: 440,000)', 2746696.15, 1901260.00, 0.00, 1901260.00, 4647956.15, 'loan_prepayment', '442', 1, '2026-02-17 15:26:49', '2026-02-17 15:26:49', 0),
(14000, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-152537 - Reference:  (Future Interest Waived: 178,900, Future Fees Waived: 440,000)', -53184789.55, 0.00, 1705960.00, -1705960.00, -54890749.55, 'loan_prepayment', '442', 1, '2026-02-17 15:26:49', '2026-02-17 15:26:49', 0),
(14001, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income - Current Instalment Only', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-152537 - Reference:  (Future Interest Waived: 178,900, Future Fees Waived: 440,000)', 7829387.32, 0.00, 85300.00, 85300.00, 7914687.32, 'loan_prepayment', '442', 1, '2026-02-17 15:26:49', '2026-02-17 15:26:49', 0),
(14002, '2026-02-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee - Current Instalment Only', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-152537 - Reference:  (Future Interest Waived: 178,900, Future Fees Waived: 440,000)', 4520827.56, 0.00, 110000.00, 110000.00, 4630827.56, 'loan_prepayment', '442', 1, '2026-02-17 15:26:49', '2026-02-17 15:26:49', 0),
(14003, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-154350 - Reference: ', 61701670.55, 525000.00, 0.00, 525000.00, 62226670.55, 'loan_prepayment', '447', 1, '2026-02-17 15:48:34', '2026-02-17 15:48:34', 0),
(14004, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-154350 - Reference:  | Future interest waived: 66,050, Future fees waived: 137,500', -54890749.55, 0.00, 500000.00, -500000.00, -55390749.55, 'loan_prepayment', '447', 1, '2026-02-17 15:48:34', '2026-02-17 15:48:34', 0),
(14005, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income - Current Instalment Only (Future Waived)', 'cbf100', 'PREPAYMENT - Loan #LN-20260217-154350 - Reference:  | Future interest waived: 66,050, Future fees waived: 137,500', 7914687.32, 0.00, 25000.00, 25000.00, 7939687.32, 'loan_prepayment', '447', 1, '2026-02-17 15:48:34', '2026-02-17 15:48:34', 0),
(14006, '2026-02-18', 'Assets', '1102', 'Bank Account', 'Loan Prepayment Received', 'cbf100', 'PREPAYMENT - Loan #LN-20260218-082535 - Reference: ', 62226670.55, 2099990.00, 0.00, 2099990.00, 64326660.55, 'loan_prepayment', '453', 1, '2026-02-18 08:41:48', '2026-02-18 08:41:48', 0),
(14007, '2026-02-18', 'Assets', '1201', 'Loans to Customers', 'Principal Prepayment', 'cbf100', 'PREPAYMENT - Loan #LN-20260218-082535 - Reference:  | Future interest waived: 264,200, Future fees waived: 550,000', -55390749.55, 0.00, 1999990.00, -1999990.00, -57390739.55, 'loan_prepayment', '453', 1, '2026-02-18 08:41:48', '2026-02-18 08:41:48', 0),
(14008, '2026-02-18', 'Revenue', '4101', 'Interest Income', 'Interest Income - Current Instalment Only (Future Waived)', 'cbf100', 'PREPAYMENT - Loan #LN-20260218-082535 - Reference:  | Future interest waived: 264,200, Future fees waived: 550,000', 7939687.32, 0.00, 100000.00, 100000.00, 8039687.32, 'loan_prepayment', '453', 1, '2026-02-18 08:41:48', '2026-02-18 08:41:48', 0),
(14009, '2026-02-09', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C018', 'Loan payment - Instalment #1 - Loan #LN-20260218-105127 - Reference: ', -1273629.74, 734420.00, 0.00, 734420.00, -539209.74, 'loan_payment', '459', 1, '2026-02-18 10:55:26', '2026-02-18 10:55:26', 0),
(14010, '2026-02-09', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C018', 'Loan payment - Instalment #1 - Loan #LN-20260218-105127 - Reference: ', 1397820.74, 0.00, 634420.00, -634420.00, 763400.74, 'loan_payment', '459', 1, '2026-02-18 10:55:26', '2026-02-18 10:55:26', 0),
(14011, '2026-02-09', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C018', 'Loan payment - Instalment #1 - Loan #LN-20260218-105127 - Reference: ', 696691.32, 0.00, 100000.00, 100000.00, 796691.32, 'loan_payment', '459', 1, '2026-02-18 10:55:26', '2026-02-18 10:55:26', 0),
(14012, '2026-02-17', 'Assets', '1102', 'Bank Account', 'Loan Payment Received', 'C033', 'Loan payment - Instalment #2 - Loan #LN-20260210-204014 - Reference: ', 62226670.55, 235910.00, 0.00, 235910.00, 62462580.55, 'loan_payment', '299', 1, '2026-02-18 13:21:03', '2026-02-18 13:21:03', 0),
(14013, '2026-02-17', 'Assets', '1201', 'Loans to Customers', 'Principal Repayment', 'C033', 'Loan payment - Instalment #2 - Loan #LN-20260210-204014 - Reference: ', -55390749.55, 0.00, 170530.00, -170530.00, -55561279.55, 'loan_payment', '299', 1, '2026-02-18 13:21:03', '2026-02-18 13:21:03', 0),
(14014, '2026-02-17', 'Revenue', '4101', 'Interest Income', 'Interest Income', 'C033', 'Loan payment - Instalment #2 - Loan #LN-20260210-204014 - Reference: ', 7939687.32, 0.00, 26880.00, 26880.00, 7966567.32, 'loan_payment', '299', 1, '2026-02-18 13:21:03', '2026-02-18 13:21:03', 0),
(14015, '2026-02-17', 'Fee Income', '4201', 'Management Fee Income', 'Management Fee', 'C033', 'Loan payment - Instalment #2 - Loan #LN-20260210-204014 - Reference: ', 4630827.56, 0.00, 38500.00, 38500.00, 4669327.56, 'loan_payment', '299', 1, '2026-02-18 13:21:03', '2026-02-18 13:21:03', 0);

-- --------------------------------------------------------

--
-- Table structure for table `loan_application_fees`
--

CREATE TABLE `loan_application_fees` (
  `id` int(11) NOT NULL,
  `loan_number` varchar(50) DEFAULT NULL,
  `applicant_name` varchar(255) DEFAULT NULL,
  `application_date` date DEFAULT NULL,
  `fee_amount` decimal(15,2) DEFAULT NULL,
  `payment_status` enum('Paid','Pending','Refunded') DEFAULT 'Pending',
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `loan_instalments`
--

CREATE TABLE `loan_instalments` (
  `instalment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `loan_number` varchar(50) NOT NULL,
  `instalment_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Principal balance at start of period',
  `closing_balance` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Principal balance at end of period',
  `principal_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Principal portion of payment',
  `interest_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Interest portion of payment',
  `management_fee` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Management fee for this instalment',
  `total_payment` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Total payment required (principal + interest + mgmt fee)',
  `paid_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount actually paid',
  `principal_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `interest_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `management_fee_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `balance_remaining` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Unpaid portion of this instalment',
  `days_overdue` int(11) NOT NULL DEFAULT '0',
  `penalty_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `penalty_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('Pending','Partially Paid','Fully Paid','Overdue') NOT NULL DEFAULT 'Pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `overdue_ledger_recorded` tinyint(1) DEFAULT '0',
  `ninety_day_recorded` tinyint(1) DEFAULT '0',
  `monitoring_fee_net` decimal(15,2) DEFAULT '0.00',
  `monitoring_fee_vat` decimal(15,2) DEFAULT '0.00',
  `monitoring_fee_total` decimal(15,2) DEFAULT '0.00',
  `provision_calculated` tinyint(1) DEFAULT '0',
  `provision_amount` decimal(15,2) DEFAULT '0.00',
  `provision_date` date DEFAULT NULL,
  `suspension_recorded` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `loan_instalments`
--

INSERT INTO `loan_instalments` (`instalment_id`, `loan_id`, `loan_number`, `instalment_number`, `due_date`, `payment_date`, `opening_balance`, `closing_balance`, `principal_amount`, `interest_amount`, `management_fee`, `total_payment`, `paid_amount`, `principal_paid`, `interest_paid`, `management_fee_paid`, `balance_remaining`, `days_overdue`, `penalty_amount`, `penalty_paid`, `status`, `created_by`, `created_at`, `updated_at`, `overdue_ledger_recorded`, `ninety_day_recorded`, `monitoring_fee_net`, `monitoring_fee_vat`, `monitoring_fee_total`, `provision_calculated`, `provision_amount`, `provision_date`, `suspension_recorded`) VALUES
(129, 26, 'LN-20260210-044117', 1, '2026-02-27', NULL, 10000000.00, 6827910.00, 3172090.00, 500000.00, 0.00, 3672090.00, 0.00, 0.00, 0.00, 0.00, 3672090.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:42:52', '2026-02-10 04:42:52', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(130, 26, 'LN-20260210-044117', 2, '2026-03-27', NULL, 6827910.00, 3497220.00, 3330690.00, 341400.00, 550000.00, 4222090.00, 0.00, 0.00, 0.00, 0.00, 4222090.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:42:52', '2026-02-10 04:42:52', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(131, 26, 'LN-20260210-044117', 3, '2026-04-27', NULL, 3497220.00, 0.00, 3497220.00, 174860.00, 550000.00, 4222080.00, 0.00, 0.00, 0.00, 0.00, 4222080.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:42:52', '2026-02-10 04:42:52', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(132, 27, 'LN-20260210-044355', 1, '2025-12-18', '2025-12-18', 10000000.00, 7679880.00, 2320120.00, 500000.00, 0.00, 2820120.00, 2820120.00, 2320120.00, 500000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 04:45:07', '2026-02-13 15:04:49', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(133, 27, 'LN-20260210-044355', 2, '2026-01-18', '2026-01-18', 7679880.00, 5243760.00, 2436120.00, 383990.00, 550000.00, 3370110.00, 3370110.00, 2436120.00, 383990.00, 550000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 04:45:07', '2026-02-13 15:05:08', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(134, 27, 'LN-20260210-044355', 3, '2026-02-18', NULL, 5243760.00, 2685830.00, 2557930.00, 262190.00, 550000.00, 3370120.00, 0.00, 0.00, 0.00, 0.00, 3370120.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:45:07', '2026-02-10 04:45:07', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(135, 27, 'LN-20260210-044355', 4, '2026-03-18', NULL, 2685830.00, 0.00, 2685830.00, 134290.00, 550000.00, 3370120.00, 0.00, 0.00, 0.00, 0.00, 3370120.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:45:07', '2026-02-10 04:45:07', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(143, 30, 'LN-20260210-045246', 1, '2026-02-21', NULL, 1700000.00, 1305580.00, 394420.00, 85000.00, 0.00, 479420.00, 0.00, 0.00, 0.00, 0.00, 479420.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:52:57', '2026-02-10 04:52:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(144, 30, 'LN-20260210-045246', 2, '2026-03-21', NULL, 1305580.00, 891440.00, 414140.00, 65280.00, 93500.00, 572920.00, 0.00, 0.00, 0.00, 0.00, 572920.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:52:57', '2026-02-10 04:52:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(145, 30, 'LN-20260210-045246', 3, '2026-04-21', NULL, 891440.00, 456590.00, 434850.00, 44570.00, 93500.00, 572920.00, 0.00, 0.00, 0.00, 0.00, 572920.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:52:57', '2026-02-10 04:52:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(146, 30, 'LN-20260210-045246', 4, '2026-05-21', NULL, 456590.00, 0.00, 456590.00, 22830.00, 93500.00, 572920.00, 0.00, 0.00, 0.00, 0.00, 572920.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:52:57', '2026-02-10 04:52:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(150, 32, 'LN-20260210-045738', 1, '2026-02-20', NULL, 3450000.00, 2942790.00, 507210.00, 172500.00, 0.00, 679710.00, 0.00, 0.00, 0.00, 0.00, 679710.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:58:57', '2026-02-10 04:58:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(151, 32, 'LN-20260210-045738', 2, '2026-03-20', NULL, 2942790.00, 2410220.00, 532570.00, 147140.00, 189750.00, 869460.00, 0.00, 0.00, 0.00, 0.00, 869460.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:58:57', '2026-02-10 04:58:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(152, 32, 'LN-20260210-045738', 3, '2026-04-20', NULL, 2410220.00, 1851020.00, 559200.00, 120510.00, 189750.00, 869460.00, 0.00, 0.00, 0.00, 0.00, 869460.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:58:57', '2026-02-10 04:58:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(153, 32, 'LN-20260210-045738', 4, '2026-05-20', NULL, 1851020.00, 1263860.00, 587160.00, 92550.00, 189750.00, 869460.00, 0.00, 0.00, 0.00, 0.00, 869460.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:58:57', '2026-02-10 04:58:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(154, 32, 'LN-20260210-045738', 5, '2026-06-20', NULL, 1263860.00, 647340.00, 616520.00, 63190.00, 189750.00, 869460.00, 0.00, 0.00, 0.00, 0.00, 869460.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:58:57', '2026-02-10 04:58:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(155, 32, 'LN-20260210-045738', 6, '2026-07-20', NULL, 647340.00, 0.00, 647340.00, 32370.00, 189750.00, 869460.00, 0.00, 0.00, 0.00, 0.00, 869460.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 04:58:57', '2026-02-10 04:58:57', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(159, 34, 'LN-20260210-050013', 1, '2026-02-09', '2026-02-14', 2000000.00, 1535980.00, 464020.00, 100000.00, 0.00, 564020.00, 560000.00, 460000.00, 100000.00, 0.00, 4020.00, 0, 0.00, 0.00, 'Partially Paid', 1, '2026-02-10 05:02:08', '2026-02-16 09:42:02', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(160, 34, 'LN-20260210-050013', 2, '2026-03-09', NULL, 1535980.00, 1048760.00, 487220.00, 76800.00, 110000.00, 674020.00, 0.00, 0.00, 0.00, 0.00, 674020.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:02:08', '2026-02-10 05:02:08', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(161, 34, 'LN-20260210-050013', 3, '2026-04-09', NULL, 1048760.00, 537170.00, 511590.00, 52440.00, 110000.00, 674030.00, 0.00, 0.00, 0.00, 0.00, 674030.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:02:08', '2026-02-10 05:02:08', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(162, 34, 'LN-20260210-050013', 4, '2026-05-09', NULL, 537170.00, 0.00, 537170.00, 26860.00, 110000.00, 674030.00, 0.00, 0.00, 0.00, 0.00, 674030.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:02:08', '2026-02-10 05:02:08', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(163, 35, 'LN-20260210-050505', 1, '2025-08-05', NULL, 2429000.00, 1244120.00, 1184880.00, 121450.00, 0.00, 1306330.00, 0.00, 0.00, 0.00, 0.00, 1306330.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:08:54', '2026-02-10 05:08:54', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(164, 35, 'LN-20260210-050505', 2, '2025-09-05', NULL, 1244120.00, 0.00, 1244120.00, 62210.00, 133600.00, 1439930.00, 0.00, 0.00, 0.00, 0.00, 1439930.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:08:54', '2026-02-10 05:08:54', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(165, 36, 'LN-20260210-050858', 1, '2025-12-17', NULL, 1000000.00, 682790.00, 317210.00, 50000.00, 0.00, 367210.00, 0.00, 0.00, 0.00, 0.00, 367210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:11:04', '2026-02-10 05:11:04', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(166, 36, 'LN-20260210-050858', 2, '2026-01-17', NULL, 682790.00, 349720.00, 333070.00, 34140.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:11:04', '2026-02-10 05:11:04', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(167, 36, 'LN-20260210-050858', 3, '2026-02-17', NULL, 349720.00, 0.00, 349720.00, 17490.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:11:04', '2026-02-10 05:11:04', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(168, 37, 'LN-20260210-051123', 1, '2026-01-02', '2026-01-23', 1000000.00, 767990.00, 232010.00, 50000.00, 0.00, 282010.00, 282010.00, 232010.00, 50000.00, 0.00, 0.00, 21, 0.00, 9870.35, 'Fully Paid', 1, '2026-02-10 05:12:15', '2026-02-13 17:16:38', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(169, 37, 'LN-20260210-051123', 2, '2026-02-02', NULL, 767990.00, 524380.00, 243610.00, 38400.00, 55000.00, 337010.00, 0.00, 0.00, 0.00, 0.00, 337010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:12:15', '2026-02-10 05:12:15', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(170, 37, 'LN-20260210-051123', 3, '2026-03-02', NULL, 524380.00, 268590.00, 255790.00, 26220.00, 55000.00, 337010.00, 0.00, 0.00, 0.00, 0.00, 337010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:12:15', '2026-02-10 05:12:15', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(171, 37, 'LN-20260210-051123', 4, '2026-04-02', NULL, 268590.00, 10.00, 268580.00, 13430.00, 55000.00, 337010.00, 0.00, 0.00, 0.00, 0.00, 337010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:12:15', '2026-02-10 05:12:15', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(172, 38, 'LN-20260210-051232', 1, '2026-01-30', '2026-01-22', 3000000.00, 2048370.00, 951630.00, 150000.00, 0.00, 1101630.00, 1101630.00, 951630.00, 150000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 05:13:56', '2026-02-13 14:18:38', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(173, 38, 'LN-20260210-051232', 2, '2026-03-02', NULL, 2048370.00, 1049160.00, 999210.00, 102420.00, 165000.00, 1266630.00, 0.00, 0.00, 0.00, 0.00, 1266630.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:13:56', '2026-02-10 05:13:56', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(174, 38, 'LN-20260210-051232', 3, '2026-03-30', NULL, 1049160.00, 0.00, 1049170.00, 52460.00, 165000.00, 1266630.00, 0.00, 0.00, 0.00, 0.00, 1266630.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 05:13:56', '2026-02-10 05:13:56', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(177, 40, 'LN-20260210-065225', 1, '2026-02-05', '2026-02-05', 1800000.00, 1229020.00, 570980.00, 90000.00, 0.00, 660980.00, 660980.00, 570980.00, 90000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 06:53:07', '2026-02-13 13:35:18', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(178, 40, 'LN-20260210-065225', 2, '2026-03-05', '2026-02-13', 1229020.00, 629500.00, 599520.00, 61450.00, 99000.00, 759970.00, 364025.00, 203575.00, 61450.00, 99000.00, 395945.00, 0, 0.00, 0.00, 'Partially Paid', 1, '2026-02-10 06:53:07', '2026-02-13 13:36:47', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(179, 40, 'LN-20260210-065225', 3, '2026-04-05', NULL, 629500.00, 0.00, 629500.00, 31480.00, 99000.00, 759980.00, 0.00, 0.00, 0.00, 0.00, 759980.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 06:53:07', '2026-02-10 06:53:07', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(180, 41, 'LN-20260210-065311', 1, '2026-01-17', '2026-01-17', 7000000.00, 4779540.00, 2220460.00, 350000.00, 0.00, 2570460.00, 2570460.00, 2220460.00, 350000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 06:54:32', '2026-02-13 13:22:43', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(181, 41, 'LN-20260210-065311', 2, '2026-02-17', '2026-01-17', 4779540.00, 2448060.00, 2331480.00, 238980.00, 385000.00, 2955460.00, 2955460.00, 2331480.00, 238980.00, 385000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 06:54:32', '2026-02-13 13:23:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(182, 41, 'LN-20260210-065311', 3, '2026-03-17', NULL, 2448060.00, 0.00, 2448060.00, 122400.00, 385000.00, 2955460.00, 0.00, 0.00, 0.00, 0.00, 2955460.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 06:54:32', '2026-02-10 06:54:32', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(183, 42, 'LN-20260210-065458', 1, '2026-01-15', '2026-01-15', 1000000.00, 682790.00, 317210.00, 50000.00, 0.00, 367210.00, 367210.00, 317210.00, 50000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 06:56:10', '2026-02-13 13:12:35', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(184, 42, 'LN-20260210-065458', 2, '2026-02-15', NULL, 682790.00, 349720.00, 333070.00, 34140.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 06:56:10', '2026-02-10 06:56:10', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(185, 42, 'LN-20260210-065458', 3, '2026-03-15', NULL, 349720.00, 0.00, 349720.00, 17490.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 06:56:10', '2026-02-10 06:56:10', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(192, 44, 'LN-20260210-065804', 1, '2026-02-05', NULL, 600000.00, 307320.00, 292680.00, 30000.00, 0.00, 322680.00, 0.00, 0.00, 0.00, 0.00, 322680.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 06:58:47', '2026-02-10 06:58:47', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(193, 44, 'LN-20260210-065804', 2, '2026-03-05', NULL, 307320.00, 0.00, 307320.00, 15370.00, 33000.00, 355690.00, 0.00, 0.00, 0.00, 0.00, 355690.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 06:58:47', '2026-02-10 06:58:47', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(194, 45, 'LN-20260210-065857', 1, '2025-12-01', '2026-02-11', 1000000.00, 682790.00, 317210.00, 50000.00, 0.00, 367210.00, 367210.00, 317210.00, 50000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 07:00:32', '2026-02-11 07:42:07', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(195, 45, 'LN-20260210-065857', 2, '2026-01-01', '2026-02-11', 682790.00, 349720.00, 333070.00, 34140.00, 55000.00, 422210.00, 422210.00, 333070.00, 34140.00, 55000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 07:00:32', '2026-02-11 07:42:31', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(196, 45, 'LN-20260210-065857', 3, '2026-02-01', '2026-02-11', 349720.00, 0.00, 349720.00, 17490.00, 55000.00, 422210.00, 422210.00, 349720.00, 17490.00, 55000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 07:00:32', '2026-02-11 07:44:03', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(197, 46, 'LN-20260210-070626', 1, '2026-02-08', '2026-02-09', 1000000.00, 682790.00, 317210.00, 50000.00, 0.00, 367210.00, 367210.00, 317210.00, 50000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 07:07:36', '2026-02-13 12:55:49', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(198, 46, 'LN-20260210-070626', 2, '2026-03-08', NULL, 682790.00, 349720.00, 333070.00, 34140.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:07:36', '2026-02-10 07:07:36', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(199, 46, 'LN-20260210-070626', 3, '2026-04-08', NULL, 349720.00, 0.00, 349720.00, 17490.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:07:36', '2026-02-10 07:07:36', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(200, 47, 'LN-20260210-070746', 1, '2026-01-02', NULL, 700000.00, 537590.00, 162410.00, 35000.00, 0.00, 197410.00, 0.00, 0.00, 0.00, 0.00, 197410.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:08:59', '2026-02-10 07:08:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(201, 47, 'LN-20260210-070746', 2, '2026-02-02', NULL, 537590.00, 367060.00, 170530.00, 26880.00, 38500.00, 235910.00, 0.00, 0.00, 0.00, 0.00, 235910.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:08:59', '2026-02-10 07:08:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(202, 47, 'LN-20260210-070746', 3, '2026-03-02', NULL, 367060.00, 188000.00, 179060.00, 18350.00, 38500.00, 235910.00, 0.00, 0.00, 0.00, 0.00, 235910.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:08:59', '2026-02-10 07:08:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(203, 47, 'LN-20260210-070746', 4, '2026-04-02', NULL, 188000.00, 0.00, 188010.00, 9400.00, 38500.00, 235910.00, 0.00, 0.00, 0.00, 0.00, 235910.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:08:59', '2026-02-10 07:08:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(204, 48, 'LN-20260210-070953', 1, '2025-10-11', NULL, 5500000.00, 3755350.00, 1744650.00, 275000.00, 0.00, 2019650.00, 0.00, 0.00, 0.00, 0.00, 2019650.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:10:34', '2026-02-10 07:10:34', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(205, 48, 'LN-20260210-070953', 2, '2025-11-11', NULL, 3755350.00, 1923470.00, 1831880.00, 187770.00, 302500.00, 2322150.00, 0.00, 0.00, 0.00, 0.00, 2322150.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:10:34', '2026-02-10 07:10:34', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(206, 48, 'LN-20260210-070953', 3, '2025-12-11', NULL, 1923470.00, 0.00, 1923470.00, 96170.00, 302500.00, 2322140.00, 0.00, 0.00, 0.00, 0.00, 2322140.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:10:34', '2026-02-10 07:10:34', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(207, 49, 'LN-20260210-071043', 1, '2025-09-22', NULL, 2000000.00, 0.00, 2000000.00, 100000.00, 0.00, 2100000.00, 0.00, 0.00, 0.00, 0.00, 2100000.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:11:49', '2026-02-10 07:11:49', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(208, 50, 'LN-20260210-071213', 1, '2026-01-15', NULL, 1000000.00, 767990.00, 232010.00, 50000.00, 0.00, 282010.00, 0.00, 0.00, 0.00, 0.00, 282010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:13:12', '2026-02-10 07:13:12', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(209, 50, 'LN-20260210-071213', 2, '2026-02-15', NULL, 767990.00, 524380.00, 243610.00, 38400.00, 55000.00, 337010.00, 0.00, 0.00, 0.00, 0.00, 337010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:13:12', '2026-02-10 07:13:12', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(210, 50, 'LN-20260210-071213', 3, '2026-03-15', NULL, 524380.00, 268590.00, 255790.00, 26220.00, 55000.00, 337010.00, 0.00, 0.00, 0.00, 0.00, 337010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:13:12', '2026-02-10 07:13:12', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(211, 50, 'LN-20260210-071213', 4, '2026-04-15', NULL, 268590.00, 10.00, 268580.00, 13430.00, 55000.00, 337010.00, 0.00, 0.00, 0.00, 0.00, 337010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 07:13:12', '2026-02-10 07:13:12', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(212, 51, 'LN-20260210-071329', 1, '2026-01-02', '2026-02-11', 1500000.00, 768290.00, 731710.00, 75000.00, 0.00, 806710.00, 806710.00, 731710.00, 75000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 07:14:31', '2026-02-11 14:22:13', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(213, 51, 'LN-20260210-071329', 2, '2026-02-02', '2026-02-11', 768290.00, 0.00, 768290.00, 38410.00, 82500.00, 889200.00, 889200.00, 768290.00, 38410.00, 82500.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 07:14:31', '2026-02-11 14:22:26', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(214, 52, 'LN-20260210-195138', 1, '2025-10-12', NULL, 3170000.00, 1623660.00, 1546340.00, 158500.00, 0.00, 1704840.00, 0.00, 0.00, 0.00, 0.00, 1704840.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:52:47', '2026-02-10 19:52:47', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(215, 52, 'LN-20260210-195138', 2, '2025-11-12', NULL, 1623660.00, 0.00, 1623660.00, 81180.00, 174350.00, 1879190.00, 0.00, 0.00, 0.00, 0.00, 1879190.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:52:47', '2026-02-10 19:52:47', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(216, 53, 'LN-20260210-195307', 1, '2025-11-17', '2025-11-17', 600000.00, 460790.00, 139210.00, 30000.00, 0.00, 169210.00, 169210.00, 139210.00, 30000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 19:54:21', '2026-02-13 12:43:51', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(217, 53, 'LN-20260210-195307', 2, '2025-12-17', '2025-12-17', 460790.00, 314620.00, 146170.00, 23040.00, 33000.00, 202210.00, 202210.00, 146170.00, 23040.00, 33000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 19:54:21', '2026-02-13 12:44:18', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(218, 53, 'LN-20260210-195307', 3, '2026-01-17', '2026-01-17', 314620.00, 161140.00, 153480.00, 15730.00, 33000.00, 202210.00, 202210.00, 153480.00, 15730.00, 33000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 19:54:21', '2026-02-13 12:44:35', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(219, 53, 'LN-20260210-195307', 4, '2026-02-17', NULL, 161140.00, 0.00, 161150.00, 8060.00, 33000.00, 202210.00, 0.00, 0.00, 0.00, 0.00, 202210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:54:21', '2026-02-10 19:54:21', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(220, 54, 'LN-20260210-195457', 1, '2025-10-10', '2025-10-10', 4500000.00, 3838420.00, 661580.00, 225000.00, 0.00, 886580.00, 886580.00, 661580.00, 225000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 19:55:55', '2026-02-13 08:46:02', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(221, 54, 'LN-20260210-195457', 2, '2025-11-10', '2025-11-10', 3838420.00, 3143760.00, 694660.00, 191920.00, 247500.00, 1134080.00, 1134080.00, 694660.00, 191920.00, 247500.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 19:55:55', '2026-02-13 08:46:21', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(222, 54, 'LN-20260210-195457', 3, '2025-12-10', '2025-11-10', 3143760.00, 2414370.00, 729390.00, 157190.00, 247500.00, 1134080.00, 1134080.00, 729390.00, 157190.00, 247500.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 19:55:55', '2026-02-13 08:46:39', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(223, 54, 'LN-20260210-195457', 4, '2026-01-10', '2026-01-20', 2414370.00, 1648510.00, 765860.00, 120720.00, 247500.00, 1134080.00, 1134878.93, 766658.93, 120720.00, 247500.00, 0.00, 8, 0.00, 15121.07, 'Fully Paid', 1, '2026-02-10 19:55:55', '2026-02-13 09:01:26', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(224, 54, 'LN-20260210-195457', 5, '2026-02-10', '2026-02-13', 1648510.00, 844360.00, 804150.00, 82430.00, 247500.00, 1134080.00, 1134080.00, 804150.00, 82430.00, 247500.00, 0.00, 17, 0.00, 32132.27, 'Fully Paid', 1, '2026-02-10 19:55:55', '2026-02-13 09:08:40', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(225, 54, 'LN-20260210-195457', 6, '2026-03-10', NULL, 844360.00, 0.00, 844360.00, 42220.00, 247500.00, 1134080.00, 0.00, 0.00, 0.00, 0.00, 1134080.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:55:55', '2026-02-10 19:55:55', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(226, 55, 'LN-20260210-195620', 1, '2025-12-15', NULL, 180000.00, 147420.00, 32580.00, 9000.00, 0.00, 41580.00, 0.00, 0.00, 0.00, 0.00, 41580.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:57:33', '2026-02-10 19:57:33', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(227, 55, 'LN-20260210-195620', 2, '2026-01-15', NULL, 147420.00, 113220.00, 34200.00, 7370.00, 9900.00, 51470.00, 0.00, 0.00, 0.00, 0.00, 51470.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:57:33', '2026-02-10 19:57:33', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(228, 55, 'LN-20260210-195620', 3, '2026-02-15', NULL, 113220.00, 77310.00, 35910.00, 5660.00, 9900.00, 51470.00, 0.00, 0.00, 0.00, 0.00, 51470.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:57:33', '2026-02-10 19:57:33', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(229, 55, 'LN-20260210-195620', 4, '2026-03-15', NULL, 77310.00, 39600.00, 37710.00, 3870.00, 9900.00, 51480.00, 0.00, 0.00, 0.00, 0.00, 51480.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:57:33', '2026-02-10 19:57:33', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(230, 55, 'LN-20260210-195620', 5, '2026-04-15', NULL, 39600.00, 0.00, 39600.00, 1980.00, 9900.00, 51480.00, 0.00, 0.00, 0.00, 0.00, 51480.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:57:33', '2026-02-10 19:57:33', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(231, 56, 'LN-20260210-195746', 1, '2025-12-27', '2025-12-27', 500000.00, 341400.00, 158600.00, 25000.00, 0.00, 183600.00, 183600.00, 158600.00, 25000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 19:58:33', '2026-02-13 12:21:13', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(232, 56, 'LN-20260210-195746', 2, '2026-01-27', '2026-01-27', 341400.00, 174870.00, 166530.00, 17070.00, 27500.00, 211100.00, 211100.00, 166530.00, 17070.00, 27500.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 19:58:33', '2026-02-13 12:21:56', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(233, 56, 'LN-20260210-195746', 3, '2026-02-27', NULL, 174870.00, 10.00, 174860.00, 8740.00, 27500.00, 211100.00, 0.00, 0.00, 0.00, 0.00, 211100.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:58:33', '2026-02-10 19:58:33', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(234, 57, 'LN-20260210-195848', 1, '2025-10-12', NULL, 15283333.00, 10435333.00, 4848000.00, 764170.00, 0.00, 5612170.00, 0.00, 0.00, 0.00, 0.00, 5612170.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:59:51', '2026-02-10 19:59:51', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(235, 57, 'LN-20260210-195848', 2, '2025-11-12', NULL, 10435333.00, 5344933.00, 5090400.00, 521770.00, 840580.00, 6452750.00, 0.00, 0.00, 0.00, 0.00, 6452750.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:59:51', '2026-02-10 19:59:51', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(236, 57, 'LN-20260210-195848', 3, '2025-12-12', NULL, 5344933.00, 13.00, 5344920.00, 267250.00, 840580.00, 6452750.00, 0.00, 0.00, 0.00, 0.00, 6452750.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 19:59:51', '2026-02-10 19:59:51', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(239, 59, 'LN-20260210-201109', 1, '2025-12-19', '2025-12-19', 1000000.00, 767990.00, 232010.00, 50000.00, 0.00, 282010.00, 282010.00, 232010.00, 50000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:11:47', '2026-02-13 12:18:30', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(240, 59, 'LN-20260210-201109', 2, '2026-01-19', '2026-01-19', 767990.00, 524380.00, 243610.00, 38400.00, 55000.00, 337010.00, 337010.00, 243610.00, 38400.00, 55000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:11:47', '2026-02-13 12:19:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(241, 59, 'LN-20260210-201109', 3, '2026-02-19', NULL, 524380.00, 268590.00, 255790.00, 26220.00, 55000.00, 337010.00, 0.00, 0.00, 0.00, 0.00, 337010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:11:47', '2026-02-10 20:11:47', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(242, 59, 'LN-20260210-201109', 4, '2026-03-19', NULL, 268590.00, 10.00, 268580.00, 13430.00, 55000.00, 337010.00, 0.00, 0.00, 0.00, 0.00, 337010.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:11:47', '2026-02-10 20:11:47', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(243, 60, 'LN-20260210-201313', 1, '2025-12-05', NULL, 1000000.00, 819030.00, 180970.00, 50000.00, 0.00, 230970.00, 0.00, 0.00, 0.00, 0.00, 230970.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:14:09', '2026-02-10 20:14:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(244, 60, 'LN-20260210-201313', 2, '2026-01-05', NULL, 819030.00, 629010.00, 190020.00, 40950.00, 55000.00, 285970.00, 0.00, 0.00, 0.00, 0.00, 285970.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:14:09', '2026-02-10 20:14:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(245, 60, 'LN-20260210-201313', 3, '2026-02-05', NULL, 629010.00, 429490.00, 199520.00, 31450.00, 55000.00, 285970.00, 0.00, 0.00, 0.00, 0.00, 285970.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:14:09', '2026-02-10 20:14:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(246, 60, 'LN-20260210-201313', 4, '2026-03-05', NULL, 429490.00, 219990.00, 209500.00, 21470.00, 55000.00, 285970.00, 0.00, 0.00, 0.00, 0.00, 285970.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:14:09', '2026-02-10 20:14:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(247, 60, 'LN-20260210-201313', 5, '2026-04-05', NULL, 219990.00, 10.00, 219980.00, 11000.00, 55000.00, 285980.00, 0.00, 0.00, 0.00, 0.00, 285980.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:14:09', '2026-02-10 20:14:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(248, 61, 'LN-20260210-201441', 1, '2026-01-22', '2026-01-22', 5000000.00, 3413960.00, 1586040.00, 250000.00, 0.00, 1836040.00, 500000.00, 250000.00, 250000.00, 0.00, 1336040.00, 0, 0.00, 0.00, 'Partially Paid', 1, '2026-02-10 20:15:30', '2026-02-13 12:14:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(249, 61, 'LN-20260210-201441', 2, '2026-02-22', NULL, 3413960.00, 1748620.00, 1665340.00, 170700.00, 275000.00, 2111040.00, 0.00, 0.00, 0.00, 0.00, 2111040.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:15:30', '2026-02-10 20:15:30', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(250, 61, 'LN-20260210-201441', 3, '2026-03-22', NULL, 1748620.00, 10.00, 1748610.00, 87430.00, 275000.00, 2111040.00, 0.00, 0.00, 0.00, 0.00, 2111040.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:15:30', '2026-02-10 20:15:30', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(251, 62, 'LN-20260210-201540', 1, '2025-09-04', NULL, 3000000.00, 2048370.00, 951630.00, 150000.00, 0.00, 1101630.00, 0.00, 0.00, 0.00, 0.00, 1101630.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:16:28', '2026-02-10 20:16:28', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(252, 62, 'LN-20260210-201540', 2, '2025-10-04', NULL, 2048370.00, 1049160.00, 999210.00, 102420.00, 165000.00, 1266630.00, 0.00, 0.00, 0.00, 0.00, 1266630.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:16:28', '2026-02-10 20:16:28', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(253, 62, 'LN-20260210-201540', 3, '2025-11-04', NULL, 1049160.00, 0.00, 1049170.00, 52460.00, 165000.00, 1266630.00, 0.00, 0.00, 0.00, 0.00, 1266630.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:16:28', '2026-02-10 20:16:28', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(254, 63, 'LN-20260210-201640', 1, '2026-01-12', '2026-01-12', 1000000.00, 682790.00, 317210.00, 50000.00, 0.00, 367210.00, 367210.00, 317210.00, 50000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:18:09', '2026-02-13 12:10:17', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(255, 63, 'LN-20260210-201640', 2, '2026-02-12', NULL, 682790.00, 349720.00, 333070.00, 34140.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:18:09', '2026-02-10 20:18:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(256, 63, 'LN-20260210-201640', 3, '2026-03-12', NULL, 349720.00, 0.00, 349720.00, 17490.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:18:09', '2026-02-10 20:18:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(257, 64, 'LN-20260210-201822', 1, '2026-02-16', NULL, 500000.00, 341400.00, 158600.00, 25000.00, 0.00, 183600.00, 0.00, 0.00, 0.00, 0.00, 183600.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:19:12', '2026-02-10 20:19:12', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(258, 64, 'LN-20260210-201822', 2, '2026-03-16', NULL, 341400.00, 174870.00, 166530.00, 17070.00, 27500.00, 211100.00, 0.00, 0.00, 0.00, 0.00, 211100.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:19:12', '2026-02-10 20:19:12', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(259, 64, 'LN-20260210-201822', 3, '2026-04-16', NULL, 174870.00, 10.00, 174860.00, 8740.00, 27500.00, 211100.00, 0.00, 0.00, 0.00, 0.00, 211100.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:19:12', '2026-02-10 20:19:12', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(260, 65, 'LN-20260210-201919', 1, '2025-12-17', '2025-12-17', 5000000.00, 3839940.00, 1160060.00, 250000.00, 0.00, 1410060.00, 1410060.00, 1160060.00, 250000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:23:52', '2026-02-13 12:06:46', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(261, 65, 'LN-20260210-201919', 2, '2026-01-17', '2026-01-20', 3839940.00, 2621880.00, 1218060.00, 192000.00, 275000.00, 1685060.00, 466997.00, 0.00, 192000.00, 274997.00, 1218063.00, 0, 0.00, 0.00, 'Partially Paid', 1, '2026-02-10 20:23:52', '2026-02-13 12:08:15', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(262, 65, 'LN-20260210-201919', 3, '2026-02-17', NULL, 2621880.00, 1342910.00, 1278970.00, 131090.00, 275000.00, 1685060.00, 0.00, 0.00, 0.00, 0.00, 1685060.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:23:52', '2026-02-10 20:23:52', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(263, 65, 'LN-20260210-201919', 4, '2026-03-17', NULL, 1342910.00, 0.00, 1342910.00, 67150.00, 275000.00, 1685060.00, 0.00, 0.00, 0.00, 0.00, 1685060.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:23:52', '2026-02-10 20:23:52', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(264, 66, 'LN-20260210-202436', 1, '2026-01-08', '2026-01-14', 1000000.00, 682790.00, 317210.00, 50000.00, 0.00, 367210.00, 367210.00, 317210.00, 50000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:24:56', '2026-02-13 09:15:27', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(265, 66, 'LN-20260210-202436', 2, '2026-02-08', NULL, 682790.00, 349720.00, 333070.00, 34140.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:24:56', '2026-02-10 20:24:56', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(266, 66, 'LN-20260210-202436', 3, '2026-03-08', NULL, 349720.00, 0.00, 349720.00, 17490.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:24:56', '2026-02-10 20:24:56', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(267, 67, 'LN-20260210-202503', 1, '2025-12-01', NULL, 4068014.00, 2777604.00, 1290410.00, 203400.00, 0.00, 1493810.00, 0.00, 0.00, 0.00, 0.00, 1493810.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:26:41', '2026-02-10 20:26:41', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(268, 67, 'LN-20260210-202503', 2, '2026-01-01', NULL, 2777604.00, 1422674.00, 1354930.00, 138880.00, 223740.00, 1717550.00, 0.00, 0.00, 0.00, 0.00, 1717550.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:26:41', '2026-02-10 20:26:41', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(269, 67, 'LN-20260210-202503', 3, '2026-02-01', NULL, 1422674.00, 0.00, 1422680.00, 71130.00, 223740.00, 1717550.00, 0.00, 0.00, 0.00, 0.00, 1717550.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:26:41', '2026-02-10 20:26:41', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(276, 69, 'LN-20260210-202825', 1, '2025-09-18', NULL, 4000000.00, 2731170.00, 1268830.00, 200000.00, 0.00, 1468830.00, 0.00, 0.00, 0.00, 0.00, 1468830.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:29:20', '2026-02-10 20:29:20', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(277, 69, 'LN-20260210-202825', 2, '2025-10-18', NULL, 2731170.00, 1398890.00, 1332280.00, 136560.00, 220000.00, 1688840.00, 0.00, 0.00, 0.00, 0.00, 1688840.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:29:20', '2026-02-10 20:29:20', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(278, 69, 'LN-20260210-202825', 3, '2025-11-18', NULL, 1398890.00, 0.00, 1398890.00, 69940.00, 220000.00, 1688830.00, 0.00, 0.00, 0.00, 0.00, 1688830.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:29:20', '2026-02-10 20:29:20', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(279, 70, 'LN-20260210-202931', 1, '2025-10-26', '2025-10-26', 950000.00, 648650.00, 301350.00, 47500.00, 0.00, 348850.00, 348850.00, 301350.00, 47500.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:31:00', '2026-02-13 11:57:24', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(280, 70, 'LN-20260210-202931', 2, '2025-11-26', '2025-11-26', 648650.00, 332230.00, 316420.00, 32430.00, 52250.00, 401100.00, 401100.00, 316420.00, 32430.00, 52250.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:31:00', '2026-02-13 11:57:51', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(281, 70, 'LN-20260210-202931', 3, '2025-12-26', '2025-12-26', 332230.00, 0.00, 332240.00, 16610.00, 52250.00, 401100.00, 401100.00, 332240.00, 16610.00, 52250.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:31:00', '2026-02-13 11:58:44', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(282, 71, 'LN-20260210-203110', 1, '2026-02-05', '2026-02-05', 5000000.00, 3413960.00, 1586040.00, 250000.00, 0.00, 1836040.00, 1836043.00, 1586043.00, 250000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:31:59', '2026-02-13 11:55:48', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(283, 71, 'LN-20260210-203110', 2, '2026-03-05', NULL, 3413960.00, 1748620.00, 1665340.00, 170700.00, 275000.00, 2111040.00, 0.00, 0.00, 0.00, 0.00, 2111040.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:31:59', '2026-02-10 20:31:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(284, 71, 'LN-20260210-203110', 3, '2026-04-05', NULL, 1748620.00, 10.00, 1748610.00, 87430.00, 275000.00, 2111040.00, 0.00, 0.00, 0.00, 0.00, 2111040.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:31:59', '2026-02-10 20:31:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(285, 72, 'LN-20260210-203213', 1, '2026-01-17', NULL, 700000.00, 537590.00, 162410.00, 35000.00, 0.00, 197410.00, 0.00, 0.00, 0.00, 0.00, 197410.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:33:44', '2026-02-10 20:33:44', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(286, 72, 'LN-20260210-203213', 2, '2026-02-17', NULL, 537590.00, 367060.00, 170530.00, 26880.00, 38500.00, 235910.00, 0.00, 0.00, 0.00, 0.00, 235910.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:33:44', '2026-02-10 20:33:44', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(287, 72, 'LN-20260210-203213', 3, '2026-03-17', NULL, 367060.00, 188000.00, 179060.00, 18350.00, 38500.00, 235910.00, 0.00, 0.00, 0.00, 0.00, 235910.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:33:44', '2026-02-10 20:33:44', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(288, 72, 'LN-20260210-203213', 4, '2026-04-17', NULL, 188000.00, 0.00, 188010.00, 9400.00, 38500.00, 235910.00, 0.00, 0.00, 0.00, 0.00, 235910.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:33:44', '2026-02-10 20:33:44', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(289, 73, 'LN-20260210-203413', 1, '2026-01-05', '2026-02-11', 5000000.00, 3839940.00, 1160060.00, 250000.00, 0.00, 1410060.00, 1410060.00, 1160060.00, 250000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:35:04', '2026-02-11 14:04:14', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(290, 73, 'LN-20260210-203413', 2, '2026-02-05', '2026-02-11', 3839940.00, 2621880.00, 1218060.00, 192000.00, 275000.00, 1685060.00, 1685060.00, 1218060.00, 192000.00, 275000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:35:04', '2026-02-11 14:04:26', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(291, 73, 'LN-20260210-203413', 3, '2026-03-05', NULL, 2621880.00, 1342910.00, 1278970.00, 131090.00, 275000.00, 1685060.00, 0.00, 0.00, 0.00, 0.00, 1685060.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:35:04', '2026-02-10 20:35:04', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(292, 73, 'LN-20260210-203413', 4, '2026-04-05', NULL, 1342910.00, 0.00, 1342910.00, 67150.00, 275000.00, 1685060.00, 0.00, 0.00, 0.00, 0.00, 1685060.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:35:04', '2026-02-10 20:35:04', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(293, 74, 'LN-20260210-203513', 1, '2026-01-01', '2026-01-01', 300000.00, 153660.00, 146340.00, 15000.00, 0.00, 161340.00, 161340.00, 146340.00, 15000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:37:42', '2026-02-13 11:50:14', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(294, 74, 'LN-20260210-203513', 2, '2026-02-01', '2026-02-01', 153660.00, 0.00, 153660.00, 7680.00, 16500.00, 177840.00, 177840.00, 153660.00, 7680.00, 16500.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:37:42', '2026-02-13 11:50:30', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(298, 76, 'LN-20260210-204014', 1, '2026-01-18', '2026-01-17', 700000.00, 537590.00, 162410.00, 35000.00, 0.00, 197410.00, 197410.00, 162410.00, 35000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:40:51', '2026-02-13 08:24:41', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(299, 76, 'LN-20260210-204014', 2, '2026-02-18', '2026-02-17', 537590.00, 367060.00, 170530.00, 26880.00, 38500.00, 235910.00, 235910.00, 170530.00, 26880.00, 38500.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:40:51', '2026-02-18 13:21:03', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(300, 76, 'LN-20260210-204014', 3, '2026-03-18', NULL, 367060.00, 188000.00, 179060.00, 18350.00, 38500.00, 235910.00, 0.00, 0.00, 0.00, 0.00, 235910.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:40:51', '2026-02-10 20:40:51', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(301, 76, 'LN-20260210-204014', 4, '2026-04-18', NULL, 188000.00, 0.00, 188010.00, 9400.00, 38500.00, 235910.00, 0.00, 0.00, 0.00, 0.00, 235910.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:40:51', '2026-02-10 20:40:51', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(302, 77, 'LN-20260210-204109', 1, '2025-12-10', '2025-12-10', 8000000.00, 6552200.00, 1447800.00, 400000.00, 0.00, 1847800.00, 1847800.00, 1447800.00, 400000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:42:07', '2026-02-13 08:01:55', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(303, 77, 'LN-20260210-204109', 2, '2026-01-10', '2026-01-27', 6552200.00, 5032010.00, 1520190.00, 327610.00, 440000.00, 2287800.00, 2223977.00, 1456367.00, 327610.00, 440000.00, 0.00, 17, 0.00, 64821.00, 'Fully Paid', 1, '2026-02-10 20:42:07', '2026-02-13 08:06:39', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(304, 77, 'LN-20260210-204109', 3, '2026-02-10', NULL, 5032010.00, 3435810.00, 1596200.00, 251600.00, 440000.00, 2287800.00, 0.00, 0.00, 0.00, 0.00, 2287800.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:42:07', '2026-02-10 20:42:07', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(305, 77, 'LN-20260210-204109', 4, '2026-03-10', NULL, 3435810.00, 1759800.00, 1676010.00, 171790.00, 440000.00, 2287800.00, 0.00, 0.00, 0.00, 0.00, 2287800.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:42:07', '2026-02-10 20:42:07', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(306, 77, 'LN-20260210-204109', 5, '2026-04-10', NULL, 1759800.00, 0.00, 1759810.00, 87990.00, 440000.00, 2287800.00, 0.00, 0.00, 0.00, 0.00, 2287800.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:42:07', '2026-02-10 20:42:07', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(307, 78, 'LN-20260210-204222', 1, '2026-01-04', '2026-01-05', 1000000.00, 682790.00, 317210.00, 50000.00, 0.00, 367210.00, 367210.00, 317210.00, 50000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:43:19', '2026-02-13 11:15:27', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(308, 78, 'LN-20260210-204222', 2, '2026-02-04', '2026-02-05', 682790.00, 349720.00, 333070.00, 34140.00, 55000.00, 422210.00, 422210.00, 333070.00, 34140.00, 55000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:43:19', '2026-02-13 11:15:44', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(309, 78, 'LN-20260210-204222', 3, '2026-03-04', NULL, 349720.00, 0.00, 349720.00, 17490.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:43:19', '2026-02-10 20:43:19', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(310, 79, 'LN-20260210-204420', 1, '2025-12-13', '2026-02-11', 300000.00, 230400.00, 69600.00, 15000.00, 0.00, 84600.00, 84600.00, 69600.00, 15000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:45:17', '2026-02-11 13:51:10', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(311, 79, 'LN-20260210-204420', 2, '2026-01-13', '2026-02-11', 230400.00, 157320.00, 73080.00, 11520.00, 16500.00, 101100.00, 101100.00, 73080.00, 11520.00, 16500.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:45:17', '2026-02-11 13:51:53', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(312, 79, 'LN-20260210-204420', 3, '2026-02-13', '2026-02-11', 157320.00, 80580.00, 76740.00, 7870.00, 16500.00, 101110.00, 101110.00, 76740.00, 7870.00, 16500.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:45:17', '2026-02-11 13:52:28', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(313, 79, 'LN-20260210-204420', 4, '2026-03-13', NULL, 80580.00, 10.00, 80570.00, 4030.00, 16500.00, 101100.00, 0.00, 0.00, 0.00, 0.00, 101100.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:45:17', '2026-02-10 20:45:17', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(314, 80, 'LN-20260210-204526', 1, '2025-12-12', '2026-02-11', 1200000.00, 982830.00, 217170.00, 60000.00, 0.00, 277170.00, 277170.00, 217170.00, 60000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:46:20', '2026-02-11 14:18:37', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(315, 80, 'LN-20260210-204526', 2, '2026-01-12', '2026-01-30', 982830.00, 754800.00, 228030.00, 49140.00, 66000.00, 343170.00, 343170.00, 228030.00, 49140.00, 66000.00, 0.00, 30, 0.00, 17158.50, 'Fully Paid', 1, '2026-02-10 20:46:20', '2026-02-11 14:17:45', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(316, 80, 'LN-20260210-204526', 3, '2026-02-12', NULL, 754800.00, 515370.00, 239430.00, 37740.00, 66000.00, 343170.00, 0.00, 0.00, 0.00, 0.00, 343170.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:46:20', '2026-02-10 20:46:20', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(317, 80, 'LN-20260210-204526', 4, '2026-03-12', NULL, 515370.00, 263970.00, 251400.00, 25770.00, 66000.00, 343170.00, 0.00, 0.00, 0.00, 0.00, 343170.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:46:20', '2026-02-10 20:46:20', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(318, 80, 'LN-20260210-204526', 5, '2026-04-12', NULL, 263970.00, 0.00, 263970.00, 13200.00, 66000.00, 343170.00, 0.00, 0.00, 0.00, 0.00, 343170.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:46:20', '2026-02-10 20:46:20', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(319, 81, 'LN-20260210-204627', 1, '2026-02-15', '2026-02-15', 3000000.00, 0.00, 3000000.00, 150000.00, 0.00, 3150000.00, 3150000.00, 2850000.00, 300000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:47:43', '2026-02-16 07:36:05', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(320, 82, 'LN-20260210-204759', 1, '2026-01-22', '2026-01-26', 800000.00, 0.00, 800000.00, 40000.00, 0.00, 840000.00, 40000.00, 0.00, 40000.00, 0.00, 800000.00, 0, 0.00, 0.00, 'Partially Paid', 1, '2026-02-10 20:49:44', '2026-02-13 10:42:40', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(321, 83, 'LN-20260210-204952', 1, '2026-01-15', '2026-02-11', 4000000.00, 2048780.00, 1951220.00, 200000.00, 0.00, 2151220.00, 2151220.00, 1951220.00, 200000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:53:58', '2026-02-11 13:57:21', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(322, 83, 'LN-20260210-204952', 2, '2026-02-15', '2026-02-11', 2048780.00, 0.00, 2048780.00, 102440.00, 220000.00, 2371220.00, 2371220.00, 2048780.00, 102440.00, 220000.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:53:58', '2026-02-11 13:57:46', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(323, 84, 'LN-20260210-205440', 1, '2026-02-13', '2026-02-13', 2000000.00, 1365580.00, 634420.00, 100000.00, 0.00, 734420.00, 734420.00, 634420.00, 100000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 20:55:00', '2026-02-13 09:11:52', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(324, 84, 'LN-20260210-205440', 2, '2026-03-13', NULL, 1365580.00, 699440.00, 666140.00, 68280.00, 110000.00, 844420.00, 0.00, 0.00, 0.00, 0.00, 844420.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:55:00', '2026-02-10 20:55:00', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(325, 84, 'LN-20260210-205440', 3, '2026-04-13', NULL, 699440.00, 0.00, 699440.00, 34970.00, 110000.00, 844410.00, 0.00, 0.00, 0.00, 0.00, 844410.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:55:00', '2026-02-10 20:55:00', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(326, 85, 'LN-20260210-205512', 1, '2026-02-13', '2026-02-09', 2000000.00, 1365580.00, 634420.00, 100000.00, 0.00, 734420.00, 367206.00, 267206.00, 100000.00, 0.00, 367214.00, 0, 0.00, 0.00, 'Partially Paid', 1, '2026-02-10 20:55:58', '2026-02-13 10:32:04', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(327, 85, 'LN-20260210-205512', 2, '2026-03-13', NULL, 1365580.00, 699440.00, 666140.00, 68280.00, 110000.00, 844420.00, 0.00, 0.00, 0.00, 0.00, 844420.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:55:58', '2026-02-10 20:55:58', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(328, 85, 'LN-20260210-205512', 3, '2026-04-13', NULL, 699440.00, 0.00, 699440.00, 34970.00, 110000.00, 844410.00, 0.00, 0.00, 0.00, 0.00, 844410.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-10 20:55:58', '2026-02-10 20:55:58', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(337, 88, 'LN-20260210-205936', 1, '2026-02-12', '2026-02-11', 21100000.00, 0.00, 21100000.00, 1055000.00, 0.00, 22155000.00, 22155000.00, 21100000.00, 1055000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-10 21:01:21', '2026-02-11 08:07:10', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(345, 92, 'LN-20260211-131649', 1, '2026-02-12', '2026-02-10', 1300000.00, 1064730.00, 235270.00, 65000.00, 0.00, 300270.00, 300270.00, 235000.00, 65270.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-11 13:19:09', '2026-02-13 10:23:01', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(346, 92, 'LN-20260211-131649', 2, '2026-03-12', NULL, 1064730.00, 817700.00, 247030.00, 53240.00, 65000.00, 365270.00, 0.00, 0.00, 0.00, 0.00, 365270.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 13:19:09', '2026-02-11 13:19:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(347, 92, 'LN-20260211-131649', 3, '2026-04-12', NULL, 817700.00, 558320.00, 259380.00, 40890.00, 65000.00, 365270.00, 0.00, 0.00, 0.00, 0.00, 365270.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 13:19:09', '2026-02-11 13:19:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(348, 92, 'LN-20260211-131649', 4, '2026-05-12', NULL, 558320.00, 285970.00, 272350.00, 27920.00, 65000.00, 365270.00, 0.00, 0.00, 0.00, 0.00, 365270.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 13:19:09', '2026-02-11 13:19:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(349, 92, 'LN-20260211-131649', 5, '2026-06-12', NULL, 285970.00, 0.00, 285970.00, 14300.00, 65000.00, 365270.00, 0.00, 0.00, 0.00, 0.00, 365270.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 13:19:09', '2026-02-11 13:19:09', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(350, 91, 'LN-20260211-121925', 1, '2026-02-13', '2026-02-11', 20000000.00, 13655828.71, 6344171.29, 1000000.00, 0.00, 7344171.29, 7344171.29, 6344171.29, 1000000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-11 13:20:30', '2026-02-11 14:29:53', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(351, 91, 'LN-20260211-121925', 2, '2026-03-13', '2026-02-11', 13655828.71, 6994448.85, 6661379.86, 682791.44, 1100000.00, 8444171.30, 1555829.00, 0.00, 682791.44, 873037.56, 6888342.30, 0, 0.00, 0.00, 'Partially Paid', 1, '2026-02-11 13:20:30', '2026-02-11 14:31:02', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(352, 91, 'LN-20260211-121925', 3, '2026-04-13', NULL, 6994448.85, 0.00, 6994448.85, 349722.44, 1100000.00, 8444171.29, 0.00, 0.00, 0.00, 0.00, 8444171.29, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 13:20:30', '2026-02-11 13:20:30', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0);
INSERT INTO `loan_instalments` (`instalment_id`, `loan_id`, `loan_number`, `instalment_number`, `due_date`, `payment_date`, `opening_balance`, `closing_balance`, `principal_amount`, `interest_amount`, `management_fee`, `total_payment`, `paid_amount`, `principal_paid`, `interest_paid`, `management_fee_paid`, `balance_remaining`, `days_overdue`, `penalty_amount`, `penalty_paid`, `status`, `created_by`, `created_at`, `updated_at`, `overdue_ledger_recorded`, `ninety_day_recorded`, `monitoring_fee_net`, `monitoring_fee_vat`, `monitoring_fee_total`, `provision_calculated`, `provision_amount`, `provision_date`, `suspension_recorded`) VALUES
(353, 93, 'LN-20260211-142242', 1, '2026-03-11', NULL, 1500000.00, 768290.00, 731710.00, 75000.00, 0.00, 806710.00, 0.00, 0.00, 0.00, 0.00, 806710.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 14:25:22', '2026-02-11 14:25:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(354, 93, 'LN-20260211-142242', 2, '2026-04-11', NULL, 768290.00, 0.00, 768290.00, 38410.00, 82500.00, 889200.00, 0.00, 0.00, 0.00, 0.00, 889200.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 14:25:22', '2026-02-11 14:25:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(355, 58, 'LN-20260210-200003', 1, '2025-12-18', NULL, 1500000.00, 768292.68, 731707.32, 75000.00, 0.00, 806707.32, 0.00, 0.00, 0.00, 0.00, 806707.32, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 16:47:48', '2026-02-11 16:47:48', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(356, 58, 'LN-20260210-200003', 2, '2026-01-18', NULL, 768292.68, 0.00, 768292.68, 38414.63, 82500.00, 889207.31, 0.00, 0.00, 0.00, 0.00, 889207.31, 0, 0.00, 0.00, 'Pending', 1, '2026-02-11 16:47:48', '2026-02-11 16:47:48', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(360, 95, 'LN-20260213-074840', 1, '2026-03-12', NULL, 1000000.00, 682790.00, 317210.00, 50000.00, 0.00, 367210.00, 0.00, 0.00, 0.00, 0.00, 367210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 07:51:22', '2026-02-13 07:51:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(361, 95, 'LN-20260213-074840', 2, '2026-04-12', NULL, 682790.00, 349720.00, 333070.00, 34140.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 07:51:22', '2026-02-13 07:51:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(362, 95, 'LN-20260213-074840', 3, '2026-05-12', NULL, 349720.00, 0.00, 349720.00, 17490.00, 55000.00, 422210.00, 0.00, 0.00, 0.00, 0.00, 422210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 07:51:22', '2026-02-13 07:51:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(363, 96, 'LN-20260213-082655', 1, '2026-02-15', NULL, 12000000.00, 10235790.00, 1764210.00, 600000.00, 0.00, 2364210.00, 0.00, 0.00, 0.00, 0.00, 2364210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:27:59', '2026-02-13 08:27:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(364, 96, 'LN-20260213-082655', 2, '2026-03-15', NULL, 10235790.00, 8383370.00, 1852420.00, 511790.00, 660000.00, 3024210.00, 0.00, 0.00, 0.00, 0.00, 3024210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:27:59', '2026-02-13 08:27:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(365, 96, 'LN-20260213-082655', 3, '2026-04-15', NULL, 8383370.00, 6438330.00, 1945040.00, 419170.00, 660000.00, 3024210.00, 0.00, 0.00, 0.00, 0.00, 3024210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:27:59', '2026-02-13 08:27:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(366, 96, 'LN-20260213-082655', 4, '2026-05-15', NULL, 6438330.00, 4396040.00, 2042290.00, 321920.00, 660000.00, 3024210.00, 0.00, 0.00, 0.00, 0.00, 3024210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:27:59', '2026-02-13 08:27:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(367, 96, 'LN-20260213-082655', 5, '2026-06-15', NULL, 4396040.00, 2251630.00, 2144410.00, 219800.00, 660000.00, 3024210.00, 0.00, 0.00, 0.00, 0.00, 3024210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:27:59', '2026-02-13 08:27:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(368, 96, 'LN-20260213-082655', 6, '2026-07-15', NULL, 2251630.00, 0.00, 2251630.00, 112580.00, 660000.00, 3024210.00, 0.00, 0.00, 0.00, 0.00, 3024210.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:27:59', '2026-02-13 08:27:59', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(369, 97, 'LN-20260213-082810', 1, '2026-02-08', NULL, 27000000.00, 20735680.00, 6264320.00, 1350000.00, 0.00, 7614320.00, 0.00, 0.00, 0.00, 0.00, 7614320.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:30:37', '2026-02-13 08:30:37', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(370, 97, 'LN-20260213-082810', 2, '2026-03-08', NULL, 20735680.00, 14158140.00, 6577540.00, 1036780.00, 1215000.00, 8829320.00, 0.00, 0.00, 0.00, 0.00, 8829320.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:30:37', '2026-02-13 08:30:37', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(371, 97, 'LN-20260213-082810', 3, '2026-04-08', NULL, 14158140.00, 7251730.00, 6906410.00, 707910.00, 1215000.00, 8829320.00, 0.00, 0.00, 0.00, 0.00, 8829320.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:30:37', '2026-02-13 08:30:37', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(372, 97, 'LN-20260213-082810', 4, '2026-05-08', NULL, 7251730.00, 0.00, 7251730.00, 362590.00, 1215000.00, 8829320.00, 0.00, 0.00, 0.00, 0.00, 8829320.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:30:37', '2026-02-13 08:30:37', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(373, 98, 'LN-20260213-083938', 1, '2026-03-10', NULL, 1000000.00, 852980.00, 147020.00, 50000.00, 0.00, 197020.00, 0.00, 0.00, 0.00, 0.00, 197020.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:41:00', '2026-02-13 08:41:00', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(374, 98, 'LN-20260213-083938', 2, '2026-04-10', NULL, 852980.00, 698610.00, 154370.00, 42650.00, 55000.00, 252020.00, 0.00, 0.00, 0.00, 0.00, 252020.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:41:00', '2026-02-13 08:41:00', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(375, 98, 'LN-20260213-083938', 3, '2026-05-10', NULL, 698610.00, 536520.00, 162090.00, 34930.00, 55000.00, 252020.00, 0.00, 0.00, 0.00, 0.00, 252020.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:41:00', '2026-02-13 08:41:00', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(376, 98, 'LN-20260213-083938', 4, '2026-06-10', NULL, 536520.00, 366330.00, 170190.00, 26830.00, 55000.00, 252020.00, 0.00, 0.00, 0.00, 0.00, 252020.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:41:00', '2026-02-13 08:41:00', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(377, 98, 'LN-20260213-083938', 5, '2026-07-10', NULL, 366330.00, 187630.00, 178700.00, 18320.00, 55000.00, 252020.00, 0.00, 0.00, 0.00, 0.00, 252020.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:41:00', '2026-02-13 08:41:00', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(378, 98, 'LN-20260213-083938', 6, '2026-08-10', NULL, 187630.00, 0.00, 187640.00, 9380.00, 55000.00, 252020.00, 0.00, 0.00, 0.00, 0.00, 252020.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 08:41:00', '2026-02-13 08:41:00', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(386, 99, 'LN-20260213-095208', 1, '2026-02-20', NULL, 1200000.00, 921585.80, 278414.20, 60000.00, 0.00, 338414.20, 0.00, 0.00, 0.00, 0.00, 338414.20, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 09:57:22', '2026-02-13 09:57:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(387, 99, 'LN-20260213-095208', 2, '2026-03-20', NULL, 921585.80, 629250.89, 292334.91, 46079.29, 60000.00, 398414.20, 0.00, 0.00, 0.00, 0.00, 398414.20, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 09:57:22', '2026-02-13 09:57:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(388, 99, 'LN-20260213-095208', 3, '2026-04-20', NULL, 629250.89, 322299.24, 306951.65, 31462.54, 60000.00, 398414.19, 0.00, 0.00, 0.00, 0.00, 398414.19, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 09:57:22', '2026-02-13 09:57:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(389, 99, 'LN-20260213-095208', 4, '2026-05-20', NULL, 322299.24, 0.00, 322299.24, 16114.96, 60000.00, 398414.20, 0.00, 0.00, 0.00, 0.00, 398414.20, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 09:57:22', '2026-02-13 09:57:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(392, 101, 'LN-20260213-115907', 1, '2026-03-19', '2026-02-11', 700000.00, 477950.00, 222050.00, 35000.00, 0.00, 257050.00, 120000.00, 85000.00, 35000.00, 0.00, 137050.00, 0, 0.00, 0.00, 'Partially Paid', 1, '2026-02-13 12:00:32', '2026-02-13 12:01:42', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(393, 101, 'LN-20260213-115907', 2, '2026-04-19', NULL, 477950.00, 244800.00, 233150.00, 23900.00, 38500.00, 295550.00, 0.00, 0.00, 0.00, 0.00, 295550.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 12:00:32', '2026-02-13 12:00:32', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(394, 101, 'LN-20260213-115907', 3, '2026-05-19', NULL, 244800.00, 0.00, 244810.00, 12240.00, 38500.00, 295550.00, 0.00, 0.00, 0.00, 0.00, 295550.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 12:00:32', '2026-02-13 12:00:32', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(395, 43, 'LN-20260210-065730', 1, '2025-12-24', '2025-12-04', 700000.00, 537591.72, 162408.28, 35000.00, 0.00, 197408.28, 197408.28, 162408.28, 35000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-13 13:06:32', '2026-02-13 13:07:28', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(396, 43, 'LN-20260210-065730', 2, '2026-01-24', '2026-01-30', 537591.72, 367063.02, 170528.70, 26879.59, 38500.00, 235908.29, 235908.29, 170528.70, 26879.59, 38500.00, 0.00, 6, 0.00, 2359.08, 'Fully Paid', 1, '2026-02-13 13:06:32', '2026-02-13 13:08:45', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(397, 43, 'LN-20260210-065730', 3, '2026-02-24', NULL, 367063.02, 188007.89, 179055.13, 18353.15, 38500.00, 235908.28, 0.00, 0.00, 0.00, 0.00, 235908.28, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 13:06:32', '2026-02-13 13:06:32', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(398, 43, 'LN-20260210-065730', 4, '2026-03-24', NULL, 188007.89, 0.00, 188007.89, 9400.39, 38500.00, 235908.28, 0.00, 0.00, 0.00, 0.00, 235908.28, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 13:06:32', '2026-02-13 13:06:32', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(399, 39, 'LN-20260210-064151', 1, '2025-11-08', '2025-11-08', 1080000.00, 553170.73, 526829.27, 54000.00, 0.00, 580829.27, 580829.27, 526829.27, 54000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-13 14:15:43', '2026-02-13 14:16:22', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(400, 39, 'LN-20260210-064151', 2, '2025-12-08', '2025-12-08', 553170.73, 0.00, 553170.73, 27658.54, 59400.00, 640229.27, 640229.27, 553170.73, 27658.54, 59400.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-13 14:15:43', '2026-02-13 14:16:42', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(401, 33, 'LN-20260210-045901', 1, '2026-01-01', '2026-01-01', 300000.00, 204837.43, 95162.57, 15000.00, 0.00, 110162.57, 110162.57, 95162.57, 15000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-13 15:00:34', '2026-02-13 15:01:53', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(402, 33, 'LN-20260210-045901', 2, '2026-02-01', '2026-02-01', 204837.43, 104916.73, 99920.70, 10241.87, 0.00, 110162.57, 110162.57, 99920.70, 10241.87, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-13 15:00:34', '2026-02-13 15:02:19', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(403, 33, 'LN-20260210-045901', 3, '2026-03-01', NULL, 104916.73, 0.00, 104916.73, 5245.84, 0.00, 110162.57, 0.00, 0.00, 0.00, 0.00, 110162.57, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 15:00:34', '2026-02-13 15:00:34', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(404, 102, 'LN-20260213-155133', 1, '2025-12-13', '2025-12-13', 500000.00, 341400.00, 158600.00, 25000.00, 0.00, 183600.00, 183600.00, 158600.00, 25000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-13 15:57:41', '2026-02-13 16:07:03', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(405, 102, 'LN-20260213-155133', 2, '2026-01-13', '2026-01-23', 341400.00, 174870.00, 166530.00, 17070.00, 27500.00, 211100.00, 211100.00, 166530.00, 17070.00, 27500.00, 0.00, 11, 0.00, 3870.17, 'Fully Paid', 1, '2026-02-13 15:57:41', '2026-02-13 16:08:32', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(406, 102, 'LN-20260213-155133', 3, '2026-02-13', NULL, 174870.00, 10.00, 174860.00, 8740.00, 27500.00, 211100.00, 0.00, 0.00, 0.00, 0.00, 211100.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-13 15:57:41', '2026-02-13 15:57:41', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(419, 75, 'LN-20260210-203750', 1, '2025-12-20', NULL, 1000000.00, 767988.17, 232011.83, 50000.00, 0.00, 282011.83, 0.00, 0.00, 0.00, 0.00, 282011.83, 0, 0.00, 0.00, 'Pending', 1, '2026-02-17 12:42:55', '2026-02-17 12:42:55', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(420, 75, 'LN-20260210-203750', 2, '2026-01-20', NULL, 767988.17, 524375.75, 243612.42, 38399.41, 55000.00, 337011.83, 0.00, 0.00, 0.00, 0.00, 337011.83, 0, 0.00, 0.00, 'Pending', 1, '2026-02-17 12:42:55', '2026-02-17 12:42:55', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(421, 75, 'LN-20260210-203750', 3, '2026-02-20', NULL, 524375.75, 268582.70, 255793.05, 26218.79, 55000.00, 337011.84, 0.00, 0.00, 0.00, 0.00, 337011.84, 0, 0.00, 0.00, 'Pending', 1, '2026-02-17 12:42:55', '2026-02-17 12:42:55', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(422, 75, 'LN-20260210-203750', 4, '2026-03-20', NULL, 268582.70, 0.00, 268582.70, 13429.14, 55000.00, 337011.84, 0.00, 0.00, 0.00, 0.00, 337011.84, 0, 0.00, 0.00, 'Pending', 1, '2026-02-17 12:42:55', '2026-02-17 12:42:55', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(459, 110, 'LN-20260218-105127', 1, '2026-02-09', '2026-02-09', 2000000.00, 1365580.00, 634420.00, 100000.00, 0.00, 734420.00, 734420.00, 634420.00, 100000.00, 0.00, 0.00, 0, 0.00, 0.00, 'Fully Paid', 1, '2026-02-18 10:53:42', '2026-02-18 10:55:26', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(460, 110, 'LN-20260218-105127', 2, '2026-03-09', NULL, 1365580.00, 699440.00, 666140.00, 68280.00, 110000.00, 844420.00, 0.00, 0.00, 0.00, 0.00, 844420.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-18 10:53:42', '2026-02-18 10:53:42', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0),
(461, 110, 'LN-20260218-105127', 3, '2026-04-09', NULL, 699440.00, 0.00, 699440.00, 34970.00, 110000.00, 844410.00, 0.00, 0.00, 0.00, 0.00, 844410.00, 0, 0.00, 0.00, 'Pending', 1, '2026-02-18 10:53:43', '2026-02-18 10:53:43', 0, 0, 0.00, 0.00, 0.00, 0, 0.00, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `payment_id` int(11) NOT NULL,
  `loan_instalment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `month_paid` varchar(200) DEFAULT NULL,
  `payment_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `beginning_balance` decimal(15,2) DEFAULT '0.00',
  `payment_amount` decimal(15,2) NOT NULL,
  `interest_amount` decimal(15,2) DEFAULT '0.00',
  `principal_amount` decimal(15,2) DEFAULT '0.00',
  `monitoring_fee` decimal(15,2) DEFAULT '0.00',
  `days_overdue` int(11) DEFAULT '0',
  `penalties` decimal(15,2) DEFAULT '0.00',
  `final_payment` decimal(15,2) DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `adjustment_id` int(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `loan_payments`
--

INSERT INTO `loan_payments` (`payment_id`, `loan_instalment_id`, `loan_id`, `month_paid`, `payment_date`, `beginning_balance`, `payment_amount`, `interest_amount`, `principal_amount`, `monitoring_fee`, `days_overdue`, `penalties`, `final_payment`, `payment_method`, `reference_number`, `notes`, `created_at`, `updated_at`, `adjustment_id`) VALUES
(334, 1025, 315, '2026-03-06', '2026-02-04 00:00:00', 1015263.31, 380269.00, 50763.17, 322049.60, 7456.24, 0, 0.00, 380269.01, 'Cash', '', '', '2026-02-04 14:16:24', '2026-02-04 14:16:24', NULL),
(335, 1031, 317, '2026-03-07', '2026-02-05 00:00:00', 1588500.00, 302116.72, 79425.00, 503885.81, 11666.19, 2, 7139.72, 602116.72, 'Bank Transfer', '', '', '2026-02-05 09:11:39', '2026-02-05 09:11:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_payment_adjustments`
--

CREATE TABLE `loan_payment_adjustments` (
  `adjustment_id` bigint(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `instalment_id` int(11) NOT NULL,
  `instalment_number` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `adjustment_amount` decimal(15,2) NOT NULL,
  `adjustment_type` varchar(100) NOT NULL DEFAULT 'ADJUSTMENT',
  `status` varchar(200) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `loan_payment_variance`
--

CREATE TABLE `loan_payment_variance` (
  `variance_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `loan_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instalment_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `expected_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `actual_amount_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `variance_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `variance_type` enum('overpayment','underpayment','prepayment','exact_payment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `principal_expected` decimal(15,2) DEFAULT '0.00',
  `principal_paid` decimal(15,2) DEFAULT '0.00',
  `principal_variance` decimal(15,2) DEFAULT '0.00',
  `interest_expected` decimal(15,2) DEFAULT '0.00',
  `interest_paid` decimal(15,2) DEFAULT '0.00',
  `interest_variance` decimal(15,2) DEFAULT '0.00',
  `monitoring_fee_expected` decimal(15,2) DEFAULT '0.00',
  `monitoring_fee_paid` decimal(15,2) DEFAULT '0.00',
  `monitoring_fee_variance` decimal(15,2) DEFAULT '0.00',
  `penalty_expected` decimal(15,2) DEFAULT '0.00',
  `penalty_paid` decimal(15,2) DEFAULT '0.00',
  `penalty_variance` decimal(15,2) DEFAULT '0.00',
  `unallocated_balance` decimal(15,2) DEFAULT '0.00',
  `allocated_balance` decimal(15,2) DEFAULT '0.00',
  `is_prepayment` tinyint(1) DEFAULT '0',
  `instalments_covered` int(11) DEFAULT '0',
  `prepayment_discount` decimal(15,2) DEFAULT '0.00',
  `status` enum('pending','allocated','partially_allocated','refunded','carried_forward') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `allocation_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_portfolio`
--

CREATE TABLE `loan_portfolio` (
  `loan_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `loan_number` varchar(50) NOT NULL,
  `loan_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Principal amount given to customer',
  `management_fee_rate` decimal(5,2) NOT NULL DEFAULT '5.50' COMMENT 'Management fee percentage (5.5%)',
  `management_fee_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Management fee (5.5% of loan amount)',
  `total_disbursed` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Loan amount + Management fee',
  `interest_rate` decimal(5,2) NOT NULL COMMENT 'Monthly interest rate percentage',
  `number_of_instalments` int(11) NOT NULL,
  `disbursement_date` date NOT NULL,
  `maturity_date` date NOT NULL,
  `total_interest` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Sum of all interest payments',
  `total_management_fees` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Total management fees across all instalments',
  `total_payment` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Total to be paid (principal + interest + mgmt fees)',
  `monthly_payment` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Fixed monthly payment amount (after 1st instalment)',
  `principal_outstanding` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Remaining principal balance',
  `interest_outstanding` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Remaining interest to be paid',
  `total_outstanding` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Total remaining balance',
  `total_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_principal_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_interest_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_management_fees_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `accrued_interest` decimal(15,2) NOT NULL DEFAULT '0.00',
  `accrued_days` int(11) NOT NULL DEFAULT '0',
  `accrued_management_fees` decimal(15,2) NOT NULL DEFAULT '0.00',
  `deferred_management_fees` decimal(15,2) NOT NULL DEFAULT '0.00',
  `days_overdue` int(11) NOT NULL DEFAULT '0',
  `penalties` decimal(15,2) NOT NULL DEFAULT '0.00',
  `cash_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `bank_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `collateral_type` varchar(50) DEFAULT NULL,
  `collateral_description` text,
  `collateral_value` decimal(15,2) DEFAULT '0.00',
  `collateral_net_value` decimal(15,2) DEFAULT '0.00',
  `provisional_rate` decimal(5,2) NOT NULL DEFAULT '1.00',
  `general_provision` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_book_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `loan_status` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_provision_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `loan_portfolio`
--

INSERT INTO `loan_portfolio` (`loan_id`, `customer_id`, `loan_number`, `loan_amount`, `management_fee_rate`, `management_fee_amount`, `total_disbursed`, `interest_rate`, `number_of_instalments`, `disbursement_date`, `maturity_date`, `total_interest`, `total_management_fees`, `total_payment`, `monthly_payment`, `principal_outstanding`, `interest_outstanding`, `total_outstanding`, `total_paid`, `total_principal_paid`, `total_interest_paid`, `total_management_fees_paid`, `accrued_interest`, `accrued_days`, `accrued_management_fees`, `deferred_management_fees`, `days_overdue`, `penalties`, `cash_amount`, `bank_amount`, `collateral_type`, `collateral_description`, `collateral_value`, `collateral_net_value`, `provisional_rate`, `general_provision`, `net_book_value`, `loan_status`, `created_by`, `created_at`, `updated_at`, `last_provision_date`) VALUES
(26, 63, 'LN-20260210-044117', 9450000.00, 5.50, 550000.00, 10000000.00, 5.00, 3, '2026-01-27', '2026-04-26', 1016256.50, 1100000.00, 12116256.50, 4222090.00, 10000000.00, 1016256.50, 11016256.50, 0.00, 0.00, 0.00, 0.00, 0.00, 5, 0.00, 0.00, 0, 0.00, 0.00, 10000000.00, '', '', 0.00, 0.00, 1.00, 100000.00, 10916256.50, 'Active', 1, '2026-02-10 04:42:52', '2026-02-10 04:42:52', NULL),
(27, 118, 'LN-20260210-044355', 9450000.00, 5.50, 550000.00, 10000000.00, 5.00, 4, '2025-11-18', '2026-03-17', 1280473.50, 1650000.00, 12930473.50, 3370115.00, 10000000.00, 1280473.50, 11280473.50, 0.00, 0.00, 0.00, 0.00, 0.00, 13, 0.00, 0.00, 0, 0.00, 0.00, 10000000.00, '', '', 0.00, 0.00, 1.00, 100000.00, 11180473.50, 'Active', 1, '2026-02-10 04:45:07', '2026-02-10 04:45:07', NULL),
(30, 66, 'LN-20260210-045246', 1606500.00, 5.50, 93500.00, 1700000.00, 5.00, 4, '2026-01-21', '2026-05-20', 217680.50, 280500.00, 2198180.50, 572920.00, 1700000.00, 217680.50, 1917680.50, 0.00, 0.00, 0.00, 0.00, 0.00, 11, 0.00, 0.00, 0, 0.00, 0.00, 1700000.00, '', '', 0.00, 0.00, 1.00, 17000.00, 1900680.50, 'Active', 1, '2026-02-10 04:52:57', '2026-02-10 04:52:57', NULL),
(32, 67, 'LN-20260210-045738', 3260250.00, 5.50, 189750.00, 3450000.00, 5.00, 6, '2026-01-20', '2026-07-19', 628261.50, 948750.00, 5027011.50, 869460.00, 3450000.00, 628261.50, 4078261.50, 0.00, 0.00, 0.00, 0.00, 0.00, 12, 0.00, 0.00, 0, 0.00, 0.00, 3450000.00, '', '', 0.00, 0.00, 1.00, 34500.00, 4043761.50, 'Active', 1, '2026-02-10 04:58:57', '2026-02-10 04:58:57', NULL),
(33, 71, 'LN-20260210-045901', 300000.00, 0.00, 0.00, 300000.00, 5.00, 3, '2025-12-01', '2026-03-01', 30487.71, 0.00, 330487.71, 110162.57, 300000.00, 30487.71, 330487.71, 0.00, 0.00, 0.00, 0.00, 0.00, 31, 0.00, 0.00, 0, 0.00, 0.00, 300000.00, '', '', 0.00, 0.00, 1.00, 3000.00, 327487.71, 'Active', 1, '2026-02-10 05:00:10', '2026-02-13 15:00:34', NULL),
(34, 68, 'LN-20260210-050013', 1890000.00, 5.50, 110000.00, 2000000.00, 5.00, 4, '2026-01-09', '2026-05-08', 256095.50, 330000.00, 2586095.50, 674025.00, 2000000.00, 256095.50, 2256095.50, 0.00, 0.00, 0.00, 0.00, 0.00, 23, 0.00, 0.00, 0, 0.00, 0.00, 2000000.00, '', '', 0.00, 0.00, 1.00, 20000.00, 2236095.50, 'Active', 1, '2026-02-10 05:02:08', '2026-02-10 05:02:08', NULL),
(35, 152, 'LN-20260210-050505', 2295405.00, 5.50, 133595.00, 2429000.00, 5.00, 2, '2025-07-05', '2025-09-04', 183656.00, 133595.00, 2746251.00, 0.00, 2429000.00, 183656.00, 2612656.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27, 0.00, 0.00, 0, 0.00, 0.00, 2429000.00, '', '', 0.00, 0.00, 1.00, 24290.00, 2588366.00, 'Active', 1, '2026-02-10 05:08:54', '2026-02-10 05:08:54', NULL),
(36, 122, 'LN-20260210-050858', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 3, '2025-11-17', '2026-02-16', 101625.50, 110000.00, 1211625.50, 422210.00, 1000000.00, 101625.50, 1101625.50, 0.00, 0.00, 0.00, 0.00, 0.00, 14, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1091625.50, 'Active', 1, '2026-02-10 05:11:04', '2026-02-10 05:11:04', NULL),
(37, 103, 'LN-20260210-051123', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 4, '2025-12-02', '2026-04-01', 128048.00, 165000.00, 1293038.00, 337010.00, 1000000.00, 128048.00, 1128048.00, 0.00, 0.00, 0.00, 0.00, 0.00, 30, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1118048.00, 'Active', 1, '2026-02-10 05:12:15', '2026-02-10 05:12:15', NULL),
(38, 85, 'LN-20260210-051232', 2835000.00, 5.50, 165000.00, 3000000.00, 5.00, 3, '2025-12-30', '2026-03-29', 304876.50, 330000.00, 3634886.50, 1266630.00, 3000000.00, 304876.50, 3304876.50, 0.00, 0.00, 0.00, 0.00, 0.00, 2, 0.00, 0.00, 0, 0.00, 0.00, 3000000.00, '', '', 0.00, 0.00, 1.00, 30000.00, 3274876.50, 'Active', 1, '2026-02-10 05:13:56', '2026-02-10 05:13:56', NULL),
(39, 69, 'LN-20260210-064151', 1020600.00, 5.50, 59400.00, 1080000.00, 5.00, 2, '2025-10-08', '2025-12-08', 81658.54, 59400.00, 1221058.54, 0.00, 1080000.00, 81658.54, 1161658.54, 0.00, 0.00, 0.00, 0.00, 0.00, 24, 0.00, 0.00, 0, 0.00, 0.00, 1080000.00, '', '', 0.00, 0.00, 1.00, 10800.00, 1150858.54, 'Closed', 1, '2026-02-10 06:42:55', '2026-02-13 14:17:10', NULL),
(40, 153, 'LN-20260210-065225', 1701000.00, 5.50, 99000.00, 1800000.00, 5.00, 3, '2026-01-05', '2026-04-04', 182926.00, 198000.00, 2180926.00, 759970.00, 1800000.00, 182926.00, 1982926.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27, 0.00, 0.00, 0, 0.00, 0.00, 1800000.00, '', '', 0.00, 0.00, 1.00, 18000.00, 1964926.00, 'Active', 1, '2026-02-10 06:53:07', '2026-02-10 06:53:07', NULL),
(41, 90, 'LN-20260210-065311', 6615000.00, 5.50, 385000.00, 7000000.00, 5.00, 3, '2025-12-17', '2026-03-16', 711380.00, 770000.00, 8481380.00, 2955460.00, 7000000.00, 711380.00, 7711380.00, 0.00, 0.00, 0.00, 0.00, 0.00, 15, 0.00, 0.00, 0, 0.00, 0.00, 7000000.00, '', '', 0.00, 0.00, 1.00, 70000.00, 7641380.00, 'Active', 1, '2026-02-10 06:54:32', '2026-02-10 06:54:32', NULL),
(42, 82, 'LN-20260210-065458', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 3, '2025-12-15', '2026-03-14', 101625.50, 110000.00, 1211625.50, 422210.00, 1000000.00, 101625.50, 1101625.50, 0.00, 0.00, 0.00, 0.00, 0.00, 17, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1091625.50, 'Active', 1, '2026-02-10 06:56:10', '2026-02-10 06:56:10', NULL),
(43, 112, 'LN-20260210-065730', 661500.00, 5.50, 38500.00, 700000.00, 5.00, 4, '2025-11-24', '2026-03-24', 89633.13, 115500.00, 905133.13, 235908.29, 700000.00, 89633.13, 789633.13, 0.00, 0.00, 0.00, 0.00, 0.00, 7, 0.00, 0.00, 0, 0.00, 0.00, 700000.00, '', 'UNSECURED', 0.00, 0.00, 1.00, 7000.00, 782633.13, 'Active', 1, '2026-02-10 06:57:55', '2026-02-13 13:06:32', NULL),
(44, 120, 'LN-20260210-065804', 567000.00, 5.50, 33000.00, 600000.00, 5.00, 2, '2026-01-05', '2026-03-04', 45366.00, 33000.00, 678366.00, 0.00, 600000.00, 45366.00, 645366.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27, 0.00, 0.00, 0, 0.00, 0.00, 600000.00, '', '', 0.00, 0.00, 1.00, 6000.00, 639366.00, 'Active', 1, '2026-02-10 06:58:47', '2026-02-10 06:58:47', NULL),
(45, 138, 'LN-20260210-065857', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 3, '2025-11-01', '2026-01-31', 101625.50, 110000.00, 1211625.50, 422210.00, 1000000.00, 101625.50, 1101625.50, 0.00, 0.00, 0.00, 0.00, 0.00, 30, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1091625.50, 'Closed', 1, '2026-02-10 07:00:32', '2026-02-13 12:58:14', NULL),
(46, 144, 'LN-20260210-070626', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 3, '2026-01-08', '2026-04-07', 101625.50, 110000.00, 1211625.50, 422210.00, 1000000.00, 101625.50, 1101625.50, 0.00, 0.00, 0.00, 0.00, 0.00, 24, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1091625.50, 'Active', 1, '2026-02-10 07:07:36', '2026-02-10 07:07:36', NULL),
(47, 99, 'LN-20260210-070746', 661500.00, 5.50, 38500.00, 700000.00, 5.00, 4, '2025-12-02', '2026-04-01', 89632.50, 115500.00, 905142.50, 235910.00, 700000.00, 89632.50, 789632.50, 0.00, 0.00, 0.00, 0.00, 0.00, 30, 0.00, 0.00, 0, 0.00, 0.00, 700000.00, '', '', 0.00, 0.00, 1.00, 7000.00, 782632.50, 'Closed', 1, '2026-02-10 07:08:59', '2026-02-13 12:52:19', NULL),
(48, 136, 'LN-20260210-070953', 5197500.00, 5.50, 302500.00, 5500000.00, 5.00, 3, '2025-09-11', '2025-12-10', 558941.00, 605000.00, 6663941.00, 2322150.00, 5500000.00, 558941.00, 6058941.00, 0.00, 0.00, 0.00, 0.00, 0.00, 20, 0.00, 0.00, 0, 0.00, 0.00, 5500000.00, '', '', 0.00, 0.00, 1.00, 55000.00, 6003941.00, 'Active', 1, '2026-02-10 07:10:34', '2026-02-10 07:10:34', NULL),
(49, 124, 'LN-20260210-071043', 1890000.00, 5.50, 110000.00, 2000000.00, 5.00, 1, '2025-08-22', '2025-09-21', 100000.00, 0.00, 2100000.00, 0.00, 2000000.00, 100000.00, 2100000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 10, 0.00, 0.00, 0, 0.00, 0.00, 2000000.00, '', '', 0.00, 0.00, 1.00, 20000.00, 2080000.00, 'Active', 1, '2026-02-10 07:11:49', '2026-02-10 07:11:49', NULL),
(50, 83, 'LN-20260210-071213', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 4, '2025-12-15', '2026-04-14', 128048.00, 165000.00, 1293038.00, 337010.00, 1000000.00, 128048.00, 1128048.00, 0.00, 0.00, 0.00, 0.00, 0.00, 17, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1118048.00, 'Active', 1, '2026-02-10 07:13:12', '2026-02-10 07:13:12', NULL),
(51, 101, 'LN-20260210-071329', 1417500.00, 5.50, 82500.00, 1500000.00, 5.00, 2, '2025-12-02', '2026-02-01', 113414.50, 82500.00, 1695914.50, 0.00, 1500000.00, 113414.50, 1613414.50, 0.00, 0.00, 0.00, 0.00, 0.00, 30, 0.00, 0.00, 0, 0.00, 0.00, 1500000.00, '', '', 0.00, 0.00, 1.00, 15000.00, 1598414.50, 'Closed', 1, '2026-02-10 07:14:31', '2026-02-17 15:43:09', NULL),
(52, 113, 'LN-20260210-195138', 2995650.00, 5.50, 174350.00, 3170000.00, 5.00, 2, '2025-09-12', '2025-11-11', 239683.00, 174350.00, 3584033.00, 0.00, 3170000.00, 239683.00, 3409683.00, 0.00, 0.00, 0.00, 0.00, 0.00, 19, 0.00, 0.00, 0, 0.00, 0.00, 3170000.00, '', '', 0.00, 0.00, 1.00, 31700.00, 3377983.00, 'Written-off', 1, '2026-02-10 19:52:47', '2026-02-13 12:47:50', NULL),
(53, 143, 'LN-20260210-195307', 567000.00, 5.50, 33000.00, 600000.00, 5.00, 4, '2025-10-17', '2026-02-16', 76827.50, 99000.00, 775837.50, 202210.00, 600000.00, 76827.50, 676827.50, 0.00, 0.00, 0.00, 0.00, 0.00, 15, 0.00, 0.00, 0, 0.00, 0.00, 600000.00, '', '', 0.00, 0.00, 1.00, 6000.00, 670827.50, 'Active', 1, '2026-02-10 19:54:21', '2026-02-10 19:54:21', NULL),
(54, 129, 'LN-20260210-195457', 4252500.00, 5.50, 247500.00, 4500000.00, 5.00, 6, '2025-09-10', '2026-03-09', 819471.00, 1237500.00, 6556971.00, 1134080.00, 4500000.00, 819471.00, 5319471.00, 0.00, 0.00, 0.00, 0.00, 0.00, 21, 0.00, 0.00, 0, 0.00, 0.00, 4500000.00, '', '', 0.00, 0.00, 1.00, 45000.00, 5274471.00, 'Active', 1, '2026-02-10 19:55:55', '2026-02-10 19:55:55', NULL),
(55, 126, 'LN-20260210-195620', 170100.00, 5.50, 9900.00, 180000.00, 5.00, 5, '2025-11-15', '2026-04-14', 27877.50, 39600.00, 247477.50, 51473.33, 180000.00, 27877.50, 207877.50, 0.00, 0.00, 0.00, 0.00, 0.00, 16, 0.00, 0.00, 0, 0.00, 0.00, 180000.00, '', '', 0.00, 0.00, 1.00, 1800.00, 206077.50, 'Active', 1, '2026-02-10 19:57:33', '2026-02-10 19:57:33', NULL),
(56, 107, 'LN-20260210-195746', 472500.00, 5.50, 27500.00, 500000.00, 5.00, 3, '2025-11-27', '2026-02-26', 50813.50, 55000.00, 605803.50, 211100.00, 500000.00, 50813.50, 550813.50, 0.00, 0.00, 0.00, 0.00, 0.00, 4, 0.00, 0.00, 0, 0.00, 0.00, 500000.00, '', '', 0.00, 0.00, 1.00, 5000.00, 545813.50, 'Active', 1, '2026-02-10 19:58:33', '2026-02-10 19:58:33', NULL),
(57, 86, 'LN-20260210-195848', 14442749.68, 5.50, 840583.32, 15283333.00, 5.00, 3, '2025-09-12', '2025-12-11', 1553179.95, 1681166.64, 18517666.59, 6452750.00, 15283333.00, 1553179.95, 16836512.95, 0.00, 0.00, 0.00, 0.00, 0.00, 19, 0.00, 0.00, 0, 0.00, 0.00, 15283333.00, '', '', 0.00, 0.00, 1.00, 152833.33, 16683679.62, 'Active', 1, '2026-02-10 19:59:51', '2026-02-10 19:59:51', NULL),
(58, 108, 'LN-20260210-200003', 1417500.00, 5.50, 82500.00, 1500000.00, 5.00, 2, '2025-11-18', '2026-01-17', 113414.63, 82500.00, 1695914.63, 0.00, 1500000.00, 113414.63, 1613414.63, 0.00, 0.00, 0.00, 0.00, 0.00, 13, 0.00, 0.00, 0, 0.00, 0.00, 1500000.00, '', '', 0.00, 0.00, 1.00, 15000.00, 1598414.63, 'Active', 1, '2026-02-10 20:10:46', '2026-02-11 16:47:48', NULL),
(59, 116, 'LN-20260210-201109', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 4, '2025-11-19', '2026-03-18', 128048.00, 165000.00, 1293038.00, 337010.00, 1000000.00, 128048.00, 1128048.00, 0.00, 0.00, 0.00, 0.00, 0.00, 12, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1118048.00, 'Active', 1, '2026-02-10 20:11:47', '2026-02-10 20:11:47', NULL),
(60, 147, 'LN-20260210-201313', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 5, '2025-11-05', '2026-04-04', 154876.00, 220000.00, 1374866.00, 285970.00, 1000000.00, 154876.00, 1154876.00, 0.00, 0.00, 0.00, 0.00, 0.00, 26, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1144876.00, 'Active', 1, '2026-02-10 20:14:09', '2026-02-10 20:14:09', NULL),
(61, 134, 'LN-20260210-201441', 4725000.00, 5.50, 275000.00, 5000000.00, 5.00, 3, '2025-12-22', '2026-03-21', 508129.00, 550000.00, 6058119.00, 2111040.00, 5000000.00, 508129.00, 5508129.00, 0.00, 0.00, 0.00, 0.00, 0.00, 10, 0.00, 0.00, 0, 0.00, 0.00, 5000000.00, '', '', 0.00, 0.00, 1.00, 50000.00, 5458129.00, 'Active', 1, '2026-02-10 20:15:30', '2026-02-10 20:15:30', NULL),
(62, 100, 'LN-20260210-201540', 2835000.00, 5.50, 165000.00, 3000000.00, 5.00, 3, '2025-08-04', '2025-11-03', 304876.50, 330000.00, 3634886.50, 1266630.00, 3000000.00, 304876.50, 3304876.50, 0.00, 0.00, 0.00, 0.00, 0.00, 28, 0.00, 0.00, 0, 0.00, 0.00, 3000000.00, '', '', 0.00, 0.00, 1.00, 30000.00, 3274876.50, 'Active', 1, '2026-02-10 20:16:28', '2026-02-10 20:16:28', NULL),
(63, 72, 'LN-20260210-201640', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 3, '2025-12-12', '2026-03-11', 101625.50, 110000.00, 1211625.50, 422210.00, 1000000.00, 101625.50, 1101625.50, 0.00, 0.00, 0.00, 0.00, 0.00, 20, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1091625.50, 'Active', 1, '2026-02-10 20:18:09', '2026-02-10 20:18:09', NULL),
(64, 73, 'LN-20260210-201822', 472500.00, 5.50, 27500.00, 500000.00, 5.00, 3, '2026-01-16', '2026-04-15', 50813.50, 55000.00, 605803.50, 211100.00, 500000.00, 50813.50, 550813.50, 0.00, 0.00, 0.00, 0.00, 0.00, 16, 0.00, 0.00, 0, 0.00, 0.00, 500000.00, '', '', 0.00, 0.00, 1.00, 5000.00, 545813.50, 'Active', 1, '2026-02-10 20:19:12', '2026-02-10 20:19:12', NULL),
(65, 123, 'LN-20260210-201919', 4725000.00, 5.50, 275000.00, 5000000.00, 5.00, 4, '2025-11-17', '2026-03-16', 640236.50, 825000.00, 6465236.50, 1685060.00, 5000000.00, 640236.50, 5640236.50, 0.00, 0.00, 0.00, 0.00, 0.00, 14, 0.00, 0.00, 0, 0.00, 0.00, 5000000.00, '', '', 0.00, 0.00, 1.00, 50000.00, 5590236.50, 'Active', 1, '2026-02-10 20:23:52', '2026-02-10 20:23:52', NULL),
(66, 89, 'LN-20260210-202436', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 3, '2025-12-08', '2026-03-07', 101625.50, 110000.00, 1211625.50, 422210.00, 1000000.00, 101625.50, 1101625.50, 0.00, 0.00, 0.00, 0.00, 0.00, 24, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1091625.50, 'Active', 1, '2026-02-10 20:24:56', '2026-02-10 20:24:56', NULL),
(67, 102, 'LN-20260210-202503', 3844273.23, 5.50, 223740.77, 4068014.00, 5.00, 3, '2025-11-01', '2026-01-31', 413414.60, 447481.54, 4928916.14, 1717550.00, 4068014.00, 413414.60, 4481428.60, 0.00, 0.00, 0.00, 0.00, 0.00, 30, 0.00, 0.00, 0, 0.00, 0.00, 4068014.00, '', '', 0.00, 0.00, 1.00, 40680.14, 4440748.46, 'Written-off', 1, '2026-02-10 20:26:41', '2026-02-13 12:03:16', NULL),
(69, 75, 'LN-20260210-202825', 3780000.00, 5.50, 220000.00, 4000000.00, 5.00, 3, '2025-08-18', '2025-11-17', 406503.00, 440000.00, 4846503.00, 1688840.00, 4000000.00, 406503.00, 4406503.00, 0.00, 0.00, 0.00, 0.00, 0.00, 14, 0.00, 0.00, 0, 0.00, 0.00, 4000000.00, '', '', 0.00, 0.00, 1.00, 40000.00, 4366503.00, 'Written-off', 1, '2026-02-10 20:29:20', '2026-02-13 08:14:46', NULL),
(70, 106, 'LN-20260210-202931', 897750.00, 5.50, 52250.00, 950000.00, 5.00, 3, '2025-09-26', '2025-12-25', 96544.00, 104500.00, 1151054.00, 401100.00, 950000.00, 96544.00, 1046544.00, 0.00, 0.00, 0.00, 0.00, 0.00, 5, 0.00, 0.00, 0, 0.00, 0.00, 950000.00, '', '', 0.00, 0.00, 1.00, 9500.00, 1037044.00, 'Closed', 1, '2026-02-10 20:31:00', '2026-02-13 11:59:02', NULL),
(71, 139, 'LN-20260210-203110', 4725000.00, 5.50, 275000.00, 5000000.00, 5.00, 3, '2026-01-05', '2026-04-04', 508129.00, 550000.00, 6058119.00, 2111040.00, 5000000.00, 508129.00, 5508129.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27, 0.00, 0.00, 0, 0.00, 0.00, 5000000.00, '', '', 0.00, 0.00, 1.00, 50000.00, 5458129.00, 'Active', 1, '2026-02-10 20:31:59', '2026-02-10 20:31:59', NULL),
(72, 94, 'LN-20260210-203213', 661500.00, 5.50, 38500.00, 700000.00, 5.00, 4, '2025-12-17', '2026-04-16', 89632.50, 115500.00, 905142.50, 235910.00, 700000.00, 89632.50, 789632.50, 0.00, 0.00, 0.00, 0.00, 0.00, 15, 0.00, 0.00, 0, 0.00, 0.00, 700000.00, '', '', 0.00, 0.00, 1.00, 7000.00, 782632.50, 'Active', 1, '2026-02-10 20:33:44', '2026-02-10 20:33:44', NULL),
(73, 95, 'LN-20260210-203413', 4725000.00, 5.50, 275000.00, 5000000.00, 5.00, 4, '2025-12-05', '2026-04-04', 640236.50, 825000.00, 6465236.50, 1685060.00, 5000000.00, 640236.50, 5640236.50, 0.00, 0.00, 0.00, 0.00, 0.00, 27, 0.00, 0.00, 0, 0.00, 0.00, 5000000.00, '', '', 0.00, 0.00, 1.00, 50000.00, 5590236.50, 'Active', 1, '2026-02-10 20:35:04', '2026-02-10 20:35:04', NULL),
(74, 105, 'LN-20260210-203513', 283500.00, 5.50, 16500.00, 300000.00, 5.00, 2, '2025-12-01', '2026-01-31', 22683.00, 16500.00, 339183.00, 0.00, 300000.00, 22683.00, 322683.00, 0.00, 0.00, 0.00, 0.00, 0.00, 31, 0.00, 0.00, 0, 0.00, 0.00, 300000.00, '', '', 0.00, 0.00, 1.00, 3000.00, 319683.00, 'Closed', 1, '2026-02-10 20:37:42', '2026-02-13 11:50:56', NULL),
(75, 115, 'LN-20260210-203750', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 4, '2025-11-20', '2026-03-19', 128047.34, 165000.00, 1293047.34, 337011.84, 1000000.00, 128047.34, 1128047.34, 0.00, 0.00, 0.00, 0.00, 0.00, 11, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1118047.34, 'Active', 1, '2026-02-10 20:40:00', '2026-02-17 12:42:55', NULL),
(76, 93, 'LN-20260210-204014', 661500.00, 5.50, 38500.00, 700000.00, 5.00, 4, '2025-12-18', '2026-04-17', 89632.50, 115500.00, 905142.50, 235910.00, 700000.00, 89632.50, 789632.50, 0.00, 0.00, 0.00, 0.00, 0.00, 14, 0.00, 0.00, 0, 0.00, 0.00, 700000.00, '', '', 0.00, 0.00, 1.00, 7000.00, 782632.50, 'Active', 1, '2026-02-10 20:40:51', '2026-02-10 20:40:51', NULL),
(77, 133, 'LN-20260210-204109', 7560000.00, 5.50, 440000.00, 8000000.00, 5.00, 5, '2025-11-10', '2026-04-09', 1238991.00, 1760000.00, 10999001.00, 2287800.00, 8000000.00, 1238991.00, 9238991.00, 0.00, 0.00, 0.00, 0.00, 0.00, 21, 0.00, 0.00, 0, 0.00, 0.00, 8000000.00, '', '', 0.00, 0.00, 1.00, 80000.00, 9158991.00, 'Active', 1, '2026-02-10 20:42:07', '2026-02-10 20:42:07', NULL),
(78, 97, 'LN-20260210-204222', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 3, '2025-12-04', '2026-03-03', 101625.50, 110000.00, 1211625.50, 422210.00, 1000000.00, 101625.50, 1101625.50, 0.00, 0.00, 0.00, 0.00, 0.00, 28, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1091625.50, 'Active', 1, '2026-02-10 20:43:19', '2026-02-10 20:43:19', NULL),
(79, 76, 'LN-20260210-204420', 283500.00, 5.50, 16500.00, 300000.00, 5.00, 4, '2025-11-13', '2026-03-12', 38415.00, 49500.00, 387905.00, 101105.00, 300000.00, 38415.00, 338415.00, 0.00, 0.00, 0.00, 0.00, 0.00, 18, 0.00, 0.00, 0, 0.00, 0.00, 300000.00, '', '', 0.00, 0.00, 1.00, 3000.00, 335415.00, 'Active', 1, '2026-02-10 20:45:17', '2026-02-10 20:45:17', NULL),
(80, 131, 'LN-20260210-204526', 1134000.00, 5.50, 66000.00, 1200000.00, 5.00, 5, '2025-11-12', '2026-04-11', 185848.50, 264000.00, 1649848.50, 343170.00, 1200000.00, 185848.50, 1385848.50, 0.00, 0.00, 0.00, 0.00, 0.00, 19, 0.00, 0.00, 0, 0.00, 0.00, 1200000.00, '', '', 0.00, 0.00, 1.00, 12000.00, 1373848.50, 'Active', 1, '2026-02-10 20:46:20', '2026-02-10 20:46:20', NULL),
(81, 121, 'LN-20260210-204627', 2835000.00, 5.50, 165000.00, 3000000.00, 5.00, 1, '2026-01-15', '2026-02-14', 150000.00, 0.00, 3150000.00, 0.00, 3000000.00, 150000.00, 3150000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 17, 0.00, 0.00, 0, 0.00, 0.00, 3000000.00, '', '', 0.00, 0.00, 1.00, 30000.00, 3120000.00, 'Closed', 1, '2026-02-10 20:47:43', '2026-02-16 07:36:44', NULL),
(82, 96, 'LN-20260210-204759', 756000.00, 5.50, 44000.00, 800000.00, 5.00, 1, '2025-12-22', '2026-01-21', 40000.00, 0.00, 840000.00, 0.00, 800000.00, 40000.00, 840000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 10, 0.00, 0.00, 0, 0.00, 0.00, 800000.00, '', '', 0.00, 0.00, 1.00, 8000.00, 832000.00, 'Active', 1, '2026-02-10 20:49:44', '2026-02-10 20:49:44', NULL),
(83, 141, 'LN-20260210-204952', 3780000.00, 5.50, 220000.00, 4000000.00, 5.00, 2, '2025-12-15', '2026-02-14', 302439.00, 220000.00, 4522439.00, 0.00, 4000000.00, 302439.00, 4302439.00, 0.00, 0.00, 0.00, 0.00, 0.00, 17, 0.00, 0.00, 0, 0.00, 0.00, 4000000.00, '', '', 0.00, 0.00, 1.00, 40000.00, 4262439.00, 'Closed', 1, '2026-02-10 20:53:58', '2026-02-16 14:53:32', NULL),
(84, 77, 'LN-20260210-205440', 1890000.00, 5.50, 110000.00, 2000000.00, 5.00, 3, '2026-01-13', '2026-04-12', 203251.00, 220000.00, 2423251.00, 844420.00, 2000000.00, 203251.00, 2203251.00, 0.00, 0.00, 0.00, 0.00, 0.00, 19, 0.00, 0.00, 0, 0.00, 0.00, 2000000.00, '', '', 0.00, 0.00, 1.00, 20000.00, 2183251.00, 'Active', 1, '2026-02-10 20:55:00', '2026-02-10 20:55:00', NULL),
(85, 114, 'LN-20260210-205512', 1890000.00, 5.50, 110000.00, 2000000.00, 5.00, 3, '2026-01-13', '2026-04-12', 203251.00, 220000.00, 2423251.00, 844420.00, 2000000.00, 203251.00, 2203251.00, 0.00, 0.00, 0.00, 0.00, 0.00, 19, 0.00, 0.00, 0, 0.00, 0.00, 2000000.00, '', '', 0.00, 0.00, 1.00, 20000.00, 2183251.00, 'Active', 1, '2026-02-10 20:55:58', '2026-02-10 20:55:58', NULL),
(88, 87, 'LN-20260210-205936', 19939500.00, 5.50, 1160500.00, 21100000.00, 5.00, 1, '2026-01-12', '2026-02-11', 1055000.00, 0.00, 22155000.00, 0.00, 21100000.00, 1055000.00, 22155000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 20, 0.00, 0.00, 0, 0.00, 0.00, 21100000.00, '', '', 0.00, 0.00, 1.00, 211000.00, 21944000.00, 'Closed', 1, '2026-02-10 21:01:21', '2026-02-13 08:14:04', NULL),
(91, 79, 'LN-20260211-121925', 18900000.00, 5.50, 1100000.00, 20000000.00, 5.00, 3, '2026-01-13', '2026-04-13', 2032513.88, 2200000.00, 24232513.88, 8444171.30, 20000000.00, 2032513.88, 22032513.88, 0.00, 0.00, 0.00, 0.00, 0.00, 19, 0.00, 0.00, 0, 0.00, 0.00, 20000000.00, 'Land', '', 0.00, 0.00, 1.00, 200000.00, 21832513.88, 'Active', 1, '2026-02-11 12:53:47', '2026-02-11 13:20:30', NULL),
(92, 78, 'LN-20260211-131649', 1235000.00, 5.00, 65000.00, 1300000.00, 5.00, 5, '2026-01-12', '2026-06-12', 201336.00, 260000.00, 1761336.00, 365270.00, 1300000.00, 201336.00, 1501336.00, 0.00, 0.00, 0.00, 0.00, 0.00, 20, 0.00, 0.00, 0, 0.00, 0.00, 1300000.00, '', '', 0.00, 0.00, 1.00, 13000.00, 1488336.00, 'Active', 1, '2026-02-11 13:19:09', '2026-02-11 13:19:09', NULL),
(93, 101, 'LN-20260211-142242', 1417500.00, 5.50, 82500.00, 1500000.00, 5.00, 2, '2026-02-11', '2026-04-11', 113414.50, 82500.00, 1695914.50, 0.00, 1500000.00, 113414.50, 1613414.50, 0.00, 0.00, 0.00, 0.00, 0.00, 18, 0.00, 0.00, 0, 0.00, 0.00, 1500000.00, 'Vehicle', '', 0.00, 0.00, 1.00, 15000.00, 1598414.50, 'Active', 1, '2026-02-11 14:25:22', '2026-02-12 06:37:43', NULL),
(95, 154, 'LN-20260213-074840', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 3, '2026-02-12', '2026-05-12', 101625.50, 110000.00, 1211625.50, 422210.00, 1000000.00, 101625.50, 1101625.50, 0.00, 0.00, 0.00, 0.00, 0.00, 17, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, 'Land', '', 0.00, 0.00, 1.00, 10000.00, 1091625.50, 'Active', 1, '2026-02-13 07:51:22', '2026-02-13 07:51:22', NULL),
(96, 74, 'LN-20260213-082655', 11340000.00, 5.50, 660000.00, 12000000.00, 5.00, 6, '2026-01-15', '2026-07-15', 2185258.00, 3300000.00, 17485258.00, 3024210.00, 12000000.00, 2185258.00, 14185258.00, 0.00, 0.00, 0.00, 0.00, 0.00, 17, 0.00, 0.00, 0, 0.00, 0.00, 12000000.00, 'Land', '', 0.00, 0.00, 1.00, 120000.00, 14065258.00, 'Active', 1, '2026-02-13 08:27:59', '2026-02-13 08:27:59', NULL),
(97, 81, 'LN-20260213-082810', 25785000.00, 4.50, 1215000.00, 27000000.00, 5.00, 4, '2026-01-08', '2026-05-08', 3457277.50, 3645000.00, 34102277.50, 8829320.00, 27000000.00, 3457277.50, 30457277.50, 0.00, 0.00, 0.00, 0.00, 0.00, 24, 0.00, 0.00, 0, 0.00, 0.00, 27000000.00, 'Land', 'located in kicukiro', 0.00, 0.00, 1.00, 270000.00, 30187277.50, 'Active', 1, '2026-02-13 08:30:37', '2026-02-13 08:30:37', NULL),
(98, 155, 'LN-20260213-083938', 945000.00, 5.50, 55000.00, 1000000.00, 5.00, 6, '2026-02-10', '2026-08-10', 182103.50, 275000.00, 1457113.50, 252020.00, 1000000.00, 182103.50, 1182103.50, 0.00, 0.00, 0.00, 0.00, 0.00, 19, 0.00, 0.00, 0, 0.00, 0.00, 1000000.00, 'Other', 'no collateral', 0.00, 0.00, 1.00, 10000.00, 1172103.50, 'Active', 1, '2026-02-13 08:41:00', '2026-02-13 08:41:00', NULL),
(99, 65, 'LN-20260213-095208', 1140000.00, 5.00, 60000.00, 1200000.00, 5.00, 4, '2026-01-20', '2026-05-20', 153656.79, 180000.00, 1533656.79, 398414.20, 1200000.00, 153656.79, 1353656.79, 0.00, 0.00, 0.00, 0.00, 0.00, 12, 0.00, 0.00, 0, 0.00, 0.00, 1200000.00, '', 'UNSECURED', 0.00, 0.00, 1.00, 12000.00, 1341656.79, 'Active', 1, '2026-02-13 09:55:02', '2026-02-13 09:57:22', NULL),
(101, 106, 'LN-20260213-115907', 661500.00, 5.50, 38500.00, 700000.00, 5.00, 3, '2026-02-19', '2026-05-19', 71137.50, 77000.00, 848147.50, 295550.00, 700000.00, 71137.50, 771137.50, 0.00, 0.00, 0.00, 0.00, 0.00, 10, 0.00, 0.00, 0, 0.00, 0.00, 700000.00, '', 'UNSECURED', 0.00, 0.00, 1.00, 7000.00, 764137.50, 'Active', 1, '2026-02-13 12:00:32', '2026-02-13 12:00:32', NULL),
(102, 130, 'LN-20260213-155133', 472500.00, 5.50, 27500.00, 500000.00, 5.00, 3, '2025-11-13', '2026-02-13', 50813.50, 55000.00, 605803.50, 211100.00, 500000.00, 50813.50, 550813.50, 0.00, 0.00, 0.00, 0.00, 0.00, 18, 0.00, 0.00, 0, 0.00, 0.00, 500000.00, '', 'UNSECURED', 0.00, 0.00, 1.00, 5000.00, 545813.50, 'Active', 1, '2026-02-13 15:57:41', '2026-02-13 15:57:41', NULL),
(110, 80, 'LN-20260218-105127', 1890000.00, 5.50, 110000.00, 2000000.00, 5.00, 3, '2026-01-09', '2026-04-08', 203251.00, 220000.00, 2423251.00, 844420.00, 2000000.00, 203251.00, 2203251.00, 0.00, 0.00, 0.00, 0.00, 0.00, 23, 0.00, 0.00, 0, 0.00, 0.00, 2000000.00, '', '', 0.00, 0.00, 1.00, 20000.00, 2183251.00, 'Active', 1, '2026-02-18 10:53:42', '2026-02-18 12:53:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_transactions`
--

CREATE TABLE `loan_transactions` (
  `transaction_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `loan_number` varchar(50) NOT NULL,
  `transaction_type` enum('Disbursement','Payment','Fee','Adjustment','Write-off','Recovery') NOT NULL,
  `transaction_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `description` text,
  `reference_number` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `loan_transactions`
--

INSERT INTO `loan_transactions` (`transaction_id`, `loan_id`, `loan_number`, `transaction_type`, `transaction_date`, `amount`, `description`, `reference_number`, `created_by`, `created_at`) VALUES
(301, 313, 'LN-20260204-145420', 'Disbursement', '2026-02-04', 1059000.00, 'Loan disbursement', NULL, 1, '2026-02-04 13:58:55'),
(302, 314, 'LN-20260204-150647', 'Disbursement', '2026-02-04', 1015263.30, 'Loan disbursement', NULL, 1, '2026-02-04 14:06:51'),
(303, 315, 'LN-20260204-151315', 'Disbursement', '2026-02-04', 1015263.30, 'Loan disbursement', NULL, 1, '2026-02-04 14:13:19'),
(304, 316, 'LN-20260205-071053', 'Disbursement', '2026-02-05', 2118000.00, 'Loan disbursement', NULL, 1, '2026-02-05 07:11:09'),
(305, 317, 'LN-20260205-085709', 'Disbursement', '2026-02-05', 1588500.00, 'Loan disbursement', NULL, 1, '2026-02-05 08:58:06'),
(306, 318, 'LN-20260205-123141', 'Disbursement', '2026-02-05', 2000000.00, 'Loan disbursement', NULL, 1, '2026-02-05 12:40:03'),
(307, 319, 'LN-20260205-131311', 'Disbursement', '2026-02-05', 2000000.00, 'Loan disbursement', NULL, 1, '2026-02-05 13:13:25'),
(334, 26, 'LN-20260210-044117', 'Disbursement', '2026-01-27', 10000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 04:42:52'),
(335, 27, 'LN-20260210-044355', 'Disbursement', '2025-11-18', 10000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 04:45:07'),
(338, 30, 'LN-20260210-045246', 'Disbursement', '2026-01-21', 1700000.00, 'Loan disbursement', NULL, 1, '2026-02-10 04:52:57'),
(340, 32, 'LN-20260210-045738', 'Disbursement', '2026-01-20', 3450000.00, 'Loan disbursement', NULL, 1, '2026-02-10 04:58:57'),
(341, 33, 'LN-20260210-045901', 'Disbursement', '2025-12-01', 300000.00, 'Loan disbursement', NULL, 1, '2026-02-10 05:00:10'),
(342, 34, 'LN-20260210-050013', 'Disbursement', '2026-01-09', 2000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 05:02:08'),
(343, 35, 'LN-20260210-050505', 'Disbursement', '2025-07-05', 2429000.00, 'Loan disbursement', NULL, 1, '2026-02-10 05:08:54'),
(344, 36, 'LN-20260210-050858', 'Disbursement', '2025-11-17', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 05:11:04'),
(345, 37, 'LN-20260210-051123', 'Disbursement', '2025-12-02', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 05:12:15'),
(346, 38, 'LN-20260210-051232', 'Disbursement', '2025-12-30', 3000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 05:13:56'),
(347, 39, 'LN-20260210-064151', 'Disbursement', '2026-02-10', 1080000.00, 'Loan disbursement', NULL, 1, '2026-02-10 06:42:55'),
(348, 40, 'LN-20260210-065225', 'Disbursement', '2026-01-05', 1800000.00, 'Loan disbursement', NULL, 1, '2026-02-10 06:53:07'),
(349, 41, 'LN-20260210-065311', 'Disbursement', '2025-12-17', 7000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 06:54:32'),
(350, 42, 'LN-20260210-065458', 'Disbursement', '2025-12-15', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 06:56:10'),
(351, 43, 'LN-20260210-065730', 'Disbursement', '2025-11-21', 700000.00, 'Loan disbursement', NULL, 1, '2026-02-10 06:57:55'),
(352, 44, 'LN-20260210-065804', 'Disbursement', '2026-01-05', 600000.00, 'Loan disbursement', NULL, 1, '2026-02-10 06:58:47'),
(353, 45, 'LN-20260210-065857', 'Disbursement', '2025-11-01', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 07:00:32'),
(354, 46, 'LN-20260210-070626', 'Disbursement', '2026-01-08', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 07:07:36'),
(355, 47, 'LN-20260210-070746', 'Disbursement', '2025-12-02', 700000.00, 'Loan disbursement', NULL, 1, '2026-02-10 07:08:59'),
(356, 48, 'LN-20260210-070953', 'Disbursement', '2025-09-11', 5500000.00, 'Loan disbursement', NULL, 1, '2026-02-10 07:10:34'),
(357, 49, 'LN-20260210-071043', 'Disbursement', '2025-08-22', 2000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 07:11:49'),
(358, 50, 'LN-20260210-071213', 'Disbursement', '2025-12-15', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 07:13:12'),
(359, 51, 'LN-20260210-071329', 'Disbursement', '2025-12-02', 1500000.00, 'Loan disbursement', NULL, 1, '2026-02-10 07:14:31'),
(360, 52, 'LN-20260210-195138', 'Disbursement', '2025-09-12', 3170000.00, 'Loan disbursement', NULL, 1, '2026-02-10 19:52:47'),
(361, 53, 'LN-20260210-195307', 'Disbursement', '2025-10-17', 600000.00, 'Loan disbursement', NULL, 1, '2026-02-10 19:54:21'),
(362, 54, 'LN-20260210-195457', 'Disbursement', '2025-09-10', 4500000.00, 'Loan disbursement', NULL, 1, '2026-02-10 19:55:55'),
(363, 55, 'LN-20260210-195620', 'Disbursement', '2025-11-15', 180000.00, 'Loan disbursement', NULL, 1, '2026-02-10 19:57:33'),
(364, 56, 'LN-20260210-195746', 'Disbursement', '2025-11-27', 500000.00, 'Loan disbursement', NULL, 1, '2026-02-10 19:58:33'),
(365, 57, 'LN-20260210-195848', 'Disbursement', '2025-09-12', 15283333.00, 'Loan disbursement', NULL, 1, '2026-02-10 19:59:51'),
(366, 58, 'LN-20260210-200003', 'Disbursement', '2026-02-10', 1500000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:10:46'),
(367, 59, 'LN-20260210-201109', 'Disbursement', '2025-11-19', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:11:47'),
(368, 60, 'LN-20260210-201313', 'Disbursement', '2025-11-05', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:14:09'),
(369, 61, 'LN-20260210-201441', 'Disbursement', '2025-12-22', 5000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:15:30'),
(370, 62, 'LN-20260210-201540', 'Disbursement', '2025-08-04', 3000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:16:28'),
(371, 63, 'LN-20260210-201640', 'Disbursement', '2025-12-12', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:18:09'),
(372, 64, 'LN-20260210-201822', 'Disbursement', '2026-01-16', 500000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:19:12'),
(373, 65, 'LN-20260210-201919', 'Disbursement', '2025-11-17', 5000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:23:52'),
(374, 66, 'LN-20260210-202436', 'Disbursement', '2025-12-08', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:24:56'),
(375, 67, 'LN-20260210-202503', 'Disbursement', '2025-11-01', 4068014.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:26:41'),
(377, 69, 'LN-20260210-202825', 'Disbursement', '2025-08-18', 4000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:29:20'),
(378, 70, 'LN-20260210-202931', 'Disbursement', '2025-09-26', 950000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:31:00'),
(379, 71, 'LN-20260210-203110', 'Disbursement', '2026-01-05', 5000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:31:59'),
(380, 72, 'LN-20260210-203213', 'Disbursement', '2025-12-17', 700000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:33:44'),
(381, 73, 'LN-20260210-203413', 'Disbursement', '2025-12-05', 5000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:35:04'),
(382, 74, 'LN-20260210-203513', 'Disbursement', '2025-12-01', 300000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:37:42'),
(383, 75, 'LN-20260210-203750', 'Disbursement', '2025-11-20', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:40:00'),
(384, 76, 'LN-20260210-204014', 'Disbursement', '2025-12-18', 700000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:40:51'),
(385, 77, 'LN-20260210-204109', 'Disbursement', '2025-11-10', 8000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:42:07'),
(386, 78, 'LN-20260210-204222', 'Disbursement', '2025-12-04', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:43:19'),
(387, 79, 'LN-20260210-204420', 'Disbursement', '2025-11-13', 300000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:45:17'),
(388, 80, 'LN-20260210-204526', 'Disbursement', '2025-11-12', 1200000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:46:20'),
(389, 81, 'LN-20260210-204627', 'Disbursement', '2026-01-15', 3000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:47:43'),
(390, 82, 'LN-20260210-204759', 'Disbursement', '2025-12-22', 800000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:49:44'),
(391, 83, 'LN-20260210-204952', 'Disbursement', '2025-12-15', 4000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:53:58'),
(392, 84, 'LN-20260210-205440', 'Disbursement', '2026-01-13', 2000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:55:00'),
(393, 85, 'LN-20260210-205512', 'Disbursement', '2026-01-13', 2000000.00, 'Loan disbursement', NULL, 1, '2026-02-10 20:55:58'),
(396, 88, 'LN-20260210-205936', 'Disbursement', '2026-01-12', 21100000.00, 'Loan disbursement', NULL, 1, '2026-02-10 21:01:21'),
(399, 91, 'LN-20260211-121925', 'Disbursement', '2026-02-11', 20000000.00, 'Loan disbursement', NULL, 1, '2026-02-11 12:53:47'),
(400, 92, 'LN-20260211-131649', 'Disbursement', '2026-01-12', 1300000.00, 'Loan disbursement', NULL, 1, '2026-02-11 13:19:09'),
(401, 91, 'LN-20260211-121925', '', '2026-02-11', 20000000.00, 'Loan updated', NULL, 1, '2026-02-11 13:20:30'),
(402, 93, 'LN-20260211-142242', 'Disbursement', '2026-02-11', 1500000.00, 'Loan disbursement', NULL, 1, '2026-02-11 14:25:22'),
(403, 58, 'LN-20260210-200003', '', '2026-02-11', 1500000.00, 'Loan updated', NULL, 1, '2026-02-11 16:47:48'),
(405, 95, 'LN-20260213-074840', 'Disbursement', '2026-02-12', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-13 07:51:22'),
(406, 96, 'LN-20260213-082655', 'Disbursement', '2026-01-15', 12000000.00, 'Loan disbursement', NULL, 1, '2026-02-13 08:27:59'),
(407, 97, 'LN-20260213-082810', 'Disbursement', '2026-01-08', 27000000.00, 'Loan disbursement', NULL, 1, '2026-02-13 08:30:37'),
(408, 98, 'LN-20260213-083938', 'Disbursement', '2026-02-10', 1000000.00, 'Loan disbursement', NULL, 1, '2026-02-13 08:41:00'),
(409, 99, 'LN-20260213-095208', 'Disbursement', '2026-02-13', 1200000.00, 'Loan disbursement', NULL, 1, '2026-02-13 09:55:02'),
(410, 99, 'LN-20260213-095208', '', '2026-02-13', 1200000.00, 'Loan updated', NULL, 1, '2026-02-13 09:56:11'),
(411, 99, 'LN-20260213-095208', '', '2026-02-13', 1200000.00, 'Loan updated', NULL, 1, '2026-02-13 09:57:22'),
(413, 101, 'LN-20260213-115907', 'Disbursement', '2026-02-19', 700000.00, 'Loan disbursement', NULL, 1, '2026-02-13 12:00:32'),
(414, 43, 'LN-20260210-065730', '', '2026-02-13', 700000.00, 'Loan updated', NULL, 1, '2026-02-13 13:06:32'),
(415, 39, 'LN-20260210-064151', '', '2026-02-13', 1080000.00, 'Loan updated', NULL, 1, '2026-02-13 14:15:43'),
(416, 33, 'LN-20260210-045901', '', '2026-02-13', 300000.00, 'Loan updated', NULL, 1, '2026-02-13 15:00:34'),
(417, 102, 'LN-20260213-155133', 'Disbursement', '2025-11-13', 500000.00, 'Loan disbursement', NULL, 1, '2026-02-13 15:57:41'),
(420, 75, 'LN-20260210-203750', '', '2026-02-17', 1000000.00, 'Loan updated', NULL, 1, '2026-02-17 12:42:55'),
(427, 110, 'LN-20260218-105127', 'Disbursement', '2026-01-09', 2000000.00, 'Loan disbursement', NULL, 1, '2026-02-18 10:53:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','manager','user') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@example.com', 'admin', 'active', NULL, '2026-01-16 08:41:39', '2026-01-16 08:41:39'),
(2, 'manager', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Finance Manager', 'manager@example.com', 'manager', 'active', NULL, '2026-01-16 08:41:39', '2026-01-16 08:41:39'),
(3, 'user', '$2y$10$E9xG2pKXF3X8h6MxLjLxNe8BVxGKxMxqxQxH8v9v5pKXq2v3VxHEi', 'Regular User', 'user@example.com', 'user', 'active', NULL, '2026-01-16 08:41:39', '2026-01-16 08:41:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `application_fees`
--
ALTER TABLE `application_fees`
  ADD PRIMARY KEY (`application_fee_id`),
  ADD UNIQUE KEY `fee_reference` (`fee_reference`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_fee_date` (`fee_date`),
  ADD KEY `idx_reference` (`fee_reference`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD UNIQUE KEY `asset_number` (`asset_number`);

--
-- Indexes for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `account_code` (`account_code`),
  ADD KEY `idx_account_code` (`account_code`),
  ADD KEY `idx_account_type` (`account_type`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `idx_customer_code` (`customer_code`),
  ADD KEY `idx_customer_name` (`customer_name`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_customer_email` (`email`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD UNIQUE KEY `expense_reference` (`expense_reference`),
  ADD KEY `idx_expense_date` (`expense_date`),
  ADD KEY `idx_account_code` (`account_code`);

--
-- Indexes for table `ledger`
--
ALTER TABLE `ledger`
  ADD PRIMARY KEY (`ledger_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_account_code` (`account_code`),
  ADD KEY `idx_voucher_number` (`voucher_number`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_ledger_sequence` (`account_code`,`transaction_date`,`sequence_number`);

--
-- Indexes for table `loan_application_fees`
--
ALTER TABLE `loan_application_fees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loan_instalments`
--
ALTER TABLE `loan_instalments`
  ADD PRIMARY KEY (`instalment_id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `loan_number` (`loan_number`),
  ADD KEY `instalment_number` (`instalment_number`),
  ADD KEY `due_date` (`due_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_instalment_dates` (`due_date`,`payment_date`),
  ADD KEY `idx_instalment_status` (`loan_id`,`status`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `loan_instalment_id` (`loan_instalment_id`),
  ADD KEY `idx_loan_id` (`loan_id`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `loan_payment_adjustments`
--
ALTER TABLE `loan_payment_adjustments`
  ADD PRIMARY KEY (`adjustment_id`),
  ADD KEY `fk_loan` (`loan_id`),
  ADD KEY `idx_customer_loan` (`customer_id`,`loan_id`),
  ADD KEY `idx_instalment` (`instalment_id`),
  ADD KEY `idx_record_date` (`record_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `loan_payment_variance`
--
ALTER TABLE `loan_payment_variance`
  ADD PRIMARY KEY (`variance_id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_loan_id` (`loan_id`),
  ADD KEY `idx_instalment_id` (`instalment_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_variance_type` (`variance_type`),
  ADD KEY `idx_unallocated_balance` (`unallocated_balance`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `loan_portfolio`
--
ALTER TABLE `loan_portfolio`
  ADD PRIMARY KEY (`loan_id`),
  ADD UNIQUE KEY `loan_number` (`loan_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `loan_status` (`loan_status`),
  ADD KEY `disbursement_date` (`disbursement_date`),
  ADD KEY `idx_loan_customer` (`customer_id`,`loan_status`),
  ADD KEY `idx_loan_dates` (`disbursement_date`,`maturity_date`);

--
-- Indexes for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_loan_id` (`loan_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_transaction_type` (`transaction_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `application_fees`
--
ALTER TABLE `application_fees`
  MODIFY `application_fee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `ledger`
--
ALTER TABLE `ledger`
  MODIFY `ledger_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14016;

--
-- AUTO_INCREMENT for table `loan_application_fees`
--
ALTER TABLE `loan_application_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_instalments`
--
ALTER TABLE `loan_instalments`
  MODIFY `instalment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=477;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=336;

--
-- AUTO_INCREMENT for table `loan_payment_adjustments`
--
ALTER TABLE `loan_payment_adjustments`
  MODIFY `adjustment_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `loan_payment_variance`
--
ALTER TABLE `loan_payment_variance`
  MODIFY `variance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_portfolio`
--
ALTER TABLE `loan_portfolio`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=431;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `loan_instalments`
--
ALTER TABLE `loan_instalments`
  ADD CONSTRAINT `loan_instalments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_portfolio` (`loan_id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD CONSTRAINT `loan_payments_ibfk_1` FOREIGN KEY (`loan_instalment_id`) REFERENCES `loan_instalments` (`instalment_id`),
  ADD CONSTRAINT `loan_payments_ibfk_2` FOREIGN KEY (`loan_id`) REFERENCES `loan_portfolio` (`loan_id`);

--
-- Constraints for table `loan_payment_adjustments`
--
ALTER TABLE `loan_payment_adjustments`
  ADD CONSTRAINT `fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_instalment` FOREIGN KEY (`instalment_id`) REFERENCES `loan_instalments` (`instalment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_loan` FOREIGN KEY (`loan_id`) REFERENCES `loan_portfolio` (`loan_id`) ON UPDATE CASCADE;

--
-- Constraints for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  ADD CONSTRAINT `loan_transactions_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_portfolio` (`loan_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
