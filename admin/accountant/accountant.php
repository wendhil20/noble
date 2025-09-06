<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Add this after: require_role(['accountant', 'superadmin']);

// =============================== 
// PAYPAL CONFIGURATION 
// =============================== 
$api_username = "sb-e9jug45931077_api1.business.example.com";
$api_password = "JA7M5EZ3V8ZDY3L8";
$api_signature = "AATf2rd9rVjFGl2pL2Qc416hwLDOAIUwgGoZOuE1LuBHdGu5B4TOQgqu";
$endpoint = "https://api-3t.sandbox.paypal.com/nvp";
$version = "204.0";

// Enhanced function to resolve current user details from nobleaccount table
function resolve_current_user_details($conn)
{
    // Return cached user details if already resolved
    if (!empty($_SESSION['current_user_details'])) {
        return $_SESSION['current_user_details'];
    }

    if (empty($_SESSION['noble_user'])) {
        return null;
    }

    $loginValue = $_SESSION['noble_user'];
    $userDetails = null;

    // Query nobleaccount table - this is the primary user table based on your schema
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
                'role' => $row['lvl'], // Using 'lvl' from nobleaccount table
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

    // If still not found, try by just email (in case fullname doesn't match exactly)
    if (!$userDetails) {
        $stmt = $conn->prepare("SELECT id, fullname, email, lvl, status, is_head, last_login FROM nobleaccount WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $loginValue);
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
    }

    // Cache the results and update session
    if ($userDetails) {
        $_SESSION['current_user_details'] = $userDetails;
        $_SESSION['user_id'] = $userDetails['id'];

        // Update last_activity in database
        $update_stmt = $conn->prepare("UPDATE nobleaccount SET last_activity = NOW() WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param('i', $userDetails['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }

    return $userDetails;
}

// Get current user details
$current_user = resolve_current_user_details($conn);
if (!$current_user) {
    $_SESSION['error'] = "Unable to identify current user. Please login again.";
    header("Location: ../../loginpage/index.php");
    exit();
}

function getPayPalTransactions($days = 30)
{
    global $api_username, $api_password, $api_signature, $endpoint, $version;

    $startDate = date("Y-m-d", strtotime("-$days days")) . "T00:00:00Z";

    $params = [
        "METHOD" => "TransactionSearch",
        "USER" => $api_username,
        "PWD" => $api_password,
        "SIGNATURE" => $api_signature,
        "VERSION" => $version,
        "STARTDATE" => $startDate,
        "ENDDATE" => date("Y-m-d") . "T23:59:59Z",
    ];

    $request = http_build_query($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if (!$response) {
        return false;
    }
    curl_close($ch);

    parse_str($response, $parsed_response);
    return $parsed_response;
}

$current_user_id = $current_user['id'];

// Initialize messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle AJAX requests for order verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'verify_payment') {
        $order_id = intval($_POST['order_id']);

        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID provided.']);
            exit();
        }

        // Begin transaction
        $conn->begin_transaction();

        // Get order details for notification and the total amount
        $order_query = $conn->prepare("SELECT user_id, customer_name, total, payment_status FROM orders WHERE id = ? LIMIT 1");
        $order_query->bind_param("i", $order_id);
        $order_query->execute();
        $order_result = $order_query->get_result();
        $order_data = $order_result->fetch_assoc();
        $order_query->close();

        if (!$order_data) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit();
        }

        if ($order_data['payment_status'] === 'verified') {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Order is already verified.']);
            exit();
        }

        $total_amount = isset($order_data['total']) ? (float)$order_data['total'] : 0.00;

        // Update order with verification details and set final_total to the order's total
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'verified', confirmed_at = CURRENT_TIMESTAMP, verified_by = ?, final_total = ? WHERE id = ?");
        $stmt->bind_param("idi", $current_user_id, $total_amount, $order_id);
        $stmt->execute();
        $stmt->close();

        // Send notification to customer
        if (!empty($order_data) && !empty($order_data['user_id'])) {
            $notification_message = "Your payment for Order #" . $order_id . " has been verified and confirmed by " . $current_user['name'] . ". Amount: ₱" . number_format($total_amount, 2);
            $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) VALUES (?, ?, 'payment_verified', ?, NOW())");
            if ($notification_stmt) {
                $notification_stmt->bind_param("iis", $order_data['user_id'], $current_user_id, $notification_message);
                $notification_stmt->execute();
                $notification_stmt->close();
            }
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified successfully',
            'verified_by' => $current_user['name']
        ]);
    } elseif ($_POST['action'] === 'reject_payment') {
        $order_id = intval($_POST['order_id']);
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');

        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID provided.']);
            exit();
        }

        if ($rejection_reason === '') {
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
            exit();
        }

        // Begin transaction
        $conn->begin_transaction();

        // Get order details for notification
        $order_query = $conn->prepare("SELECT user_id, customer_name, total, payment_status FROM orders WHERE id = ? LIMIT 1");
        $order_query->bind_param("i", $order_id);
        $order_query->execute();
        $order_result = $order_query->get_result();
        $order_data = $order_result->fetch_assoc();
        $order_query->close();

        if (!$order_data) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit();
        }

        if ($order_data['payment_status'] === 'rejected') {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Order is already rejected.']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'rejected', rejection_reason = ?, rejection_date = CURRENT_TIMESTAMP, rejected_at = CURRENT_TIMESTAMP, rejected_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $rejection_reason, $current_user_id, $order_id);
        $stmt->execute();
        $stmt->close();

        // Send notification to customer
        if (!empty($order_data) && !empty($order_data['user_id'])) {
            $notification_message = "Your payment for Order #" . $order_id . " has been rejected by " . $current_user['name'] . ". Reason: " . $rejection_reason . ". Please submit a new payment screenshot.";
            $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) VALUES (?, ?, 'payment_rejected', ?, NOW())");
            if ($notification_stmt) {
                $notification_stmt->bind_param("iis", $order_data['user_id'], $current_user_id, $notification_message);
                $notification_stmt->execute();
                $notification_stmt->close();
            }
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Payment rejected successfully',
            'rejected_by' => $current_user['name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    }

    exit();
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'pending';

// Get summary statistics
$pending_payments = 0;
$verified_today = 0;
$total_revenue_today = 0;
$rejected_payments = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'");
if ($result) {
    $pending_payments = $result->fetch_assoc()['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($result) {
    $verified_today = $result->fetch_assoc()['count'] ?? 0;
}

$result = $conn->query("SELECT COALESCE(SUM(final_total), 0) as total FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($result) {
    $total_revenue_today = $result->fetch_assoc()['total'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'rejected'");
if ($result) {
    $rejected_payments = $result->fetch_assoc()['count'] ?? 0;
}

// Fetch PayPal transactions
$paypal_transactions = getPayPalTransactions(30);
$paypal_revenue_today = 0;
$paypal_transaction_count = 0;

if ($paypal_transactions) {
    $i = 0;
    while (isset($paypal_transactions["L_TRANSACTIONID$i"])) {
        $amt = $paypal_transactions["L_AMT$i"] ?? "0.00";
        $timestamp = $paypal_transactions["L_TIMESTAMP$i"] ?? "";

        if ($timestamp && date('Y-m-d', strtotime($timestamp)) === date('Y-m-d')) {
            $paypal_revenue_today += floatval($amt);
        }
        $paypal_transaction_count++;
        $i++;
    }
}

// Get orders based on filter - UPDATED TO USE nobleaccount TABLE
$orders_result = null;
$where_condition = "";
switch ($filter) {
    case 'pending':
        $where_condition = "WHERE o.payment_status = 'pending' AND o.payment_screenshot IS NOT NULL";
        break;
    case 'verified':
        $where_condition = "WHERE o.payment_status = 'verified'";
        break;
    case 'rejected':
        $where_condition = "WHERE o.payment_status = 'rejected'";
        break;
    case 'paypal':
        // This will be handled separately below
        break;
    default:
        $where_condition = "WHERE o.payment_screenshot IS NOT NULL";
        break;
}

$orders_query = "
    SELECT o.*, 
           vb.fullname as verified_by_name,
           rb.fullname as rejected_by_name
    FROM orders o 
    LEFT JOIN nobleaccount vb ON o.verified_by = vb.id
    LEFT JOIN nobleaccount rb ON o.rejected_by = rb.id
    $where_condition
    ORDER BY 
        CASE o.payment_status 
            WHEN 'pending' THEN 1 
            WHEN 'verified' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        o.created_at DESC 
    LIMIT 100
";

$orders_result = $conn->query($orders_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                        'noble-orange-light': '#fb923c',
                        'noble-orange-dark': '#ea580c',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <header class="bg-white shadow-sm border-b-2 border-noble-orange">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-noble-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-calculator text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Accountant Dashboard</h1>
                        <p class="text-sm text-gray-600">Welcome back, <?php echo htmlspecialchars($current_user['name']); ?></p>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div id="successAlert" class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
                <button onclick="document.getElementById('successAlert').style.display='none'" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div id="errorAlert" class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <button onclick="document.getElementById('errorAlert').style.display='none'" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-500 transform hover:scale-105 transition-transform">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Payments</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($pending_payments); ?></p>
                        <p class="text-xs text-yellow-600 mt-1">Awaiting review</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500 transform hover:scale-105 transition-transform">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Verified Today</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($verified_today); ?></p>
                        <p class="text-xs text-green-600 mt-1"><?php echo date('M d, Y'); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-noble-orange transform hover:scale-105 transition-transform">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-peso-sign text-noble-orange text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Revenue Today</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($total_revenue_today, 2); ?></p>
                        <p class="text-xs text-noble-orange mt-1">Verified payments</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-red-500 transform hover:scale-105 transition-transform">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Rejected</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($rejected_payments); ?></p>
                        <p class="text-xs text-red-600 mt-1">Total rejected</p>
                    </div>
                </div>
            </div>

            <!-- Add this after the existing 4 cards -->
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500 transform hover:scale-105 transition-transform">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fab fa-paypal text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">PayPal Today</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($paypal_revenue_today, 2); ?></p>
                        <p class="text-xs text-blue-600 mt-1"><?php echo $paypal_transaction_count; ?> transactions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-xl shadow-sm mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                    <a href="?filter=pending" class="<?php echo $filter === 'pending' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        Pending (<?php echo number_format($pending_payments); ?>)
                    </a>
                    <a href="?filter=verified" class="<?php echo $filter === 'verified' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Verified
                    </a>
                    <a href="?filter=rejected" class="<?php echo $filter === 'rejected' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-times-circle mr-2"></i>
                        Rejected (<?php echo number_format($rejected_payments); ?>)
                    </a>
                    <a href="?filter=all" class="<?php echo $filter === 'all' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        All
                    </a>
                    <!-- Add this after the "All" tab -->
                    <a href="?filter=paypal" class="<?php echo $filter === 'paypal' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fab fa-paypal mr-2"></i>
                        PayPal History
                    </a>
                </nav>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Payment Verification Queue</h2>
                <p class="text-sm text-gray-600">Review and verify customer payments</p>
            </div>
            <?php if ($filter !== 'paypal'): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                                <?php while ($order = $orders_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors" id="order-row-<?php echo $order['id']; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <span class="bg-gray-100 px-2 py-1 rounded-md">#<?php echo $order['id']; ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-blue-600 text-xs font-semibold">
                                                        <?php echo strtoupper(substr($order['customer_name'] ?: 'U', 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $display_amount = $order['total'];
                                            if ($order['payment_status'] === 'verified' && isset($order['final_total']) && $order['final_total'] !== null && $order['final_total'] !== '') {
                                                $display_amount = $order['final_total'];
                                            }
                                            ?>
                                            <div class="text-sm font-semibold text-gray-900">₱<?php echo number_format((float)$display_amount, 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?></div>
                                            <?php if ($order['bank_type']): ?>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['bank_type']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <code class="text-sm bg-gray-100 px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($order['reference_number'] ?: $order['reference_no'] ?: 'N/A'); ?>
                                            </code>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                'verified' => 'bg-green-100 text-green-800 border-green-200',
                                                'rejected' => 'bg-red-100 text-red-800 border-red-200'
                                            ];
                                            $status = $order['payment_status'];
                                            $status_icons = [
                                                'pending' => 'fas fa-clock',
                                                'verified' => 'fas fa-check-circle',
                                                'rejected' => 'fas fa-times-circle'
                                            ];
                                            ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border <?php echo $status_colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?>">
                                                <i class="<?php echo $status_icons[$status] ?? 'fas fa-question'; ?> mr-1"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                            <?php if ($order['payment_status'] === 'rejected' && $order['rejection_reason']): ?>
                                                <div class="text-xs text-red-600 mt-1 cursor-pointer" title="<?php echo htmlspecialchars($order['rejection_reason']); ?>">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Reason: <?php echo htmlspecialchars(substr($order['rejection_reason'], 0, 20)) . (strlen($order['rejection_reason']) > 20 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php
                                                if ($order['payment_status'] === 'verified' && $order['verified_by_name']) {
                                                    echo htmlspecialchars($order['verified_by_name']);
                                                } elseif ($order['payment_status'] === 'rejected' && $order['rejected_by_name']) {
                                                    echo htmlspecialchars($order['rejected_by_name']);
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                            <?php if ($order['confirmed_at'] && $order['payment_status'] === 'verified'): ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M d, H:i', strtotime($order['confirmed_at'])); ?>
                                                </div>
                                            <?php elseif ($order['rejected_at'] && $order['payment_status'] === 'rejected'): ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M d, H:i', strtotime($order['rejected_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($order['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if ($order['payment_status'] === 'pending' && $order['payment_screenshot']): ?>
                                                <div class="flex space-x-2">
                                                    <?php
                                                    $screenshot_path = $order['payment_screenshot'];
                                                    // Improved path handling for both old and new formats
                                                    if ($screenshot_path) {
                                                        // If it already contains the full uploads path, use as is
                                                        if (strpos($screenshot_path, 'uploads/payment_screenshots/') !== false) {
                                                            $full_screenshot_path = $screenshot_path;
                                                        } else {
                                                            // Old format - just filename, add the path prefix
                                                            $full_screenshot_path = '../../uploads/payment_screenshots/' . $screenshot_path;
                                                        }
                                                    } else {
                                                        $full_screenshot_path = '';
                                                    }
                                                    ?>
                                                    <button onclick="viewPaymentScreenshot('<?php echo htmlspecialchars($full_screenshot_path); ?>', <?php echo $order['id']; ?>)"
                                                        class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-xs leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-noble-orange">
                                                        <i class="fas fa-eye mr-1"></i>
                                                        View
                                                    </button>
                                                    <button onclick="verifyPayment(<?php echo $order['id']; ?>)"
                                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                        <i class="fas fa-check mr-1"></i>
                                                        Verify
                                                    </button>
                                                    <button onclick="showRejectModal(<?php echo $order['id']; ?>)"
                                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                        <i class="fas fa-times mr-1"></i>
                                                        Reject
                                                    </button>
                                                </div>
                                            <?php elseif ($order['payment_screenshot']): ?>
                                                <?php
                                                $screenshot_path = $order['payment_screenshot'];
                                                // Improved path handling for both old and new formats
                                                if ($screenshot_path) {
                                                    // If it already contains the full uploads path, use as is
                                                    if (strpos($screenshot_path, 'uploads/payment_screenshots/') !== false) {
                                                        $full_screenshot_path = $screenshot_path;
                                                    } else {
                                                        // Old format - just filename, add the path prefix
                                                        $full_screenshot_path = '../../uploads/payment_screenshots/' . $screenshot_path;
                                                    }
                                                } else {
                                                    $full_screenshot_path = '';
                                                }
                                                ?>
                                                <button onclick="viewPaymentScreenshot('<?php echo htmlspecialchars($full_screenshot_path); ?>', <?php echo $order['id']; ?>)"
                                                    class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-xs leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-noble-orange">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    View
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">No screenshot</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-1">No orders found</h3>
                                            <p class="text-sm text-gray-500">
                                                <?php if ($filter === 'pending'): ?>
                                                    No pending payments to review at the moment.
                                                <?php elseif ($filter === 'verified'): ?>
                                                    No verified payments found.
                                                <?php elseif ($filter === 'rejected'): ?>
                                                    No rejected payments found.
                                                <?php else: ?>
                                                    No payment records found.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- PayPal Transactions Table (show only when filter is 'paypal') -->
        <?php if ($filter === 'paypal'): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">PayPal Transaction History</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($paypal_transactions): ?>
                                <?php
                                $i = 0;
                                $has_transactions = false;
                                while (isset($paypal_transactions["L_TRANSACTIONID$i"])):
                                    $has_transactions = true;
                                    $id = $paypal_transactions["L_TRANSACTIONID$i"];
                                    $type = $paypal_transactions["L_TYPE$i"] ?? "N/A";
                                    $status = $paypal_transactions["L_STATUS$i"] ?? "N/A";
                                    $amt = $paypal_transactions["L_AMT$i"] ?? "0.00";
                                    $fee = $paypal_transactions["L_FEEAMT$i"] ?? "0.00";
                                    $time = $paypal_transactions["L_TIMESTAMP$i"] ?? "N/A";

                                    if ($time !== "N/A") {
                                        $timestamp = strtotime($time);
                                        $formatted_time = date("M d, Y h:i A", $timestamp);
                                    } else {
                                        $formatted_time = "N/A";
                                    }

                                    $status_colors = [
                                        'Completed' => 'bg-green-100 text-green-800 border-green-200',
                                        'Pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                        'Failed' => 'bg-red-100 text-red-800 border-red-200'
                                    ];
                                    $status_class = $status_colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($id); ?></code>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($type); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            ₱<?php echo htmlspecialchars($amt); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ₱<?php echo htmlspecialchars($fee); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($formatted_time); ?>
                                        </td>
                                    </tr>
                                <?php
                                    $i++;
                                endwhile;

                                if (!$has_transactions):
                                ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <i class="fab fa-paypal text-4xl text-gray-300 mb-4"></i>
                                                <h3 class="text-lg font-medium text-gray-900 mb-1">No PayPal transactions found</h3>
                                                <p class="text-sm text-gray-500">No transactions found in the last 30 days.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-exclamation-triangle text-4xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-1">Unable to fetch PayPal data</h3>
                                            <p class="text-sm text-gray-500">There was an error connecting to PayPal API.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Payment Screenshot Modal -->
    <div id="screenshotModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between pb-3 border-b">
                <h3 class="text-lg font-bold text-gray-900">Payment Screenshot - Order #<span id="modalOrderId"></span></h3>
                <button onclick="closeScreenshotModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mt-4">
                <div class="flex justify-center">
                    <img id="screenshotImage" src="" alt="Payment Screenshot" class="max-w-full max-h-96 object-contain rounded-lg shadow-md">
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between pb-3 border-b">
                <h3 class="text-lg font-bold text-gray-900">Reject Payment - Order #<span id="rejectOrderId"></span></h3>
                <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mt-4">
                <label for="rejectionReason" class="block text-sm font-medium text-gray-700 mb-2">
                    Rejection Reason <span class="text-red-500">*</span>
                </label>
                <textarea id="rejectionReason" rows="4"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent"
                    placeholder="Please provide a detailed reason for rejecting this payment..."
                    maxlength="500"></textarea>
                <div class="text-xs text-gray-500 mt-1">
                    <span id="reasonCharCount">0</span>/500 characters
                </div>
            </div>
            <div class="flex items-center justify-end pt-4 border-t space-x-3">
                <button onclick="closeRejectModal()"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button onclick="submitRejection()"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                    id="submitRejectBtn">
                    <i class="fas fa-times mr-2"></i>
                    Reject Payment
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 shadow-xl">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-noble-orange"></div>
                <span class="text-gray-700">Processing...</span>
            </div>
        </div>
    </div>

    <script>
        let currentOrderId = null;

        function viewPaymentScreenshot(screenshotPath, orderId) {
            document.getElementById('modalOrderId').textContent = orderId;

            let fullPath;

            // Check if the path already contains the full uploads path
            if (screenshotPath.includes('uploads/payment_screenshots/')) {
                // New format: already has full path, just use it as is
                fullPath = screenshotPath;
            } else {
                // Old format: just filename, need to add the path prefix
                fullPath = '../../uploads/payment_screenshots/' + screenshotPath;
            }

            document.getElementById('screenshotImage').src = fullPath;
            document.getElementById('screenshotModal').classList.remove('hidden');
        }

        function closeScreenshotModal() {
            document.getElementById('screenshotModal').classList.add('hidden');
        }

        function showRejectModal(orderId) {
            currentOrderId = orderId;
            document.getElementById('rejectOrderId').textContent = orderId;
            document.getElementById('rejectionReason').value = '';
            document.getElementById('reasonCharCount').textContent = '0';
            document.getElementById('rejectionModal').classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
            currentOrderId = null;
        }

        // Character count for rejection reason
        document.getElementById('rejectionReason').addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('reasonCharCount').textContent = count;
        });

        function verifyPayment(orderId) {
            if (!confirm('Are you sure you want to verify this payment? This action cannot be undone.')) {
                return;
            }

            showLoading();

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=verify_payment&order_id=' + orderId
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showAlert('success', data.message);
                        // Update the row to reflect the new status
                        updateOrderRow(orderId, 'verified', data.verified_by);
                    } else {
                        showAlert('error', data.message || 'Failed to verify payment');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while verifying the payment');
                });
        }

        function submitRejection() {
            const reason = document.getElementById('rejectionReason').value.trim();

            if (!reason) {
                alert('Please provide a rejection reason.');
                return;
            }

            if (!currentOrderId) {
                alert('Invalid order ID.');
                return;
            }

            const submitBtn = document.getElementById('submitRejectBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Rejecting...';

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=reject_payment&order_id=' + currentOrderId + '&rejection_reason=' + encodeURIComponent(reason)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        updateOrderRow(currentOrderId, 'rejected', data.rejected_by, reason);
                        closeRejectModal();
                    } else {
                        showAlert('error', data.message || 'Failed to reject payment');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while rejecting the payment');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Reject Payment';
                });
        }

        function updateOrderRow(orderId, newStatus, processedBy, rejectionReason = null) {
            const row = document.getElementById('order-row-' + orderId);
            if (!row) return;

            // Update status cell
            const statusCell = row.cells[5]; // Status column (6th column, 0-indexed)
            let statusHTML = '';
            let statusClass = '';
            let statusIcon = '';

            switch (newStatus) {
                case 'verified':
                    statusClass = 'bg-green-100 text-green-800 border-green-200';
                    statusIcon = 'fas fa-check-circle';
                    break;
                case 'rejected':
                    statusClass = 'bg-red-100 text-red-800 border-red-200';
                    statusIcon = 'fas fa-times-circle';
                    break;
            }

            statusHTML = `<span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border ${statusClass}">
                            <i class="${statusIcon} mr-1"></i>
                            ${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}
                         </span>`;

            if (newStatus === 'rejected' && rejectionReason) {
                statusHTML += `<div class="text-xs text-red-600 mt-1 cursor-pointer" title="${rejectionReason}">
                                <i class="fas fa-info-circle mr-1"></i>
                                Reason: ${rejectionReason.length > 20 ? rejectionReason.substring(0, 20) + '...' : rejectionReason}
                               </div>`;
            }

            statusCell.innerHTML = statusHTML;

            // Update processed by cell
            const processedByCell = row.cells[6]; // Processed By column (7th column, 0-indexed)
            const currentDate = new Date();
            const timeString = currentDate.toLocaleString('en-US', {
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });

            processedByCell.innerHTML = `<div class="text-sm text-gray-900">${processedBy}</div>
                                        <div class="text-xs text-gray-500">${timeString}</div>`;

            // Update actions cell
            const actionsCell = row.cells[8]; // Actions column (9th column, 0-indexed)
            if (newStatus === 'verified' || newStatus === 'rejected') {
                // Find if there's a payment screenshot to show view button
                const currentActions = actionsCell.innerHTML;
                if (currentActions.includes('viewPaymentScreenshot')) {
                    // Extract the screenshot path from the current view button
                    const viewButton = actionsCell.querySelector('button[onclick*="viewPaymentScreenshot"]');
                    if (viewButton) {
                        actionsCell.innerHTML = viewButton.outerHTML;
                    }
                } else {
                    actionsCell.innerHTML = '<span class="text-xs text-gray-400">No actions</span>';
                }
            }
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `fixed top-4 right-4 z-50 max-w-md p-4 rounded-lg shadow-lg border ${
                type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'
            }`;

            alertDiv.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-2"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 ${
                        type === 'success' ? 'text-green-600 hover:text-green-800' : 'text-red-600 hover:text-red-800'
                    }">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            document.body.appendChild(alertDiv);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Close modals when clicking outside
        document.getElementById('screenshotModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeScreenshotModal();
            }
        });

        document.getElementById('rejectionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });

        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeScreenshotModal();
                closeRejectModal();
            }
        });

        // Auto-refresh page every 30 seconds to check for new pending payments
        <?php if ($filter === 'pending'): ?>
            setInterval(function() {
                // Only refresh if no modals are open
                if (document.getElementById('screenshotModal').classList.contains('hidden') &&
                    document.getElementById('rejectionModal').classList.contains('hidden')) {
                    window.location.reload();
                }
            }, 30000);
        <?php endif; ?>
    </script>
</body>

</html>