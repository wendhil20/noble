<?php
// owner_dashboard.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
// ONLY SUPERADMIN CAN ACCESS
require_role(['superadmin']);

// Ensure session exists
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// --- Resolve logged-in user robustly ---
$user_id = null;
$fullname = '';
$user_lvl = '';

$sessionUser = $_SESSION['noble_user'];

if (is_array($sessionUser)) {
    if (isset($sessionUser['id'])) {
        $user_id = (int)$sessionUser['id'];
    } elseif (isset($sessionUser['user_id'])) {
        $user_id = (int)$sessionUser['user_id'];
    }
    $fullname = $sessionUser['fullname'] ?? $sessionUser['name'] ?? '';
    $user_lvl = $sessionUser['lvl'] ?? $sessionUser['level'] ?? '';
}

if (empty($user_id) || empty($user_lvl)) {
    // Try lookup by numeric or email stored in session
    if (!is_array($sessionUser)) {
        if (ctype_digit((string)$sessionUser)) {
            $candidate = (int)$sessionUser;
            $sql = "SELECT id, fullname, lvl FROM nobleaccount WHERE id = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("i", $candidate);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) {
                    $user_id = (int)$r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                }
            }
        } else {
            $candidate = (string)$sessionUser;
            $sql = "SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("s", $candidate);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) {
                    $user_id = (int)$r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                }
            }
        }
    } else {
        // if session array had email
        if (!empty($sessionUser['email'])) {
            $candidate = $sessionUser['email'];
            $sql = "SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("s", $candidate);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) {
                    $user_id = (int)$r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                }
            }
        }
    }
}

// If still unresolved -> logout
if (empty($user_id) || empty($user_lvl)) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php");
    exit();
}

// --- Filters from GET ---
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query  = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from     = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to       = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$warehouse_filter = isset($_GET['warehouse']) ? (int)$_GET['warehouse'] : 0;
$show_active = isset($_GET['active']) ? (bool)$_GET['active'] : false;

// --- Build WHERE conditions ---
$whereParts = [];
$params = [];
$types = '';

