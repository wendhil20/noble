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

// Count qualifying orders (₱20,000+, Delivered status only, count each order as 1)
$tier_stmt = $conn->prepare("
    SELECT COUNT(*) as qualifying_orders 
    FROM orders 
    WHERE user_id = ? 
    AND total >= 20000 
    AND status = 'Completed'
");
$tier_stmt->bind_param("i", $user_id);
$tier_stmt->execute();
$tier_result = $tier_stmt->get_result();
$qualifying_orders = $tier_result->fetch_assoc()['qualifying_orders'];
$tier_stmt->close();

// Function to calculate tier and get tier card info from database
function calculateUserTier($qualifying_orders, $conn) {
    $tier_info = [
        'qualifying_orders' => $qualifying_orders,
        'tier_card_id' => null,
        'tier_name' => 'Member',
        'tier_icon' => '',
        'discount_percent' => 0,
        'color' => 'from-blue-400 to-blue-600',
        'bg' => 'bg-blue-50',
        'text' => 'text-blue-800',
        'border' => 'border-blue-300',
        'progress' => ($qualifying_orders / 25) * 100,
        'next_target' => 'Silver',
        'orders_needed' => 25 - $qualifying_orders,
        'benefits' => ['Standard Support', 'Regular Promotions'],
        'card_image' => null
    ];
    
    if ($qualifying_orders >= 75) {
        // Platinum tier
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'platinum' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_card_id' => $card['id'],
                'tier_name' => 'Platinum',
                'tier_icon' => '',
                'discount_percent' => $card['card_discount'],
                'color' => 'from-purple-400 to-purple-600',
                'bg' => 'bg-purple-50',
                'text' => 'text-purple-800',
                'border' => 'border-purple-300',
                'progress' => 100,
                'next_target' => null,
                'orders_needed' => 0,
                'benefits' => ['60% Discount', 'VIP Support', 'Free Express Shipping', 'Exclusive Products'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    } elseif ($qualifying_orders >= 50) {
        // Gold tier
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'gold' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_card_id' => $card['id'],
                'tier_name' => 'Gold',
                'tier_icon' => '',
                'discount_percent' => $card['card_discount'],
                'color' => 'from-yellow-400 to-yellow-600',
                'bg' => 'bg-yellow-50',
                'text' => 'text-yellow-800',
                'border' => 'border-yellow-300',
                'progress' => ($qualifying_orders / 75) * 100,
                'next_target' => 'Platinum',
                'orders_needed' => 75 - $qualifying_orders,
                'benefits' => ['20% Discount', 'Priority Support', 'Free Shipping', 'Early Access'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    } elseif ($qualifying_orders >= 25) {
        // Silver tier
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'silver' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_card_id' => $card['id'],
                'tier_name' => 'Silver',
                'tier_icon' => '',
                'discount_percent' => $card['card_discount'],
                'color' => 'from-gray-400 to-gray-600',
                'bg' => 'bg-gray-50',
                'text' => 'text-gray-800',
                'border' => 'border-gray-300',
                'progress' => ($qualifying_orders / 50) * 100,
                'next_target' => 'Gold',
                'orders_needed' => 50 - $qualifying_orders,
                'benefits' => ['10% Discount', 'Priority Support', 'Special Offers'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    }
    
    return $tier_info;
}

// Get user's current tier info
$user_tier = calculateUserTier($qualifying_orders, $conn);

// Update user's tier card in database
function updateUserTierCard($user_id, $tier_card_id, $conn) {
    if ($tier_card_id) {
        $stmt = $conn->prepare("UPDATE users SET tiercard_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $tier_card_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Set to NULL for Member tier
        $stmt = $conn->prepare("UPDATE users SET tiercard_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Update user's tier card in the database
updateUserTierCard($user_id, $user_tier['tier_card_id'], $conn);

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
function getStatusBadge($status) {
    $status = strtolower($status ?? 'pending');
    $badges = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'confirmed' => 'bg-green-100 text-green-800',
        'shipped' => 'bg-purple-100 text-purple-800',
        'Completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];

    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
    return "<span class='px-2 py-1 rounded-full text-xs font-medium $class'>" . ucfirst($status) . "</span>";
}

// Function to check if order qualifies for tier (₱20K+, delivered)
function isOrderQualifying($order) {
    return $order['total'] >= 20000 && 
           strtolower($order['status']) === 'Completed';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Order History - Noble Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .tier-card-image {
            width: 80px;
            height: 50px;
            object-fit: contain;
            border-radius: 8px;
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
                <span class="text-gray-600 font-medium">Order History</span>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Enhanced Tier Status Card -->
        <div class="bg-gradient-to-r <?= $user_tier['color'] ?> rounded-xl shadow-lg text-white p-6 mb-6 tier-glow">
            <div class="flex flex-col lg:flex-row items-center justify-between">
                <div class="flex items-center gap-6 mb-4 lg:mb-0">
                    <div class="text-center">
                        <?php if ($user_tier['card_image']): ?>
                            <img src="../../uploads/<?= $user_tier['card_image'] ?>" 
                                 alt="<?= $user_tier['tier_name'] ?> Card" 
                                 class="tier-card-image mx-auto">
                        <?php else: ?>
                            <div class="text-4xl mb-2 text-white opacity-50">No Card</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold"><?= $user_tier['tier_name'] ?> Member</h2>
                        <p class="opacity-90"><?= $qualifying_orders ?> qualifying orders completed</p>
                        <p class="text-lg font-semibold opacity-95">
                            <i class="fas fa-percent mr-1"></i><?= number_format($user_tier['discount_percent'], 1) ?>% Discount
                        </p>
                        <?php if ($user_tier['next_target']): ?>
                            <p class="text-sm opacity-80"><?= $user_tier['orders_needed'] ?> more orders to reach <?= $user_tier['next_target'] ?>!</p>
                        <?php else: ?>
                            <p class="text-sm opacity-80 font-semibold">🎉 Maximum tier achieved!</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold"><?= $qualifying_orders ?></div>
                    <div class="text-sm opacity-90">Qualifying Orders</div>
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
                    <div class="bg-white h-3 rounded-full progress-bar" style="width: <?= min(100, $user_tier['progress']) ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tier Benefits & Requirements Info -->
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

            <!-- How Tier Works -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">How Tier System Works</h3>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <i class="fas fa-shopping-cart text-blue-500"></i>
                        <span>Each order counts as 1 point (regardless of items)</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <i class="fas fa-money-bill-wave text-green-500"></i>
                        <span>Order must be ₱20,000+ to qualify</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <i class="fas fa-truck text-purple-500"></i>
                        <span>Order must be delivered to count</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <i class="fas fa-infinity text-orange-500"></i>
                        <span>Tier benefits are permanent</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier Requirements Info with Database Values -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Tier Requirements</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center p-3 rounded-lg bg-blue-50 border border-blue-200">
                    <div class="text-sm mb-1 font-semibold">No Card</div>
                    <div class="font-medium text-sm">Member</div>
                    <div class="text-xs text-gray-600">0+ Orders</div>
                    <div class="text-xs font-semibold text-blue-600">0% Discount</div>
                </div>
                
                <?php
                // Get actual tier card data from database
                $tier_cards_stmt = $conn->query("SELECT * FROM tiercard ORDER BY card_discount ASC");
                $tier_requirements = [25, 50, 75]; // Silver, Gold, Platinum
                $tier_index = 0;
                
                while ($tier_card = $tier_cards_stmt->fetch_assoc()): ?>
                <div class="text-center p-3 rounded-lg <?= $qualifying_orders >= $tier_requirements[$tier_index] ? 'bg-green-50 border border-green-300' : 'bg-gray-50 border border-gray-200' ?>">
                    <?php if ($tier_card['card_image']): ?>
                        <img src="../../uploads/<?= $tier_card['card_image'] ?>" 
                             alt="<?= $tier_card['card_name'] ?> Card" 
                             class="w-12 h-8 object-contain rounded mx-auto mb-2">
                    <?php else: ?>
                        <div class="text-sm mb-1 text-gray-400">No Image</div>
                    <?php endif; ?>
                    <div class="font-medium text-sm"><?= ucfirst($tier_card['card_name']) ?></div>
                    <div class="text-xs text-gray-600"><?= $tier_requirements[$tier_index] ?>+ Orders</div>
                    <div class="text-xs font-semibold <?= $qualifying_orders >= $tier_requirements[$tier_index] ? 'text-green-600' : 'text-gray-500' ?>">
                        <?= number_format($tier_card['card_discount'], 1) ?>% Discount
                    </div>
                </div>
                <?php $tier_index++; endwhile; ?>
            </div>
            <div class="mt-3 text-sm text-gray-600 text-center">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                Only <strong>delivered</strong> orders with ₱20,000+ total count towards tier progression (1 order = 1 point)
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
                        placeholder="Search by reference number, customer name..."
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
                    $is_qualifying = $is_high_value && $is_delivered;
                    ?>
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow order-item <?= $is_qualifying ? 'border-l-4 border-green-500' : ($is_high_value ? 'border-l-4 border-yellow-400' : '') ?>"
                        data-reference="<?= strtolower($order['reference_no']) ?>"
                        data-customer="<?= strtolower($order['customer_name']) ?>"
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
                                        <?php elseif ($is_high_value && !$is_delivered): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-truck mr-1"></i>High Value (Pending)
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <i class="fas fa-user w-4"></i>
                                                <span class="font-medium">Customer:</span>
                                            </div>
                                            <div class="ml-6"><?= htmlspecialchars($order['customer_name']) ?></div>
                                        </div>
                                        
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <i class="fas fa-calendar w-4"></i>
                                                <span class="font-medium">Order Date:</span>
                                            </div>
                                            <div class="ml-6"><?= date('M j, Y - g:i A', strtotime($order['created_at'])) ?></div>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <i class="fas fa-credit-card w-4"></i>
                                                <span class="font-medium">Payment:</span>
                                            </div>
                                            <div class="ml-6"><?= htmlspecialchars($order['mode_payment']) ?></div>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <i class="fas fa-phone w-4"></i>
                                                <span class="font-medium">Mobile:</span>
                                            </div>
                                            <div class="ml-6"><?= htmlspecialchars($order['mobile']) ?></div>
                                        </div>
                                    </div>

                                    <div class="mt-3 text-sm text-gray-600">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-map-marker-alt w-4"></i>
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
                                                <i class="fas fa-check-circle"></i> +1 Tier Point
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
                    const customer = item.dataset.customer;
                    const status = item.dataset.status;
                    const isQualifying = item.dataset.qualifying === 'true';
                    const isHighValue = item.dataset.highValue === 'true';
                    const isDelivered = item.dataset.delivered === 'true';

                    const matchesSearch = !searchTerm || 
                        reference.includes(searchTerm) || 
                        customer.includes(searchTerm);
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

                // Show/hide empty state message
                const visibleOrders = document.querySelectorAll('.order-item[style="display: block;"], .order-item:not([style*="display: none"])').length;
                const ordersContainer = document.getElementById('ordersContainer');
                
                if (visibleOrders === 0 && (searchTerm || selectedStatus || selectedTier)) {
                    if (!document.getElementById('noResults')) {
                        const noResults = document.createElement('div');
                        noResults.id = 'noResults';
                        noResults.className = 'bg-white rounded-lg shadow-sm p-8 text-center';
                        noResults.innerHTML = `
                            <div class="text-gray-400 text-4xl mb-4">🔍</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No orders found</h3>
                            <p class="text-gray-500">Try adjusting your search or filter criteria</p>
                        `;
                        ordersContainer.appendChild(noResults);
                    }
                } else {
                    const existingNoResults = document.getElementById('noResults');
                    if (existingNoResults) {
                        existingNoResults.remove();
                    }
                }
            }

            searchInput.addEventListener('input', filterOrders);
            statusFilter.addEventListener('change', filterOrders);
            tierFilter.addEventListener('change', filterOrders);

            // Add click animation to tier cards
            const tierCard = document.querySelector('.tier-glow');
            if (tierCard) {
                tierCard.addEventListener('click', function() {
                    this.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 200);
                });
            }

            // Show tier achievement notification for new users
            const currentTier = '<?= $user_tier['tier_name'] ?>';
            const qualifyingOrders = <?= $qualifying_orders ?>;
            
            if (qualifyingOrders === 25 || qualifyingOrders === 50 || qualifyingOrders === 75) {
                // Show congratulations for reaching a new tier
                setTimeout(() => {
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-4 right-4 bg-green-600 text-white p-4 rounded-lg shadow-lg z-50';
                    notification.innerHTML = `
                        <div class="flex items-center gap-2">
                            <i class="fas fa-trophy text-yellow-300"></i>
                            <span class="font-medium">Congratulations! You've reached ${currentTier} tier!</span>
                        </div>
                    `;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 5000);
                }, 1000);
            }
        });

        // Auto-refresh tier status every 5 minutes to catch any new qualifying orders
        setInterval(function() {
            // Only refresh if user is active (to avoid unnecessary server requests)
            if (document.visibilityState === 'visible') {
                // Check if there are any pending high-value orders that might have been delivered
                const pendingHighValue = document.querySelectorAll('[data-high-value="true"][data-delivered="false"]');
                if (pendingHighValue.length > 0) {
                    window.location.reload();
                }
            }
        }, 300000); // 5 minutes

        // Add visual feedback when hovering over tier qualifying orders
        document.querySelectorAll('[data-qualifying="true"]').forEach(order => {
            order.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 4px 12px rgba(34, 197, 94, 0.15)';
            });
            order.addEventListener('mouseleave', function() {
                this.style.boxShadow = '';
            });
        });
    </script>

</body>

</html>