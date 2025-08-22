<?php
// order_list.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
// keep the same role guard if you want only specific roles to access this page
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);

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
                if ($r) { $user_id = (int)$r['id']; $fullname = $r['fullname']; $user_lvl = $r['lvl']; }
            }
        } else {
            $candidate = (string)$sessionUser;
            $sql = "SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("s", $candidate);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) { $user_id = (int)$r['id']; $fullname = $r['fullname']; $user_lvl = $r['lvl']; }
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
                if ($r) { $user_id = (int)$r['id']; $fullname = $r['fullname']; $user_lvl = $r['lvl']; }
            }
        } elseif (!empty($sessionUser['id'])) {
            $candidate = (int)$sessionUser['id'];
            $sql = "SELECT id, fullname, lvl FROM nobleaccount WHERE id = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("i", $candidate);
                $s->execute();
                $r = $s->get_result()->fetch_assoc();
                $s->close();
                if ($r) { $user_id = (int)$r['id']; $fullname = $r['fullname']; $user_lvl = $r['lvl']; }
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

// --- Build WHERE conditions ---
// IMPORTANT: We ALWAYS restrict by warehouse_employee_id = logged in user
$whereParts = ["o.warehouse_employee_id = ?"];
$params = [$user_id];
$types = 'i';

if ($status_filter !== '') {
    $whereParts[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search_query !== '') {
    $whereParts[] = "(o.customer_name LIKE ? OR o.email LIKE ? OR o.id LIKE ?)";
    $like = "%{$search_query}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
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

$whereClause = 'WHERE ' . implode(' AND ', $whereParts);

// --- Helper to bind params dynamically (call_user_func_array with references) ---
function bindParamsToStmt($stmt, $types, $params) {
    if ($types === '' || empty($params)) return;
    $bind_names[] = $types;
    // create references
    for ($i=0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

// --- Orders query (only orders assigned to this user) ---
$ordersSql = "
    SELECT 
        o.id,
        o.customer_name,
        o.email,
        o.created_at,
        o.status,
        o.total,
        COUNT(oi.id) as item_count,
        SUM(CASE WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN 1 ELSE 0 END) as assigned_linked_count,
        SUM(CASE WHEN oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL THEN 1 ELSE 0 END) as assigned_manual_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $whereClause
    GROUP BY o.id, o.customer_name, o.email, o.created_at, o.status, o.total
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
} else {
    // If prepare fails, ensure safe fallback (only if no params)
    if (empty($params)) {
        $res = $conn->query($ordersSql);
        if ($res) $orders = $res->fetch_all(MYSQLI_ASSOC);
    }
}

// --- Status counts: only counts for this user's assigned orders ---
$statusCounts = [];
$totalOrders = 0;
$statusSql = "SELECT status, COUNT(*) as count FROM orders WHERE warehouse_employee_id = ? GROUP BY status";
if ($stmt2 = $conn->prepare($statusSql)) {
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    if ($r2) $statusCounts = $r2->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
}

$statusCountsArray = [];
foreach ($statusCounts as $row) {
    $statusCountsArray[$row['status']] = (int)$row['count'];
    $totalOrders += (int)$row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders Management - P.O System</title>
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
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-shopping-cart text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Orders Management</h1>
                        <p class="text-gray-600 mt-1">Only orders assigned to you are visible here</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-primary-50 px-4 py-2 rounded-lg mb-2">
                        <span class="text-primary-700 font-medium">Logged in as: <?php echo htmlspecialchars($fullname); ?></span>
                    </div>
                    <div class="bg-primary-50 px-4 py-2 rounded-lg">
                        <span class="text-primary-700 font-medium"><?php echo number_format(count($orders)); ?> Orders</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="?" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo ($status_filter === '') ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        My Orders (<?php echo $totalOrders; ?>)
                    </a>
                    <?php foreach ($statusCountsArray as $status => $count): ?>
                        <a href="?status=<?php echo urlencode($status); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 <?php echo ($status_filter === $status) ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                            <?php echo htmlspecialchars(ucfirst($status)); ?> (<?php echo (int)$count; ?>)
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Order ID, Customer, Email..." class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders -->
        <?php if (!empty($orders)): ?>
            <div class="space-y-4">
            <?php foreach ($orders as $order):
                $assignedItems = (int)$order['assigned_linked_count'] + (int)$order['assigned_manual_count'];
                $item_count = (int)$order['item_count'];
                $assignmentPercentage = $item_count > 0 ? round(($assignedItems / $item_count) * 100) : 0;
            ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-start space-x-4">
                                <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-2 rounded-lg">
                                    <i class="fas fa-receipt text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-1">Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <div class="flex items-center"><i class="fas fa-user mr-2"></i><span><?php echo htmlspecialchars($order['customer_name']); ?></span></div>
                                        <div class="flex items-center"><i class="fas fa-envelope mr-2"></i><span><?php echo htmlspecialchars($order['email']); ?></span></div>
                                        <div class="flex items-center"><i class="fas fa-calendar mr-2"></i><span><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right space-y-2">
                                <div class="text-lg font-bold text-primary-700">₱<?php echo number_format((float)$order['total'], 2); ?></div>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo ($order['status'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : (($order['status'] === 'completed') ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'); ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                                    <span class="text-sm text-gray-600"><?php echo $item_count; ?> items</span>
                                </div>

                                <div class="text-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-gray-600">Supplier Assignment</span>
                                        <span class="font-medium <?php echo ($assignmentPercentage === 100) ? 'text-green-600' : (($assignmentPercentage > 0) ? 'text-yellow-600' : 'text-red-600'); ?>"><?php echo $assignmentPercentage; ?>%</span>
                                    </div>
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full <?php echo ($assignmentPercentage === 100) ? 'bg-green-500' : (($assignmentPercentage > 0) ? 'bg-yellow-500' : 'bg-red-500'); ?>" style="width: <?php echo $assignmentPercentage; ?>%"></div>
                                    </div>
                                    <div class="mt-1 text-gray-500"><?php echo $assignedItems; ?>/<?php echo $item_count; ?> assigned</div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2 ml-4">
                                <a href="po_management.php?order_id=<?php echo urlencode($order['id']); ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                    <i class="fas fa-cogs"></i><span>Manage P.O.</span>
                                </a>
                                <?php if ($assignmentPercentage === 100): ?>
                                    <a href="generate_po.php?order_id=<?php echo urlencode($order['id']); ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                        <i class="fas fa-file-invoice"></i><span>Generate P.O.</span>
                                    </a>
                                <?php endif; ?>
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
                        You don't have any assigned orders right now.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
