<?php
// warehouse_staff_management_main.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
// keep the same role guard if you want only specific roles to access this page
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);
// Either warehouse receiver or warehouse keeper can access
require_subrole(['warehouse_staff']);

// Ensure session exists
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// --- Resolve logged-in user robustly ---
$user_id = null;
$fullname = '';
$user_lvl = '';
$is_head = false; // ADD THIS LINE

$sessionUser = $_SESSION['noble_user'];

if (is_array($sessionUser)) {
    if (isset($sessionUser['id'])) {
        $user_id = (int)$sessionUser['id'];
    } elseif (isset($sessionUser['user_id'])) {
        $user_id = (int)$sessionUser['user_id'];
    }
    $fullname = $sessionUser['fullname'] ?? $sessionUser['name'] ?? '';
    $user_lvl = $sessionUser['lvl'] ?? $sessionUser['level'] ?? '';
    $is_head = isset($sessionUser['is_head']) && (int)$sessionUser['is_head'] === 1; // ADD THIS LINE
}

if (empty($user_id) || empty($user_lvl)) {
    // Try lookup by numeric or email stored in session
    if (!is_array($sessionUser)) {
        if (ctype_digit((string)$sessionUser)) {
            $candidate = (int)$sessionUser;
            // MODIFY THIS QUERY TO INCLUDE is_head
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
                    $is_head = (int)$r['is_head'] === 1; // ADD THIS LINE
                }
            }
        } else {
            $candidate = (string)$sessionUser;
            // MODIFY THIS QUERY TO INCLUDE is_head
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
                    $is_head = (int)$r['is_head'] === 1; // ADD THIS LINE
                }
            }
        }
    } else {
        // if session array had email
        if (!empty($sessionUser['email'])) {
            $candidate = $sessionUser['email'];
            // MODIFY THIS QUERY TO INCLUDE is_head
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
                    $is_head = (int)$r['is_head'] === 1; // ADD THIS LINE
                }
            }
        } elseif (!empty($sessionUser['id'])) {
            $candidate = (int)$sessionUser['id'];
            // MODIFY THIS QUERY TO INCLUDE is_head
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
                    $is_head = (int)$r['is_head'] === 1; // ADD THIS LINE
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
$show_replacements = isset($_GET['replacements']) ? (bool)$_GET['replacements'] : false;
$show_ready_for_schedule = isset($_GET['ready_schedule']) ? (bool)$_GET['ready_schedule'] : false;
$show_defects = isset($_GET['defects']) ? (bool)$_GET['defects'] : false;
$show_ready_replacements = isset($_GET['ready_replacements']) ? (bool)$_GET['ready_replacements'] : false;
$show_ready_to_order = isset($_GET['ready_to_order']) ? (bool)$_GET['ready_to_order'] : false;

// --- Build WHERE conditions ---
// IMPORTANT: We ALWAYS restrict by warehouse_employee_id = logged in user
$whereParts = ["o.warehouse_employee_id = ?"];
$params = [$user_id];
$types = 'i';

// For "My Orders" (no status filter), only show orders that are NOT 100% assigned
if ($status_filter === '' && !$show_replacements && !$show_ready_for_schedule && !$show_defects && !$show_ready_replacements && !$show_ready_to_order) {
    $whereParts[] = "(
        (SELECT COUNT(*) FROM order_items oi_check 
         WHERE oi_check.order_id = o.id 
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

// Add defects filter - show orders with unresolved defects
if ($show_defects) {
    $whereParts[] = "EXISTS (SELECT 1 FROM defect_reports dr 
                     WHERE dr.order_id = o.id 
                     AND dr.status != 'resolved')";
}

// Add ready for schedule filter
if ($show_ready_for_schedule) {
    $whereParts[] = "NOT EXISTS (SELECT 1 FROM delivery_schedules ds WHERE ds.order_id = o.id)";
    $whereParts[] = "o.status IN ('processing', 'Ready for Pickup', 'Out for Delivery')";
    $whereParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.tracking_status = 'In Warehouse') = (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id)";
    $whereParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0";
}

// Add ready replacements filter
if ($show_ready_replacements) {
    $whereParts[] = "EXISTS (
        SELECT 1 FROM replacement_requests rr 
        WHERE rr.order_id = o.id 
        AND rr.status IN ('approved', 'processing', 'In Warehouse')
    )";
    $whereParts[] = "NOT EXISTS (
        SELECT 1 FROM delivery_schedules ds 
        WHERE ds.order_id = o.id 
        AND ds.item_type = 'replacement'
    )";
    $whereParts[] = "(
        SELECT COUNT(*) 
        FROM replacement_requests rr2 
        WHERE rr2.order_id = o.id 
        AND rr2.status = 'In Warehouse'
    ) = (
        SELECT COUNT(*) 
        FROM replacement_requests rr3 
        WHERE rr3.order_id = o.id 
        AND rr3.status IN ('approved', 'processing', 'In Warehouse')
    )";
    $whereParts[] = "(
        SELECT COUNT(*) 
        FROM replacement_requests rr4 
        WHERE rr4.order_id = o.id 
        AND rr4.status IN ('approved', 'processing', 'In Warehouse')
    ) > 0";
}

