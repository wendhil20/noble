<?php
// accountant_view_orders.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);
require_subrole(['document_controller', '']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get current user details
function resolve_current_user_details($conn)
{
    if (!empty($_SESSION['current_user_details'])) {
        return $_SESSION['current_user_details'];
    }

    if (empty($_SESSION['noble_user'])) {
        return null;
    }

    $loginValue = $_SESSION['noble_user'];
    $userDetails = null;

    $stmt = $conn->prepare("SELECT id, fullname, email, lvl, status, is_head, last_login FROM nobleaccount WHERE email = ? OR fullname = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $loginValue, $loginValue);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $userDetails = [
                'id' => (int)$row['id'],
                'name' => $row['fullname'],
                'email' => $row['email'],
                'role' => $row['lvl'],
                'status' => $row['status'],
                'is_head' => (bool)$row['is_head'],
                'last_login' => $row['last_login'],
                'table' => 'nobleaccount'
            ];
            $stmt->close();
        } else {
            $stmt->close();
        }
    }

    if ($userDetails) {
        $_SESSION['current_user_details'] = $userDetails;
    }

    return $userDetails;
}

$current_user = resolve_current_user_details($conn);
if (!$current_user) {
    $_SESSION['error'] = "Unable to identify current user. Please login again.";
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get filters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$approval_pending = isset($_GET['approval_pending']) ? true : false;

// Build WHERE conditions
$whereParts = ["1=1"];
$params = [];
$types = '';

// NEW: Filter for orders with pending approvals (Document Controller level)
if ($approval_pending) {
    $whereParts[] = "EXISTS (SELECT 1 FROM po_attachments WHERE order_id = o.id AND approval_status = 'pending' AND superadmin_approval_status = 'approved')";
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

// Get orders with P.O. file counts
$ordersSql = "
    SELECT 
        o.id,
        o.customer_name,
        o.email,
        o.created_at,
        o.status,
        o.total,
        o.warehouse_employee_id,
        we.fullname as warehouse_employee_name,
        COUNT(DISTINCT oi.id) as item_count,
        COUNT(DISTINCT poa.id) as po_attachment_count,
        SUM(CASE WHEN poa.approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approvals
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN po_attachments poa ON o.id = poa.order_id
    LEFT JOIN nobleaccount we ON o.warehouse_employee_id = we.id
    $whereClause
    GROUP BY o.id, o.customer_name, o.email, o.created_at, o.status, o.total, o.warehouse_employee_id, we.fullname
    ORDER BY o.created_at DESC
    LIMIT 100
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

// Get status counts
$statusCounts = [];
$totalOrders = 0;

$statusSql = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$res = $conn->query($statusSql);
if ($res) {
    $statusCounts = $res->fetch_all(MYSQLI_ASSOC);
    foreach ($statusCounts as $row) {
        $totalOrders += (int)$row['count'];
    }
}

$statusCountsArray = [];
foreach ($statusCounts as $row) {
    $statusCountsArray[$row['status']] = (int)$row['count'];
}

// Get count of orders with pending P.O. approvals (Document Controller pending only)
$pendingApprovalsSql = "
    SELECT COUNT(DISTINCT o.id) as pending_count
    FROM orders o
    INNER JOIN po_attachments poa ON o.id = poa.order_id
    WHERE poa.approval_status = 'pending'
    AND poa.superadmin_approval_status = 'approved'
";
$pendingApprovalsResult = $conn->query($pendingApprovalsSql);
$pendingApprovalsCount = 0;
if ($pendingApprovalsResult) {
    $pendingApprovalsRow = $pendingApprovalsResult->fetch_assoc();
    $pendingApprovalsCount = (int)$pendingApprovalsRow['pending_count'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Orders Overview - Accountant</title>
    <style>
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="w-full px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-noble-orange rounded-lg flex items-center justify-center relative">
                        <i class="fas fa-box text-white text-lg"></i>

                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Warehouse Orders Overview</h1>
                        <p class="text-sm text-gray-600">
                            View all warehouse orders and P.O. files
                            <?php if ($pendingApprovalsCount > 0): ?>
                                <span class="inline-flex items-center ml-2 px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                    <i class="fas fa-bell mr-1"></i>
                                    <?php echo $pendingApprovalsCount; ?> pending approval<?php echo $pendingApprovalsCount != 1 ? 's' : ''; ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="w-full px-6 py-8">
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="?" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 relative <?php echo $status_filter === '' && !isset($_GET['approval_pending']) ? 'bg-noble-orange text-black' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        <i class="fas fa-list mr-1"></i>
                        All Orders
                        <?php if ($pendingApprovalsCount > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center animate-pulse">
                                <?php echo $pendingApprovalsCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- NEW: Requesting Approval Filter -->
                    <a href="?approval_pending=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center space-x-1 relative <?php echo isset($_GET['approval_pending']) ? 'bg-orange-600 shadow-md' : 'bg-orange-100 text-orange-700 hover:bg-orange-200'; ?>">
                        <i class="fas fa-bell"></i>
                        <span>Requesting Approval</span>
                        <?php if ($pendingApprovalsCount > 0): ?>
                            <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo isset($_GET['approval_pending']) ? 'bg-white/20' : 'bg-orange-200'; ?>">
                                <?php echo (int)$pendingApprovalsCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <?php
                    $statusOrder = ['pending', 'Ongoing', 'processing', 'Ready for Pickup', 'Out for Delivery', 'Delivered', 'completed', 'cancelled'];
                    $statusConfig = [
                        'pending' => ['icon' => 'fa-clock', 'color' => 'yellow'],
                        'Ongoing' => ['icon' => 'fa-tasks', 'color' => 'orange'],
                        'processing' => ['icon' => 'fa-cog', 'color' => 'blue'],
                        'Ready for Pickup' => ['icon' => 'fa-box', 'color' => 'indigo'],
                        'Out for Delivery' => ['icon' => 'fa-truck', 'color' => 'purple'],
                        'Delivered' => ['icon' => 'fa-check-circle', 'color' => 'green'],
                        'completed' => ['icon' => 'fa-check-double', 'color' => 'green'],
                        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red']
                    ];

                    foreach ($statusOrder as $status):
                        if (!isset($statusCountsArray[$status])) continue;
                        $count = $statusCountsArray[$status];
                        $config = $statusConfig[$status] ?? ['icon' => 'fa-circle', 'color' => 'gray'];
                        $isActive = ($status_filter === $status);
                    ?>
                        <a href="?status=<?php echo urlencode($status); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center space-x-1 <?php echo $isActive ? 'bg-' . $config['color'] . '-600 text-white shadow-md' : 'bg-' . $config['color'] . '-100 text-' . $config['color'] . '-700 hover:bg-' . $config['color'] . '-200'; ?>">
                            <i class="fas <?php echo $config['icon']; ?>"></i>
                            <span><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                            <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?php echo $isActive ? 'bg-white/20' : 'bg-' . $config['color'] . '-200'; ?>">
                                <?php echo (int)$count; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="flex gap-2">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                                placeholder="Order ID, Customer, Email..."
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                            <button type="submit" class="bg-noble-orange hover:bg-noble-orange-dark text-white px-6 py-2 rounded-md transition-colors duration-200 flex items-center justify-center whitespace-nowrap">
                                <i class="fas fa-search mr-2"></i>
                                Search
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                    </div>
                </div>

            </form>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Orders List</h2>
                <p class="text-sm text-gray-600">All warehouse orders with P.O. file status</p>
            </div>

            <?php if (!empty($orders)): ?>
                <div class="overflow-x-auto shadow-md rounded-lg">
    <div class="max-h-[600px] overflow-y-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 sticky top-0 z-10">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">P.O. Files</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($orders as $order): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="bg-gray-100 px-2 py-1 rounded-md text-sm font-medium">#<?php echo $order['id']; ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-blue-600 text-xs font-semibold">
                                        <?php echo strtoupper(substr($order['customer_name'] ?: 'U', 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900">₱<?php echo number_format((float)$order['total'], 2); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                <i class="fas fa-box mr-1"></i>
                                <?php echo $order['item_count']; ?> items
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $status_colors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'Ongoing' => 'bg-orange-100 text-orange-800',
                                'processing' => 'bg-blue-100 text-blue-800',
                                'Ready for Pickup' => 'bg-indigo-100 text-indigo-800',
                                'Out for Delivery' => 'bg-purple-100 text-purple-800',
                                'Delivered' => 'bg-green-100 text-green-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800'
                            ];
                            $status_class = $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo $order['warehouse_employee_name'] ? htmlspecialchars($order['warehouse_employee_name']) : '<span class="text-gray-400">Not assigned</span>'; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <?php if ($order['po_attachment_count'] > 0): ?>
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                        <i class="fas fa-file-excel mr-1"></i>
                                        <?php echo $order['po_attachment_count']; ?> file(s)
                                    </span>
                                    <?php if ($order['pending_approvals'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full animate-pulse">
                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                            <?php echo $order['pending_approvals']; ?> pending
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 text-gray-500 rounded-full">
                                        <i class="fas fa-times mr-1"></i>
                                        No files
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?php if ($order['po_attachment_count'] > 0): ?>
                                <a href="accountant_view_po.php?order_id=<?php echo $order['id']; ?>"
                                    class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-eye mr-1"></i>
                                    View P.O.
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">No P.O. files</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
            <?php else: ?>
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No orders found</h3>
                    <p class="text-sm text-gray-500">Try adjusting your filters or search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>

</html>