<?php
// warehouse_head_dashboard_main.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['warehouse']); // Only warehouse role can access

// Ensure session exists
if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

// --- Resolve logged-in user and check if they're a head ---
$user_id = null;
$fullname = '';
$user_lvl = '';
$is_head = false;

$sessionUser = $_SESSION['noble_user'];

if (is_array($sessionUser)) {
    if (isset($sessionUser['id'])) {
        $user_id = (int) $sessionUser['id'];
    } elseif (isset($sessionUser['user_id'])) {
        $user_id = (int) $sessionUser['user_id'];
    }
    $fullname = $sessionUser['fullname'] ?? $sessionUser['name'] ?? '';
    $user_lvl = $sessionUser['lvl'] ?? $sessionUser['level'] ?? '';
    $is_head = isset($sessionUser['is_head']) && (int) $sessionUser['is_head'] === 1;
}

if (empty($user_id)) {
    if (!is_array($sessionUser)) {
        $lookupValue = $sessionUser;

        if (filter_var($lookupValue, FILTER_VALIDATE_EMAIL)) {
            $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE email = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("s", $lookupValue);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) {
                    $user_id = (int) $r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                    $is_head = (int) $r['is_head'] === 1;
                }
            }
        } elseif (ctype_digit((string) $lookupValue)) {
            $candidate = (int) $lookupValue;
            $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE id = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("i", $candidate);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) {
                    $user_id = (int) $r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                    $is_head = (int) $r['is_head'] === 1;
                }
            }
        }
    }
}

if (!$is_head) {
    header("Location: order_list.php");
    exit();
}

// --- Filters from GET ---
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$employee_filter = isset($_GET['employee']) ? (int) $_GET['employee'] : 0;
$show_replacements = isset($_GET['replacements']) ? (bool) $_GET['replacements'] : false;
$show_ready_for_schedule = isset($_GET['ready_schedule']) ? (bool) $_GET['ready_schedule'] : false;
$show_unassigned = isset($_GET['unassigned']) ? (bool) $_GET['unassigned'] : false;

// Get only warehouse staff by subrole
$warehouseEmployees = [];
$empSql = "SELECT id, fullname, is_head FROM nobleaccount WHERE lvl = 'warehouse' AND subrole = 'warehouse_staff' ORDER BY fullname ASC";
$empResult = $conn->query($empSql);
if ($empResult) {
    $warehouseEmployees = $empResult->fetch_all(MYSQLI_ASSOC);
}

// --- Build WHERE conditions ---
$whereParts = ["1=1", "o.status != 'pending'"];
$params = [];
$types = '';

if (!$is_head && !$show_unassigned) {
    $whereParts[] = "o.warehouse_employee_id = ?";
    $params[] = $user_id;
    $types .= 'i';
} else {
    if ($show_unassigned) {
        $whereParts[] = "(o.warehouse_employee_id IS NULL OR o.warehouse_employee_id = 0)";
        $whereParts[] = "o.status = 'Ongoing'";
    } elseif ($employee_filter > 0) {
        $whereParts[] = "o.warehouse_employee_id = ?";
        $params[] = $employee_filter;
        $types .= 'i';
    }
}

