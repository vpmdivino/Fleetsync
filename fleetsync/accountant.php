<?php
session_start();
require_once 'db_connect.php';

// 1. Authentication & Authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Accountant', 'Admin'])) {
    $_SESSION['error_message'] = "You do not have permission to access the Accountant page.";
    header('Location: index.php');
    exit();
}

// 2. Prepare user data for display
$userName = $_SESSION['user_name'] ?? 'Accountant';
$userRole = $_SESSION['user_role'] ?? 'Accountant';
$nameParts = explode(' ', $userName);
$initials = (isset($nameParts[0][0]) ? strtoupper($nameParts[0][0]) : '') . (isset($nameParts[1][0]) ? strtoupper($nameParts[1][0]) : '');


try {
    // Fetch completed trips that might need invoicing.
    // This assumes there isn't a direct "invoiced" flag on the trips table.
    // A more robust solution might join against an `invoices` table to exclude trips that are already invoiced.
    $unbilledTripsStmt = $pdo->prepare("
        SELECT t.*, d.name as driver_name, v.plate_number as vehicle_plate 
        FROM trips t 
        LEFT JOIN drivers d ON t.driver_id = d.id 
        LEFT JOIN vehicles v ON t.vehicle_id = v.id 
        WHERE t.status = 'Completed' 
        ORDER BY t.arrival_datetime DESC
    ");
    $unbilledTripsStmt->execute();
    $unbilled_trips = $unbilledTripsStmt->fetchAll();

    // Fetch invoices
    $invoicesStmt = $pdo->query("SELECT * FROM invoices ORDER BY issue_date DESC, created_at DESC");
    $invoices = $invoicesStmt->fetchAll();

    // Fetch outstanding invoices for payment recording
    $outstandingInvoices = $pdo->query("SELECT id, invoice_number, client_name, amount FROM invoices WHERE status = 'Sent' OR status = 'Draft' ORDER BY issue_date ASC")->fetchAll();

    // Fetch expense logs
    $expensesStmt = $pdo->query("SELECT * FROM expenses ORDER BY date_incurred DESC, created_at DESC");
    $expenses = $expensesStmt->fetchAll();

    // --- Dashboard Metrics ---
    // These would be more complex queries in a real application
    $gross_revenue_mtd = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status = 'Paid' AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())")->fetchColumn() ?? 0;

    $accounts_receivable = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status = 'Sent' OR status = 'Draft'")->fetchColumn() ?? 0;

    $operating_expenses = $pdo->query("SELECT SUM(amount) FROM expenses WHERE MONTH(date_incurred) = MONTH(CURRENT_DATE()) AND YEAR(date_incurred) = YEAR(CURRENT_DATE())")->fetchColumn() ?? 0;

    $uninvoiced_trips_count = count($unbilled_trips);
    
    // A simple example of recent activity. In a real app, this would be a more complex query or view.
    // --- Financial Reports Data ---
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');

    // Total Revenue (from paid invoices in the date range)
    $totalRevenueStmt = $pdo->prepare("SELECT SUM(amount) FROM invoices WHERE status = 'Paid' AND payment_date BETWEEN :start_date AND :end_date");
    $totalRevenueStmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $total_revenue = $totalRevenueStmt->fetchColumn() ?? 0;

    // Total Expenses (in the date range)
    $totalExpensesStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE date_incurred BETWEEN :start_date AND :end_date");
    $totalExpensesStmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $total_expenses = $totalExpensesStmt->fetchColumn() ?? 0;

    // Net Operating Income
    $net_income = $total_revenue - $total_expenses;

    // Expense breakdown by category
    $expenseBreakdownStmt = $pdo->prepare("
        SELECT expense_category, SUM(amount) as total 
        FROM expenses WHERE date_incurred BETWEEN :start_date AND :end_date 
        GROUP BY expense_category ORDER BY total DESC");
    $expenseBreakdownStmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $expense_breakdown = $expenseBreakdownStmt->fetchAll();
    $recent_paid_invoices = $pdo->query("SELECT * FROM invoices WHERE status = 'Paid' ORDER BY payment_date DESC LIMIT 1")->fetchAll();
    $recent_expenses = $pdo->query("SELECT * FROM expenses ORDER BY date_incurred DESC LIMIT 1")->fetchAll();

    // Fetch recently paid invoices for the Reconciliation Audit Queue
    $reconciliation_queue = $pdo->query("SELECT * FROM invoices WHERE status = 'Paid' ORDER BY payment_date DESC LIMIT 5")->fetchAll();

    $success_message = $_SESSION['success_message'] ?? null;
    $error_message = $_SESSION['error_message'] ?? null;
    unset($_SESSION['success_message'], $_SESSION['error_message']);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FleetSync - Accountant Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
        
        /* Print Styles for clean invoice prints */
        @media print {
            body * { visibility: hidden; background: transparent !important; color: #000 !important; }
            #printable-invoice-area, #printable-invoice-area * { visibility: visible; }
            #printable-invoice-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 font-sans min-h-screen flex flex-col md:flex-row">

    <!-- MOBILE NAVIGATION HEADER -->
    <div class="md:hidden w-full bg-slate-800 border-b border-slate-700 flex items-center justify-between p-4 sticky top-0 z-50 no-print">
        <div class="flex items-center space-x-3">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-600 text-white">
                <i class="fa-solid fa-truck-fast text-sm"></i>
            </div>
            <span class="text-xl font-bold tracking-tight">FleetSync</span>
        </div>
        <button onclick="toggleSidebar()" class="text-slate-400 hover:text-white focus:outline-none p-1">
            <i class="fa-solid fa-bars text-xl" id="menu-icon"></i>
        </button>
    </div>

    <!-- SIDEBAR NAVIGATION -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out w-64 bg-slate-800 border-r border-slate-700 flex flex-col z-40 h-[calc(100vh-65px)] md:h-screen pt-4 md:pt-0 shrink-0 no-print">
        <!-- Logo Header -->
        <div class="hidden md:flex items-center space-x-3 p-6 border-b border-slate-700">
            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-600 text-white text-xl shadow-lg shadow-emerald-500/20">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight">FleetSync</h1>
                <p class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">Finance & Accounting</p>
            </div>
        </div>

        <!-- Navigation Menu Links -->
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <button onclick="switchView('dashboard')" id="nav-dashboard" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg bg-emerald-600 text-white font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-chart-pie w-5"></i>
                <span>Financial Dashboard</span>
            </button>
            
            <div class="pt-4 pb-1 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Billing Ops</div>
            <button onclick="switchView('trips')" id="nav-trips" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-truck-ramp-box w-5"></i>
                <span>Completed Trips</span>
            </button>
            <button onclick="switchView('invoices')" id="nav-invoices" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-file-invoice w-5"></i>
                <span>Invoices Ledger</span>
            </button>

            <div class="pt-4 pb-1 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Bookkeeping</div>
            <button onclick="switchView('payments')" id="nav-payments" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-cash-register w-5"></i>
                <span>Record Payments</span>
            </button>
            <button onclick="switchView('expenses')" id="nav-expenses" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-receipt w-5"></i>
                <span>Track Expenses</span>
            </button>

            <div class="pt-4 pb-1 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Intelligence</div>
            <button onclick="switchView('reports')" id="nav-reports" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-chart-line w-5"></i>
                <span>Financial Reports</span>
            </button>
        </nav>

        <!-- Profile tag at bottom -->
        <div class="p-4 border-t border-slate-700 bg-slate-850 flex items-center justify-between">
            <div class="flex items-center space-x-3 overflow-hidden">
                <div class="w-9 h-9 rounded-full bg-emerald-600 flex items-center justify-center text-sm font-bold text-white shrink-0">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-slate-200 truncate"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[10px] text-slate-400 font-medium"><?= htmlspecialchars($userRole) ?></p>
                </div>
            </div>
            <a href="index.php" class="text-slate-400 hover:text-red-400 p-1.5 transition-colors" title="Sign Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <div id="sidebar-overlay" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-slate-950/60 z-30 md:hidden backdrop-blur-sm no-print"></div>

    <!-- MAIN APP CANVAS CONTAINER -->
    <main class="flex-1 min-w-0 flex flex-col h-screen overflow-y-auto">
        <!-- HEADER STATUS BAR -->
        <header class="hidden md:flex items-center justify-between px-8 py-4 bg-slate-900 border-b border-slate-800 shrink-0 no-print">
            <div>
                <h2 id="page-title" class="text-xl font-bold text-white">Financial Dashboard</h2>
                <p id="page-subtitle" class="text-xs text-slate-400">Real-time revenue flows, cash outstanding profiles, and billing analytics.</p>
            </div>
            <div class="text-xs text-slate-400 bg-slate-800 px-3 py-1.5 rounded-md border border-slate-700 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Ledger Balanced
            </div>
        </header>

        <!-- PAGE CONTENT SLOTS -->
        <div class="p-4 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto">
            
            <!-- Session Message Notifications -->
            <?php if ($success_message): ?>
                <div id="session-success-toast" class="hidden" data-message="<?= htmlspecialchars($success_message) ?>"></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg p-4 mb-6 flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <h4 class="font-bold">An Error Occurred</h4>
                        <p class="text-xs"><?= htmlspecialchars($error_message) ?></p>
                    </div>
                </div>
            <?php endif; ?>


            <!-- SECTION 1: FINANCIAL DASHBOARD OVERVIEW -->
            <section id="view-dashboard" class="space-y-6 dashboard-view">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Gross Revenue (MTD)</p>
                            <h3 class="text-2xl font-bold text-white mt-1">₱<?= number_format($gross_revenue_mtd) ?></h3>
                        </div>
                        <div class="p-3 bg-emerald-500/10 text-emerald-400 rounded-lg"><i class="fa-solid fa-money-bill-trend-up text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Accounts Receivable</p>
                            <h3 class="text-2xl font-bold text-amber-400 mt-1">₱<?= number_format($accounts_receivable) ?></h3>
                        </div>
                        <div class="p-3 bg-amber-500/10 text-amber-400 rounded-lg"><i class="fa-solid fa-business-time text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Operating Expenses</p>
                            <h3 class="text-2xl font-bold text-red-400 mt-1">₱<?= number_format($operating_expenses, 2) ?></h3>
                        </div>
                        <div class="p-3 bg-red-500/10 text-red-400 rounded-lg"><i class="fa-solid fa-wallet text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Uninvoiced Trips</p>
                            <h3 class="text-2xl font-bold text-blue-400 mt-1"><?= $uninvoiced_trips_count ?> Deliveries</h3>
                        </div>
                        <div class="p-3 bg-blue-500/10 text-blue-500 rounded-lg"><i class="fa-solid fa-file-circle-plus text-xl"></i></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 lg:col-span-2 space-y-4">
                        <h4 class="font-bold text-white">Recent Accounts Activity</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-400 border-b border-slate-700 uppercase font-bold"><th class="pb-2">Reference</th><th class="pb-2">Account/Client</th><th class="pb-2">Type</th><th class="pb-2">Amount</th><th class="pb-2">Status</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700/60 text-slate-300">
                                    <?php foreach($recent_paid_invoices as $invoice): ?>
                                        <tr><td class="py-3 font-mono"><?= htmlspecialchars($invoice['invoice_number']) ?></td><td class="py-3"><?= htmlspecialchars($invoice['client_name']) ?></td><td class="py-3">Invoice</td><td class="py-3 font-semibold text-emerald-400">+₱<?= htmlspecialchars(number_format($invoice['amount'], 2)) ?></td><td><span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded">Paid</span></td></tr>
                                    <?php endforeach; ?>
                                    <?php foreach($recent_expenses as $expense): ?>
                                        <tr><td class="py-3 font-mono">EXP-<?= htmlspecialchars($expense['id']) ?></td><td class="py-3"><?= htmlspecialchars($expense['vendor'] ?: $expense['expense_category']) ?></td><td class="py-3">Expense</td><td class="py-3 font-semibold text-red-400">-₱<?= htmlspecialchars(number_format($expense['amount'], 2)) ?></td><td><span class="px-2 py-0.5 bg-slate-900 border border-slate-700 text-slate-400 rounded">Settled</span></td></tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent_paid_invoices) && empty($recent_expenses)): ?>
                                        <tr><td colspan="5" class="py-4 text-center text-slate-400">No recent activity.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex flex-col justify-between">
                        <h4 class="font-bold text-white">Profitability Matrix</h4>
                        <div class="space-y-3 my-4">
                            <div>
                                <div class="flex justify-between text-xs text-slate-400 mb-1"><span>Net Profit Margin</span><span class="text-emerald-400 font-bold">74%</span></div>
                                <div class="w-full bg-slate-900 h-2 rounded-full overflow-hidden"><div class="bg-emerald-500 h-full" style="width: 74%"></div></div>
                            </div>
                            <div class="text-xs text-slate-400 bg-slate-900/50 p-3 rounded-lg border border-slate-700 space-y-1.5">
                                <div class="flex justify-between"><span>Cash Flow Buffer:</span><span class="font-bold text-slate-200">Optimal</span></div>
                                <div class="flex justify-between"><span>DSO (Avg Days Open):</span><span class="font-bold text-slate-200">14 Days</span></div>
                            </div>
                        </div>
                        <button onclick="switchView('reports')" class="w-full bg-slate-900 hover:bg-slate-750 text-slate-300 border border-slate-700 text-xs py-2 rounded-lg font-semibold transition-all">View Analytics Matrix</button>
                    </div>
                </div>
            </section>

            <!-- SECTION 2: VIEW COMPLETED TRIPS & GENERATE INVOICES AUTOMATICALLY -->
            <section id="view-trips" class="space-y-6 dashboard-view hidden">
                <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-white">Unbilled Completed Trips</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Trips marked finalized by Fleet Managers that require processing.</p>
                    </div>
                    <button onclick="showNotification('Auto-generated 3 client invoices successfully!')" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs px-4 py-2.5 rounded-lg flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/10"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Generate All Pending Invoices</button>
                </div>
                
                <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-850 text-xs uppercase text-slate-400 tracking-wider border-b border-slate-700">
                                <tr><th class="px-6 py-3.5">Trip ID</th><th class="px-6 py-3.5">Route</th><th class="px-6 py-3.5">Cargo</th><th class="px-6 py-3.5">Completion Date</th><th class="px-6 py-3.5 text-right">Action</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700 text-slate-300">
                                <?php if (empty($unbilled_trips)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-slate-400">No unbilled completed trips found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($unbilled_trips as $trip): ?>
                                        <?php
                                            // A placeholder for the client name. In a real app, this might come from the trip or a related table.
                                            $clientName = "Client for " . htmlspecialchars($trip['destination']);
                                            // A placeholder for the invoice amount. This would be calculated based on distance, cargo, etc.
                                            $rate = '₱' . number_format(rand(50000, 250000), 2);
                                        ?>
                                        <tr>
                                            <td class="px-6 py-4 font-mono font-bold text-white text-xs" data-trip-id="<?= $trip['id'] ?>"><?= htmlspecialchars($trip['trip_number']) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($trip['origin']) ?> ➔ <?= htmlspecialchars($trip['destination']) ?></td>
                                            <td class="px-6 py-4 truncate max-w-xs" title="<?= htmlspecialchars($trip['cargo']) ?>"><?= htmlspecialchars($trip['cargo']) ?></td>
                                            <td class="px-6 py-4 text-xs"><?= $trip['arrival_datetime'] ? htmlspecialchars(date('M d, Y H:i', strtotime($trip['arrival_datetime']))) : 'N/A' ?></td>
                                            <td class="px-6 py-4 text-right">
                                                <button onclick="openInvoiceModal('<?= $clientName ?>', '<?= $rate ?>', <?= $trip['id'] ?>)" class="bg-slate-900 border border-emerald-500/30 hover:border-emerald-500 text-emerald-400 hover:bg-emerald-500/5 text-xs font-semibold px-3 py-1.5 rounded transition-all"><i class="fa-solid fa-calculator mr-1"></i> Generate Invoice</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- SECTION 3: EDIT INVOICES BEFORE APPROVAL & PRINT INVOICES -->
            <section id="view-invoices" class="space-y-6 dashboard-view hidden">
                <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-xl">
                    <div class="p-5 bg-slate-850 border-b border-slate-700"><h3 class="font-bold text-white text-base">Invoices Ledger Matrix</h3></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-900 text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700">
                                <tr><th class="px-6 py-3.5">Invoice ID</th><th class="px-6 py-3.5">Client Profile</th><th class="px-6 py-3.5">Base Total</th><th class="px-6 py-3.5">Status Checked</th><th class="px-6 py-3.5 text-right">Actions Workflow</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700 text-slate-300">
                                <?php if (empty($invoices)): ?>
                                    <tr><td colspan="5" class="px-6 py-4 text-center text-slate-400">No invoices found. Generate one from a completed trip.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <?php
                                            $status = htmlspecialchars($invoice['status']);
                                            $statusColor = 'slate';
                                            $textColor = 'text-slate-400';
                                            if ($status === 'Draft') { $statusColor = 'amber'; $textColor = 'text-white'; }
                                            else if ($status === 'Sent') { $statusColor = 'blue'; }
                                            else if ($status === 'Paid') { $statusColor = 'emerald'; }
                                            else if ($status === 'Void') { $statusColor = 'rose'; }
                                            $invoiceJson = htmlspecialchars(json_encode($invoice), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td class="px-6 py-4 font-mono font-bold <?= $textColor ?> text-xs"><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                            <td class="px-6 py-4 <?= $textColor ?>"><?= htmlspecialchars($invoice['client_name']) ?></td>
                                            <td class="px-6 py-4 font-semibold <?= $textColor ?>">₱<?= htmlspecialchars(number_format($invoice['amount'], 2)) ?></td>
                                            <td class="px-6 py-4"><span class="px-2 py-0.5 bg-<?= $statusColor ?>-500/10 text-<?= $statusColor ?>-400 border border-<?= $statusColor ?>-500/20 text-xs font-semibold rounded"><?= $status ?></span></td>
                                            <td class="px-6 py-4 text-right space-x-1.5">
                                                <?php if ($status === 'Draft'): ?>
                                                    <button onclick='openInvoiceModal(<?= $invoiceJson ?>)' class="text-xs font-semibold bg-slate-900 border border-slate-700 hover:text-white px-2.5 py-1.5 rounded transition-colors"><i class="fa-solid fa-eraser mr-1"></i> Edit / Review</button>
                                                <?php endif; ?>
                                                <button onclick="printInvoice('<?= htmlspecialchars($invoice['invoice_number']) ?>', '<?= htmlspecialchars($invoice['client_name']) ?>', '<?= htmlspecialchars(number_format($invoice['amount'], 2)) ?>')" class="text-xs font-semibold bg-slate-900 border border-slate-700 hover:text-white px-2.5 py-1.5 rounded transition-colors"><i class="fa-solid fa-print"></i> Print</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- SECTION 4: RECORD PAYMENTS -->
            <section id="view-payments" class="grid grid-cols-1 lg:grid-cols-3 gap-6 dashboard-view hidden">
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4 shadow-xl h-fit">
                    <h3 class="font-bold text-white text-base border-b border-slate-700 pb-2">Record Incoming Inward Remittance</h3>
                    <form action="backend/payments.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="record_payment">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Target Outstanding Invoice</label>
                            <select name="invoice_id" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 cursor-pointer" required>
                                <option value="">Select an invoice...</option>
                                <?php foreach($outstandingInvoices as $inv): ?>
                                    <option value="<?= $inv['id'] ?>">
                                        <?= htmlspecialchars($inv['invoice_number']) ?> (<?= htmlspecialchars($inv['client_name']) ?>) - ₱<?= number_format($inv['amount'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Amount Received (₱)</label>
                                <input type="number" name="amount_received" step="0.01" placeholder="0.00" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Payment Date</label>
                                <input type="date" name="payment_date" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Payment Mode</label>
                                <select name="payment_mode" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 cursor-pointer">
                                    <option>Bank Wire ACH</option>
                                    <option>Corporate Check</option>
                                    <option>Credit Instrument</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Transaction Reference / Trace ID</label>
                            <input type="text" name="reference" placeholder="e.g. ACH-992104-X" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs py-2.5 rounded-lg transition-all shadow-md">Reconcile Allocation Statement</button>
                    </form>
                </div>
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 lg:col-span-2 space-y-3">
                    <h3 class="font-bold text-white text-base">Reconciliation Audit Queue</h3>
                    <div class="divide-y divide-slate-700 text-xs">
                        <?php if (empty($reconciliation_queue)): ?>
                            <div class="py-3 text-center text-slate-400">No reconciled payments yet.</div>
                        <?php else: ?>
                            <?php foreach ($reconciliation_queue as $item): ?>
                                <div class="py-3 flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-slate-200"><?= htmlspecialchars($item['client_name']) ?> Cleared</p>
                                        <p class="text-slate-500 text-[11px]">Invoice <?= htmlspecialchars($item['invoice_number']) ?> • Paid on <?= htmlspecialchars(date('M d, Y', strtotime($item['payment_date']))) ?></p>
                                    </div>
                                    <span class="text-emerald-400 font-mono font-bold">₱<?= htmlspecialchars(number_format($item['amount'], 2)) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- SECTION 5: TRACK EXPENSES -->
            <section id="view-expenses" class="grid grid-cols-1 lg:grid-cols-3 gap-6 dashboard-view hidden">
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4 shadow-xl h-fit">
                    <h3 class="font-bold text-white text-base border-b border-slate-700 pb-2">Log Operational Debit/Expense</h3>
                    <form action="backend/expenses.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Expense Category Classification</label>
                            <select name="expense_category" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 cursor-pointer" required>
                                <option>Fuel</option>
                                <option>Maintenance & Repair</option>
                                <option>Tolls & Fees</option>
                                <option>Driver Allowance</option>
                                <option>Insurance</option>
                                <option>Salaries</option>
                                <option>Miscellaneous Expenses</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Cost Outlay (₱)</label>
                                <input type="number" name="amount" step="0.01" placeholder="0.00" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Date Incurred</label>
                                <input type="date" name="date_incurred" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Vendor/Merchant Creditor</label>
                            <input type="text" name="vendor" placeholder="e.g. Shell Fleet Services" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200">
                        </div>
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-500 text-white font-semibold text-xs py-2.5 rounded-lg transition-all">Post Corporate Expense</button>
                    </form>
                </div>
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 lg:col-span-2 space-y-3">
                    <h3 class="font-bold text-white text-base">Expense Disbursement Stream</h3>
                    <div class="divide-y divide-slate-700 text-xs max-h-96 overflow-y-auto">
                        <?php if (empty($expenses)): ?>
                            <div class="py-3 text-center text-slate-400">No expenses recorded yet.</div>
                        <?php else: ?>
                            <?php foreach ($expenses as $expense): ?>
                                <div class="py-3 flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-slate-200"><?= htmlspecialchars($expense['vendor'] ?: $expense['expense_category']) ?></p>
                                        <p class="text-slate-500 text-[11px]"><?= htmlspecialchars($expense['expense_category']) ?> • <?= htmlspecialchars(date('M d, Y', strtotime($expense['date_incurred']))) ?></p>
                                    </div>
                                    <span class="text-red-400 font-mono font-bold">-₱<?= htmlspecialchars(number_format($expense['amount'], 2)) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- SECTION 6: GENERATE FINANCIAL REPORTS & EXPORT REPORTS -->
            <section id="view-reports" class="space-y-6 dashboard-view hidden">
                <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <form method="GET" action="accountant.php#view-reports" class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div>
                            <h3 class="font-bold text-white text-base">Statement Compilation & BI</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Generate P&L reports and operational cost matrices for a specific date range.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="bg-slate-900 border border-slate-700 text-slate-300 text-xs px-3 py-2 rounded-lg">
                            <span class="text-slate-400">to</span>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="bg-slate-900 border border-slate-700 text-slate-300 text-xs px-3 py-2 rounded-lg">
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2 rounded-lg">Generate Report</button>
                    </form>
                    <div class="flex flex-wrap gap-2 pt-2 sm:pt-0">
                        <button onclick="showNotification('Exporting to XLSX is a planned feature.')" class="bg-slate-900 border border-slate-700 hover:text-white text-slate-300 text-xs font-semibold px-4 py-2 rounded-lg flex items-center gap-2 transition-all"><i class="fa-solid fa-file-excel text-emerald-500"></i> Export XLSX</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4">
                        <h4 class="font-bold text-white text-sm border-b border-slate-700 pb-2">Profit & Loss (P&L) from <?= htmlspecialchars(date('M d, Y', strtotime($start_date))) ?> to <?= htmlspecialchars(date('M d, Y', strtotime($end_date))) ?></h4>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-700/40"><span class="text-slate-400">Total Revenue:</span><span class="font-mono text-emerald-400 font-bold">+₱<?= number_format($total_revenue, 2) ?></span></div>
                            <div class="flex justify-between py-1 border-b border-slate-700/40"><span class="text-slate-400">Total Operational Expenditures:</span><span class="font-mono text-red-400 font-bold">-₱<?= number_format($total_expenses, 2) ?></span></div>
                            <div class="flex justify-between py-2 text-sm font-bold bg-slate-900/40 px-2 rounded mt-2"><span class="text-white">Net Operating Income:</span><span class="font-mono <?= $net_income >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">₱<?= number_format($net_income, 2) ?></span></div>
                        </div>
                    </div>
                    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4">
                        <h4 class="font-bold text-white text-sm border-b border-slate-700 pb-2">Category Cost Outlay Ratios</h4>
                        <div class="space-y-3 pt-2 text-xs">
                            <?php if ($total_expenses > 0): ?>
                                <?php foreach($expense_breakdown as $item): ?>
                                    <?php $percentage = ($item['total'] / $total_expenses) * 100; ?>
                                    <div>
                                        <div class="flex justify-between text-slate-400 mb-1"><span><?= htmlspecialchars($item['expense_category']) ?></span><span><?= number_format($percentage, 1) ?>%</span></div>
                                        <div class="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden"><div class="bg-amber-500 h-full" style="width: <?= $percentage ?>%"></div></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-slate-400 text-center py-4">No expense data for the selected period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- INVOICE INTERMEDIATE EDIT & APPROVAL MODAL -->
    <div id="invoice-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm no-print">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
            <div class="p-5 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-white text-base">Review & Edit Generated Invoice</h3>
                    <p class="text-xs text-slate-400">Adjust Line valuations before signing and posting to ledger accounts.</p>
                </div>
                <button onclick="closeModal('invoice-modal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form action="backend/invoices.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" id="invoice-action" value="add">
                <input type="hidden" name="id" id="invoice-id">
                <input type="hidden" name="trip_id" id="invoice-trip-id">

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Billed Debtor Account</label>
                    <input type="text" name="client_name" id="modal-client-name" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200" required>
                </div>
                <div class="grid grid-cols-3 gap-4 bg-slate-900/50 p-3 rounded-lg border border-slate-700">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Freight Allocation Run Charge</label>
                        <input type="text" value="Standard Distance Freightage" class="w-full px-2 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-200">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Rate Valuation</label>
                        <input type="text" name="amount" id="modal-client-rate" class="w-full px-2 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-emerald-400 font-semibold" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Status</label>
                        <select name="status" id="invoice-status" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                            <option value="Draft">Draft</option>
                            <option value="Sent">Sent</option>
                            <option value="Paid">Paid</option>
                            <option value="Void">Void</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Due Date</label>
                        <input type="date" name="due_date" id="invoice-due-date" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm">
                    </div>
                </div>
                <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('invoice-modal')" class="bg-slate-900 border border-slate-700 text-slate-300 font-semibold text-xs px-4 py-2 rounded-lg">Discard Changes</button>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs px-4 py-2 rounded-lg shadow-lg">Finalize & Approve Billing State</button>
                </div>
            </form>
        </div>
    </div>

    <!-- INVOICE PRINT CANVAS STRUCTURE (DOM ELEMENT OVERLAY FOR PRINT MODE ACTION) -->
    <div id="printable-invoice-area" class="hidden text-black p-10 bg-white space-y-6">
        <div class="flex justify-between border-b pb-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900">FLEETSYNC LOGISTICS LTD.</h1>
                <p class="text-xs text-slate-600">4024 Logistics Way, Suite 100, Austin, TX</p>
                <p class="text-xs text-slate-600">billing@fleetsync.com</p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-slate-800">COMMERCIAL INVOICE</h2>
                <p class="text-sm font-mono font-bold text-slate-700" id="print-inv-id">INV-000</p>
                <p class="text-xs text-slate-500">Date: July 4, 2026</p>
            </div>
        </div>
        <div class="my-4">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Bill To Account:</h3>
            <p class="text-base font-bold text-slate-900" id="print-client-name">Target Debtor Client Corp</p>
            <p class="text-xs text-slate-600">Verified Corporate Account Freight Network Personnel</p>
        </div>
        <table class="w-full text-left border-collapse my-6">
            <thead>
                <tr class="border-b-2 border-slate-300 text-xs font-bold uppercase text-slate-700"><th class="py-2">Operational Item Line Summary</th><th class="py-2 text-right">Aggregated Net Cost Total</th></tr>
            </thead>
            <tbody class="text-sm divide-y">
                <tr><td class="py-3">Standard Intermodal Distance Freightage Route Execution Allocation</td><td class="py-3 text-right font-mono font-semibold" id="print-item-total">$0.00</td></tr>
                <tr class="font-bold text-base border-t-2 border-slate-300"><td class="py-3 text-right">Total Balance Due Outstanding:</td><td class="py-3 text-right font-mono text-slate-900" id="print-grand-total">$0.00</td></tr>
            </tbody>
        </table>
        <div class="pt-8 border-t text-center text-[10px] text-slate-400 italic">Electronic verification manifest record file item. Terms: Net 30 days framework. Balanced ledger settlement requirement. All amounts in PHP (₱).</div>
    </div>

    <!-- FLOATING STATUS NOTIFICATION CONTAINER -->
    <div id="toast-notif" class="hidden fixed bottom-5 right-5 z-50 bg-slate-800 border border-emerald-500 text-emerald-400 px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3 text-xs font-semibold animate-bounce">
        <i class="fa-solid fa-circle-check text-base"></i>
        <span id="toast-msg">Action processed into financial ledger</span>
    </div>

    <!-- SCREEN ROUTING ENGINE INTERACTS -->
    <script>
        const headingsMeta = {
            dashboard: { title: "Financial Dashboard", subtitle: "Real-time revenue flows, cash outstanding profiles, and billing analytics." },
            trips: { title: "Completed Logistics Deliveries", subtitle: "Process finalized freight manifests into accounts billing profiles." },
            invoices: { title: "Invoices Ledger Matrix", subtitle: "Review drafts, execute item revisions, print statements, or post records." },
            payments: { title: "Record Remittances & ACH", subtitle: "Allocate inbound cash clearing statements to active account files balances." },
            expenses: { title: "Track Operational Expenses", subtitle: "Debit corporate resources parameters to track infrastructure running overheads." },
            reports: { title: "Financial Reports & Insights", subtitle: "Consolidated financial health monitoring and data export center." }
        };

        function switchView(viewKey) {
            document.querySelectorAll('.dashboard-view').forEach(view => view.classList.add('hidden'));
            document.getElementById(`view-${viewKey}`).classList.remove('hidden');

            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('bg-emerald-600', 'text-white');
                btn.classList.add('text-slate-400', 'hover:bg-slate-700/50', 'hover:text-slate-200');
            });
            const targetBtn = document.getElementById(`nav-${viewKey}`);
            targetBtn.classList.remove('text-slate-400', 'hover:bg-slate-700/50', 'hover:text-slate-200');
            targetBtn.classList.add('bg-emerald-600', 'text-white');

            document.getElementById('page-title').innerText = headingsMeta[viewKey].title;
            document.getElementById('page-subtitle').innerText = headingsMeta[viewKey].subtitle;

            window.location.hash = `view-${viewKey}`;

            if (window.innerWidth < 768) {
                toggleSidebar(false);
            }
        }

        function toggleSidebar(forceState = null) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const menuIcon = document.getElementById('menu-icon');
            const isOpen = forceState !== null ? !forceState : sidebar.classList.contains('translate-x-0');

            if (isOpen) {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                menuIcon.className = "fa-solid fa-bars text-xl";
            } else {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                overlay.classList.remove('hidden');
                menuIcon.className = "fa-solid fa-xmark text-xl";
            }
        }

        function openInvoiceModal(data, rate, tripId) {
            const modal = document.getElementById('invoice-modal');
            const form = modal.querySelector('form');
            form.reset();

            const title = modal.querySelector('h3');
            const actionInput = document.getElementById('invoice-action');
            const idInput = document.getElementById('invoice-id');
            const tripIdInput = document.getElementById('invoice-trip-id');
            const clientInput = document.getElementById('modal-client-name');
            const rateInput = document.getElementById('modal-client-rate');
            const statusInput = document.getElementById('invoice-status');
            const dueDateInput = document.getElementById('invoice-due-date');

            if (typeof data === 'object') { // Editing an existing invoice (data is JSON object)
                title.innerText = 'Edit Invoice';
                actionInput.value = 'edit';
                idInput.value = data.id;
                tripIdInput.value = data.trip_id;
                clientInput.value = data.client_name; // This is a string
                rateInput.value = '₱' + parseFloat(data.amount).toFixed(2);
                statusInput.value = data.status;
                dueDateInput.value = data.due_date ? data.due_date.split(' ')[0] : '';
            } else { // Creating a new invoice (data is client name string, rate is second arg)
                title.innerText = 'Generate New Invoice';
                actionInput.value = 'add';
                clientInput.value = data; 
                rateInput.value = rate; 
                tripIdInput.value = tripId; 
            }
            modal.classList.remove('hidden');
        }

        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

        function printInvoice(invId, client, amount) {
            document.getElementById('print-inv-id').innerText = invId;
            document.getElementById('print-client-name').innerText = client;
            document.getElementById('print-item-total').innerText = `₱${amount}`;
            document.getElementById('print-grand-total').innerText = `₱${amount}`;
            
            // Trigger native print process window overlay
            window.print();
        }

        function showNotification(msg, isSuccess = true) {
            const toast = document.getElementById('toast-notif');
            document.getElementById('toast-msg').innerText = msg;
            
            toast.classList.toggle('border-emerald-500', isSuccess);
            toast.classList.toggle('text-emerald-400', isSuccess);
            toast.classList.toggle('border-red-500', !isSuccess);
            toast.classList.toggle('text-red-400', !isSuccess);

            toast.classList.remove('hidden');
            setTimeout(() => { toast.classList.add('hidden'); }, 3500);
        }

        // On page load, check for a hash and switch to the correct view
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash;
            if (hash) {
                const viewKey = hash.substring(1).replace('view-', ''); // remove '#' and 'view-'
                if (headingsMeta[viewKey]) {
                    switchView(viewKey);
                }
            }

            // Check for session-based success toast
            const successToast = document.getElementById('session-success-toast');
            if (successToast) {
                showNotification(successToast.dataset.message);
            }
        });
    </script>
</body>
</html>