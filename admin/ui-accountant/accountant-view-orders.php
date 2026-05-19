<?php
// accountant_view_orders.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['accountant', 'superadmin']);
require_subrole(['document_controller', '']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

function resolve_current_user_details($conn)
{
    if (!empty($_SESSION['current_user_details']))
        return $_SESSION['current_user_details'];
    if (empty($_SESSION['noble_user']))
        return null;
    $loginValue = $_SESSION['noble_user'];
    $userDetails = null;
    $stmt = $conn->prepare("SELECT id, fullname, email, lvl, status, is_head, last_login FROM nobleaccount WHERE email = ? OR fullname = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $loginValue, $loginValue);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $userDetails = ['id' => (int) $row['id'], 'name' => $row['fullname'], 'email' => $row['email'], 'role' => $row['lvl'], 'status' => $row['status'], 'is_head' => (bool) $row['is_head'], 'last_login' => $row['last_login'], 'table' => 'nobleaccount'];
            $stmt->close();
        } else {
            $stmt->close();
        }
    }
    if ($userDetails)
        $_SESSION['current_user_details'] = $userDetails;
    return $userDetails;
}

$current_user = resolve_current_user_details($conn);
if (!$current_user) {
    $_SESSION['error'] = "Unable to identify current user. Please login again.";
    header("Location: " . BASE_URL . "/main");
    exit();
}

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$approval_pending = isset($_GET['approval_pending']) ? true : false;

$whereParts = ["1=1"];
$params = [];
$types = '';

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

$ordersSql = "
    SELECT o.id, o.customer_name, o.email, o.created_at, o.status, o.total,
           o.warehouse_employee_id, we.fullname as warehouse_employee_name,
           COUNT(DISTINCT oi.id) as item_count,
           COUNT(DISTINCT poa.id) as po_attachment_count,
           SUM(CASE WHEN poa.approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approvals
    FROM orders o
    LEFT JOIN order_items oi  ON o.id = oi.order_id
    LEFT JOIN po_attachments poa ON o.id = poa.order_id
    LEFT JOIN nobleaccount we ON o.warehouse_employee_id = we.id
    $whereClause
    GROUP BY o.id, o.customer_name, o.email, o.created_at, o.status, o.total, o.warehouse_employee_id, we.fullname
    ORDER BY o.created_at DESC
    LIMIT 100
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

$statusCounts = [];
$totalOrders = 0;
$res = $conn->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
if ($res) {
    $statusCounts = $res->fetch_all(MYSQLI_ASSOC);
    foreach ($statusCounts as $row)
        $totalOrders += (int) $row['count'];
}
$statusCountsArray = [];
foreach ($statusCounts as $row)
    $statusCountsArray[$row['status']] = (int) $row['count'];

$pendingApprovalsCount = 0;
$pendingApprovalsResult = $conn->query("SELECT COUNT(DISTINCT o.id) as pending_count FROM orders o INNER JOIN po_attachments poa ON o.id = poa.order_id WHERE poa.approval_status = 'pending' AND poa.superadmin_approval_status = 'approved'");
if ($pendingApprovalsResult) {
    $pendingApprovalsCount = (int) $pendingApprovalsResult->fetch_assoc()['pending_count'];
}

$statusOrder = ['pending', 'Ongoing', 'processing', 'Ready for Pickup', 'Out for Delivery', 'Delivered', 'completed', 'cancelled'];
$statusConfig = [
    'pending' => ['icon' => 'fa-clock', 'color' => 'yellow'],
    'Ongoing' => ['icon' => 'fa-tasks', 'color' => 'orange'],
    'processing' => ['icon' => 'fa-cog', 'color' => 'blue'],
    'Ready for Pickup' => ['icon' => 'fa-box', 'color' => 'indigo'],
    'Out for Delivery' => ['icon' => 'fa-truck', 'color' => 'purple'],
    'Delivered' => ['icon' => 'fa-check-circle', 'color' => 'green'],
    'completed' => ['icon' => 'fa-check-double', 'color' => 'green'],
    'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red'],
];

