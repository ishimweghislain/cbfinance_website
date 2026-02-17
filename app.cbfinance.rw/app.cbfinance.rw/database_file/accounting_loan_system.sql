-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 08, 2026 at 02:31 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `accounting_loan_system`
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
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `income_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_fees`
--

INSERT INTO `application_fees` (`application_fee_id`, `customer_id`, `fee_reference`, `fee_date`, `total_amount`, `income_amount`, `vat_amount`, `payment_method`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 13, 'APPFEE-20260108-130746', '2026-01-08', 5000.00, 4237.29, 762.71, 'Cash', '', 1, '2026-01-08 12:07:46', '2026-01-08 12:07:46'),
(7, 12, 'APPFEE-20260108-132057', '2026-01-08', 5000.00, 4237.29, 762.71, 'Cash', '', 1, '2026-01-08 12:20:57', '2026-01-08 12:20:57'),
(10, 12, 'APPFEE-20260108-134532', '2026-01-08', 5000.00, 4237.29, 762.71, 'Cash', '', 1, '2026-01-08 12:45:32', '2026-01-08 12:45:32'),
(11, 12, 'APPFEE-20260108-134600', '2026-01-08', 5000.00, 4237.29, 762.71, 'Cash', '', 1, '2026-01-08 12:46:00', '2026-01-08 12:46:00'),
(12, 11, 'APPFEE-20260108-134901', '2026-01-08', 5000.00, 4237.29, 762.71, 'Cash', '', 1, '2026-01-08 12:49:01', '2026-01-08 12:49:01');

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
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chart_of_accounts`
--

INSERT INTO `chart_of_accounts` (`account_id`, `class`, `account_code`, `account_name`, `account_type`, `sub_type`, `normal_balance`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Balance Sheet', '1101', 'Cash on Hand', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(2, 'Balance Sheet', '1102', 'Bank Account – Main', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(3, 'Balance Sheet', '1103', 'Mobile Money Account', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(4, 'Balance Sheet', '1104', 'Petty Cash', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(5, 'Balance Sheet', '1201', 'Loan Portfolio – Performing', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(6, 'Balance Sheet', '1202', 'Loan Portfolio – Non-Performing', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(7, 'Balance Sheet', '1203', 'Interest Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(8, 'Balance Sheet', '1204', 'Monitoring Fees Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(9, 'Balance Sheet', '1205', 'Disbursement Fee Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(10, 'Balance Sheet', '1206', 'VAT Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(11, 'Balance Sheet', '1207', 'Staff Advance', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(12, 'Balance Sheet', '1209', 'Suspended Interest Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(13, 'Balance Sheet', '1210', 'Suspended Monitoring Fee Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(14, 'Balance Sheet', '1211', 'Suspended Disbursement Fee Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(15, 'Balance Sheet', '1212', 'Suspended VAT Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(16, 'Balance Sheet', '1213', 'Principal in Arrears', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(17, 'Balance Sheet', '1214', 'Interest in Arrears', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(18, 'Balance Sheet', '1215', 'Fees in Arrears', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(19, 'Balance Sheet', '1250', 'Loan Offset Control Account', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(20, 'Balance Sheet', '1301', 'Prepaid Interest Receivable', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(21, 'Balance Sheet', '1302', 'Prepaid Monitoring Fees', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(22, 'Balance Sheet', '1303', 'Prepaid Monitoring Fees VAT', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(23, 'Balance Sheet', '1304', 'Prepaid Rent', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(24, 'Balance Sheet', '1305', 'Prepaid Insurance', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(25, 'Balance Sheet', '1306', 'Due from Shareholders', 'Asset', 'Current Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(26, 'Balance Sheet', '1401', 'Office Furniture', 'Asset', 'Fixed Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(27, 'Balance Sheet', '1402', 'Computers & Electronics', 'Asset', 'Fixed Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(28, 'Balance Sheet', '1403', 'Office Renovation', 'Asset', 'Fixed Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(29, 'Balance Sheet', '1404', 'Motor Vehicle', 'Asset', 'Fixed Asset', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(30, 'Balance Sheet', '1405', 'Accumulated Depreciation', 'Asset', 'Fixed Asset', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(31, 'Balance Sheet', '2101', 'Accounts Payable', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(32, 'Balance Sheet', '2102', 'Accrued Expenses', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(33, 'Balance Sheet', '2103', 'Accrued Salaries', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(34, 'Balance Sheet', '2104', 'Accrued Withholding Tax Payable', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(35, 'Balance Sheet', '2105', 'VAT Payable', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(36, 'Balance Sheet', '2106', 'Acrrued Payee', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(37, 'Balance Sheet', '2107', 'Acrrued Pension', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(38, 'Balance Sheet', '2108', 'Accrued Maternity Leave', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(39, 'Balance Sheet', '2109', 'Accrued Mutuel', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(40, 'Balance Sheet', '2201', 'Loan Payable – Banks', 'Liability', 'Long-term Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(41, 'Balance Sheet', '2202', 'Loan Payable – Other Institutions', 'Liability', 'Long-term Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(42, 'Balance Sheet', '2301', 'Loan Loss Provision', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(43, 'Balance Sheet', '2302', 'Other Provisions', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(44, 'Balance Sheet', '2401', 'Deferred Disbursement Fees', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(45, 'Balance Sheet', '2402', 'Deferred Monitoring Fees', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(46, 'Balance Sheet', '2403', 'Deferred VAT', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(47, 'Balance Sheet', '2405', 'Suspended Deferred Disbursement Fees', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(48, 'Balance Sheet', '2406', 'Suspended Deferred VAT', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(49, 'Balance Sheet', '2407', 'Customer Deposits (Advance Payments)', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(50, 'Balance Sheet', '2408', 'Loan Overpayment Liability', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(51, 'Balance Sheet', '2409', 'Refunds Payable', 'Liability', 'Current Liability', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(52, 'Balance Sheet', '3101', 'Share Capital', 'Equity', 'Capital Stock', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(53, 'Balance Sheet', '3102', 'Retained Earnings', 'Equity', 'Retained Earnings', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(54, 'Balance Sheet', '3103', 'Current Year Earnings/Loss', 'Equity', 'Retained Earnings', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(55, 'Balance Sheet', '3104', 'Capital Reserve', 'Equity', 'Other Equity', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(56, 'Income Statement', '4101', 'Interest on Loans', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(57, 'Income Statement', '4102', 'Interest on Bank Deposits', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(58, 'Income Statement', '4201', 'Disbursement Fee Income', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(59, 'Income Statement', '4202', 'Monitoring Fee Income', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(60, 'Income Statement', '4203', 'Penalty Charges', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(61, 'Income Statement', '4204', 'Application Fees', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(62, 'Income Statement', '4205', 'Other Operating Income', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(63, 'Income Statement', '4301', 'Impairment Recovery (Provision Reversal Income)', 'Revenue', 'Operating Revenue', 'Credit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(64, 'Income Statement', '5101', 'Salaries & Wages', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(65, 'Income Statement', '5102', 'Staff Training & Development', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(66, 'Income Statement', '5103', 'Transport & Travel', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(67, 'Income Statement', '5104', 'Rent', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(68, 'Income Statement', '5105', 'Utilities', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(69, 'Income Statement', '5106', 'Office Supplies & Services', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(70, 'Income Statement', '5107', 'Communication (Internet, Phone)', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(71, 'Income Statement', '5108', 'Insurance', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(72, 'Income Statement', '5109', 'Employer Pension Contributions', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(73, 'Income Statement', '5110', 'Employer Maternity Leave Contributions', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(74, 'Income Statement', '5150', 'Board Sitting Allowances', 'Expense', 'Administrative Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(75, 'Income Statement', '5151', 'Board Meeting Refreshments', 'Expense', 'Administrative Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(76, 'Income Statement', '5201', 'Legal & Regulatory Fees', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(77, 'Income Statement', '5202', 'Consulting Services', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(78, 'Income Statement', '5203', 'Audit & Accounting Services', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(79, 'Income Statement', '5250', 'IT and Communication', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(80, 'Income Statement', '5261', 'Office Equipment Repairs', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(81, 'Income Statement', '5262', 'Building Maintenance', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(82, 'Income Statement', '5263', 'Vehicle Maintenance', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(83, 'Income Statement', '5264', 'Office Partition', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(84, 'Income Statement', '5265', 'Office Branding', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(85, 'Income Statement', '5270', 'Marketing & Advertising Expense', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(86, 'Income Statement', '5275', 'Branding and Design Expenses', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(87, 'Income Statement', '5301', 'Loan Interest Expense', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(88, 'Income Statement', '5302', 'Bank Charges', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(89, 'Income Statement', '5303', 'Mobile Money Charges', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(90, 'Income Statement', '5401', 'Depreciation – Furniture', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(91, 'Income Statement', '5402', 'Depreciation – Equipment', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(92, 'Income Statement', '5403', 'Loan Loss Provision Expense', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(93, 'Income Statement', '5501', 'Loan Loss Expense – Principal', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22'),
(94, 'Income Statement', '5502', 'Loan Loss Expense – Interest & Fees', 'Expense', 'Operating Expense', 'Debit', 1, '2026-01-08 13:30:22', '2026-01-08 13:30:22');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `date_of_birth` date DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `risk_rating` enum('Low','Medium','High') DEFAULT 'Medium',
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `total_loans` decimal(15,2) DEFAULT 0.00,
  `total_paid` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_code`, `customer_name`, `id_number`, `gender`, `date_of_birth`, `contact_person`, `email`, `phone`, `address`, `tin_number`, `risk_rating`, `current_balance`, `total_loans`, `total_paid`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(11, 'C001', 'Nsabiyumva Jacques', '', 'Male', NULL, '', '', '+250794082154', '', '', 'Medium', 4236000.00, 4236000.00, 0.00, 1, NULL, '2026-01-07 17:03:07', '2026-01-07 17:03:44'),
