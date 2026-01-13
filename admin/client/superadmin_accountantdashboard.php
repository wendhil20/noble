<?php
// admin/client/superadmin_accountantdashboard.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get current user details
$loginValue = $_SESSION['noble_user'];
$current_user = null;

$stmt = $conn->prepare("SELECT id, fullname, email FROM nobleaccount WHERE email = ? OR fullname = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('ss', $loginValue, $loginValue);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $current_user = $row;
    }
    $stmt->close();
}

if (!$current_user) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get summary statistics
$pending_payments = 0;
$verified_payments = 0;
$rejected_payments = 0;
$total_revenue = 0;
$total_pending_amount = 0;

// Count pending payments
$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'");
if ($result) {
    $pending_payments = $result->fetch_assoc()['count'] ?? 0;
}

// Count verified payments
$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'verified'");
if ($result) {
    $verified_payments = $result->fetch_assoc()['count'] ?? 0;
}

// Count rejected payments
$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'rejected'");
if ($result) {
    $rejected_payments = $result->fetch_assoc()['count'] ?? 0;
}

// Get total verified revenue
$result = $conn->query("SELECT COALESCE(SUM(final_total), 0) as total FROM orders WHERE payment_status = 'verified'");
if ($result) {
    $total_revenue = $result->fetch_assoc()['total'] ?? 0;
}

// Get total pending amount
$result = $conn->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE payment_status = 'pending'");
if ($result) {
    $total_pending_amount = $result->fetch_assoc()['total'] ?? 0;
}

// Get payment method breakdown
$paymongo_count = 0;
$qr_count = 0;
$bank_count = 0;
$paypal_count = 0;

$result = $conn->query("SELECT mode_payment, COUNT(*) as count FROM orders GROUP BY mode_payment");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        switch ($row['mode_payment']) {
            case 'PayMongo':
                $paymongo_count = $row['count'];
                break;
            case 'QR Payment':
                $qr_count = $row['count'];
                break;
            case 'Bank Transfer':
                $bank_count = $row['count'];
                break;
            case 'PayPal':
                $paypal_count = $row['count'];
                break;
        }
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$payment_method = $_GET['payment_method'] ?? 'all';

// Get orders based on filter
$where_condition = "";
switch ($filter) {
    case 'pending':
        $where_condition = "WHERE o.payment_status = 'pending'";
        break;
    case 'verified':
        $where_condition = "WHERE o.payment_status = 'verified'";
        break;
    case 'rejected':
        $where_condition = "WHERE o.payment_status = 'rejected'";
        break;
    case 'all':
    default:
        $where_condition = "";
        break;
}

// Add payment method filter
if ($payment_method !== 'all') {
    if ($where_condition === "") {
        $where_condition = "WHERE o.mode_payment = '" . $conn->real_escape_string($payment_method) . "'";
    } else {
        $where_condition .= " AND o.mode_payment = '" . $conn->real_escape_string($payment_method) . "'";
    }
}

$recent_orders_query = "
    SELECT o.*, 
           vb.fullname as verified_by_name,
           rb.fullname as rejected_by_name
    FROM orders o 
    LEFT JOIN nobleaccount vb ON o.verified_by = vb.id
    LEFT JOIN nobleaccount rb ON o.rejected_by = rb.id
    $where_condition
    ORDER BY o.created_at DESC 
    LIMIT 100
";
$recent_orders_result = $conn->query($recent_orders_query);

// Get today's statistics
$verified_today = 0;
$revenue_today = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($result) {
    $verified_today = $result->fetch_assoc()['count'] ?? 0;
}

