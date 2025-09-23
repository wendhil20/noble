<?php
// order_history.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Pagination settings
$orders_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $orders_per_page;

// Count total orders
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $orders_per_page);
$count_stmt->close();

// Count qualifying orders for tier system (Within 6 months, ₱20,000+, Delivered status only)
$six_months_ago = date('Y-m-d H:i:s', strtotime('-6 months'));
$tier_stmt = $conn->prepare("
    SELECT COUNT(*) as qualifying_orders 
    FROM orders 
    WHERE user_id = ? 
    AND total >= 20000 
    AND status = 'delivered' 
    AND created_at >= ?
");
$tier_stmt->bind_param("is", $user_id, $six_months_ago);
$tier_stmt->execute();
$tier_result = $tier_stmt->get_result();
$qualifying_orders = $tier_result->fetch_assoc()['qualifying_orders'];
$tier_stmt->close();

// Get the date of the earliest qualifying order to show tier period
$period_stmt = $conn->prepare("
    SELECT MIN(created_at) as earliest_order 
    FROM orders 
    WHERE user_id = ? 
    AND total >= 20000 
    AND status = 'delivered' 
    AND created_at >= ?
");
$period_stmt->bind_param("is", $user_id, $six_months_ago);
$period_stmt->execute();
$period_result = $period_stmt->get_result();
$earliest_order = $period_result->fetch_assoc()['earliest_order'];
$period_stmt->close();

// Calculate days until tier reset
$days_until_reset = 0;
$next_reset_date = '';
if ($earliest_order) {
    $earliest_date = new DateTime($earliest_order);
    $six_months_later = $earliest_date->add(new DateInterval('P6M'));
    $now = new DateTime();
    $days_until_reset = max(0, $six_months_later->diff($now)->days);
    $next_reset_date = $six_months_later->format('M j, Y');
}

// Calculate user tier
function calculateTier($qualifying_orders) {
    if ($qualifying_orders >= 75) {
        return [
            'name' => 'Gold',
            'icon' => '🏆',
            'color' => 'from-yellow-400 to-yellow-600',
            'bg' => 'bg-yellow-50',
            'text' => 'text-yellow-800',
            'border' => 'border-yellow-300',
            'progress' => 100,
            'next_target' => null,
            'orders_needed' => 0,
            'benefits' => ['15% Discount', 'VIP Support', 'Free Express Shipping', 'Exclusive Products']
        ];
    } elseif ($qualifying_orders >= 50) {
        return [
            'name' => 'Platinum',
            'icon' => '💎',
            'color' => 'from-purple-400 to-purple-600',
            'bg' => 'bg-purple-50',
            'text' => 'text-purple-800',
            'border' => 'border-purple-300',
            'progress' => ($qualifying_orders / 75) * 100,
            'next_target' => 'Gold',
            'orders_needed' => 75 - $qualifying_orders,
            'benefits' => ['10% Discount', 'Priority Support', 'Free Shipping', 'Early Access']
        ];
    } elseif ($qualifying_orders >= 25) {
        return [
            'name' => 'Silver',
            'icon' => '🥈',
            'color' => 'from-gray-400 to-gray-600',
            'bg' => 'bg-gray-50',
            'text' => 'text-gray-800',
            'border' => 'border-gray-300',
            'progress' => ($qualifying_orders / 50) * 100,
            'next_target' => 'Platinum',
            'orders_needed' => 50 - $qualifying_orders,
            'benefits' => ['5% Discount', 'Priority Support', 'Special Offers']
        ];
    } else {
        return [
            'name' => 'Member',
            'icon' => '👤',
            'color' => 'from-blue-400 to-blue-600',
            'bg' => 'bg-blue-50',
            'text' => 'text-blue-800',
            'border' => 'border-blue-300',
            'progress' => ($qualifying_orders / 25) * 100,
            'next_target' => 'Silver',
            'orders_needed' => 25 - $qualifying_orders,
            'benefits' => ['Standard Support', 'Regular Promotions']
        ];
    }
}

$user_tier = calculateTier($qualifying_orders);

// Fetch orders with pagination
$stmt = $conn->prepare("
    SELECT 
        id,
        reference_no,
        customer_name,
        email,
        mobile,
        address,
        mode_payment,
        total,
        status,
        created_at
    FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $orders_per_page, $offset);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// Function to get order status badge
function getStatusBadge($status)
{
    $status = strtolower($status ?? 'pending');
    $badges = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'confirmed' => 'bg-green-100 text-green-800',
        'shipped' => 'bg-purple-100 text-purple-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];

    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
    return "<span class='px-2 py-1 rounded-full text-xs font-medium $class'>" . ucfirst($status) . "</span>";
}

// Function to check if order qualifies for tier (within 6 months, delivered, ₱20K+)
function isOrderQualifying($order, $six_months_ago) {
    return $order['total'] >= 20000 && 
           strtolower($order['status']) === 'delivered' && 
           $order['created_at'] >= $six_months_ago;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Order History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tier-glow {
            animation: glow 2s ease-in-out infinite alternate;
        }
        @keyframes glow {
            from { box-shadow: 0 0 10px rgba(59, 130, 246, 0.3); }
            to { box-shadow: 0 0 20px rgba(59, 130, 246, 0.6); }
        }
        .progress-bar {
            animation: fillProgress 2s ease-in-out;
        }
        @keyframes fillProgress {
            from { width: 0%; }
        }
        .countdown-timer {
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Enhanced Breadcrumb -->
    <nav class="bg-white border-b border-gray-200 px-4 py-3">
        <div class="">
            <div class="flex items-center space-x-2 text-sm">
                <a href="index" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
                    <i class="fas fa-home mr-1"></i>Home
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <span class="text-gray-600 font-medium">History</span>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Tier Status Card -->
        <div class="bg-gradient-to-r <?= $user_tier['color'] ?> rounded-xl shadow-lg text-white p-6 mb-6 tier-glow">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="flex items-center gap-4 mb-4 md:mb-0">
                    <div class="text-4xl"><?= $user_tier['icon'] ?></div>
                    <div>
                        <h2 class="text-2xl font-bold"><?= $user_tier['name'] ?> Member</h2>
                        <p class="opacity-90"><?= $qualifying_orders ?> qualifying orders (6 months)</p>
                        <?php if ($user_tier['next_target']): ?>
                            <p class="text-sm opacity-80"><?= $user_tier['orders_needed'] ?> more orders to reach <?= $user_tier['next_target'] ?>!</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold"><?= $qualifying_orders ?></div>
                    <div class="text-sm opacity-90">Completed Orders</div>
                    <div class="text-xs opacity-75">₱20K+ & Delivered</div>
                </div>
            </div>
            
            <?php if ($user_tier['next_target']): ?>
            <!-- Progress Bar -->
            <div class="mt-4">
                <div class="flex justify-between text-sm mb-2 opacity-90">
                    <span>Progress to <?= $user_tier['next_target'] ?></span>
                    <span><?= number_format($user_tier['progress'], 1) ?>%</span>
                </div>
                <div class="w-full bg-white bg-opacity-30 rounded-full h-3">
                    <div class="bg-white h-3 rounded-full progress-bar" style="width: <?= $user_tier['progress'] ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tier Period & Reset Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Tier Benefits -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Your Current Benefits</h3>
                <div class="space-y-2">
                    <?php foreach ($user_tier['benefits'] as $benefit): ?>
                        <div class="flex items-center gap-2 text-sm text-gray-700">
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span><?= $benefit ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tier Reset Info -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Tier Period</h3>
                <?php if ($earliest_order && $days_until_reset > 0): ?>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Current Period:</span>
                            <span class="font-medium"><?= date('M j, Y', strtotime($earliest_order)) ?> - <?= $next_reset_date ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Days Until Reset:</span>
                            <span class="font-medium countdown-timer text-orange-600"><?= $days_until_reset ?> days</span>
                        </div>
                        <div class="mt-3 p-3 bg-yellow-50 rounded-lg">
                            <p class="text-xs text-yellow-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                Your tier resets every 6 months. Keep completing ₱20K+ orders to maintain your status!
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-500">
                        <i class="fas fa-calendar-alt text-2xl mb-2"></i>
                        <p class="text-sm">Complete your first ₱20K+ order to start your tier journey!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tier Requirements Info -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Tier Requirements (6-Month Period)</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center p-3 rounded-lg <?= $qualifying_orders >= 0 ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50' ?>">
                    <div class="text-2xl mb-1">👤</div>
                    <div class="font-medium text-sm">Member</div>
                    <div class="text-xs text-gray-600">0+ Orders</div>
                </div>
                <div class="text-center p-3 rounded-lg <?= $qualifying_orders >= 25 ? 'bg-gray-50 border border-gray-300' : 'bg-gray-50' ?>">
                    <div class="text-2xl mb-1">🥈</div>
                    <div class="font-medium text-sm">Silver</div>
                    <div class="text-xs text-gray-600">25+ Orders</div>
                </div>
                <div class="text-center p-3 rounded-lg <?= $qualifying_orders >= 50 ? 'bg-purple-50 border border-purple-300' : 'bg-gray-50' ?>">
                    <div class="text-2xl mb-1">💎</div>
                    <div class="font-medium text-sm">Platinum</div>
                    <div class="text-xs text-gray-600">50+ Orders</div>
                </div>
                <div class="text-center p-3 rounded-lg <?= $qualifying_orders >= 75 ? 'bg-yellow-50 border border-yellow-300' : 'bg-gray-50' ?>">
                    <div class="text-2xl mb-1">🏆</div>
                    <div class="font-medium text-sm">Gold</div>
                    <div class="text-xs text-gray-600">75+ Orders</div>
                </div>
            </div>
            <div class="mt-3 text-sm text-gray-600 text-center">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                Only <strong>delivered</strong> orders with ₱20,000+ total within the last 6 months count towards tier progression
            </div>
        </div>

        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Order History</h1>
                    <p class="text-gray-600 mt-1">Track and manage your orders</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-orange-600"><?= $total_orders ?></div>
                    <div class="text-sm text-gray-500">Total Orders</div>
                    <div class="text-sm font-medium text-green-600 mt-1"><?= $qualifying_orders ?> Tier Qualifying</div>
                </div>
            </div>
        </div>

        <!-- Filter/Search Bar -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <input
                        type="text"
                        id="searchOrders"
                        placeholder="Search by reference number, product name..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" />
                </div>
                <div>
                    <select id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <select id="tierFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                        <option value="">All Orders</option>
                        <option value="qualifying">Tier Qualifying</option>
                        <option value="high-value">₱20K+ Orders</option>
                        <option value="delivered">Delivered Only</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                <svg class="mx-auto h-24 w-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No Orders Yet</h3>
                <p class="text-gray-500 mb-6">You haven't placed any orders yet. Start shopping to see your orders here.</p>
                <a href="../products/" class="inline-flex items-center gap-2 bg-orange-600 text-white px-6 py-3 rounded-lg hover:bg-orange-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4" id="ordersContainer">
                <?php foreach ($orders as $order): ?>
                    <?php 
                    $is_high_value = $order['total'] >= 20000;
                    $is_delivered = strtolower($order['status']) === 'delivered';
                    $is_within_6_months = $order['created_at'] >= $six_months_ago;
                    $is_qualifying = $is_high_value && $is_delivered && $is_within_6_months;
                    $order_age_months = (time() - strtotime($order['created_at'])) / (30 * 24 * 3600); // approximate months
                    ?>
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow order-item <?= $is_qualifying ? 'border-l-4 border-green-500' : ($is_high_value && $is_delivered ? 'border-l-4 border-yellow-400' : '') ?>"
                        data-reference="<?= strtolower($order['reference_no']) ?>"
                        data-status="<?= strtolower($order['status'] ?? 'pending') ?>"
                        data-qualifying="<?= $is_qualifying ? 'true' : 'false' ?>"
                        data-high-value="<?= $is_high_value ? 'true' : 'false' ?>"
                        data-delivered="<?= $is_delivered ? 'true' : 'false' ?>">
                        <div class="p-6">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <!-- Order Info -->
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3 flex-wrap">
                                        <h3 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($order['reference_no']) ?></h3>
                                        <?= getStatusBadge($order['status'] ?? 'pending') ?>
                                        
                                        <?php if ($is_qualifying): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-star mr-1"></i>Tier Qualifying
                                            </span>
                                        <?php elseif ($is_high_value && $is_delivered && !$is_within_6_months): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-clock mr-1"></i>Expired (<?= number_format($order_age_months, 1) ?>mo old)
                                            </span>
                                        <?php elseif ($is_high_value && !$is_delivered): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-truck mr-1"></i>High Value (Pending)
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 6v6M8 13v6m8-6v6m-4-6v6M3 21h18"></path>
                                                </svg>
                                                <span class="font-medium">Order Date:</span>
                                            </div>
                                            <div class="ml-6"><?= date('M j, Y - g:i A', strtotime($order['created_at'])) ?></div>
                                            <div class="ml-6 text-xs <?= $is_within_6_months ? 'text-green-600' : 'text-gray-400' ?>">
                                                <?= number_format($order_age_months, 1) ?> months ago
                                            </div>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                </svg>
                                                <span class="font-medium">Payment:</span>
                                            </div>
                                            <div class="ml-6"><?= htmlspecialchars($order['mode_payment']) ?></div>
                                        </div>
                                    </div>

                                    <div class="mt-3 text-sm text-gray-600">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                            <span><?= htmlspecialchars(substr($order['address'], 0, 80)) ?><?= strlen($order['address']) > 80 ? '...' : '' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Total & Actions -->
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                                    <div class="text-center sm:text-right">
                                        <div class="text-2xl font-bold <?= $is_qualifying ? 'text-green-700' : ($is_high_value ? 'text-blue-700' : 'text-gray-700') ?>">
                                            ₱<?= number_format($order['total'], 2) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">Total Amount</div>
                                        <?php if ($is_qualifying): ?>
                                            <div class="text-xs text-green-600 font-medium mt-1">
                                                <i class="fas fa-check-circle"></i> Counts for tier
                                            </div>
                                        <?php elseif ($is_high_value && $is_delivered && !$is_within_6_months): ?>
                                            <div class="text-xs text-yellow-600 font-medium mt-1">
                                                <i class="fas fa-clock"></i> Tier expired
                                            </div>
                                        <?php elseif ($is_high_value && !$is_delivered): ?>
                                            <div class="text-xs text-blue-600 font-medium mt-1">
                                                <i class="fas fa-truck"></i> Will count when delivered
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <a href="order_receipt.php?order_id=<?= $order['id'] ?>"
                                            class="inline-flex items-center gap-2 bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition text-sm font-medium">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            View Receipt
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <nav class="flex items-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" class="px-3 py-2 text-sm font-medium <?= $i == $page ? 'text-orange-600 bg-orange-50 border-orange-500' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50' ?> border rounded-md">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Enhanced Search and Filter functionality with new tier logic
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchOrders');
            const statusFilter = document.getElementById('statusFilter');
            const tierFilter = document.getElementById('tierFilter');
            const orderItems = document.querySelectorAll('.order-item');

            function filterOrders() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedStatus = statusFilter.value.toLowerCase();
                const selectedTier = tierFilter.value;

                orderItems.forEach(item => {
                    const reference = item.dataset.reference;
                    const status = item.dataset.status;
                    const isQualifying = item.dataset.qualifying === 'true';
                    const isHighValue = item.dataset.highValue === 'true';
                    const isDelivered = item.dataset.delivered === 'true';

                    const matchesSearch = !searchTerm || reference.includes(searchTerm);
                    const matchesStatus = !selectedStatus || status === selectedStatus;
                    
                    let matchesTier = true;
                    if (selectedTier === 'qualifying') {
                        matchesTier = isQualifying;
                    } else if (selectedTier === 'high-value') {
                        matchesTier = isHighValue;
                    } else if (selectedTier === 'delivered') {
                        matchesTier = isDelivered;
                    }

                    if (matchesSearch && matchesStatus && matchesTier) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            searchInput.addEventListener('input', filterOrders);
            statusFilter.addEventListener('change', filterOrders);
            tierFilter.addEventListener('change', filterOrders);

            // Update countdown timer every hour
            setInterval(function() {
                const countdownElements = document.querySelectorAll('.countdown-timer');
                countdownElements.forEach(element => {
                    let days = parseInt(element.textContent);
                    if (days > 0) {
                        // This is a simple approximation - in real implementation, 
                        // you'd want to calculate exact time remaining
                        const now = new Date();
                        const hours = now.getHours();
                        if (hours === 0) { // Update at midnight
                            element.textContent = (days - 1) + ' days';
                        }
                    }
                });
            }, 3600000); // Update every hour
        });

        // Show tier information modal
        function showTierInfo() {
            alert('Tier System:\n\n' +
                  '• Only DELIVERED orders with ₱20,000+ total count\n' +
                  '• Tier resets every 6 months\n' +
                  '• Orders older than 6 months do not count\n' +
                  '• Complete more high-value orders to maintain your tier!');
        }
    </script>

    <!-- Tier Info Button -->
    <div class="fixed bottom-6 right-6">
        <button onclick="showTierInfo()" 
                class="bg-orange-600 hover:bg-orange-700 text-white rounded-full p-3 shadow-lg transition-all duration-300 hover:scale-110"
                title="Tier System Info">
            <i class="fas fa-question text-lg"></i>
        </button>
    </div>
</body>

</html>