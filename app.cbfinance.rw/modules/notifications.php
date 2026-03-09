<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) { header('Content-Type: text/html'); }
        echo '<div style="background:#f8d7da;color:#721c24;padding:20px;font-family:monospace;border:2px solid #f5c6cb;margin:20px;">';
        echo '<strong>Fatal PHP Error:</strong><br>';
        echo htmlspecialchars($error['message']) . '<br>';
        echo 'File: ' . htmlspecialchars($error['file']) . ' Line: ' . $error['line'];
        echo '</div>';
    }
});

require_once('vendor/autoload.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$notifications  = [];
$error_message  = '';
$today          = new DateTime();
$today->setTime(0, 0, 0);
$threshold_date = (clone $today)->modify('+3 days');

try {
    require_once __DIR__ . '/../config/database.php';
    $conn = getConnection();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Fetch pending/partially-paid instalments due from today up to +3 days
    $query = "
        SELECT 
            li.instalment_id,
            li.loan_id,
            li.instalment_number,
            li.due_date,
            li.opening_balance,
            li.closing_balance,
            li.principal_amount,
            li.interest_amount,
            li.management_fee,
            li.total_payment,
            li.paid_amount,
            li.balance_remaining,
            li.status,
            li.days_overdue,
            lp.loan_number,
            lp.loan_status,
            c.customer_name,
            c.customer_code
        FROM loan_instalments li
        LEFT JOIN loan_portfolio lp ON li.loan_id = lp.loan_id
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE li.status NOT IN ('Fully Paid')
          AND li.due_date >= CURDATE()
          AND li.due_date <= ?
        ORDER BY li.due_date ASC, c.customer_name ASC
    ";

    $threshold_str = $threshold_date->format('Y-m-d');
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        throw new Exception("Query preparation failed: " . $conn->error . " | SQL: " . $query);
    }

    $stmt->bind_param("s", $threshold_str);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conn->close();

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    error_log("notifications.php error: " . $e->getMessage());
}

// Classify each instalment
$due_today = [];
$due_soon  = []; // 1–3 days

foreach ($notifications as $n) {
    $due = new DateTime($n['due_date']);
    $due->setTime(0, 0, 0);
    $diff = $today->diff($due);
    $days = (int)$diff->days;

    if ($days === 0) {
        $n['_days_diff'] = 0;
        $due_today[] = $n;
    } else {
        $n['_days_diff'] = $days;
        $due_soon[] = $n;
    }
}

