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
