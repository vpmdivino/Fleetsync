<?php
session_start();
require_once 'db_connect.php';

// 1. Authentication & Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['error_message'] = "You must be logged in as an administrator to access this page.";
    header('Location: index.php');
    exit();
}

// 2. Prepare user data for display
$userName = $_SESSION['user_name'] ?? 'Admin User';
$userRole = $_SESSION['user_role'] ?? 'Administrator';
$nameParts = explode(' ', $userName);
$initials = (isset($nameParts[0][0]) ? strtoupper($nameParts[0][0]) : '') . (isset($nameParts[1][0]) ? strtoupper($nameParts[1][0]) : '');

try {
    // Fetch all users for the user management table
    $usersStmt = $pdo->query("SELECT id, first_name, last_name, email, role, status FROM users ORDER BY created_at DESC");
    $users = $usersStmt->fetchAll();

    // Fetch system logs for the audit trail
    $logsStmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 20");
    $system_logs = $logsStmt->fetchAll();

} catch (PDOException $e) {
    // In a real app, log this and show a user-friendly error.
    die("Database error fetching user data: " . $e->getMessage());
}

$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FleetSync - Admin Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Smooth custom scrolling for sleek look */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 font-sans min-h-screen flex flex-col md:flex-row">

    <!-- MOBILE TOP BAR -->
    <div class="md:hidden w-full bg-slate-800 border-b border-slate-700 flex items-center justify-between p-4 sticky top-0 z-50">
        <div class="flex items-center space-x-3">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-600 text-white">
                <i class="fa-solid fa-truck-fast text-sm"></i>
            </div>
            <span class="text-xl font-bold tracking-tight">FleetSync</span>
        </div>
        <button onclick="toggleSidebar()" class="text-slate-400 hover:text-wwhite focus:outline-none p-1">
            <i class="fa-solid fa-bars text-xl" id="menu-icon"></i>
        </button>
    </div>

    <!-- SIDEBAR NAVIGATION -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out w-64 bg-slate-800 border-r border-slate-700 flex flex-col z-40 h-[calc(100vh-65px)] md:h-screen pt-4 md:pt-0">
        <!-- Logo (Desktop Only) -->
        <div class="hidden md:flex items-center space-x-3 p-6 border-b border-slate-700">
            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-600 text-white text-xl shadow-lg shadow-blue-500/20">
                <i class="fa-solid fa-truck-fast"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight">FleetSync</h1>
                <p class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">Admin Portal</p>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
            <button onclick="switchView('overview')" id="nav-overview" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg bg-blue-600 text-white font-medium text-sm transition-all shadow-md shadow-blue-600/10 nav-btn">
                <i class="fa-solid fa-chart-pie w-5"></i>
                <span>Overall Dashboard</span>
            </button>
            <button onclick="switchView('users')" id="nav-users" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-users-gear w-5"></i>
                <span>Manage Users & Roles</span>
            </button>
            <button onclick="switchView('company')" id="nav-company" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-building w-5"></i>
                <span>Company Info</span>
            </button>
            <button onclick="switchView('logs')" id="nav-logs" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-clock-rotate-left w-5"></i>
                <span>System Edit Logs</span>
            </button>
            <button onclick="switchView('database')" id="nav-database" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-database w-5"></i>
                <span>Backup / Restore</span>
            </button>
        </nav>

        <!-- Sidebar Footer Admin Info -->
        <div class="p-4 border-t border-slate-700 bg-slate-850 flex items-center justify-between">
            <div class="flex items-center space-x-3 overflow-hidden">
                <div class="w-9 h-9 rounded-full bg-slate-600 flex items-center justify-center text-sm font-bold text-white shrink-0">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-slate-200 truncate"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[10px] text-slate-400 font-medium"><?= htmlspecialchars($userRole) ?></p>
                </div>
            </div>
            <a href="index.php" class="text-slate-400 hover:text-red-400 p-1.5 rounded transition-colors" title="Logout">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <!-- Overlay backdrop for mobile menu -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-slate-950/60 z-30 md:hidden backdrop-blur-sm"></div>

    <!-- MAIN APP CONTENT CONTAINER -->
    <main class="flex-1 min-w-0 flex flex-col h-screen overflow-y-auto">
        
        <!-- TOP HEADER BAR (Desktop) -->
        <header class="hidden md:flex items-center justify-between px-8 py-4 bg-slate-900 border-b border-slate-800 shrink-0">
            <div>
                <h2 id="page-title" class="text-xl font-bold text-white">Overall Dashboard</h2>
                <p id="page-subtitle" class="text-xs text-slate-400">Real-time snapshots of operations, active tracking, and diagnostics.</p>
            </div>
            <div class="text-xs text-slate-400 bg-slate-800 px-3 py-1.5 rounded-md border border-slate-700 flex items-center gap-2 font-medium">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                System Live Environment
            </div>
        </header>

        <!-- CONTAINER FOR DYNAMIC VIEWS -->
        <div class="p-4 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto">

            <!-- Session Message Notifications -->
            <?php if ($success_message): ?>
                <div id="session-success-toast" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg p-4 mb-6 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check"></i>
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg p-4 mb-6 flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <p><?= htmlspecialchars($error_message) ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- VIEW 1: OVERALL DASHBOARD OVERVIEW -->
            <section id="view-overview" class="space-y-6 dashboard-view">
                <!-- Metrics Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Fleet</p>
                            <h3 class="text-2xl font-bold text-white">142 / 150</h3>
                        </div>
                        <div class="p-3 bg-blue-500/10 text-blue-500 rounded-lg"><i class="fa-solid fa-truck text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">System Users</p>
                            <h3 class="text-2xl font-bold text-white"><?= count($users) ?> Users</h3>
                        </div>
                        <div class="p-3 bg-purple-500/10 text-purple-500 rounded-lg"><i class="fa-solid fa-users text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Fuel Costs (MTD)</p>
                            <h3 class="text-2xl font-bold text-white">₱2,119,500</h3>
                        </div>
                        <div class="p-3 bg-emerald-500/10 text-emerald-500 rounded-lg"><i class="fa-solid fa-gas-pump text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Alerts</p>
                            <h3 class="text-2xl font-bold text-amber-400">4 Warning</h3>
                        </div>
                        <div class="p-3 bg-amber-500/10 text-amber-500 rounded-lg"><i class="fa-solid fa-triangle-exclamation text-xl"></i></div>
                    </div>
                </div>

                <!-- Snapshot Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Quick Fleet Activity Status -->
                    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 lg:col-span-2 space-y-4">
                        <h4 class="font-bold text-white text-base">Fleet Operations Breakdown</h4>
                        <div class="space-y-3 pt-2">
                            <div>
                                <div class="flex justify-between text-xs font-medium text-slate-400 mb-1">
                                    <span>Transit Deliveries</span>
                                    <span class="text-slate-200">74%</span>
                                </div>
                                <div class="w-full bg-slate-900 h-2 rounded-full overflow-hidden"><div class="bg-blue-500 h-full rounded-full" style="width: 74%"></div></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-medium text-slate-400 mb-1">
                                    <span>Maintenance & Diagnostics</span>
                                    <span class="text-slate-200">12%</span>
                                </div>
                                <div class="w-full bg-slate-900 h-2 rounded-full overflow-hidden"><div class="bg-amber-500 h-full rounded-full" style="width: 12%"></div></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-medium text-slate-400 mb-1">
                                    <span>Idle / Standby Status</span>
                                    <span class="text-slate-200">14%</span>
                                </div>
                                <div class="w-full bg-slate-900 h-2 rounded-full overflow-hidden"><div class="bg-slate-600 h-full rounded-full" style="width: 14%"></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Role Distribution -->
                    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4">
                        <h4 class="font-bold text-white text-base">Account Access Counts</h4>
                        <?php
                            $role_counts = array_count_values(array_column($users, 'role'));
                            $admin_count = $role_counts['Admin'] ?? 0;
                            $fm_count = $role_counts['Fleet Manager'] ?? 0;
                            $acc_count = $role_counts['Accountant'] ?? 0;
                        ?>
                        <div class="divide-y divide-slate-700">
                            <div class="py-2.5 flex justify-between items-center text-sm">
                                <span class="text-slate-400 flex items-center gap-2"><i class="fa-solid fa-user-shield text-red-400 text-xs"></i> Administrators</span>
                                <span class="bg-slate-900 border border-slate-700 px-2.5 py-0.5 rounded text-xs text-slate-300 font-semibold"><?= $admin_count ?></span>
                            </div>
                            <div class="py-2.5 flex justify-between items-center text-sm">
                                <span class="text-slate-400 flex items-center gap-2"><i class="fa-solid fa-truck-ramp-box text-blue-400 text-xs"></i> Fleet Managers</span>
                                <span class="bg-slate-900 border border-slate-700 px-2.5 py-0.5 rounded text-xs text-slate-300 font-semibold"><?= $fm_count ?></span>
                            </div>
                            <div class="py-2.5 flex justify-between items-center text-sm">
                                <span class="text-slate-400 flex items-center gap-2"><i class="fa-solid fa-file-invoice-dollar text-emerald-400 text-xs"></i> Accountants</span>
                                <span class="bg-slate-900 border border-slate-700 px-2.5 py-0.5 rounded text-xs text-slate-300 font-semibold"><?= $acc_count ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- VIEW 2: MANAGE USERS, ROLES & ACCOUNT DEACTIVATION -->
            <section id="view-users" class="space-y-6 dashboard-view hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-800 p-4 rounded-xl border border-slate-700">
                    <div class="relative max-w-xs w-full">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500"><i class="fa-solid fa-magnifying-glass text-xs"></i></span>
                        <input type="text" id="user-search-input" onkeyup="filterUserTable()" placeholder="Search system personnel..." class="w-full bg-slate-900 border border-slate-700 pl-9 pr-4 py-2 rounded-lg text-xs text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="openModal('deactivation-policy-modal')" class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-3.5 py-2 rounded-lg text-xs font-semibold flex items-center justify-center gap-2 border border-slate-600 transition-all shadow-sm">
                            <i class="fa-solid fa-shield-halved text-red-400"></i> Deactivation Rules
                        </button>
                        <button onclick="openUserModal()" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-xs font-semibold flex items-center justify-center gap-2 shadow-lg shadow-blue-500/10">
                            <i class="fa-solid fa-plus"></i> Create New User
                        </button>
                    </div>
                </div>

                <!-- Account Deactivation Policy Summary Card -->
                <div class="bg-slate-800/90 border border-red-500/20 rounded-xl p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-lg bg-red-500/10 text-red-400 shrink-0">
                            <i class="fa-solid fa-user-lock text-base"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-200">Account Deactivation Rules & Governance:</p>
                            <p class="text-slate-400">
                                <span class="text-red-400 font-medium">Soft Deactivation:</span> Immediate sign-in block &bull;
                                <span class="text-blue-400 font-medium">Data Integrity:</span> Historic trips & logs permanently preserved &bull;
                                <span class="text-amber-400 font-medium">Quorum Safeguard:</span> Cannot deactivate self or sole active Administrator &bull;
                                <span class="text-emerald-400 font-medium">Reversible:</span> Single-click reactivation
                            </p>
                        </div>
                    </div>
                    <button type="button" onclick="openModal('deactivation-policy-modal')" class="shrink-0 text-red-400 hover:text-red-300 font-semibold underline underline-offset-2 flex items-center gap-1">
                        Read Policy Details &rarr;
                    </button>
                </div>

                <!-- Responsive Table Wrapper -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-850 border-b border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <th class="px-6 py-4">User Details</th>
                                    <th class="px-6 py-4">Assigned Role</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="user-table-body" class="divide-y divide-slate-700 text-sm text-slate-300">
                                <?php foreach ($users as $user): ?>
                                    <?php
                                        $userJson = htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8');
                                        $is_current_user = ($user['id'] === $_SESSION['user_id']);
                                    ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-white"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                            <div class="text-xs text-slate-400"><?= htmlspecialchars($user['email']) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                                $role_color = 'blue';
                                                if ($user['role'] === 'Admin') $role_color = 'red';
                                                if ($user['role'] === 'Accountant') $role_color = 'emerald';
                                            ?>
                                            <span class="px-2 py-1 bg-<?= $role_color ?>-500/10 border border-<?= $role_color ?>-500/30 text-<?= $role_color ?>-400 text-xs font-medium rounded-md"><?= htmlspecialchars($user['role']) ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($user['status'] === 'Active'): ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs text-emerald-400 font-medium"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 font-medium"><span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span> Deactivated</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right space-x-1.5">
                                            <button onclick='openUserModal(<?= $userJson ?>)' class="text-xs font-semibold text-slate-400 hover:text-white bg-slate-900 border border-slate-700 px-2.5 py-1.5 rounded-md transition-colors"><i class="fa-solid fa-pen mr-1"></i> Edit</button>
                                            <?php if ($user['status'] === 'Active'): ?>
                                                <?php if ($is_current_user): ?>
                                                    <span class="text-xs text-slate-500 italic px-2 py-1" title="You cannot deactivate your own logged-in account (Self-Deactivation Protection Rule)"><i class="fa-solid fa-lock text-[10px] mr-1"></i>Current User</span>
                                                <?php else: ?>
                                                    <button type="button" onclick="promptDeactivateUser(<?= htmlspecialchars(json_encode([
                                                        'id' => $user['id'],
                                                        'name' => $user['first_name'] . ' ' . $user['last_name'],
                                                        'email' => $user['email'],
                                                        'role' => $user['role']
                                                    ]), ENT_QUOTES, 'UTF-8') ?>)" class="text-xs font-semibold text-red-400 hover:bg-red-500/10 border border-transparent hover:border-red-500/20 px-2.5 py-1.5 rounded-md transition-all">Deactivate</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="backend/users.php?action=update_status&id=<?= $user['id'] ?>&status=Active" onclick="return confirm('Are you sure you want to reactivate this user account? Access will be immediately restored.');" class="text-xs font-semibold text-emerald-400 hover:bg-emerald-500/10 border border-transparent hover:border-emerald-500/20 px-2.5 py-1.5 rounded-md transition-all">Activate</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- VIEW 3: MANAGE COMPANY INFORMATION -->
            <section id="view-company" class="dashboard-view hidden">
                <div class="bg-slate-800 rounded-xl border border-slate-700 max-w-3xl overflow-hidden shadow-xl">
                    <div class="p-5 border-b border-slate-700 bg-slate-850">
                        <h3 class="font-bold text-white text-base">Corporate Information</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Configure universal labels and details used across operational manifests & invoicing modules.</p>
                    </div>
                    <form class="p-6 space-y-5" onsubmit="event.preventDefault();">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Legal Company Name</label>
                                <input type="text" value="FleetSync Global Logistics Ltd." class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Operational Tax ID</label>
                                <input type="text" value="TX-892401-C" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Headquarters Street Address</label>
                            <input type="text" value="4024 Logistics Way, Suite 100, Austin, TX" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Support Contact Email</label>
                                <input type="email" value="operations@fleetsync.com" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">System Currency Configuration</label>
                                <select class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all cursor-pointer">
                                    <option value="php" selected>PHP (₱) - Philippine Peso</option>
                                    <option value="usd">USD ($) - US Dollar</option>
                                    <option value="eur">EUR (€) - Euro</option>
                                    <option value="gbp">GBP (£) - British Pound</option>
                                </select>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-slate-700 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-5 py-2.5 rounded-lg shadow-lg shadow-blue-500/10 transition-all">
                                Update Company Info
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- VIEW 4: SYSTEM EDIT LOGS -->
            <section id="view-logs" class="space-y-6 dashboard-view hidden">
                <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-xl">
                    <div class="p-5 border-b border-slate-700 bg-slate-850 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-white text-base">Administrative Track Audit</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Immutable record trail displaying system changes, role alterations, and configurations.</p>
                        </div>
                        <button class="bg-slate-900 border border-slate-700 text-slate-300 hover:text-white text-xs px-3 py-1.5 rounded-lg font-medium transition-colors"><i class="fa-solid fa-download mr-1.5"></i> Export CSV</button>
                    </div>
                    <div class="divide-y divide-slate-700 max-h-96 overflow-y-auto">
                        <?php if (empty($system_logs)): ?>
                            <div class="p-4 text-center text-slate-400">No system logs found.</div>
                        <?php else: ?>
                            <?php foreach ($system_logs as $log): ?>
                                <div class="p-4 sm:px-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-slate-750/30 transition-colors">
                                    <div class="space-y-1">
                                        <p class="text-sm font-semibold text-white"><?= htmlspecialchars($log['action']) ?></p>
                                        <p class="text-xs text-slate-400 flex items-center gap-2">
                                            <span>Actor: <strong><?= htmlspecialchars($log['actor_name']) ?></strong></span> • 
                                            <span class="text-slate-500">IP: <?= htmlspecialchars($log['ip_address']) ?></span>
                                        </p>
                                    </div>
                                    <span class="text-xs text-slate-500 whitespace-nowrap"><i class="fa-regular fa-clock mr-1"></i> <?= htmlspecialchars(date('M d, Y - H:i', strtotime($log['created_at']))) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- VIEW 5: DATABASE BACKUP / RESTORE -->
            <section id="view-database" class="grid grid-cols-1 lg:grid-cols-2 gap-6 dashboard-view hidden">
                <!-- Backup Panel -->
                <form action="backend/database.php" method="POST" class="bg-slate-800 rounded-xl border border-slate-700 p-5 flex flex-col space-y-4 shadow-xl">
                    <input type="hidden" name="action" value="backup">
                    <div class="flex-1 flex flex-col space-y-4">
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <h3 class="font-bold text-white text-base">Generate Database Backup</h3>
                                <p class="text-xs text-slate-400">Instantly package current server schemas, assets logs, and operational history into a downloadable SQL file.</p>
                            </div>
                            <div class="p-2.5 bg-blue-500/10 text-blue-500 rounded-lg"><i class="fa-solid fa-cloud-arrow-up text-lg"></i></div>
                        </div>
                        <div class="p-3.5 bg-slate-900 rounded-lg border border-slate-700 space-y-2">
                            <div class="flex justify-between text-xs"><span class="text-slate-400">Last Automated Backup Run:</span> <span class="text-slate-300 font-semibold">Today at 02:00 AM</span></div>
                            <div class="flex justify-between text-xs"><span class="text-slate-400">File Format:</span> <span class="text-slate-300 font-semibold">.sql</span></div>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs py-2.5 rounded-lg transition-all shadow-md shadow-blue-500/10">
                        Initiate Manual Secure Backup
                    </button>
                </form>

                <!-- Restore Panel -->
                <form action="backend/database.php" method="POST" enctype="multipart/form-data" class="bg-slate-800 rounded-xl border border-slate-700 p-5 flex flex-col space-y-4 shadow-xl">
                    <input type="hidden" name="action" value="restore">
                    <div class="flex-1 flex flex-col space-y-4">
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <h3 class="font-bold text-white text-base">Restore From Backup</h3>
                                <p class="text-xs text-slate-400">Revert the database to a previous state using a .sql backup file. <strong class="text-amber-400">Warning:</strong> This will overwrite all current data.</p>
                            </div>
                            <div class="p-2.5 bg-amber-500/10 text-amber-500 rounded-lg"><i class="fa-solid fa-clock-rotate-left text-lg"></i></div>
                        </div>
                        <div class="border-2 border-dashed border-slate-700 rounded-lg p-5 text-center bg-slate-900/50 hover:bg-slate-900 transition-all">
                            <label for="backup_file_input" class="cursor-pointer">
                                <i class="fa-solid fa-file-arrow-up text-slate-500 text-xl mb-2"></i>
                                <p class="text-xs font-semibold text-slate-300">Select backup file (.sql)</p>
                                <p class="text-[10px] text-slate-500 mt-0.5">Max file size: 50MB</p>
                            </label>
                            <input type="file" name="backup_file" id="backup_file_input" class="hidden" accept=".sql" required>
                        </div>
                    </div>
                    <button type="submit" onclick="return confirm('Are you sure you want to restore the database from this backup? This will overwrite all existing data.');" class="w-full bg-slate-900 border border-amber-500/30 hover:border-amber-500 text-amber-400 hover:bg-amber-500/5 font-semibold text-xs py-2.5 rounded-lg transition-all">
                        Execute Restore Procedure
                    </button>
                </form>
            </section>

        </div>
    </main>

    <!-- CREATE/EDIT USER MODAL CONTAINER (Hidden by Default) -->
    <div id="user-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-5 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                <h3 id="user-modal-title" class="font-bold text-white text-base">Configure User Identity</h3>
                <button onclick="closeModal('user-modal')" class="text-slate-400 hover:text-white transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="backend/users.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" id="user-action" value="add">
                <input type="hidden" name="id" id="user-id">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">First Name</label>
                        <input type="text" name="first_name" id="user-first-name" placeholder="John" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Last Name</label>
                        <input type="text" name="last_name" id="user-last-name" placeholder="Doe" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Email Identity Address</label>
                    <input type="email" name="email" id="user-email" placeholder="name@company.com" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Operational Scope Role</label>
                    <select name="role" id="user-role" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer" required>
                        <option value="Admin">Admin</option>
                        <option value="Fleet Manager">Fleet Manager</option>
                        <option value="Accountant">Accountant</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Password</label>
                    <input type="password" name="password" id="user-password" placeholder="Min. 8 characters" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p id="password-help" class="text-xs text-slate-500 mt-1">Leave blank to keep the current password when editing.</p>
                </div>
                <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('user-modal')" class="bg-slate-900 border border-slate-700 text-slate-300 hover:text-white font-semibold text-xs px-4 py-2 rounded-lg transition-colors">Cancel</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2 rounded-lg shadow-lg shadow-blue-500/10 transition-all">Save Profile Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ACCOUNT DEACTIVATION CONFIRMATION MODAL -->
    <div id="deactivate-confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4">
            <div class="flex items-center space-x-3 text-amber-400">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 class="font-bold text-white text-base">Confirm Account Deactivation</h3>
                    <p class="text-xs text-slate-400">Review policy impact before proceeding</p>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-700/80 rounded-xl p-3.5 space-y-1.5 text-xs text-slate-300">
                <div class="flex justify-between"><span class="text-slate-400">Target Personnel:</span> <span id="deact-user-name" class="font-semibold text-white"></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Email Address:</span> <span id="deact-user-email" class="font-mono text-slate-200"></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Assigned Role:</span> <span id="deact-user-role" class="font-medium text-blue-400"></span></div>
            </div>

            <div class="space-y-2 text-xs text-slate-400">
                <p class="font-semibold text-slate-300">Deactivation Policy Enforcement:</p>
                <ul class="list-disc list-inside space-y-1 text-slate-400">
                    <li>Immediate login revocation across all devices.</li>
                    <li>All historic trips, logs, and financial records are preserved.</li>
                    <li>Account can be reactivated by any Administrator at any time.</li>
                </ul>
            </div>

            <div class="pt-3 border-t border-slate-700 flex justify-end gap-3">
                <button type="button" onclick="closeModal('deactivate-confirm-modal')" class="bg-slate-900 border border-slate-700 text-slate-300 hover:text-white font-semibold text-xs px-4 py-2 rounded-lg transition-colors">Cancel</button>
                <a id="deact-confirm-btn" href="#" class="bg-red-600 hover:bg-red-500 text-white font-semibold text-xs px-4 py-2 rounded-lg shadow-lg shadow-red-500/10 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-user-slash"></i> Deactivate Account
                </a>
            </div>
        </div>
    </div>

    <!-- COMPREHENSIVE DEACTIVATION POLICY MODAL -->
    <div id="deactivation-policy-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
            <div class="p-5 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-red-500/20 text-red-400 flex items-center justify-center">
                        <i class="fa-solid fa-shield-halved text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-base">Account Deactivation Rules & Governance</h3>
                        <p class="text-xs text-slate-400">Official security policy on how and when accounts are deactivated.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('deactivation-policy-modal')" class="text-slate-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4 overflow-y-auto text-xs text-slate-300">
                <!-- Rule 1: How Deactivation Works -->
                <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-blue-400 font-bold text-sm">
                        <i class="fa-solid fa-power-off"></i>
                        <span>1. How an Account is Deactivated (Lifecycle Process)</span>
                    </div>
                    <p class="text-slate-300">
                        When an administrator triggers deactivation, the account status transitions from <span class="text-emerald-400 font-semibold">Active</span> to <span class="text-slate-400 font-semibold">Deactivated</span>. This performs a non-destructive <strong>soft deactivation</strong>:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-slate-400 pl-1">
                        <li><strong>Instant Sign-in Block:</strong> Login attempts are immediately rejected by the authentication system (<code class="text-slate-300 font-mono">backend/login.php</code>).</li>
                        <li><strong>Zero Data Loss:</strong> Historical records (trips, assigned vehicles, cargo manifests, fuel logs, invoices, and system audit logs) are permanently retained.</li>
                        <li><strong>Audit Event Generation:</strong> The deactivation is logged to <code class="text-slate-300 font-mono">system_logs</code> with the acting admin's ID, timestamp, and target email.</li>
                    </ul>
                </div>

                <!-- Rule 2: Security Safeguards & Quorum -->
                <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-amber-400 font-bold text-sm">
                        <i class="fa-solid fa-lock"></i>
                        <span>2. Security Safeguards & Quorum Rules</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-slate-400 pl-1">
                        <li><strong>Self-Deactivation Immunity:</strong> Administrators cannot deactivate their own logged-in account. The action is hidden in the UI and rejected on the server.</li>
                        <li><strong>Last Admin Protection (Quorum):</strong> The system requires at least <strong>one active Administrator</strong> at all times. Deactivating the sole remaining active admin is strictly prohibited.</li>
                    </ul>
                </div>

                <!-- Rule 3: Operational Responsibilities -->
                <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-purple-400 font-bold text-sm">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                        <span>3. Operational Handover Guidelines</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-slate-400 pl-1">
                        <li><strong>Fleet Managers:</strong> Any in-progress dispatches or unassigned cargo loads must be reviewed and transferred to another active manager.</li>
                        <li><strong>Accountants:</strong> Outstanding draft invoices and pending disbursement workflows must be reallocated.</li>
                    </ul>
                </div>

                <!-- Rule 4: Reactivation Policy -->
                <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-emerald-400 font-bold text-sm">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>4. Reactivation Protocol</span>
                    </div>
                    <p class="text-slate-400">
                        Deactivation is completely reversible. Any active Administrator can click <span class="text-emerald-400 font-semibold">Activate</span> to immediately reinstate access, preserving all prior settings, role scopes, and login credentials.
                    </p>
                </div>
            </div>

            <div class="p-4 bg-slate-850 border-t border-slate-700 flex justify-end">
                <button type="button" onclick="closeModal('deactivation-policy-modal')" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-5 py-2.5 rounded-lg shadow-lg">
                    Acknowledge & Close
                </button>
            </div>
        </div>
    </div>

    <!-- UI ROUTING & SCREEN CONTROLLER SCRIPT -->
    <script>
        // Mapping Title configurations for context shifts
        const viewMeta = {
            overview: { title: "Overall Dashboard", sub: "Real-time snapshots of operations, active tracking, and diagnostics." },
            users: { title: "Manage Users & Access Roles", sub: "Provision profiles, shift access scopes, or trigger system deactivations." },
            company: { title: "Manage Company Information", sub: "Central node for overarching business location coordinates and meta tags." },
            logs: { title: "System Edit Logs", sub: "Secured audit trail monitoring active adjustments of high-tier operations." },
            database: { title: "Database Backup / Restore", sub: "Structural fail-safe interfaces to preserve server assets logs securely." }
        };

        // Core single-page screen routing function
        function switchView(viewKey) {
            // Hide all views
            document.querySelectorAll('.dashboard-view').forEach(view => view.classList.add('hidden'));
            // Reveal chosen target view
            document.getElementById(`view-${viewKey}`).classList.remove('hidden');

            // Synchronize Sidebar Button highlight colors
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white', 'shadow-md', 'shadow-blue-600/10');
                btn.classList.add('text-slate-400', 'hover:bg-slate-700/50', 'hover:text-slate-200');
            });
            const targetBtn = document.getElementById(`nav-${viewKey}`);
            targetBtn.classList.remove('text-slate-400', 'hover:bg-slate-700/50', 'hover:text-slate-200');
            targetBtn.classList.add('bg-blue-600', 'text-white', 'shadow-md', 'shadow-blue-600/10');

            // Sync Header text fields dynamically
            document.getElementById('page-title').innerText = viewMeta[viewKey].title;
            document.getElementById('page-subtitle').innerText = viewMeta[viewKey].sub;

            window.location.hash = `view-${viewKey}`;

            // Enforce mobile navigation retraction on menu selection clicks
            if (window.innerWidth < 768) {
                toggleSidebar(false);
            }
        }

        // Sidebar Navigation expansion toggle handler (Mobile viewports only)
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

        // Modal triggers helpers
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

        function openUserModal(user = null) {
            const modal = document.getElementById('user-modal');
            const title = document.getElementById('user-modal-title');
            const form = modal.querySelector('form');
            form.reset();

            const actionInput = document.getElementById('user-action');
            const idInput = document.getElementById('user-id');
            const passwordInput = document.getElementById('user-password');
            const passwordHelp = document.getElementById('password-help');

            if (user) { // Editing existing user
                title.innerText = 'Edit User Profile';
                actionInput.value = 'edit';
                idInput.value = user.id;
                document.getElementById('user-first-name').value = user.first_name;
                document.getElementById('user-last-name').value = user.last_name;
                document.getElementById('user-email').value = user.email;
                document.getElementById('user-role').value = user.role;
                passwordInput.required = false;
                passwordHelp.classList.remove('hidden');
            } else { // Creating new user
                title.innerText = 'Create New User';
                actionInput.value = 'add';
                idInput.value = '';
                passwordInput.required = true;
                passwordHelp.classList.add('hidden');
            }

            modal.classList.remove('hidden');
        }

        // Account Deactivation Modal Launcher
        function promptDeactivateUser(user) {
            document.getElementById('deact-user-name').innerText = user.name;
            document.getElementById('deact-user-email').innerText = user.email;
            document.getElementById('deact-user-role').innerText = user.role;
            document.getElementById('deact-confirm-btn').href = `backend/users.php?action=update_status&id=${user.id}&status=Deactivated`;
            openModal('deactivate-confirm-modal');
        }

        // Real-time table search filter
        function filterUserTable() {
            const query = document.getElementById('user-search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#user-table-body tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        // On page load, check for a hash and switch to the correct view
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash;
            if (hash) {
                const viewKey = hash.substring(1).replace('view-', ''); // remove '#' and 'view-'
                if (viewMeta[viewKey]) {
                    switchView(viewKey);
                }
            }
        });
    </script>
</body>
</html>