$total_count = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Upcoming Due Instalments</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --ink:       #1a1a2e;
            --ink-soft:  #4a4a6a;
            --surface:   #f7f6f2;
            --card-bg:   #ffffff;
            --danger:    #c0392b;
            --danger-lt: #fdf0ee;
            --warn:      #d35400;
            --warn-lt:   #fef5ec;
            --ok:        #1a6b4a;
            --ok-lt:     #edf7f2;
            --accent:    #2c3e8c;
            --accent-lt: #eef0f9;
            --border:    #e4e3dc;
            --shadow:    0 2px 12px rgba(26,26,46,.08);
            --shadow-md: 0 4px 24px rgba(26,26,46,.13);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }

        /* ─── TOPBAR ─── */
        .topbar {
            background: var(--ink);
            color: #fff;
            padding: 18px 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .topbar-brand .logo-mark {
            width: 42px; height: 42px;
            background: var(--accent);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'DM Serif Display', serif;
            font-size: 1.1rem; color: #fff; font-weight: 400;
            letter-spacing: .5px;
        }
        .topbar-brand .brand-name {
            font-family: 'DM Serif Display', serif;
            font-size: 1.25rem; letter-spacing: .3px;
        }
        .topbar-brand .brand-sub {
            font-size: .72rem; opacity: .55; letter-spacing: 1.5px;
            text-transform: uppercase; margin-top: 1px;
        }
        .topbar-actions { display: flex; align-items: center; gap: 10px; }
        .topbar-actions .btn-nav {
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.2);
            color: #fff;
            border-radius: 8px;
            padding: 8px 18px;
            font-size: .85rem;
            text-decoration: none;
            transition: background .2s;
        }
        .topbar-actions .btn-nav:hover { background: rgba(255,255,255,.18); color: #fff; }
        .topbar-actions .btn-nav.btn-overdue {
            background: rgba(192,57,43,.35);
            border-color: rgba(192,57,43,.5);
        }
        .topbar-actions .btn-nav.btn-overdue:hover { background: rgba(192,57,43,.55); }

        /* ─── HERO STRIP ─── */
        .hero-strip {
            background: linear-gradient(100deg, var(--accent) 0%, #1a2660 100%);
            padding: 36px 36px 28px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .hero-strip::after {
            content: '';
            position: absolute;
            right: -60px; top: -60px;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: rgba(255,255,255,.04);
            pointer-events: none;
        }
        .hero-title {
            font-family: 'DM Serif Display', serif;
            font-size: 2rem;
            margin: 0 0 4px;
        }
        .hero-sub { font-size: .9rem; opacity: .7; margin: 0; }
        .hero-date {
            font-size: .78rem; opacity: .55; letter-spacing: .5px;
            margin-top: 10px;
        }

        /* ─── STAT PILLS ─── */
        .stat-row {
            display: flex; flex-wrap: wrap; gap: 12px;
            padding: 24px 36px 10px;
        }
        .stat-pill {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 10px 22px;
            display: flex; align-items: center; gap: 10px;
            box-shadow: var(--shadow);
            font-size: .88rem;
        }
        .stat-pill .pill-dot { width: 10px; height: 10px; border-radius: 50%; }
        .stat-pill .pill-count { font-weight: 600; font-size: 1rem; }
        .stat-pill.pill-warn .pill-dot { background: var(--warn); }
        .stat-pill.pill-warn .pill-count { color: var(--warn); }
        .stat-pill.pill-ok .pill-dot { background: var(--ok); }
        .stat-pill.pill-ok .pill-count { color: var(--ok); }
        .stat-pill.pill-all .pill-dot { background: var(--accent); }
        .stat-pill.pill-all .pill-count { color: var(--accent); }

        /* ─── MAIN CONTENT ─── */
        .content-area {
            padding: 10px 36px 48px;
            max-width: 1200px;
        }

        /* ─── SECTION LABEL ─── */
        .section-label {
            display: flex; align-items: center; gap: 10px;
            margin: 28px 0 14px;
        }
        .section-label .label-pill {
            font-size: .7rem; font-weight: 600;
            letter-spacing: 1.2px; text-transform: uppercase;
            padding: 4px 12px; border-radius: 20px;
        }
        .section-label .label-line { flex: 1; height: 1px; background: var(--border); }
        .lbl-warn .label-pill { background: var(--warn-lt); color: var(--warn); }
        .lbl-ok .label-pill   { background: var(--ok-lt);   color: var(--ok); }

        /* ─── NOTIFICATION CARD ─── */
        .notif-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-left: 4px solid transparent;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 10px;
            cursor: pointer;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 18px;
            box-shadow: var(--shadow);
            text-decoration: none;
            color: inherit;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .notif-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: inherit;
            text-decoration: none;
        }
        .notif-card.card-warn { border-left-color: var(--warn); }
        .notif-card.card-ok   { border-left-color: var(--ok); }

        .notif-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .card-warn .notif-icon { background: var(--warn-lt); color: var(--warn); }
        .card-ok .notif-icon   { background: var(--ok-lt);   color: var(--ok); }

        .notif-body { min-width: 0; }
        .notif-customer {
            font-weight: 600; font-size: .97rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .notif-meta {
            font-size: .78rem; color: var(--ink-soft);
            margin-top: 2px;
            display: flex; flex-wrap: wrap; gap: 10px;
        }
        .notif-meta span { display: flex; align-items: center; gap: 4px; }

        .notif-amounts { text-align: right; flex-shrink: 0; }
        .notif-balance { font-size: 1.15rem; font-weight: 700; letter-spacing: -.5px; }
        .card-warn .notif-balance { color: var(--warn); }
        .card-ok .notif-balance   { color: var(--ok); }
        .notif-total { font-size: .75rem; color: var(--ink-soft); margin-top: 2px; }
        .notif-badge {
            font-size: .7rem; font-weight: 600;
            padding: 2px 9px; border-radius: 20px;
            margin-top: 5px; display: inline-block;
        }
        .card-warn .notif-badge { background: var(--warn-lt); color: var(--warn); }
        .card-ok .notif-badge   { background: var(--ok-lt);   color: var(--ok); }

        .chevron-ico { color: var(--border); font-size: .85rem; transition: color .15s; }
        .notif-card:hover .chevron-ico { color: var(--ink-soft); }

        .empty-state {
            text-align: center; padding: 64px 24px;
            color: var(--ink-soft);
        }
        .empty-state i { font-size: 3rem; opacity: .25; margin-bottom: 16px; }
        .empty-state h5 { font-family: 'DM Serif Display', serif; color: var(--ink); }

        .error-strip {
            background: var(--danger-lt);
            border-left: 4px solid var(--danger);
            border-radius: 8px;
            padding: 14px 20px;
            color: var(--danger);
            margin: 20px 36px;
            font-size: .9rem;
        }

        .search-wrap { padding: 16px 36px 0; max-width: 480px; }
        .search-input {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 16px 10px 42px;
            font-family: 'DM Sans', sans-serif;
            font-size: .88rem;
            background: var(--card-bg);
            width: 100%;
            outline: none;
            transition: border-color .15s;
        }
        .search-input:focus { border-color: var(--accent); }
        .search-wrap-inner { position: relative; }
        .search-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--ink-soft); font-size: .85rem;
            pointer-events: none;
        }

        @media (max-width: 640px) {
            .hero-strip, .stat-row, .content-area, .search-wrap { padding-left: 16px; padding-right: 16px; }
            .topbar { padding: 14px 16px; }
            .notif-card { grid-template-columns: auto 1fr; }
            .notif-amounts { display: none; }
            .hero-title { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<!-- ─── TOPBAR ─── -->
<div class="topbar">
    <div class="topbar-brand">
        <div class="logo-mark">CBF</div>
        <div>
            <div class="brand-name">Capital Bridge Finance</div>
            <div class="brand-sub">Loan Management System</div>
        </div>
    </div>
    <div class="topbar-actions">
        <a href="?page=overdue" class="btn-nav btn-overdue">
            <i class="fas fa-exclamation-triangle me-1"></i> View Overdue
        </a>
        <a href="index.php?page=loans" class="btn-nav">
            <i class="fas fa-arrow-left me-1"></i> Back to Loans
        </a>
    </div>
</div>

<!-- ─── HERO ─── -->
<div class="hero-strip">
    <div class="hero-title">
        <i class="fas fa-bell" style="font-size:1.5rem;opacity:.8;margin-right:10px;"></i>
        Payment Notifications
    </div>
    <p class="hero-sub">Instalments due today and within the next 3 days — requiring your attention</p>
    <p class="hero-date">
        <i class="fas fa-calendar-day me-1"></i>
        Today: <?php echo $today->format('l, d F Y'); ?>
        &nbsp;·&nbsp;
        Threshold: <?php echo $threshold_date->format('d F Y'); ?>
    </p>
</div>

<?php if ($error_message): ?>
<div class="error-strip">
    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- ─── STAT PILLS ─── -->
<div class="stat-row">
    <div class="stat-pill pill-all">
        <div class="pill-dot"></div>
        <span class="pill-count"><?php echo $total_count; ?></span>
        <span>Total Alerts</span>
    </div>
    <div class="stat-pill pill-warn">
        <div class="pill-dot"></div>
        <span class="pill-count"><?php echo count($due_today); ?></span>
        <span>Due Today</span>
    </div>
    <div class="stat-pill pill-ok">
        <div class="pill-dot"></div>
        <span class="pill-count"><?php echo count($due_soon); ?></span>
        <span>Due in 1–3 Days</span>
    </div>
</div>

<!-- ─── SEARCH ─── -->
<div class="search-wrap">
    <div class="search-wrap-inner">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="search-input" id="searchInput"
               placeholder="Search by customer, loan number…" oninput="filterCards()">
    </div>
</div>

<!-- ─── CONTENT ─── -->
<div class="content-area">

    <?php if ($total_count === 0 && !$error_message): ?>
    <div class="empty-state">
        <div><i class="fas fa-check-circle"></i></div>
        <h5>All Clear!</h5>
        <p>No instalments are due within the next 3 days.<br>Check back later.</p>
    </div>
    <?php endif; ?>

    <?php
    function renderCard(array $n, string $cardClass, string $dayLabel): string {
        $balance   = number_format(floatval($n['balance_remaining']), 0);
        $total     = number_format(floatval($n['total_payment']), 0);
        $principal = number_format(floatval($n['principal_amount']), 0);
        $interest  = number_format(floatval($n['interest_amount']), 0);
        $fee       = number_format(floatval($n['management_fee']), 0);
        $dueDate   = date('d M Y', strtotime($n['due_date']));
        $status    = htmlspecialchars($n['status']);
        $customer  = htmlspecialchars($n['customer_name'] ?? 'N/A');
        $loanNum   = htmlspecialchars($n['loan_number'] ?? 'N/A');
        $loanId    = intval($n['loan_id']);
        $instNum   = intval($n['instalment_number']);

        $icons = [
            'card-warn' => 'fa-clock',
            'card-ok'   => 'fa-calendar-check',
        ];
        $ico = $icons[$cardClass] ?? 'fa-bell';

        return <<<HTML
        <a href="index.php?page=recordpayment&loan_id={$loanId}"
           class="notif-card {$cardClass} notif-searchable"
           title="Click to process payment — Loan ID: {$loanId}">
            <div class="notif-icon"><i class="fas {$ico}"></i></div>
            <div class="notif-body">
                <div class="notif-customer">{$customer}</div>
                <div class="notif-meta">
                    <span><i class="fas fa-hashtag"></i> Loan {$loanNum}</span>
                    <span><i class="fas fa-layer-group"></i> Instalment #{$instNum}</span>
                    <span><i class="fas fa-calendar"></i> Due {$dueDate}</span>
                    <span title="Principal / Interest / Fee">
                        <i class="fas fa-coins"></i> P: {$principal} &nbsp;|&nbsp; I: {$interest} &nbsp;|&nbsp; F: {$fee}
                    </span>
                    <span><i class="fas fa-tag"></i> {$status}</span>
                </div>
            </div>
            <div class="notif-amounts">
                <div class="notif-balance">{$balance}</div>
                <div class="notif-total">Total due: {$total}</div>
                <div class="notif-badge">{$dayLabel}</div>
            </div>
            <div class="chevron-ico"><i class="fas fa-chevron-right"></i></div>
        </a>
HTML;
    }
    ?>

    <!-- DUE TODAY -->
    <?php if (!empty($due_today)): ?>
    <div class="section-label lbl-warn">
        <div class="label-pill">
            <i class="fas fa-clock me-1"></i>Due Today (<?php echo count($due_today); ?>)
        </div>
        <div class="label-line"></div>
    </div>
    <?php foreach ($due_today as $n):
        echo renderCard($n, 'card-warn', 'Due Today');
    endforeach; ?>
    <?php endif; ?>

    <!-- DUE SOON (1–3 days) -->
    <?php if (!empty($due_soon)): ?>
    <div class="section-label lbl-ok">
        <div class="label-pill">
            <i class="fas fa-calendar-check me-1"></i>Due in 1–3 Days (<?php echo count($due_soon); ?>)
        </div>
        <div class="label-line"></div>
    </div>
    <?php foreach ($due_soon as $n):
        $diff  = $n['_days_diff'];
        $label = $diff === 1 ? 'Due Tomorrow' : 'Due in ' . $diff . ' days';
        echo renderCard($n, 'card-ok', $label);
    endforeach; ?>
    <?php endif; ?>

</div>

<script>
function filterCards() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('.notif-searchable').forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
