<?php
// index-profile-page-6.php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

// ✅ Restore session from remember_token (normal account or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// ✅ Retrieve user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? null;
$user_picture = $_SESSION['user_picture'] ?? null;

$orders = [];
$pending_orders = [];
$all_orders = [];

if ($user_id) {
    $stmt = $conn->prepare("
        SELECT *, payment_status FROM orders 
        WHERE email = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;

        if ($row['status'] === 'Pending') {
            $pending_orders[] = $row;
        }

        $all_orders[] = $row;
    }
    $stmt->close();
}

// Function to get order items
function getOrderItems($conn, $order_id)
{
    $stmt = $conn->prepare("
        SELECT * FROM order_items 
        WHERE order_id = ?
        ORDER BY id
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

// Function to get tracking history for an order
function getOrderTrackingHistory($conn, $order_id)
{
    $stmt = $conn->prepare("
        SELECT *
        FROM variant_tracking
        WHERE order_id = ?
        ORDER BY timestamp DESC
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tracking = [];
    while ($row = $result->fetch_assoc()) {
        $tracking[] = $row;
    }
    $stmt->close();
    return $tracking;
}

// Function to get the latest tracking status for an order
function getLatestOrderStatus($conn, $order_id)
{
    $stmt = $conn->prepare("
        SELECT status, place, timestamp, driver_name, truck_plate
        FROM variant_tracking
        WHERE order_id = ?
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    $stmt->close();
    return null;
}

// ✅ NEW: Function to check if an order has any replacement requests
function getOrderReplacementInfo($conn, $order_id)
{
    $stmt = $conn->prepare("
        SELECT rr.id, rr.status
        FROM replacement_requests rr
        INNER JOIN order_items oi ON rr.order_item_id = oi.id
        WHERE oi.order_id = ?
        ORDER BY rr.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $info = $result->fetch_assoc();
    $stmt->close();
    return $info;
}

// ✅ NEW: Helper to get replacement badge color, icon, and label
function getReplacementBadge($replacement_status)
{
    switch ($replacement_status) {
        case 'delivered':
        case 'picked_up':
            return [
                'color' => 'bg-green-100 text-green-700 border border-green-200',
                'icon' => '',
                'label' => 'Replacement Done',
            ];
        case 'rejected':
            return [
                'color' => 'bg-red-100 text-red-700 border border-red-200',
                'icon' => '',
                'label' => 'Replacement Rejected',
            ];
        case 'pending':
            return [
                'color' => 'bg-orange-100 text-orange-700 border border-orange-200',
                'icon' => '',
                'label' => 'Replacement Pending',
            ];
        case 'approved':
            return [
                'color' => 'bg-blue-100 text-blue-700 border border-blue-200',
                'icon' => '',
                'label' => 'Replacement Approved',
            ];
        case 'processing':
            return [
                'color' => 'bg-yellow-100 text-yellow-700 border border-yellow-200',
                'icon' => '',
                'label' => 'Replacement Processing',
            ];
        case 'item_is_loaded':
        case 'out_for_delivery':
        case 'out_for_pickup':
            return [
                'color' => 'bg-indigo-100 text-indigo-700 border border-indigo-200',
                'icon' => '',
                'label' => 'Replacement On the Way',
            ];
        case 'ready_for_pickup':
            return [
                'color' => 'bg-purple-100 text-purple-700 border border-purple-200',
                'icon' => '',
                'label' => 'Replacement Ready',
            ];
        default:
            return [
                'color' => 'bg-blue-100 text-blue-700 border border-blue-200',
                'icon' => '',
                'label' => 'Replacement ' . ucfirst(str_replace('_', ' ', $replacement_status)),
            ];
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>User Profile</title>
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        body {
            background: #f8fafc;
        }

        .professional-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        }

        .professional-card:hover {
            transform: translateY(-2px);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .payment-filter.active {
            background-color: #1E40AF !important;
            color: white !important;
        }

        /* Custom scrollbar for order list */
        #orderList::-webkit-scrollbar {
            width: 6px;
        }

        #orderList::-webkit-scrollbar-track {
            background: #F1F5F9;
            border-radius: 3px;
        }

        #orderList::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 3px;
        }

        #orderList::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }
    </style>
</head>

<body class="min-h-screen bg-gray-50 " style="font-family: 'Montserrat', sans-serif; color: #2f1200">
    

    <!-- Professional Header -->
    <div class="bg-black text-white shadow-lg">
        <div class="container mx-auto px-6 py-12">
            <div class="text-center">
                <h1 class="text-4xl tracking-tight mb-4"> Recent Order</h1>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-6 py-8 max-w-full">

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="professional-card rounded-xl p-6 animate-fade-in">
                    <!-- Header with icon and title -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clipboard-list text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="text-xl text-gray-900">Recent Orders</h3>
                                <p class="text-sm text-gray-500">Order history and tracking information</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <?php if (!empty($all_orders)): ?>
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 text-sm rounded-full">
                                    <?php echo count($all_orders); ?> orders
                                </span>
                            <?php endif; ?>

                            <a href="<?= BASE_URL ?>/history"
                                class="flex items-center gap-2 px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-900 transition-colors text-sm font-medium">
                                <i class="fas fa-history"></i>
                                View All
                            </a>
                        </div>
                    </div>

                    <!-- Search and Filters -->
                    <?php if (!empty($all_orders)): ?>
                        <div class="mb-6 space-y-4">
                            <input type="text" id="orderSearch" placeholder="Search by order ID, date, or status..."
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                oninput="filterOrders()">

                            <div class="flex flex-wrap gap-2">
                                <button onclick="filterByPaymentStatus('all')"
                                    class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors payment-filter active">
                                    All Orders
                                </button>
                                <button onclick="filterByPaymentStatus('pending')"
                                    class="px-4 py-2 text-sm rounded-lg bg-yellow-50 text-yellow-700 hover:bg-yellow-100 transition-colors payment-filter">
                                    Pending Payment
                                </button>
                                <button onclick="filterByPaymentStatus('verified')"
                                    class="px-4 py-2 text-sm rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition-colors payment-filter">
                                    Verified
                                </button>
                                <button onclick="filterByPaymentStatus('Completed')"
                                    class="px-4 py-2 text-sm rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition-colors payment-filter">
                                    Completed
                                </button>
                                <!-- ✅ Replacement filter now correctly filters by data-has-replacement -->
                                <button onclick="filterByReplacement()"
                                    class="px-4 py-2 text-sm rounded-lg bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors payment-filter"
                                    id="replacementFilterBtn">
                                    Replacement
                                </button>
                            </div>
                        </div>

                        <div
                            class="hidden md:grid grid-cols-7 gap-6 px-4 py-3 bg-white border-b-2 border-gray-300 mb-3 sticky top-0">
                            <div class="text-sm font-semibold text-gray-700 w-20">Order ID</div>
                            <div class="text-sm font-semibold text-gray-700 w-32">Date</div>
                            <div class="text-sm font-semibold text-gray-700 w-32">Total Amount</div>
                            <div class="text-sm font-semibold text-gray-700 w-32">Order Status</div>
                            <div class="text-sm font-semibold text-gray-700 w-32">Payment Status</div>
                            <div class="text-sm font-semibold text-gray-700 w-32">Replacement</div>
                            <div class="text-sm font-semibold text-gray-700 w-20">Action</div>
                        </div>

                        <!-- Orders List -->
                        <div id="orderList" class="space-y-3 max-h-[600px] overflow-y-auto">
                            <?php foreach ($all_orders as $order): ?>
                                <?php
                                // ✅ Get replacement info for this order
                                $replacement_info = getOrderReplacementInfo($conn, $order['id']);
                                $has_replacement = !empty($replacement_info);
                                $rep_status = $has_replacement ? strtolower($replacement_info['status']) : null;
                                $rep_badge = $has_replacement ? getReplacementBadge($rep_status) : null;
                                ?>

                                <!-- Desktop View -->
                                <div class="hidden md:grid grid-cols-7 gap-6 px-4 py-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-all order-item cursor-pointer"
                                    data-id="<?php echo $order['id']; ?>"
                                    data-date="<?php echo strtolower(date('M j, Y g:i A', strtotime($order['created_at']))); ?>"
                                    data-payment-status="<?php echo strtolower($order['payment_status'] ?? 'pending'); ?>"
                                    data-has-replacement="<?php echo $has_replacement ? 'yes' : 'no'; ?>"
                                    onclick="window.location.href='<?= BASE_URL ?>/ordertrack?order_id=<?php echo $order['id']; ?>'">

                                    <div class="flex items-center w-20">
                                        <span class="font-bold text-gray-900">#<?php echo $order['id']; ?></span>
                                    </div>

                                    <div class="flex items-center w-32">
                                        <div class="flex flex-col">
                                            <p class="text-sm text-gray-600 flex items-center gap-1">
                                                <i class="far fa-calendar"></i>
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo date('g:i A', strtotime($order['created_at'])); ?></p>
                                        </div>
                                    </div>

                                    <div class="flex items-center w-32">
                                        <span
                                            class="font-bold text-lg text-gray-900">₱<?php echo number_format($order['total'], 2); ?></span>
                                    </div>

                                    <!-- Order Status + Replacement Badge -->
                                    <div class="flex flex-col justify-center gap-1 w-32">
                                        <span class="inline-flex items-center gap-2 px-3 py-1 text-xs rounded-full w-fit
                                            <?php
                                            switch ($order['status']) {
                                                case 'Pending':
                                                    echo 'bg-orange-100 text-orange-800 border border-orange-200';
                                                    break;
                                                case 'Ongoing':
                                                    echo 'bg-blue-100 text-blue-800 border border-blue-200';
                                                    break;
                                                case 'processing':
                                                    echo 'bg-purple-100 text-purple-800 border border-purple-200';
                                                    break;
                                                case 'Ready for Pickup':
                                                    echo 'bg-gray-100 text-gray-800 border border-gray-200';
                                                    break;
                                                case 'Out for Delivery':
                                                    echo 'bg-red-100 text-red-800 border border-red-200';
                                                    break;
                                                case 'Out for Pickup':
                                                    echo 'bg-red-100 text-red-800 border border-red-200';
                                                    break;
                                                case 'Delivered':
                                                    echo 'bg-green-100 text-green-800 border border-green-100';
                                                    break;
                                                case 'Picked Up':
                                                    echo 'bg-green-100 text-green-800 border border-green-100';
                                                    break;
                                                case 'Completed':
                                                    echo 'bg-green-100 text-green-800 border border-green-200';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800 border border-gray-200';
                                                    break;
                                            }
                                            ?>">
                                            <?php if ($order['status'] === 'Complete'): ?>
                                                <i class="fas fa-check-circle"></i>
                                            <?php else: ?>
                                                <i class="fas fa-circle text-xs"></i>
                                            <?php endif; ?>
                                            <?php echo $order['status']; ?>
                                        </span>


                                    </div>

                                    <!-- Payment Status -->
                                    <div class="flex items-center w-32">
                                        <?php $payment_status = $order['payment_status'] ?? 'pending'; ?>
                                        <span class="inline-flex items-center gap-2 px-3 py-1 text-xs rounded-full
                                            <?php
                                            switch (strtolower($payment_status)) {
                                                case 'verified':
                                                case 'approved':
                                                    echo 'bg-green-100 text-green-800 border border-green-200';
                                                    break;
                                                case 'rejected':
                                                case 'declined':
                                                    echo 'bg-red-100 text-red-800 border border-red-200';
                                                    break;
                                                default:
                                                    echo 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                                                    break;
                                            }
                                            ?>">
                                            <?php if (in_array(strtolower($payment_status), ['verified', 'approved'])): ?>
                                                <i class="fas fa-check-circle"></i>
                                            <?php elseif (in_array(strtolower($payment_status), ['rejected', 'declined'])): ?>
                                                <i class="fas fa-times-circle"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock"></i>
                                            <?php endif; ?>
                                            <?php echo ucfirst($payment_status); ?>
                                        </span>
                                    </div>
                                    <!-- Replacement Column -->
                                    <div class="flex items-center w-64">
                                        <?php if ($has_replacement): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full <?= $rep_badge['color'] ?>">
                                               <i class="fa-solid fa-arrows-rotate"></i> <?= $rep_badge['icon'] ?><?= $rep_badge['label'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">—</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex items-center w-full">
                                        <a href="<?= BASE_URL ?>/ordertrack?order_id=<?php echo $order['id']; ?>"
                                            class="text-center gap-1 px-2 py-1 bg-red-600 text-white rounded-lg hover:bg-red-500 transition-colors text-sm font-semibold"
                                            onclick="event.stopPropagation()">
                                            Quick View
                                        </a>
                                    </div>
                                </div>

                                <!-- Mobile View - Card Layout -->
                                <div class="md:hidden bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all order-item"
                                    data-id="<?php echo $order['id']; ?>"
                                    data-date="<?php echo strtolower(date('M j, Y g:i A', strtotime($order['created_at']))); ?>"
                                    data-payment-status="<?php echo strtolower($order['payment_status'] ?? 'pending'); ?>"
                                    data-has-replacement="<?php echo $has_replacement ? 'yes' : 'no'; ?>">

                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <p class="font-bold text-lg text-gray-900">Order #<?php echo $order['id']; ?></p>
                                            <p class="text-xs text-gray-500 flex items-center gap-1 mt-1">
                                                <i class="far fa-calendar"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                            </p>
                                        </div>
                                        <span
                                            class="font-bold text-lg text-gray-900">₱<?php echo number_format($order['total'], 2); ?></span>
                                    </div>

                                    <div class="flex flex-col gap-2 mb-3">
                                        <!-- Order Status -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-gray-600">Status:</span>
                                            <span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full
                                                <?php
                                                switch ($order['status']) {
                                                    case 'Pending':
                                                        echo 'bg-orange-100 text-orange-800 border border-orange-200';
                                                        break;
                                                    case 'Ongoing':
                                                        echo 'bg-blue-100 text-blue-800 border border-blue-200';
                                                        break;
                                                    case 'processing':
                                                        echo 'bg-purple-100 text-purple-800 border border-purple-200';
                                                        break;
                                                    case 'Ready for Pickup':
                                                        echo 'bg-gray-100 text-gray-800 border border-gray-200';
                                                        break;
                                                    case 'Out for Delivery':
                                                        echo 'bg-red-100 text-red-800 border border-red-200';
                                                        break;
                                                    case 'Out for Pickup':
                                                        echo 'bg-red-100 text-red-800 border border-red-200';
                                                        break;
                                                    case 'Delivered':
                                                        echo 'bg-green-100 text-green-800 border border-green-100';
                                                        break;
                                                    case 'Picked Up':
                                                        echo 'bg-green-100 text-green-800 border border-green-100';
                                                        break;
                                                    case 'Completed':
                                                        echo 'bg-green-100 text-green-800 border border-green-200';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800 border border-gray-200';
                                                        break;
                                                }
                                                ?>">
                                                <?php if ($order['status'] === 'Complete'): ?>
                                                    <i class="fas fa-check-circle"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-circle text-xs"></i>
                                                <?php endif; ?>
                                                <?php echo $order['status']; ?>
                                            </span>
                                        </div>

                                        <!-- Payment Status -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-gray-600">Payment:</span>
                                            <?php $payment_status = $order['payment_status'] ?? 'pending'; ?>
                                            <span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full
                                                <?php
                                                switch (strtolower($payment_status)) {
                                                    case 'verified':
                                                    case 'approved':
                                                        echo 'bg-green-100 text-green-800 border border-green-200';
                                                        break;
                                                    case 'rejected':
                                                    case 'declined':
                                                        echo 'bg-red-100 text-red-800 border border-red-200';
                                                        break;
                                                    default:
                                                        echo 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                                                        break;
                                                }
                                                ?>">
                                                <?php if (in_array(strtolower($payment_status), ['verified', 'approved'])): ?>
                                                    <i class="fas fa-check-circle"></i>
                                                <?php elseif (in_array(strtolower($payment_status), ['rejected', 'declined'])): ?>
                                                    <i class="fas fa-times-circle"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-clock"></i>
                                                <?php endif; ?>
                                                <?php echo ucfirst($payment_status); ?>
                                            </span>
                                        </div>


                                    </div>

                                    <a href="<?= BASE_URL ?>/ordertrack?order_id=<?php echo $order['id']; ?>"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-semibold">
                                        View Details
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- NO ORDERS UI -->
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-shopping-bag text-2xl text-gray-400"></i>
                            </div>
                            <h4 class="text-lg text-gray-900 mb-2">No Orders Found</h4>
                            <p class="text-gray-600 mb-6">You haven't placed any orders yet. Start exploring our products!
                            </p>
                            <a href="index-page-1-A-B-C-D-E.php"
                                class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-shopping-cart"></i>
                                Browse Products
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Orders Sidebar - RIGHT (1 column) -->
            <div class="lg:col-span-1">
                <div class="professional-card rounded-xl p-6 border border-gray-200 animate-fade-in">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-hourglass-half text-orange-600"></i>
                        </div>
                        <div>
                            <h3 class="text-xl text-gray-900">Pending Orders</h3>
                            <p class="text-sm text-gray-500">Requires attention</p>
                        </div>
                    </div>

                    <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                        <?php if (empty($pending_orders)): ?>
                            <div class="text-center py-8">
                                <div
                                    class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-check-circle text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-600">All Orders Current</p>
                                <p class="text-sm text-gray-400">No pending orders require attention</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_orders as $order): ?>
                                <?php
                                // ✅ Also check replacement for pending orders in sidebar
                                $p_replacement_info = getOrderReplacementInfo($conn, $order['id']);
                                $p_has_replacement = !empty($p_replacement_info);
                                $p_rep_status = $p_has_replacement ? strtolower($p_replacement_info['status']) : null;
                                $p_rep_badge = $p_has_replacement ? getReplacementBadge($p_rep_status) : null;
                                $payment_status = $order['payment_status'] ?? 'pending';
                                ?>
                                <div class="border border-orange-200 rounded-lg p-4 bg-orange-50/50 hover:bg-orange-50 transition-colors cursor-pointer"
                                    onclick="window.location.href='<?= BASE_URL ?>/ordertrack?order_id=<?php echo $order['id']; ?>'">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-gray-900 mb-1">Order #<?php echo $order['id']; ?></p>
                                            <p class="text-sm text-gray-700">₱<?php echo number_format($order['total'], 2); ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <i class="far fa-calendar mr-1"></i>
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </p>
                                        </div>

                                        <div class="text-right space-y-2">
                                            <!-- Order Status -->
                                            <div class="flex items-center gap-2 justify-end">
                                                <i class="fas fa-circle text-orange-500 text-xs"></i>
                                                <span class="text-xs text-orange-600">Pending</span>
                                            </div>

                                            <!-- Payment Status -->
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full
                                                <?php
                                                switch (strtolower($payment_status)) {
                                                    case 'verified':
                                                    case 'approved':
                                                        echo 'bg-green-100 text-green-700 border border-green-200';
                                                        break;
                                                    case 'rejected':
                                                    case 'declined':
                                                        echo 'bg-red-100 text-red-700 border border-red-200';
                                                        break;
                                                    default:
                                                        echo 'bg-yellow-100 text-yellow-700 border border-yellow-200';
                                                        break;
                                                }
                                                ?>">
                                                <?php if (strtolower($payment_status) === 'verified'): ?>
                                                    <i class="fas fa-check text-xs"></i>
                                                <?php elseif (strtolower($payment_status) === 'rejected'): ?>
                                                    <i class="fas fa-times text-xs"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-clock text-xs"></i>
                                                <?php endif; ?>
                                                Pay: <?php echo ucfirst($payment_status); ?>
                                            </span>

                                            <!-- ✅ Replacement badge in sidebar -->
                                            <?php if ($p_has_replacement): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full <?= $p_rep_badge['color'] ?>">
                                                    <?= $p_rep_badge['icon'] ?>             <?= $p_rep_badge['label'] ?>
                                                </span>
                                            <?php endif; ?>

                                            <!-- Update Payment Button -->
                                            <?php if (strtolower($payment_status) === 'rejected'): ?>
                                                <button
                                                    onclick="updatePayment(<?php echo $order['id']; ?>); event.stopPropagation();"
                                                    class="mt-2 w-full px-3 py-1 bg-blue-600 text-white text-xs rounded-md hover:bg-blue-700 transition-colors">
                                                    <i class="fas fa-upload mr-1"></i>Update Payment
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm items-center justify-center z-50">
        <div
            class="bg-white rounded-xl p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-slide-up shadow-2xl">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                <h3 class="text-2xl text-gray-900">Order Details</h3>
                <button onclick="closeModal()"
                    class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200 transition-colors">
                    <i class="fas fa-times text-gray-600"></i>
                </button>
            </div>
            <div id="orderDetails" class="space-y-6">
                <div class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
            </div>
        </div>
    </div>

    <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>

   <script>

    window.filterOrders = function() {
        const input = document.getElementById('orderSearch').value.toLowerCase();
        const items = document.querySelectorAll('.order-item');

        document.getElementById('replacementFilterBtn').classList.remove('active');

        items.forEach(item => {
            const id = item.getAttribute('data-id');
            const date = item.getAttribute('data-date');
            const paymentStatus = item.getAttribute('data-payment-status') || '';

            const match = id.includes(input) ||
                date.includes(input) ||
                paymentStatus.includes(input);
            item.style.display = match ? '' : 'none';
        });
    }

    window.filterByPaymentStatus = function(status) {
        const items = document.querySelectorAll('.order-item');
        const buttons = document.querySelectorAll('.payment-filter');

        buttons.forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');

        items.forEach(item => {
            if (status === 'all') {
                item.style.display = '';
            } else {
                const paymentStatus = item.getAttribute('data-payment-status') || 'pending';
                item.style.display = paymentStatus === status ? '' : 'none';
            }
        });
    }

    window.filterByReplacement = function() {
        const items = document.querySelectorAll('.order-item');
        const buttons = document.querySelectorAll('.payment-filter');

        buttons.forEach(btn => btn.classList.remove('active'));
        document.getElementById('replacementFilterBtn').classList.add('active');

        items.forEach(item => {
            const hasReplacement = item.getAttribute('data-has-replacement');
            item.style.display = hasReplacement === 'yes' ? '' : 'none';
        });
    }

    window.viewOrder = function(orderId) {
        window.location.href = BASE_URL + '/ordertrack?order_id=' + orderId;
    }

    window.toggleBillingDropdown = function() {
        const dropdown = document.getElementById('billingDropdown');
        const icon = document.getElementById('dropdownIcon');

        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
            icon.style.transform = 'rotate(180deg)';
        } else {
            dropdown.classList.add('hidden');
            icon.style.transform = 'rotate(0deg)';
        }
    }

    window.showNotification = function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;

        switch (type) {
            case 'success': notification.className += ' bg-green-500 text-white'; break;
            case 'error':   notification.className += ' bg-red-500 text-white'; break;
            default:        notification.className += ' bg-blue-500 text-white'; break;
        }

        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    ${type === 'success'
                        ? '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>'
                        : '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>'
                    }
                </div>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(notification);
        setTimeout(() => { notification.classList.remove('translate-x-full'); }, 100);
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => { document.body.removeChild(notification); }, 300);
        }, 3000);
    }

    window.openBillingModal = function() {
        document.getElementById('billingModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    window.closeBillingModal = function() {
        document.getElementById('billingModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    window.updatePayment = function(orderId) {
        window.location.href = BASE_URL + '/45?order_id=' + orderId;
    }

    // Event listeners (no onclick needed)
    const billingModal = document.getElementById('billingModal');
    if (billingModal) {
        billingModal.addEventListener('click', function(e) {
            if (e.target === this) { window.closeBillingModal(); }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('billingModal');
            if (modal && !modal.classList.contains('hidden')) { window.closeBillingModal(); }
        }
    });
</script>

</body>

</html>