$status_badge_css = [
    'pending' => 'background:#fef9c3;color:#a16207;',
    'Ongoing' => 'background:#ffedd5;color:#c2410c;',
    'processing' => 'background:#dbeafe;color:#1d4ed8;',
    'Ready for Pickup' => 'background:#e0e7ff;color:#4338ca;',
    'Out for Delivery' => 'background:#f3e8ff;color:#7e22ce;',
    'Delivered' => 'background:#dcfce7;color:#15803d;',
    'completed' => 'background:#dcfce7;color:#15803d;',
    'cancelled' => 'background:#fee2e2;color:#b91c1c;',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Overview — Accountant</title>
    <style>
        /* Status filter pills */
        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .filter-pill .count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        /* Status badge in table */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Table row hover */
        tbody tr {
            transition: background 0.1s;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        /* Scrollable table body */
        .table-scroll {
            max-height: 560px;
            overflow-y: auto;
        }

        /* Pending pulse */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .5
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-screen-xl mx-auto px-4 py-8 space-y-6">

        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Warehouse Orders</h1>
                <p class="text-sm text-gray-500 mt-0.5">View all orders and manage P.O. file approvals</p>
            </div>
            <?php if ($pendingApprovalsCount > 0): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold pulse"
                    style="background:#fee2e2;color:#b91c1c;">
                    <i class="fas fa-bell text-xs"></i>
                    <?php echo $pendingApprovalsCount; ?> pending
                    approval<?php echo $pendingApprovalsCount != 1 ? 's' : ''; ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Filters Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <form method="GET" class="space-y-4">

                <!-- Status pills -->
                <div class="flex flex-wrap gap-2">

                    <!-- All -->
                    <a href="?"
                        class="filter-pill <?php echo $status_filter === '' && !isset($_GET['approval_pending']) ? 'text-white' : ''; ?>"
                        style="<?php echo $status_filter === '' && !isset($_GET['approval_pending']) ? 'background:#ea580c;color:#fff;' : 'background:#f3f4f6;color:#374151;'; ?>">
                        <i class="fas fa-list text-xs"></i> All
                        <span class="count" style="background:rgba(0,0,0,0.12);"><?php echo $totalOrders; ?></span>
                    </a>

                    <!-- Requesting Approval -->
                    <a href="?approval_pending=1<?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>"
                        class="filter-pill"
                        style="<?php echo isset($_GET['approval_pending']) ? 'background:#ea580c;color:#fff;' : 'background:#ffedd5;color:#c2410c;'; ?>">
                        <i class="fas fa-bell text-xs"></i> Requesting Approval
                        <?php if ($pendingApprovalsCount > 0): ?>
                            <span class="count"
                                style="<?php echo isset($_GET['approval_pending']) ? 'background:rgba(255,255,255,0.25);color:#fff;' : 'background:#fed7aa;color:#c2410c;'; ?>">
                                <?php echo $pendingApprovalsCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Per-status pills -->
                    <?php foreach ($statusOrder as $status):
                        if (!isset($statusCountsArray[$status]))
                            continue;
                        $count = $statusCountsArray[$status];
                        $cfg = $statusConfig[$status] ?? ['icon' => 'fa-circle', 'color' => 'gray'];
                        $isActive = ($status_filter === $status);
                        $colors = [
                            'yellow' => ['active' => '#ca8a04', 'pill' => '#fef9c3', 'text' => '#a16207', 'count' => '#fde68a'],
                            'orange' => ['active' => '#ea580c', 'pill' => '#ffedd5', 'text' => '#c2410c', 'count' => '#fed7aa'],
                            'blue' => ['active' => '#2563eb', 'pill' => '#dbeafe', 'text' => '#1d4ed8', 'count' => '#bfdbfe'],
                            'indigo' => ['active' => '#4338ca', 'pill' => '#e0e7ff', 'text' => '#4338ca', 'count' => '#c7d2fe'],
                            'purple' => ['active' => '#7e22ce', 'pill' => '#f3e8ff', 'text' => '#7e22ce', 'count' => '#e9d5ff'],
                            'green' => ['active' => '#15803d', 'pill' => '#dcfce7', 'text' => '#15803d', 'count' => '#bbf7d0'],
                            'red' => ['active' => '#b91c1c', 'pill' => '#fee2e2', 'text' => '#b91c1c', 'count' => '#fecaca'],
                            'gray' => ['active' => '#374151', 'pill' => '#f3f4f6', 'text' => '#374151', 'count' => '#e5e7eb'],
                        ][$cfg['color']] ?? ['active' => '#374151', 'pill' => '#f3f4f6', 'text' => '#374151', 'count' => '#e5e7eb'];
                        ?>
                        <a href="?status=<?php echo urlencode($status); ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>"
                            class="filter-pill"
                            style="<?php echo $isActive ? "background:{$colors['active']};color:#fff;" : "background:{$colors['pill']};color:{$colors['text']};"; ?>">
                            <i class="fas <?php echo $cfg['icon']; ?> text-xs"></i>
                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                            <span class="count"
                                style="<?php echo $isActive ? 'background:rgba(255,255,255,0.2);color:#fff;' : "background:{$colors['count']};color:{$colors['text']};"; ?>">
                                <?php echo $count; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search + Date -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2 flex gap-2">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Search by Order ID, Customer, Email..."
                            class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <?php if ($status_filter): ?><input type="hidden" name="status"
                                value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
                        <?php if ($approval_pending): ?><input type="hidden" name="approval_pending"
                                value="1"><?php endif; ?>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition"
                            style="background:#ea580c;">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                    </div>
                    <div>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                    <div>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Orders List</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Showing <?php echo count($orders); ?>
                        order<?php echo count($orders) != 1 ? 's' : ''; ?></p>
                </div>
            </div>

            <?php if (!empty($orders)): ?>
                <div class="table-scroll">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3 text-left">Order</th>
                                <th class="px-5 py-3 text-left">Customer</th>
                                <th class="px-5 py-3 text-left">Amount</th>
                                <th class="px-5 py-3 text-left">Items</th>
                                <th class="px-5 py-3 text-left">Status</th>
                                <th class="px-5 py-3 text-left">Warehouse Staff</th>
                                <th class="px-5 py-3 text-left">P.O. Files</th>
                                <th class="px-5 py-3 text-left">Date</th>
                                <th class="px-5 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="px-5 py-3">
                                        <span
                                            class="text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-1 rounded-md">#<?php echo $order['id']; ?></span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                                                <span class="text-xs font-bold text-blue-600">
                                                    <?php echo strtoupper(substr($order['customer_name'] ?: 'U', 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 leading-snug">
                                                    <?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></p>
                                                <p class="text-xs text-gray-400">
                                                    <?php echo htmlspecialchars($order['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-semibold text-gray-900">
                                        ₱<?php echo number_format((float) $order['total'], 2); ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                            <?php echo $order['item_count']; ?>
                                            item<?php echo $order['item_count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="status-badge"
                                            style="<?php echo $status_badge_css[$order['status']] ?? 'background:#f3f4f6;color:#374151;'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-gray-700">
                                        <?php echo $order['warehouse_employee_name']
                                            ? htmlspecialchars($order['warehouse_employee_name'])
                                            : '<span class="text-gray-400 text-xs">Not assigned</span>'; ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <?php if ($order['po_attachment_count'] > 0): ?>
                                            <div class="flex flex-col gap-1">
                                                <span class="text-xs font-medium" style="color:#15803d;">
                                                    <i
                                                        class="fas fa-paperclip mr-1"></i><?php echo $order['po_attachment_count']; ?>
                                                    file<?php echo $order['po_attachment_count'] != 1 ? 's' : ''; ?>
                                                </span>
                                                <?php if ($order['pending_approvals'] > 0): ?>
                                                    <span class="text-xs font-semibold pulse" style="color:#c2410c;">
                                                        <i
                                                            class="fas fa-exclamation-circle mr-1"></i><?php echo $order['pending_approvals']; ?>
                                                        pending
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-sm text-gray-700">
                                            <?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                                        <p class="text-xs text-gray-400">
                                            <?php echo date('H:i', strtotime($order['created_at'])); ?></p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <?php if ($order['po_attachment_count'] > 0): ?>
                                            <a href="<?= BASE_URL; ?>/accountantviewpo?order_id=<?php echo $order['id']; ?>"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-white transition"
                                                style="background:#2563eb;">
                                                <i class="fas fa-eye"></i> View P.O.
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

            <?php else: ?>
                <div class="py-16 text-center">
                    <i class="fas fa-inbox text-5xl text-gray-200 mb-4"></i>
                    <h3 class="text-base font-semibold text-gray-600 mb-1">No orders found</h3>
                    <p class="text-sm text-gray-400">Try adjusting your filters or search criteria.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>