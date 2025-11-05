<?php
// warehouse_head_dashboard.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse']); // Only warehouse role can access

// Ensure session exists
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// --- Resolve logged-in user and check if they're a head ---
$user_id = null;
$fullname = '';
$user_lvl = '';
$is_head = false;

$sessionUser = $_SESSION['noble_user'];

// Initialize variables
$user_id = null;
$fullname = '';
$user_lvl = '';
$is_head = false;

// Check if session is an array
if (is_array($sessionUser)) {
    if (isset($sessionUser['id'])) {
        $user_id = (int)$sessionUser['id'];
    } elseif (isset($sessionUser['user_id'])) {
        $user_id = (int)$sessionUser['user_id'];
    }
    $fullname = $sessionUser['fullname'] ?? $sessionUser['name'] ?? '';
    $user_lvl = $sessionUser['lvl'] ?? $sessionUser['level'] ?? '';
    $is_head = isset($sessionUser['is_head']) && (int)$sessionUser['is_head'] === 1;
}

// If session is just an email or string, look up user by email or id
if (empty($user_id)) {
    if (!is_array($sessionUser)) {
        $lookupValue = $sessionUser;

        // Check if it's an email
        if (filter_var($lookupValue, FILTER_VALIDATE_EMAIL)) {
            $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE email = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("s", $lookupValue);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) {
                    $user_id = (int)$r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                    $is_head = (int)$r['is_head'] === 1;
                }
            }
        }
        // Check if it's a numeric ID
        elseif (ctype_digit((string)$lookupValue)) {
            $candidate = (int)$lookupValue;
            $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE id = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("i", $candidate);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) {
                    $user_id = (int)$r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                    $is_head = (int)$r['is_head'] === 1;
                }
            }
        }
    }
}

// CRITICAL: Only warehouse heads can access this page
if (!$is_head) {
    header("Location: order_list.php");
    exit();
}

// --- Filters from GET ---
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query  = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from     = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to       = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$employee_filter = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;
$show_replacements = isset($_GET['replacements']) ? (bool)$_GET['replacements'] : false;
$show_ready_for_schedule = isset($_GET['ready_schedule']) ? (bool)$_GET['ready_schedule'] : false;
$show_unassigned = isset($_GET['unassigned']) ? (bool)$_GET['unassigned'] : false;

// Get all warehouse employees
$warehouseEmployees = [];
$empSql = "SELECT id, fullname, is_head FROM nobleaccount WHERE lvl = 'warehouse' ORDER BY fullname ASC";
$empResult = $conn->query($empSql);
if ($empResult) {
    $warehouseEmployees = $empResult->fetch_all(MYSQLI_ASSOC);
}

// --- Build WHERE conditions ---
$whereParts = ["1=1"]; // Start with always true condition
$params = [];
$types = '';

