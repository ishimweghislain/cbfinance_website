# CB Finance — Accounting & Loan Management System

A comprehensive, enterprise-grade solution for micro-finance institutions to manage the full lifecycle of loans, customer interactions, and financial accounting. Built for accuracy, security, and real-time portfolio tracking.

---

## 1. System Overview

### Purpose & Goal
This system is designed to automate the core operations of a micro-finance institution (CB Finance). It simplifies the transition from manual record-keeping to a digital, real-time ledger. The goal is to provide absolute accuracy in interest calculations, automated overdue tracking, and one-click financial reporting.

### Target Users
*   **Loan Officers:** To manage customer applications, disburse loans, and record repayments.
*   **Finance Managers/Admins:** To monitor portfolio health, approve new customers, and manage the general ledger.
*   **Executives:** To view high-level revenue metrics and export legal financial statements.

### Problem It Solves
*   **Revenue Discrepancy:** Eliminates the gap between "collected cash" and "recognized revenue" through sophisticated paid-basis accounting logic.
*   **Inaccurate Schedules:** Automates the generation of payment schedules with precise periodic fees and interest rates.
*   **Portfolio Risk:** Real-time tracking of 30+, 60+, and 90+ day overdue installments with an automated "Days Overdue" update engine.
*   **Manual Accounting:** Automatically creates journal entries for every loan disbursement and repayment.

---

## 2. Tech Stack

*   **Core Logic:** PHP 8.x (Vanilla + Modern OOP patterns)
*   **Database:** MySQL / MariaDB (Relational design with ACID compliance)
*   **Frontend UI:** Bootstrap 5, Font Awesome, Bootstrap Icons (Responsive Dashboard)
*   **Reporting Engine:** TCPDF (Custom PDF generation for Trial Balance & Receipts)
*   **Dependency Management:** Composer (Autoloader & Semver)
*   **Security:** Prepared Statements (SQLi Prevention), Session management, Role-Based Access Control (RBAC).

---

## 3. Architecture

The system follows a **Monolithic Modular Architecture**:

*   **Router (`index.php`):** A centralized entry point that handles authentication and routes requests to specific modules based on URL parameters.
*   **Module Layer (`modules/`):** Each major feature (Loans, Accounting, Reports) is an independent module file, making the system easy to extend without breaking core logic.
*   **Shared Services (`includes/`):** Contains reusable business logic such as `accounting_functions.php` (for automatic ledger entries) and `sidebar.php` (for role-aware navigation).
*   **Configuration (`config/`):** Centralized database connection and environment detection (Local vs. Production).

---

## 4. Features & Functionality

### 💼 Loan Management
*   **Add Loan:** Automatic calculation of installment amounts, maturity dates, and total loan value.
*   **Portfolio Tracking:** Real-time visibility into "Total Distributed," "Active Principal," and "Expected Interest."
*   **Repayment Recording:** Handles partial payments, overpayments (carry-over logic), and automated penalty application.
*   **Payment Schedules:** Dynamic generation of repayment tables with "Capped" revenue logic for reporting.

### 👥 Customer Management
*   **Approval Workflow:** Multi-stage workflow for new customers (Pending -> Approval -> Active).
*   **Customer 360:** View a customer's entire loan history, payment reliability, and personal documentation in one view.

### 🏛️ Financial Accounting (The Double-Entry Engine)

The system is built on a standard **Double-Entry Accounting Framework**. Every financial event generates balanced Debit and Credit entries in the `ledger`.

*   **Account Hierarchy (1-6):**
    *   **1xxx (Current Assets):** Cash/Bank (1102), Loan Portfolio (1201).
    *   **2xxx (Fixed Assets):** Physical property (Office Equipment).
    *   **3xxx (Liabilities & Equity):** Deposits, Taxes, and Owner's Capital.
    *   **4xxx (Revenue):** Interest (4101), Periodic Fee (4201), Upfront Fee (4202).
    *   **5xxx-6xxx (Expenses):** Direct costs and general operating expenses.
*   **Shadow Accounting:** The system automatically creates journal entries for Loan Disbursements (DR Asset/CR Cash) and Repayments (DR Cash/CR Asset & Revenue).
*   **Capped Revenue Logic:** To prevent rounding errors or accidental overpayments from distorting revenue, the system uses a **Capped Recognition Bridge**. It cross-references loan installments to recognize the **Expected Amount** when an installment is fully paid, ensuring perfect alignment with the amortization schedule.
*   **Depreciation Logic:** Real-time tracking of accumulated depreciation for fixed assets to report current **Net Book Value** on the Balance Sheet.
*   **Integrity Checks:** The Trial Balance performs a real-time sum of all closing balances, ensuring they equal zero before a report can be generated.

### 🏷️ Asset Management
*   **Fixed Assets:** Track acquisition value, additions, and disposal dates.
*   **Depreciation:** Automated tracking of accumulated depreciation and current book value.

### 👮 Security & Admin
*   **RBAC:** Different views and permissions for Admin, Developer, and Loan Officer roles.
*   **Audit Logs:** Records system activity (Logins, Approvals, Deletions) for security audits.

---

## 5. Setup & Installation

### Prerequisites
*   Web Server (Apache/Nginx) with PHP 8.0+
*   MySQL/MariaDB Database
*   Composer (for autoloader)

### Installation Steps
1.  **Clone/Upload:** Upload the files to your server (e.g., `htdocs/cbfinance_website`).
2.  **Database Config:**
    *   Navigate to `config/database.php`.
    *   Update the `DB_USER`, `DB_PASS`, and `DB_NAME` constants for your environment.
3.  **Import Database:** Import the provided `.sql` file (if available) or ensure the schema is applied to the database named in your config.
4.  **Install Dependencies:** Run `composer install` to generate the autoloader.
5.  **Access:** Open your browser to `http://localhost/app.cbfinance.rw/login.php`.

---

## 6. How to Use It (User Flows)

### Flow 1: Disbursing a New Loan
1.  Navigate to **Customers** and submit a new application.
2.  An Admin reviews and clicks **Approve**.
3.  Navigate to **Add Loan**, select the customer, and input the loan terms.
4.  The system generates a **Payment Schedule** and creates the initial **Journal Entries** automatically.

### Flow 2: Recording a Payment
1.  Go to **Loans** and click **Record Payment** next to a loan.
2.  Select the installment being paid.
3.  Input the amount. The system automatically recalculates the `balance_remaining` and updates the `Principal Outstanding`.

### Flow 3: Financial Reporting
1.  Go to **Financial Reports**.
2.  Select **Income Statement**.
3.  Pick a date range. The system will bridge the gaps between the physical ledger and the specific loan installments to show a unified **Total Revenue** figure.

---

## 7. Configuration & Extensions

*   **MCRYPT Bridge:** The system includes a custom bridge in `database.php` for TCPDF compatibility with modern PHP versions.
*   **Revenue Rule:** Total Revenue is calculated based on:
    *   **4101 (Interest):** Recognized based on actual installment payments.
    *   **4202 (Upfront Fee):** Recognized at the time of disbursement.
    *   **Other 4xxx:** Summated directly from the ledger.

---
© 2026 CB Finance. Built for precision.
