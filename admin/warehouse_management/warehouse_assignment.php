<?php
//warehouse_assignment.php - For managers to assign warehouse employees to orders
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin', 'warehouse']); // Only managers can access

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// ADD HEAD ACCESS CHECK HERE - LOCATION 1
// --- Resolve logged-in user and check head status ---
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

if (empty($user_id) || empty($user_lvl)) {
    // Try lookup by numeric or email stored in session
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
        } else {
            $candidate = (string)$sessionUser;
            $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE email = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("s", $candidate);
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
    } else {
        // if session array had email
        if (!empty($sessionUser['email'])) {
            $candidate = $sessionUser['email'];
            $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE email = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("s", $candidate);
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
        } elseif (!empty($sessionUser['id'])) {
            $candidate = (int)$sessionUser['id'];
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

// If still unresolved -> logout
if (empty($user_id) || empty($user_lvl)) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php");
    exit();
}

// REDIRECT NON-HEAD USERS - LOCATION 2
if (!$is_head) {
    header("Location: order_list.php");
    exit();
}

// Handle warehouse assignment via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'assign_warehouse') {
    header('Content-Type: application/json');
    
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $warehouse_employee_id = isset($_POST['warehouse_employee_id']) ? intval($_POST['warehouse_employee_id']) : null;
    
    if ($order_id > 0) {
        // If warehouse_employee_id is 0, set to NULL for unassignment
        if ($warehouse_employee_id === 0) {
            $warehouse_employee_id = null;
        }
        
        $updateQuery = "UPDATE orders SET warehouse_employee_id = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ii", $warehouse_employee_id, $order_id);
        
        if ($stmt->execute()) {
            $message = $warehouse_employee_id ? 'Warehouse employee assigned successfully' : 'Order unassigned successfully';
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update assignment']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    }
    exit();
}

// Handle bulk unassign action
if (isset($_POST['action']) && $_POST['action'] === 'bulk_unassign') {
    header('Content-Type: application/json');
    
    $order_ids = isset($_POST['order_ids']) ? json_decode($_POST['order_ids'], true) : [];
    
    if (!empty($order_ids) && is_array($order_ids)) {
        $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
        $updateQuery = "UPDATE orders SET warehouse_employee_id = NULL WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => count($order_ids) . ' orders unassigned successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to unassign orders']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'No orders selected']);
    }
    exit();
}

// Handle bulk assign action
if (isset($_POST['action']) && $_POST['action'] === 'bulk_assign') {
    header('Content-Type: application/json');
    
    $order_ids = isset($_POST['order_ids']) ? json_decode($_POST['order_ids'], true) : [];
    $warehouse_employee_id = isset($_POST['warehouse_employee_id']) ? intval($_POST['warehouse_employee_id']) : 0;
    
    if (!empty($order_ids) && is_array($order_ids) && $warehouse_employee_id > 0) {
        $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
        $params = array_merge([$warehouse_employee_id], $order_ids);
        $updateQuery = "UPDATE orders SET warehouse_employee_id = ? WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => count($order_ids) . ' orders assigned successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to assign orders']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$warehouse_filter = isset($_GET['warehouse']) ? $_GET['warehouse'] : '';
$view_assigned = isset($_GET['view_assigned']) ? $_GET['view_assigned'] : '';

// Build the query
$whereConditions = [];
$params = [];
$types = '';

// MODIFIED: Show unassigned orders by default, or assigned orders to specific staff when view_assigned is set
if (!empty($view_assigned)) {
    $whereConditions[] = "o.status = 'ongoing' AND o.warehouse_employee_id = ?";
    $params[] = $view_assigned;
    $types .= 'i';
} else {
    $whereConditions[] = "o.status = 'ongoing' AND o.warehouse_employee_id IS NULL";
}

if (!empty($search_query)) {
    $whereConditions[] = "(o.customer_name LIKE ? OR o.email LIKE ? OR o.id LIKE ?)";
    $searchParam = "%$search_query%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get orders with item counts and warehouse employee info
$ordersQuery = "
    SELECT 
        o.id,
        o.customer_name,
        o.email,
        o.created_at,
        o.status,
        o.total,
        o.warehouse_employee_id,
        wh.fullname as warehouse_employee_name,
        wh.email as warehouse_employee_email,
        COUNT(oi.id) as item_count,
        SUM(CASE WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN 1 ELSE 0 END) as assigned_linked_count,
        SUM(CASE WHEN oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL THEN 1 ELSE 0 END) as assigned_manual_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN nobleaccount wh ON o.warehouse_employee_id = wh.id 
    AND wh.lvl = 'warehouse' 
    AND wh.is_head = 0 
    AND wh.subrole = 'warehouse_staff'
    $whereClause
    GROUP BY o.id, o.customer_name, o.email, o.created_at, o.status, o.total, o.warehouse_employee_id, wh.fullname, wh.email
    ORDER BY 
        CASE WHEN o.warehouse_employee_id IS NULL THEN 0 ELSE 1 END,
        o.created_at DESC
";

$stmt = $conn->prepare($ordersQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get status counts only for ongoing orders
$statusCountsQuery = "
    SELECT 
        'ongoing' as status,
        COUNT(*) as count
    FROM orders 
    WHERE status = 'ongoing'
";
$statusCounts = $conn->query($statusCountsQuery)->fetch_all(MYSQLI_ASSOC);
$statusCountsArray = [];
$totalOngoingOrders = 0;
foreach ($statusCounts as $status) {
    $statusCountsArray[$status['status']] = $status['count'];
    $totalOngoingOrders += $status['count'];
}

// Get warehouse employees for assignment dropdown (exclude heads, only show warehouse_staff)
$warehouseEmployeesQuery = "
    SELECT id, fullname, email 
    FROM nobleaccount 
    WHERE lvl = 'warehouse' 
    AND status = 'active' 
    AND is_head = 0 
    AND subrole = 'warehouse_staff'
    ORDER BY fullname ASC
";
$warehouseEmployees = $conn->query($warehouseEmployeesQuery)->fetch_all(MYSQLI_ASSOC);

// Get warehouse assignment counts only for ongoing orders (exclude heads, only show warehouse_staff)
$warehouseCountsQuery = "
    SELECT 
        wh.id,
        wh.fullname,
        COUNT(o.id) as order_count
    FROM nobleaccount wh
    LEFT JOIN orders o ON wh.id = o.warehouse_employee_id AND o.status = 'ongoing'
    WHERE wh.lvl = 'warehouse' 
    AND wh.status = 'active' 
    AND wh.is_head = 0 
    AND wh.subrole = 'warehouse_staff'
    GROUP BY wh.id, wh.fullname
    ORDER BY wh.fullname ASC
";
$warehouseCounts = $conn->query($warehouseCountsQuery)->fetch_all(MYSQLI_ASSOC);

// Get unassigned orders count only for ongoing status
$unassignedCountQuery = "SELECT COUNT(*) as count FROM orders WHERE warehouse_employee_id IS NULL AND status = 'ongoing'";
$unassignedCount = $conn->query($unassignedCountQuery)->fetch_assoc()['count'];

// Count unassigned orders in current filter
$unassignedInFilter = array_filter($orders, function($order) {
    return is_null($order['warehouse_employee_id']);
});
$unassignedInFilterCount = count($unassignedInFilter);

// Get current staff name if viewing assigned orders
$currentStaffName = '';
if (!empty($view_assigned)) {
    foreach ($warehouseEmployees as $employee) {
        if ($employee['id'] == $view_assigned) {
            $currentStaffName = $employee['fullname'];
            break;
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Assignment - P.O System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74',
                            400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c',
                            800: '#9a3412', 900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    <!-- Header -->
<div class="bg-transparent">
    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-4">
                <div class="bg-blue-500 p-3 rounded-lg">
                    <i class="fas fa-users-cog text-white text-2xl"></i>
                </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Warehouse Assignment</h1>
                        <p class="text-gray-600 mt-1">
                            <?php if (!empty($view_assigned)): ?>
                                Orders assigned to <?php echo htmlspecialchars($currentStaffName); ?>
                            <?php else: ?>
                                Assign warehouse employees to ongoing orders
                            <?php endif; ?>
                            <!-- ADD HEAD INDICATOR - LOCATION 3 (OPTIONAL) -->
                            <span class="inline-flex items-center ml-3 px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                <i class="fas fa-crown mr-1"></i>Head Access Only
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="bg-primary-50 px-4 py-2 rounded-lg">
                        <span class="text-primary-700 font-medium"><?php echo count($orders); ?> Orders</span>
                    </div>
                    <?php if (!empty($view_assigned)): ?>
                    <a href="warehouse_assignment.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Unassigned
                    </a>
                    <?php endif; ?>
                    <a href="warehouse_head_dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-list mr-2"></i>Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
<div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Assignment Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 <?php echo $unassignedCount > 0 ? 'ring-2 ring-red-400' : ''; ?>">
                <div class="flex items-center">
                    <div class="bg-red-100 p-3 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Unassigned</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $unassignedCount; ?></p>
                        <?php if ($unassignedCount > 0): ?>
                        <p class="text-xs text-red-500 mt-1">
                            <i class="fas fa-clock mr-1"></i>Needs attention
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Ongoing Orders</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $totalOngoingOrders; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Warehouse Staff</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo count($warehouseEmployees); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Assigned</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $totalOngoingOrders - $unassignedCount; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODIFIED: Staff Assignment Overview with clickable staff cards -->
        <?php if (empty($view_assigned)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-2 rounded-lg">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-bold text-gray-900">Warehouse Staff Overview</h3>
                        <p class="text-gray-600">Click on staff members to view their assigned orders</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                        <span class="text-sm text-gray-700">Unassigned: <?php echo $unassignedCount; ?></span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-sm text-gray-700">Total Assigned: <?php echo $totalOngoingOrders - $unassignedCount; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Staff Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <!-- Unassigned Orders Card -->
                <div class="bg-red-50 border-2 border-red-200 rounded-lg p-4 hover:shadow-md transition-all duration-200 cursor-pointer <?php echo empty($view_assigned) ? 'ring-2 ring-red-400' : ''; ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="bg-red-200 p-2 rounded-lg">
                                <i class="fas fa-exclamation-triangle text-red-700 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="font-bold text-red-900">Unassigned</h4>
                                <p class="text-sm text-red-700"><?php echo $unassignedCount; ?> orders</p>
                            </div>
                        </div>
                        <div class="text-red-600">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <?php if ($unassignedCount > 0): ?>
                    <div class="mt-3 text-xs text-red-600 font-medium">
                        <i class="fas fa-clock mr-1"></i>Requires immediate attention
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Staff Cards -->
                <?php foreach ($warehouseCounts as $staff): ?>
                <a href="?view_assigned=<?php echo $staff['id']; ?>" 
                   class="bg-white border-2 border-gray-200 rounded-lg p-4 hover:shadow-md hover:border-blue-300 transition-all duration-200 cursor-pointer block">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="bg-<?php echo $staff['order_count'] > 0 ? 'blue' : 'gray'; ?>-100 p-2 rounded-lg">
                                <i class="fas fa-user text-<?php echo $staff['order_count'] > 0 ? 'blue' : 'gray'; ?>-600 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="font-bold text-gray-900 truncate" title="<?php echo htmlspecialchars($staff['fullname']); ?>">
                                    <?php echo htmlspecialchars(strlen($staff['fullname']) > 15 ? substr($staff['fullname'], 0, 15) . '...' : $staff['fullname']); ?>
                                </h4>
                                <p class="text-sm text-gray-600"><?php echo $staff['order_count']; ?> orders</p>
                            </div>
                        </div>
                        <div class="text-blue-600">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    <?php if ($staff['order_count'] > 0): ?>
                    <div class="mt-3">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                 style="width: <?php echo min(100, ($staff['order_count'] / max($totalOngoingOrders, 1)) * 100); ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            <?php echo round(($staff['order_count'] / max($totalOngoingOrders, 1)) * 100, 1); ?>% of total orders
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mt-3 text-xs text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>Available for assignments
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bulk Actions for Unassigned Orders -->
        <?php if ($unassignedInFilterCount > 0 && empty($view_assigned)): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="bg-red-100 p-2 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-bold text-red-800">Unassigned Orders Alert</h3>
                        <p class="text-red-600">
                            <?php echo $unassignedInFilterCount; ?> ongoing order<?php echo $unassignedInFilterCount !== 1 ? 's' : ''; ?> 
                            in current view require<?php echo $unassignedInFilterCount === 1 ? 's' : ''; ?> warehouse assignment
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="selectAllUnassigned()" 
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-check-square mr-2"></i>Select All Unassigned
                    </button>
                    <div class="flex items-center space-x-2">
                        <select id="bulkWarehouseSelect" 
                                class="px-3 py-2 border border-red-300 rounded-lg text-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                            <option value="">Select Staff...</option>
                            <?php foreach ($warehouseEmployees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['fullname']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="bulkAssignSelected()" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                            <i class="fas fa-user-plus mr-2"></i>Bulk Assign
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <!-- Status Filter - MODIFIED: Show current view status -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <?php if (!empty($view_assigned)): ?>
                    <div class="px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white">
                        Orders Assigned to <?php echo htmlspecialchars($currentStaffName); ?> (<?php echo count($orders); ?>)
                    </div>
                    <input type="hidden" name="view_assigned" value="<?php echo htmlspecialchars($view_assigned); ?>">
                    <?php else: ?>
                    <div class="px-4 py-2 rounded-lg text-sm font-medium bg-primary-600 text-white">
                        Unassigned Ongoing Orders (<?php echo $unassignedCount; ?>)
                    </div>
                    <div class="px-3 py-2 rounded-lg text-xs text-gray-600 bg-gray-50">
                        <i class="fas fa-info-circle mr-1"></i>
                        Orders disappear once assigned to warehouse staff
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Search and Date Filters -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Order ID, Customer, Email..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Bar (shown when orders are selected) -->
        <div id="bulkActionsBar" class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 hidden">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    <span id="selectedCount" class="text-blue-800 font-medium">0 orders selected</span>
                </div>
                <div class="flex items-center space-x-3">
                    <select id="bulkActionWarehouseSelect" 
                            class="px-3 py-2 border border-blue-300 rounded-lg text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Warehouse Staff...</option>
                        <?php foreach ($warehouseEmployees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>">
                            <?php echo htmlspecialchars($employee['fullname']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="bulkAssignSelected()" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-user-plus mr-2"></i>Assign Selected
                    </button>
                    <button onclick="bulkUnassignSelected()" 
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-user-times mr-2"></i>Unassign Selected
                    </button>
                    <button onclick="clearSelection()" 
                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-times mr-2"></i>Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (!empty($orders)): ?>
        <div class="space-y-4">
            <?php foreach ($orders as $order): ?>
            <?php
            $assignedItems = $order['assigned_linked_count'] + $order['assigned_manual_count'];
            $assignmentPercentage = $order['item_count'] > 0 ? round(($assignedItems / $order['item_count']) * 100) : 0;
            $isUnassigned = is_null($order['warehouse_employee_id']);
            ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200 <?php echo $isUnassigned ? 'ring-2 ring-red-200 bg-red-50' : 'ring-1 ring-blue-200 bg-blue-50'; ?>">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <!-- Checkbox for bulk actions -->
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       class="order-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                       data-order-id="<?php echo $order['id']; ?>"
                                       data-is-unassigned="<?php echo $isUnassigned ? '1' : '0'; ?>"
                                       onchange="updateBulkActions()">
                            </div>
                            
                            <!-- Order Info -->
                            <div class="flex items-start space-x-4">
                                <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-2 rounded-lg">
                                    <i class="fas fa-receipt text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-1 <?php echo $isUnassigned ? 'text-red-800' : 'text-blue-800'; ?>">
                                        Order #<?php echo $order['id']; ?>
                                        <?php if ($isUnassigned): ?>
                                        <span class="inline-flex items-center ml-2 px-2 py-1 rounded-full text-xs font-medium bg-red-600 text-white">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>UNASSIGNED
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center ml-2 px-2 py-1 rounded-full text-xs font-medium bg-blue-600 text-white">
                                            <i class="fas fa-check mr-1"></i>ASSIGNED
                                        </span>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-user mr-2"></i>
                                            <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-envelope mr-2"></i>
                                            <span><?php echo htmlspecialchars($order['email']); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar mr-2"></i>
                                            <span><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                                        </div>
                                        <!-- Current Assignment Display -->
                                        <div class="flex items-center">
                                            <i class="fas fa-warehouse mr-2"></i>
                                            <?php if ($order['warehouse_employee_id']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-1"></i>
                                                    <?php echo htmlspecialchars($order['warehouse_employee_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    Unassigned
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Stats -->
                        <div class="text-right space-y-2">
                            <div class="text-lg font-bold text-primary-700">
                                ₱<?php echo number_format($order['total'], 2); ?>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Ongoing
                                </span>
                                <span class="text-sm text-gray-600">
                                    <?php echo $order['item_count']; ?> items
                                </span>
                            </div>
                        </div>

                        <!-- Assignment Actions -->
                        <div class="flex flex-col items-end space-y-3 ml-4">
                            <!-- Warehouse Assignment Dropdown -->
                            <div class="w-64">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Assign Warehouse Staff</label>
                                <select onchange="assignWarehouse(<?php echo $order['id']; ?>, this.value)" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 <?php echo $order['warehouse_employee_id'] ? 'bg-green-50 border-green-300' : 'bg-red-50 border-red-300'; ?>">
                                    <option value="">Select Warehouse Staff...</option>
                                    <?php foreach ($warehouseEmployees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" 
                                            <?php echo ($order['warehouse_employee_id'] == $employee['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['fullname']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Priority Actions -->
                            <div class="flex items-center space-x-2">
                                <?php if (!$order['warehouse_employee_id']): ?>
                                <span class="text-xs text-red-600 font-medium">
                                    <i class="fas fa-exclamation-circle mr-1"></i>Requires Assignment
                                </span>
                                <button onclick="quickAssign(<?php echo $order['id']; ?>)" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md text-xs font-medium transition-colors duration-200">
                                    <i class="fas fa-bolt mr-1"></i>Quick Assign
                                </button>
                                <?php else: ?>
                                <span class="text-xs text-green-600 font-medium">
                                    <i class="fas fa-check-circle mr-1"></i>Assigned
                                </span>
                                <button onclick="unassignOrder(<?php echo $order['id']; ?>)" 
                                        class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded-md text-xs font-medium transition-colors duration-200">
                                    <i class="fas fa-user-times mr-1"></i>Unassign
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <div class="text-gray-500">
                <i class="fas fa-inbox text-6xl mb-4"></i>
                <h3 class="text-lg font-medium mb-2">No Orders Found</h3>
                <p class="text-sm">
                    <?php if (!empty($view_assigned)): ?>
                        No orders are currently assigned to <?php echo htmlspecialchars($currentStaffName); ?>.
                    <?php elseif (!empty($search_query) || !empty($date_from) || !empty($date_to)): ?>
                        No unassigned ongoing orders match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        Great! All ongoing orders have been assigned to warehouse staff.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search_query) || !empty($date_from) || !empty($date_to)): ?>
                <a href="warehouse_assignment.php<?php echo !empty($view_assigned) ? '?view_assigned=' . $view_assigned : ''; ?>" 
                   class="inline-block mt-4 text-primary-600 hover:text-primary-700 font-medium">
                    Clear filters and view all orders
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Assign Modal -->
    <div id="quickAssignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">Quick Assign Order</h3>
                <button onclick="closeQuickAssignModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-3">Select a warehouse staff member to assign to Order #<span id="quickAssignOrderId"></span></p>
                <select id="quickAssignSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select Warehouse Staff...</option>
                    <?php foreach ($warehouseEmployees as $employee): ?>
                    <option value="<?php echo $employee['id']; ?>">
                        <?php echo htmlspecialchars($employee['fullname']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex space-x-3">
                <button onclick="executeQuickAssign()" 
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                    <i class="fas fa-check mr-2"></i>Assign
                </button>
                <button onclick="closeQuickAssignModal()" 
                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="messageContainer" class="fixed top-4 right-4 z-50"></div>

    <script>
        let selectedOrders = [];
        let quickAssignOrderId = null;

        function assignWarehouse(orderId, warehouseEmployeeId) {
            // Show loading state
            const select = event.target;
            const originalValue = select.value;
            select.disabled = true;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=assign_warehouse&order_id=${orderId}&warehouse_employee_id=${warehouseEmployeeId || ''}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    // Reload page after a short delay to show updated assignments
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage(data.message, 'error');
                    // Revert select value on error
                    select.value = originalValue;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred while assigning warehouse employee', 'error');
                select.value = originalValue;
            })
            .finally(() => {
                select.disabled = false;
            });
        }

        function unassignOrder(orderId) {
            if (confirm('Are you sure you want to unassign this order?')) {
                assignWarehouse(orderId, '');
            }
        }

        function quickAssign(orderId) {
            quickAssignOrderId = orderId;
            document.getElementById('quickAssignOrderId').textContent = orderId;
            document.getElementById('quickAssignModal').classList.remove('hidden');
            document.getElementById('quickAssignModal').classList.add('flex');
        }

        function closeQuickAssignModal() {
            document.getElementById('quickAssignModal').classList.add('hidden');
            document.getElementById('quickAssignModal').classList.remove('flex');
            document.getElementById('quickAssignSelect').value = '';
            quickAssignOrderId = null;
        }

        function executeQuickAssign() {
            const warehouseEmployeeId = document.getElementById('quickAssignSelect').value;
            if (!warehouseEmployeeId) {
                showMessage('Please select a warehouse staff member', 'error');
                return;
            }
            
            assignWarehouse(quickAssignOrderId, warehouseEmployeeId);
            closeQuickAssignModal();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            selectedOrders = Array.from(checkboxes).map(cb => parseInt(cb.dataset.orderId));
            
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedOrders.length > 0) {
                bulkActionsBar.classList.remove('hidden');
                selectedCount.textContent = `${selectedOrders.length} order${selectedOrders.length !== 1 ? 's' : ''} selected`;
            } else {
                bulkActionsBar.classList.add('hidden');
            }
        }

        function clearSelection() {
            document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
            updateBulkActions();
        }

        function selectAllUnassigned() {
            document.querySelectorAll('.order-checkbox[data-is-unassigned="1"]').forEach(cb => cb.checked = true);
            updateBulkActions();
        }

        function bulkAssignSelected() {
            const warehouseEmployeeId = document.getElementById('bulkActionWarehouseSelect').value || document.getElementById('bulkWarehouseSelect').value;
            
            if (selectedOrders.length === 0) {
                showMessage('No orders selected', 'error');
                return;
            }
            
            if (!warehouseEmployeeId) {
                showMessage('Please select a warehouse staff member', 'error');
                return;
            }
            
            if (confirm(`Assign ${selectedOrders.length} order${selectedOrders.length !== 1 ? 's' : ''} to the selected warehouse staff?`)) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=bulk_assign&order_ids=${JSON.stringify(selectedOrders)}&warehouse_employee_id=${warehouseEmployeeId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred while assigning orders', 'error');
                });
            }
        }

        function bulkUnassignSelected() {
            if (selectedOrders.length === 0) {
                showMessage('No orders selected', 'error');
                return;
            }
            
            if (confirm(`Unassign ${selectedOrders.length} order${selectedOrders.length !== 1 ? 's' : ''}?`)) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=bulk_unassign&order_ids=${JSON.stringify(selectedOrders)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred while unassigning orders', 'error');
                });
            }
        }

        function showMessage(message, type) {
            const container = document.getElementById('messageContainer');
            const messageEl = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            
            messageEl.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-lg mb-4 opacity-0 transition-opacity duration-300`;
            messageEl.textContent = message;
            
            container.appendChild(messageEl);
            
            // Fade in
            setTimeout(() => {
                messageEl.classList.remove('opacity-0');
            }, 100);
            
            // Fade out and remove after 3 seconds
            setTimeout(() => {
                messageEl.classList.add('opacity-0');
                setTimeout(() => {
                    if (container.contains(messageEl)) {
                        container.removeChild(messageEl);
                    }
                }, 300);
            }, 3000);
        }

        // Auto-submit form when status filter buttons are clicked
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all checkboxes
            document.querySelectorAll('.order-checkbox').forEach(cb => {
                cb.addEventListener('change', updateBulkActions);
            });

            // Close modal when clicking outside
            document.getElementById('quickAssignModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeQuickAssignModal();
                }
            });

            // Handle ESC key for modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !document.getElementById('quickAssignModal').classList.contains('hidden')) {
                    closeQuickAssignModal();
                }
            });
        });
    </script>
</body>
</html>