// Active orders filter (excludes Delivered and Picked Up)
if ($show_active) {
    $whereParts[] = "o.status NOT IN ('Delivered', 'Picked Up')";
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

if ($warehouse_filter > 0) {
    $whereParts[] = "o.warehouse_employee_id = ?";
    $params[] = $warehouse_filter;
    $types .= 'i';
}

$whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// --- Helper to bind params ---
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

// --- Orders query (ALL orders for owner) ---
$ordersSql = "
    SELECT 
        o.id,
        o.customer_name,
        o.email,
        o.created_at,
        o.status,
        o.total,
        o.warehouse_employee_id,
        na.fullname as warehouse_staff_name,
        COUNT(DISTINCT oi.id) as item_count,
        COUNT(DISTINCT CASE 
            WHEN (oi.supplier_id IS NOT NULL AND oi.supplier_id > 0) 
              OR (oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '') 
            THEN oi.id 
        END) as assigned_count,
        COUNT(DISTINCT poa.id) as po_attachment_count,
        COUNT(DISTINCT CASE WHEN rr.status IN ('approved', 'processing') THEN rr.id END) as approved_replacements_count,
        COUNT(DISTINCT CASE WHEN rr.status = 'In Warehouse' THEN rr.id END) as warehouse_replacements_count,
        COUNT(DISTINCT CASE WHEN dr.status != 'resolved' THEN dr.id END) as unresolved_defects_count,
        COUNT(DISTINCT ds.id) as scheduled_deliveries_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN po_attachments poa ON o.id = poa.order_id
    LEFT JOIN replacement_requests rr ON o.id = rr.order_id
    LEFT JOIN defect_reports dr ON o.id = dr.order_id
    LEFT JOIN delivery_schedules ds ON o.id = ds.order_id
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

// --- Status counts (ALL orders) ---
$statusCounts = [];
$totalOrders = 0;
$activeOrdersCount = 0;

$statusSql = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$res2 = $conn->query($statusSql);
if ($res2) {
    $statusCounts = $res2->fetch_all(MYSQLI_ASSOC);
}

$statusCountsArray = [];
foreach ($statusCounts as $row) {
    $statusCountsArray[$row['status']] = (int)$row['count'];
    $totalOrders += (int)$row['count'];
}

// Count active orders (excluding Delivered and Picked Up)
$activeOrdersSql = "SELECT COUNT(*) as count FROM orders WHERE status NOT IN ('Delivered', 'Picked Up')";
$activeRes = $conn->query($activeOrdersSql);
if ($activeRes) {
    $activeResult = $activeRes->fetch_assoc();
    $activeOrdersCount = (int)$activeResult['count'];
}

// Get warehouse staff list for filter
$warehouseStaffSql = "SELECT id, fullname FROM nobleaccount WHERE lvl = 'warehouse' AND subrole = 'warehouse_staff' ORDER BY fullname";
$warehouseStaff = [];
$warehouseRes = $conn->query($warehouseStaffSql);
if ($warehouseRes) {
    $warehouseStaff = $warehouseRes->fetch_all(MYSQLI_ASSOC);
}

// Calculate statistics
$totalRevenue = 0;
$avgOrderValue = 0;
$ordersWithReplacements = 0;
$ordersWithDefects = 0;

foreach ($orders as $order) {
    $totalRevenue += (float)$order['total'];
    if ((int)$order['approved_replacements_count'] > 0) $ordersWithReplacements++;
    if ((int)$order['unresolved_defects_count'] > 0) $ordersWithDefects++;
}

if (count($orders) > 0) {
    $avgOrderValue = $totalRevenue / count($orders);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Owner Dashboard - P.O System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fef3c7',
                            100: '#fde68a',
                            200: '#fcd34d',
                            300: '#fbbf24',
                            400: '#f59e0b',
                            500: '#d97706',
                            600: '#b45309',
                            700: '#92400e',
                            800: '#78350f',
                            900: '#451a03',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .metric-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .status-badge {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse-notification {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }

        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-gradient-to-r from-amber-600 via-orange-600 to-yellow-600 text-white shadow-2xl">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="bg-white bg-opacity-20 p-4 rounded-2xl backdrop-blur-lg">
                        <i class="fas fa-crown text-3xl"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold">Owner Dashboard</h1>
                        <p class="text-white text-opacity-90 mt-2 text-lg">Complete Overview of All Orders & Operations</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-white text-opacity-90">Welcome back,</div>
                    <div class="text-xl font-bold"><?php echo htmlspecialchars($fullname); ?></div>
                    <div class="text-xs text-white text-opacity-75 mt-1">
                        <i class="fas fa-clock mr-1"></i><?php echo date('l, F j, Y - g:i A'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Key Metrics Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Orders -->
            <div class="metric-card rounded-2xl p-6 border-2 border-blue-200 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-4 rounded-xl">
                        <i class="fas fa-shopping-cart text-white text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-4xl font-bold text-blue-600"><?php echo $totalOrders; ?></div>
                        <div class="text-sm text-gray-600 mt-1">Total Orders</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500">
                    <i class="fas fa-chart-line mr-1"></i>All time orders
                </div>
            </div>

            <!-- Active Orders -->
            <div class="metric-card rounded-2xl p-6 border-2 border-green-200 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-gradient-to-br from-green-500 to-green-600 p-4 rounded-xl">
                        <i class="fas fa-tasks text-white text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-4xl font-bold text-green-600"><?php echo $activeOrdersCount; ?></div>
                        <div class="text-sm text-gray-600 mt-1">Active Orders</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>Excluding delivered/picked up
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="metric-card rounded-2xl p-6 border-2 border-purple-200 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-4 rounded-xl">
                        <i class="fas fa-peso-sign text-white text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-purple-600">₱<?php echo number_format($totalRevenue, 2); ?></div>
                        <div class="text-sm text-gray-600 mt-1">Total Revenue</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500">
                    <i class="fas fa-calculator mr-1"></i>Avg: ₱<?php echo number_format($avgOrderValue, 2); ?>
                </div>
            </div>

            <!-- Issues -->
            <div class="metric-card rounded-2xl p-6 border-2 border-red-200 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-gradient-to-br from-red-500 to-red-600 p-4 rounded-xl">
                        <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-4xl font-bold text-red-600"><?php echo $ordersWithDefects + $ordersWithReplacements; ?></div>
                        <div class="text-sm text-gray-600 mt-1">Issues</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500">
                    <i class="fas fa-sync-alt mr-1"></i><?php echo $ordersWithReplacements; ?> replacements, <?php echo $ordersWithDefects; ?> defects
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-200 p-6 mb-8">
            <form method="GET" class="space-y-6">
                <!-- Status Filters -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-filter mr-2 text-primary-600"></i>Filter Orders
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="?" class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2 <?php echo ($status_filter === '' && !$show_active) ? 'bg-primary-600 text-white shadow-lg scale-105' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                            <i class="fas fa-th"></i>
                            <span>All Orders</span>
                            <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo ($status_filter === '' && !$show_active) ? 'bg-white/20' : 'bg-gray-200'; ?>">
                                <?php echo $totalOrders; ?>
                            </span>
                        </a>

                        <a href="?active=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo $warehouse_filter > 0 ? '&warehouse=' . $warehouse_filter : ''; ?>" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2 <?php echo $show_active ? 'bg-green-600 text-white shadow-lg scale-105' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?>">
                            <i class="fas fa-fire"></i>
                            <span>Active Orders</span>
                            <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $show_active ? 'bg-white/20' : 'bg-green-200'; ?>">
                                <?php echo $activeOrdersCount; ?>
                            </span>
                        </a>

                        <?php 
                        $statusOrder = ['Pending', 'Ongoing', 'processing', 'Ready for Pickup', 'Out for Delivery', 'Out for Pickup', 'Delivered', 'Picked Up'];
                        $statusConfig = [
                            'Pending' => ['icon' => 'fa-clock', 'color' => 'yellow'],
                            'Ongoing' => ['icon' => 'fa-tasks', 'color' => 'orange'],
                            'processing' => ['icon' => 'fa-cog', 'color' => 'blue'],
                            'Ready for Pickup' => ['icon' => 'fa-box', 'color' => 'indigo'],
                            'Out for Delivery' => ['icon' => 'fa-truck', 'color' => 'purple'],
                            'Out for Pickup' => ['icon' => 'fa-hand-holding', 'color' => 'pink'],
                            'Delivered' => ['icon' => 'fa-check-circle', 'color' => 'green'],
                            'Picked Up' => ['icon' => 'fa-check-double', 'color' => 'teal']
                        ];
                        
                        foreach ($statusOrder as $status):
                            if (!isset($statusCountsArray[$status])) continue;
                            $count = $statusCountsArray[$status];
                            $config = $statusConfig[$status] ?? ['icon' => 'fa-circle', 'color' => 'gray'];
                            $isActive = ($status_filter === $status && !$show_active);
                        ?>
                            <a href="?status=<?php echo urlencode($status); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo $warehouse_filter > 0 ? '&warehouse=' . $warehouse_filter : ''; ?>"
                               class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2 <?php echo $isActive ? 'bg-' . $config['color'] . '-600 text-white shadow-lg scale-105' : 'bg-' . $config['color'] . '-100 text-' . $config['color'] . '-700 hover:bg-' . $config['color'] . '-200'; ?>">
                                <i class="fas <?php echo $config['icon']; ?>"></i>
                                <span><?php echo htmlspecialchars($status); ?></span>
                                <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $isActive ? 'bg-white/20' : 'bg-' . $config['color'] . '-200'; ?>">
                                    <?php echo $count; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Search and Advanced Filters -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search mr-1"></i>Search Orders
                        </label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Order ID, Customer, Email..." 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-cog mr-1"></i>Warehouse Staff
                        </label>
                        <select name="warehouse" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="0">All Staff</option>
                            <?php foreach ($warehouseStaff as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $warehouse_filter == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-1"></i>Date From
                        </label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-check mr-1"></i>Date To
                        </label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="?" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg transition-all duration-200 flex items-center space-x-2">
                        <i class="fas fa-redo"></i>
                        <span>Reset Filters</span>
                    </a>
                    <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white px-8 py-3 rounded-lg transition-all duration-200 flex items-center space-x-2 shadow-lg hover:shadow-xl">
                        <i class="fas fa-search"></i>
                        <span>Apply Filters</span>
                    </button>
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
                    $approved_replacements_count = (int)($order['approved_replacements_count'] ?? 0);
                    $unresolved_defects_count = (int)($order['unresolved_defects_count'] ?? 0);
                    $scheduled_deliveries = (int)($order['scheduled_deliveries_count'] ?? 0);
                    $assignmentPercentage = $item_count > 0 ? round(($assignedItems / $item_count) * 100) : 0;
                    
                    $hasIssues = $approved_replacements_count > 0 || $unresolved_defects_count > 0;
                ?>
                    <div class="bg-white rounded-xl shadow-lg border-2 border-gray-200 hover:shadow-2xl transition-all duration-300 <?php echo $hasIssues ? 'border-l-8 border-l-red-500' : ''; ?>">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4 flex-1">
                                    <div class="bg-gradient-to-br from-primary-500 to-primary-600 p-3 rounded-xl relative">
                                        <i class="fas fa-receipt text-white text-xl"></i>
                                        <?php if ($hasIssues): ?>
                                            <span class="absolute -top-2 -right-2 h-6 w-6 bg-red-500 border-2 border-white rounded-full flex items-center justify-center pulse-notification shadow-lg">
                                                <i class="fas fa-exclamation text-white text-xs"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center flex-wrap gap-2 mb-2">
                                            <h3 class="text-xl font-bold text-gray-900">Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                                            <?php if ($approved_replacements_count > 0): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 pulse-notification">
                                                    <i class="fas fa-sync-alt mr-1"></i>
                                                    <?php echo $approved_replacements_count; ?> Replacement<?php echo $approved_replacements_count > 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($unresolved_defects_count > 0): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-800 pulse-notification">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    <?php echo $unresolved_defects_count; ?> Defect<?php echo $unresolved_defects_count > 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($scheduled_deliveries > 0): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                                    <i class="fas fa-calendar-check mr-1"></i>Scheduled
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-600 space-y-1 mb-3">
                                            <div class="flex items-center">
                                                <i class="fas fa-user mr-2 w-4"></i>
                                                <span class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-envelope mr-2 w-4"></i>
                                                <span><?php echo htmlspecialchars($order['email']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar mr-2 w-4"></i>
                                                <span><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                                            </div>
                                            <?php if (!empty($order['warehouse_staff_name'])): ?>
                                                <div class="flex items-center">
                                                    <i class="fas fa-user-cog mr-2 w-4"></i>
                                                    <span class="text-blue-600 font-medium">Assigned to: <?php echo htmlspecialchars($order['warehouse_staff_name']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Assignment Progress -->
                                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-xs font-medium text-gray-700">
                                                    <i class="fas fa-clipboard-check mr-1"></i>Supplier Assignment
                                                </span>
                                                <span class="text-xs font-bold <?php echo ($assignmentPercentage === 100) ? 'text-green-600' : (($assignmentPercentage > 0) ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                    <?php echo $assignmentPercentage; ?>%
                                                </span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="h-2 rounded-full <?php echo ($assignmentPercentage === 100) ? 'bg-green-500' : (($assignmentPercentage > 0) ? 'bg-yellow-500' : 'bg-red-500'); ?> transition-all duration-500" 
                                                     style="width: <?php echo $assignmentPercentage; ?>%"></div>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500">
                                                <?php echo $assignedItems; ?>/<?php echo $item_count; ?> items assigned
                                                <?php if ($po_attachment_count > 0): ?>
                                                    <span class="ml-2 text-green-600">
                                                        <i class="fas fa-file-excel"></i> <?php echo $po_attachment_count; ?> P.O.
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right space-y-3 ml-4">
                                    <div>
                                        <div class="text-2xl font-bold text-primary-700">₱<?php echo number_format((float)$order['total'], 2); ?></div>
                                        <div class="flex items-center justify-end space-x-2 mt-2">
                                            <?php
                                            $statusColors = [
                                                'Pending' => 'bg-yellow-100 text-yellow-800',
                                                'Ongoing' => 'bg-orange-100 text-orange-800',
                                                'processing' => 'bg-blue-100 text-blue-800',
                                                'Ready for Pickup' => 'bg-indigo-100 text-indigo-800',
                                                'Out for Delivery' => 'bg-purple-100 text-purple-800',
                                                'Out for Pickup' => 'bg-pink-100 text-pink-800',
                                                'Delivered' => 'bg-green-100 text-green-800',
                                                'Picked Up' => 'bg-teal-100 text-teal-800'
                                            ];
                                            $statusColor = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?php echo $statusColor; ?>">
                                                <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                            </span>
                                            <span class="text-sm text-gray-600"><?php echo $item_count; ?> items</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col space-y-2">
                                        <a href="owner_order_view.php?order_id=<?php echo $order['id']; ?>" 
                                           class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-2 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 text-sm shadow-md hover:shadow-lg">
                                            <i class="fas fa-eye"></i>
                                            <span>View Details</span>
                                        </a>
                                        
                                        <?php if ($unresolved_defects_count > 0): ?>
                                            <a href="view_defects.php?order_id=<?php echo $order['id']; ?>" 
                                               class="bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white px-4 py-2 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 text-sm shadow-md hover:shadow-lg pulse-notification">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span>View Defects</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Info -->
            <div class="mt-8 bg-white rounded-xl shadow-lg border-2 border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div class="text-gray-600">
                        <i class="fas fa-info-circle mr-2"></i>
                        Showing <strong><?php echo count($orders); ?></strong> order(s)
                    </div>
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        Last updated: <?php echo date('g:i A'); ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-200 p-12 text-center">
                <div class="bg-gradient-to-br from-gray-100 to-gray-200 rounded-full w-32 h-32 flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-inbox text-gray-400 text-6xl"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mb-4">No Orders Found</h3>
                <p class="text-gray-600 text-lg mb-6">
                    <?php if ($show_active): ?>
                        There are no active orders matching your filters.
                    <?php elseif ($status_filter): ?>
                        No orders found with status "<?php echo htmlspecialchars($status_filter); ?>".
                    <?php elseif ($search_query): ?>
                        No orders found matching your search "<?php echo htmlspecialchars($search_query); ?>".
                    <?php else: ?>
                        No orders have been placed yet.
                    <?php endif; ?>
                </p>
                <?php if ($status_filter || $search_query || $date_from || $date_to || $warehouse_filter || $show_active): ?>
                    <a href="?" class="inline-flex items-center space-x-2 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white px-8 py-4 rounded-xl font-bold transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-redo"></i>
                        <span>Clear Filters & View All Orders</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Stats Footer -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-blue-100 text-sm font-medium mb-1">Average Processing Time</div>
                        <div class="text-3xl font-bold">~3-5 days</div>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                        <i class="fas fa-clock text-3xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-green-100 text-sm font-medium mb-1">Customer Satisfaction</div>
                        <div class="text-3xl font-bold">
                            <?php 
                            $satisfactionRate = $totalOrders > 0 ? round((($totalOrders - $ordersWithDefects - $ordersWithReplacements) / $totalOrders) * 100, 1) : 100;
                            echo $satisfactionRate; ?>%
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                        <i class="fas fa-smile text-3xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-purple-100 text-sm font-medium mb-1">On-Time Delivery Rate</div>
                        <div class="text-3xl font-bold">92%</div>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-xl">
                        <i class="fas fa-shipping-fast text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Refresh Button -->
    <div class="fixed bottom-8 right-8 z-50">
        <button onclick="location.reload()" 
                class="bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white p-4 rounded-full shadow-2xl hover:shadow-3xl transition-all duration-200 hover:scale-110 flex items-center space-x-3">
            <i class="fas fa-sync-alt text-xl"></i>
            <span class="font-bold pr-2">Refresh</span>
        </button>
    </div>

    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);

        // Smooth scroll animations
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading state to buttons
        document.querySelectorAll('a[href*="order_tracking"], a[href*="view_po_files"], a[href*="view_defects"]').forEach(link => {
            link.addEventListener('click', function() {
                const icon = this.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-spinner fa-spin';
                }
            });
        });
    </script>
</body>

</html>