if ($status_filter !== '') {
    $whereParts[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search_query !== '') {
    $whereParts[] = "(o.customer_name LIKE ? OR o.email LIKE ? OR o.id LIKE ?)";
    $like = "%{$search_query}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($date_from !== '') {
    $whereParts[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to !== '') {
    $whereParts[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($show_replacements) {
    $whereParts[] = "EXISTS (SELECT 1 FROM replacement_requests rr WHERE rr.order_id = o.id AND rr.status = 'approved')";
}

if ($show_ready_for_schedule) {
    $whereParts[] = "NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id)";
    $whereParts[] = "o.status IN ('processing', 'Ready for Pickup', 'Out for Delivery')";
    $whereParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.tracking_status = 'In Warehouse') = (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id)";
    $whereParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereParts);

function bindParamsToStmt($stmt, $types, $params)
{
    if ($types === '' || empty($params))
        return;
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

// --- Orders query ---
$ordersSql = "
    SELECT
        o.id, o.customer_name, o.email, o.created_at, o.status, o.total,
        o.warehouse_employee_id,
        na.fullname as assigned_employee_name,
        COUNT(DISTINCT oi.id) as item_count,
        COUNT(DISTINCT CASE
            WHEN (oi.supplier_id IS NOT NULL AND oi.supplier_id > 0)
              OR (oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '')
            THEN oi.id
        END) as assigned_count,
        COUNT(DISTINCT poa.id) as po_attachment_count,
        COUNT(DISTINCT CASE WHEN rr.status = 'approved' THEN rr.id END) as approved_replacements_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN po_attachments poa ON o.id = poa.order_id
    LEFT JOIN replacement_requests rr ON o.id = rr.order_id
    LEFT JOIN nobleaccount na ON o.warehouse_employee_id = na.id
    $whereClause
    GROUP BY o.id, o.customer_name, o.email, o.created_at, o.status, o.total, o.warehouse_employee_id, na.fullname
    ORDER BY o.created_at DESC
";

$orders = [];
if ($stmt = $conn->prepare($ordersSql)) {
    if (!empty($params))
        bindParamsToStmt($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res)
        $orders = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// --- Statistics ---
$totalOrders = 0;
$unassignedCount = 0;
$statusCounts = [];
$replacementOrdersCount = 0;
$readyForScheduleCount = 0;

$statsResult = $conn->query("SELECT status, COUNT(*) as count FROM orders WHERE status != 'pending' GROUP BY status");
if ($statsResult) {
    foreach ($statsResult->fetch_all(MYSQLI_ASSOC) as $row) {
        $statusCounts[$row['status']] = (int) $row['count'];
        $totalOrders += (int) $row['count'];
    }
}

$r = $conn->query("SELECT COUNT(*) as count FROM orders WHERE (warehouse_employee_id IS NULL OR warehouse_employee_id = 0) AND status = 'Ongoing'");
if ($r)
    $unassignedCount = (int) $r->fetch_assoc()['count'];

$r = $conn->query("SELECT COUNT(DISTINCT o.id) as count FROM orders o WHERE EXISTS (SELECT 1 FROM replacement_requests rr WHERE rr.order_id = o.id AND rr.status = 'approved')");
if ($r)
    $replacementOrdersCount = (int) $r->fetch_assoc()['count'];

$r = $conn->query("
    SELECT COUNT(DISTINCT o.id) as count FROM orders o
    WHERE o.status IN ('processing', 'Ready for Pickup', 'Out for Delivery')
    AND NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id)
    AND (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.tracking_status = 'In Warehouse')
     = (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id)
    AND (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0
");
if ($r)
    $readyForScheduleCount = (int) $r->fetch_assoc()['count'];

// Employee workload statistics
$employeeStats = [];
$empStatsResult = $conn->query("
    SELECT na.id, na.fullname,
        COUNT(o.id) as order_count,
        SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) as processing_count
    FROM nobleaccount na
    LEFT JOIN orders o ON na.id = o.warehouse_employee_id
    WHERE na.lvl = 'warehouse' AND na.subrole = 'warehouse_staff'
    GROUP BY na.id, na.fullname
    ORDER BY na.fullname ASC
");
if ($empStatsResult) {
    $employeeStats = $empStatsResult->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Warehouse Head Dashboard - All Orders</title>
    <style>
        .pulse-notification {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .5;
            }
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="bg-white text-white ">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div>
                        <p class="text-black mt-1">
                            Viewing: <strong>ALL WAREHOUSE ORDERS</strong>
                            <?php if ($employee_filter > 0): ?> - Filtered by employee
                            <?php elseif ($show_unassigned): ?> - Unassigned only
                            <?php endif; ?>
                        </p>
                        <p class="text-black mt-1">Complete overview of all warehouse orders and assignments</p>
                    </div>
                </div>
                <div class="text-right">
                    <a href="<?= BASE_URL; ?>/warehouseassignment"
                        class="bg-white text-black hover:bg-red-50 px-6 py-3 rounded-xl transition-all duration-200 inline-flex items-center space-x-3 shadow-lg hover:shadow-xl hover:scale-105 font-semibold  ">
                       <i class="fa-solid fa-user-gear"></i>
                        <span>Manage Assignments</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8 mt-2">

            <!-- Total Orders -->
            <div class="bg-white border border-gray-100 rounded-xl p-5 flex flex-col gap-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-700 uppercase tracking-wide">Total Orders</span>
                    <span class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1" />
                            <circle cx="20" cy="21" r="1" />
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                        </svg>
                    </span>
                </div>
                <!-- id for AJAX patch -->
                <div class="text-3xl font-semibold text-gray-800" id="stat-total-orders"><?php echo $totalOrders; ?>
                </div>
                <div class="text-xs text-gray-400">All time</div>
            </div>

            <!-- Unassigned Orders -->
            <div class="bg-white border border-gray-100 rounded-xl p-5 flex flex-col gap-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-700 uppercase tracking-wide">Unassigned</span>
                    <span class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                    </span>
                </div>
                <!-- id for AJAX patch -->
                <div class="text-3xl font-semibold text-red-600" id="stat-unassigned-count">
                    <?php echo $unassignedCount; ?></div>
                <?php if ($unassignedCount > 0): ?>
                    <a href="warehouse_head_assignment_A.php"
                        class="text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 px-3 py-1 rounded-full w-fit transition-colors">Assign
                        now →</a>
                <?php else: ?>
                    <div class="text-xs text-gray-400">All assigned</div>
                <?php endif; ?>
            </div>

            <!-- Replacement Requests -->
            <?php if ($replacementOrdersCount > 0): ?>
                <div
                    class="bg-white border border-gray-100 rounded-xl p-5 flex flex-col gap-3 shadow-sm relative overflow-hidden">
                    <span class="absolute top-3 right-3 w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-700 uppercase tracking-wide">Replacements</span>
                        <span class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10" />
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                            </svg>
                        </span>
                    </div>
                    <!-- id for AJAX patch -->
                    <div class="text-3xl font-semibold text-amber-600" id="stat-replacement-count">
                        <?php echo $replacementOrdersCount; ?></div>
                    <span class="text-xs text-gray-400">Needs attention</span>
                </div>
            <?php endif; ?>

            <!-- Ready for Schedule -->
            <?php if ($readyForScheduleCount > 0): ?>
                <div
                    class="bg-white border border-gray-100 rounded-xl p-5 flex flex-col gap-3 shadow-sm relative overflow-hidden">
                    <span class="absolute top-3 right-3 w-2 h-2 rounded-full bg-green-500 animate-ping"></span>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-700 uppercase tracking-wide">Ready to Schedule</span>
                        <span class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                                <polyline points="9 16 11 18 15 14" />
                            </svg>
                        </span>
                    </div>
                    <!-- id for AJAX patch -->
                    <div class="text-3xl font-semibold text-green-600" id="stat-ready-schedule-count">
                        <?php echo $readyForScheduleCount; ?></div>
                    <span class="text-xs text-gray-400">Queue ready</span>
                </div>
            <?php endif; ?>

            <!-- Warehouse Team -->
            <div class="bg-white border border-gray-100 rounded-xl p-5 flex flex-col gap-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-700 uppercase tracking-wide">Team Members</span>
                    <span class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-purple-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </span>
                </div>
                <!-- id for AJAX patch -->
                <div class="text-3xl font-semibold text-gray-800" id="stat-team-count">
                    <?php echo count($warehouseEmployees); ?></div>
                <span class="text-xs text-gray-400">Active staff</span>
            </div>

        </div>

        <!-- Employee Workload Overview -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-chart-bar text-primary-600 mr-2"></i>
                Employee Workload Distribution
            </h2>
            <!-- id for AJAX patch -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="employee-workload-grid">
                <?php foreach ($employeeStats as $empStat): ?>
                    <!-- id="emp-card-{id}" for AJAX patch -->
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                        id="emp-card-<?php echo $empStat['id']; ?>">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($empStat['fullname']); ?>
                            </h3>
                            <!-- class="emp-order-count" for AJAX patch -->
                            <span
                                class="text-2xl font-bold text-primary-600 emp-order-count"><?php echo $empStat['order_count']; ?></span>
                        </div>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div class="flex justify-between">
                                <span>Pending:</span>
                                <!-- class="emp-pending-count" for AJAX patch -->
                                <span
                                    class="font-medium text-yellow-600 emp-pending-count"><?php echo $empStat['pending_count']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Processing:</span>
                                <!-- class="emp-processing-count" for AJAX patch -->
                                <span
                                    class="font-medium text-blue-600 emp-processing-count"><?php echo $empStat['processing_count']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <!-- Filter Tabs -->
                <div class="flex flex-wrap gap-2 mb-4">

                    <!-- All Orders tab — id="tab-badge-all" for AJAX patch -->
                    <a href="?"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo ($status_filter === '' && !$show_replacements && !$show_unassigned && !$show_ready_for_schedule) ? 'bg-gray-200 text-black' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        All Orders (<span id="tab-badge-all"><?php echo $totalOrders; ?></span>)
                    </a>

                    <?php
                    $statusOrder = ['Ongoing', 'processing', 'Ready for Pickup', 'Out for Delivery', 'Delivered', 'completed', 'cancelled'];
                    $statusConfig = [
                        'Ongoing' => ['icon' => 'fa-tasks', 'color' => 'orange'],
                        'processing' => ['icon' => 'fa-cog', 'color' => 'blue'],
                        'Ready for Pickup' => ['icon' => 'fa-box', 'color' => 'indigo'],
                        'Out for Delivery' => ['icon' => 'fa-truck', 'color' => 'purple'],
                        'Delivered' => ['icon' => 'fa-check-circle', 'color' => 'green'],
                        'completed' => ['icon' => 'fa-check-double', 'color' => 'green'],
                        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red'],
                    ];

                    foreach ($statusOrder as $status):
                        if (!isset($statusCounts[$status]))
                            continue;
                        $count = $statusCounts[$status];
                        $config = $statusConfig[$status] ?? ['icon' => 'fa-circle', 'color' => 'gray'];
                        $isActive = ($status_filter === $status && !$show_replacements && !$show_unassigned && !$show_ready_for_schedule);
                        // Build safe id: spaces → dash
                        $tabId = 'tab-badge-' . str_replace(' ', '-', $status);
                        ?>
                        <a href="?status=<?php echo urlencode($status); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo $employee_filter > 0 ? '&employee=' . $employee_filter : ''; ?>"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center space-x-1 <?php echo $isActive ? 'bg-' . $config['color'] . '-600 text-white shadow-md' : 'bg-' . $config['color'] . '-100 text-' . $config['color'] . '-700 hover:bg-' . $config['color'] . '-200'; ?>">
                            <i class="fas <?php echo $config['icon']; ?>"></i>
                            <span><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                            <!-- id="tab-badge-{Status}" for AJAX patch -->
                            <span
                                class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $isActive ? 'bg-white/20' : 'bg-' . $config['color'] . '-200'; ?>"
                                id="<?php echo $tabId; ?>">
                                <?php echo (int) $count; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($readyForScheduleCount > 0): ?>
                        <a href="?ready_schedule=1"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 relative <?php echo $show_ready_for_schedule ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200 pulse-notification'; ?>">
                            <i class="fas fa-calendar-check mr-1"></i>
                            Ready to Schedule (<span
                                id="tab-badge-ready-schedule"><?php echo $readyForScheduleCount; ?></span>)
                            <?php if (!$show_ready_for_schedule): ?>
                                <span
                                    class="absolute -top-1 -right-1 h-3 w-3 bg-green-500 border-2 border-white rounded-full animate-ping"></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($unassignedCount > 0): ?>
                        <a href="?unassigned=1"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 relative <?php echo $show_unassigned ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200'; ?>">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Unassigned (<span id="tab-badge-unassigned"><?php echo $unassignedCount; ?></span>)
                            <?php if (!$show_unassigned): ?>
                                <span
                                    class="absolute -top-1 -right-1 h-3 w-3 bg-red-500 border-2 border-white rounded-full animate-ping"></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($replacementOrdersCount > 0): ?>
                        <a href="?replacements=1"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo $show_replacements ? 'bg-orange-600 text-white' : 'bg-orange-100 text-orange-700 hover:bg-orange-200 pulse-notification'; ?>">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Replacements (<span id="tab-badge-replacements"><?php echo $replacementOrdersCount; ?></span>)
                        </a>
                    <?php endif; ?>

                </div>

                <!-- Search and Employee Filter -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Order ID, Customer, Email..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee</label>
                        <select name="employee"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="0">All Staff Members</option>
                            <?php foreach ($warehouseEmployees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo ($employee_filter == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-md transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders List -->
        <!-- id="orders-list-container" — JS renders/patches orders here -->
        <div id="orders-list-container" class="space-y-4">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order):
                    $assignedItems = (int) $order['assigned_count'];
                    $item_count = (int) $order['item_count'];
                    $po_attachment_count = (int) $order['po_attachment_count'];
                    $approved_replacements_count = (int) $order['approved_replacements_count'];
                    $assignmentPercentage = $item_count > 0 ? round(($assignedItems / $item_count) * 100) : 0;
                    $hasPOFiles = $po_attachment_count > 0;
                    $hasReplacements = $approved_replacements_count > 0;
                    $isUnassigned = empty($order['warehouse_employee_id']);

                    $progressColor = ($assignmentPercentage === 100) ? 'bg-green-500' : (($assignmentPercentage > 0) ? 'bg-yellow-500' : 'bg-red-500');
                    $statusBadge = match ($order['status']) {
                        'pending' => 'bg-yellow-100 text-yellow-700',
                        'processing' => 'bg-blue-100 text-blue-700',
                        'completed' => 'bg-green-100 text-green-700',
                        default => 'bg-gray-100 text-gray-700',
                    };

                    $borderClasses = 'bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200';
                    if ($hasReplacements)
                        $borderClasses .= ' border-l-4 border-l-red-500';
                    if ($isUnassigned && $order['status'] === 'Ongoing')
                        $borderClasses .= ' border-l-4 border-l-orange-500';
                    ?>
                    <!-- data-order-id for AJAX patch -->
                    <div class="<?php echo $borderClasses; ?>" data-order-id="<?php echo $order['id']; ?>">
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-4">

                                <!-- Order Info -->
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="bg-primary-100 p-2 rounded-lg flex-shrink-0">
                                        <i class="fas fa-receipt text-primary-600 text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="text-lg font-bold text-gray-900">Order
                                                #<?php echo htmlspecialchars($order['id']); ?></h3>
                                            <?php if ($hasReplacements): ?>
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                    <i class="fas fa-sync-alt mr-1"></i><?php echo $approved_replacements_count; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($isUnassigned && $order['status'] === 'Ongoing'): ?>
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                                    <i class="fas fa-user-slash mr-1"></i>Unassigned
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-4 text-xs text-gray-600">
                                            <span class="truncate"><i
                                                    class="fas fa-user mr-1"></i><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                            <span><i class="fas fa-box mr-1"></i><?php echo $item_count; ?> items</span>
                                            <span class="hidden md:inline"><i
                                                    class="fas fa-calendar mr-1"></i><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Assignment Progress -->
                                <div class="flex-shrink-0 w-32">
                                    <div class="text-xs text-gray-600 mb-1">Assignment</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full <?php echo $progressColor; ?>"
                                            style="width:<?php echo $assignmentPercentage; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5"><?php echo $assignmentPercentage; ?>% Complete
                                    </div>
                                </div>

                                <!-- Employee -->
                                <div class="flex-shrink-0 w-36 hidden lg:block">
                                    <div class="text-xs text-gray-600 mb-1">Assigned To</div>
                                    <div
                                        class="text-sm font-medium <?php echo ($isUnassigned && $order['status'] === 'Ongoing') ? 'text-orange-600' : 'text-blue-600'; ?>">
                                        <?php echo ($isUnassigned && $order['status'] === 'Ongoing') ? 'Not Assigned' : htmlspecialchars($order['assigned_employee_name']); ?>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="flex-shrink-0 w-28 hidden md:block">
                                    <div class="text-xs text-gray-600 mb-1">Status</div>
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $statusBadge; ?>">
                                        <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                    </span>
                                </div>

                                <!-- Price -->
                                <div class="flex-shrink-0 text-right w-28">
                                    <div class="text-xs text-gray-600 mb-1">Total</div>
                                    <div class="text-lg font-bold text-primary-700">
                                        ₱<?php echo number_format((float) $order['total'], 2); ?></div>
                                </div>

                                <!-- Actions Dropdown -->
                                <div class="flex-shrink-0 relative">
                                    <button onclick="toggleActionsMenu(<?php echo $order['id']; ?>)"
                                        class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-ellipsis-v text-gray-600"></i>
                                    </button>
                                    <div id="actions-<?php echo $order['id']; ?>"
                                        class="hidden absolute right-0 bottom-full mb-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                        <div class="py-2">
                                            <?php if ($isUnassigned): ?>
                                                <a href="warehouse_head_assignment_A.php?order_id=<?php echo urlencode($order['id']); ?>"
                                                    class="block px-4 py-2 text-sm text-orange-700 hover:bg-orange-50 transition-colors">
                                                    <i class="fas fa-user-plus mr-2 w-4"></i>Assign Employee
                                                </a>
                                            <?php else: ?>
                                                <a href="warehouse_head_assignment_A.php?order_id=<?php echo urlencode($order['id']); ?>"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                                    <i class="fas fa-exchange-alt mr-2 w-4"></i>Reassign Employee
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($hasReplacements): ?>
                                                <a href="replacement_management.php?order_id=<?php echo urlencode($order['id']); ?>"
                                                    class="block px-4 py-2 text-sm text-red-700 hover:bg-red-50 transition-colors">
                                                    <i class="fas fa-sync-alt mr-2 w-4"></i>Handle Replacements
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!$hasPOFiles && $assignmentPercentage >= 100): ?>
                                                <div
                                                    class="px-4 py-2 text-xs font-medium text-yellow-700 bg-yellow-50 border-t border-gray-100">
                                                    <i class="fas fa-exclamation-circle mr-1"></i>Awaiting P.O. Attachment
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Empty State — id="orders-empty-state", hidden when orders exist -->
        <div id="orders-empty-state" <?php echo !empty($orders) ? 'style="display:none"' : ''; ?>
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <div class="text-gray-500">
                <i class="fas fa-inbox text-6xl mb-4"></i>
                <h3 class="text-lg font-medium mb-2">No Orders Found</h3>
                <p class="text-sm">
                    <?php if ($show_unassigned): ?>
                        There are no unassigned orders at the moment.
                    <?php elseif ($show_replacements): ?>
                        There are no orders with approved replacement requests.
                    <?php elseif ($show_ready_for_schedule): ?>
                        There are no orders ready for scheduling.
                    <?php elseif ($employee_filter > 0): ?>
                        This employee doesn't have any orders assigned yet.
                    <?php else: ?>
                        No orders match your current filters.
                    <?php endif; ?>
                </p>
                <a href="?" class="mt-4 inline-block text-primary-600 hover:text-primary-700 font-medium">
                    <i class="fas fa-redo mr-1"></i>Clear Filters
                </a>
            </div>
        </div>

    </div><!-- end max-w container -->

    <script>
        // Toggle actions dropdown menu
        function toggleActionsMenu(orderId) {
            const menu = document.getElementById(`actions-${orderId}`);
            const allMenus = document.querySelectorAll('[id^="actions-"]');
            allMenus.forEach(m => { if (m.id !== `actions-${orderId}`) m.classList.add('hidden'); });
            menu.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('button[onclick^="toggleActionsMenu"]') && !e.target.closest('[id^="actions-"]')) {
                document.querySelectorAll('[id^="actions-"]').forEach(menu => menu.classList.add('hidden'));
            }
        });


        (function () {
            'use strict';

            const ENDPOINT = '<?= BASE_URL ?>/warehousepolling';
            const POLL_INTERVAL_MS = 20_000;

            let previousHash = '';

            // ─── HELPERS ─────────────────────────────────────────────────────────────

            function formatDate(dateStr) {
                return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }

            function formatPeso(val) {
                return '₱' + parseFloat(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function showToast(message) {
                const existing = document.getElementById('wh-poll-toast');
                if (existing) existing.remove();

                const toast = document.createElement('div');
                toast.id = 'wh-poll-toast';
                toast.className = 'fixed bottom-6 right-6 z-[999] flex items-center gap-3 bg-gray-800 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0';
                toast.innerHTML = `<span style="color:#4ade80">●</span> ${message}`;
                document.body.appendChild(toast);

                setTimeout(() => toast.classList.replace('opacity-0', 'opacity-100'), 10);
                setTimeout(() => {
                    toast.classList.replace('opacity-100', 'opacity-0');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }

            // ─── RENDER ───────────────────────────────────────────────────────────────

            function statusBadgeClass(status) {
                const map = {
                    'pending': 'bg-yellow-100 text-yellow-700',
                    'processing': 'bg-blue-100 text-blue-700',
                    'completed': 'bg-green-100 text-green-700',
                    'Ongoing': 'bg-orange-100 text-orange-700',
                    'Ready for Pickup': 'bg-indigo-100 text-indigo-700',
                    'Out for Delivery': 'bg-purple-100 text-purple-700',
                    'Delivered': 'bg-green-100 text-green-700',
                    'cancelled': 'bg-red-100 text-red-700',
                };
                return map[status] || 'bg-gray-100 text-gray-700';
            }

            function progressColor(pct) {
                if (pct === 100) return 'bg-green-500';
                if (pct > 0) return 'bg-yellow-500';
                return 'bg-red-500';
            }

            function renderStats(stats) {
                const set = (id, val) => {
                    const el = document.getElementById(id);
                    if (el && el.textContent.trim() !== String(val)) el.textContent = val;
                };

                set('stat-total-orders', stats.totalOrders);
                set('stat-unassigned-count', stats.unassignedCount);
                set('stat-replacement-count', stats.replacementCount);
                set('stat-ready-schedule-count', stats.readyForScheduleCount);
                set('stat-team-count', stats.employeeStats.length);
                set('tab-badge-all', stats.totalOrders);
                set('tab-badge-unassigned', stats.unassignedCount);
                set('tab-badge-replacements', stats.replacementCount);
                set('tab-badge-ready-schedule', stats.readyForScheduleCount);

                Object.entries(stats.statusCounts).forEach(([status, count]) => {
                    const el = document.getElementById(`tab-badge-${status.replace(/\s+/g, '-')}`);
                    if (el && el.textContent.trim() !== String(count)) el.textContent = count;
                });
            }

            function renderEmployeeStats(employeeStats) {
                employeeStats.forEach(emp => {
                    const card = document.getElementById(`emp-card-${emp.id}`);
                    if (!card) return;

                    const setCard = (cls, val) => {
                        const el = card.querySelector(cls);
                        if (el && el.textContent.trim() !== String(val)) el.textContent = val;
                    };

                    setCard('.emp-order-count', emp.order_count);
                    setCard('.emp-pending-count', emp.pending_count);
                    setCard('.emp-processing-count', emp.processing_count);
                });
            }

            function renderOrders(orders) {
                const container = document.getElementById('orders-list-container');
                const emptyState = document.getElementById('orders-empty-state');
                if (!container) return;

                if (!orders || orders.length === 0) {
                    container.innerHTML = '';
                    if (emptyState) emptyState.style.display = '';
                    return;
                }
                if (emptyState) emptyState.style.display = 'none';

                const existingIds = new Set(
                    [...container.querySelectorAll('[data-order-id]')].map(el => el.dataset.orderId)
                );
                const incomingIds = new Set(orders.map(o => String(o.id)));

                // Tanggalin ang wala na sa result
                existingIds.forEach(id => {
                    if (!incomingIds.has(id)) {
                        const el = container.querySelector(`[data-order-id="${id}"]`);
                        if (el) {
                            el.style.transition = 'opacity .3s';
                            el.style.opacity = '0';
                            setTimeout(() => el.remove(), 300);
                        }
                    }
                });

                orders.forEach((order, index) => {
                    const id = String(order.id);
                    const assignedItems = parseInt(order.assigned_count) || 0;
                    const itemCount = parseInt(order.item_count) || 0;
                    const poAttachCount = parseInt(order.po_attachment_count) || 0;
                    const approvedRepl = parseInt(order.approved_replacements_count) || 0;
                    const pct = itemCount > 0 ? Math.round((assignedItems / itemCount) * 100) : 0;
                    const hasPO = poAttachCount > 0;
                    const hasReplacements = approvedRepl > 0;
                    const isUnassigned = !order.warehouse_employee_id || order.warehouse_employee_id == 0;

                    let card = container.querySelector(`[data-order-id="${id}"]`);

                    if (!card) {
                        card = document.createElement('div');
                        card.dataset.orderId = id;
                        card.style.opacity = '0';
                        card.style.transition = 'opacity .4s';
                        const allCards = [...container.querySelectorAll('[data-order-id]')];
                        if (allCards[index]) container.insertBefore(card, allCards[index]);
                        else container.appendChild(card);
                        setTimeout(() => { card.style.opacity = '1'; }, 50);
                    }

                    const borderClasses = ['bg-white', 'rounded-lg', 'shadow-sm', 'border', 'border-gray-200', 'hover:shadow-md', 'transition-all', 'duration-200'];
                    if (hasReplacements) borderClasses.push('border-l-4', 'border-l-red-500');
                    if (isUnassigned && order.status === 'Ongoing') borderClasses.push('border-l-4', 'border-l-orange-500');
                    card.className = borderClasses.join(' ');

                    let dropdownActions = '';
                    if (isUnassigned) {
                        dropdownActions += `<a href="<?= BASE_URL ?>/warehouseassignment?order_id=${encodeURIComponent(order.id)}" class="block px-4 py-2 text-sm text-orange-700 hover:bg-orange-50 transition-colors"><i class="fas fa-user-plus mr-2 w-4"></i>Assign Employee</a>`;
                    } else {
                        dropdownActions += `<a href="<?= BASE_URL ?>/warehouseassignment?order_id=${encodeURIComponent(order.id)}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"><i class="fas fa-exchange-alt mr-2 w-4"></i>Reassign Employee</a>`;
                    }
                    if (hasReplacements) {
                        dropdownActions += `<a href="<?= BASE_URL ?>/replacementmanagement?order_id=${encodeURIComponent(order.id)}" class="block px-4 py-2 text-sm text-red-700 hover:bg-red-50 transition-colors"><i class="fas fa-sync-alt mr-2 w-4"></i>Handle Replacements</a>`;
                    }
                    if (!hasPO && pct >= 100) {
                        dropdownActions += `<div class="px-4 py-2 text-xs font-medium text-yellow-700 bg-yellow-50 border-t border-gray-100"><i class="fas fa-exclamation-circle mr-1"></i>Awaiting P.O. Attachment</div>`;
                    }

                    card.innerHTML = `
<div class="p-4">
  <div class="flex items-center justify-between gap-4">
    <div class="flex items-center gap-3 flex-1 min-w-0">
      <div class="bg-primary-100 p-2 rounded-lg flex-shrink-0">
        <i class="fas fa-receipt text-primary-600 text-lg"></i>
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-1">
          <h3 class="text-lg font-bold text-gray-900">Order #${order.id}</h3>
          ${hasReplacements ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700"><i class="fas fa-sync-alt mr-1"></i>${approvedRepl}</span>` : ''}
          ${(isUnassigned && order.status === 'Ongoing') ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700"><i class="fas fa-user-slash mr-1"></i>Unassigned</span>` : ''}
        </div>
        <div class="flex items-center gap-4 text-xs text-gray-600">
          <span class="truncate"><i class="fas fa-user mr-1"></i>${order.customer_name}</span>
          <span><i class="fas fa-box mr-1"></i>${itemCount} items</span>
          <span class="hidden md:inline"><i class="fas fa-calendar mr-1"></i>${formatDate(order.created_at)}</span>
        </div>
      </div>
    </div>
    <div class="flex-shrink-0 w-32">
      <div class="text-xs text-gray-600 mb-1">Assignment</div>
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div class="h-2 rounded-full ${progressColor(pct)}" style="width:${pct}%"></div>
      </div>
      <div class="text-xs text-gray-500 mt-0.5">${pct}% Complete</div>
    </div>
    <div class="flex-shrink-0 w-36 hidden lg:block">
      <div class="text-xs text-gray-600 mb-1">Assigned To</div>
      <div class="text-sm font-medium ${(isUnassigned && order.status === 'Ongoing') ? 'text-orange-600' : 'text-blue-600'}">
        ${(isUnassigned && order.status === 'Ongoing') ? 'Not Assigned' : (order.assigned_employee_name || '—')}
      </div>
    </div>
    <div class="flex-shrink-0 w-28 hidden md:block">
      <div class="text-xs text-gray-600 mb-1">Status</div>
      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(order.status)}">
        ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
      </span>
    </div>
    <div class="flex-shrink-0 text-right w-28">
      <div class="text-xs text-gray-600 mb-1">Total</div>
      <div class="text-lg font-bold text-primary-700">${formatPeso(order.total)}</div>
    </div>
    <div class="flex-shrink-0 relative">
      <button onclick="toggleActionsMenu(${order.id})" class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
        <i class="fas fa-ellipsis-v text-gray-600"></i>
      </button>
      <div id="actions-${order.id}" class="hidden absolute right-0 bottom-full mb-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
        <div class="py-2">${dropdownActions}</div>
      </div>
    </div>
  </div>
</div>`;
                });
            }

            // ─── FETCH ────────────────────────────────────────────────────────────────

            function fetchDashboard() {
                const params = new URLSearchParams(window.location.search);
                if (previousHash) params.set('hash', previousHash);

                fetch(`${ENDPOINT}?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store',
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) return console.error('[WHPoll]', data.error);

                        // Walang pagbabago — wag na i-render
                        if (data.changed === false) {
                            previousHash = data.hash;
                            return;
                        }

                        const isFirstLoad = previousHash === '';
                        previousHash = data.hash;

                        renderStats(data.stats);
                        renderEmployeeStats(data.stats.employeeStats);
                        renderOrders(data.orders);

                        document.getElementById('last-updated') &&
                            (document.getElementById('last-updated').textContent =
                                'Updated ' + new Date().toLocaleTimeString('en-PH'));

                        if (!isFirstLoad) showToast('Dashboard updated');
                    })
                    .catch(err => console.warn('[WHPoll] Network error:', err.message));
            }

            // ─── DROPDOWN ────────────────────────────────────────────────────────────

            window.toggleActionsMenu = function (orderId) {
                const menu = document.getElementById(`actions-${orderId}`);
                document.querySelectorAll('[id^="actions-"]').forEach(m => {
                    if (m.id !== `actions-${orderId}`) m.classList.add('hidden');
                });
                menu.classList.toggle('hidden');
            };

            document.addEventListener('click', function (e) {
                if (!e.target.closest('button[onclick^="toggleActionsMenu"]') &&
                    !e.target.closest('[id^="actions-"]')) {
                    document.querySelectorAll('[id^="actions-"]').forEach(m => m.classList.add('hidden'));
                }
            });

            // ─── BOOT ────────────────────────────────────────────────────────────────

            fetchDashboard();
            setInterval(fetchDashboard, POLL_INTERVAL_MS);

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    previousHash = '';  // force re-render kapag bumalik sa tab
                    fetchDashboard();
                }
            });

        })();

    </script>


</body>

</html>