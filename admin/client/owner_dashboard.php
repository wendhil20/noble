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
      COUNT(DISTINCT CASE WHEN rr.status IN ('pending', 'approved', 'processing', 'In Warehouse') THEN rr.id END) as approved_replacements_count,
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        
        .badge-completed {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    box-shadow: 0 2px 4px rgba(217, 119, 6, 0.4);
    font-weight: 700;
    letter-spacing: 0.05em;
}
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Professional Header -->
    <div class="bg-white border-b-2 border-gray-200 shadow-sm">
        <div class="max-w-[1400px] mx-auto px-6 py-5">
            <div class="flex items-center justify-between">
                <!-- Left: Title and Info -->
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Owner Dashboard</h1>
                    <p class="text-sm text-gray-600 mt-0.5">
                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($fullname); ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar mr-1"></i><?php echo date('l, F j, Y - g:i A'); ?>
                    </p>
                </div>

                <!-- Right: Quick Actions -->
                <div class="flex items-center space-x-3">
                    <button onclick="location.reload()" class="flex items-center justify-center w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        <i class="fas fa-sync-alt text-gray-700"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="max-w-[1400px] mx-auto px-6 py-6">
        
        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <!-- Total Orders -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Orders</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $totalOrders; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Active Orders -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Active Orders</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $activeOrdersCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($totalRevenue, 2); ?></p>
                        <p class="text-xs text-gray-500 mt-1">Avg: ₱<?php echo number_format($avgOrderValue, 2); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-peso-sign text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Issues -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Issues</p>
                        <p class="text-3xl font-bold text-<?php echo ($ordersWithDefects + $ordersWithReplacements) > 0 ? 'red' : 'gray'; ?>-600"><?php echo $ordersWithDefects + $ordersWithReplacements; ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo $ordersWithReplacements; ?> replacements, <?php echo $ordersWithDefects; ?> defects</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4 flex items-center">
                <i class="fas fa-filter w-5 text-blue-600 mr-2"></i>
                Filter Orders
            </h3>
            
            <form method="GET" class="space-y-4">
                <!-- Status Filters -->
                <div>
                    <div class="flex flex-wrap gap-2 mb-4">
                        <a href="?" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo ($status_filter === '' && !$show_active) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                            <i class="fas fa-th mr-2"></i>
                            All Orders
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-bold <?php echo ($status_filter === '' && !$show_active) ? 'bg-white/20' : 'bg-gray-200'; ?>">
                                <?php echo $totalOrders; ?>
                            </span>
                        </a>

                        <a href="?active=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo $warehouse_filter > 0 ? '&warehouse=' . $warehouse_filter : ''; ?>" 
                           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $show_active ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?>">
                            <i class="fas fa-fire mr-2"></i>
                            Active Orders
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $show_active ? 'bg-white/20' : 'bg-green-200'; ?>">
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
                               class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $isActive ? 'bg-' . $config['color'] . '-600 text-white' : 'bg-' . $config['color'] . '-100 text-' . $config['color'] . '-700 hover:bg-' . $config['color'] . '-200'; ?>">
                                <i class="fas <?php echo $config['icon']; ?> mr-2"></i>
                                <?php echo htmlspecialchars($status); ?>
                                <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $isActive ? 'bg-white/20' : 'bg-' . $config['color'] . '-200'; ?>">
                                    <?php echo $count; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Search and Advanced Filters -->
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide mb-2">
                            <i class="fas fa-search mr-1"></i>Search
                        </label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Order ID, Customer, Email..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide mb-2">
                            <i class="fas fa-user-cog mr-1"></i>Warehouse Staff
                        </label>
                        <select name="warehouse" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="0">All Staff</option>
                            <?php foreach ($warehouseStaff as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $warehouse_filter == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide mb-2">
                            <i class="fas fa-calendar-alt mr-1"></i>Date From
                        </label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide mb-2">
                            <i class="fas fa-calendar-check mr-1"></i>Date To
                        </label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <a href="?" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors flex items-center space-x-2 text-sm">
                        <i class="fas fa-redo"></i>
                        <span>Reset</span>
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors flex items-center space-x-2 text-sm">
                        <i class="fas fa-search"></i>
                        <span>Apply Filters</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (!empty($orders)): ?>
            <div class="bg-white rounded-lg border border-gray-200 p-5 mb-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4 flex items-center">
                    <i class="fas fa-list w-5 text-blue-600 mr-2"></i>
                    Orders (<?php echo count($orders); ?>)
                </h3>

                <div class="space-y-2">
                    <?php foreach ($orders as $order):
                        $assignedItems = (int)$order['assigned_count'];
                        $item_count = (int)$order['item_count'];
                        $approved_replacements_count = (int)($order['approved_replacements_count'] ?? 0);
                        $unresolved_defects_count = (int)($order['unresolved_defects_count'] ?? 0);
                        $assignmentPercentage = $item_count > 0 ? round(($assignedItems / $item_count) * 100) : 0;
                        
                        $hasIssues = $approved_replacements_count > 0 || $unresolved_defects_count > 0;
                        
$statusColors = [
    'Pending' => 'bg-yellow-100 text-yellow-800',
    'Ongoing' => 'bg-orange-100 text-orange-800',
    'processing' => 'bg-blue-100 text-blue-800',
    'Ready for Pickup' => 'bg-indigo-100 text-indigo-800',
    'Out for Delivery' => 'bg-purple-100 text-purple-800',
    'Out for Pickup' => 'bg-pink-100 text-pink-800',
    'Delivered' => 'bg-green-100 text-green-800',
    'Picked Up' => 'bg-teal-100 text-teal-800',
    'Completed' => '' // handled separately
];
                        $statusColor = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                        <div class="border <?php echo $hasIssues ? 'border-l-4 border-l-red-500 border-gray-200' : 'border-gray-200'; ?> bg-white rounded-lg p-3 hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between">
                                <!-- Left: Order Info -->
                                <div class="flex items-center space-x-4 flex-1">
                                    <div class="text-center min-w-[80px]">
                                        <div class="text-xs text-gray-500 mb-1">Order</div>
                                        <div class="text-lg font-bold text-gray-900">#<?php echo htmlspecialchars($order['id']); ?></div>
                                    </div>
                                    
                                    <div class="h-10 w-px bg-gray-200"></div>
                                    
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Middle: Status & Badges -->
<div class="flex items-center space-x-2 flex-wrap gap-y-1">
    
    <?php if ($order['status'] === 'Completed'): ?>
        <span class="inline-flex items-center px-3 py-1 rounded text-xs badge-completed">
            COMPLETED
        </span>
    <?php else: ?>
        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold <?php echo $statusColor; ?>">
            <?php echo htmlspecialchars($order['status']); ?>
        </span>
    <?php endif; ?>

<?php if ($approved_replacements_count > 0): ?>
    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-red-600 text-white" 
          title="<?php echo $approved_replacements_count; ?> replacement(s)">
        <i class="fas fa-sync-alt mr-1"></i>
        <?php echo $approved_replacements_count; ?> Replacement<?php echo $approved_replacements_count > 1 ? 's' : ''; ?>
    </span>
<?php endif; ?>

    <!-- Warehouse Replacements -->
    <?php 
    $warehouse_replacements = (int)($order['warehouse_replacements_count'] ?? 0);
    if ($warehouse_replacements > 0): ?>
        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-amber-500 text-white"
              title="<?php echo $warehouse_replacements; ?> replacement(s) in warehouse">
            <i class="fas fa-warehouse mr-1"></i>
            <?php echo $warehouse_replacements; ?> In Warehouse
        </span>
    <?php endif; ?>

    <!-- Defects Badge -->
    <?php if ($unresolved_defects_count > 0): ?>
        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-orange-600 text-white"
              title="<?php echo $unresolved_defects_count; ?> unresolved defect(s)">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <?php echo $unresolved_defects_count; ?> Defect<?php echo $unresolved_defects_count > 1 ? 's' : ''; ?>
        </span>
    <?php endif; ?>

    <span class="text-xs text-gray-500">
        <?php echo $item_count; ?> item<?php echo $item_count > 1 ? 's' : ''; ?>
    </span>

    <span class="text-xs font-semibold <?php echo ($assignmentPercentage === 100) ? 'text-green-600' : (($assignmentPercentage > 0) ? 'text-yellow-600' : 'text-red-600'); ?>">
        <?php echo $assignmentPercentage; ?>%
    </span>
</div>
                                
                                <!-- Right: Amount & Action -->
                                <div class="flex items-center space-x-4 ml-4">
                                    <div class="text-right min-w-[100px]">
                                        <div class="text-xs text-gray-500">Total</div>
                                        <div class="text-lg font-bold text-gray-900">₱<?php echo number_format((float)$order['total'], 2); ?></div>
                                    </div>
                                    
                                    <a href="owner_order_view.php?order_id=<?php echo $order['id']; ?>" 
                                       class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors text-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Orders Found</h3>
                <p class="text-gray-600 mb-4">
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
                    <a href="?" class="inline-flex items-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                        <i class="fas fa-redo"></i>
                        <span>Clear Filters</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>