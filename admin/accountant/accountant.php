<?php
//accountant.php
date_default_timezone_set('Asia/Manila');
session_name("nobleadmin");
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'document_controller') {
    header("Location: accountant_view_orders.php");
    exit();
}

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

function resolve_current_user_details($conn)
{
    if (!empty($_SESSION['current_user_details'])) return $_SESSION['current_user_details'];
    if (empty($_SESSION['noble_user'])) return null;
    $loginValue = $_SESSION['noble_user'];
    $userDetails = null;
    $stmt = $conn->prepare("SELECT id, fullname, email, lvl, status, is_head, last_login FROM nobleaccount WHERE email = ? OR fullname = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $loginValue, $loginValue);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $userDetails = ['id' => (int)$row['id'], 'name' => $row['fullname'], 'email' => $row['email'], 'role' => $row['lvl'], 'status' => $row['status'], 'is_head' => (bool)$row['is_head'], 'last_login' => $row['last_login'], 'table' => 'nobleaccount'];
        }
        $stmt->close();
    }
    if (!$userDetails) {
        $stmt = $conn->prepare("SELECT id, fullname, email, lvl, status, is_head, last_login FROM nobleaccount WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $loginValue);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $userDetails = ['id' => (int)$row['id'], 'name' => $row['fullname'], 'email' => $row['email'], 'role' => $row['lvl'], 'status' => $row['status'], 'is_head' => (bool)$row['is_head'], 'last_login' => $row['last_login'], 'table' => 'nobleaccount'];
            }
            $stmt->close();
        }
    }
    if ($userDetails) {
        $_SESSION['current_user_details'] = $userDetails;
        $_SESSION['user_id'] = $userDetails['id'];
        $u = $conn->prepare("UPDATE nobleaccount SET last_activity = NOW() WHERE id = ?");
        if ($u) {
            $u->bind_param('i', $userDetails['id']);
            $u->execute();
            $u->close();
        }
    }
    return $userDetails;
}

$current_user = resolve_current_user_details($conn);
if (!$current_user) {
    $_SESSION['error'] = "Unable to identify current user. Please login again.";
    header("Location: ../../loginpage/index.php");
    exit();
}
$current_user_id = $current_user['id'];

$success_message = $_SESSION['success_message'] ?? '';
$error_message   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// ============================================================
// AJAX HANDLER: verify only
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    try {
        if ($_POST['action'] === 'verify_payment') {
            $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
            if (!$order_id || $order_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
                exit();
            }
            $conn->begin_transaction();
            try {
                $q = $conn->prepare("SELECT user_id, customer_name, total, payment_status FROM orders WHERE id = ? LIMIT 1");
                if (!$q) throw new Exception("DB prepare failed");
                $q->bind_param("i", $order_id);
                $q->execute();
                $od = $q->get_result()->fetch_assoc();
                $q->close();
                if (!$od) throw new Exception("Order not found");
                if ($od['payment_status'] === 'verified') throw new Exception("Order is already verified");
                $total = (float)($od['total'] ?? 0);
                $s = $conn->prepare("UPDATE orders SET payment_status='verified', status='Ongoing', confirmed_at=CURRENT_TIMESTAMP, verified_by=?, final_total=? WHERE id=?");
                if (!$s) throw new Exception("DB prepare failed for update");
                $s->bind_param("idi", $current_user_id, $total, $order_id);
                $s->execute();
                $s->close();
                if (!empty($od['user_id'])) {
                    $msg = "Your payment for Order #$order_id has been verified and confirmed by {$current_user['name']}. Amount: ₱" . number_format($total, 2);
                    $n = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) VALUES (?,?,'payment_verified',?,NOW())");
                    if ($n) {
                        $n->bind_param("iis", $od['user_id'], $current_user_id, $msg);
                        $n->execute();
                        $n->close();
                    }
                }
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Payment verified successfully', 'verified_by' => $current_user['name']]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

// ============================================================
// PAGE LOAD — filter + stats
// ============================================================
$filter = $_GET['filter'] ?? 'pending';
$payment_method_filter = $_GET['method']    ?? null;
$bank_type_filter      = $_GET['bank_type'] ?? null;
$qr_type_filter        = $_GET['qr_type']   ?? null;

// Stats
$pending_payments    = 0;
$verified_today      = 0;
$total_revenue_today = 0;
$paymongo_pending    = 0;
$qr_pending          = 0;

$r = $conn->query("SELECT mode_payment, COUNT(*) as count FROM orders WHERE payment_status = 'paid' GROUP BY mode_payment");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ($row['mode_payment'] === 'PayMongo')       $paymongo_pending = $row['count'];
        elseif ($row['mode_payment'] === 'QR Payment') $qr_pending       = $row['count'];
    }
}

