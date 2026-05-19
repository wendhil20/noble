<?php
//warehouse_head_assignment_A.php - For managers to assign warehouse employees to orders
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$user_id     = null;
$fullname    = '';
$user_lvl    = '';
$is_head     = false;
$sessionUser = $_SESSION['noble_user'];

if (is_array($sessionUser)) {
    $user_id  = (int) ($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0);
    $fullname = $sessionUser['fullname'] ?? $sessionUser['name'] ?? '';
    $user_lvl = $sessionUser['lvl'] ?? $sessionUser['level'] ?? '';
    $is_head  = isset($sessionUser['is_head']) && (int) $sessionUser['is_head'] === 1;
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
                    $user_id  = (int) $r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                    $is_head  = (int) $r['is_head'] === 1;
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
                    $user_id  = (int) $r['id'];
                    $fullname = $r['fullname'];
                    $user_lvl = $r['lvl'];
                    $is_head  = (int) $r['is_head'] === 1;
                }
            }
        }
    } else {
        $lookup_field = !empty($sessionUser['email']) ? 'email' : 'id';
        $lookup_val   = !empty($sessionUser['email']) ? $sessionUser['email'] : (int) $sessionUser['id'];
        $sql = "SELECT id, fullname, lvl, is_head FROM nobleaccount WHERE $lookup_field = ? LIMIT 1";
        if ($s = $conn->prepare($sql)) {
            $type = is_int($lookup_val) ? 'i' : 's';
            $s->bind_param($type, $lookup_val);
            $s->execute();
            $r = $s->get_result()->fetch_assoc();
            $s->close();
            if ($r) {
                $user_id  = (int) $r['id'];
                $fullname = $r['fullname'];
                $user_lvl = $r['lvl'];
                $is_head  = (int) $r['is_head'] === 1;
            }
        }
    }
}

if (empty($user_id) || empty($user_lvl)) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php");
    exit();
}

if (!$is_head) {
    header("Location: order_list.php");
    exit();
}

// ─── Filters ──────────────────────────────────────────────────────────────────
$search_query  = isset($_GET['search'])        ? trim($_GET['search'])  : '';
$date_from     = isset($_GET['date_from'])     ? $_GET['date_from']     : '';
$date_to       = isset($_GET['date_to'])       ? $_GET['date_to']       : '';
$view_assigned = isset($_GET['view_assigned']) ? $_GET['view_assigned'] : '';

// ─── Build main query ─────────────────────────────────────────────────────────
$whereConditions = [];
$params          = [];
$types           = '';

if (!empty($view_assigned)) {
    $whereConditions[] = "o.status = 'ongoing' AND o.warehouse_employee_id = ?";
    $params[]          = $view_assigned;
    $types            .= 'i';
} else {
    $whereConditions[] = "o.status = 'ongoing' AND o.warehouse_employee_id IS NULL";
}

if (!empty($search_query)) {
    $whereConditions[] = "(o.customer_name LIKE ? OR o.email LIKE ? OR o.id LIKE ?)";
    $sp       = "%$search_query%";
    $params[] = $sp;
    $params[] = $sp;
    $params[] = $sp;
    $types   .= 'sss';
}
if (!empty($date_from)) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $params[]          = $date_from;
    $types            .= 's';
}
if (!empty($date_to)) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $params[]          = $date_to;
    $types            .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$ordersQuery = "
    SELECT
        o.id, o.customer_name, o.email, o.created_at, o.status, o.total,
        o.warehouse_employee_id,
        wh.fullname  AS warehouse_employee_name,
        wh.email     AS warehouse_employee_email,
        COUNT(oi.id) AS item_count,
        SUM(CASE WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN 1 ELSE 0 END) AS assigned_linked_count,
        SUM(CASE WHEN oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL   THEN 1 ELSE 0 END) AS assigned_manual_count
    FROM orders o
    LEFT JOIN order_items  oi ON o.id = oi.order_id
    LEFT JOIN nobleaccount wh ON o.warehouse_employee_id = wh.id
        AND wh.lvl = 'warehouse' AND wh.is_head = 0 AND wh.subrole = 'warehouse_staff'
    $whereClause
    GROUP BY o.id, o.customer_name, o.email, o.created_at, o.status, o.total,
             o.warehouse_employee_id, wh.fullname, wh.email
    ORDER BY CASE WHEN o.warehouse_employee_id IS NULL THEN 0 ELSE 1 END, o.created_at DESC
