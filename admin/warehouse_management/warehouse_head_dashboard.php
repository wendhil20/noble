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

// Lookup user if needed
if (empty($user_id) || empty($user_lvl)) {
    if (!is_array($sessionUser)) {
        if (ctype_digit((string)$sessionUser)) {
            $candidate = (int)$sessionUser;
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

// Unassigned orders filter
if ($show_unassigned) {
    $whereParts[] = "(o.warehouse_employee_id IS NULL OR o.warehouse_employee_id = 0)";
} elseif ($employee_filter > 0) {
    $whereParts[] = "o.warehouse_employee_id = ?";
    $params[] = $employee_filter;
    $types .= 'i';
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
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
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
                        <div class="flex items-center space-x-2">
                            <h1 class="text-3xl font-bold">Warehouse Head Dashboard</h1>
                            <span class="px-3 py-1 bg-yellow-300 text-red-800 rounded-full text-sm font-bold">
                                <i class="fas fa-shield-alt mr-1"></i>HEAD ACCESS
                            </span>
                        </div>
                        <p class="text-red-100 mt-1">Complete overview of all warehouse orders and assignments</p>
                    </div>
                </div>
                <div class="text-right">
                    <a href="order_list.php" class="bg-white text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg transition-all duration-200 flex items-center space-x-2 shadow-lg">
                        <i class="fas fa-arrow-left"></i>
                        <span class="font-medium">Back to My Orders</span>
                    </a>
                    <p class="text-red-100 text-sm mt-2">Logged in as: <strong><?php echo htmlspecialchars($fullname); ?></strong></p>
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

                    <?php if ($unassignedCount > 0): ?>
                        <a href="?unassigned=1" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 relative <?php echo $show_unassigned ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200 pulse-notification'; ?>">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Unassigned (<?php echo $unassignedCount; ?>)
                            <?php if (!$show_unassigned): ?>
                                <span class="absolute -top-1 -right-1 h-3 w-3 bg-red-500 border-2 border-white rounded-full animate-ping"></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <?php foreach ($statusCounts as $status => $count): ?>
                        <a href="?status=<?php echo urlencode($status); ?>" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo ($status_filter === $status) ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                            <?php echo htmlspecialchars(ucfirst($status)); ?> (<?php echo $count; ?>)
                        </a>
                    <?php endforeach; ?>

                    <?php if ($replacementOrdersCount > 0): ?>
                        <a href="?replacements=1" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo $show_replacements ? 'bg-orange-600 text-white' : 'bg-orange-100 text-orange-700 hover:bg-orange-200 pulse-notification'; ?>">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Replacements (<?php echo $replacementOrdersCount; ?>)
                        </a>
                    <?php endif; ?>

                    <?php if ($readyForScheduleCount > 0): ?>
                        <a href="?ready_schedule=1" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 relative <?php echo $show_ready_for_schedule ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200 pulse-notification'; ?>">
                            <i class="fas fa-calendar-check mr-1"></i>
                            Ready to Schedule (<?php echo $readyForScheduleCount; ?>)
                            <?php if (!$show_ready_for_schedule): ?>
                                <span class="absolute -top-1 -right-1 h-3 w-3 bg-green-500 border-2 border-white rounded-full animate-ping"></span>
                            <?php endif; ?>
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
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200 <?php echo $hasReplacements ? 'border-l-4 border-l-red-500' : ''; ?> <?php echo $isUnassigned ? 'border-l-4 border-l-orange-500' : ''; ?>">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4 flex-1">
                                    <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-3 rounded-lg relative">
                                        <i class="fas fa-receipt text-white text-xl"></i>
                                        <?php if ($hasReplacements): ?>
                                            <span class="absolute -top-2 -right-2 h-5 w-5 bg-red-500 border-2 border-white rounded-full flex items-center justify-center">
                                                <i class="fas fa-exclamation text-white text-xs"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <h3 class="text-xl font-bold text-gray-900">Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                                            <?php if ($hasReplacements): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 pulse-notification">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    <?php echo $approved_replacements_count; ?> Replacement<?php echo $approved_replacements_count > 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($isUnassigned): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 pulse-notification">
                                                    <i class="fas fa-user-slash mr-1"></i>Not Assigned
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                                            <div>
                                                <div class="flex items-center mb-1">
                                                    <i class="fas fa-user mr-2 text-gray-400"></i>
                                                    <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                </div>
                                                <div class="flex items-center mb-1">
                                                    <i class="fas fa-envelope mr-2 text-gray-400"></i>
                                                    <span><?php echo htmlspecialchars($order['email']); ?></span>
                                                </div>
                                                <div class="flex items-center">
                                                    <i class="fas fa-calendar mr-2 text-gray-400"></i>
                                                    <span><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <div class="flex items-center mb-1">
                                                    <i class="fas fa-user-tie mr-2 text-gray-400"></i>
                                                    <span class="font-medium <?php echo $isUnassigned ? 'text-orange-600' : 'text-blue-600'; ?>">
                                                        <?php echo $isUnassigned ? 'Unassigned' : htmlspecialchars($order['assigned_employee_name']); ?>
                                                    </span>
                                                </div>
                                                <div class="flex items-center mb-1">
                                                    <i class="fas fa-box mr-2 text-gray-400"></i>
                                                    <span><?php echo $item_count; ?> item(s)</span>
                                                </div>
                                                <div class="flex items-center">
                                                    <i class="fas fa-circle mr-2 <?php echo ($order['status'] === 'pending') ? 'text-yellow-500' : (($order['status'] === 'processing') ? 'text-blue-500' : (($order['status'] === 'completed') ? 'text-green-500' : 'text-gray-500')); ?>"></i>
                                                    <span><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Assignment Progress -->
                                        <div class="mt-4">
                                            <div class="flex items-center justify-between mb-1 text-xs">
                                                <span class="text-gray-600 font-medium">Supplier Assignment Progress</span>
                                                <span class="font-bold <?php echo ($assignmentPercentage === 100) ? 'text-green-600' : (($assignmentPercentage > 0) ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                    <?php echo $assignmentPercentage; ?>%
                                                </span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                                <div class="h-3 rounded-full transition-all duration-300 <?php echo ($assignmentPercentage === 100) ? 'bg-gradient-to-r from-green-500 to-green-600' : (($assignmentPercentage > 0) ? 'bg-gradient-to-r from-yellow-500 to-yellow-600' : 'bg-gradient-to-r from-red-500 to-red-600'); ?>" 
                                                     style="width: <?php echo $assignmentPercentage; ?>%">
                                                </div>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500">
                                                <?php echo $assignedItems; ?> of <?php echo $item_count; ?> items assigned to suppliers
                                            </div>
                                            
                                            <?php if ($hasPOFiles): ?>
                                                <div class="mt-2 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-file-excel mr-1"></i>
                                                    <?php echo $po_attachment_count; ?> P.O. file(s) attached
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right side: Price and Actions -->
                                <div class="text-right ml-6">
                                    <div class="text-2xl font-bold text-primary-700 mb-4">
                                        ₱<?php echo number_format((float)$order['total'], 2); ?>
                                    </div>

                                    <div class="space-y-2">
                                        <!-- View Order Details -->
                                        <a href="order_list.php?search=<?php echo urlencode($order['id']); ?>" 
                                           class="block w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center text-sm">
                                            <i class="fas fa-eye mr-1"></i>View Details
                                        </a>

                                        <?php if ($isUnassigned): ?>
                                            <!-- Assign Employee -->
                                            <a href="warehouse_assignment.php?order_id=<?php echo urlencode($order['id']); ?>" 
                                               class="block w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center text-sm pulse-notification">
                                                <i class="fas fa-user-plus mr-1"></i>Assign Employee
                                            </a>
                                        <?php else: ?>
                                            <!-- Reassign -->
                                            <a href="warehouse_assignment.php?order_id=<?php echo urlencode($order['id']); ?>" 
                                               class="block w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center text-sm">
                                                <i class="fas fa-exchange-alt mr-1"></i>Reassign
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!$hasPOFiles && $assignmentPercentage >= 100): ?>
                                            <!-- Needs P.O. -->
                                            <div class="block w-full bg-yellow-100 text-yellow-800 px-4 py-2 rounded-lg text-center text-xs font-medium">
                                                <i class="fas fa-exclamation-circle mr-1"></i>Awaiting P.O. Attachment
                                            </div>
                                        <?php elseif ($hasPOFiles): ?>
                                            <!-- View P.O. Files -->
                                            <a href="view_po_files.php?order_id=<?php echo urlencode($order['id']); ?>" 
                                               class="block w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center text-sm">
                                                <i class="fas fa-file-alt mr-1"></i>View P.O. Files
                                            </a>
                                        <?php endif; ?>

                                        <?php if (in_array($order['status'], ['processing', 'Ready for Pickup', 'Picked Up', 'Delivered', 'Out for Delivery'])): ?>
                                            <!-- Track Items -->
                                            <a href="order_tracking.php?order_id=<?php echo urlencode($order['id']); ?>" 
                                               class="block w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center text-sm">
                                                <i class="fas fa-route mr-1"></i>Track Items
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($hasReplacements): ?>
                                            <!-- Handle Replacements -->
                                            <a href="replacement_management.php?order_id=<?php echo urlencode($order['id']); ?>" 
                                               class="block w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 text-center text-sm pulse-notification">
                                                <i class="fas fa-sync-alt mr-1"></i>Handle Replacements
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
                                            <div class="block w-full bg-green-100 text-green-800 px-4 py-2 rounded-lg text-center text-xs font-bold pulse-notification border-2 border-green-500">
                                                <i class="fas fa-calendar-check mr-1"></i>READY TO SCHEDULE
                                            </div>
                                        <?php endif; ?>
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

        <!-- Quick Actions Panel -->
        <div class="mt-8 bg-gradient-to-r from-primary-500 to-primary-600 rounded-xl shadow-lg p-6 text-white">
            <h3 class="text-lg font-bold mb-4">
                <i class="fas fa-bolt mr-2"></i>Quick Actions
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="warehouse_assignment.php" class="bg-white/20 hover:bg-white/30 rounded-lg p-4 transition-all duration-200 flex items-center space-x-3">
                    <div class="bg-white/20 p-3 rounded-lg">
                        <i class="fas fa-users-cog text-2xl"></i>
                    </div>
                    <div>
                        <div class="font-bold">Manage Assignments</div>
                        <div class="text-sm text-primary-100">Assign orders to employees</div>
                    </div>
                </a>

                <?php if ($unassignedCount > 0): ?>
                <a href="?unassigned=1" class="bg-white/20 hover:bg-white/30 rounded-lg p-4 transition-all duration-200 flex items-center space-x-3 pulse-notification">
                    <div class="bg-red-500 p-3 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                    </div>
                    <div>
                        <div class="font-bold">Unassigned Orders</div>
                        <div class="text-sm text-primary-100"><?php echo $unassignedCount; ?> orders need assignment</div>
                    </div>
                </a>
                <?php endif; ?>

                <a href="order_list.php" class="bg-white/20 hover:bg-white/30 rounded-lg p-4 transition-all duration-200 flex items-center space-x-3">
                    <div class="bg-white/20 p-3 rounded-lg">
                        <i class="fas fa-clipboard-list text-2xl"></i>
                    </div>
                    <div>
                        <div class="font-bold">My Orders</div>
                        <div class="text-sm text-primary-100">View your assigned orders</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>