$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status = 'paid'");
if ($r) $pending_payments = $r->fetch_assoc()['c'] ?? 0;

$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($r) $verified_today = $r->fetch_assoc()['c'] ?? 0;

$r = $conn->query("SELECT COALESCE(SUM(final_total),0) as t FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($r) $total_revenue_today = $r->fetch_assoc()['t'] ?? 0;

// Build WHERE clause
$where = "";
switch ($filter) {
    case 'pending':
        $where = "WHERE o.payment_status = 'paid'";
        if ($payment_method_filter) {
            if ($payment_method_filter === 'paymongo') $where .= " AND o.mode_payment = 'PayMongo'";
            elseif ($payment_method_filter === 'qr') {
                $where .= " AND o.mode_payment = 'QR Payment'";
                if ($qr_type_filter) $where .= " AND o.bank_type = '" . $conn->real_escape_string($qr_type_filter) . "'";
            }
        }
        break;
    case 'verified':
        $where = "WHERE o.payment_status = 'verified'";
        if ($payment_method_filter) {
            if ($payment_method_filter === 'paymongo') $where .= " AND o.mode_payment = 'PayMongo'";
            elseif ($payment_method_filter === 'qr') {
                $where .= " AND o.mode_payment = 'QR Payment'";
                if ($qr_type_filter) $where .= " AND o.bank_type = '" . $conn->real_escape_string($qr_type_filter) . "'";
            }
        }
        break;
    case 'paymongo':
        break;
    default:
        $where = "WHERE o.payment_status IN ('paid','verified')";
        break;
}

$orders_result = null;
if ($filter !== 'paymongo') {
    $orders_result = $conn->query("
        SELECT o.*, vb.fullname as verified_by_name
        FROM orders o
        LEFT JOIN nobleaccount vb ON o.verified_by = vb.id
        $where
        ORDER BY CASE o.payment_status WHEN 'paid' THEN 1 WHEN 'verified' THEN 2 END, o.created_at DESC
        LIMIT 100
    ");
}

function tabClass($current, $target)
{
    return $current === $target
        ? 'border-noble-orange text-noble-orange'
        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                        'noble-orange-light': '#fb923c',
                        'noble-orange-dark': '#ea580c'
                    }
                }
            }
        }
    </script>
    <style>
        html, body { overflow-x: hidden; max-width: 100%; }
        .order-row-clickable { cursor: pointer; transition: all 0.2s ease; }
        .order-row-clickable:hover { background-color: #f3f4f6 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
    </style>
</head>

<body class="bg-gray-50 overflow-x-hidden">
    <?php include '../navbar/top.php'; ?>

    <header class="bg-white shadow-sm">
        <div class="w-full px-6 py-4 flex items-center space-x-3">
            <div class="w-8 h-8 bg-noble-orange rounded-lg flex items-center justify-center">
                <i class="fas fa-calculator text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Accountant Dashboard</h1>
                <p class="text-sm text-gray-600">Welcome back, <?php echo htmlspecialchars($current_user['name']); ?></p>
            </div>
        </div>
    </header>

    <main class="w-full px-6 py-8 overflow-x-hidden">

        <?php if ($success_message): ?>
            <div id="successAlert" class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <span><i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?></span>
                <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div id="errorAlert" class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <span><i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error_message); ?></span>
                <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- ===== TABS ===== -->
        <div class="bg-white rounded-xl shadow-sm mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-6 px-6 overflow-x-auto">
                    <a href="?filter=pending" class="<?php echo tabClass($filter, 'pending'); ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-clock mr-2"></i>For Verification (<?php echo number_format($pending_payments); ?>)
                    </a>
                    <a href="?filter=verified" class="<?php echo tabClass($filter, 'verified'); ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>Verified
                    </a>
                    <a href="?filter=all" class="<?php echo tabClass($filter, 'all'); ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-list mr-2"></i>All
                    </a>
                    <a href="?filter=paymongo" class="<?php echo tabClass($filter, 'paymongo'); ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-mobile-alt mr-2"></i>PayMongo History
                    </a>
                </nav>
            </div>
        </div>

        <!-- ===== PAYMENT METHOD FILTER (For Verification) ===== -->
        <?php if ($filter === 'pending'): ?>
            <div class="bg-white rounded-xl shadow-sm mb-6 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Filter by Payment Method:</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                    $methods = [
                        'paymongo' => ['label' => 'PayMongo',   'count' => $paymongo_pending, 'icon' => 'fas fa-mobile-alt', 'color_active' => 'bg-green-500 border-green-600',   'color_icon' => 'text-green-600',  'color_hover' => 'hover:border-green-500 hover:bg-green-50'],
                        'qr'       => ['label' => 'QR Payment', 'count' => $qr_pending,       'icon' => 'fas fa-qrcode',     'color_active' => 'bg-indigo-500 border-indigo-600', 'color_icon' => 'text-indigo-600', 'color_hover' => 'hover:border-indigo-500 hover:bg-indigo-50'],
                    ];
                    foreach ($methods as $key => $m):
                        $active = isset($_GET['method']) && $_GET['method'] === $key;
                    ?>
                        <a href="?filter=pending&method=<?php echo $key; ?>"
                            class="<?php echo $active ? $m['color_active'] . ' text-white' : 'bg-white text-gray-700 border-gray-300 ' . $m['color_hover']; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                            <i class="<?php echo $m['icon']; ?> text-3xl mb-2 <?php echo $active ? 'text-white' : $m['color_icon']; ?>"></i>
                            <div class="font-semibold text-sm"><?php echo $m['label']; ?></div>
                            <div class="text-xs mt-1 opacity-75"><?php echo $m['count']; ?> to verify</div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($_GET['method'])): ?>
                    <div class="mt-4 text-center">
                        <a href="?filter=pending" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
                            <i class="fas fa-times mr-2"></i>Clear Filter — Show All
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- QR sub-filter -->
            <?php if ($payment_method_filter === 'qr'):
                $qres = $conn->query("SELECT DISTINCT REPLACE(o.bank_type,'QR_','') as qr_id, pqc.payment_method, COUNT(*) as c FROM orders o LEFT JOIN payment_qr_codes pqc ON REPLACE(o.bank_type,'QR_','')=pqc.id WHERE o.payment_status='paid' AND o.mode_payment='QR Payment' AND o.bank_type IS NOT NULL AND o.bank_type LIKE 'QR_%' GROUP BY o.bank_type, pqc.payment_method");
            ?>
                <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-filter mr-2 text-indigo-600"></i>Filter by QR Payment Method:</h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="?filter=pending&method=qr" class="<?php echo !$qr_type_filter ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium">All QR Methods</a>
                        <?php if ($qres) while ($row = $qres->fetch_assoc()): ?>
                            <a href="?filter=pending&method=qr&qr_type=QR_<?php echo urlencode($row['qr_id']); ?>"
                                class="<?php echo $qr_type_filter === 'QR_' . $row['qr_id'] ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium">
                                <?php echo htmlspecialchars($row['payment_method'] ?: 'QR Payment'); ?> <span class="text-xs opacity-75">(<?php echo $row['c']; ?>)</span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ===== VERIFIED TAB METHOD FILTERS ===== -->
        <?php if ($filter === 'verified'):
            $vmethods = [
                'paymongo' => ['label' => 'PayMongo',   'icon' => 'fas fa-mobile-alt', 'aclass' => 'bg-green-500 border-green-600',   'iclass' => 'text-green-600',  'hclass' => 'hover:border-green-500 hover:bg-green-50'],
                'qr'       => ['label' => 'QR Payment', 'icon' => 'fas fa-qrcode',     'aclass' => 'bg-indigo-500 border-indigo-600', 'iclass' => 'text-indigo-600', 'hclass' => 'hover:border-indigo-500 hover:bg-indigo-50'],
            ];
        ?>
            <div class="bg-white rounded-xl shadow-sm mb-6 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Filter Verified Payments by Method:</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($vmethods as $key => $m): $active = $payment_method_filter === $key; ?>
                        <a href="?filter=verified&method=<?php echo $key; ?>"
                            class="<?php echo $active ? $m['aclass'] . ' text-white' : 'bg-white text-gray-700 border-gray-300 ' . $m['hclass']; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                            <i class="<?php echo $m['icon']; ?> text-3xl mb-2 <?php echo $active ? 'text-white' : $m['iclass']; ?>"></i>
                            <div class="font-semibold text-sm"><?php echo $m['label']; ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($payment_method_filter): ?>
                    <div class="mt-4 text-center"><a href="?filter=verified" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium"><i class="fas fa-times mr-2"></i>Clear Filter</a></div>
                <?php endif; ?>
            </div>
            <?php if ($payment_method_filter === 'qr'):
                $qv = $conn->query("SELECT DISTINCT REPLACE(o.bank_type,'QR_','') as qr_id, pqc.payment_method, COUNT(*) as c FROM orders o LEFT JOIN payment_qr_codes pqc ON REPLACE(o.bank_type,'QR_','')=pqc.id WHERE o.payment_status='verified' AND o.mode_payment='QR Payment' AND o.bank_type LIKE 'QR_%' GROUP BY o.bank_type, pqc.payment_method"); ?>
                <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-filter mr-2 text-indigo-600"></i>Filter by QR Method:</h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="?filter=verified&method=qr" class="<?php echo !$qr_type_filter ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium">All QR Methods</a>
                        <?php if ($qv) while ($row = $qv->fetch_assoc()): ?>
                            <a href="?filter=verified&method=qr&qr_type=QR_<?php echo urlencode($row['qr_id']); ?>" class="<?php echo $qr_type_filter === 'QR_' . $row['qr_id'] ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium"><?php echo htmlspecialchars($row['payment_method'] ?: 'QR'); ?> <span class="text-xs opacity-75">(<?php echo $row['c']; ?>)</span></a>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ===== MAIN ORDERS TABLE ===== -->
        <?php if ($filter !== 'paymongo'): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden w-full min-w-0">

                <!-- Stats bar -->
                <div class="flex flex-col lg:flex-row gap-6 p-6 border-b border-gray-100">
                    <div class="lg:w-1/4 flex flex-col justify-center">
                        <h2 class="text-lg font-semibold text-gray-900">Payment Verification Queue</h2>
                        <p class="text-sm text-gray-600 mt-1">Review and verify customer payments</p>
                    </div>
                    <div class="lg:w-3/4 grid grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="p-4 rounded-lg bg-yellow-50">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3"><i class="fas fa-clock text-yellow-600"></i></div>
                                <div>
                                    <p class="text-xs text-gray-600">For Verification</p>
                                    <p class="text-xl font-bold text-gray-900"><?php echo number_format($pending_payments); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 rounded-lg bg-green-50">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3"><i class="fas fa-check-circle text-green-600"></i></div>
                                <div>
                                    <p class="text-xs text-gray-600">Verified Today</p>
                                    <p class="text-xl font-bold text-gray-900"><?php echo number_format($verified_today); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 rounded-lg bg-orange-50">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3"><i class="fas fa-peso-sign text-noble-orange"></i></div>
                                <div>
                                    <p class="text-xs text-gray-600">Revenue Today</p>
                                    <p class="text-xl font-bold text-gray-900">₱<?php echo number_format($total_revenue_today, 0); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Method</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Verified By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($orders_result && $orders_result->num_rows > 0):
                                while ($order = $orders_result->fetch_assoc()): ?>
                                    <tr class="order-row-clickable" id="order-row-<?php echo $order['id']; ?>"
                                        onclick="viewOrderDetails(<?php echo $order['id']; ?>)" title="Click to view order details">

                                        <!-- Order ID -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="bg-gray-100 px-2 py-1 rounded-md inline-flex items-center text-sm font-medium">
                                                #<?php echo $order['id']; ?>
                                            </span>
                                        </td>

                                        <!-- Customer -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-blue-600 text-xs font-semibold"><?php echo strtoupper(substr($order['customer_name'] ?: 'U', 0, 1)); ?></span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Amount -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php $amt = ($order['payment_status'] === 'verified' && !empty($order['final_total'])) ? $order['final_total'] : $order['total']; ?>
                                            <div class="text-sm font-semibold text-gray-900">₱<?php echo number_format((float)$amt, 2); ?></div>
                                        </td>

                                        <!-- Payment Method -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 flex items-center">
                                                <?php if ($order['mode_payment'] === 'PayMongo'): ?>
                                                    <i class="fas fa-mobile-alt text-green-600 mr-2"></i>
                                                <?php elseif ($order['mode_payment'] === 'QR Payment'): ?>
                                                    <i class="fas fa-qrcode text-indigo-600 mr-2"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?>
                                            </div>
                                            <?php if (!empty($order['bank_type']) && strpos($order['bank_type'], 'QR_') === 0): ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php
                                                    $qid = str_replace('QR_', '', $order['bank_type']);
                                                    $qs  = $conn->prepare("SELECT payment_method FROM payment_qr_codes WHERE id=?");
                                                    $qs->bind_param("i", $qid);
                                                    $qs->execute();
                                                    $qrow = $qs->get_result()->fetch_assoc();
                                                    $qs->close();
                                                    echo htmlspecialchars($qrow['payment_method'] ?? '');
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Reference -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <code class="text-sm bg-gray-100 px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($order['reference_number'] ?: $order['reference_no'] ?: 'N/A'); ?>
                                            </code>
                                            <?php if (!empty($order['paymongo_payment_id'])): ?>
                                                <div class="mt-1"><code class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded"><?php echo htmlspecialchars($order['paymongo_payment_id']); ?></code></div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $sm = [
                                                'paid'     => ['cls' => 'bg-yellow-100 text-yellow-800 border-yellow-200', 'icon' => 'fas fa-clock',        'lbl' => 'Paid'],
                                                'verified' => ['cls' => 'bg-green-100 text-green-800 border-green-200',   'icon' => 'fas fa-check-circle', 'lbl' => 'Verified'],
                                            ];
                                            $s = $sm[$order['payment_status']] ?? ['cls' => 'bg-gray-100 text-gray-800 border-gray-200', 'icon' => 'fas fa-question', 'lbl' => ucfirst($order['payment_status'])];
                                            ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border <?php echo $s['cls']; ?>">
                                                <i class="<?php echo $s['icon']; ?> mr-1"></i><?php echo $s['lbl']; ?>
                                            </span>
                                        </td>

                                        <!-- Verified By -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($order['verified_by_name'] ?: 'N/A'); ?>
                                            <?php if ($order['confirmed_at'] && $order['payment_status'] === 'verified'): ?>
                                                <div class="text-xs text-gray-500"><?php echo date('M d, g:i A', strtotime($order['confirmed_at'])); ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Date -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($order['created_at'])); ?></div>
                                        </td>

                                        <!-- Actions — only paid orders get Verify button -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm" onclick="event.stopPropagation()">
                                            <?php if ($order['payment_status'] === 'paid'): ?>
                                                <button onclick="verifyPayment(<?php echo $order['id']; ?>)"
                                                    class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                    <i class="fas fa-check mr-1"></i>Verify
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 italic">Verified</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <i class="fas fa-receipt text-4xl text-gray-300 mb-4 block"></i>
                                        <p class="text-gray-500 text-sm">
                                            <?php
                                            if ($filter === 'pending') echo 'No payments awaiting verification.';
                                            elseif ($filter === 'verified') echo 'No verified payments found.';
                                            else echo 'No payment records found.';
                                            ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ===== PAYMONGO HISTORY TAB ===== -->
        <?php if ($filter === 'paymongo'):
            $pmr = $conn->query("SELECT o.*, vb.fullname as verified_by_name FROM orders o LEFT JOIN nobleaccount vb ON o.verified_by=vb.id WHERE o.mode_payment='PayMongo' ORDER BY o.created_at DESC LIMIT 100");
        ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center"><i class="fas fa-mobile-alt text-green-600 mr-2"></i>PayMongo Transaction History</h2>
                    <p class="text-sm text-gray-600">All PayMongo payment transactions</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference / Payment ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Verified By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($pmr && $pmr->num_rows > 0): while ($t = $pmr->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap"><span class="bg-gray-100 px-2 py-1 rounded-md text-sm font-medium">#<?php echo $t['id']; ?></span></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3"><span class="text-green-600 text-xs font-semibold"><?php echo strtoupper(substr($t['customer_name'] ?: 'U', 0, 1)); ?></span></div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($t['customer_name'] ?: 'N/A'); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($t['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php $da = ($t['payment_status'] === 'verified' && !empty($t['final_total'])) ? $t['final_total'] : $t['total']; ?>
                                            <div class="text-sm font-semibold text-gray-900">₱<?php echo number_format((float)$da, 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <code class="text-sm bg-gray-100 px-2 py-1 rounded block"><?php echo htmlspecialchars($t['reference_number'] ?: $t['reference_no'] ?: 'N/A'); ?></code>
                                            <?php if (!empty($t['paymongo_payment_id'])): ?>
                                                <code class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded mt-1 block"><?php echo htmlspecialchars($t['paymongo_payment_id']); ?></code>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php $ps = $t['payment_status'];
                                            $sm = ['paid' => 'bg-yellow-100 text-yellow-800 border-yellow-200', 'verified' => 'bg-green-100 text-green-800 border-green-200'];
                                            $si = ['paid' => 'fas fa-clock', 'verified' => 'fas fa-check-circle']; ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border <?php echo $sm[$ps] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?>">
                                                <i class="<?php echo $si[$ps] ?? 'fas fa-question'; ?> mr-1"></i><?php echo ucfirst($ps); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($t['verified_by_name'] ?: 'N/A'); ?></div>
                                            <?php if ($t['confirmed_at']): ?><div class="text-xs text-gray-500"><?php echo date('M d, g:i A', strtotime($t['confirmed_at'])); ?></div><?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($t['created_at'])); ?></div>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500"><i class="fas fa-mobile-alt text-4xl text-gray-300 mb-4 block"></i>No PayMongo transactions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 shadow-xl flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-noble-orange"></div>
            <span class="text-gray-700">Processing...</span>
        </div>
    </div>

    <script>
        function viewOrderDetails(id) {
            const w = 1200, h = 800, l = (screen.width - w) / 2, t = (screen.height - h) / 2;
            window.open('order_details.php?id=' + id, 'OrderDetails', `width=${w},height=${h},left=${l},top=${t},scrollbars=yes,resizable=yes`);
        }

        function verifyPayment(id) {
            if (!confirm('Are you sure you want to verify this payment? This cannot be undone.')) return;
            showLoading();
            const fd = new FormData();
            fd.append('action', 'verify_payment');
            fd.append('order_id', id);
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.text())
                .then(text => {
                    hideLoading();
                    try {
                        const d = JSON.parse(text);
                        if (d.success) {
                            showAlert('success', d.message);
                            updateRow(id, 'verified', d.verified_by);
                        } else showAlert('error', d.message || 'Failed to verify payment');
                    } catch (e) {
                        showAlert('error', 'Invalid server response');
                    }
                })
                .catch(e => { hideLoading(); showAlert('error', 'Network error: ' + e.message); });
        }

        function updateRow(id, status, by) {
            const row = document.getElementById('order-row-' + id);
            if (!row) return;
            // Status cell (index 5)
            row.cells[5].innerHTML = `<span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border bg-green-100 text-green-800 border-green-200"><i class="fas fa-check-circle mr-1"></i>Verified</span>`;
            // Verified by (index 6)
            const now = new Date().toLocaleString('en-US', { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true });
            row.cells[6].innerHTML = `<div class="text-sm text-gray-900">${by}</div><div class="text-xs text-gray-500">${now}</div>`;
            // Actions (index 8)
            row.cells[8].innerHTML = '<span class="text-xs text-gray-400 italic">Verified</span>';
            row.classList.add('bg-green-50');
            setTimeout(() => row.classList.remove('bg-green-50'), 2500);
        }

        function showLoading() { document.getElementById('loadingOverlay').classList.remove('hidden'); }
        function hideLoading() { document.getElementById('loadingOverlay').classList.add('hidden'); }

        function showAlert(type, msg) {
            document.querySelectorAll('.alert-msg').forEach(a => a.remove());
            const c = { success: 'bg-green-50 border-green-200 text-green-800', error: 'bg-red-50 border-red-200 text-red-800' };
            const i = { success: 'fas fa-check-circle', error: 'fas fa-exclamation-triangle' };
            const el = document.createElement('div');
            el.className = `alert-msg mb-6 ${c[type]} border px-4 py-3 rounded-lg flex items-center justify-between`;
            el.innerHTML = `<span><i class="${i[type]} mr-2"></i>${msg}</span><button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
            document.querySelector('main').prepend(el);
            setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 5000);
        }
    </script>
</body>

</html>