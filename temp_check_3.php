        <!-- Report Navigation Tabs -->
        <div class="row mb-4 no-print">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $report_type == 'trial_balance' ? 'active' : ''; ?>" 
                                        type="button" onclick="switchReportType('trial_balance')">
                                    <i class="fas fa-balance-scale me-1"></i> Trial Balance
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $report_type == 'balance_sheet' ? 'active' : ''; ?>" 
                                        type="button" onclick="switchReportType('balance_sheet')">
                                    <i class="fas fa-file-invoice me-1"></i> Balance Sheet
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $report_type == 'income_statement' ? 'active' : ''; ?>" 
                                        type="button" onclick="switchReportType('income_statement')">
                                    <i class="fas fa-chart-line me-1"></i> Income Statement
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $report_type == 'income_analysis' ? 'active' : ''; ?>" 
                                        type="button" onclick="switchReportType('income_analysis')">
                                    <i class="fas fa-search-dollar me-1"></i> Income Analysis (Customer)
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Content -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center no-print">
                        <h5 class="mb-0">
                            <i class="fas <?php 
                                echo $report_type == 'trial_balance' ? 'fa-balance-scale' : 
                                     ($report_type == 'balance_sheet' ? 'fa-file-invoice' : 'fa-chart-line'); 
                            ?> me-2"></i>
                            <?php echo htmlspecialchars($report_title); ?>
                        </h5>
                        <div>
                            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn btn-outline-primary btn-sm ms-2" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </button>
                            <button class="btn btn-outline-danger btn-sm ms-2" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> Export to PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Report Header Info -->
                        <div class="report-header text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h4 class="text-white">Accounting & Loan Management System</h4>
                            <h5 class="text-white"><?php echo htmlspecialchars($report_title); ?></h5>
                            <p class="text-white">
                                <?php if ($report_type == 'balance_sheet'): ?>
                                    As of <?php echo date('F d, Y', strtotime($end_date)); ?>
                                <?php else: ?>
                                    Period: <?php echo date('F d, Y', strtotime($start_date)); ?> 
                                    to <?php echo date('F d, Y', strtotime($end_date)); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if (empty($report_data)): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> No data found for the selected period.
                        </div>
                        <?php else: ?>
                        
                        <?php if ($report_type == 'balance_sheet'): ?>
                        <!-- =============================================
                             BALANCE SHEET â€” ENHANCED TWO-COLUMN LAYOUT
                        ============================================== -->

                        <?php
                        // Pre-group each section by class for clean rendering
                        $asset_groups = [];
                        foreach ($assets_data as $row) {
                            $cls = trim($row['class']) ?: 'Assets';
                            if (!isset($asset_groups[$cls])) $asset_groups[$cls] = ['rows' => [], 'subtotal' => 0];
                            $asset_groups[$cls]['rows'][]    = $row;
                            $asset_groups[$cls]['subtotal'] += $row['closing_balance'];
                        }

                        $equity_groups = [];
                        foreach ($equity_data as $row) {
                            $cls = trim($row['class']) ?: 'Equity';
                            if (!isset($equity_groups[$cls])) $equity_groups[$cls] = ['rows' => [], 'subtotal' => 0];
                            $equity_groups[$cls]['rows'][]    = $row;
                            $equity_groups[$cls]['subtotal'] += $row['closing_balance'];
                        }

                        $liability_groups = [];
                        foreach ($liabilities_data as $row) {
                            $cls = trim($row['class']) ?: 'Liabilities';
                            if (!isset($liability_groups[$cls])) $liability_groups[$cls] = ['rows' => [], 'subtotal' => 0];
                            $liability_groups[$cls]['rows'][]    = $row;
                            $liability_groups[$cls]['subtotal'] += $row['closing_balance'];
                        }
                        ?>

                        <div class="mt-4 bs-wrapper" id="reportTable">

                            <!-- LEFT COLUMN: ASSETS -->
                            <div class="bs-col">
                                <div class="bs-section-title assets-title">
                                    <i class="fas fa-landmark"></i> Assets
                                </div>

                                <?php foreach ($asset_groups as $group_name => $group): ?>
                                <div class="bs-sub-label"><?php echo htmlspecialchars($group_name); ?></div>

                                <?php foreach ($group['rows'] as $asset): ?>
                                <div class="bs-row">
                                    <div class="acct-info">
                                        <span class="account-code"><?php echo htmlspecialchars($asset['account_code']); ?></span>
                                        <span class="acct-name"><?php echo htmlspecialchars($asset['account_name']); ?></span>
                                    </div>
                                    <span class="acct-amount"><?php echo formatMoney($asset['closing_balance']); ?></span>
                                </div>
                                <?php endforeach; // end rows ?>

                                <?php if (count($asset_groups) > 1): // only show subtotal when there are multiple groups ?>
                                <div class="bs-subtotal">
                                    <span><?php echo htmlspecialchars($group_name); ?> Subtotal</span>
                                    <span><?php echo formatMoney($group['subtotal']); ?></span>
                                </div>
                                <?php endif; ?>

                                <?php endforeach; // end asset groups ?>

                                <div class="bs-grand-total assets-total">
                                    <span><i class="fas fa-sigma me-1"></i> TOTAL ASSETS</span>
                                    <span><?php echo formatMoney($total_assets); ?></span>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: EQUITY + LIABILITIES -->
                            <div class="bs-col">

                                <!-- EQUITY -->
                                <div class="bs-section-title equity-title">
                                    <i class="fas fa-user-tie"></i> Owner's Equity
                                </div>

                                <?php foreach ($equity_groups as $group_name => $group): ?>
                                <div class="bs-sub-label equity-sub"><?php echo htmlspecialchars($group_name); ?></div>

                                <?php foreach ($group['rows'] as $equity): ?>
                                <div class="bs-row">
                                    <div class="acct-info">
                                        <span class="account-code"><?php echo htmlspecialchars($equity['account_code']); ?></span>
                                        <span class="acct-name"><?php echo htmlspecialchars($equity['account_name']); ?></span>
                                    </div>
                                    <span class="acct-amount equity-amt"><?php echo formatMoney(abs($equity['closing_balance'])); ?></span>
                                </div>
                                <?php endforeach; // end rows ?>

                                <?php if (count($equity_groups) > 1): ?>
                                <div class="bs-subtotal equity-sub-total">
                                    <span><?php echo htmlspecialchars($group_name); ?> Subtotal</span>
                                    <span><?php echo formatMoney(abs($group['subtotal'])); ?></span>
                                </div>
                                <?php endif; ?>

                                <?php endforeach; // end equity groups ?>

                                <div class="bs-grand-total equity-total">
                                    <span><i class="fas fa-sigma me-1"></i> TOTAL EQUITY</span>
                                    <span><?php echo formatMoney(abs($total_equity)); ?></span>
                                </div>

                                <!-- visual separator -->
                                <div class="bs-section-gap"></div>

                                <!-- LIABILITIES -->
                                <div class="bs-section-title liab-title">
                                    <i class="fas fa-file-invoice-dollar"></i> Liabilities
                                </div>

                                <?php foreach ($liability_groups as $group_name => $group): ?>
                                <div class="bs-sub-label liab-sub"><?php echo htmlspecialchars($group_name); ?></div>

                                <?php foreach ($group['rows'] as $liability): ?>
                                <div class="bs-row">
                                    <div class="acct-info">
                                        <span class="account-code"><?php echo htmlspecialchars($liability['account_code']); ?></span>
                                        <span class="acct-name"><?php echo htmlspecialchars($liability['account_name']); ?></span>
                                    </div>
                                    <span class="acct-amount liab-amt"><?php echo formatMoney(abs($liability['closing_balance'])); ?></span>
                                </div>
                                <?php endforeach; // end rows ?>

                                <?php if (count($liability_groups) > 1): ?>
                                <div class="bs-subtotal liab-sub-total">
                                    <span><?php echo htmlspecialchars($group_name); ?> Subtotal</span>
                                    <span><?php echo formatMoney(abs($group['subtotal'])); ?></span>
                                </div>
                                <?php endif; ?>

                                <?php endforeach; // end liability groups ?>

                                <div class="bs-grand-total liab-total">
                                    <span><i class="fas fa-sigma me-1"></i> TOTAL LIABILITIES</span>
                                    <span><?php echo formatMoney(abs($total_liabilities)); ?></span>
                                </div>

                                <div class="bs-grand-total el-total">
                                    <span><i class="fas fa-equals me-1"></i> TOTAL EQUITY &amp; LIABILITIES</span>
                                    <span><?php echo formatMoney(abs($total_equity) + abs($total_liabilities)); ?></span>
                                </div>
                            </div>
                        </div><!-- /.bs-wrapper -->

                        <?php elseif ($report_type == 'trial_balance'): ?>
                        <!-- TRIAL BALANCE TABLE (unchanged) -->
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-sm table-hover" id="reportTable">
                                <thead class="table-light">
                                    <tr>
                                        <th rowspan="2" style="vertical-align: middle;">Group</th>
                                        <th rowspan="2" style="vertical-align: middle;">Account Code</th>
                                        <th rowspan="2" style="vertical-align: middle;">Account Name</th>
                                        <th colspan="3" class="text-center" style="border-bottom: 2px solid #dee2e6;">Opening Balance</th>
                                        <th colspan="2" class="text-center" style="border-bottom: 2px solid #dee2e6;">Movements</th>
                                        <th colspan="2" class="text-center" style="border-bottom: 2px solid #dee2e6;">Closing Balance</th>
                                    </tr>
                                    <tr>
                                        <th class="text-end">Initial Balance</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-end">Final</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Initialize totals
                                    $grand_initial_balance = 0;
                                    $grand_initial_debit = 0;
                                    $grand_initial_credit = 0;
                                    $grand_movement_debit = 0;
                                    $grand_movement_credit = 0;
                                    $grand_closing_balance = 0;
                                    $grand_closing_debit = 0;
                                    $grand_closing_credit = 0;
                                    
                                    // Group totals
                                    $current_class = '';
                                    $class_initial_balance = 0;
                                    $class_initial_debit = 0;
                                    $class_initial_credit = 0;
                                    $class_movement_debit = 0;
                                    $class_movement_credit = 0;
                                    $class_closing_balance = 0;
                                    $class_closing_debit = 0;
                                    $class_closing_credit = 0;
                                    
                                    foreach ($report_data as $index => $row): 
                                        $initial_balance = $row['initial_balance'];
                                        $period_debit = $row['period_debit'];
                                        $period_credit = $row['period_credit'];
                                        $closing_balance = $row['closing_balance'];
                                        
                                        // Calculate display values for opening balance
                                        $initial_debit = $initial_balance > 0 ? $initial_balance : 0;
                                        $initial_credit = $initial_balance < 0 ? abs($initial_balance) : 0;
                                        
                                        // Movements stay as is
                                        $movement_debit = $period_debit;
                                        $movement_credit = $period_credit;
                                        
                                        // Calculate display values for closing balance
                                        $closing_debit = $closing_balance > 0 ? $closing_balance : 0;
                                        $closing_credit = $closing_balance < 0 ? abs($closing_balance) : 0;
                                        
                                        // Display class header when class changes
                                        if ($row['class'] != $current_class):
                                            // Print class total if not first class
                                            if ($current_class != ''):
                                    ?>
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="3" class="text-end"><?php echo htmlspecialchars($current_class); ?> Total:</td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_movement_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_movement_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_closing_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_closing_balance); ?></td>
                                    </tr>
                                    <?php 
                                                // Reset class totals
                                                $class_initial_balance = 0;
                                                $class_initial_debit = 0;
                                                $class_initial_credit = 0;
                                                $class_movement_debit = 0;
                                                $class_movement_credit = 0;
                                                $class_closing_balance = 0;
                                                $class_closing_debit = 0;
                                                $class_closing_credit = 0;
                                            endif;
                                            
                                            $current_class = $row['class'];
                                        endif;
                                        
                                        // Accumulate class totals
                                        $class_initial_balance += $initial_balance;
                                        $class_initial_debit += $initial_debit;
                                        $class_initial_credit += $initial_credit;
                                        $class_movement_debit += $movement_debit;
                                        $class_movement_credit += $movement_credit;
                                        $class_closing_balance += $closing_balance;
                                        $class_closing_debit += $closing_debit;
                                        $class_closing_credit += $closing_credit;
                                        
                                        // Accumulate grand totals
                                        $grand_initial_balance += $initial_balance;
                                        $grand_initial_debit += $initial_debit;
                                        $grand_initial_credit += $initial_credit;
                                        $grand_movement_debit += $movement_debit;
                                        $grand_movement_credit += $movement_credit;
                                        $grand_closing_balance += $closing_balance;
                                        $grand_closing_debit += $closing_debit;
                                        $grand_closing_credit += $closing_credit;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['class']); ?></td>
                                        <td>
                                            <span class="account-code"><?php echo htmlspecialchars($row['account_code']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($initial_balance); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($initial_debit); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($initial_credit); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($movement_debit); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($movement_credit); ?></td>
                                        <td class="text-end amount-cell <?php echo $closing_balance > 0 ? 'text-success' : ($closing_balance < 0 ? 'text-danger' : ''); ?>">
                                            <?php echo formatMoney($closing_balance); ?>
                                        </td>
                                        <td class="text-end amount-cell fw-bold <?php echo $closing_balance > 0 ? 'text-success' : ($closing_balance < 0 ? 'text-danger' : ''); ?>">
                                            <?php echo formatMoney($closing_balance); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php 
                                    // Print final class total
                                    if ($current_class != ''):
                                    ?>
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="3" class="text-end"><?php echo htmlspecialchars($current_class); ?> Total:</td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_movement_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_movement_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_closing_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_closing_balance); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <!-- GRAND TOTALS ROW -->
                                    <tr class="grand-total-row">
                                        <td colspan="3" class="text-end">GRAND TOTAL:</td>
                                        <td class="text-end"><?php echo formatMoney($grand_initial_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_initial_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_initial_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_movement_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_movement_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_closing_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_closing_balance); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <?php else: ?>
                        <!-- =============================================
                             INCOME STATEMENT â€” ENHANCED TWO-COLUMN LAYOUT
                        ============================================== -->
                        <?php
                        // Pre-group income statement data by section and class
                        $revenue_groups = [];
                        $expense_groups = [];
                        $is_rev_total = 0;
                        $is_exp_total = 0;

                        foreach ($report_data as $row) {
                            $first_digit = substr($row['account_code'], 0, 1);
                            $closing_balance = $row['closing_balance'];
                            $class = $row['class'];

                            if ($first_digit == '4') {
                                $display = abs($closing_balance);
                                $is_rev_total += $display;
                                if (!isset($revenue_groups[$class])) $revenue_groups[$class] = ['rows' => [], 'total' => 0];
                                $revenue_groups[$class]['rows'][] = $row;
                                $revenue_groups[$class]['total'] += $display;
                            } else {
                                $display = $closing_balance;
                                $is_exp_total += $display;
                                if (!isset($expense_groups[$class])) $expense_groups[$class] = ['rows' => [], 'total' => 0];
                                $expense_groups[$class]['rows'][] = $row;
                                $expense_groups[$class]['total'] += $display;
                            }
                        }
                        ?>

                        <div class="mt-4 is-wrapper" id="reportTable">
                            <div class="is-two-col">

                                <!-- LEFT: REVENUE -->
                                <div class="is-col">
                                    <div class="is-section-title rev-title">
                                        <i class="fas fa-arrow-trend-up"></i> Revenue
                                    </div>

                                    <?php foreach ($revenue_groups as $class_name => $group): ?>
                                    <div class="is-sub-label rev-sub"><?php echo htmlspecialchars($class_name); ?></div>
                                    <?php foreach ($group['rows'] as $row):
                                        $display = abs($row['closing_balance']);
                                    ?>
                                    <div class="is-row">
                                        <div class="acct-info">
                                            <span class="account-code"><?php echo htmlspecialchars($row['account_code']); ?></span>
                                            <span class="acct-name"><?php echo htmlspecialchars($row['account_name']); ?></span>
                                        </div>
                                        <span class="acct-amount rev-amt"><?php echo formatMoney($display); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="is-class-total rev-class-total">
                                        <span><?php echo htmlspecialchars($class_name); ?> Total</span>
                                        <span><?php echo formatMoney($group['total']); ?></span>
                                    </div>
                                    <?php endforeach; ?>

                                    <div class="is-section-total rev-total">
                                        <span><i class="fas fa-sigma me-1"></i> TOTAL REVENUE</span>
                                        <span><?php echo formatMoney($is_rev_total); ?></span>
                                    </div>
                                </div>

                                <!-- RIGHT: EXPENSES -->
                                <div class="is-col">
                                    <div class="is-section-title exp-title">
                                        <i class="fas fa-arrow-trend-down"></i> Expenses
                                    </div>

                                    <?php foreach ($expense_groups as $class_name => $group): ?>
                                    <div class="is-sub-label exp-sub"><?php echo htmlspecialchars($class_name); ?></div>
                                    <?php foreach ($group['rows'] as $row):
                                        $display = $row['closing_balance'];
                                    ?>
                                    <div class="is-row">
                                        <div class="acct-info">
                                            <span class="account-code"><?php echo htmlspecialchars($row['account_code']); ?></span>
                                            <span class="acct-name"><?php echo htmlspecialchars($row['account_name']); ?></span>
                                        </div>
                                        <span class="acct-amount exp-amt"><?php echo formatMoney($display); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="is-class-total exp-class-total">
                                        <span><?php echo htmlspecialchars($class_name); ?> Total</span>
                                        <span><?php echo formatMoney($group['total']); ?></span>
                                    </div>
                                    <?php endforeach; ?>

                                    <div class="is-section-total exp-total">
                                        <span><i class="fas fa-sigma me-1"></i> TOTAL EXPENSES</span>
                                        <span><?php echo formatMoney($is_exp_total); ?></span>
                                    </div>
                                </div>

                            </div><!-- /.is-two-col -->

                            <!-- NET INCOME / LOSS BANNER (full width) -->
                            <div class="is-net-bar <?php echo $net_income >= 0 ? 'profit' : 'loss'; ?>">
                                <div>
                                    <div class="net-label">
                                        <i class="fas <?php echo $net_income >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></i>
                                        Net <?php echo $net_income >= 0 ? 'Income (Profit)' : 'Loss'; ?>
                                    </div>
                                    <div style="font-size:0.8rem;opacity:0.8;">
                                        <?php echo $total_revenue > 0 ? number_format(($net_income / $total_revenue) * 100, 2) : '0.00'; ?>% Profit Margin
                                    </div>
