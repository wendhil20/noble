<?php
//accountant.php
ob_start();
date_default_timezone_set('Asia/Manila');
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'document_controller') {
    header("Location: " . BASE_URL . "/accountantvieworder");
    exit();
}

require_role(['accountant', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// ============================================================
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
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $userDetails = ['id' => (int) $row['id'], 'name' => $row['fullname'], 'email' => $row['email'], 'role' => $row['lvl'], 'status' => $row['status'], 'is_head' => (bool) $row['is_head'], 'last_login' => $row['last_login'], 'table' => 'nobleaccount'];
        }
        $stmt->close();
    }
    if (!$userDetails) {
        $stmt = $conn->prepare("SELECT id, fullname, email, lvl, status, is_head, last_login FROM nobleaccount WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $loginValue);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $userDetails = ['id' => (int) $row['id'], 'name' => $row['fullname'], 'email' => $row['email'], 'role' => $row['lvl'], 'status' => $row['status'], 'is_head' => (bool) $row['is_head'], 'last_login' => $row['last_login'], 'table' => 'nobleaccount'];
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
    header("Location: " . BASE_URL . "/main");
    exit();
}
$current_user_id = $current_user['id'];

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// ============================================================
// AJAX HANDLER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
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
                if (!$q)
                    throw new Exception("DB prepare failed");
                $q->bind_param("i", $order_id);
                $q->execute();
                $od = $q->get_result()->fetch_assoc();
                $q->close();
                if (!$od)
                    throw new Exception("Order not found");
                if ($od['payment_status'] === 'verified')
                    throw new Exception("Order is already verified");
                $total = (float) ($od['total'] ?? 0);
                $s = $conn->prepare("UPDATE orders SET payment_status='verified', status='Ongoing', confirmed_at=CURRENT_TIMESTAMP, verified_by=?, final_total=? WHERE id=?");
                if (!$s)
                    throw new Exception("DB prepare failed for update");
                $s->bind_param("idi", $current_user_id, $total, $order_id);
                $s->execute();
                $s->close();
                if (!empty($od['user_id'])) {
                    $msg = "Your payment for Order #$order_id has been verified Amount: ₱" . number_format($total, 2);
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
$payment_method_filter = $_GET['method'] ?? null;

$pending_payments = 0;
$verified_today = 0;
$total_revenue_today = 0;
$paymongo_pending = 0;
$qr_pending = 0;

$r = $conn->query("SELECT mode_payment, COUNT(*) as count FROM orders WHERE payment_status = 'paid' GROUP BY mode_payment");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ($row['mode_payment'] === 'PayMongo')
            $paymongo_pending = $row['count'];
        elseif ($row['mode_payment'] === 'QR Payment')
            $qr_pending = $row['count'];
    }
}

$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status = 'paid'");
if ($r)
    $pending_payments = $r->fetch_assoc()['c'] ?? 0;

$r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($r)
    $verified_today = $r->fetch_assoc()['c'] ?? 0;