$result = $conn->query("SELECT COALESCE(SUM(final_total), 0) as total FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($result) {
    $revenue_today = $result->fetch_assoc()['total'] ?? 0;
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
    <header class="bg-white">
        <div class="w-full px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-noble-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-calculator text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Accountant Dashboard</h1>
                        <p class="text-sm text-gray-600">Payment Overview & Analytics</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="w-full px-6 py-8">
        
        <!-- Key Metrics Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Pending Payments Card -->
            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl shadow-sm p-6 hover:shadow-lg transition-all hover:scale-105 border border-yellow-200">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-yellow-700 uppercase tracking-wider mb-2">Pending Payments</p>
                        <p class="text-4xl font-bold text-yellow-900"><?php echo number_format($pending_payments); ?></p>
                        <p class="text-sm text-yellow-700 mt-3 font-medium">
                            <i class="fas fa-money-bill mr-2"></i>
                            ₱<?php echo number_format($total_pending_amount, 2); ?>
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-yellow-200 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-hourglass-half text-yellow-700 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Verified Payments Card -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow-sm p-6 hover:shadow-lg transition-all hover:scale-105 border border-green-200">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-green-700 uppercase tracking-wider mb-2">Verified Payments</p>
                        <p class="text-4xl font-bold text-green-900"><?php echo number_format($verified_payments); ?></p>
                        <p class="text-sm text-green-700 mt-3 font-medium">
                            <i class="fas fa-check-circle mr-2"></i>
                            Completed
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-green-200 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-check-double text-green-700 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Revenue Card -->
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl shadow-sm p-6 hover:shadow-lg transition-all hover:scale-105 border border-orange-200">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-orange-700 uppercase tracking-wider mb-2">Total Revenue</p>
                        <p class="text-4xl font-bold text-orange-900">₱<?php echo number_format($total_revenue, 2); ?></p>
                        <p class="text-sm text-orange-700 mt-3 font-medium">
                            <i class="fas fa-coins mr-2"></i>
                            Verified Only
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-orange-200 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-chart-line text-orange-700 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Rejected Payments Card -->
            <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl shadow-sm p-6 hover:shadow-lg transition-all hover:scale-105 border border-red-200">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-red-700 uppercase tracking-wider mb-2">Rejected Payments</p>
                        <p class="text-4xl font-bold text-red-900"><?php echo number_format($rejected_payments); ?></p>
                        <p class="text-sm text-red-700 mt-3 font-medium">
                            <i class="fas fa-times-circle mr-2"></i>
                            Declined
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-red-200 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-ban text-red-700 text-2xl"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Today's Summary & Payment Methods -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- Verified Today -->
            <div class="lg:col-span-1 bg-gradient-to-br from-emerald-50 via-green-50 to-emerald-100 rounded-xl shadow-sm p-6 border border-emerald-200 hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-emerald-900 uppercase tracking-wider">Today's Verification</h3>
                    <i class="fas fa-calendar-check text-emerald-600 text-3xl opacity-80"></i>
                </div>
                <div class="space-y-4">
                    <div class="bg-white bg-opacity-70 backdrop-blur rounded-lg p-4 border border-emerald-100">
                        <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wider mb-1">Orders Verified</p>
                        <p class="text-3xl font-bold text-emerald-900"><?php echo number_format($verified_today); ?></p>
                    </div>
                    <div class="bg-white bg-opacity-70 backdrop-blur rounded-lg p-4 border border-emerald-100">
                        <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wider mb-1">Revenue Generated</p>
                        <p class="text-2xl font-bold text-emerald-900">₱<?php echo number_format($revenue_today, 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Payment Methods Breakdown -->
            <div class="lg:col-span-2 bg-gradient-to-br from-blue-50 via-indigo-50 to-blue-100 rounded-xl shadow-sm p-6 border border-blue-200 hover:shadow-lg transition-shadow">
                <h3 class="text-base font-bold text-blue-900 uppercase tracking-wider mb-5 flex items-center">
                    <i class="fas fa-credit-card mr-3 text-blue-600 text-xl"></i>
                    Payment Methods Breakdown
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- PayMongo -->
                    <div class="bg-white bg-opacity-80 backdrop-blur rounded-lg p-4 border border-green-200 hover:border-green-400 transition-colors">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-mobile-alt text-green-600 text-lg"></i>
                            </div>
                            <span class="text-xs font-bold text-green-700 bg-green-100 px-2 py-1 rounded-full">
                                <?php echo number_format($paymongo_count); ?>
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-gray-800">PayMongo</p>
                        <p class="text-xs text-gray-500 mt-1">Mobile Payments</p>
                    </div>

                    <!-- QR Payment -->
                    <div class="bg-white bg-opacity-80 backdrop-blur rounded-lg p-4 border border-indigo-200 hover:border-indigo-400 transition-colors">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-qrcode text-indigo-600 text-lg"></i>
                            </div>
                            <span class="text-xs font-bold text-indigo-700 bg-indigo-100 px-2 py-1 rounded-full">
                                <?php echo number_format($qr_count); ?>
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-gray-800">QR Payment</p>
                        <p class="text-xs text-gray-500 mt-1">QR Code Transfers</p>
                    </div>

                    <!-- Bank Transfer -->
                    <div class="bg-white bg-opacity-80 backdrop-blur rounded-lg p-4 border border-purple-200 hover:border-purple-400 transition-colors">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-university text-purple-600 text-lg"></i>
                            </div>
                            <span class="text-xs font-bold text-purple-700 bg-purple-100 px-2 py-1 rounded-full">
                                <?php echo number_format($bank_count); ?>
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-gray-800">Bank Transfer</p>
                        <p class="text-xs text-gray-500 mt-1">Direct Bank Payments</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-xl shadow-sm mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6 overflow-x-auto" aria-label="Tabs">
                    <a href="?filter=all&payment_method=all" class="<?php echo (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        All Orders (<?php echo number_format($pending_payments + $verified_payments + $rejected_payments); ?>)
                    </a>
                    <a href="?filter=pending&payment_method=all" class="<?php echo (isset($_GET['filter']) && $_GET['filter'] === 'pending') ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        Pending (<?php echo number_format($pending_payments); ?>)
                    </a>
                    <a href="?filter=verified&payment_method=all" class="<?php echo (isset($_GET['filter']) && $_GET['filter'] === 'verified') ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Verified (<?php echo number_format($verified_payments); ?>)
                    </a>
                    <a href="?filter=rejected&payment_method=all" class="<?php echo (isset($_GET['filter']) && $_GET['filter'] === 'rejected') ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <i class="fas fa-times-circle mr-2"></i>
                        Rejected (<?php echo number_format($rejected_payments); ?>)
                    </a>
                </nav>
            </div>
        </div>

        <!-- Payment Method Filter Dropdown -->
        <div class="bg-white rounded-xl shadow-sm mb-6 p-6">
            <div class="flex items-center space-x-4">
                <label for="paymentMethodFilter" class="text-sm font-medium text-gray-700 whitespace-nowrap">
                    <i class="fas fa-filter mr-2 text-noble-orange"></i>
                    Filter by Payment Method:
                </label>
                <select id="paymentMethodFilter" onchange="filterByPaymentMethod(this.value)" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent bg-white text-gray-700">
                    <option value="all">All Payment Methods</option>
                    <option value="PayMongo" <?php echo ($payment_method === 'PayMongo') ? 'selected' : ''; ?>>
                        PayMongo
                    </option>
                    <option value="QR Payment" <?php echo ($payment_method === 'QR Payment') ? 'selected' : ''; ?>>
                        QR Payment
                    </option>
                    <option value="Bank Transfer" <?php echo ($payment_method === 'Bank Transfer') ? 'selected' : ''; ?>>
                        Bank Transfer
                    </option>
                </select>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Payment Orders</h2>
                <p class="text-sm text-gray-600">Complete payment record</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($recent_orders_result && $recent_orders_result->num_rows > 0): ?>
                            <?php while ($order = $recent_orders_result->fetch_assoc()): ?>
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
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?>
                                                </div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars(substr($order['email'], 0, 25)); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $display_amount = $order['total'];
                                        if ($order['payment_status'] === 'verified' && isset($order['final_total']) && $order['final_total'] !== null) {
                                            $display_amount = $order['final_total'];
                                        }
                                        ?>
                                        <div class="text-sm font-semibold text-gray-900">₱<?php echo number_format((float)$display_amount, 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 flex items-center">
                                            <?php if ($order['mode_payment'] === 'PayPal'): ?>
                                                <i class="fab fa-paypal text-blue-600 mr-2"></i>
                                            <?php elseif ($order['mode_payment'] === 'PayMongo'): ?>
                                                <i class="fas fa-mobile-alt text-green-600 mr-2"></i>
                                            <?php elseif ($order['mode_payment'] === 'QR Payment'): ?>
                                                <i class="fas fa-qrcode text-indigo-600 mr-2"></i>
                                            <?php elseif ($order['mode_payment'] === 'Bank Transfer'): ?>
                                                <i class="fas fa-university text-purple-600 mr-2"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'verified' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800'
                                        ];
                                        $status = $order['payment_status'];
                                        $status_icons = [
                                            'pending' => 'fas fa-clock',
                                            'verified' => 'fas fa-check-circle',
                                            'rejected' => 'fas fa-times-circle'
                                        ];
                                        ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$status] ?? 'bg-gray-100 text-gray-800'; ?>">
                                            <i class="<?php echo $status_icons[$status] ?? 'fas fa-question'; ?> mr-1"></i>
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php
                                            if ($order['payment_status'] === 'verified' && $order['verified_by_name']) {
                                                echo htmlspecialchars($order['verified_by_name']);
                                            } elseif ($order['payment_status'] === 'rejected' && $order['rejected_by_name']) {
                                                echo htmlspecialchars($order['rejected_by_name']);
                                            } else {
                                                echo 'Pending';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('H:i', strtotime($order['created_at'])); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                                        <h3 class="text-lg font-medium text-gray-900 mb-1">No orders found</h3>
                                        <p class="text-sm text-gray-500">No payment records available at this time.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        function filterByPaymentMethod(method) {
            const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
            // Store scroll position before navigating
            sessionStorage.setItem('scrollPosition', window.scrollY);
            window.location.href = `?filter=${currentFilter}&payment_method=${method}`;
        }

        // Restore scroll position after page loads
        window.addEventListener('load', function() {
            const scrollPosition = sessionStorage.getItem('scrollPosition');
            if (scrollPosition !== null) {
                window.scrollTo(0, parseInt(scrollPosition));
                sessionStorage.removeItem('scrollPosition');
            }
        });

        // Also handle tab clicks
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('nav a');
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                });
            });
        });
    </script>

</body>
</html>