";
$stmt = $conn->prepare($ordersQuery);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Counts ───────────────────────────────────────────────────────────────────
$totalOngoingOrders = (int) $conn->query("SELECT COUNT(*) FROM orders WHERE status='ongoing'")->fetch_row()[0];
$unassignedCount    = (int) $conn->query("SELECT COUNT(*) FROM orders WHERE warehouse_employee_id IS NULL AND status='ongoing'")->fetch_row()[0];

$warehouseEmployees = $conn->query("
    SELECT id, fullname, email FROM nobleaccount
    WHERE lvl='warehouse' AND status='active' AND is_head=0 AND subrole='warehouse_staff'
    ORDER BY fullname ASC
")->fetch_all(MYSQLI_ASSOC);

$warehouseCounts = $conn->query("
    SELECT wh.id, wh.fullname, COUNT(o.id) AS order_count
    FROM nobleaccount wh
    LEFT JOIN orders o ON wh.id = o.warehouse_employee_id AND o.status='ongoing'
    WHERE wh.lvl='warehouse' AND wh.status='active' AND wh.is_head=0 AND wh.subrole='warehouse_staff'
    GROUP BY wh.id, wh.fullname
    ORDER BY wh.fullname ASC
")->fetch_all(MYSQLI_ASSOC);

$unassignedInFilterCount = count(array_filter($orders, fn($o) => is_null($o['warehouse_employee_id'])));

$currentStaffName = '';
if (!empty($view_assigned)) {
    foreach ($warehouseEmployees as $emp) {
        if ($emp['id'] == $view_assigned) {
            $currentStaffName = $emp['fullname'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Assignment</title>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-[96%] mx-auto px-4 py-6">

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="bg-blue-600 p-2.5 rounded-lg shrink-0">
                 <i class="fa-solid fa-warehouse text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Warehouse Assignment</h1>
                    <p class="text-sm text-gray-500 flex items-center gap-2 mt-0.5">
                        <?php if (!empty($view_assigned)): ?>
                            Viewing orders assigned to <strong><?= htmlspecialchars($currentStaffName) ?></strong>
                        <?php else: ?>
                            Assign warehouse staff to ongoing orders
                        <?php endif; ?>
                        <span
                            class="inline-flex items-center gap-1 bg-red-50 text-red-700 text-xs font-medium px-2 py-0.5 rounded-full border border-red-200">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            Head access only
                        </span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-600 bg-white border border-gray-200 px-3 py-1.5 rounded-lg font-medium">
                    <?= count($orders) ?> orders
                </span>
                <?php if (!empty($view_assigned)): ?>
                    <a href="<?= BASE_URL; ?>/warehouseassignment"
                        class="flex items-center gap-2 text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-medium transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to unassigned
                    </a>
                <?php endif; ?>
                <a href="<?= BASE_URL; ?>/warehousedashboard"
                    class="flex items-center gap-2 text-sm bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    All orders
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div
                class="bg-white border <?= $unassignedCount > 0 ? 'border-red-300 ring-1 ring-red-200' : 'border-gray-200' ?> rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="bg-red-50 p-2 rounded-lg shrink-0">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Unassigned</p>
                        <p class="text-2xl font-bold text-red-600"><?= $unassignedCount ?></p>
                        <?php if ($unassignedCount > 0): ?>
                            <p class="text-xs text-red-500 mt-0.5">Needs attention</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-50 p-2 rounded-lg shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Ongoing orders</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $totalOngoingOrders ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="bg-green-50 p-2 rounded-lg shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Warehouse staff</p>
                        <p class="text-2xl font-bold text-green-600"><?= count($warehouseEmployees) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="bg-purple-50 p-2 rounded-lg shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Assigned</p>
                        <p class="text-2xl font-bold text-purple-600"><?= $totalOngoingOrders - $unassignedCount ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staff overview -->
        <?php if (empty($view_assigned)): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-50 p-2 rounded-lg">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Staff overview</h2>
                            <p class="text-xs text-gray-500">Click a staff card to view their orders</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1.5"><span
                                class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>Unassigned:
                            <?= $unassignedCount ?></span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>Assigned:
                            <?= $totalOngoingOrders - $unassignedCount ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                    <!-- Unassigned card -->
                    <div
                        class="border-2 border-red-200 bg-red-50 rounded-xl p-4 cursor-pointer hover:border-red-400 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <div class="bg-red-100 p-2 rounded-lg">
                                <svg class="w-4 h-4 text-red-700" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-red-900">Unassigned</p>
                        <p class="text-xs text-red-600 mt-0.5"><?= $unassignedCount ?> orders</p>
                        <?php if ($unassignedCount > 0): ?>
                            <p class="text-xs text-red-500 mt-2 font-medium">⚠ Immediate attention</p>
                        <?php endif; ?>
                    </div>

                    <!-- Per-staff cards -->
                    <?php foreach ($warehouseCounts as $staff): ?>
                        <a href="?view_assigned=<?= $staff['id'] ?>"
                            class="block border border-gray-200 bg-white rounded-xl p-4 hover:border-blue-300 hover:shadow-sm transition-all cursor-pointer">
                            <div class="flex items-center justify-between mb-2">
                                <div
                                    class="w-8 h-8 rounded-full bg-<?= $staff['order_count'] > 0 ? 'blue' : 'gray' ?>-100 flex items-center justify-center text-xs font-semibold text-<?= $staff['order_count'] > 0 ? 'blue' : 'gray' ?>-700">
                                    <?= strtoupper(substr($staff['fullname'], 0, 2)) ?>
                                </div>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 truncate"
                                title="<?= htmlspecialchars($staff['fullname']) ?>"><?= htmlspecialchars($staff['fullname']) ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5"><?= $staff['order_count'] ?>
                                order<?= $staff['order_count'] !== 1 ? 's' : '' ?></p>
                            <?php if ($staff['order_count'] > 0): ?>
                                <div class="mt-2.5 w-full bg-gray-100 rounded-full h-1.5">
                                    <div class="bg-blue-500 h-1.5 rounded-full"
                                        style="width:<?= min(100, round(($staff['order_count'] / max($totalOngoingOrders, 1)) * 100)) ?>%">
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= round(($staff['order_count'] / max($totalOngoingOrders, 1)) * 100, 1) ?>% of total</p>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 mt-2">Available</p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Unassigned alert + bulk assign -->
        <?php if ($unassignedInFilterCount > 0 && empty($view_assigned)): ?>
            <div
                class="bg-red-50 border border-red-200 rounded-xl px-5 py-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="bg-red-100 p-2 rounded-lg shrink-0">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-red-800">Unassigned orders alert</p>
                        <p class="text-xs text-red-600"><?= $unassignedInFilterCount ?> ongoing
                            order<?= $unassignedInFilterCount !== 1 ? 's' : '' ?>
                            need<?= $unassignedInFilterCount === 1 ? 's' : '' ?> warehouse assignment</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button onclick="selectAllUnassigned()"
                        class="text-xs font-medium bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Select all unassigned
                    </button>
                    <select id="bulkWarehouseSelect"
                        class="text-xs border border-red-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-red-400">
                        <option value="">Select staff...</option>
                        <?php foreach ($warehouseEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="bulkAssignSelected()"
                        class="text-xs font-medium bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Bulk assign
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
            <form method="GET">
                <?php if (!empty($view_assigned)): ?>
                    <input type="hidden" name="view_assigned" value="<?= htmlspecialchars($view_assigned) ?>">
                <?php endif; ?>

                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <?php if (!empty($view_assigned)): ?>
                        <span class="text-xs font-medium bg-blue-600 text-white px-3 py-1 rounded-full">
                            Assigned to <?= htmlspecialchars($currentStaffName) ?> (<?= count($orders) ?>)
                        </span>
                    <?php else: ?>
                        <span class="text-xs font-medium bg-blue-600 text-white px-3 py-1 rounded-full">
                            Unassigned ongoing (<?= $unassignedCount ?>)
                        </span>
                        <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Orders disappear once assigned
                        </span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>"
                            placeholder="Order ID, customer, email..."
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Date from</label>
                        <input type="date" name="date_from" value="<?= $date_from ?>"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Date to</label>
                        <input type="date" name="date_to" value="<?= $date_to ?>"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit"
                            class="flex-1 flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Search
                        </button>
                        <?php if (!empty($search_query) || !empty($date_from) || !empty($date_to)): ?>
                            <a href="warehouse_head_assignment_A.php<?= !empty($view_assigned) ? '?view_assigned=' . $view_assigned : '' ?>"
                                class="flex items-center justify-center gap-1 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk actions bar -->
        <div id="bulkActionsBar" class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-3 mb-4 hidden">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-sm text-blue-800 font-medium">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span id="selectedCount">0 orders selected</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <select id="bulkActionWarehouseSelect"
                        class="text-xs border border-blue-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-400">
                        <option value="">Select staff...</option>
                        <?php foreach ($warehouseEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="bulkAssignSelected()"
                        class="text-xs font-medium bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Assign
                    </button>
                    <button onclick="bulkUnassignSelected()"
                        class="text-xs font-medium bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                        </svg>
                        Unassign
                    </button>
                    <button onclick="clearSelection()"
                        class="text-xs font-medium bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded-lg transition-colors">
                        Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Orders list -->
        <?php if (!empty($orders)): ?>
            <div class="space-y-3">
                <?php foreach ($orders as $order):
                    $isUnassigned = is_null($order['warehouse_employee_id']);
                    ?>
                    <div
                        class="bg-white border <?= $isUnassigned ? 'border-red-200' : 'border-blue-100' ?> rounded-xl p-5 hover:shadow-sm transition-shadow">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">

                            <!-- Left: checkbox + info -->
                            <div class="flex items-start gap-3">
                                <input type="checkbox"
                                    class="order-checkbox mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    data-order-id="<?= $order['id'] ?>" data-is-unassigned="<?= $isUnassigned ? '1' : '0' ?>"
                                    onchange="updateBulkActions()">

                                <div class="<?= $isUnassigned ? 'bg-red-50' : 'bg-blue-50' ?> p-2 rounded-lg shrink-0">
                                    <svg class="w-4 h-4 <?= $isUnassigned ? 'text-red-500' : 'text-blue-500' ?>" fill="none"
                                        stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>

                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-bold text-gray-900">Order #<?= $order['id'] ?></span>
                                        <?php if ($isUnassigned): ?>
                                            <span
                                                class="text-xs font-semibold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Unassigned</span>
                                        <?php else: ?>
                                            <span
                                                class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Assigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-1.5 space-y-1 text-xs text-gray-500">
                                        <p class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <?= htmlspecialchars($order['customer_name']) ?>
                                        </p>
                                        <p class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <?= htmlspecialchars($order['email']) ?>
                                        </p>
                                        <p class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                        </p>
                                        <p class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            <?php if ($order['warehouse_employee_id']): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 bg-green-50 text-green-700 px-2 py-0.5 rounded-full font-medium">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <?= htmlspecialchars($order['warehouse_employee_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center gap-1 bg-red-50 text-red-600 px-2 py-0.5 rounded-full font-medium">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                    Unassigned
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: total + assign -->
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 lg:gap-6 ml-7 lg:ml-0">
                                <div class="text-right">
                                    <p class="text-base font-bold text-blue-700">₱<?= number_format($order['total'], 2) ?></p>
                                    <p class="text-xs text-gray-400 mt-0.5"><?= $order['item_count'] ?>
                                        item<?= $order['item_count'] !== 1 ? 's' : '' ?></p>
                                    <span
                                        class="text-xs font-medium bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">Ongoing</span>
                                </div>

                                <!-- Assign dropdown + actions -->
                                <div class="flex flex-col gap-2 min-w-[220px]">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Assign warehouse
                                            staff</label>
                                        <select onchange="assignWarehouse(<?= $order['id'] ?>, this.value)"
                                            class="w-full text-sm border <?= $order['warehouse_employee_id'] ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' ?> rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-400 text-gray-800">
                                            <option value="">Select staff...</option>
                                            <?php foreach ($warehouseEmployees as $emp): ?>
                                                <option value="<?= $emp['id'] ?>" <?= ($order['warehouse_employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($emp['fullname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?php if (!$order['warehouse_employee_id']): ?>
                                            <span class="text-xs text-red-500 font-medium flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Requires assignment
                                            </span>
                                            <button onclick="quickAssign(<?= $order['id'] ?>)"
                                                class="ml-auto text-xs font-medium bg-red-600 hover:bg-red-700 text-white px-2.5 py-1 rounded-lg transition-colors flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                Quick assign
                                            </button>
                                        <?php else: ?>
                                            <span class="text-xs text-green-600 font-medium flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Assigned
                                            </span>
                                            <button onclick="unassignOrder(<?= $order['id'] ?>)"
                                                class="ml-auto text-xs font-medium bg-orange-500 hover:bg-orange-600 text-white px-2.5 py-1 rounded-lg transition-colors flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                                                </svg>
                                                Unassign
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
            <!-- Empty state -->
            <div class="bg-white border border-gray-200 rounded-xl p-16 text-center">
                <div class="flex flex-col items-center gap-3 text-gray-400">
                    <svg class="w-14 h-14 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-gray-600">No orders found</p>
                        <p class="text-xs text-gray-400 mt-1">
                            <?php if (!empty($view_assigned)): ?>
                                No orders are currently assigned to <?= htmlspecialchars($currentStaffName) ?>.
                            <?php elseif (!empty($search_query) || !empty($date_from) || !empty($date_to)): ?>
                                No unassigned orders match your current filters.
                            <?php else: ?>
                                All ongoing orders have been assigned.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search_query) || !empty($date_from) || !empty($date_to)): ?>
                            <a href="warehouse_head_assignment_A.php<?= !empty($view_assigned) ? '?view_assigned=' . $view_assigned : '' ?>"
                                class="inline-block mt-3 text-xs text-blue-600 hover:underline font-medium">
                                Clear filters
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick assign modal -->
    <div id="quickAssignModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 px-4">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="bg-blue-50 p-2 rounded-lg">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900">Quick assign order</h3>
                </div>
                <button onclick="closeQuickAssignModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-xs text-gray-500 mb-3">Select a warehouse staff member for Order #<span
                    id="quickAssignOrderId" class="font-semibold text-gray-800"></span></p>
            <select id="quickAssignSelect"
                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-blue-300 text-gray-800">
                <option value="">Select warehouse staff...</option>
                <?php foreach ($warehouseEmployees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['fullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <button onclick="executeQuickAssign()"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Assign
                </button>
                <button onclick="closeQuickAssignModal()"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Toast container -->
    <div id="toastContainer" class="fixed top-5 right-5 z-50 space-y-2 pointer-events-none"></div>

    <script>
        // All fetch calls now point to the dedicated AJAX file
        const AJAX_URL = '<?= BASE_URL; ?>/warehouseheadassignmentajax';

        let selectedOrders = [];
        let quickAssignOrderId = null;

        function assignWarehouse(orderId, warehouseEmployeeId) {
            const select = event.target;
            select.disabled = true;
            fetch(AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=assign_warehouse&order_id=${orderId}&warehouse_employee_id=${warehouseEmployeeId || ''}`
            })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) setTimeout(() => location.reload(), 900);
                })
                .catch(() => showToast('An error occurred', 'error'))
                .finally(() => select.disabled = false);
        }

        function unassignOrder(orderId) {
            if (confirm('Unassign this order?')) assignWarehouse(orderId, '');
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
            const id = document.getElementById('quickAssignSelect').value;
            if (!id) { showToast('Please select a staff member', 'error'); return; }
            assignWarehouse(quickAssignOrderId, id);
            closeQuickAssignModal();
        }

        function updateBulkActions() {
            selectedOrders = Array.from(document.querySelectorAll('.order-checkbox:checked'))
                .map(cb => parseInt(cb.dataset.orderId));
            const bar = document.getElementById('bulkActionsBar');
            document.getElementById('selectedCount').textContent =
                `${selectedOrders.length} order${selectedOrders.length !== 1 ? 's' : ''} selected`;
            bar.classList.toggle('hidden', selectedOrders.length === 0);
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
            const empId = document.getElementById('bulkActionWarehouseSelect')?.value
                || document.getElementById('bulkWarehouseSelect')?.value;
            if (!selectedOrders.length) { showToast('No orders selected', 'error'); return; }
            if (!empId)                 { showToast('Please select a staff member', 'error'); return; }
            if (!confirm(`Assign ${selectedOrders.length} order(s) to selected staff?`)) return;
            fetch(AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=bulk_assign&order_ids=${JSON.stringify(selectedOrders)}&warehouse_employee_id=${empId}`
            })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) setTimeout(() => location.reload(), 900);
                })
                .catch(() => showToast('An error occurred', 'error'));
        }

        function bulkUnassignSelected() {
            if (!selectedOrders.length) { showToast('No orders selected', 'error'); return; }
            if (!confirm(`Unassign ${selectedOrders.length} order(s)?`)) return;
            fetch(AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=bulk_unassign&order_ids=${JSON.stringify(selectedOrders)}`
            })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) setTimeout(() => location.reload(), 900);
                })
                .catch(() => showToast('An error occurred', 'error'));
        }

        function showToast(message, type) {
            const el = document.createElement('div');
            el.className = `pointer-events-auto flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg text-sm font-medium transition-all duration-300 opacity-0 translate-y-1 ${type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`;
            el.innerHTML = `<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${type === 'success' ? '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' : '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>'}</svg>${message}`;
            document.getElementById('toastContainer').appendChild(el);
            requestAnimationFrame(() => { el.classList.remove('opacity-0', 'translate-y-1'); });
            setTimeout(() => {
                el.classList.add('opacity-0', 'translate-y-1');
                setTimeout(() => el.remove(), 300);
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('quickAssignModal').addEventListener('click', function (e) {
                if (e.target === this) closeQuickAssignModal();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') closeQuickAssignModal();
            });
        });
    </script>
</body>

</html>