$r = $conn->query("SELECT COALESCE(SUM(final_total),0) as t FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($r)
    $total_revenue_today = $r->fetch_assoc()['t'] ?? 0;

$where = "";
switch ($filter) {
    case 'pending':
        $where = "WHERE o.payment_status = 'paid'";
        if ($payment_method_filter === 'paymongo')
            $where .= " AND o.mode_payment = 'PayMongo'";
        elseif ($payment_method_filter === 'qr')
            $where .= " AND o.mode_payment = 'QR Payment'";
        break;
    case 'verified':
        $where = "WHERE o.payment_status = 'verified'";
        if ($payment_method_filter === 'paymongo')
            $where .= " AND o.mode_payment = 'PayMongo'";
        elseif ($payment_method_filter === 'qr')
            $where .= " AND o.mode_payment = 'QR Payment'";
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard — Noble Home</title>
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>
    <style>
        body {
            background-color: #f8f9fb;
        }

        .tab-active {
            border-bottom: 3px solid #f97316;
            color: #f97316;
            font-weight: 600;
        }

        .tab-inactive {
            border-bottom: 3px solid transparent;
            color: #6b7280;
        }

        .tab-inactive:hover {
            color: #111827;
            border-color: #d1d5db;
        }

        .method-card-active {
            background: #f97316;
            color: #fff;
            border-color: #ea580c;
        }

        .method-card-inactive {
            background: #fff;
            color: #374151;
            border-color: #e5e7eb;
        }

        .method-card-inactive:hover {
            border-color: #f97316;
            background: #fff7ed;
        }

        .row-click {
            cursor: pointer;
            transition: background .15s;
        }

        .row-click:hover td {
            background: #fafafa;
        }

        .badge-paid {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde68a;
        }

        .badge-verified {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .badge-pm {
            background: #f0fdf4;
            color: #15803d;
        }

        .badge-qr {
            background: #eef2ff;
            color: #3730a3;
        }
    </style>
</head>

<body>

 
 

    <div class="max-w-7xl mx-auto px-4 py-6">

    <div class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between rounded-lg">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-500 flex items-center justify-center text-white">
                <i class="fas fa-calculator text-sm"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-900 leading-none">Accountant Dashboard</h1>
                <p class="text-xs text-gray-500 mt-0.5">Welcome back, <?= htmlspecialchars($current_user['name']) ?></p>
            </div>
        </div>
        <span class="text-xs text-gray-400"><?= date('F d, Y') ?></span>
    </div>

        <!-- ── ALERTS ── -->
        <?php if ($success_message): ?>
            <div
                class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
                <i class="fas fa-check-circle text-green-500"></i>
                <span class="flex-1"><?= htmlspecialchars($success_message) ?></span>
                <button onclick="this.closest('div').remove()" class="text-green-500 hover:text-green-700"><i
                        class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div
                class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
                <span class="flex-1"><?= htmlspecialchars($error_message) ?></span>
                <button onclick="this.closest('div').remove()" class="text-red-500 hover:text-red-700"><i
                        class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- ── STAT CARDS ── -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 mt-2">
            <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
                <div
                    class="w-11 h-11 rounded-lg bg-yellow-100 flex items-center justify-center text-yellow-600 flex-shrink-0">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">For Verification</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($pending_payments) ?></p>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
                <div
                    class="w-11 h-11 rounded-lg bg-green-100 flex items-center justify-center text-green-600 flex-shrink-0">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Verified Today</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($verified_today) ?></p>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
                <div
                    class="w-11 h-11 rounded-lg bg-orange-100 flex items-center justify-center text-orange-500 flex-shrink-0">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Revenue Today</p>
                    <p class="text-2xl font-bold text-gray-900">₱<?= number_format($total_revenue_today, 0) ?></p>
                </div>
            </div>
        </div>

        <!-- ── TABS ── -->
        <div class="bg-white rounded-xl border border-gray-200 mb-5">
            <nav class="flex overflow-x-auto px-4 gap-1">
                <?php
                $tabs = [
                    'pending' => ['icon' => 'fa-clock', 'label' => 'For Verification', 'badge' => $pending_payments],
                    'verified' => ['icon' => 'fa-check-circle', 'label' => 'Verified', 'badge' => null],
                    'all' => ['icon' => 'fa-list', 'label' => 'All', 'badge' => null],
                    'paymongo' => ['icon' => 'fa-mobile-alt', 'label' => 'PayMongo History', 'badge' => null],
                ];
                foreach ($tabs as $key => $tab):
                    $active = $filter === $key;
                    ?>
                    <a href="?filter=<?= $key ?>"
                        class="<?= $active ? 'tab-active' : 'tab-inactive' ?> flex items-center gap-2 py-4 px-3 text-sm whitespace-nowrap transition-colors">
                        <i class="fas <?= $tab['icon'] ?> text-xs"></i>
                        <?= $tab['label'] ?>
                        <?php if ($tab['badge'] !== null && $tab['badge'] > 0): ?>
                            <span
                                class="bg-orange-500 text-white text-xs font-semibold px-1.5 py-0.5 rounded-full leading-none"><?= $tab['badge'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- ── METHOD FILTERS ── -->
        <?php if (in_array($filter, ['pending', 'verified'])): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Filter by Payment Method</p>
                <div class="flex flex-wrap gap-3">
                    <!-- All -->
                    <a href="?filter=<?= $filter ?>"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-all <?= !$payment_method_filter ? 'method-card-active' : 'method-card-inactive' ?>">
                        <i class="fas fa-border-all text-xs"></i> All Methods
                    </a>
                    <!-- PayMongo -->
                    <a href="?filter=<?= $filter ?>&method=paymongo"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-all <?= $payment_method_filter === 'paymongo' ? 'method-card-active' : 'method-card-inactive' ?>">
                        <i class="fas fa-mobile-alt text-xs"></i> PayMongo
                        <?php if ($filter === 'pending' && $paymongo_pending > 0): ?>
                            <span class="text-xs opacity-75">(<?= $paymongo_pending ?>)</span>
                        <?php endif; ?>
                    </a>
                    <!-- QR -->
                    <a href="?filter=<?= $filter ?>&method=qr"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-all <?= $payment_method_filter === 'qr' ? 'method-card-active' : 'method-card-inactive' ?>">
                        <i class="fas fa-qrcode text-xs"></i> QR Payment
                        <?php if ($filter === 'pending' && $qr_pending > 0): ?>
                            <span class="text-xs opacity-75">(<?= $qr_pending ?>)</span>
                        <?php endif; ?>
                    </a>
                </div>


            </div>
        <?php endif; ?>

       
        <?php if ($filter !== 'paymongo'): ?>
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr
                                class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3 text-left">Order</th>
                                <th class="px-5 py-3 text-left">Customer</th>
                                <th class="px-5 py-3 text-left">Amount</th>
                                <th class="px-5 py-3 text-left">Method</th>
                                <th class="px-5 py-3 text-left">Reference</th>
                                <th class="px-5 py-3 text-left">Status</th>
                                <th class="px-5 py-3 text-left">Verified By</th>
                                <th class="px-5 py-3 text-left">Date</th>
                                <th class="px-5 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($orders_result && $orders_result->num_rows > 0):
                                while ($order = $orders_result->fetch_assoc()):
                                    $amt = ($order['payment_status'] === 'verified' && !empty($order['final_total'])) ? $order['final_total'] : $order['total'];
                                    ?>
                                    <tr class="row-click" id="order-row-<?= $order['id'] ?>"
                                        onclick="viewOrderDetails(<?= $order['id'] ?>)" title="View order details">

                                        <!-- Order ID -->
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <span class="font-mono text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                                                #<?= $order['id'] ?>
                                            </span>
                                        </td>

                                        <!-- Customer -->
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 font-semibold text-xs flex items-center justify-center flex-shrink-0">
                                                    <?= strtoupper(substr($order['customer_name'] ?: 'U', 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900 leading-none">
                                                        <?= htmlspecialchars($order['customer_name'] ?: 'N/A') ?></p>
                                                    <p class="text-xs text-gray-400 mt-0.5">
                                                        <?= htmlspecialchars($order['email'] ?? '') ?></p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Amount -->
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <span class="font-semibold text-gray-900">₱<?= number_format((float) $amt, 2) ?></span>
                                        </td>

                                        <!-- Method -->
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <?php if ($order['mode_payment'] === 'PayMongo'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1.5 text-xs font-medium badge-pm px-2.5 py-1 rounded-full">
                                                    <i class="fas fa-mobile-alt"></i> PayMongo
                                                </span>
                                            <?php elseif ($order['mode_payment'] === 'QR Payment'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1.5 text-xs font-medium badge-qr px-2.5 py-1 rounded-full">
                                                    <i class="fas fa-qrcode"></i> QR Payment
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="text-gray-500"><?= htmlspecialchars($order['mode_payment'] ?: 'N/A') ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Reference -->
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <code class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded block">
                                                <?= htmlspecialchars($order['reference_number'] ?: $order['reference_no'] ?: 'N/A') ?>
                                            </code>
                                            <?php if (!empty($order['paymongo_payment_id'])): ?>
                                                <code class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded block mt-1">
                                                        <?= htmlspecialchars($order['paymongo_payment_id']) ?>
                                                    </code>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <?php if ($order['payment_status'] === 'verified'): ?>
                                                <span
                                                    class="badge-verified inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    <i class="fas fa-check-circle text-xs"></i> Verified
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="badge-paid inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    <i class="fas fa-clock text-xs"></i> Paid
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Verified By -->
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <?php if ($order['verified_by_name']): ?>
                                                <p class="text-gray-800"><?= htmlspecialchars($order['verified_by_name']) ?></p>
                                                <?php if ($order['confirmed_at']): ?>
                                                    <p class="text-xs text-gray-400">
                                                        <?= date('M d, g:i A', strtotime($order['confirmed_at'])) ?></p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Date -->
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <p class="text-gray-800"><?= date('M d, Y', strtotime($order['created_at'])) ?></p>
                                            <p class="text-xs text-gray-400"><?= date('g:i A', strtotime($order['created_at'])) ?>
                                            </p>
                                        </td>

                                        <!-- Action -->
                                        <td class="px-5 py-4 whitespace-nowrap" onclick="event.stopPropagation()">
                                            <?php if ($order['payment_status'] === 'paid'): ?>
                                                <button onclick="verifyPayment(<?= $order['id'] ?>)"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg transition-colors">
                                                    <i class="fas fa-check text-xs"></i> Verify
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 italic">Done</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="9" class="py-16 text-center">
                                        <i class="fas fa-receipt text-4xl text-gray-200 mb-3 block"></i>
                                        <p class="text-gray-400 text-sm">
                                            <?php
                                            if ($filter === 'pending')
                                                echo 'No payments awaiting verification.';
                                            elseif ($filter === 'verified')
                                                echo 'No verified payments found.';
                                            else
                                                echo 'No payment records found.';
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

      
        <?php if ($filter === 'paymongo'):
            $pmr = $conn->query("SELECT o.*, vb.fullname as verified_by_name FROM orders o LEFT JOIN nobleaccount vb ON o.verified_by=vb.id WHERE o.mode_payment='PayMongo' ORDER BY o.created_at DESC LIMIT 100");
            ?>
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-mobile-alt text-green-600"></i>
                    <h2 class="font-semibold text-gray-900">PayMongo Transaction History</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr
                                class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3 text-left">Order</th>
                                <th class="px-5 py-3 text-left">Customer</th>
                                <th class="px-5 py-3 text-left">Amount</th>
                                <th class="px-5 py-3 text-left">Reference / Payment ID</th>
                                <th class="px-5 py-3 text-left">Status</th>
                                <th class="px-5 py-3 text-left">Verified By</th>
                                <th class="px-5 py-3 text-left">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($pmr && $pmr->num_rows > 0):
                                while ($t = $pmr->fetch_assoc()):
                                    $da = ($t['payment_status'] === 'verified' && !empty($t['final_total'])) ? $t['final_total'] : $t['total'];
                                    ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <span
                                                class="font-mono text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">#<?= $t['id'] ?></span>
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-green-100 text-green-600 font-semibold text-xs flex items-center justify-center flex-shrink-0">
                                                    <?= strtoupper(substr($t['customer_name'] ?: 'U', 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900 leading-none">
                                                        <?= htmlspecialchars($t['customer_name'] ?: 'N/A') ?></p>
                                                    <p class="text-xs text-gray-400 mt-0.5">
                                                        <?= htmlspecialchars($t['email'] ?? '') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <span class="font-semibold text-gray-900">₱<?= number_format((float) $da, 2) ?></span>
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <code class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded block">
                                                <?= htmlspecialchars($t['reference_number'] ?: $t['reference_no'] ?: 'N/A') ?>
                                            </code>
                                            <?php if (!empty($t['paymongo_payment_id'])): ?>
                                                <code class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded block mt-1">
                                                        <?= htmlspecialchars($t['paymongo_payment_id']) ?>
                                                    </code>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <?php if ($t['payment_status'] === 'verified'): ?>
                                                <span
                                                    class="badge-verified inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    <i class="fas fa-check-circle text-xs"></i> Verified
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="badge-paid inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    <i class="fas fa-clock text-xs"></i> Paid
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <?php if ($t['verified_by_name']): ?>
                                                <p class="text-gray-800"><?= htmlspecialchars($t['verified_by_name']) ?></p>
                                                <?php if ($t['confirmed_at']): ?>
                                                    <p class="text-xs text-gray-400">
                                                        <?= date('M d, g:i A', strtotime($t['confirmed_at'])) ?></p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <p class="text-gray-800"><?= date('M d, Y', strtotime($t['created_at'])) ?></p>
                                            <p class="text-xs text-gray-400"><?= date('g:i A', strtotime($t['created_at'])) ?></p>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="7" class="py-16 text-center">
                                        <i class="fas fa-mobile-alt text-4xl text-gray-200 mb-3 block"></i>
                                        <p class="text-gray-400 text-sm">No PayMongo transactions found.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

   
    <div id="loadingOverlay"
        class="fixed bottom-6 right-6 z-50 hidden items-center gap-3 bg-white border border-gray-200 shadow-xl rounded-xl px-5 py-3">
        <div class="w-4 h-4 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        <span class="text-sm text-gray-700 font-medium">Processing…</span>
    </div>

    <script>
        const BASE_URL = '<?= htmlspecialchars(BASE_URL, ENT_QUOTES) ?>';

        function showLoading() {
            const el = document.getElementById('loadingOverlay');
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
        function hideLoading() {
            const el = document.getElementById('loadingOverlay');
            el.classList.add('hidden');
            el.classList.remove('flex');
        }

        function viewOrderDetails(id) {
            const w = 1200, h = 800, l = (screen.width - w) / 2, t = (screen.height - h) / 2;
            window.open(BASE_URL + '/accountantorderdetail?id=' + id, 'OrderDetails',
                `width=${w},height=${h},left=${l},top=${t},scrollbars=yes,resizable=yes`);
        }

        function verifyPayment(id) {
            if (!confirm('Verify this payment? This cannot be undone.')) return;
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
                            updateRow(id, d.verified_by);
                        } else {
                            showAlert('error', d.message || 'Failed to verify payment.');
                        }
                    } catch (e) {
                        showAlert('error', 'Invalid server response.');
                    }
                })
                .catch(e => { hideLoading(); showAlert('error', 'Network error: ' + e.message); });
        }

        function updateRow(id, by) {
            const row = document.getElementById('order-row-' + id);
            if (!row) return;
            // Status cell (index 5)
            row.cells[5].innerHTML = `<span class="badge-verified inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full"><i class="fas fa-check-circle text-xs"></i> Verified</span>`;
            // Verified by cell (index 6)
            const now = new Date().toLocaleString('en-US', { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true });
            row.cells[6].innerHTML = `<p class="text-gray-800">${by}</p><p class="text-xs text-gray-400">${now}</p>`;
            // Action cell (index 8)
            row.cells[8].innerHTML = `<span class="text-xs text-gray-400 italic">Done</span>`;
            row.style.transition = 'background .4s';
            row.style.background = '#f0fdf4';
            setTimeout(() => row.style.background = '', 2500);
        }

        function showAlert(type, msg) {
            document.querySelectorAll('.alert-msg').forEach(a => a.remove());
            const styles = {
                success: 'bg-green-50 border-green-200 text-green-800',
                error: 'bg-red-50 border-red-200 text-red-800'
            };
            const icons = {
                success: 'fa-check-circle text-green-500',
                error: 'fa-exclamation-triangle text-red-500'
            };
            const el = document.createElement('div');
            el.className = `alert-msg fixed top-5 right-5 z-50 flex items-center gap-3 ${styles[type]} border px-4 py-3 rounded-xl shadow-lg text-sm max-w-sm`;
            el.innerHTML = `<i class="fas ${icons[type]}"></i><span class="flex-1">${msg}</span><button onclick="this.closest('div').remove()" class="ml-2 opacity-60 hover:opacity-100"><i class="fas fa-times"></i></button>`;
            document.body.appendChild(el);
            setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 5000);
        }
    </script>
</body>

</html>