(12, 'C002', 'bobo dev', '', 'Male', NULL, '', '', '+250788408023', '', '', 'Medium', 5295000.00, 5295000.00, 0.00, 1, NULL, '2026-01-07 17:06:31', '2026-01-08 13:07:20'),
(13, 'C003', 'Ndengeye', '', 'Male', NULL, '', '', '+250786566309', '', '', 'Medium', 2118000.00, 2118000.00, 0.00, 1, NULL, '2026-01-08 10:02:21', '2026-01-08 12:19:02');

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
  `narration` text DEFAULT NULL,
  `beginning_balance` decimal(15,2) DEFAULT 0.00,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `movement` decimal(15,2) DEFAULT 0.00,
  `ending_balance` decimal(15,2) DEFAULT 0.00,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ledger`
--

INSERT INTO `ledger` (`ledger_id`, `transaction_date`, `class`, `account_code`, `account_name`, `particular`, `voucher_number`, `narration`, `beginning_balance`, `debit_amount`, `credit_amount`, `movement`, `ending_balance`, `reference_type`, `reference_id`, `created_by`, `created_at`, `updated_at`) VALUES
(39, '2026-01-07', 'Assets', '1102', 'Bank Account', 'Payment for bobo dev - Instalment #1', 'C002', 'Payment for bobo dev - Instalment #1', 0.00, 396651.00, 0.00, 396651.00, 396651.00, NULL, NULL, 1, '2026-01-07 17:07:03', '2026-01-07 17:07:03'),
(40, '2026-01-07', 'Assets', '1201', 'Loan Portfolio – Performing', 'Payment for bobo dev - Instalment #1', 'C002', 'Payment for bobo dev - Instalment #1', 0.00, 0.00, 335924.00, -335924.00, -335924.00, NULL, NULL, 1, '2026-01-07 17:07:03', '2026-01-07 17:07:03'),
(41, '2026-01-07', 'Revenue', '4101', 'Interest on Loans', 'Payment for bobo dev - Instalment #1', 'C002', 'Payment for bobo dev - Instalment #1', 0.00, 0.00, 52950.00, -52950.00, -52950.00, NULL, NULL, 1, '2026-01-07 17:07:03', '2026-01-07 17:07:03'),
(42, '2026-01-07', 'Fee Income', '4202', 'Monitoring Fee Income', 'Payment for bobo dev - Instalment #1', 'C002', 'Payment for bobo dev - Instalment #1', 0.00, 0.00, 6377.14, -6377.14, -6377.14, NULL, NULL, 1, '2026-01-07 17:07:03', '2026-01-07 17:07:03'),
(43, '2026-01-07', 'Liabilities', '2105', 'VAT Payable', 'Payment for bobo dev - Instalment #1', 'C002', 'Payment for bobo dev - Instalment #1', 0.00, 0.00, 1399.86, -1399.86, -1399.86, NULL, NULL, 1, '2026-01-07 17:07:03', '2026-01-07 17:07:03'),
(44, '2026-01-08', 'Assets', '1102', 'Bank Account', 'Payment for bobo dev - Instalment #2', 'C002', 'Payment for bobo dev - Instalment #2', 396651.00, 396651.00, 0.00, 396651.00, 793302.00, NULL, NULL, 1, '2026-01-08 08:13:49', '2026-01-08 08:13:49'),
(45, '2026-01-08', 'Assets', '1201', 'Loan Portfolio – Performing', 'Payment for bobo dev - Instalment #2', 'C002', 'Payment for bobo dev - Instalment #2', -335924.00, 0.00, 352720.20, -352720.20, -688644.20, NULL, NULL, 1, '2026-01-08 08:13:49', '2026-01-08 08:13:49'),
(46, '2026-01-08', 'Revenue', '4101', 'Interest on Loans', 'Payment for bobo dev - Instalment #2', 'C002', 'Payment for bobo dev - Instalment #2', -52950.00, 0.00, 36153.80, -36153.80, -89103.80, NULL, NULL, 1, '2026-01-08 08:13:49', '2026-01-08 08:13:49'),
(47, '2026-01-08', 'Fee Income', '4202', 'Monitoring Fee Income', 'Payment for bobo dev - Instalment #2', 'C002', 'Payment for bobo dev - Instalment #2', -6377.14, 0.00, 6377.14, -6377.14, -12754.28, NULL, NULL, 1, '2026-01-08 08:13:49', '2026-01-08 08:13:49'),
(48, '2026-01-08', 'Liabilities', '2105', 'VAT Payable', 'Payment for bobo dev - Instalment #2', 'C002', 'Payment for bobo dev - Instalment #2', -1399.86, 0.00, 1399.86, -1399.86, -2799.72, NULL, NULL, 1, '2026-01-08 08:13:49', '2026-01-08 08:13:49'),
(49, '2026-01-08', 'Assets', '1102', 'Bank Account', 'C002', 'APPFEE-20260108-134532', 'Application Fees for bobo dev', 793302.00, 5000.00, 0.00, 5000.00, 798302.00, 'application_fee', 10, 1, '2026-01-08 12:45:32', '2026-01-08 12:45:32'),
(50, '2026-01-08', 'Fee Income', '4204', 'Application Fees', 'C002', 'APPFEE-20260108-134532', 'Application Fees for bobo dev', 0.00, 0.00, 4237.29, 4237.29, 4237.29, 'application_fee', 10, 1, '2026-01-08 12:45:32', '2026-01-08 12:45:32'),
(51, '2026-01-08', 'Liabilities', '2105', 'VAT Payable', 'C002', 'APPFEE-20260108-134532', 'Application Fees for bobo dev', -2799.72, 0.00, 762.71, 762.71, -2037.01, 'application_fee', 10, 1, '2026-01-08 12:45:32', '2026-01-08 12:45:32'),
(52, '2026-01-08', 'Assets', '1101', 'Cash Account', 'C002', 'APPFEE-20260108-134600', 'Application Fees for bobo dev', 0.00, 5000.00, 0.00, 5000.00, 5000.00, 'application_fee', 11, 1, '2026-01-08 12:46:00', '2026-01-08 12:46:00'),
(53, '2026-01-08', 'Fee Income', '4204', 'Application Fees', 'C002', 'APPFEE-20260108-134600', 'Application Fees for bobo dev', 4237.29, 0.00, 4237.29, 4237.29, 8474.58, 'application_fee', 11, 1, '2026-01-08 12:46:00', '2026-01-08 12:46:00'),
(54, '2026-01-08', 'Liabilities', '2105', 'VAT Payable', 'C002', 'APPFEE-20260108-134600', 'Application Fees for bobo dev', -2037.01, 0.00, 762.71, 762.71, -1274.30, 'application_fee', 11, 1, '2026-01-08 12:46:00', '2026-01-08 12:46:00'),
(55, '2026-01-08', 'Assets', '1101', 'Cash Account', 'C001', 'APPFEE-20260108-134901', 'Application Fees for Nsabiyumva Jacques', 5000.00, 5000.00, 0.00, 5000.00, 10000.00, 'application_fee', 12, 1, '2026-01-08 12:49:01', '2026-01-08 12:49:01'),
(56, '2026-01-08', 'Fee Income', '4204', 'Application Fees', 'C001', 'APPFEE-20260108-134901', 'Application Fees for Nsabiyumva Jacques', 8474.58, 0.00, 4237.29, 4237.29, 12711.87, 'application_fee', 12, 1, '2026-01-08 12:49:01', '2026-01-08 12:49:01'),
(57, '2026-01-08', 'Liabilities', '2105', 'VAT Payable', 'C001', 'APPFEE-20260108-134901', 'Application Fees for Nsabiyumva Jacques', -1274.30, 0.00, 762.71, 762.71, -511.59, 'application_fee', 12, 1, '2026-01-08 12:49:01', '2026-01-08 12:49:01'),
(58, '2026-01-08', 'Asset', '1201', 'Loans to Customers', 'Loan Disbursement', 'C002', 'Loan #LN-20260108-140707 to bobo dev', -688644.20, 2118000.00, 0.00, 2118000.00, 1429355.80, 'loan', 23, 1, '2026-01-08 13:07:20', '2026-01-08 13:07:20'),
(59, '2026-01-08', 'Asset', '1101', 'Cash on Hand', 'Cash Payment', 'C002', 'Loan #LN-20260108-140707 to bobo dev', 10000.00, 0.00, 2000000.00, -2000000.00, -1990000.00, 'loan', 23, 1, '2026-01-08 13:07:20', '2026-01-08 13:07:20'),
(60, '2026-01-08', 'Liability', '2401', 'Deferred Disbursement Fees', 'Disbursement Fees', 'C002', 'Loan #LN-20260108-140707 to bobo dev', 0.00, 0.00, 100000.00, 100000.00, 100000.00, 'loan', 23, 1, '2026-01-08 13:07:20', '2026-01-08 13:07:20'),
(61, '2026-01-08', 'Liability', '2403', 'Deferred Disbursement Fees VAT', 'Disbursement VAT', 'C002', 'Loan #LN-20260108-140707 to bobo dev', 0.00, 0.00, 18000.00, 18000.00, 18000.00, 'loan', 23, 1, '2026-01-08 13:07:20', '2026-01-08 13:07:20');

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
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `principal_amount` decimal(15,2) DEFAULT 0.00,
  `interest_amount` decimal(15,2) DEFAULT 0.00,
  `fees_amount` decimal(15,2) DEFAULT 0.00,
  `monitoring_fee` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'Pending',
  `payment_date` date DEFAULT NULL,
  `days_overdue` int(11) DEFAULT 0,
  `penalty_amount` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_instalments`
