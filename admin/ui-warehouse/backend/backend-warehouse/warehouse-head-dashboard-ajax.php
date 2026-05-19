<?php
// warehouse-head-dashboard-ajax.php
// AJAX endpoint - returns a hash + data for smart polling
// Only responds to XHR requests

header('Content-Type: application/json');

// Block direct non-AJAX access
if (
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['warehouse']);

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['error' => 'Unauthenticated']);
    exit();
}

// --- Resolve user (same logic as main dashboard) ---
$user_id = null;
$is_head = false;
$sessionUser = $_SESSION['noble_user'];

if (is_array($sessionUser)) {
    $user_id = (int) ($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0);
    $is_head = isset($sessionUser['is_head']) && (int) $sessionUser['is_head'] === 1;
}

if (empty($user_id) && !is_array($sessionUser)) {
    $lookupValue = $sessionUser;
    if (filter_var($lookupValue, FILTER_VALIDATE_EMAIL)) {
        $sql = "SELECT id, is_head FROM nobleaccount WHERE email = ? LIMIT 1";
    } elseif (ctype_digit((string) $lookupValue)) {
        $sql = "SELECT id, is_head FROM nobleaccount WHERE id = ? LIMIT 1";
    }
    if (!empty($sql) && $s = $conn->prepare($sql)) {
        $s->bind_param(filter_var($lookupValue, FILTER_VALIDATE_EMAIL) ? "s" : "i", $lookupValue);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) {
            $user_id = (int) $r['id'];
            $is_head = (int) $r['is_head'] === 1;
        }
    }
}

if (!$is_head) {
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// --- Collect all data needed for hashing and rendering ---

// 1. Stats
$totalOrders = 0;
$unassignedCount = 0;
$replacementCount = 0;
$readyForScheduleCount = 0;
$statusCounts = [];

$statsResult = $conn->query("SELECT status, COUNT(*) as count FROM orders WHERE status != 'pending' GROUP BY status");
if ($statsResult) {
    foreach ($statsResult->fetch_all(MYSQLI_ASSOC) as $row) {
        $statusCounts[$row['status']] = (int) $row['count'];
        $totalOrders += (int) $row['count'];
    }
}

$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE (warehouse_employee_id IS NULL OR warehouse_employee_id = 0) AND status = 'Ongoing'");
$unassignedCount = $r ? (int) $r->fetch_assoc()['c'] : 0;

$r = $conn->query("SELECT COUNT(DISTINCT o.id) as c FROM orders o WHERE EXISTS (SELECT 1 FROM replacement_requests rr WHERE rr.order_id = o.id AND rr.status = 'approved')");
$replacementCount = $r ? (int) $r->fetch_assoc()['c'] : 0;

$r = $conn->query("
    SELECT COUNT(DISTINCT o.id) as c FROM orders o
    WHERE o.status IN ('processing','Ready for Pickup','Out for Delivery')
    AND NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id)
    AND (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.tracking_status = 'In Warehouse')
     = (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id)
    AND (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0
");
$readyForScheduleCount = $r ? (int) $r->fetch_assoc()['c'] : 0;

// 2. Employee workload
$employeeStats = [];
$empResult = $conn->query("
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
if ($empResult) {
    $employeeStats = $empResult->fetch_all(MYSQLI_ASSOC);
}

// 3. Orders list (respects GET filters passed from client)
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$employee_filter = isset($_GET['employee']) ? (int) $_GET['employee'] : 0;
$show_replacements = !empty($_GET['replacements']);
$show_ready_for_schedule = !empty($_GET['ready_schedule']);
$show_unassigned = !empty($_GET['unassigned']);

$whereParts = ["1=1", "o.status != 'pending'"];
$params = [];
$types = '';

if ($show_unassigned) {
    $whereParts[] = "(o.warehouse_employee_id IS NULL OR o.warehouse_employee_id = 0)";
    $whereParts[] = "o.status = 'Ongoing'";
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
    $whereParts[] = "o.status IN ('processing','Ready for Pickup','Out for Delivery')";
    $whereParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.tracking_status = 'In Warehouse') = (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id)";
    $whereParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereParts);

$orders = [];
$ordersSql = "
    SELECT o.id, o.customer_name, o.email, o.created_at, o.status, o.total,
           o.warehouse_employee_id,
           na.fullname as assigned_employee_name,
           COUNT(DISTINCT oi.id) as item_count,
           COUNT(DISTINCT CASE
               WHEN (oi.supplier_id IS NOT NULL AND oi.supplier_id > 0)
                 OR (oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '')
               THEN oi.id END) as assigned_count,
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

if ($stmt = $conn->prepare($ordersSql)) {
    if (!empty($params)) {
        // Dynamic bind
        $bind_names = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'b' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res)
        $orders = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// --- Build payload ---
$payload = [
    'stats' => [
        'totalOrders' => $totalOrders,
        'unassignedCount' => $unassignedCount,
        'replacementCount' => $replacementCount,
        'readyForScheduleCount' => $readyForScheduleCount,
        'statusCounts' => $statusCounts,
        'employeeStats' => $employeeStats,
    ],
    'orders' => $orders,
];

// --- Generate a lightweight hash of the payload ---
// Client sends its current hash; if same, we return {changed: false} only
$hash = md5(json_encode($payload));
$clientHash = isset($_GET['hash']) ? trim($_GET['hash']) : '';

if ($clientHash !== '' && $clientHash === $hash) {
    // Nothing changed — send minimal response
    echo json_encode(['changed' => false, 'hash' => $hash]);
    exit();
}

// Data changed (or first load) — send full payload
$payload['changed'] = true;
$payload['hash'] = $hash;

echo json_encode($payload);