// Add ready to order filter
if ($show_ready_to_order) {
    $whereParts[] = "EXISTS (
        SELECT 1 FROM po_attachments pa 
        WHERE pa.order_id = o.id 
        AND pa.approval_status = 'approved'
        AND pa.marked_as_ordered = 0
    )";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereParts);

// --- Helper to bind params dynamically (call_user_func_array with references) ---
function bindParamsToStmt($stmt, $types, $params)
{
    if ($types === '' || empty($params)) return;
    $bind_names[] = $types;
    // create references
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

// --- Orders query (only orders assigned to this user) ---
// Updated to include P.O. attachment count and replacement requests count
$ordersSql = "
    SELECT 
        o.id,
        o.customer_name,
        o.email,
        o.created_at,
        o.status,
        o.total,
        COUNT(DISTINCT oi.id) as item_count,
        COUNT(DISTINCT CASE 
            WHEN (oi.supplier_id IS NOT NULL AND oi.supplier_id > 0) 
              OR (oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '') 
            THEN oi.id 
        END) as assigned_count,
        COUNT(DISTINCT poa.id) as po_attachment_count,
        COUNT(DISTINCT CASE WHEN rr.status IN ('approved', 'processing') THEN rr.id END) as approved_replacements_count,
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
$replacementOrdersCount = 0;

$statusSql = "SELECT status, COUNT(*) as count FROM orders WHERE warehouse_employee_id = ? GROUP BY status";
if ($stmt2 = $conn->prepare($statusSql)) {
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    if ($r2) $statusCounts = $r2->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
    // Count ongoing orders (not 100% assigned)
    $ongoingOrdersSql = "
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    WHERE o.warehouse_employee_id = ? 
    AND (
        (SELECT COUNT(*) FROM order_items oi_check 
         WHERE oi_check.order_id = o.id 
         AND ((oi_check.supplier_id IS NOT NULL AND oi_check.supplier_id > 0) 
              OR (oi_check.supplier_id = 0 AND oi_check.manual_supplier_name IS NOT NULL AND oi_check.manual_supplier_name != ''))
        ) < (SELECT COUNT(*) FROM order_items oi_total WHERE oi_total.order_id = o.id)
        OR (SELECT COUNT(*) FROM order_items oi_total WHERE oi_total.order_id = o.id) = 0
    )
";
    $ongoingOrdersCount = 0;
    if ($stmt_ongoing = $conn->prepare($ongoingOrdersSql)) {
        $stmt_ongoing->bind_param("i", $user_id);
        $stmt_ongoing->execute();
        $r_ongoing = $stmt_ongoing->get_result();
        if ($r_ongoing) {
            $ongoingResult = $r_ongoing->fetch_assoc();
            $ongoingOrdersCount = (int)$ongoingResult['count'];
        }
        $stmt_ongoing->close();
    }
}

// Count orders with approved OR processing replacement requests
$replacementSql = "
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    WHERE o.warehouse_employee_id = ? 
    AND EXISTS (SELECT 1 FROM replacement_requests rr 
                WHERE rr.order_id = o.id 
                AND rr.status IN ('approved', 'processing'))
";
if ($stmt3 = $conn->prepare($replacementSql)) {
    $stmt3->bind_param("i", $user_id);
    $stmt3->execute();
    $r3 = $stmt3->get_result();
    if ($r3) {
        $replacementResult = $r3->fetch_assoc();
        $replacementOrdersCount = (int)$replacementResult['count'];
    }
    $stmt3->close();
    // Count orders ready for scheduling
    $readyForScheduleSql = "
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    WHERE o.warehouse_employee_id = ? 
    AND o.status IN ('processing', 'Ready for Pickup', 'Out for Delivery')
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
    $readyForScheduleCount = 0;
    if ($stmt4 = $conn->prepare($readyForScheduleSql)) {
        $stmt4->bind_param("i", $user_id);
        $stmt4->execute();
        $r4 = $stmt4->get_result();
        if ($r4) {
            $readyResult = $r4->fetch_assoc();
            $readyForScheduleCount = (int)$readyResult['count'];
        }
        $stmt4->close();
    }
}

// Count orders with replacements ready for scheduling
$readyReplacementsSql = "
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    WHERE o.warehouse_employee_id = ? 
    AND EXISTS (
        SELECT 1 FROM replacement_requests rr 
        WHERE rr.order_id = o.id 
        AND rr.status IN ('approved', 'processing', 'In Warehouse')
    )
    AND NOT EXISTS (
        SELECT 1 FROM delivery_schedules ds 
        WHERE ds.order_id = o.id 
        AND ds.item_type = 'replacement'
    )
    AND (
        SELECT COUNT(*) 
        FROM replacement_requests rr2 
        WHERE rr2.order_id = o.id 
        AND rr2.status = 'In Warehouse'
    ) = (
        SELECT COUNT(*) 
        FROM replacement_requests rr3 
        WHERE rr3.order_id = o.id 
        AND rr3.status IN ('approved', 'processing', 'In Warehouse')
    )
    AND (
        SELECT COUNT(*) 
        FROM replacement_requests rr4 
        WHERE rr4.order_id = o.id 
        AND rr4.status IN ('approved', 'processing', 'In Warehouse')
    ) > 0
";
$readyReplacementsCount = 0;
if ($stmt_repl = $conn->prepare($readyReplacementsSql)) {
    $stmt_repl->bind_param("i", $user_id);
    $stmt_repl->execute();
    $r_repl = $stmt_repl->get_result();
    if ($r_repl) {
        $replReadyResult = $r_repl->fetch_assoc();
        $readyReplacementsCount = (int)$replReadyResult['count'];
    }
    $stmt_repl->close();
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
    <!-- Add this modal CSS and JS -->
    <style>
        .modal {
            transition: opacity 0.25s ease;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .pulse-notification {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .5;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    <!-- Header -->
    <div class="bg-transparent">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="bg-primary-500 p-3 rounded-lg">
                        <i class="fas fa-shopping-cart text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Orders Management</h1>
                        <p class="text-gray-600 mt-1">Only orders assigned to you are visible here</p>
                    </div>
                </div>
                <div class="text-right">
                    <!-- ADD HEAD BUTTON HERE -->
                    <?php if ($is_head): ?>
                        <div class="mt-2">
                            <a href="warehouse_head_assignment.php" class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-2 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg">
                                <i class="fas fa-crown"></i>
                                <span class="font-medium">Head Dashboard</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Modern Filters Section -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <form method="GET" class="space-y-4">
        
        <!-- Status Filter Tabs -->
        <div class="flex flex-wrap gap-2 mb-4">
            
            <!-- Incomplete Assignments -->
            <a href="?" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 inline-flex items-center gap-1.5 <?php echo ($status_filter === '' && !$show_replacements && !$show_ready_for_schedule && !$show_defects && !$show_ready_replacements) ? 'bg-primary-600 text-white shadow-sm' : 'bg-orange-100 text-orange-700 hover:bg-orange-200'; ?>">
                <i class="fas fa-tasks text-sm"></i>
                <span>Incomplete</span>
                <span class="ml-1 px-1.5 py-0.5 rounded text-xs <?php echo ($status_filter === '' && !$show_replacements && !$show_ready_for_schedule && !$show_defects && !$show_ready_replacements) ? 'bg-white/20' : 'bg-orange-200'; ?>">
                    <?php echo $ongoingOrdersCount; ?>
                </span>
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
                $isActive = ($status_filter === $status && !$show_replacements && !$show_ready_for_schedule && !$show_defects && !$show_ready_replacements);
            ?>
                <a href="?status=<?php echo urlencode($status); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 inline-flex items-center gap-1.5 <?php echo $isActive ? 'bg-' . $config['color'] . '-600 text-white shadow-sm' : 'bg-' . $config['color'] . '-100 text-' . $config['color'] . '-700 hover:bg-' . $config['color'] . '-200'; ?>">
                    <i class="fas <?php echo $config['icon']; ?> text-sm"></i>
                    <span><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs <?php echo $isActive ? 'bg-white/20' : 'bg-' . $config['color'] . '-200'; ?>">
                        <?php echo (int)$count; ?>
                    </span>
                </a>
            <?php endforeach; ?>

            <!-- Replacements -->
            <?php if ($replacementOrdersCount > 0): ?>
                <a href="?replacements=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                   class="relative px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 inline-flex items-center gap-1.5 <?php echo $show_replacements ? 'bg-red-600 text-white shadow-sm' : 'bg-red-100 text-red-700 hover:bg-red-200'; ?>">
                    <i class="fas fa-sync-alt text-sm"></i>
                    <span>Replacements</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs <?php echo $show_replacements ? 'bg-white/20' : 'bg-red-200'; ?>">
                        <?php echo $replacementOrdersCount; ?>
                    </span>
                    <?php if (!$show_replacements): ?>
                        <span class="absolute -top-1 -right-1 h-2 w-2 bg-red-500 border border-white rounded-full animate-pulse"></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <!-- Defects -->
            <?php
            $defectsOrdersCountSql = "
                SELECT COUNT(DISTINCT o.id) as count 
                FROM orders o 
                WHERE o.warehouse_employee_id = ? 
                AND EXISTS (SELECT 1 FROM defect_reports dr 
                            WHERE dr.order_id = o.id 
                            AND dr.status != 'resolved')
            ";
            $defectsOrdersCount = 0;
            if ($stmt5 = $conn->prepare($defectsOrdersCountSql)) {
                $stmt5->bind_param("i", $user_id);
                $stmt5->execute();
                $r5 = $stmt5->get_result();
                if ($r5) {
                    $defectsResult = $r5->fetch_assoc();
                    $defectsOrdersCount = (int)$defectsResult['count'];
                }
                $stmt5->close();
            }

            if ($defectsOrdersCount > 0): ?>
                <a href="?defects=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                   class="relative px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 inline-flex items-center gap-1.5 <?php echo $show_defects ? 'bg-orange-600 text-white shadow-sm' : 'bg-orange-100 text-orange-700 hover:bg-orange-200'; ?>">
                    <i class="fas fa-exclamation-triangle text-sm"></i>
                    <span>Defects</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs <?php echo $show_defects ? 'bg-white/20' : 'bg-orange-200'; ?>">
                        <?php echo $defectsOrdersCount; ?>
                    </span>
                    <?php if (!$show_defects): ?>
                        <span class="absolute -top-1 -right-1 h-2 w-2 bg-orange-500 border border-white rounded-full animate-pulse"></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <!-- Ready to Schedule -->
            <?php if ($readyForScheduleCount > 0): ?>
                <a href="?ready_schedule=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                   class="relative px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 inline-flex items-center gap-1.5 <?php echo $show_ready_for_schedule ? 'bg-green-600 text-white shadow-sm' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?>">
                    <i class="fas fa-calendar-check text-sm"></i>
                    <span>Schedule</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs <?php echo $show_ready_for_schedule ? 'bg-white/20' : 'bg-green-200'; ?>">
                        <?php echo $readyForScheduleCount; ?>
                    </span>
                    <?php if (!$show_ready_for_schedule): ?>
                        <span class="absolute -top-1 -right-1 h-2 w-2 bg-green-500 border border-white rounded-full animate-pulse"></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <!-- Ready to Order -->
            <?php
            $readyToOrderCountSql = "
                SELECT COUNT(DISTINCT o.id) as count 
                FROM orders o 
                WHERE o.warehouse_employee_id = ? 
                AND EXISTS (
                    SELECT 1 FROM po_attachments pa 
                    WHERE pa.order_id = o.id 
                    AND pa.approval_status = 'approved'
                    AND pa.marked_as_ordered = 0
                )
            ";
            $readyToOrderFilterCount = 0;
            if ($stmt_ready_order = $conn->prepare($readyToOrderCountSql)) {
                $stmt_ready_order->bind_param("i", $user_id);
                $stmt_ready_order->execute();
                $r_ready_order = $stmt_ready_order->get_result();
                if ($r_ready_order) {
                    $readyOrderResult = $r_ready_order->fetch_assoc();
                    $readyToOrderFilterCount = (int)$readyOrderResult['count'];
                }
                $stmt_ready_order->close();
            }
            $show_ready_to_order = isset($_GET['ready_to_order']) ? (bool)$_GET['ready_to_order'] : false;

            if ($readyToOrderFilterCount > 0): ?>
                <a href="?ready_to_order=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                   class="relative px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 inline-flex items-center gap-1.5 <?php echo $show_ready_to_order ? 'bg-green-600 text-white shadow-sm' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?>">
                    <i class="fas fa-bell text-sm"></i>
                    <span>Order Ready</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs <?php echo $show_ready_to_order ? 'bg-white/20' : 'bg-green-200'; ?>">
                        <?php echo $readyToOrderFilterCount; ?>
                    </span>
                    <?php if (!$show_ready_to_order): ?>
                        <span class="absolute -top-1 -right-1 h-2 w-2 bg-green-500 border border-white rounded-full animate-pulse"></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <!-- Replacements Ready -->
            <?php if ($readyReplacementsCount > 0): ?>
                <a href="?ready_replacements=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"
                   class="relative px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 inline-flex items-center gap-1.5 <?php echo $show_ready_replacements ? 'bg-red-600 text-white shadow-sm' : 'bg-red-100 text-red-700 hover:bg-red-200'; ?>">
                    <i class="fas fa-sync-alt text-sm"></i>
                    <span>Rep. Ready</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs <?php echo $show_ready_replacements ? 'bg-white/20' : 'bg-red-200'; ?>">
                        <?php echo $readyReplacementsCount; ?>
                    </span>
                    <?php if (!$show_ready_replacements): ?>
                        <span class="absolute -top-1 -right-1 h-2 w-2 bg-red-500 border border-white rounded-full animate-pulse"></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Search Bar -->
        <div class="flex gap-2">
            <div class="flex-1">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                       placeholder="Order ID, Customer, Email..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-600 focus:border-transparent outline-none transition-all duration-200">
            </div>
            <button type="submit" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition-all duration-200 flex items-center gap-2">
                <i class="fas fa-search"></i>
                Search
            </button>
        </div>

        <!-- Hidden field -->
        <?php if ($show_ready_for_schedule): ?>
            <input type="hidden" name="ready_schedule" value="1">
        <?php endif; ?>
    </form>
</div>

        <!-- Orders -->
        <?php if (!empty($orders)): ?>
            <div class="space-y-4">
                <?php foreach ($orders as $order):
                    $assignedItems = (int)$order['assigned_count'];
                    $item_count = (int)$order['item_count'];
                    $po_attachment_count = (int)$order['po_attachment_count'];
                    $approved_replacements_count = (int)($order['approved_replacements_count'] ?? 0);
                    $unresolved_defects_count = (int)($order['unresolved_defects_count'] ?? 0);
                    $assignmentPercentage = $item_count > 0 ? round(($assignedItems / $item_count) * 100) : 0;
                    $hasPOFiles = $po_attachment_count > 0;
                    $hasReplacements = $approved_replacements_count > 0;
                ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200 <?php echo $hasReplacements ? 'border-l-4 border-l-red-500' : ''; ?>">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-2 rounded-lg relative">
                                        <i class="fas fa-receipt text-white"></i>
                                        <?php if ($hasReplacements): ?>
                                            <span class="absolute -top-2 -right-2 h-5 w-5 bg-red-500 border-2 border-white rounded-full flex items-center justify-center pulse-notification shadow-lg">
                                                <i class="fas fa-exclamation text-white text-xs"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center flex-wrap gap-2 mb-1">
                                            <h3 class="text-lg font-bold text-gray-900">Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                                            <?php if ($hasReplacements): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 pulse-notification">
                                                    <i class="fas fa-sync-alt mr-1"></i>
                                                    <?php echo $approved_replacements_count; ?> Replacement<?php echo $approved_replacements_count > 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($unresolved_defects_count > 0): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 pulse-notification">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    <?php echo $unresolved_defects_count; ?> Defect<?php echo $unresolved_defects_count > 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php
                                            // Check if order is ready for scheduling - ADD THIS HERE TOO
                                            $isReadyForSchedule = false;
                                            if ($hasPOFiles && $order['po_attachment_count'] > 0) {
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
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 pulse-notification relative">
                                                    <i class="fas fa-calendar-check mr-1"></i>Ready to Schedule
                                                </span>
                                            <?php endif; ?>
                                            <?php
                                            // Check if replacements are ready for scheduling
                                            $replacementsReady = false;
                                            $warehouse_replacements_count = (int)($order['warehouse_replacements_count'] ?? 0);
                                            if ($warehouse_replacements_count > 0 && $approved_replacements_count > 0) {
                                                $checkReplReadySql = "
        SELECT 
            (SELECT COUNT(*) FROM replacement_requests WHERE order_id = ? AND status = 'In Warehouse') as ready_count,
            (SELECT COUNT(*) FROM replacement_requests WHERE order_id = ? AND status IN ('approved', 'processing', 'In Warehouse')) as total_count,
            (SELECT COUNT(*) FROM delivery_schedules WHERE order_id = ? AND item_type = 'replacement') as scheduled_count
    ";
                                                if ($checkReplStmt = $conn->prepare($checkReplReadySql)) {
                                                    $checkReplStmt->bind_param("iii", $order['id'], $order['id'], $order['id']);
                                                    $checkReplStmt->execute();
                                                    $replReadyResult = $checkReplStmt->get_result()->fetch_assoc();
                                                    $checkReplStmt->close();

                                                    if (
                                                        $replReadyResult['ready_count'] == $replReadyResult['total_count']
                                                        && $replReadyResult['total_count'] > 0
                                                        && $replReadyResult['scheduled_count'] == 0
                                                    ) {
                                                        $replacementsReady = true;
                                                    }
                                                }
                                            }
                                            if ($replacementsReady): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 pulse-notification relative">
                                                    <i class="fas fa-sync-alt mr-1"></i>Replacements Ready
                                                </span>
                                            <?php endif; ?>
                                        </div>
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
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo ($order['status'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : (($order['status'] === 'processing') ? 'bg-blue-100 text-blue-800' : (($order['status'] === 'completed') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')); ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
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

                                        <?php if ($hasPOFiles): ?>
                                            <div class="mt-2 text-green-600">
                                                <i class="fas fa-file-excel mr-1"></i>
                                                <?php echo $po_attachment_count; ?> P.O. file(s)
                                            </div>
                                            <?php
                                            // Check if there are approved POs ready to order
                                            $readyToOrderSql = "SELECT COUNT(*) as count FROM po_attachments 
                        WHERE order_id = ? AND approval_status = 'approved' 
                        AND marked_as_ordered = 0";
                                            $readyToOrderStmt = $conn->prepare($readyToOrderSql);
                                            $readyToOrderStmt->bind_param("i", $order['id']);
                                            $readyToOrderStmt->execute();
                                            $readyToOrderResult = $readyToOrderStmt->get_result()->fetch_assoc();
                                            $readyToOrderStmt->close();
                                            $readyToOrderCount = (int)$readyToOrderResult['count'];

                                            if ($readyToOrderCount > 0): ?>
                                                <div class="mt-1 flex items-center">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 pulse-notification">
                                                        <i class="fas fa-bell mr-1"></i>
                                                        <?php echo $readyToOrderCount; ?> Ready to Order
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex flex-col items-center space-y-2 ml-4">
                                    <?php

                                    // Check if all PO files are approved
                                    $allPOApprovedSql = "SELECT 
    COUNT(*) as total_pos,
    SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_pos
    FROM po_attachments WHERE order_id = ?";
                                    $allPOApprovedStmt = $conn->prepare($allPOApprovedSql);
                                    $allPOApprovedStmt->bind_param("i", $order['id']);
                                    $allPOApprovedStmt->execute();
                                    $allPOApprovedResult = $allPOApprovedStmt->get_result()->fetch_assoc();
                                    $allPOApprovedStmt->close();

                                    $totalPOs = (int)$allPOApprovedResult['total_pos'];
                                    $approvedPOs = (int)$allPOApprovedResult['approved_pos'];
                                    $allPOsApproved = ($totalPOs > 0 && $totalPOs === $approvedPOs);

                                    // Always show Manage P.O. button (for both generating new and managing existing)
                                    ?>
                                    <a href="warehouse_staff_po_management_A.php?order_id=<?php echo urlencode($order['id']); ?>"
                                        class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                        <i class="fas fa-cogs"></i>
                                        <span><?php echo $hasPOFiles ? 'Manage P.O.' : 'Generate P.O.'; ?></span>
                                    </a>

                                    <?php if ($hasPOFiles): ?>
                                        <a href="warehouse_head_staff_view_po_files_B.php?order_id=<?php echo urlencode($order['id']); ?>"
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                            <i class="fas fa-eye"></i>
                                            <span>View P.O. (<?php echo $po_attachment_count; ?>)</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    // Only show Track Items if PO files exist AND all are marked as ordered
                                    if (in_array($order['status'], ['processing', 'Ready for Pickup', 'Picked Up', 'Delivered', 'Out for Delivery']) && $hasPOFiles) {
                                        // Check if all PO files are marked as ordered
                                        $checkOrderedSql = "SELECT 
        COUNT(*) as total_pos,
        SUM(CASE WHEN marked_as_ordered = 1 THEN 1 ELSE 0 END) as ordered_pos
        FROM po_attachments WHERE order_id = ?";
                                        $checkOrderedStmt = $conn->prepare($checkOrderedSql);
                                        $checkOrderedStmt->bind_param("i", $order['id']);
                                        $checkOrderedStmt->execute();
                                        $checkOrderedResult = $checkOrderedStmt->get_result()->fetch_assoc();
                                        $checkOrderedStmt->close();

                                        $totalPOsCheck = (int)$checkOrderedResult['total_pos'];
                                        $orderedPOsCheck = (int)$checkOrderedResult['ordered_pos'];
                                        $allPOsOrderedCheck = ($totalPOsCheck > 0 && $totalPOsCheck === $orderedPOsCheck);

                                        if ($allPOsOrderedCheck):
                                    ?>
                                            <a href="warehouse_staff_order_tracking_C.php?order_id=<?php echo urlencode($order['id']); ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 text-sm">
                                                <i class="fas fa-route"></i><span>Track Items</span>
                                            </a>
                                    <?php
                                        endif;
                                    }
                                    ?>
                                    <!-- Defect Reports Button -->
                                    <?php if ($unresolved_defects_count > 0): ?>
                                        <a href="view_defects.php?order_id=<?php echo urlencode($order['id']); ?>"
                                            class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 text-sm pulse-notification relative">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span>View Defects (<?php echo $unresolved_defects_count; ?>)</span>
                                            <span class="absolute -top-1 -right-1 h-3 w-3 bg-orange-500 border-2 border-white rounded-full animate-ping"></span>
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
                        <?php if ($show_replacements): ?>
                            You don't have any orders with approved replacement requests.
                        <?php elseif ($show_ready_to_order): ?>
                            You don't have any orders with P.O. files ready to order from suppliers.
                        <?php elseif ($show_ready_for_schedule): ?>
                            You don't have any orders ready for delivery scheduling.
                        <?php elseif ($show_defects): ?>
                            You don't have any orders with unresolved defects.
                        <?php else: ?>
                            You don't have any assigned orders right now.
                        <?php endif; ?>
                    </p>
                    <?php if ($show_replacements || $show_ready_for_schedule): ?>
                        <a href="?" class="mt-4 inline-block text-primary-600 hover:text-primary-700 font-medium">
                            <i class="fas fa-arrow-left mr-1"></i>View All Orders
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>