--

INSERT INTO `loan_instalments` (`instalment_id`, `loan_id`, `loan_number`, `instalment_number`, `due_date`, `amount`, `principal_amount`, `interest_amount`, `fees_amount`, `monitoring_fee`, `total_amount`, `paid_amount`, `balance`, `status`, `payment_date`, `days_overdue`, `penalty_amount`, `created_by`, `created_at`, `updated_at`) VALUES
(52, 15, 'LN-20260107-180310', 1, '2025-07-24', 834566.00, 0.00, 0.00, 0.00, 16691.00, 851257.00, 0.00, 0.00, 'Overpaid', NULL, 0, 0.00, 1, '2026-01-07 17:03:44', '2026-01-07 17:05:28'),
(53, 15, 'LN-20260107-180310', 2, '2025-08-23', 834566.00, 0.00, 0.00, 0.00, 16691.00, 851257.00, 0.00, 851257.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-07 17:03:44', '2026-01-07 17:03:44'),
(54, 15, 'LN-20260107-180310', 3, '2025-09-22', 834566.00, 0.00, 0.00, 0.00, 16691.00, 851257.00, 0.00, 851257.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-07 17:03:44', '2026-01-07 17:03:44'),
(55, 15, 'LN-20260107-180310', 4, '2025-10-22', 834566.00, 0.00, 0.00, 0.00, 16691.00, 851257.00, 0.00, 851257.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-07 17:03:44', '2026-01-07 17:03:44'),
(56, 15, 'LN-20260107-180310', 5, '2025-11-21', 834566.00, 0.00, 0.00, 0.00, 16691.00, 851257.00, 0.00, 851257.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-07 17:03:44', '2026-01-07 17:03:44'),
(57, 15, 'LN-20260107-180310', 6, '2025-12-21', 834566.00, 0.00, 0.00, 0.00, 16691.00, 851257.00, 0.00, 0.00, 'Overpaid', NULL, 0, 0.00, 1, '2026-01-07 17:03:44', '2026-01-07 17:05:09'),
(58, 16, 'LN-20260107-180634', 1, '2026-02-06', 388874.00, 0.00, 0.00, 0.00, 7777.00, 396651.00, 0.00, 0.00, 'Paid', NULL, 0, 0.00, 1, '2026-01-07 17:06:43', '2026-01-07 17:07:01'),
(59, 16, 'LN-20260107-180634', 2, '2026-03-08', 388874.00, 0.00, 0.00, 0.00, 7777.00, 396651.00, 0.00, 0.00, 'Paid', NULL, 0, 0.00, 1, '2026-01-07 17:06:43', '2026-01-08 08:13:47'),
(60, 16, 'LN-20260107-180634', 3, '2026-04-07', 388874.00, 0.00, 0.00, 0.00, 7777.00, 396651.00, 0.00, 396651.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-07 17:06:43', '2026-01-07 17:06:43'),
(61, 19, 'LN-20260108-130331', 1, '2026-02-07', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 12:03:43', '2026-01-08 12:03:43'),
(62, 19, 'LN-20260108-130331', 2, '2026-03-09', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 12:03:43', '2026-01-08 12:03:43'),
(63, 19, 'LN-20260108-130331', 3, '2026-04-08', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 12:03:43', '2026-01-08 12:03:43'),
(64, 20, 'LN-20260108-131846', 1, '2026-02-07', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 12:19:02', '2026-01-08 12:19:02'),
(65, 20, 'LN-20260108-131846', 2, '2026-03-09', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 12:19:02', '2026-01-08 12:19:02'),
(66, 20, 'LN-20260108-131846', 3, '2026-04-08', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 12:19:02', '2026-01-08 12:19:02'),
(67, 23, 'LN-20260108-140707', 1, '2026-02-07', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 13:07:20', '2026-01-08 13:07:20'),
(68, 23, 'LN-20260108-140707', 2, '2026-03-09', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 13:07:20', '2026-01-08 13:07:20'),
(69, 23, 'LN-20260108-140707', 3, '2026-04-08', 777748.00, 0.00, 0.00, 0.00, 15555.00, 793303.00, 0.00, 793303.00, 'Pending', NULL, 0, 0.00, 1, '2026-01-08 13:07:20', '2026-01-08 13:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `payment_id` int(11) NOT NULL,
  `loan_instalment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `month_paid` date NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `beginning_balance` decimal(15,2) DEFAULT 0.00,
  `payment_amount` decimal(15,2) NOT NULL,
  `interest_amount` decimal(15,2) DEFAULT 0.00,
  `principal_amount` decimal(15,2) DEFAULT 0.00,
  `monitoring_fee` decimal(15,2) DEFAULT 0.00,
  `days_overdue` int(11) DEFAULT 0,
  `penalties` decimal(15,2) DEFAULT 0.00,
  `final_payment` decimal(15,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_payments`
--

INSERT INTO `loan_payments` (`payment_id`, `loan_instalment_id`, `loan_id`, `month_paid`, `payment_date`, `beginning_balance`, `payment_amount`, `interest_amount`, `principal_amount`, `monitoring_fee`, `days_overdue`, `penalties`, `final_payment`, `payment_method`, `reference_number`, `notes`, `created_at`, `updated_at`) VALUES
(8, 57, 15, '2025-12-21', '2026-01-07 00:00:00', 794824.73, 938085.21, 39741.24, 794824.76, 16691.00, 17, 86828.21, 938085.21, 'Cash', NULL, NULL, '2026-01-07 17:04:40', '2026-01-07 17:04:40'),
(9, 57, 15, '2025-12-21', '2026-01-07 00:00:00', 794824.73, 938085.21, 39741.24, 794824.76, 16691.00, 17, 86828.21, 938085.21, 'Cash', NULL, NULL, '2026-01-07 17:05:09', '2026-01-07 17:05:09'),
(10, 52, 15, '2025-07-24', '2026-01-07 00:00:00', 4236000.00, 1704216.51, 211800.00, 622766.00, 16691.00, 167, 852959.51, 1704216.51, 'Bank Transfer', NULL, NULL, '2026-01-07 17:05:28', '2026-01-07 17:05:28'),
(11, 58, 16, '2026-02-06', '2026-01-07 00:00:00', 1059000.00, 396651.00, 52950.00, 335924.00, 7777.00, 0, 0.00, 396651.00, 'Bank Transfer', NULL, NULL, '2026-01-07 17:07:01', '2026-01-07 17:07:01'),
(12, 59, 16, '2026-03-08', '2026-01-08 00:00:00', 723076.00, 396651.00, 36153.80, 352720.20, 7777.00, 0, 0.00, 396651.00, 'Bank Transfer', NULL, NULL, '2026-01-08 08:13:47', '2026-01-08 08:13:47');

-- --------------------------------------------------------

--
-- Table structure for table `loan_portfolio`
--

CREATE TABLE `loan_portfolio` (
  `loan_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `loan_number` varchar(50) NOT NULL,
  `disbursement_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `disbursement_date` date NOT NULL,
  `maturity_date` date NOT NULL,
  `number_of_instalments` int(11) NOT NULL DEFAULT 1,
  `instalment_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `loan_status` enum('Active','Paid','Overdue','Defaulted','Cancelled') DEFAULT 'Active',
  `total_disbursed` decimal(15,2) DEFAULT 0.00,
  `principal_outstanding` decimal(15,2) DEFAULT 0.00,
  `interest_outstanding` decimal(15,2) DEFAULT 0.00,
  `total_outstanding` decimal(15,2) DEFAULT 0.00,
  `disbursement_fees` decimal(15,2) DEFAULT 0.00,
  `disbursement_fees_vat` decimal(15,2) DEFAULT 0.00,
  `total_fees` decimal(15,2) DEFAULT 0.00,
  `total_interest` decimal(15,2) DEFAULT 0.00,
  `total_payable` decimal(15,2) DEFAULT 0.00,
  `monitoring_fees` decimal(15,2) DEFAULT 0.00,
  `monitoring_fees_vat` decimal(15,2) DEFAULT 0.00,
  `accrued_interest` decimal(15,2) DEFAULT 0.00,
  `accrued_days` int(11) DEFAULT 0,
  `accrued_monitoring_fees` decimal(15,2) DEFAULT 0.00,
  `accrued_monitoring_fees_vat` decimal(15,2) DEFAULT 0.00,
  `deferred_disbursement_fees` decimal(15,2) DEFAULT 0.00,
  `deferred_disbursement_fees_vat` decimal(15,2) DEFAULT 0.00,
  `days_overdue` int(11) DEFAULT 0,
  `penalties` decimal(15,2) DEFAULT 0.00,
  `accumulated_loan_amount` decimal(15,2) DEFAULT 0.00,
  `collateral_type` varchar(100) DEFAULT NULL,
  `collateral_description` text DEFAULT NULL,
  `collateral_value` decimal(15,2) DEFAULT 0.00,
  `collateral_net_value` decimal(15,2) DEFAULT 0.00,
  `provisional_rate` decimal(5,2) DEFAULT 0.00,
  `general_provision` decimal(15,2) DEFAULT 0.00,
  `net_book_value` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_portfolio`
--

INSERT INTO `loan_portfolio` (`loan_id`, `customer_id`, `loan_number`, `disbursement_amount`, `interest_rate`, `disbursement_date`, `maturity_date`, `number_of_instalments`, `instalment_amount`, `loan_status`, `total_disbursed`, `principal_outstanding`, `interest_outstanding`, `total_outstanding`, `disbursement_fees`, `disbursement_fees_vat`, `total_fees`, `total_interest`, `total_payable`, `monitoring_fees`, `monitoring_fees_vat`, `accrued_interest`, `accrued_days`, `accrued_monitoring_fees`, `accrued_monitoring_fees_vat`, `deferred_disbursement_fees`, `deferred_disbursement_fees_vat`, `days_overdue`, `penalties`, `accumulated_loan_amount`, `collateral_type`, `collateral_description`, `collateral_value`, `collateral_net_value`, `provisional_rate`, `general_provision`, `net_book_value`, `created_by`, `created_at`, `updated_at`) VALUES
(15, 11, 'LN-20260107-180310', 4000000.00, 60.00, '2025-06-24', '2025-12-21', 6, 851257.00, 'Active', 4236000.00, 1787584.48, 480113.52, 2503698.00, 200000.00, 36000.00, 336146.00, 771396.00, 5107542.00, 100146.00, 18026.00, 0.00, 7, 0.00, 0.00, 200000.00, 36000.00, 0, 0.00, 4236000.00, '', '', 0.00, 0.00, 1.00, 40000.00, 4967396.00, 1, '2026-01-07 17:03:44', '2026-01-07 17:05:28'),
(16, 12, 'LN-20260107-180634', 1000000.00, 60.00, '2026-01-07', '2026-04-07', 3, 396651.00, 'Active', 1059000.00, 311355.80, 18518.20, 388874.00, 50000.00, 9000.00, 82331.00, 107622.00, 1189953.00, 23331.00, 4200.00, 0.00, 25, 0.00, 0.00, 50000.00, 9000.00, 0, 0.00, 1059000.00, '', '', 0.00, 0.00, 1.00, 10000.00, 1156622.00, 1, '2026-01-07 17:06:43', '2026-01-08 08:13:47'),
(19, 12, 'LN-20260108-130331', 2000000.00, 60.00, '2026-01-08', '2026-04-08', 3, 793303.00, 'Active', 2118000.00, 2000000.00, 215243.00, 2333243.00, 100000.00, 18000.00, 164665.00, 215243.00, 2379908.00, 46665.00, 8400.00, 0.00, 24, 0.00, 0.00, 100000.00, 18000.00, 0, 0.00, 2118000.00, '', '', 0.00, 0.00, 1.00, 20000.00, 2313243.00, 1, '2026-01-08 12:03:43', '2026-01-08 12:03:43'),
(20, 13, 'LN-20260108-131846', 2000000.00, 60.00, '2026-01-08', '2026-04-08', 3, 793303.00, 'Active', 2118000.00, 2000000.00, 215243.00, 2333243.00, 100000.00, 18000.00, 164665.00, 215243.00, 2379908.00, 46665.00, 8400.00, 0.00, 24, 0.00, 0.00, 100000.00, 18000.00, 0, 0.00, 2118000.00, '', '', 0.00, 0.00, 1.00, 20000.00, 2313243.00, 1, '2026-01-08 12:19:02', '2026-01-08 12:19:02'),
(23, 12, 'LN-20260108-140707', 2000000.00, 60.00, '2026-01-08', '2026-04-08', 3, 793303.00, 'Active', 2118000.00, 2000000.00, 215243.00, 2333243.00, 100000.00, 18000.00, 164665.00, 215243.00, 2379908.00, 46665.00, 8400.00, 0.00, 24, 0.00, 0.00, 100000.00, 18000.00, 0, 0.00, 2118000.00, '', '', 0.00, 0.00, 1.00, 20000.00, 2313243.00, 1, '2026-01-08 13:07:20', '2026-01-08 13:07:20');

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
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_transactions`
--

INSERT INTO `loan_transactions` (`transaction_id`, `loan_id`, `loan_number`, `transaction_type`, `transaction_date`, `amount`, `description`, `reference_number`, `created_by`, `created_at`) VALUES
(15, 15, 'LN-20260107-180310', 'Disbursement', '2025-06-24', 4236000.00, 'Loan disbursement', NULL, 1, '2026-01-07 17:03:44'),
(16, 16, 'LN-20260107-180634', 'Disbursement', '2026-01-07', 1059000.00, 'Loan disbursement', NULL, 1, '2026-01-07 17:06:43'),
(17, 19, 'LN-20260108-130331', 'Disbursement', '2026-01-08', 2118000.00, 'Loan disbursement', NULL, 1, '2026-01-08 12:03:43'),
(18, 20, 'LN-20260108-131846', 'Disbursement', '2026-01-08', 2118000.00, 'Loan disbursement', NULL, 1, '2026-01-08 12:19:02'),
(19, 23, 'LN-20260108-140707', 'Disbursement', '2026-01-08', 2118000.00, 'Loan disbursement', NULL, 1, '2026-01-08 13:07:20');

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
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `ledger`
--
ALTER TABLE `ledger`
  ADD PRIMARY KEY (`ledger_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_account_code` (`account_code`),
  ADD KEY `idx_voucher_number` (`voucher_number`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `loan_instalments`
--
ALTER TABLE `loan_instalments`
  ADD PRIMARY KEY (`instalment_id`),
  ADD UNIQUE KEY `unique_instalment` (`loan_id`,`instalment_number`),
  ADD KEY `idx_loan_id` (`loan_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `loan_instalment_id` (`loan_instalment_id`),
  ADD KEY `idx_loan_id` (`loan_id`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `loan_portfolio`
--
ALTER TABLE `loan_portfolio`
  ADD PRIMARY KEY (`loan_id`),
  ADD UNIQUE KEY `loan_number` (`loan_number`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_loan_number` (`loan_number`),
  ADD KEY `idx_loan_status` (`loan_status`),
  ADD KEY `idx_disbursement_date` (`disbursement_date`),
  ADD KEY `idx_maturity_date` (`maturity_date`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_loan_id` (`loan_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_transaction_type` (`transaction_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `application_fees`
--
ALTER TABLE `application_fees`
  MODIFY `application_fee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `ledger`
--
ALTER TABLE `ledger`
  MODIFY `ledger_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `loan_instalments`
--
ALTER TABLE `loan_instalments`
  MODIFY `instalment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `loan_portfolio`
--
ALTER TABLE `loan_portfolio`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
-- Constraints for table `loan_portfolio`
--
ALTER TABLE `loan_portfolio`
  ADD CONSTRAINT `loan_portfolio_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  ADD CONSTRAINT `loan_transactions_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_portfolio` (`loan_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
