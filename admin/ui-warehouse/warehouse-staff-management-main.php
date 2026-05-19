<?php
// warehouse_staff_management_main.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);
require_subrole(['warehouse_staff']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$user_id = null;
$fullname = '';
$user_lvl = '';
$is_head = false;

$sessionUser = $_SESSION['noble_user'];

if (is_array($sessionUser)) {
    if (isset($sessionUser['id']))
        $user_id = (int) $sessionUser['id'];
    elseif (isset($sessionUser['user_id']))
        $user_id = (int) $sessionUser['user_id'];
    $fullname = $sessionUser['fullname'] ?? $sessionUser['name'] ?? '';
    $user_lvl = $sessionUser['lvl'] ?? $sessionUser['level'] ?? '';
    $is_head = isset($sessionUser['is_head']) && (int) $sessionUser['is_head'] === 1;
}

if (empty($user_id) || empty($user_lvl)) {
    if (!is_array($sessionUser)) {
        if (ctype_digit((string) $sessionUser)) {
            $candidate = (int) $sessionUser;
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
        } else {
            $candidate = (string) $sessionUser;
            $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE email = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param("s", $candidate);
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
    } else {
        $lookupField = !empty($sessionUser['email']) ? 'email' : (!empty($sessionUser['id']) ? 'id' : null);
        if ($lookupField) {
            $candidate = $sessionUser[$lookupField];
            $type = ($lookupField === 'id') ? 'i' : 's';
            $col = ($lookupField === 'id') ? 'id' : 'email';
            $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE $col = ? LIMIT 1";
            if ($s = $conn->prepare($sql)) {
                $s->bind_param($type, $candidate);
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

if (empty($user_id) || empty($user_lvl)) {
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/main");
    exit();
}

// --- Filters ---
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$show_replacements = isset($_GET['replacements']) ? (bool) $_GET['replacements'] : false;
$show_ready_for_schedule = isset($_GET['ready_schedule']) ? (bool) $_GET['ready_schedule'] : false;
$show_defects = isset($_GET['defects']) ? (bool) $_GET['defects'] : false;
$show_ready_replacements = isset($_GET['ready_replacements']) ? (bool) $_GET['ready_replacements'] : false;
$show_ready_to_order = isset($_GET['ready_to_order']) ? (bool) $_GET['ready_to_order'] : false;

// --- WHERE builder ---
$whereParts = ["o.warehouse_employee_id = ?"];
$params = [$user_id];
$types = 'i';

if ($status_filter === '' && !$show_replacements && !$show_ready_for_schedule && !$show_defects && !$show_ready_replacements && !$show_ready_to_order) {
    $whereParts[] = "(
        (SELECT COUNT(*) FROM order_items oi_check WHERE oi_check.order_id = o.id
         AND ((oi_check.supplier_id IS NOT NULL AND oi_check.supplier_id > 0)
              OR (oi_check.supplier_id = 0 AND oi_check.manual_supplier_name IS NOT NULL AND oi_check.manual_supplier_name != ''))
        ) < (SELECT COUNT(*) FROM order_items oi_total WHERE oi_total.order_id = o.id)
        OR (SELECT COUNT(*) FROM order_items oi_total WHERE oi_total.order_id = o.id) = 0
    )";
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
if ($show_defects) {
    $whereParts[] = "EXISTS (SELECT 1 FROM defect_reports dr WHERE dr.order_id = o.id AND dr.status != 'resolved')";
}
if ($show_ready_for_schedule) {
    $whereParts[] = "NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id)";
    $whereParts[] = "o.status IN ('processing', 'Ready for Pickup', 'Out for Delivery')";
    $whereParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.tracking_status = 'In Warehouse') = (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id)";
    $whereParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0";
}
if ($show_ready_replacements) {
    $whereParts[] = "EXISTS (SELECT 1 FROM replacement_requests rr WHERE rr.order_id = o.id AND rr.status IN ('approved','processing','In Warehouse'))";
    $whereParts[] = "NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id AND ds.item_type = 'replacement')";
    $whereParts[] = "(SELECT COUNT(*) FROM replacement_requests rr2 WHERE rr2.order_id = o.id AND rr2.status = 'In Warehouse') = (SELECT COUNT(*) FROM replacement_requests rr3 WHERE rr3.order_id = o.id AND rr3.status IN ('approved','processing','In Warehouse'))";
    $whereParts[] = "(SELECT COUNT(*) FROM replacement_requests rr4 WHERE rr4.order_id = o.id AND rr4.status IN ('approved','processing','In Warehouse')) > 0";
}
if ($show_ready_to_order) {
    $whereParts[] = "EXISTS (SELECT 1 FROM po_attachments pa WHERE pa.order_id = o.id AND pa.approval_status = 'approved' AND pa.marked_as_ordered = 0)";
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

// --- Main orders query ---
$ordersSql = "
    SELECT o.id, o.customer_name, o.email, o.created_at, o.status, o.total,
        COUNT(DISTINCT oi.id) as item_count,
        COUNT(DISTINCT CASE WHEN (oi.supplier_id IS NOT NULL AND oi.supplier_id > 0) OR (oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '') THEN oi.id END) as assigned_count,
        COUNT(DISTINCT poa.id) as po_attachment_count,
        COUNT(DISTINCT CASE WHEN rr.status IN ('approved','processing','In Warehouse') THEN rr.id END) as approved_replacements_count,
        COUNT(DISTINCT CASE WHEN rr.status = 'In Warehouse' THEN rr.id END) as warehouse_replacements_count,
        COUNT(DISTINCT CASE WHEN dr.status != 'resolved' THEN dr.id END) as unresolved_defects_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN po_attachments poa ON o.id = poa.order_id
    LEFT JOIN replacement_requests rr ON o.id = rr.order_id
    LEFT JOIN defect_reports dr ON o.id = dr.order_id
    $whereClause
    GROUP BY o.id, o.customer_name, o.email, o.created_at, o.status, o.total
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

// --- Status counts ---
$statusCounts = [];
$totalOrders = 0;
$replacementOrdersCount = 0;
$statusSql = "SELECT status, COUNT(*) as count FROM orders WHERE warehouse_employee_id = ? GROUP BY status";
if ($stmt2 = $conn->prepare($statusSql)) {
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    if ($r2)
        $statusCounts = $r2->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
}

$ongoingOrdersCount = 0;
$ongoingOrdersSql = "SELECT COUNT(DISTINCT o.id) as count FROM orders o WHERE o.warehouse_employee_id = ? AND ((SELECT COUNT(*) FROM order_items oi_check WHERE oi_check.order_id = o.id AND ((oi_check.supplier_id IS NOT NULL AND oi_check.supplier_id > 0) OR (oi_check.supplier_id = 0 AND oi_check.manual_supplier_name IS NOT NULL AND oi_check.manual_supplier_name != ''))) < (SELECT COUNT(*) FROM order_items oi_total WHERE oi_total.order_id = o.id) OR (SELECT COUNT(*) FROM order_items oi_total WHERE oi_total.order_id = o.id) = 0)";
if ($stmt_ongoing = $conn->prepare($ongoingOrdersSql)) {
    $stmt_ongoing->bind_param("i", $user_id);
    $stmt_ongoing->execute();
    $r_ongoing = $stmt_ongoing->get_result();
    if ($r_ongoing)
        $ongoingOrdersCount = (int) $r_ongoing->fetch_assoc()['count'];
    $stmt_ongoing->close();
}

$replacementSql = "SELECT COUNT(DISTINCT o.id) as count FROM orders o WHERE o.warehouse_employee_id = ? AND EXISTS (SELECT 1 FROM replacement_requests rr WHERE rr.order_id = o.id AND rr.status IN ('approved','processing'))";
if ($stmt3 = $conn->prepare($replacementSql)) {
    $stmt3->bind_param("i", $user_id);
    $stmt3->execute();
    $r3 = $stmt3->get_result();
    if ($r3)
        $replacementOrdersCount = (int) $r3->fetch_assoc()['count'];
    $stmt3->close();
}

$readyForScheduleCount = 0;
$readyForScheduleSql = "SELECT COUNT(DISTINCT o.id) as count FROM orders o WHERE o.warehouse_employee_id = ? AND o.status IN ('processing','Ready for Pickup','Out for Delivery') AND NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id) AND (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.tracking_status = 'In Warehouse') = (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id) AND (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0";
if ($stmt4 = $conn->prepare($readyForScheduleSql)) {
    $stmt4->bind_param("i", $user_id);
    $stmt4->execute();
    $r4 = $stmt4->get_result();
    if ($r4)
        $readyForScheduleCount = (int) $r4->fetch_assoc()['count'];
    $stmt4->close();
}

$readyReplacementsCount = 0;
$readyReplacementsSql = "SELECT COUNT(DISTINCT o.id) as count FROM orders o WHERE o.warehouse_employee_id = ? AND EXISTS (SELECT 1 FROM replacement_requests rr WHERE rr.order_id = o.id AND rr.status IN ('approved','processing','In Warehouse')) AND NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id AND ds.item_type = 'replacement') AND (SELECT COUNT(*) FROM replacement_requests rr2 WHERE rr2.order_id = o.id AND rr2.status = 'In Warehouse') = (SELECT COUNT(*) FROM replacement_requests rr3 WHERE rr3.order_id = o.id AND rr3.status IN ('approved','processing','In Warehouse')) AND (SELECT COUNT(*) FROM replacement_requests rr4 WHERE rr4.order_id = o.id AND rr4.status IN ('approved','processing','In Warehouse')) > 0";
if ($stmt_repl = $conn->prepare($readyReplacementsSql)) {
    $stmt_repl->bind_param("i", $user_id);
    $stmt_repl->execute();
    $r_repl = $stmt_repl->get_result();
    if ($r_repl)
        $readyReplacementsCount = (int) $r_repl->fetch_assoc()['count'];
    $stmt_repl->close();
}

$defectsOrdersCount = 0;
$defectsOrdersCountSql = "SELECT COUNT(DISTINCT o.id) as count FROM orders o WHERE o.warehouse_employee_id = ? AND EXISTS (SELECT 1 FROM defect_reports dr WHERE dr.order_id = o.id AND dr.status != 'resolved')";
if ($stmt5 = $conn->prepare($defectsOrdersCountSql)) {
    $stmt5->bind_param("i", $user_id);
    $stmt5->execute();
    $r5 = $stmt5->get_result();
    if ($r5)
        $defectsOrdersCount = (int) $r5->fetch_assoc()['count'];
    $stmt5->close();
}

$readyToOrderFilterCount = 0;
$readyToOrderCountSql = "SELECT COUNT(DISTINCT o.id) as count FROM orders o WHERE o.warehouse_employee_id = ? AND EXISTS (SELECT 1 FROM po_attachments pa WHERE pa.order_id = o.id AND pa.approval_status = 'approved' AND pa.marked_as_ordered = 0)";
if ($stmt_ready_order = $conn->prepare($readyToOrderCountSql)) {
    $stmt_ready_order->bind_param("i", $user_id);
    $stmt_ready_order->execute();
    $r_ready_order = $stmt_ready_order->get_result();
    if ($r_ready_order)
        $readyToOrderFilterCount = (int) $r_ready_order->fetch_assoc()['count'];
    $stmt_ready_order->close();
}

$statusCountsArray = [];
foreach ($statusCounts as $row) {
    $statusCountsArray[$row['status']] = (int) $row['count'];
    $totalOrders += (int) $row['count'];
}

$itemStatuses = [];
$itemStatusSql = "SELECT o.id, COUNT(*) as total_items, COUNT(CASE WHEN oi.tracking_status = 'pending' THEN 1 END) as pending, COUNT(CASE WHEN oi.tracking_status = 'processing' THEN 1 END) as processing FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id WHERE o.warehouse_employee_id = ? GROUP BY o.id";
if ($stmtStatus = $conn->prepare($itemStatusSql)) {
    $stmtStatus->bind_param("i", $user_id);
    $stmtStatus->execute();
    $resultStatus = $stmtStatus->get_result();
    if ($resultStatus)
        while ($row = $resultStatus->fetch_assoc())
            $itemStatuses[$row['id']] = $row;
    $stmtStatus->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Orders Management</title>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-screen-xl mx-auto px-6 py-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center">
                    <i class="fas fa-boxes text-white text-sm"></i>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Orders Management</h1>
                    <p class="text-sm text-gray-500">Showing orders assigned to you</p>
                </div>
            </div>
            <?php if ($is_head): ?>
                <a href="warehouse_head_assignment.php"
                    class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-crown text-yellow-400 text-xs"></i>
                    Head Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-4">

        <!-- Filter Tabs + Search -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">

            <!-- Tabs -->
            <div class="flex flex-wrap gap-2">

                <!-- Incomplete -->
                <?php
                $isDefault = ($status_filter === '' && !$show_replacements && !$show_ready_for_schedule && !$show_defects && !$show_ready_replacements && !$show_ready_to_order);
                ?>
                <a href="?" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors
                      <?= $isDefault ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    <i class="fas fa-list-check"></i>
                    Incomplete
                    <span class="<?= $isDefault ? 'bg-white/20' : 'bg-gray-200' ?> px-1.5 py-0.5 rounded text-xs">
                        <?= $ongoingOrdersCount ?>
                    </span>
                </a>

                <?php
                $statusConfig = [
                    'pending' => ['icon' => 'fa-clock', 'tab' => 'bg-amber-50 text-amber-700 hover:bg-amber-100', 'active' => 'bg-amber-500 text-white'],
                    'Ongoing' => ['icon' => 'fa-spinner', 'tab' => 'bg-orange-50 text-orange-700 hover:bg-orange-100', 'active' => 'bg-orange-500 text-white'],
                    'processing' => ['icon' => 'fa-cog', 'tab' => 'bg-blue-50 text-blue-700 hover:bg-blue-100', 'active' => 'bg-blue-500 text-white'],
                    'Ready for Pickup' => ['icon' => 'fa-box', 'tab' => 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100', 'active' => 'bg-indigo-500 text-white'],
                    'Out for Delivery' => ['icon' => 'fa-truck', 'tab' => 'bg-purple-50 text-purple-700 hover:bg-purple-100', 'active' => 'bg-purple-500 text-white'],
                    'Delivered' => ['icon' => 'fa-circle-check', 'tab' => 'bg-green-50 text-green-700 hover:bg-green-100', 'active' => 'bg-green-500 text-white'],
                    'completed' => ['icon' => 'fa-check-double', 'tab' => 'bg-teal-50 text-teal-700 hover:bg-teal-100', 'active' => 'bg-teal-500 text-white'],
                    'cancelled' => ['icon' => 'fa-xmark', 'tab' => 'bg-red-50 text-red-600 hover:bg-red-100', 'active' => 'bg-red-500 text-white'],
                ];
                foreach ($statusConfig as $status => $cfg):
                    if (!isset($statusCountsArray[$status]))
                        continue;
                    $isActive = ($status_filter === $status && !$show_replacements && !$show_ready_for_schedule && !$show_defects && !$show_ready_replacements && !$show_ready_to_order);
                    $url = '?status=' . urlencode($status)
                        . (!empty($search_query) ? '&search=' . urlencode($search_query) : '')
                        . (!empty($date_from) ? '&date_from=' . urlencode($date_from) : '')
                        . (!empty($date_to) ? '&date_to=' . urlencode($date_to) : '');
                    ?>
                    <a href="<?= $url ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors
                      <?= $isActive ? $cfg['active'] : $cfg['tab'] ?>">
                        <i class="fas <?= $cfg['icon'] ?>"></i>
                        <?= htmlspecialchars(ucfirst($status)) ?>
                        <span class="<?= $isActive ? 'bg-white/25' : 'bg-black/10' ?> px-1.5 py-0.5 rounded text-xs">
                            <?= (int) $statusCountsArray[$status] ?>
                        </span>
                    </a>
                <?php endforeach; ?>

                <!-- Alert tabs -->
                <?php
                $alertTabs = [
                    ['show' => $replacementOrdersCount > 0, 'flag' => 'replacements=1', 'active' => $show_replacements, 'icon' => 'fa-arrows-rotate', 'label' => 'Replacements', 'count' => $replacementOrdersCount, 'color' => 'red'],
                    ['show' => $defectsOrdersCount > 0, 'flag' => 'defects=1', 'active' => $show_defects, 'icon' => 'fa-triangle-exclamation', 'label' => 'Defects', 'count' => $defectsOrdersCount, 'color' => 'orange'],
                    ['show' => $readyForScheduleCount > 0, 'flag' => 'ready_schedule=1', 'active' => $show_ready_for_schedule, 'icon' => 'fa-calendar-check', 'label' => 'Schedule', 'count' => $readyForScheduleCount, 'color' => 'green'],
                    ['show' => $readyToOrderFilterCount > 0, 'flag' => 'ready_to_order=1', 'active' => $show_ready_to_order, 'icon' => 'fa-bell', 'label' => 'Order Ready', 'count' => $readyToOrderFilterCount, 'color' => 'emerald'],
                    ['show' => $readyReplacementsCount > 0, 'flag' => 'ready_replacements=1', 'active' => $show_ready_replacements, 'icon' => 'fa-arrows-rotate', 'label' => 'Rep. Ready', 'count' => $readyReplacementsCount, 'color' => 'rose'],
                ];
                $alertColors = [
                    'red' => ['tab' => 'bg-red-50 text-red-700 hover:bg-red-100', 'active' => 'bg-red-600 text-white', 'badge' => 'bg-red-200', 'dot' => 'bg-red-500'],
                    'orange' => ['tab' => 'bg-orange-50 text-orange-700 hover:bg-orange-100', 'active' => 'bg-orange-600 text-white', 'badge' => 'bg-orange-200', 'dot' => 'bg-orange-500'],
                    'green' => ['tab' => 'bg-green-50 text-green-700 hover:bg-green-100', 'active' => 'bg-green-600 text-white', 'badge' => 'bg-green-200', 'dot' => 'bg-green-500'],
                    'emerald' => ['tab' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100', 'active' => 'bg-emerald-600 text-white', 'badge' => 'bg-emerald-200', 'dot' => 'bg-emerald-500'],
                    'rose' => ['tab' => 'bg-rose-50 text-rose-700 hover:bg-rose-100', 'active' => 'bg-rose-600 text-white', 'badge' => 'bg-rose-200', 'dot' => 'bg-rose-500'],
                ];
                foreach ($alertTabs as $tab):
                    if (!$tab['show'])
                        continue;
                    $c = $alertColors[$tab['color']];
                    $url = '?' . $tab['flag']
                        . (!empty($search_query) ? '&search=' . urlencode($search_query) : '')
                        . (!empty($date_from) ? '&date_from=' . urlencode($date_from) : '')
                        . (!empty($date_to) ? '&date_to=' . urlencode($date_to) : '');
                    ?>
                    <a href="<?= $url ?>" class="relative inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors
                      <?= $tab['active'] ? $c['active'] : $c['tab'] ?>">
                        <i class="fas <?= $tab['icon'] ?>"></i>
                        <?= $tab['label'] ?>
                        <span class="<?= $tab['active'] ? 'bg-white/25' : $c['badge'] ?> px-1.5 py-0.5 rounded text-xs">
                            <?= $tab['count'] ?>
                        </span>
                        <?php if (!$tab['active']): ?>
                            <span
                                class="absolute -top-1 -right-1 w-2 h-2 <?= $c['dot'] ?> rounded-full ring-2 ring-white animate-pulse"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search -->
            <form method="GET" class="flex gap-2">
                <?php if ($show_ready_for_schedule): ?><input type="hidden" name="ready_schedule"
                        value="1"><?php endif; ?>
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>"
                    placeholder="Search by order ID, customer, or email…"
                    class="flex-1 text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:border-transparent">
                <button type="submit"
                    class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-search text-xs"></i> Search
                </button>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (!empty($orders)): ?>
            <div class="space-y-3">
                <?php foreach ($orders as $order):
                    $assignedItems = (int) $order['assigned_count'];
                    $item_count = (int) $order['item_count'];
                    $po_attachment_count = (int) $order['po_attachment_count'];
                    $approved_replacements_count = (int) ($order['approved_replacements_count'] ?? 0);
                    $warehouse_replacements_count = (int) ($order['warehouse_replacements_count'] ?? 0);
                    $unresolved_defects_count = (int) ($order['unresolved_defects_count'] ?? 0);
                    $assignmentPct = $item_count > 0 ? round(($assignedItems / $item_count) * 100) : 0;
                    $hasPOFiles = $po_attachment_count > 0;
                    $hasReplacements = $approved_replacements_count > 0;
                    $statuses = $itemStatuses[$order['id']] ?? null;

                    // Progress bar color
                    $barColor = $assignmentPct === 100 ? 'bg-green-500' : ($assignmentPct > 0 ? 'bg-amber-400' : 'bg-red-400');
                    $pctColor = $assignmentPct === 100 ? 'text-green-600' : ($assignmentPct > 0 ? 'text-amber-600' : 'text-red-500');

                    // Status badge color
                    $statusMap = [
                        'pending' => 'bg-amber-100 text-amber-700',
                        'processing' => 'bg-blue-100 text-blue-700',
                        'completed' => 'bg-green-100 text-green-700',
                        'cancelled' => 'bg-red-100 text-red-700',
                        'Delivered' => 'bg-green-100 text-green-700',
                        'Out for Delivery' => 'bg-purple-100 text-purple-700',
                        'Ready for Pickup' => 'bg-indigo-100 text-indigo-700',
                    ];
                    $statusClass = $statusMap[$order['status']] ?? 'bg-gray-100 text-gray-600';

                    // Ready for schedule check
                    $isReadyForSchedule = false;
                    if ($hasPOFiles) {
                        $sql = "SELECT (SELECT COUNT(*) FROM order_items WHERE order_id=? AND tracking_status='In Warehouse') as wh, (SELECT COUNT(*) FROM order_items WHERE order_id=?) as tot, (SELECT COUNT(*) FROM delivery_schedules WHERE order_id=?) as sc";
                        if ($s = $conn->prepare($sql)) {
                            $s->bind_param("iii", $order['id'], $order['id'], $order['id']);
                            $s->execute();
                            $rr = $s->get_result()->fetch_assoc();
                            $s->close();
                            $isReadyForSchedule = ($rr['wh'] == $rr['tot'] && $rr['tot'] > 0 && $rr['sc'] == 0);
                        }
                    }

                    // Replacements ready check
                    $replacementsReady = false;
                    if ($warehouse_replacements_count > 0 && $approved_replacements_count > 0) {
                        $sql2 = "SELECT (SELECT COUNT(*) FROM replacement_requests WHERE order_id=? AND status='In Warehouse') as ready, (SELECT COUNT(*) FROM replacement_requests WHERE order_id=? AND status IN ('approved','processing','In Warehouse')) as tot, (SELECT COUNT(*) FROM delivery_schedules WHERE order_id=? AND item_type='replacement') as sc";
                        if ($s2 = $conn->prepare($sql2)) {
                            $s2->bind_param("iii", $order['id'], $order['id'], $order['id']);
                            $s2->execute();
                            $rr2 = $s2->get_result()->fetch_assoc();
                            $s2->close();
                            $replacementsReady = ($rr2['ready'] == $rr2['tot'] && $rr2['tot'] > 0 && $rr2['sc'] == 0);
                        }
                    }

                    // Ready to order count
                    $readyToOrderCount = 0;
                    if ($hasPOFiles) {
                        $sqlRto = "SELECT COUNT(*) as c FROM po_attachments WHERE order_id=? AND approval_status='approved' AND marked_as_ordered=0";
                        if ($sRto = $conn->prepare($sqlRto)) {
                            $sRto->bind_param("i", $order['id']);
                            $sRto->execute();
                            $readyToOrderCount = (int) $sRto->get_result()->fetch_assoc()['c'];
                            $sRto->close();
                        }
                    }

                    // All POs ordered check (for Track Items button)
                    $showTrackItems = false;
                    if (in_array($order['status'], ['processing', 'Ready for Pickup', 'Picked Up', 'Delivered', 'Out for Delivery', 'completed']) && $hasPOFiles) {
                        $sqlPo = "SELECT COUNT(*) as tot, SUM(CASE WHEN marked_as_ordered=1 THEN 1 ELSE 0 END) as ord FROM po_attachments WHERE order_id=?";
                        if ($sPo = $conn->prepare($sqlPo)) {
                            $sPo->bind_param("i", $order['id']);
                            $sPo->execute();
                            $rPo = $sPo->get_result()->fetch_assoc();
                            $sPo->close();
                            $showTrackItems = ((int) $rPo['tot'] > 0 && (int) $rPo['tot'] === (int) $rPo['ord']) || $order['status'] === 'completed';
                        }
                    }
                    ?>

                    <!-- Order Card -->
                    <div
                        class="bg-white rounded-xl border <?= $hasReplacements ? 'border-red-200 border-l-4 border-l-red-400' : 'border-gray-200' ?> overflow-hidden">

                        <!-- Card top: order info + actions -->
                        <div class="p-5 flex flex-col md:flex-row md:items-start gap-4">

                            <!-- Left: icon + info -->
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div
                                    class="relative shrink-0 w-10 h-10 rounded-lg bg-gray-800 flex items-center justify-center">
                                    <i class="fas fa-dolly text-white text-sm"></i>
                                    <?php if ($hasReplacements): ?>
                                        <span
                                            class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center ring-2 ring-white animate-pulse">
                                            <i class="fas fa-arrows-rotate text-white" style="font-size:7px"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <!-- Order ID + badges -->
                                    <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                        <span class="text-base font-semibold text-gray-900">Order
                                            #<?= htmlspecialchars($order['id']) ?></span>
                                        <span class="<?= $statusClass ?> text-xs font-medium px-2 py-0.5 rounded-full">
                                            <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                        </span>
                                        <?php if ($hasReplacements): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-red-100 text-red-700 text-xs font-medium px-2 py-0.5 rounded-full animate-pulse">
                                                <i class="fas fa-arrows-rotate text-xs"></i>
                                                <?= $approved_replacements_count ?>
                                                Replacement<?= $approved_replacements_count > 1 ? 's' : '' ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($unresolved_defects_count > 0): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-medium px-2 py-0.5 rounded-full animate-pulse">
                                                <i class="fas fa-triangle-exclamation text-xs"></i>
                                                <?= $unresolved_defects_count ?>
                                                Defect<?= $unresolved_defects_count > 1 ? 's' : '' ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($isReadyForSchedule): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                                <i class="fas fa-calendar-check text-xs"></i> Ready to Schedule
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($replacementsReady): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-rose-100 text-rose-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                                <i class="fas fa-arrows-rotate text-xs"></i> Replacements Ready
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Meta -->
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                                        <span class="flex items-center gap-1"><i class="fas fa-user text-xs"></i>
                                            <?= htmlspecialchars($order['customer_name']) ?></span>
                                        <span class="flex items-center gap-1 truncate"><i class="fas fa-envelope text-xs"></i>
                                            <?= htmlspecialchars($order['email']) ?></span>
                                        <span class="flex items-center gap-1"><i class="fas fa-clock text-xs"></i>
                                            <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                                    </div>
                                    <!-- Item tracking statuses -->
                                    <?php if ($statuses && $statuses['total_items'] > 0 && ($statuses['pending'] > 0 || $statuses['processing'] > 0)): ?>
                                        <div class="flex gap-2 mt-2">
                                            <?php if ($statuses['pending'] > 0): ?>
                                                <span class="bg-amber-100 text-amber-700 text-xs font-medium px-2 py-1 rounded-lg">
                                                    <?= $statuses['pending'] ?> Pending
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($statuses['processing'] > 0): ?>
                                                <span class="bg-blue-100 text-blue-700 text-xs font-medium px-2 py-1 rounded-lg">
                                                    <?= $statuses['processing'] ?> Ready
                                                    Item<?= $statuses['processing'] > 1 ? 's' : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Right: amount + progress + actions -->
                            <div class="flex flex-col items-end gap-3 shrink-0">

                                <!-- Amount -->
                                <span
                                    class="text-lg font-bold text-gray-900">₱<?= number_format((float) $order['total'], 2) ?></span>

                                <!-- Supplier assignment progress -->
                                <div class="text-right w-36">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs text-gray-500">Supplier Assignment</span>
                                        <span class="text-xs font-semibold <?= $pctColor ?>"><?= $assignmentPct ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full <?= $barColor ?> transition-all duration-300"
                                            style="width:<?= $assignmentPct ?>%"></div>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1"><?= $assignedItems ?>/<?= $item_count ?> assigned</p>
                                    <?php if ($hasPOFiles): ?>
                                        <p class="text-xs text-green-600 mt-1">
                                            <i class="fas fa-file-alt mr-1"></i><?= $po_attachment_count ?> P.O.
                                            file<?= $po_attachment_count > 1 ? 's' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($readyToOrderCount > 0): ?>
                                        <span
                                            class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 text-xs font-medium px-2 py-0.5 rounded-full mt-1 animate-pulse">
                                            <i class="fas fa-bell text-xs"></i> <?= $readyToOrderCount ?> Ready to Order
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Action buttons -->
                                <div class="flex flex-wrap gap-2 justify-end">

                                    <?php if ($hasReplacements): ?>
                                        <a href="<?= BASE_URL ?>/warehousestafftrackorder?order_id=<?= urlencode($order['id']) ?>"
                                            class="relative inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors animate-pulse">
                                            <i class="fas fa-arrows-rotate"></i>
                                            Replacements (<?= $approved_replacements_count ?>)
                                            <span
                                                class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-400 rounded-full ring-2 ring-white animate-ping"></span>
                                        </a>
                                    <?php endif; ?>

                                    <a href="<?= BASE_URL ?>/warehousestaffpomanagement?order_id=<?= urlencode($order['id']) ?>"
                                        class="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                                        <i class="fas fa-cogs"></i>
                                        <?= $hasPOFiles ? 'Manage P.O.' : 'Generate P.O.' ?>
                                    </a>

                                    <?php if ($hasPOFiles): ?>
                                        <a href="<?= BASE_URL ?>/warehousestaffpomanagement?order_id=<?= urlencode($order['id']) ?>"
                                            class="inline-flex items-center gap-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                                            <i class="fas fa-eye"></i>
                                            View P.O. (<?= $po_attachment_count ?>)
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($showTrackItems): ?>
                                        <a href="<?= BASE_URL ?>/warehousestafftrackorder?order_id=<?= urlencode($order['id']) ?>"
                                            class="inline-flex items-center gap-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                                            <i class="fas fa-route"></i>
                                            Track Items
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($unresolved_defects_count > 0): ?>
                                        <a href="view_defects.php?order_id=<?= urlencode($order['id']) ?>"
                                            class="relative inline-flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors animate-pulse">
                                            <i class="fas fa-triangle-exclamation"></i>
                                            Defects (<?= $unresolved_defects_count ?>)
                                            <span
                                                class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-orange-400 rounded-full ring-2 ring-white animate-ping"></span>
                                        </a>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Empty state -->
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-inbox text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-700 mb-1">No Orders Found</h3>
                <p class="text-sm text-gray-400 mb-4">
                    <?php if ($show_replacements): ?>No orders with approved replacement requests.
                    <?php elseif ($show_ready_to_order): ?>No orders with P.O. files ready to order.
                    <?php elseif ($show_ready_for_schedule): ?>No orders ready for delivery scheduling.
                    <?php elseif ($show_defects): ?>No orders with unresolved defects.
                    <?php else: ?>You don't have any assigned orders right now.<?php endif; ?>
                </p>
                <?php if ($show_replacements || $show_ready_for_schedule || $show_defects || $show_ready_to_order): ?>
                    <a href="?" class="text-sm text-gray-600 hover:text-gray-900 font-medium underline underline-offset-2">
                        <i class="fas fa-arrow-left mr-1"></i> View All Orders
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>