// IMPORTANT: Heads see ALL orders, regular employees see only their assigned orders
if (!$is_head && !$show_unassigned) {
    // Regular warehouse employee: only show their assigned orders
    $whereParts[] = "o.warehouse_employee_id = ?";
    $params[] = $user_id;
    $types .= 'i';
} else {
    // Head: apply filters based on GET parameters
    if ($show_unassigned) {
        $whereParts[] = "(o.warehouse_employee_id IS NULL OR o.warehouse_employee_id = 0)";
    } elseif ($employee_filter > 0) {
        $whereParts[] = "o.warehouse_employee_id = ?";
        $params[] = $employee_filter;
        $types .= 'i';
    }
    // If no filter, heads see ALL orders (no additional WHERE needed)
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

// Helper function for binding params
function bindParamsToStmt($stmt, $types, $params)
{
    if ($types === '' || empty($params)) return;
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

// --- Orders query (ALL orders for head view) ---
$ordersSql = "
    SELECT 
        o.id,
        o.customer_name,
        o.email,
        o.created_at,
        o.status,
        o.total,
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
    if (!empty($params)) {
        bindParamsToStmt($stmt, $types, $params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) $orders = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// --- Statistics ---
$totalOrders = 0;
$unassignedCount = 0;
$statusCounts = [];
$replacementOrdersCount = 0;
$readyForScheduleCount = 0;

// Total and status counts
$statsSql = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$statsResult = $conn->query($statsSql);
if ($statsResult) {
    $statsData = $statsResult->fetch_all(MYSQLI_ASSOC);
    foreach ($statsData as $row) {
        $statusCounts[$row['status']] = (int)$row['count'];
        $totalOrders += (int)$row['count'];
    }
}

// Unassigned orders count
$unassignedSql = "SELECT COUNT(*) as count FROM orders WHERE warehouse_employee_id IS NULL OR warehouse_employee_id = 0";
$unassignedResult = $conn->query($unassignedSql);
if ($unassignedResult) {
    $unassignedData = $unassignedResult->fetch_assoc();
    $unassignedCount = (int)$unassignedData['count'];
}

// Replacement orders count
$replacementSql = "
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    WHERE EXISTS (SELECT 1 FROM replacement_requests rr WHERE rr.order_id = o.id AND rr.status = 'approved')
";
$replacementResult = $conn->query($replacementSql);
if ($replacementResult) {
    $replacementData = $replacementResult->fetch_assoc();
    $replacementOrdersCount = (int)$replacementData['count'];
}

// Ready for schedule count
$readyForScheduleSql = "
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    WHERE o.status IN ('processing', 'Ready for Pickup', 'Out for Delivery')
    AND NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id)
    AND (
        SELECT COUNT(*)
        FROM order_items oi
        WHERE oi.order_id = o.id
        AND oi.tracking_status = 'In Warehouse'
    ) = (
        SELECT COUNT(*)
        FROM order_items oi2
        WHERE oi2.order_id = o.id
    )
    AND (
        SELECT COUNT(*)
        FROM order_items oi
        WHERE oi.order_id = o.id
    ) > 0
";
$readyForScheduleResult = $conn->query($readyForScheduleSql);
if ($readyForScheduleResult) {
    $readyData = $readyForScheduleResult->fetch_assoc();
    $readyForScheduleCount = (int)$readyData['count'];
}

// Employee workload statistics
$employeeStats = [];
$empStatsSql = "
    SELECT 
        na.id,
        na.fullname,
        COUNT(o.id) as order_count,
        SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) as processing_count
    FROM nobleaccount na
    LEFT JOIN orders o ON na.id = o.warehouse_employee_id
    WHERE na.lvl = 'warehouse'
    GROUP BY na.id, na.fullname
    ORDER BY na.fullname ASC
";
$empStatsResult = $conn->query($empStatsSql);
if ($empStatsResult) {
    $employeeStats = $empStatsResult->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Warehouse Head Dashboard - All Orders</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
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

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header with Crown Badge -->
    <div class="bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="bg-white/20 p-4 rounded-xl backdrop-blur-sm">
                        <i class="fas fa-crown text-yellow-300 text-3xl"></i>
                    </div>
                    <div>
                        <p class="text-red-100 mt-1">
                            Viewing: <strong>ALL WAREHOUSE ORDERS</strong>
                            <?php if ($employee_filter > 0): ?>
                                - Filtered by employee
                            <?php elseif ($show_unassigned): ?>
                                - Unassigned only
                            <?php endif; ?>
                        </p>
                        <p class="text-red-100 mt-1">Complete overview of all warehouse orders and assignments</p>
                    </div>
                </div>
                <div class="text-right">
                    <a href="warehouse_assignment.php" class="bg-white text-red-600 hover:bg-red-50 px-6 py-3 rounded-xl transition-all duration-200 inline-flex items-center space-x-3 shadow-lg hover:shadow-xl hover:scale-105 font-semibold border-2 border-red-100">
                        <i class="fas fa-users-cog text-lg"></i>
                        <span>Manage Assignments</span>
                    </a>
                    <p class="text-red-100 text-sm mt-3">Logged in as: <strong><?php echo htmlspecialchars($fullname); ?></strong></p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <!-- Total Orders -->
            <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Orders</p>
                        <p class="text-3xl font-bold mt-1"><?php echo $totalOrders; ?></p>
                    </div>
                    <div class="bg-white/20 p-3 rounded-lg">
                        <i class="fas fa-shopping-cart text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Unassigned Orders -->
            <div class="stat-card bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100 text-sm font-medium">Unassigned</p>
                        <p class="text-3xl font-bold mt-1"><?php echo $unassignedCount; ?></p>
                    </div>
                    <div class="bg-white/20 p-3 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                    </div>
                </div>
                <?php if ($unassignedCount > 0): ?>
                    <a href="warehouse_assignment.php" class="mt-3 text-xs bg-white/20 hover:bg-white/30 px-3 py-1 rounded-full inline-block">
                        Assign Now →
                    </a>
                <?php endif; ?>
            </div>

            <!-- Replacement Requests -->
            <?php if ($replacementOrdersCount > 0): ?>
                <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white pulse-notification">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Replacements</p>
                            <p class="text-3xl font-bold mt-1"><?php echo $replacementOrdersCount; ?></p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i class="fas fa-sync-alt text-2xl"></i>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ready for Schedule -->
            <?php if ($readyForScheduleCount > 0): ?>
                <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white pulse-notification">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Ready to Schedule</p>
                            <p class="text-3xl font-bold mt-1"><?php echo $readyForScheduleCount; ?></p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i class="fas fa-calendar-check text-2xl"></i>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Warehouse Team -->
            <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Team Members</p>
                        <p class="text-3xl font-bold mt-1"><?php echo count($warehouseEmployees); ?></p>
                    </div>
                    <div class="bg-white/20 p-3 rounded-lg">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Workload Overview -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-chart-bar text-primary-600 mr-2"></i>
                Employee Workload Distribution
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($employeeStats as $empStat): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($empStat['fullname']); ?></h3>
                            <span class="text-2xl font-bold text-primary-600"><?php echo $empStat['order_count']; ?></span>
                        </div>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div class="flex justify-between">
                                <span>Pending:</span>
                                <span class="font-medium text-yellow-600"><?php echo $empStat['pending_count']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Processing:</span>
                                <span class="font-medium text-blue-600"><?php echo $empStat['processing_count']; ?></span>
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
                    <a href="?" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo ($status_filter === '' && !$show_replacements && !$show_unassigned && !$show_ready_for_schedule) ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        All Orders (<?php echo $totalOrders; ?>)
                    </a>

                    <?php
                    // Define the specific order you want
                    $statusOrder = ['processing', 'Picked Up', 'Delivered'];

                    // Display statuses in the specified order
                    foreach ($statusOrder as $status) {
                        if (isset($statusCounts[$status]) && $statusCounts[$status] > 0):
                    ?>
                            <a href="?status=<?php echo urlencode($status); ?>"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo ($status_filter === $status) ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                <?php echo htmlspecialchars(ucfirst($status)); ?> (<?php echo $statusCounts[$status]; ?>)
                            </a>
                    <?php
                        endif;
                    }
                    ?>

                    <?php if ($readyForScheduleCount > 0): ?>
                        <a href="?ready_schedule=1" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 relative <?php echo $show_ready_for_schedule ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200 pulse-notification'; ?>">
                            <i class="fas fa-calendar-check mr-1"></i>
                            Ready to Schedule (<?php echo $readyForScheduleCount; ?>)
                            <?php if (!$show_ready_for_schedule): ?>
                                <span class="absolute -top-1 -right-1 h-3 w-3 bg-green-500 border-2 border-white rounded-full animate-ping"></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($unassignedCount > 0): ?>
                        <a href="?unassigned=1" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 relative <?php echo $show_unassigned ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200 pulse-notification'; ?>">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Unassigned (<?php echo $unassignedCount; ?>)
                            <?php if (!$show_unassigned): ?>
                                <span class="absolute -top-1 -right-1 h-3 w-3 bg-red-500 border-2 border-white rounded-full animate-ping"></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($replacementOrdersCount > 0): ?>
                        <a href="?replacements=1" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo $show_replacements ? 'bg-orange-600 text-white' : 'bg-orange-100 text-orange-700 hover:bg-orange-200 pulse-notification'; ?>">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Replacements (<?php echo $replacementOrdersCount; ?>)
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
                        <select name="employee" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="0">All Employees</option>
                            <?php foreach ($warehouseEmployees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo ($employee_filter == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['fullname']); ?>
                                    <?php echo ((int)$emp['is_head'] === 1) ? ' (Head)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-md transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (!empty($orders)): ?>
            <div class="space-y-4">
                <?php foreach ($orders as $order):
                    $assignedItems = (int)$order['assigned_count'];
                    $item_count = (int)$order['item_count'];
                    $po_attachment_count = (int)$order['po_attachment_count'];
                    $approved_replacements_count = (int)$order['approved_replacements_count'];
                    $assignmentPercentage = $item_count > 0 ? round(($assignedItems / $item_count) * 100) : 0;
                    $hasPOFiles = $po_attachment_count > 0;
                    $hasReplacements = $approved_replacements_count > 0;
                    $isUnassigned = empty($order['warehouse_employee_id']);
                ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 <?php echo $hasReplacements ? 'border-l-4 border-l-red-500' : ''; ?> <?php echo $isUnassigned ? 'border-l-4 border-l-orange-500' : ''; ?>">
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-4">
                                <!-- Order Info -->
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="bg-primary-100 p-2 rounded-lg flex-shrink-0">
                                        <i class="fas fa-receipt text-primary-600 text-lg"></i>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="text-lg font-bold text-gray-900">Order #<?php echo htmlspecialchars($order['id']); ?></h3>

                                            <?php if ($hasReplacements): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                    <i class="fas fa-sync-alt mr-1"></i><?php echo $approved_replacements_count; ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($isUnassigned): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                                    <i class="fas fa-user-slash mr-1"></i>Unassigned
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex items-center gap-4 text-xs text-gray-600">
                                            <span class="truncate"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                            <span><i class="fas fa-box mr-1"></i><?php echo $item_count; ?> items</span>
                                            <span class="hidden md:inline"><i class="fas fa-calendar mr-1"></i><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Assignment Progress -->
                                <div class="flex-shrink-0 w-32">
                                    <div class="text-xs text-gray-600 mb-1">Assignment</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full <?php echo ($assignmentPercentage === 100) ? 'bg-green-500' : (($assignmentPercentage > 0) ? 'bg-yellow-500' : 'bg-red-500'); ?>"
                                            style="width: <?php echo $assignmentPercentage; ?>%">
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5"><?php echo $assignmentPercentage; ?>% Complete</div>
                                </div>

                                <!-- Employee -->
                                <div class="flex-shrink-0 w-36 hidden lg:block">
                                    <div class="text-xs text-gray-600 mb-1">Assigned To</div>
                                    <div class="text-sm font-medium <?php echo $isUnassigned ? 'text-orange-600' : 'text-blue-600'; ?>">
                                        <?php echo $isUnassigned ? 'Not Assigned' : htmlspecialchars($order['assigned_employee_name']); ?>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="flex-shrink-0 w-28 hidden md:block">
                                    <div class="text-xs text-gray-600 mb-1">Status</div>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                        <?php
                        echo ($order['status'] === 'pending') ? 'bg-yellow-100 text-yellow-700' : (($order['status'] === 'processing') ? 'bg-blue-100 text-blue-700' : (($order['status'] === 'completed') ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'));
                        ?>">
                                        <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                    </span>
                                </div>

                                <!-- Price -->
                                <div class="flex-shrink-0 text-right w-28">
                                    <div class="text-xs text-gray-600 mb-1">Total</div>
                                    <div class="text-lg font-bold text-primary-700">
                                        ₱<?php echo number_format((float)$order['total'], 2); ?>
                                    </div>
                                </div>

                                <!-- Actions Dropdown -->
                                <div class="flex-shrink-0 relative">
                                    <button onclick="toggleActionsMenu(<?php echo $order['id']; ?>)"
                                        class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-ellipsis-v text-gray-600"></i>
                                    </button>

                                    <!-- Dropdown Menu -->
                                    <div id="actions-<?php echo $order['id']; ?>"
                                        class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                        <div class="py-2">
                                            <?php if ($isUnassigned): ?>
                                                <a href="warehouse_assignment.php?order_id=<?php echo urlencode($order['id']); ?>"
                                                    class="block px-4 py-2 text-sm text-orange-700 hover:bg-orange-50 transition-colors">
                                                    <i class="fas fa-user-plus mr-2 w-4"></i>Assign Employee
                                                </a>
                                            <?php else: ?>
                                                <a href="warehouse_assignment.php?order_id=<?php echo urlencode($order['id']); ?>"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                                    <i class="fas fa-exchange-alt mr-2 w-4"></i>Reassign Employee
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($hasPOFiles): ?>
                                                <a href="view_po_files.php?order_id=<?php echo urlencode($order['id']); ?>"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                                    <i class="fas fa-file-alt mr-2 w-4"></i>View P.O. Files
                                                </a>
                                            <?php endif; ?>

                                            <?php if (in_array($order['status'], ['processing', 'Ready for Pickup', 'Picked Up', 'Delivered', 'Out for Delivery'])): ?>
                                                <button onclick="openTrackingModal(<?php echo $order['id']; ?>); toggleActionsMenu(<?php echo $order['id']; ?>);"
                                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                                    <i class="fas fa-route mr-2 w-4"></i>Track Items
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($hasReplacements): ?>
                                                <a href="replacement_management.php?order_id=<?php echo urlencode($order['id']); ?>"
                                                    class="block px-4 py-2 text-sm text-red-700 hover:bg-red-50 transition-colors">
                                                    <i class="fas fa-sync-alt mr-2 w-4"></i>Handle Replacements
                                                </a>
                                            <?php endif; ?>

                                            <?php
                                            // Check if ready for scheduling
                                            $isReadyForSchedule = false;
                                            if ($hasPOFiles && $po_attachment_count > 0) {
                                                $checkReadySql = "
                                    SELECT 
                                        (SELECT COUNT(*) FROM order_items WHERE order_id = ? AND tracking_status = 'In Warehouse') as in_warehouse_count,
                                        (SELECT COUNT(*) FROM order_items WHERE order_id = ?) as total_count,
                                        (SELECT COUNT(*) FROM delivery_schedules WHERE order_id = ?) as schedule_count
                                ";
                                                if ($checkReadyStmt = $conn->prepare($checkReadySql)) {
                                                    $checkReadyStmt->bind_param("iii", $order['id'], $order['id'], $order['id']);
                                                    $checkReadyStmt->execute();
                                                    $checkReadyResult = $checkReadyStmt->get_result()->fetch_assoc();
                                                    $checkReadyStmt->close();

                                                    if (
                                                        $checkReadyResult['in_warehouse_count'] == $checkReadyResult['total_count']
                                                        && $checkReadyResult['total_count'] > 0
                                                        && $checkReadyResult['schedule_count'] == 0
                                                    ) {
                                                        $isReadyForSchedule = true;
                                                    }
                                                }
                                            }

                                            if ($isReadyForSchedule): ?>
                                                <div class="px-4 py-2 text-xs font-bold text-green-700 bg-green-50 border-t border-gray-100">
                                                    <i class="fas fa-calendar-check mr-1"></i>READY TO SCHEDULE
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!$hasPOFiles && $assignmentPercentage >= 100): ?>
                                                <div class="px-4 py-2 text-xs font-medium text-yellow-700 bg-yellow-50 border-t border-gray-100">
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
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
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
        <?php endif; ?>
    </div>
    <!-- Tracking Modal -->
    <div id="trackingModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" style="backdrop-filter: blur(8px);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-route mr-3 text-blue-600"></i>
                    Order Tracking - <span id="modalOrderId"></span>
                </h3>
                <button onclick="closeTrackingModal()" class="text-gray-500 hover:text-gray-700 p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-6">
                <div id="trackingContent">
                    <!-- Loading spinner -->
                    <div class="flex items-center justify-center py-12">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Status definitions
        const statusDefinitions = {
            'local': {
                'pending': {
                    icon: 'fa-cog',
                    color: 'blue',
                    label: 'Pending',
                    description: 'Order confirmed and being prepared',
                    progress: 16
                },
                'processing': {
                    icon: 'fa-cog',
                    color: 'blue',
                    label: 'Processing',
                    description: 'Order confirmed and being prepared',
                    progress: 16
                },
                'In Warehouse': {
                    icon: 'fa-warehouse',
                    color: 'indigo',
                    label: 'In Warehouse',
                    description: 'Item received and stored in warehouse',
                    progress: 33
                },
                'scheduled': {
                    icon: 'fa-calendar-check',
                    color: 'purple',
                    label: 'Scheduled',
                    description: 'Delivery has been scheduled',
                    progress: 50
                },
                'ready_for_pickup': {
                    icon: 'fa-truck',
                    color: 'yellow',
                    label: 'Ready for Pickup/Dispatch',
                    description: 'Item ready for local delivery',
                    progress: 66
                },
                'out_for_delivery': {
                    icon: 'fa-shipping-fast',
                    color: 'orange',
                    label: 'Out for Delivery',
                    description: 'Courier delivering to customer',
                    progress: 83
                },
                'delivered': {
                    icon: 'fa-check-circle',
                    color: 'green',
                    label: 'Delivered',
                    description: 'Customer received the item',
                    progress: 100
                },
                'cancelled': {
                    icon: 'fa-times-circle',
                    color: 'red',
                    label: 'Returned',
                    description: 'Order cancelled or returned',
                    progress: 0
                }
            },
            'international': {
                'pending': {
                    icon: 'fa-cog',
                    color: 'blue',
                    label: 'Pending',
                    description: 'Order confirmed, supplier preparing',
                    progress: 9
                },
                'processing': {
                    icon: 'fa-cog',
                    color: 'blue',
                    label: 'Processing',
                    description: 'Order confirmed, supplier preparing',
                    progress: 9
                },
                'shipped_overseas': {
                    icon: 'fa-ship',
                    color: 'purple',
                    label: 'Shipped from Overseas',
                    description: 'Item has left the overseas supplier',
                    progress: 20
                },
                'in_transit_international': {
                    icon: 'fa-plane',
                    color: 'yellow',
                    label: 'In Transit (International)',
                    description: 'Item is on the way (by sea/air)',
                    progress: 32
                },
                'customs_clearance': {
                    icon: 'fa-file-signature',
                    color: 'orange',
                    label: 'Customs Clearance',
                    description: 'Item undergoing customs inspection',
                    progress: 44
                },
                'In Warehouse': {
                    icon: 'fa-warehouse',
                    color: 'indigo',
                    label: 'In Warehouse',
                    description: 'Item received and stored in local warehouse',
                    progress: 55
                },
                'scheduled': {
                    icon: 'fa-calendar-check',
                    color: 'purple',
                    label: 'Scheduled',
                    description: 'Delivery has been scheduled',
                    progress: 66
                },
                'ready_for_pickup': {
                    icon: 'fa-truck',
                    color: 'yellow',
                    label: 'Ready for Pickup/Dispatch',
                    description: 'Item ready for local delivery',
                    progress: 77
                },
                'out_for_delivery': {
                    icon: 'fa-shipping-fast',
                    color: 'orange',
                    label: 'Out for Delivery',
                    description: 'Courier delivering to customer',
                    progress: 88
                },
                'delivered': {
                    icon: 'fa-check-circle',
                    color: 'green',
                    label: 'Delivered',
                    description: 'Customer received the item',
                    progress: 100
                },
                'cancelled': {
                    icon: 'fa-times-circle',
                    color: 'red',
                    label: 'Returned',
                    description: 'Order cancelled or returned',
                    progress: 0
                }
            }
        };

        const replacementStatusDefinitions = {
            'approved': {
                icon: 'fa-check-circle',
                color: 'green',
                label: 'Approved',
                description: 'Replacement request has been approved',
                progress: 14
            },
            'processing': {
                icon: 'fa-cog',
                color: 'blue',
                label: 'Processing',
                description: 'Replacement being prepared',
                progress: 28
            },
            'In Warehouse': {
                icon: 'fa-warehouse',
                color: 'indigo',
                label: 'In Warehouse',
                description: 'Replacement received and stored in warehouse',
                progress: 42
            },
            'scheduled': {
                icon: 'fa-calendar-check',
                color: 'purple',
                label: 'Scheduled',
                description: 'Replacement delivery scheduled',
                progress: 57
            },
            'ready_for_pickup': {
                icon: 'fa-truck',
                color: 'yellow',
                label: 'Ready for Pickup/Dispatch',
                description: 'Replacement ready for delivery',
                progress: 71
            },
            'out_for_delivery': {
                icon: 'fa-shipping-fast',
                color: 'orange',
                label: 'Out for Delivery',
                description: 'Replacement being delivered',
                progress: 85
            },
            'delivered': {
                icon: 'fa-check-circle',
                color: 'green',
                label: 'Delivered',
                description: 'Replacement delivered to customer',
                progress: 100
            },
            'cancelled': {
                icon: 'fa-times-circle',
                color: 'red',
                label: 'Cancelled',
                description: 'Replacement request cancelled',
                progress: 0
            }
        };

        function openTrackingModal(orderId) {
            const modal = document.getElementById('trackingModal');
            const content = document.getElementById('trackingContent');
            const orderIdSpan = document.getElementById('modalOrderId');

            orderIdSpan.textContent = `#${orderId}`;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');

            // Show loading
            content.innerHTML = `
                <div class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
                </div>
            `;

            // Fetch tracking data
            fetch(`get_tracking_data.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderTrackingContent(data);
                    } else {
                        content.innerHTML = `
                            <div class="text-center py-12">
                                <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                <p class="text-gray-600">${data.error || 'Failed to load tracking data'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div class="text-center py-12">
                            <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                            <p class="text-gray-600">Failed to load tracking data. Please try again.</p>
                        </div>
                    `;
                });
        }

        function closeTrackingModal() {
            const modal = document.getElementById('trackingModal');
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function renderTrackingContent(data) {
            const content = document.getElementById('trackingContent');
            let html = '';

            // Render grouped items
            ['local', 'international'].forEach(origin => {
                if (data.groupedItems[origin] && Object.keys(data.groupedItems[origin]).length > 0) {
                    const config = origin === 'local' ?
                        {
                            color: 'green',
                            icon: 'fa-home',
                            emoji: '🏠',
                            gradient: 'from-green-500 to-green-600',
                            title: 'Local Products'
                        } :
                        {
                            color: 'blue',
                            icon: 'fa-globe',
                            emoji: '🌏',
                            gradient: 'from-blue-500 to-blue-600',
                            title: 'International Products'
                        };

                    html += `
                        <div class="mb-8">
                            <div class="flex items-center mb-6">
                                <div class="bg-gradient-to-r ${config.gradient} p-4 rounded-2xl mr-4 shadow-lg">
                                    <i class="fas ${config.icon} text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">${config.emoji} ${config.title}</h2>
                                </div>
                            </div>
                    `;

                    Object.keys(data.groupedItems[origin]).forEach(supplier => {
                        const supplierItems = data.groupedItems[origin][supplier];
                        html += renderSupplierGroup(supplier, supplierItems, origin, config);
                    });

                    html += `</div>`;
                }
            });

            content.innerHTML = html;
        }

        function renderSupplierGroup(supplier, items, origin, config) {
            let html = `
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-6 overflow-hidden">
                    <div class="p-6 bg-gradient-to-r ${config.gradient} text-white">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                                    <i class="fas fa-building text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold">${supplier}</h3>
                                    <p class="text-white text-opacity-90">${config.emoji} ${origin.charAt(0).toUpperCase() + origin.slice(1)} Supplier</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
            `;

            // Render original items
            items.original.forEach(item => {
                html += renderItem(item, origin, false);
            });

            // Render replacement items
            items.replacement.forEach(item => {
                html += renderItem(item, origin, true);
            });

            html += `</div></div>`;
            return html;
        }

        function renderItem(item, origin, isReplacement) {
            const statuses = isReplacement ? replacementStatusDefinitions : statusDefinitions[origin];
            const currentStatus = item.current_status;
            const statusInfo = statuses[currentStatus] || statuses['processing'];

            let html = `
                <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition-shadow cursor-pointer ${isReplacement ? 'bg-gradient-to-r from-red-50 to-white border-l-4 border-l-red-500' : ''}"
                     onclick="showItemProgress('${item.id}', '${origin}', '${currentStatus}', ${isReplacement})">
            `;

            if (isReplacement) {
                html += `
                    <div class="mb-4 flex items-center justify-between">
                        <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-semibold">
                            <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT ITEM
                        </span>
                        <span class="text-sm text-gray-600">Reason: ${item.replacement_reason}</span>
                    </div>
                `;
            }

            html += `
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900 text-lg mb-2">${item.product_name}</h4>
                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                <span><i class="fas fa-boxes mr-1"></i>Qty: <strong>${item.quantity}</strong></span>
                                <span><i class="fas fa-peso-sign mr-1"></i>Price: <strong>${parseFloat(item.price).toFixed(2)}</strong></span>
                                ${item.codename ? `<span><i class="fas fa-tag mr-1"></i>Code: <strong>${item.codename}</strong></span>` : ''}
                            </div>
                        </div>
                        <div class="text-center ml-4">
                            <div class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold mb-2 bg-${statusInfo.color}-100 text-${statusInfo.color}-800">
                                <i class="fas ${statusInfo.icon} mr-2"></i>
                                <span>${statusInfo.label}</span>
                            </div>
                            <div class="text-xs text-gray-600">${statusInfo.description}</div>
                            <div class="mt-2 text-xs font-bold text-${statusInfo.color}-600">${statusInfo.progress}% Complete</div>
                        </div>
                    </div>
                </div>
            `;

            return html;
        }

        function showItemProgress(itemId, origin, currentStatus, isReplacement) {
            const statuses = isReplacement ? replacementStatusDefinitions : statusDefinitions[origin];
            const statusKeys = Object.keys(statuses);
            const currentIndex = statusKeys.indexOf(currentStatus);

            let progressHTML = `
                <div class="fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4" style="backdrop-filter: blur(8px);" onclick="this.remove()">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-route mr-3 text-blue-600"></i>
                                Tracking Progress
                            </h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700 p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6">
                            <div class="mb-8">
                                <div class="flex items-center justify-between mb-6">
                                    <h4 class="text-lg font-bold text-gray-900">
                                        ${isReplacement ? 'Replacement' : 'Item'} Progress Timeline
                                    </h4>
                                    ${isReplacement ? '<span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-semibold"><i class="fas fa-sync-alt mr-1"></i>REPLACEMENT</span>' : ''}
                                </div>
                                <div class="relative pl-12">
                                    <div class="absolute left-6 top-0 bottom-0 w-1 bg-gray-200 rounded-full"></div>
                                    <div class="absolute left-6 top-0 w-1 ${isReplacement ? 'bg-red-500' : 'bg-blue-500'} rounded-full transition-all duration-1500" style="height: ${((currentIndex + 1) / statusKeys.length) * 100}%;"></div>
            `;

            statusKeys.forEach((status, index) => {
                const statusInfo = statuses[status];
                const isActive = index <= currentIndex;
                const isCurrent = status === currentStatus;

                progressHTML += `
                    <div class="relative flex items-start mb-8 last:mb-0">
                        <div class="absolute -left-8 flex-shrink-0 w-8 h-8 rounded-full border-4 ${isActive ? `${isReplacement ? 'bg-red-500 border-red-500' : 'bg-blue-500 border-blue-500'}` : 'bg-white border-gray-300'} flex items-center justify-center z-10 shadow-lg">
                            <i class="fas ${statusInfo.icon} ${isActive ? 'text-white' : 'text-gray-400'} text-sm"></i>
                        </div>
                        <div class="ml-6 flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="text-base font-bold ${isActive ? 'text-gray-900' : 'text-gray-500'}">${statusInfo.label}</h5>
                                <span class="text-sm font-medium ${isActive ? (isReplacement ? 'text-red-600' : 'text-blue-600') : 'text-gray-400'}">${statusInfo.progress}%</span>
                            </div>
                            <p class="text-sm ${isActive ? 'text-gray-600' : 'text-gray-400'} mb-3">${statusInfo.description}</p>
                            ${isCurrent ? `<span class="inline-flex items-center ${isReplacement ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'} text-xs font-bold px-3 py-1 rounded-full"><i class="fas fa-map-marker-alt mr-1"></i>Current Status</span>` : ''}
                        </div>
                    </div>
                `;
            });

            progressHTML += `
                                </div>
                            </div>
                            <div class="bg-gradient-to-r ${isReplacement ? 'from-red-50 to-red-100' : 'from-blue-50 to-blue-100'} rounded-xl p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <span class="text-base font-bold text-gray-900">Overall Progress</span>
                                    <span class="text-xl font-bold ${isReplacement ? 'text-red-600' : 'text-blue-600'}">${statuses[currentStatus].progress}%</span>
                                </div>
                                <div class="w-full bg-gray-300 rounded-full h-4 overflow-hidden">
                                    <div class="bg-gradient-to-r ${isReplacement ? 'from-red-400 to-red-500' : 'from-blue-400 to-blue-500'} h-4 rounded-full transition-all duration-1500" style="width: ${statuses[currentStatus].progress}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', progressHTML);
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTrackingModal();
            }
        });

        // Close tracking modal when clicking outside
        document.getElementById('trackingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTrackingModal();
            }
        });

        // Auto-refresh every 2 minutes to keep data current
        setTimeout(function() {
            location.reload();
        }, 120000);

        // Highlight newly unassigned orders
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('unassigned') === '1') {
                console.log('Showing unassigned orders');
            }
        });

        // Toggle actions dropdown menu
        function toggleActionsMenu(orderId) {
            const menu = document.getElementById(`actions-${orderId}`);
            const allMenus = document.querySelectorAll('[id^="actions-"]');

            // Close all other menus
            allMenus.forEach(m => {
                if (m.id !== `actions-${orderId}`) {
                    m.classList.add('hidden');
                }
            });

            // Toggle current menu
            menu.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('button[onclick^="toggleActionsMenu"]') && !e.target.closest('[id^="actions-"]')) {
                document.querySelectorAll('[id^="actions-"]').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });
    </script>
</body>

</html>