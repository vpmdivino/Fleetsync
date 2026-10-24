<?php
session_start();
require_once 'db_connect.php';

// 1. Authentication & Authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Fleet Manager', 'Admin'])) {
    $_SESSION['error_message'] = "You do not have permission to access the Fleet Manager page.";
    header('Location: index.php');
    exit();
}

// 2. Prepare user data for display
$userName = $_SESSION['user_name'] ?? 'Fleet Manager';
$userRole = $_SESSION['user_role'] ?? 'Fleet Manager';
$nameParts = explode(' ', $userName);
$initials = (isset($nameParts[0][0]) ? strtoupper($nameParts[0][0]) : '') . (isset($nameParts[1][0]) ? strtoupper($nameParts[1][0]) : '');


try {
    $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY created_at DESC");
    $vehicles = $stmt->fetchAll();

    $driverStmt = $pdo->query("SELECT * FROM drivers ORDER BY created_at DESC");
    $drivers = $driverStmt->fetchAll();

    $tripStmt = $pdo->query("SELECT t.*, d.name as driver_name, v.plate_number as vehicle_plate 
                             FROM trips t 
                             LEFT JOIN drivers d ON t.driver_id = d.id 
                             LEFT JOIN vehicles v ON t.vehicle_id = v.id 
                             ORDER BY t.created_at DESC");
    $trips = $tripStmt->fetchAll();

    $fuelStmt = $pdo->query("SELECT fl.*, v.plate_number, v.brand, v.model
                             FROM fuel_logs fl
                             JOIN vehicles v ON fl.vehicle_id = v.id
                             ORDER BY fl.log_date DESC");
    $fuel_logs = $fuelStmt->fetchAll();

    $maintenanceStmt = $pdo->query("SELECT ml.*, v.plate_number, v.brand, v.model
                                     FROM maintenance_logs ml
                                     JOIN vehicles v ON ml.vehicle_id = v.id
                                     ORDER BY ml.scheduled_date DESC, ml.created_at DESC");
    $maintenance_logs = $maintenanceStmt->fetchAll();

    // Dashboard Metrics
    $vehicles_in_transit = 0;
    foreach ($vehicles as $vehicle) {
        if ($vehicle['status'] === 'In Use') {
            $vehicles_in_transit++;
        }
    }
    $total_vehicles = count($vehicles);

    $available_drivers = 0;
    // Assuming 'Full-Time' and 'Contractor' are available if not on a trip. This logic can be more complex.
    // For now, let's count drivers with a specific employment status.
    foreach($drivers as $driver) {
        if (in_array($driver['employment_status'], ['Full-Time', 'Part-Time', 'Contractor'])) {
            $available_drivers++;
        }
    }

    $completed_deliveries_mtd = $pdo->query("SELECT COUNT(id) FROM trips WHERE status = 'Completed' AND MONTH(arrival_datetime) = MONTH(CURRENT_DATE()) AND YEAR(arrival_datetime) = YEAR(CURRENT_DATE())")->fetchColumn();

    $maintenance_alerts = $pdo->query("SELECT COUNT(id) FROM maintenance_logs WHERE status = 'Scheduled' AND scheduled_date <= CURDATE() + INTERVAL 7 DAY")->fetchColumn();
    
    $active_trips = array_filter($trips, fn($trip) => in_array($trip['status'], ['In Progress', 'Pending']));
} catch (PDOException $e) {
    // In a real app, you'd want to log this and show a user-friendly error page.
    $error = "Database error: " . $e->getMessage();
    // For simplicity, we'll just output the error. In a production environment, handle this more gracefully.
    die($error);
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
    <title>FleetSync - Fleet Manager Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 font-sans min-h-screen flex flex-col md:flex-row">

    <!-- MOBILE NAVIGATION HEADER -->
    <div class="md:hidden w-full bg-slate-800 border-b border-slate-700 flex items-center justify-between p-4 sticky top-0 z-50">
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
    <aside id="sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out w-64 bg-slate-800 border-r border-slate-700 flex flex-col z-40 h-[calc(100vh-65px)] md:h-screen pt-4 md:pt-0 shrink-0">
        <!-- Logo Header -->
        <div class="hidden md:flex items-center space-x-3 p-6 border-b border-slate-700">
            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-600 text-white text-xl shadow-lg shadow-blue-500/20">
                <i class="fa-solid fa-truck-fast"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight">FleetSync</h1>
                <p class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">Fleet Management</p>
            </div>
        </div>

        <!-- Navigation Menu Links -->
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <button onclick="switchView('dashboard')" id="nav-dashboard" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-chart-line w-5"></i>
                <span>Dashboard Overview</span>
            </button>
            
            <div class="pt-4 pb-1 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Core Assets</div>
            <button onclick="switchView('vehicles')" id="nav-vehicles" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-truck w-5"></i>
                <span>Manage Vehicles</span>
            </button>
            <button onclick="switchView('drivers')" id="nav-drivers" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-id-card w-5"></i>
                <span>Manage Drivers</span>
            </button>

            <div class="pt-4 pb-1 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Operations</div>
            <button onclick="switchView('trips')" id="nav-trips" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-calendar-plus w-5"></i>
                <span>Schedules & Assignments</span>
            </button>
            <button onclick="switchView('tracking')" id="nav-tracking" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-route w-5"></i>
                <span>Active Deliveries & Actions</span>
            </button>
            <button onclick="switchView('history')" id="nav-history" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-clock-rotate-left w-5"></i>
                <span>Trip History</span>
            </button>

            <div class="pt-4 pb-1 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Logs</div>
            <button onclick="switchView('fuel')" id="nav-fuel" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-gas-pump w-5"></i>
                <span>Fuel Consumption</span>
            </button>
            <button onclick="switchView('maintenance')" id="nav-maintenance" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 font-medium text-sm transition-all nav-btn">
                <i class="fa-solid fa-wrench w-5"></i>
                <span>Maintenance Logs</span>
            </button>
        </nav>

        <!-- Personnel profile tag at bottom -->
        <div class="p-4 border-t border-slate-700 bg-slate-850 flex items-center justify-between">
            <div class="flex items-center space-x-3 overflow-hidden">
                <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-sm font-bold text-white shrink-0">
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

    <div id="sidebar-overlay" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-slate-950/60 z-30 md:hidden backdrop-blur-sm"></div>

    <!-- MAIN APP CANVAS CONTAINER -->
    <main class="flex-1 min-w-0 flex flex-col h-screen overflow-y-auto">
        <!-- HEADER STATUS BAR -->
        <header class="hidden md:flex items-center justify-between px-8 py-4 bg-slate-900 border-b border-slate-800 shrink-0">
            <div>
                <h2 id="page-title" class="text-xl font-bold text-white">Dashboard Overview</h2>
                <p id="page-subtitle" class="text-xs text-slate-400">Holistic operations status breakdown and assets tracking summary.</p>
            </div>
            <div class="text-xs text-slate-400 bg-slate-800 px-3 py-1.5 rounded-md border border-slate-700 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Regional Hub Active
            </div>
        </header>

        <!-- PAGE CONTENT SLOTS -->
        <div class="p-4 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto">
            
            <!-- Session Message Notifications -->
            <?php if ($success_message): ?>
                <div id="session-success-toast" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-xl p-4 mb-6 flex items-center justify-between gap-3 shadow-lg">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-circle-check text-lg shrink-0"></i>
                        <p class="font-medium"><?= htmlspecialchars($success_message) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div id="session-error-toast" class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl p-4 mb-6 flex items-center justify-between gap-3 shadow-lg">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-circle-exclamation text-lg shrink-0 text-red-400"></i>
                        <div>
                            <p class="font-bold text-xs uppercase tracking-wider text-red-300">Input Validation / Error</p>
                            <p class="text-sm text-red-200"><?= htmlspecialchars($error_message) ?></p>
                        </div>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-red-400 hover:text-white p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- SECTION 1: OVERALL DASHBOARD OVERVIEW -->
            <section id="view-dashboard" class="space-y-6 dashboard-view">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Vehicles In Transit</p>
                            <h3 class="text-2xl font-bold text-white mt-1"><?= $vehicles_in_transit ?> / <?= $total_vehicles ?></h3>
                        </div>
                        <div class="p-3 bg-blue-500/10 text-blue-500 rounded-lg"><i class="fa-solid fa-truck-moving text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Available Drivers</p>
                            <h3 class="text-2xl font-bold text-white mt-1"><?= $available_drivers ?> Active</h3>
                        </div>
                        <div class="p-3 bg-emerald-500/10 text-emerald-500 rounded-lg"><i class="fa-solid fa-user-check text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Deliveries Completed (MTD)</p>
                            <h3 class="text-2xl font-bold text-white mt-1"><?= $completed_deliveries_mtd ?></h3>
                        </div>
                        <div class="p-3 bg-purple-500/10 text-purple-500 rounded-lg"><i class="fa-solid fa-circle-check text-xl"></i></div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Maintenance Alerts</p>
                            <h3 class="text-2xl font-bold text-amber-400 mt-1"><?= $maintenance_alerts ?> Urgent</h3>
                        </div>
                        <div class="p-3 bg-amber-500/10 text-amber-500 rounded-lg"><i class="fa-solid fa-screwdriver-wrench text-xl"></i></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 lg:col-span-2 space-y-4 min-h-[150px]">
                        <h4 class="font-bold text-white">Active Run Allocations</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-400 border-b border-slate-700 uppercase font-bold"><th class="pb-2">Trip ID</th><th class="pb-2">Assignment</th><th class="pb-2">Destination</th><th class="pb-2">Status</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700/60 text-slate-300">
                                    <?php if (empty($active_trips)): ?>
                                        <tr><td colspan="4" class="py-4 text-center text-slate-400">No active runs at the moment.</td></tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($active_trips, 0, 5) as $trip): // Show max 5 ?>
                                            <?php
                                                $status = htmlspecialchars($trip['status']);
                                                $statusColor = ($status === 'In Progress') ? 'blue' : 'amber';
                                            ?>
                                            <tr>
                                                <td class="py-2.5 font-mono"><?= htmlspecialchars($trip['trip_number']) ?></td>
                                                <td class="py-2.5"><?= htmlspecialchars($trip['vehicle_plate'] ?? 'N/A') ?> (<?= htmlspecialchars($trip['driver_name'] ?? 'N/A') ?>)</td>
                                                <td class="py-2.5"><?= htmlspecialchars($trip['destination']) ?></td>
                                                <td><span class="px-2 py-0.5 bg-<?= $statusColor ?>-500/10 text-<?= $statusColor ?>-400 rounded"><?= $status ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 space-y-3">
                        <h4 class="font-bold text-white">Quick Metrics Action</h4>
                        <div class="p-3 bg-slate-900 rounded-lg border border-slate-700 text-xs flex justify-between">
                            <span class="text-slate-400">Total Fleet Mileage Today:</span><span class="font-bold text-slate-200">1,420 mi</span>
                        </div>
                        <div class="p-3 bg-slate-900 rounded-lg border border-slate-700 text-xs flex justify-between">
                            <span class="text-slate-400">Average Fuel Economy:</span><span class="font-bold text-slate-200">6.8 mpg</span>
                        </div>
                    </div>
                </div>
            </section>

           <section id="view-vehicles" class="space-y-6 dashboard-view hidden">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-800 p-4 rounded-xl border border-slate-700">
        <h3 class="text-base font-bold text-white">Automotive Asset Inventory</h3>
        <button onclick="openVehicleModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Add New Vehicle
        </button>
    </div>
    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[1000px]">
                <thead class="bg-slate-850 text-xs uppercase text-slate-400 tracking-wider border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-3.5">Plate Number</th>
                        <th class="px-6 py-3.5">Vehicle Type</th>
                        <th class="px-6 py-3.5">Brand</th>
                        <th class="px-6 py-3.5">Model</th>
                        <th class="px-6 py-3.5">Capacity</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Mileage</th>
                        <th class="px-6 py-3.5">Registration Date</th>
                        <th class="px-6 py-3.5">Insurance Expiry</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700 text-slate-300">
                    <?php if (empty($vehicles)): ?>
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-slate-400">No vehicles found. Add one to get started!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <?php
                                $status = htmlspecialchars($vehicle['status']);
                                $statusColor = 'slate';
                                if ($status === 'Available') $statusColor = 'emerald';
                                else if ($status === 'In Use') $statusColor = 'blue';
                                else if ($status === 'Under Maintenance') $statusColor = 'amber';
                                else if ($status === 'Retired') $statusColor = 'rose';
                                $vehicleJson = htmlspecialchars(json_encode($vehicle), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td class="px-6 py-4 font-mono font-semibold text-white"><?= htmlspecialchars($vehicle['plate_number']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($vehicle['vehicle_type']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($vehicle['brand']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($vehicle['model']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($vehicle['capacity']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 bg-<?= $statusColor ?>-500/10 text-<?= $statusColor ?>-400 rounded-full text-xs font-medium border border-<?= $statusColor ?>-500/20 whitespace-nowrap"><?= $status ?></span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars(number_format($vehicle['mileage'])) ?> mi</td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars(date('Y-m-d', strtotime($vehicle['registration_date']))) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= $vehicle['insurance_expiry'] ? htmlspecialchars(date('Y-m-d', strtotime($vehicle['insurance_expiry']))) : 'N/A' ?></td>
                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                    <button onclick='openVehicleModal(<?= $vehicleJson ?>)' class="text-slate-400 hover:text-white"><i class="fa-solid fa-marker"></i></button>
                                    <a href="backend/vehicles.php?action=delete&id=<?= $vehicle['id'] ?>" onclick="return confirm('Are you sure you want to delete this vehicle?')" class="text-red-400 hover:text-red-300"><i class="fa-solid fa-trash-can"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

            <!-- SECTION 3: MANAGE DRIVERS -->

<section id="view-drivers" class="space-y-6 dashboard-view hidden">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-800 p-4 rounded-xl border border-slate-700">
        <h3 class="text-base font-bold text-white">Roster Personnel Registry</h3>
        <button onclick="openDriverModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fa-solid fa-user-plus"></i> Onboard Driver
        </button>
    </div>
    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[1200px]">
                <thead class="bg-slate-850 text-xs uppercase text-slate-400 tracking-wider border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-3.5">Name</th>
                        <th class="px-6 py-3.5">License No.</th>
                        <th class="px-6 py-3.5">Expiry</th>
                        <th class="px-6 py-3.5">Contact</th>
                        <th class="px-6 py-3.5">Employment Status</th>
                        <th class="px-6 py-3.5">Date Hired</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700 text-slate-300">
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-slate-400">No drivers found. Onboard one to get started!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $driver): ?>
                            <?php
                                $driverJson = htmlspecialchars(json_encode($driver), ENT_QUOTES, 'UTF-8');
                                $licenseExpiry = new DateTime($driver['license_expiry']);
                                $isExpiringSoon = (new DateTime())->diff($licenseExpiry)->days <= 30 && (new DateTime()) < $licenseExpiry;
                                $expiryColor = $isExpiringSoon ? 'text-amber-400' : '';
                            ?>
                            <tr>
                                <td class="px-6 py-4 font-semibold text-white"><?= htmlspecialchars($driver['name']) ?></td>
                                <td class="px-6 py-4 font-mono text-xs"><?= htmlspecialchars($driver['license_no']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap <?= $expiryColor ?>"><?= htmlspecialchars(date('Y-m-d', strtotime($driver['license_expiry']))) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($driver['contact']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($driver['employment_status']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars(date('Y-m-d', strtotime($driver['date_hired']))) ?></td>
                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                    <button onclick='openDriverModal(<?= $driverJson ?>)' class="text-slate-400 hover:text-white"><i class="fa-solid fa-user-pen"></i></button>
                                    <a href="backend/drivers.php?action=delete&id=<?= $driver['id'] ?>" onclick="return confirm('Are you sure you want to delete this driver?')" class="text-red-400 hover:text-red-300"><i class="fa-solid fa-trash-can"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

            <!-- SECTION 4: SCHEDULES & ASSIGNMENTS -->
            <section id="view-trips" class="space-y-6 dashboard-view hidden">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-800 p-4 rounded-xl border border-slate-700">
        <div>
            <h3 class="text-base font-bold text-white">Trip Management & Dispatch Schedules</h3>
            <p class="text-xs text-slate-400">Manage dispatch runs, enforce distance validation and scheduling policies.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <button onclick="openSchedulingPolicyModal()" class="bg-slate-700 hover:bg-slate-600 text-slate-200 font-semibold text-xs px-3.5 py-2 rounded-lg flex items-center gap-2 border border-slate-600 shadow-sm transition-all">
                <i class="fa-solid fa-book-bookmark text-blue-400"></i> Scheduling Rules
            </button>
            <button onclick="openTripModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2 rounded-lg flex items-center gap-2 shadow-sm transition-all">
                <i class="fa-solid fa-plus"></i> Create New Trip
            </button>
        </div>
    </div>

    <!-- Live Policy Rule Summary Pill -->
    <div class="bg-slate-800/90 border border-blue-500/20 rounded-xl p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-lg bg-blue-500/10 text-blue-400 shrink-0">
                <i class="fa-solid fa-shield-halved text-base"></i>
            </div>
            <div>
                <p class="font-semibold text-slate-200">Enforced Scheduling & Distance Rules:</p>
                <p class="text-slate-400">
                    <span class="text-blue-400 font-medium">Distance:</span> Non-negative (&ge; 0 mi) &bull;
                    <span class="text-emerald-400 font-medium">Earliest Schedule:</span> Max 30 days retroactive (Outdated years 2012, 2023 rejected) &bull;
                    <span class="text-amber-400 font-medium">Latest Schedule:</span> Max +1 year ahead &bull;
                    <span class="text-purple-400 font-medium">Sequence:</span> Arrival &ge; Departure (Max 14 days)
                </p>
            </div>
        </div>
        <button type="button" onclick="openSchedulingPolicyModal()" class="shrink-0 text-blue-400 hover:text-blue-300 font-semibold underline underline-offset-2 flex items-center gap-1">
            Read Policy Details &rarr;
        </button>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[1600px]">
                <thead class="bg-slate-850 text-xs uppercase text-slate-400 tracking-wider border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-3.5">Trip #</th>
                        <th class="px-6 py-3.5">Driver</th>
                        <th class="px-6 py-3.5">Vehicle</th>
                        <th class="px-6 py-3.5">Origin</th>
                        <th class="px-6 py-3.5">Destination</th>
                        <th class="px-6 py-3.5">Departure</th>
                        <th class="px-6 py-3.5">Arrival</th>
                        <th class="px-6 py-3.5">Cargo</th>
                        <th class="px-6 py-3.5">Distance</th>
                        <th class="px-6 py-3.5">Fuel Used</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700 text-slate-300">
                    <?php if (empty($trips)): ?>
                        <tr>
                            <td colspan="12" class="px-6 py-4 text-center text-slate-400">No trips found. Create one to get started!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trips as $trip): ?>
                            <?php
                                $tripJson = htmlspecialchars(json_encode($trip), ENT_QUOTES, 'UTF-8');
                                $status = htmlspecialchars($trip['status']);
                                $statusColor = 'slate';
                                if ($status === 'Pending') $statusColor = 'amber';
                                else if ($status === 'In Progress') $statusColor = 'blue';
                                else if ($status === 'Completed') $statusColor = 'emerald';
                                else if ($status === 'Canceled') $statusColor = 'rose';
                            ?>
                            <tr>
                                <td class="px-6 py-4 font-mono font-semibold text-white"><?= htmlspecialchars($trip['trip_number']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($trip['driver_name'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($trip['vehicle_plate'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($trip['origin']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($trip['destination']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= $trip['departure_datetime'] ? htmlspecialchars(date('Y-m-d H:i', strtotime($trip['departure_datetime']))) : 'N/A' ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= $trip['arrival_datetime'] ? htmlspecialchars(date('Y-m-d H:i', strtotime($trip['arrival_datetime']))) : 'N/A' ?></td>
                                <td class="px-6 py-4 truncate max-w-xs"><?= htmlspecialchars($trip['cargo']) ?></td>
                                <td class="px-6 py-4"><?= $trip['distance'] ? htmlspecialchars($trip['distance']) . ' mi' : 'N/A' ?></td>
                                <td class="px-6 py-4"><?= $trip['fuel_used'] ? htmlspecialchars($trip['fuel_used']) . ' gal' : 'N/A' ?></td>
                                <td class="px-6 py-4"><span class="px-2 py-0.5 bg-<?= $statusColor ?>-500/10 text-<?= $statusColor ?>-400 rounded-full text-xs font-medium border border-<?= $statusColor ?>-500/20 whitespace-nowrap"><?= $status ?></span></td>
                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                    <button onclick='openTripModal(<?= $tripJson ?>)' class="text-slate-400 hover:text-white" title="Edit"><i class="fa-solid fa-marker"></i></button>
                                    <a href="backend/trips.php?action=delete&id=<?= $trip['id'] ?>" onclick="return confirm('Are you sure you want to delete this trip?')" class="text-red-400 hover:text-red-300" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>


            <!-- SECTION 5: ACTIONS (RECORD DELIVERIES, FUEL, MAINTENANCE & COMPLETE TRIPS) -->
            <section id="view-tracking" class="grid grid-cols-1 lg:grid-cols-3 gap-6 dashboard-view hidden">
                <!-- Action Execution Center -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 lg:col-span-2 space-y-6 shadow-xl">
                    <h3 class="font-bold text-white text-base border-b border-slate-700 pb-2">Delivery Tracking</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Record Delivery -->
                        <div class="p-4 bg-slate-900/50 border border-slate-700 rounded-xl space-y-3">
                            <span class="text-xs font-bold text-blue-400 uppercase tracking-wide flex items-center gap-1.5"><i class="fa-solid fa-boxes-packing"></i> Record Delivery</span>
                            <form action="backend/tracking.php" method="POST" class="space-y-2">
                                <input type="hidden" name="action" value="log_event">
                                <select name="trip_id" class="w-full bg-slate-900 border border-slate-700 rounded p-2 text-xs text-slate-200" required>
                                    <option value="">Select a trip</option>
                                    <?php foreach($active_trips as $trip): ?>
                                        <option value="<?= $trip['id'] ?>"><?= htmlspecialchars($trip['trip_number']) ?> (<?= htmlspecialchars($trip['destination']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="datetime-local" name="event_datetime" placeholder="MM/DD/YYYY hh:mm A" class="w-full bg-slate-900 border border-slate-700 rounded p-2 text-xs text-slate-200">
                                <select name="event_type" class="w-full bg-slate-900 border border-slate-700 rounded p-2 text-xs text-slate-200" required>
                                    <option>Out for Delivery</option>
                                    <option>Delivered</option>
                                    <option>Attempted Delivery</option>
                                    <option>Delayed</option>
                                </select>
                                <textarea name="notes" placeholder="Notes" rows="2" class="w-full bg-slate-900 border border-slate-700 rounded p-2 text-xs text-slate-200"></textarea>
                                <button type="submit" class="w-full py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded border border-slate-600">Log Event</button>
                            </form>
                        </div>

                        <!-- Delivery Confirmation -->
                        <div class="p-4 bg-slate-900/50 border border-slate-700 rounded-xl space-y-3">
                            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wide flex items-center gap-1.5"><i class="fa-solid fa-flag-checkered"></i> Delivery Confirmation</span>
                            <form action="backend/tracking.php" method="POST" class="space-y-2">
                                <input type="hidden" name="action" value="confirm_delivery">
                                <select name="trip_id" class="w-full bg-slate-900 border border-slate-700 rounded p-2 text-xs text-slate-200" required>
                                    <option value="">Select trip to complete</option>
                                    <?php foreach($active_trips as $trip): ?>
                                        <option value="<?= $trip['id'] ?>"><?= htmlspecialchars($trip['trip_number']) ?> (<?= htmlspecialchars($trip['destination']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Optional Document Upload Integrated -->
                                <label class="block text-[10px] text-slate-400 font-medium">Upload Signed Manifest Bill of Lading (Optional)</label>
                                <input type="file" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-300 hover:file:bg-slate-750 cursor-pointer">
                                <button type="submit" class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded shadow shadow-emerald-600/10">Confirm Delivery</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Status Reference Panel -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 h-fit space-y-4 hidden sm:block">
                    <h4 class="font-bold text-white text-sm">Real-time Deployment Snapshot</h4>
                    <div class="p-4 bg-slate-900 border border-amber-500/20 rounded-lg space-y-2">
                        <div class="flex justify-between items-center text-xs"><span class="text-slate-400">Active Run:</span> <span class="font-mono font-bold text-slate-200">TRIP-892</span></div>
                        <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden"><div class="bg-amber-500 h-full" style="width: 65%"></div></div>
                        <p class="text-[10px] text-slate-400 italic">Passing checkpoint 4 out of 6</p>
                    </div>
                </div>
            </section>

            <!-- SECTION 6: FUEL CONSUMPTION LOGS -->
            <section id="view-fuel" class="grid grid-cols-1 lg:grid-cols-3 gap-6 dashboard-view hidden">
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4 shadow-xl h-fit">
                    <h3 class="font-bold text-white text-base border-b border-slate-700 pb-2">Record Fuel Receipt</h3>
                    <form action="backend/fuel.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Select Vehicle</label>
                            <select name="vehicle_id" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200" required>
                                <option value="" disabled selected>Choose a vehicle</option>
                                <?php foreach($vehicles as $vehicle): ?>
                                    <option value="<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plate_number'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Gallons Pumped</label>
                                <input type="number" name="gallons_pumped" step="0.01" min="0.01" max="5000" placeholder="45.2" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200" required>
                                <p class="text-[10px] text-slate-500 mt-1">&gt; 0 gal required</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Total Invoice Cost</label>
                                <input type="number" name="total_cost" step="0.01" min="0" max="1000000" placeholder="185.00" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200" required>
                                <p class="text-[10px] text-slate-500 mt-1">&ge; ₱0.00 required</p>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs py-2.5 rounded-lg">Commit Fuel Transaction</button>
                    </form>
                </div>
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 lg:col-span-2 space-y-3">
                    <h3 class="font-bold text-white text-base">Fuel Management Audit Trail</h3>
                    <div class="divide-y divide-slate-700 text-xs max-h-96 overflow-y-auto">
                        <?php if (empty($fuel_logs)): ?>
                            <div class="py-3 text-center text-slate-400">No fuel logs recorded yet.</div>
                        <?php else: ?>
                            <?php foreach ($fuel_logs as $log): ?>
                                <div class="py-3 flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-slate-200"><?= htmlspecialchars($log['brand'] . ' ' . $log['model']) ?> (<span class="font-mono"><?= htmlspecialchars($log['plate_number']) ?></span>)</p>
                                        <p class="text-slate-400 text-[11px]"><?= htmlspecialchars($log['gallons_pumped']) ?> Gallons • Logged on <?= htmlspecialchars(date('M d, Y H:i', strtotime($log['log_date']))) ?></p>
                                    </div>
                                    <span class="text-slate-400 font-mono self-center">₱<?= htmlspecialchars(number_format($log['total_cost'], 2)) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- SECTION 7: MAINTENANCE LOGS -->
<section id="view-maintenance" class="space-y-6 dashboard-view hidden">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-800 p-4 rounded-xl border border-slate-700">
        <h3 class="text-base font-bold text-white">Maintenance Records & Scheduling</h3>
        <button onclick="openMaintenanceModal()" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Schedule Maintenance
        </button>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Upcoming Maintenance & Cost Tracking -->
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-3">
                <h3 class="font-bold text-white text-base">Upcoming Scheduled Maintenance</h3>
                <div class="divide-y divide-slate-700 text-xs max-h-48 overflow-y-auto">
                    <?php 
                        $upcoming = array_filter($maintenance_logs, fn($log) => $log['status'] === 'Scheduled' && strtotime($log['scheduled_date']) >= time());
                        if (empty($upcoming)): 
                    ?>
                        <div class="py-3 text-center text-slate-400">No upcoming maintenance scheduled.</div>
                    <?php else: ?>
                        <?php foreach ($upcoming as $log): 
                            $scheduledDate = new DateTime($log['scheduled_date']);
                            $now = new DateTime();
                            // To ensure we only compare dates, reset time part
                            $now->setTime(0, 0, 0);
                            $scheduledDate->setTime(0, 0, 0);

                            $diff = $now->diff($scheduledDate);
                            $days = $diff->days;
                            
                            $daysText = "In {$days} day(s)";
                            if ($days === 0) {
                                $daysText = "Today";
                            } elseif ($days === 1) {
                                $daysText = "Tomorrow";
                            }
                        ?>
                        <div class="py-3 flex justify-between items-center">
                            <div>
                                <p class="font-bold text-slate-200"><?= htmlspecialchars($log['brand'] . ' ' . $log['model']) ?>: <span class="font-normal"><?= htmlspecialchars($log['service_type']) ?></span></p>
                                <p class="text-slate-400 text-[11px]">Scheduled for: <?= htmlspecialchars(date('M d, Y', strtotime($log['scheduled_date']))) ?></p>
                            </div>
                            <span class="text-amber-400 font-semibold"><?= $daysText ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Records -->
    <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-xl">
        <div class="p-5 bg-slate-850 border-b border-slate-700">
            <h3 class="font-bold text-white text-base flex items-center gap-2"><i class="fa-solid fa-book"></i> Maintenance Records</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3">Vehicle</th>
                        <th class="px-6 py-3">Service Details</th>
                        <th class="px-6 py-3">Scheduled Date</th>
                        <th class="px-6 py-3">Completion Date</th>
                        <th class="px-6 py-3">Cost</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700 text-slate-300">
                    <?php if (empty($maintenance_logs)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-slate-400">No maintenance records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($maintenance_logs as $log): ?>
                            <?php
                                $logJson = htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8');
                                $status = htmlspecialchars($log['status']);
                                $statusColor = 'slate';
                                if ($status === 'Scheduled') $statusColor = 'amber';
                                else if ($status === 'In Progress') $statusColor = 'blue';
                                else if ($status === 'Completed') $statusColor = 'emerald';
                                else if ($status === 'Canceled') $statusColor = 'rose';
                            ?>
                            <tr>
                                <td class="px-6 py-4 font-semibold"><?= htmlspecialchars($log['brand'] . ' ' . $log['model']) ?></td>
                                <td class="px-6 py-4" title="<?= htmlspecialchars($log['details']) ?>"><?= htmlspecialchars($log['service_type']) ?></td>
                                <td class="px-6 py-4"><?= $log['scheduled_date'] ? htmlspecialchars(date('Y-m-d', strtotime($log['scheduled_date']))) : 'N/A' ?></td>
                                <td class="px-6 py-4"><?= $log['completion_date'] ? htmlspecialchars(date('Y-m-d', strtotime($log['completion_date']))) : 'N/A' ?></td>
                                <td class="px-6 py-4 font-mono text-red-400"><?= $log['cost'] ? '₱' . htmlspecialchars(number_format($log['cost'], 2)) : 'N/A' ?></td>
                                <td class="px-6 py-4"><span class="px-2 py-0.5 bg-<?= $statusColor ?>-500/10 text-<?= $statusColor ?>-400 rounded-full text-xs font-medium border border-<?= $statusColor ?>-500/20 whitespace-nowrap"><?= $status ?></span></td>
                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                    <button onclick='openMaintenanceModal(<?= $logJson ?>)' class="text-slate-400 hover:text-white" title="Edit"><i class="fa-solid fa-marker"></i></button>
                                    <a href="backend/maintenance.php?action=delete&id=<?= $log['id'] ?>" onclick="return confirm('Are you sure you want to delete this maintenance log?')" class="text-red-400 hover:text-red-300" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

            <!-- SECTION 8: TRIP HISTORY -->
            <section id="view-history" class="space-y-6 dashboard-view hidden">
                <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-xl">
                    <div class="p-5 bg-slate-850 border-b border-slate-700"><h3 class="font-bold text-white text-base">Archived Run Historical Data Logs</h3></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-900 text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700">
                                <tr><th class="px-6 py-3">Trip ID Code</th><th class="px-6 py-3">Assigned Asset</th><th class="px-6 py-3">Route Track</th><th class="px-6 py-3">Closing State</th><th class="px-6 py-3 text-right">Manifest Docs</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700 text-slate-300">
                                <?php 
                                    $historicalTrips = array_filter($trips, function($trip) {
                                        return in_array($trip['status'], ['Completed', 'Canceled']);
                                    });
                                ?>
                                <?php if (empty($historicalTrips)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-slate-400">No historical trips found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($historicalTrips as $trip): ?>
                                        <?php
                                            $status = htmlspecialchars($trip['status']);
                                            $statusColor = 'slate';
                                            $statusText = 'Unknown';
                                            if ($status === 'Completed') {
                                                $statusColor = 'emerald';
                                                $statusText = 'Completed Successful';
                                            } else if ($status === 'Canceled') {
                                                $statusColor = 'rose';
                                                $statusText = 'Canceled';
                                            }
                                        ?>
                                        <tr>
                                            <td class="px-6 py-4 font-mono font-bold text-white text-xs"><?= htmlspecialchars($trip['trip_number']) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($trip['vehicle_plate'] ?? 'N/A') ?> (<?= htmlspecialchars($trip['driver_name'] ?? 'N/A') ?>)</td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($trip['origin']) ?> ➔ <?= htmlspecialchars($trip['destination']) ?></td>
                                            <td class="px-6 py-4"><span class="px-2 py-0.5 bg-<?= $statusColor ?>-500/10 text-<?= $statusColor ?>-400 border border-<?= $statusColor ?>-500/20 text-xs font-semibold rounded"><?= $statusText ?></span></td>
                                            <td class="px-6 py-4 text-right"><a href="#" class="text-blue-400 hover:underline text-xs"><i class="fa-solid fa-file-pdf mr-1"></i> View BOL.pdf</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- ADD/EDIT VEHICLE MODAL -->
    <div id="vehicle-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-2xl shadow-2xl">
            <div class="p-5 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                <h3 id="vehicle-modal-title" class="font-bold text-white text-base">Add New Vehicle</h3>
                <button onclick="closeModal('vehicle-modal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="backend/vehicles.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" id="vehicle-action" value="add">
                <input type="hidden" name="id" id="vehicle-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Plate Number</label>
                        <input type="text" name="plate_number" id="plate_number" placeholder="e.g., TX-12A-345" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Vehicle Type</label>
                        <input type="text" name="vehicle_type" id="vehicle_type" placeholder="e.g., Heavy Truck" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Brand</label>
                        <input type="text" name="brand" id="brand" placeholder="e.g., Freightliner" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Model</label>
                        <input type="text" name="model" id="model" placeholder="e.g., Cascadia" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Capacity</label>
                        <input type="text" name="capacity" id="capacity" placeholder="e.g., 15 Tons" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Mileage</label>
                        <input type="number" name="mileage" id="mileage" min="0" max="2500000" placeholder="e.g., 142390" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                        <p class="text-[10px] text-slate-500 mt-1">&ge; 0 mi required</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Status</label>
                        <select name="status" id="status" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                            <option value="Available">Available</option>
                            <option value="In Use">In Use</option>
                            <option value="Under Maintenance">Under Maintenance</option>
                            <option value="Retired">Retired</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Registration Date</label>
                        <input type="date" name="registration_date" id="registration_date" min="1980-01-01" max="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                        <p class="text-[10px] text-slate-500 mt-1">1980 to current date</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Insurance Expiry Date</label>
                        <input type="date" name="insurance_expiry" id="insurance_expiry" min="<?= date('Y-01-01', strtotime('-1 year')) ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm">
                        <p class="text-[10px] text-slate-500 mt-1">Valid current / future expiry</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('vehicle-modal')" class="bg-slate-900 border border-slate-700 text-slate-300 font-semibold text-xs px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2 rounded-lg shadow-lg">Save Vehicle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD/EDIT DRIVER MODAL -->
    <div id="driver-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-2xl shadow-2xl">
            <div class="p-5 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                <h3 id="driver-modal-title" class="font-bold text-white text-base">Onboard New Driver</h3>
                <button onclick="closeModal('driver-modal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="backend/drivers.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" id="driver-action" value="add">
                <input type="hidden" name="id" id="driver-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Full Name</label>
                        <input type="text" name="name" id="driver-name" placeholder="e.g., John Doe" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Contact Number</label>
                        <input type="text" name="contact" id="driver-contact" placeholder="e.g., +1 (555) 123-4567" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">License No.</label>
                        <input type="text" name="license_no" id="driver-license-no" placeholder="e.g., DL-9928A-XYZ" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">License Expiry Date</label>
                        <input type="date" name="license_expiry" id="driver-license-expiry" min="<?= date('Y-01-01', strtotime('-1 year')) ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                        <p class="text-[10px] text-slate-500 mt-1">Valid current / future license</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Address</label>
                    <input type="text" name="address" id="driver-address" placeholder="e.g., 123 Logistics Way, Houston, TX" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Employment Status</label>
                        <select name="employment_status" id="driver-employment-status" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                            <option value="Full-Time">Full-Time</option><option value="Part-Time">Part-Time</option><option value="Contractor">Contractor</option><option value="Seasonal">Seasonal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Date Hired</label>
                        <input type="date" name="date_hired" id="driver-date-hired" min="1980-01-01" max="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                        <p class="text-[10px] text-slate-500 mt-1">1980 to today</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('driver-modal')" class="bg-slate-900 border border-slate-700 text-slate-300 font-semibold text-xs px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2 rounded-lg shadow-lg">Save Driver</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD/EDIT TRIP MODAL -->
    <div id="trip-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-4xl shadow-2xl">
            <div class="p-5 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                <h3 id="trip-modal-title" class="font-bold text-white text-base">Create New Trip</h3>
                <button onclick="closeModal('trip-modal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="backend/trips.php" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto" onsubmit="return validateTripForm(this)">
                <input type="hidden" name="action" id="trip-action" value="add">
                <input type="hidden" name="id" id="trip-id">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Trip Number</label>
                        <input type="text" name="trip_number" id="trip_number" placeholder="e.g., T-<?= date('Ymd-His') ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                     <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Driver</label>
                        <select name="driver_id" id="trip-driver-id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm">
                            <option value="">Assign Driver</option>
                            <?php foreach($drivers as $driver): ?>
                                <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Vehicle</label>
                        <select name="vehicle_id" id="trip-vehicle-id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm">
                            <option value="">Assign Vehicle</option>
                            <?php foreach($vehicles as $vehicle): ?>
                                <option value="<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plate_number'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Origin</label>
                        <input type="text" name="origin" id="trip-origin" placeholder="e.g., Austin Warehouse" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Destination</label>
                        <input type="text" name="destination" id="trip-destination" placeholder="e.g., Dallas Hub" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase">Departure Schedule</label>
                            <span class="text-[10px] text-blue-400 font-mono">Policy: -30d to +1yr</span>
                        </div>
                        <input type="datetime-local" name="departure_datetime" id="trip-departure" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200" onchange="syncTripArrivalMin()">
                        <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-clock mr-1"></i> Earliest: 30 days retroactive | Latest: +1 year ahead</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase">Arrival Schedule</label>
                            <span class="text-[10px] text-emerald-400 font-mono">After Departure</span>
                        </div>
                        <input type="datetime-local" name="arrival_datetime" id="trip-arrival" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200">
                        <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-flag-checkered mr-1"></i> Must be &ge; Departure (Max 14 days leg)</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Cargo Details</label>
                    <textarea name="cargo" id="trip-cargo" rows="2" placeholder="e.g., 2 pallets of electronics, 500 lbs" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase">Distance (mi)</label>
                            <span class="text-[10px] text-amber-400 font-mono">Min 0.0</span>
                        </div>
                        <input type="number" step="0.1" min="0" max="25000" name="distance" id="trip-distance" placeholder="0.0" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm">
                        <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-ban mr-1 text-rose-400"></i> No negative distance allowed</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase">Fuel Used (gal)</label>
                            <span class="text-[10px] text-amber-400 font-mono">Min 0.0</span>
                        </div>
                        <input type="number" step="0.1" min="0" max="5000" name="fuel_used" id="trip-fuel-used" placeholder="0.0" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm">
                        <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-ban mr-1 text-rose-400"></i> Non-negative consumption (&ge; 0)</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Status</label>
                        <select name="status" id="trip-status" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                            <option>Pending</option>
                            <option>In Progress</option>
                            <option>Completed</option>
                            <option>Canceled</option>
                        </select>
                    </div>
                </div>

                <div><label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Remarks</label><textarea name="remarks" id="trip-remarks" rows="2" placeholder="Add any relevant notes..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm"></textarea></div>

                <div class="pt-4 border-t border-slate-700 flex justify-end gap-3"><button type="button" onclick="closeModal('trip-modal')" class="bg-slate-900 border border-slate-700 text-slate-300 font-semibold text-xs px-4 py-2 rounded-lg">Cancel</button><button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2 rounded-lg shadow-lg">Save Trip</button></div>
            </form>
        </div>
    </div>

    <!-- ADD/EDIT MAINTENANCE MODAL -->
    <div id="maintenance-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-2xl shadow-2xl">
            <div class="p-5 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                <h3 id="maintenance-modal-title" class="font-bold text-white text-base">Schedule Maintenance</h3>
                <button onclick="closeModal('maintenance-modal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="backend/maintenance.php" method="POST" class="p-6 space-y-4" onsubmit="return validateMaintenanceForm(this)">
                <input type="hidden" name="action" id="maintenance-action" value="add">
                <input type="hidden" name="id" id="maintenance-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Vehicle</label>
                        <select name="vehicle_id" id="maintenance-vehicle-id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                            <option value="">Select a vehicle</option>
                            <?php foreach($vehicles as $vehicle): ?>
                                <option value="<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plate_number'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Service Type</label>
                        <input type="text" name="service_type" id="maintenance-service-type" placeholder="e.g., Oil Change" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase">Scheduled Date</label>
                            <span class="text-[10px] text-amber-400 font-mono">Current Window</span>
                        </div>
                        <input type="date" name="scheduled_date" id="maintenance-scheduled-date" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required onchange="syncMaintenanceCompMin()">
                        <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-clock mr-1"></i> -60 days to +1 year (rejects 2012, 2023)</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase">Completion Date</label>
                            <span class="text-[10px] text-emerald-400 font-mono">&ge; Scheduled</span>
                        </div>
                        <input type="date" name="completion_date" id="maintenance-completion-date" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm">
                        <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-check mr-1"></i> On or after Scheduled Date</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase">Cost (₱)</label>
                            <span class="text-[10px] text-amber-400 font-mono">Min ₱0.00</span>
                        </div>
                        <input type="number" step="0.01" min="0" max="1000000" name="cost" id="maintenance-cost" placeholder="0.00" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm">
                        <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-ban mr-1 text-rose-400"></i> Non-negative cost (&ge; ₱0.00)</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Status</label>
                        <select name="status" id="maintenance-status" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm" required>
                            <option>Scheduled</option>
                            <option>In Progress</option>
                            <option>Completed</option>
                            <option>Canceled</option>
                        </select>
                    </div>
                </div>

                <div><label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Details / Notes</label><textarea name="details" id="maintenance-details" rows="2" placeholder="e.g., Check engine light is on." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm"></textarea></div>

                <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('maintenance-modal')" class="bg-slate-900 border border-slate-700 text-slate-300 font-semibold text-xs px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs px-4 py-2 rounded-lg shadow-lg">Save Maintenance Log</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCHEDULING RULES & INPUT VALIDATION POLICY MODAL -->
    <div id="scheduling-policy-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-3xl shadow-2xl flex flex-col max-h-[90vh]">
            <div class="p-5 bg-slate-850 border-b border-slate-700 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center">
                        <i class="fa-solid fa-scale-balanced text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-base">Fleet Scheduling & Input Validation Policy</h3>
                        <p class="text-xs text-slate-400">Rules governing schedule timeliness, distance integrity, and field boundaries.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('scheduling-policy-modal')" class="text-slate-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-5 overflow-y-auto text-xs text-slate-300">
                <!-- How Early Rule -->
                <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-emerald-400 font-bold text-sm">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>1. "How Early" A Schedule Can Be (Earliest Boundary)</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-slate-300 pl-1">
                        <li><strong>Retroactive Logging Window:</strong> Maximum <strong>30 days in the past</strong> for logging completed trips.</li>
                        <li><strong>Historical Years Rejection:</strong> System strictly rejects obsolete years (e.g. <strong>2012, 2023</strong>) to protect operational MTD records.</li>
                        <li><strong>Future Dispatches:</strong> New planned dispatches require at least <strong>30 minutes advance lead time</strong> prior to scheduled departure.</li>
                    </ul>
                </div>

                <!-- How Late Rule -->
                <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-amber-400 font-bold text-sm">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>2. "How Late" A Schedule Can Be (Latest Boundary)</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-slate-300 pl-1">
                        <li><strong>Maximum Planning Horizon:</strong> At most <strong>365 days (1 year) in advance</strong> from the current date. Schedules beyond 1 year are rejected due to credential and insurance uncertainty.</li>
                        <li><strong>Daily Operating Hours:</strong> Standard fleet dispatch commences from <strong>05:00 AM</strong> (early roll-out) until <strong>22:00 (10:00 PM)</strong> late dispatch cut-off.</li>
                    </ul>
                </div>

                <!-- Sequence & Duration -->
                <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-purple-400 font-bold text-sm">
                        <i class="fa-solid fa-route"></i>
                        <span>3. Schedule Sequence & Leg Duration</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-slate-300 pl-1">
                        <li><strong>Chronology:</strong> Arrival datetime must be <strong>greater than or equal to</strong> Departure datetime.</li>
                        <li><strong>Maximum Transit Duration:</strong> Single continuous run leg cannot exceed <strong>14 consecutive days</strong>.</li>
                    </ul>
                </div>

                <!-- Distance & Metrics -->
                <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-blue-400 font-bold text-sm">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>4. Distance & Numeric Integrity Rules</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-slate-300 pl-1">
                        <li><strong>Distance:</strong> Non-negative (&ge; 0.0 mi). Completed trips require positive distance. Negative values rejected immediately.</li>
                        <li><strong>Fuel Used:</strong> Non-negative (&ge; 0.0 gal). Maximum cap 5,000 gal.</li>
                        <li><strong>Fuel Gallons Pumped:</strong> Strictly greater than zero (&gt; 0 gal).</li>
                        <li><strong>Costs & Mileage:</strong> Vehicle mileage, fuel invoices, and maintenance costs must all be &ge; 0.</li>
                    </ul>
                </div>
            </div>

            <div class="p-4 bg-slate-850 border-t border-slate-700 flex justify-end">
                <button type="button" onclick="closeModal('scheduling-policy-modal')" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-5 py-2.5 rounded-lg shadow-lg">
                    I Understand
                </button>
            </div>
        </div>
    </div>

    <!-- NAVIGATION SYSTEM ROUTING INTERACTION SCRIPT -->
    <script>
        const headingsMeta = {
            dashboard: { title: "Dashboard Overview", subtitle: "Holistic operations status breakdown and assets tracking summary." },
            vehicles: { title: "Manage Vehicles Inventory", subtitle: "Register assets, view plate records, and inspect mechanical assignment eligibility states." },
            drivers: { title: "Manage Driver Roster", subtitle: "Onboard operational personnel, regulate commercial vehicle compliance credentials." },
            trips: { title: "Schedules & Assignments", subtitle: "Map out planned commercial distribution runs and dispatch route parameters." },
            tracking: { title: "Active Deliveries & Actions", subtitle: "Real-time verification center for recording drops and concluding active routes." },
            fuel: { title: "Fuel Consumption Logging", subtitle: "Track fueling expenditure parameters to maintain financial clarity." },
            maintenance: { title: "Maintenance Logs & Servicing", subtitle: "Document mechanics workshop operations to avoid infrastructure breakdown variables." },
            history: { title: "Trip History Archives", subtitle: "Immutable chronological log index containing historic logistics run files data." }
        };

        function switchView(viewKey) {
            // Hide all screen contents
            document.querySelectorAll('.dashboard-view').forEach(view => view.classList.add('hidden'));
            // Display active view target
            document.getElementById(`view-${viewKey}`).classList.remove('hidden');

            // Reset navigation menu styles
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('text-slate-400', 'hover:bg-slate-700/50', 'hover:text-slate-200');
            });
            // Focus clicked selection highlight
            const targetBtn = document.getElementById(`nav-${viewKey}`);
            targetBtn.classList.remove('text-slate-400', 'hover:bg-slate-700/50', 'hover:text-slate-200');
            targetBtn.classList.add('bg-blue-600', 'text-white');

            // Swap header tracking labels
            document.getElementById('page-title').innerText = headingsMeta[viewKey].title;
            document.getElementById('page-subtitle').innerText = headingsMeta[viewKey].subtitle;

            window.location.hash = `view-${viewKey}`;

            if (window.innerWidth < 768) { toggleSidebar(false); }
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

        function openVehicleModal(vehicle = null) {
            const modal = document.getElementById('vehicle-modal');
            const title = document.getElementById('vehicle-modal-title');
            const form = modal.querySelector('form');
            form.reset();

            if (vehicle) {
                title.innerText = 'Edit Vehicle';
                document.getElementById('vehicle-action').value = 'edit';
                document.getElementById('vehicle-id').value = vehicle.id;
                document.getElementById('plate_number').value = vehicle.plate_number;
                document.getElementById('vehicle_type').value = vehicle.vehicle_type;
                document.getElementById('brand').value = vehicle.brand;
                document.getElementById('model').value = vehicle.model;
                document.getElementById('capacity').value = vehicle.capacity;
                document.getElementById('mileage').value = vehicle.mileage;
                document.getElementById('status').value = vehicle.status;
                document.getElementById('registration_date').value = vehicle.registration_date ? vehicle.registration_date.split(' ')[0] : '';
                document.getElementById('insurance_expiry').value = vehicle.insurance_expiry ? vehicle.insurance_expiry.split(' ')[0] : '';
            } else {
                title.innerText = 'Add New Vehicle';
                document.getElementById('vehicle-action').value = 'add';
                document.getElementById('vehicle-id').value = '';
            }
            modal.classList.remove('hidden');
        }

        function openTripModal(trip = null) {
            const modal = document.getElementById('trip-modal');
            const title = document.getElementById('trip-modal-title');
            const form = modal.querySelector('form');
            form.reset();

            if (trip) {
                title.innerText = 'Edit Trip';
                document.getElementById('trip-action').value = 'edit';
                document.getElementById('trip-id').value = trip.id;
                document.getElementById('trip_number').value = trip.trip_number;
                document.getElementById('trip-driver-id').value = trip.driver_id;
                document.getElementById('trip-vehicle-id').value = trip.vehicle_id;
                document.getElementById('trip-origin').value = trip.origin;
                document.getElementById('trip-destination').value = trip.destination;
                document.getElementById('trip-departure').value = trip.departure_datetime ? trip.departure_datetime.replace(' ', 'T') : '';
                document.getElementById('trip-arrival').value = trip.arrival_datetime ? trip.arrival_datetime.replace(' ', 'T') : '';
                document.getElementById('trip-cargo').value = trip.cargo;
                document.getElementById('trip-distance').value = trip.distance;
                document.getElementById('trip-fuel-used').value = trip.fuel_used;
                document.getElementById('trip-status').value = trip.status;
                document.getElementById('trip-remarks').value = trip.remarks;
            } else {
                title.innerText = 'Create New Trip';
                document.getElementById('trip-action').value = 'add';
                document.getElementById('trip-id').value = '';
                document.getElementById('trip_number').value = `T-${new Date().toISOString().slice(0, 19).replace(/[-:T]/g, '').substring(0,13)}`;
            }
            modal.classList.remove('hidden');
        }

        function openDriverModal(driver = null) {
            const modal = document.getElementById('driver-modal');
            const title = document.getElementById('driver-modal-title');
            const form = modal.querySelector('form');
            form.reset();

            if (driver) {
                title.innerText = 'Edit Driver Information';
                document.getElementById('driver-action').value = 'edit';
                document.getElementById('driver-id').value = driver.id;
                document.getElementById('driver-name').value = driver.name;
                document.getElementById('driver-license-no').value = driver.license_no;
                document.getElementById('driver-license-expiry').value = driver.license_expiry ? driver.license_expiry.split(' ')[0] : '';
                document.getElementById('driver-contact').value = driver.contact;
                document.getElementById('driver-address').value = driver.address;
                document.getElementById('driver-employment-status').value = driver.employment_status;
                document.getElementById('driver-date-hired').value = driver.date_hired ? driver.date_hired.split(' ')[0] : '';
            } else {
                title.innerText = 'Onboard New Driver';
                document.getElementById('driver-action').value = 'add';
                document.getElementById('driver-id').value = '';
            }
            modal.classList.remove('hidden');
        }

        function openMaintenanceModal(log = null) {
            const modal = document.getElementById('maintenance-modal');
            const title = document.getElementById('maintenance-modal-title');
            const form = modal.querySelector('form');
            form.reset();

            if (log) {
                title.innerText = 'Edit Maintenance Log';
                document.getElementById('maintenance-action').value = 'edit';
                document.getElementById('maintenance-id').value = log.id;
                document.getElementById('maintenance-vehicle-id').value = log.vehicle_id;
                document.getElementById('maintenance-service-type').value = log.service_type;
                document.getElementById('maintenance-scheduled-date').value = log.scheduled_date ? log.scheduled_date.split(' ')[0] : '';
                document.getElementById('maintenance-completion-date').value = log.completion_date ? log.completion_date.split(' ')[0] : '';
                document.getElementById('maintenance-cost').value = log.cost;
                document.getElementById('maintenance-status').value = log.status;
                document.getElementById('maintenance-details').value = log.details;
            } else {
                title.innerText = 'Schedule New Maintenance';
                document.getElementById('maintenance-action').value = 'add';
                document.getElementById('maintenance-id').value = '';
            }
            modal.classList.remove('hidden');
        }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
        function openSchedulingPolicyModal() { document.getElementById('scheduling-policy-modal').classList.remove('hidden'); }

        function formatDateTimeLocal(date) {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        }

        function formatDateOnly(date) {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        }

        function syncTripArrivalMin() {
            const depInput = document.getElementById('trip-departure');
            const arrInput = document.getElementById('trip-arrival');
            if (depInput && arrInput && depInput.value) {
                arrInput.min = depInput.value;
                if (arrInput.value && arrInput.value < depInput.value) {
                    arrInput.value = depInput.value;
                }
            }
        }

        function syncMaintenanceCompMin() {
            const schInput = document.getElementById('maintenance-scheduled-date');
            const compInput = document.getElementById('maintenance-completion-date');
            if (schInput && compInput && schInput.value) {
                compInput.min = schInput.value;
                if (compInput.value && compInput.value < schInput.value) {
                    compInput.value = schInput.value;
                }
            }
        }

        function validateTripForm(form) {
            const distanceInput = form.querySelector('[name="distance"]');
            const fuelInput = form.querySelector('[name="fuel_used"]');
            const depInput = form.querySelector('[name="departure_datetime"]');
            const arrInput = form.querySelector('[name="arrival_datetime"]');

            const distance = distanceInput ? distanceInput.value.trim() : '';
            const fuelUsed = fuelInput ? fuelInput.value.trim() : '';
            const depVal = depInput ? depInput.value : '';
            const arrVal = arrInput ? arrInput.value : '';

            // 1. Distance validation: cannot accept negative value
            if (distance !== '' && Number(distance) < 0) {
                alert('Validation Error: Trip distance cannot accept negative values. Please enter a positive mileage (0.0 or greater).');
                distanceInput.focus();
                return false;
            }

            // 2. Fuel validation: cannot accept negative value
            if (fuelUsed !== '' && Number(fuelUsed) < 0) {
                alert('Validation Error: Fuel used cannot accept negative values. Please enter a valid quantity.');
                fuelInput.focus();
                return false;
            }

            const now = new Date();
            // Earliest schedule rule: 30 days retroactive (rejects 2012, 2023, etc.)
            const minDate = new Date(now.getTime() - (30 * 24 * 60 * 60 * 1000));
            // Latest schedule rule: 365 days in advance
            const maxDate = new Date(now.getTime() + (365 * 24 * 60 * 60 * 1000));

            // 3. Departure schedule timing validation
            if (depVal) {
                const depDate = new Date(depVal);
                if (depDate < minDate) {
                    alert('Validation Error: Departure schedule date is too early! System rejects outdated historical years (e.g. 2012, 2023). Earliest allowed schedule is ' + formatDateOnly(minDate) + ' (max 30 days retroactive).');
                    depInput.focus();
                    return false;
                }
                if (depDate > maxDate) {
                    alert('Validation Error: Departure schedule date is too late! Maximum forward scheduling horizon is 1 year (Latest: ' + formatDateOnly(maxDate) + ').');
                    depInput.focus();
                    return false;
                }
            }

            // 4. Arrival schedule timing validation
            if (arrVal) {
                const arrDate = new Date(arrVal);
                if (arrDate < minDate) {
                    alert('Validation Error: Arrival schedule date is too early! System rejects outdated historical years (e.g. 2012, 2023).');
                    arrInput.focus();
                    return false;
                }
                if (arrDate > maxDate) {
                    alert('Validation Error: Arrival schedule date is too late! Maximum forward scheduling horizon is 1 year.');
                    arrInput.focus();
                    return false;
                }
                if (depVal) {
                    const depDate = new Date(depVal);
                    if (arrDate < depDate) {
                        alert('Validation Error: Invalid schedule sequence. Arrival datetime cannot be earlier than Departure datetime.');
                        arrInput.focus();
                        return false;
                    }
                    const diffDays = (arrDate - depDate) / (1000 * 60 * 60 * 24);
                    if (diffDays > 14) {
                        alert('Validation Error: Trip duration exceeds maximum allowable limit of 14 days.');
                        arrInput.focus();
                        return false;
                    }
                }
            }

            return true;
        }

        function validateMaintenanceForm(form) {
            const costInput = form.querySelector('[name="cost"]');
            const schInput = form.querySelector('[name="scheduled_date"]');
            const compInput = form.querySelector('[name="completion_date"]');

            const cost = costInput ? costInput.value.trim() : '';
            const schVal = schInput ? schInput.value : '';
            const compVal = compInput ? compInput.value : '';

            if (cost !== '' && Number(cost) < 0) {
                alert('Validation Error: Maintenance cost cannot accept negative values.');
                costInput.focus();
                return false;
            }

            const now = new Date();
            const minDate = new Date(now.getTime() - (60 * 24 * 60 * 60 * 1000));
            const maxDate = new Date(now.getTime() + (365 * 24 * 60 * 60 * 1000));

            if (schVal) {
                const schDate = new Date(schVal);
                if (schDate < minDate) {
                    alert('Validation Error: Scheduled maintenance date is too early! System rejects outdated historical years (e.g., 2012, 2023). Earliest allowed is ' + formatDateOnly(minDate) + '.');
                    schInput.focus();
                    return false;
                }
                if (schDate > maxDate) {
                    alert('Validation Error: Scheduled maintenance date is too late! Maximum advance scheduling is 1 year.');
                    schInput.focus();
                    return false;
                }
            }

            if (compVal && schVal) {
                const schDate = new Date(schVal);
                const compDate = new Date(compVal);
                if (compDate < schDate) {
                    alert('Validation Error: Completion date cannot be earlier than Scheduled date.');
                    compInput.focus();
                    return false;
                }
            }

            return true;
        }

        // On page load, configure dynamic input boundaries and check URL hash
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash;
            if (hash) {
                const viewKey = hash.substring(1).replace('view-', '');
                if (headingsMeta[viewKey]) {
                    switchView(viewKey);
                }
            }

            // Enforce dynamic policy bounds on form inputs
            const now = new Date();
            const minTripDate = new Date(now.getTime() - (30 * 24 * 60 * 60 * 1000));
            const maxTripDate = new Date(now.getTime() + (365 * 24 * 60 * 60 * 1000));
            
            const depInput = document.getElementById('trip-departure');
            const arrInput = document.getElementById('trip-arrival');
            if (depInput && arrInput) {
                depInput.min = formatDateTimeLocal(minTripDate);
                depInput.max = formatDateTimeLocal(maxTripDate);
                arrInput.min = formatDateTimeLocal(minTripDate);
                arrInput.max = formatDateTimeLocal(maxTripDate);
            }

            const minMaintDate = new Date(now.getTime() - (60 * 24 * 60 * 60 * 1000));
            const maxMaintDate = new Date(now.getTime() + (365 * 24 * 60 * 60 * 1000));
            const maintSch = document.getElementById('maintenance-scheduled-date');
            const maintComp = document.getElementById('maintenance-completion-date');
            if (maintSch && maintComp) {
                maintSch.min = formatDateOnly(minMaintDate);
                maintSch.max = formatDateOnly(maxMaintDate);
                maintComp.min = formatDateOnly(minMaintDate);
                maintComp.max = formatDateOnly(maxMaintDate);
            }

            // Real-time visual feedback for distance input
            const distField = document.getElementById('trip-distance');
            if (distField) {
                distField.addEventListener('input', function() {
                    if (this.value !== '' && Number(this.value) < 0) {
                        this.classList.add('border-red-500', 'text-red-400');
                    } else {
                        this.classList.remove('border-red-500', 'text-red-400');
                    }
                });
            }
        });
    </script>
</body>
</html>