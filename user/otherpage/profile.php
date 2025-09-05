<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

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

        // Check if the account is Google-based (optional flag or logic)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final check if logged in (either normal or Google)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login/Google callback
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
    // Get orders with payment status included
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

        // Separate pending orders
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


// Get user's billing addresses
$billing_addresses = [];
if ($user_id) {
    $stmt = $conn->prepare("
        SELECT * FROM billing_addresses 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $billing_addresses[] = $row;
    }
    $stmt->close();
}


$query = mysqli_query($conn, "SELECT is_verified FROM user_details WHERE user_id = '$user_id'");
$row = mysqli_fetch_assoc($query);

$is_verified = null; // default value
if ($row && isset($row['is_verified'])) {
    $is_verified = $row['is_verified'];
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT is_verified FROM user_details WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$user = $result->fetch_assoc();
$stmt->close();

// Safe access
$is_verified = $user['is_verified'] ?? null;


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1E40AF',
                        secondary: '#1F2937',
                        accent: '#3B82F6',
                        neutral: '#F8FAFC',
                        success: '#059669',
                        warning: '#D97706',
                        error: '#DC2626',
                        corporate: '#0F172A',
                        professional: '#334155'
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                        'roboto': ['Roboto', 'sans-serif']
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-in-out',
                        'slide-up': 'slideUp 0.4s ease-out'
                    }
                }
            }
        }
    </script>
    
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
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

<body class="min-h-screen bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Professional Header -->
    <div class="bg-black text-white shadow-lg">
        <div class="container mx-auto px-6 py-12">
            <div class="text-center">
                <h1 class="text-4xl font-bold font-inter tracking-tight mb-4">Account Management Portal</h1>
                <p class="text-blue-100 text-lg font-medium max-w-2xl mx-auto">
                    Comprehensive dashboard for account information, order tracking, and profile management
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-6 py-8 max-w-full">
        <!-- Professional Profile Section -->
        <div class="professional-card rounded-xl p-8 mb-8 animate-fade-in">
            <div class="flex flex-col lg:flex-row gap-8">
                
                <!-- Profile Information -->
                <div class="flex-1">
                    <div class="flex items-start gap-6">
                        <!-- Professional Avatar -->
                        <div class="relative">
                            <?php if ($user_picture): ?>
                                <div class="w-24 h-24 rounded-xl overflow-hidden border-2 border-gray-200 shadow-md">
                                    <img src="<?= htmlspecialchars($user_picture); ?>" alt="Profile Picture" class="w-full h-full object-cover">
                                </div>
                            <?php else: ?>
                                <div class="w-24 h-24 bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl flex items-center justify-center text-white text-2xl font-bold shadow-md">
                                    <?= strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($is_verified) && $is_verified == 1): ?>
                                <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-success rounded-full border-4 border-white flex items-center justify-center shadow-sm">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- User Details -->
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-3">
                                <h2 class="text-2xl font-bold text-gray-900 font-inter"><?= htmlspecialchars($user_name); ?></h2>
                                <?php if (!empty($is_verified) && $is_verified == 1): ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full border border-green-200">
                                        <i class="fas fa-shield-alt mr-1"></i>Verified Account
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-red-100 text-red-800 text-sm font-medium rounded-full border border-red-200">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Pending Verification
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="space-y-2 text-gray-600 mb-6">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-envelope text-gray-400 w-4"></i>
                                    <span class="font-medium"><?= htmlspecialchars($user_email); ?></span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div>
                                <?php if ($is_verified == 1): ?>
                                    <button disabled class="flex items-center gap-3 px-6 py-3 bg-green-50 text-green-700 font-semibold rounded-lg border border-green-200 cursor-not-allowed">
                                        <i class="fas fa-check-circle"></i>
                                        Account Verified
                                    </button>
                                <?php else: ?>
                                    <a href="settings.php" class="inline-flex items-center gap-3 px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-300 shadow-md hover:shadow-lg">
                                        <i class="fas fa-user-cog"></i>
                                        Complete Verification
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Feedback Form -->
<div class="mt-6 p-4 border rounded-lg bg-gray-50">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Comment on this Website</h3>
    <form action="profilerate.php" method="POST" class="space-y-3">
        <input type="hidden" name="user_id" value="<?= $user_id; ?>">
        
        <!-- Rating -->
        <div class="flex items-center gap-1">
            <?php for ($i=1; $i<=5; $i++): ?>
                <label>
                    <input type="radio" name="rating" value="<?= $i ?>" required class="hidden peer">
                    <i class="fas fa-star text-gray-300 peer-checked:text-yellow-500 cursor-pointer text-xl"></i>
                </label>
            <?php endfor; ?>
        </div>

        <!-- Comment -->
        <textarea name="comment" rows="3" class="w-full border rounded-lg p-2 text-sm" placeholder="Write your feedback..."></textarea>

        <!-- Submit -->
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Submit Feedback
        </button>
    </form>

    <?php
    // Kunin yung huling feedback na ginawa ng naka-login user para sa profile na ito
    $current_feedback = $conn->query("
        SELECT * FROM user_feedback 
        WHERE user_id = $user_id AND author_id = {$_SESSION['user_id']}
        ORDER BY created_at DESC LIMIT 1
    ");
    ?>

    <?php if ($current_feedback && $current_feedback->num_rows > 0): 
        $fb = $current_feedback->fetch_assoc(); ?>
        
        <details class="mt-4 border rounded-lg bg-white shadow-sm">
            <summary class="cursor-pointer px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-100 rounded-t-lg">
                Your Latest Review
            </summary>
            <div class="p-4 border-t">
                <!-- Rating -->
                <div class="flex items-center gap-1 mb-2">
                    <?php for ($i=1; $i<=5; $i++): ?>
                        <i class="fas fa-star <?= $i <= $fb['rating'] ? 'text-yellow-500' : 'text-gray-300' ?>"></i>
                    <?php endfor; ?>
                </div>

                <!-- Comment -->
                <p class="text-gray-700 text-sm mb-2"><?= htmlspecialchars($fb['comment']); ?></p>
                <span class="text-xs text-gray-500">Submitted on <?= date('M j, Y g:i A', strtotime($fb['created_at'])); ?></span>
            </div>
        </details>

    <?php endif; ?>
</div>


                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="lg:w-96">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <!-- Total Orders -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 mb-1">Total Orders</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= count($all_orders); ?></p>
                                </div>
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-shopping-bag text-blue-600"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Orders -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 mb-1">Pending</p>
                                    <p class="text-2xl font-bold text-warning"><?= count($pending_orders); ?></p>
                                </div>
                                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-clock text-orange-600"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Addresses Card -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-map-marker-alt text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Delivery Addresses</p>
                                    <p class="font-bold text-gray-900"><?= count($billing_addresses) ?> Saved</p>
                                </div>
                            </div>
                            <button onclick="openBillingModal()" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium">
                                <i class="fas fa-eye mr-1"></i>View
                            </button>
                        </div>
                        
                        <button onclick="window.location.href='update_billing_add.php'" class="w-full px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>Add New Address
                        </button>
                    </div>

                    <!-- Order History Button -->
                    <div class="mt-4">
                        <a href="order_history.php" class="w-full inline-flex items-center justify-center px-4 py-3 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition-colors font-medium">
                            <i class="fas fa-history mr-2"></i>View Order History
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Orders History - LEFT (2 columns) -->
            <div class="lg:col-span-2">
                <div class="professional-card rounded-xl p-6 animate-fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clipboard-list text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 font-inter">Recent Orders</h3>
                                <p class="text-sm text-gray-500">Order history and tracking information</p>
                            </div>
                        </div>
                        <?php if (!empty($all_orders)): ?>
                            <span class="px-3 py-1 bg-gray-100 text-gray-600 text-sm font-medium rounded-full">
                                <?php echo count($all_orders); ?> orders
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Search and Filters -->
                    <?php if (!empty($all_orders)): ?>
                        <div class="mb-6 space-y-4">
                            <input type="text" id="orderSearch" 
                                   placeholder="Search by order ID, date, or status..." 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm" 
                                   oninput="filterOrders()">

                            <div class="flex flex-wrap gap-2">
                                <button onclick="filterByPaymentStatus('all')"
                                    class="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors payment-filter active font-medium">
                                    All Orders
                                </button>
                                <button onclick="filterByPaymentStatus('pending')"
                                    class="px-4 py-2 text-sm rounded-lg bg-yellow-50 text-yellow-700 hover:bg-yellow-100 transition-colors payment-filter font-medium">
                                    Pending Payment
                                </button>
                                <button onclick="filterByPaymentStatus('verified')"
                                    class="px-4 py-2 text-sm rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition-colors payment-filter font-medium">
                                    Verified
                                </button>
                                <button onclick="filterByPaymentStatus('rejected')"
                                    class="px-4 py-2 text-sm rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition-colors payment-filter font-medium">
                                    Rejected
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($all_orders)): ?>
                        <!-- NO ORDERS UI -->
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-shopping-bag text-2xl text-gray-400"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">No Orders Found</h4>
                            <p class="text-gray-600 mb-6">You haven't placed any orders yet. Start exploring our products!</p>
                            <a href="index.php" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                <i class="fas fa-shopping-cart"></i>
                                Browse Products
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 max-h-[600px] overflow-y-auto pr-2" id="orderList">
                            <?php foreach ($all_orders as $order): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-gray-300 hover:shadow-md transition-all cursor-pointer order-item bg-white"
                                    data-id="<?php echo $order['id']; ?>"
                                    data-date="<?php echo strtolower(date('M j, Y g:i A', strtotime($order['created_at']))); ?>"
                                    data-payment-status="<?php echo strtolower($order['payment_status'] ?? 'pending'); ?>"
                                    onclick="window.location.href='order_tracking.php?order_id=<?php echo $order['id']; ?>'">
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-200">
                                                <span class="text-sm font-bold text-gray-600">#<?php echo $order['id']; ?></span>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900 mb-1">Order #<?php echo $order['id']; ?></p>
                                                <p class="text-sm text-gray-500">
                                                    <i class="far fa-calendar mr-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="text-right">
                                            <p class="font-bold text-lg text-gray-900 mb-2">₱<?php echo number_format($order['total'], 2); ?></p>
                                            
                                            <div class="flex flex-col gap-2 items-end">
                                                <!-- Order Status -->
                                                <span class="inline-flex items-center gap-2 px-3 py-1 text-xs rounded-full font-medium
                                                    <?php
                                                    switch ($order['status']) {
                                                        case 'Pending':
                                                            echo 'bg-orange-100 text-orange-800 border border-orange-200';
                                                            break;
                                                        case 'Ongoing':
                                                            echo 'bg-blue-100 text-blue-800 border border-blue-200';
                                                            break;
                                                        case 'Arrival':
                                                            echo 'bg-purple-100 text-purple-800 border border-purple-200';
                                                            break;
                                                        case 'Departure':
                                                            echo 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                                                            break;
                                                        case 'Complete':
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

                                                <!-- Payment Status -->
                                                <span class="inline-flex items-center gap-2 px-3 py-1 text-xs rounded-full font-medium
                                                    <?php
                                                    $payment_status = $order['payment_status'] ?? 'pending';
                                                    switch (strtolower($payment_status)) {
                                                        case 'verified':
                                                        case 'approved':
                                                            echo 'bg-green-100 text-green-800 border border-green-200';
                                                            break;
                                                        case 'rejected':
                                                        case 'declined':
                                                            echo 'bg-red-100 text-red-800 border border-red-200';
                                                            break;
                                                        case 'pending':
                                                        default:
                                                            echo 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                                                            break;
                                                    }
                                                    ?>">
                                                    <?php if (strtolower($payment_status) === 'verified' || strtolower($payment_status) === 'approved'): ?>
                                                        <i class="fas fa-check-circle"></i>
                                                    <?php elseif (strtolower($payment_status) === 'rejected' || strtolower($payment_status) === 'declined'): ?>
                                                        <i class="fas fa-times-circle"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-clock"></i>
                                                    <?php endif; ?>
                                                    Payment: <?php echo ucfirst($payment_status); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
                            <h3 class="text-xl font-bold text-gray-900 font-inter">Pending Orders</h3>
                            <p class="text-sm text-gray-500">Requires attention</p>
                        </div>
                    </div>

                    <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                        <?php if (empty($pending_orders)): ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-check-circle text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium">All Orders Current</p>
                                <p class="text-sm text-gray-400">No pending orders require attention</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_orders as $order): ?>
                                <div class="border border-orange-200 rounded-lg p-4 bg-orange-50/50 hover:bg-orange-50 transition-colors cursor-pointer" 
                                     onclick="window.location.href='order_tracking.php?order_id=<?php echo $order['id']; ?>'">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-semibold text-gray-900 mb-1">Order #<?php echo $order['id']; ?></p>
                                            <p class="text-sm font-medium text-gray-700">₱<?php echo number_format($order['total'], 2); ?></p>
                                            <p class="text-xs text-gray-500">
                                                <i class="far fa-calendar mr-1"></i>
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </p>
                                        </div>
                                        
                                        <div class="text-right space-y-2">
                                            <!-- Order Status -->
                                            <div class="flex items-center gap-2 justify-end">
                                                <i class="fas fa-circle text-orange-500 text-xs"></i>
                                                <span class="text-xs text-orange-600 font-medium">Pending</span>
                                            </div>

                                            <!-- Payment Status -->
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full font-medium
                                                <?php
                                                $payment_status = $order['payment_status'] ?? 'pending';
                                                switch (strtolower($payment_status)) {
                                                    case 'verified':
                                                    case 'approved':
                                                        echo 'bg-green-100 text-green-700 border border-green-200';
                                                        break;
                                                    case 'rejected':
                                                    case 'declined':
                                                        echo 'bg-red-100 text-red-700 border border-red-200';
                                                        break;
                                                    case 'pending':
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

                                            <!-- Update Payment Button (only show if payment is rejected) -->
                                            <?php if (strtolower($payment_status) === 'rejected'): ?>
                                                <button onclick="updatePayment(<?php echo $order['id']; ?>); event.stopPropagation();"
                                                    class="mt-2 w-full px-3 py-1 bg-blue-600 text-white text-xs rounded-md hover:bg-blue-700 transition-colors font-medium">
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

    <!-- Professional Order Details Modal -->
    <div id="orderModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-slide-up shadow-2xl">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                <h3 class="text-2xl font-bold text-gray-900 font-inter">Order Details</h3>
                <button onclick="closeModal()" class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200 transition-colors">
                    <i class="fas fa-times text-gray-600"></i>
                </button>
            </div>
            <div id="orderDetails" class="space-y-6">
                <!-- Order details will be loaded here -->
                <div class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Professional Billing Addresses Modal -->
    <div id="billingModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-slide-up shadow-2xl">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-map-marker-alt text-green-600"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 font-inter">Delivery Addresses</h3>
                        <p class="text-sm text-gray-600"><?= count($billing_addresses) ?> saved addresses</p>
                    </div>
                </div>
                <button onclick="closeBillingModal()" class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200 transition-colors">
                    <i class="fas fa-times text-gray-600"></i>
                </button>
            </div>

            <!-- Add New Address Button -->
            <div class="mb-6">
                <button onclick="window.location.href='update_billing_add.php'" class="w-full px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 transition-all duration-300 flex items-center justify-center gap-2 font-medium">
                    <i class="fas fa-plus"></i>
                    Add New Address
                </button>
            </div>

            <!-- Addresses Content -->
            <div class="space-y-4">
                <?php if (empty($billing_addresses)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-map-marker-alt text-2xl text-gray-400"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">No Addresses Saved</h4>
                        <p class="text-gray-600 mb-6">Add your first delivery address for faster checkout</p>
                        <button onclick="window.location.href='update_billing_add.php'" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            <i class="fas fa-plus"></i>
                            Add Address
                        </button>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($billing_addresses as $index => $address): ?>
                            <div class="border border-gray-200 rounded-lg p-6 hover:border-gray-300 hover:shadow-md transition-all duration-300 bg-white">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-user text-green-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-900"><?= htmlspecialchars($address['full_name']) ?></h4>
                                            <?php if ($index === 0): ?>
                                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full font-medium border border-green-200">Primary Address</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex gap-2">
                                        <button onclick="editAddress(<?= $address['id'] ?>)" 
                                                class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors" 
                                                title="Edit Address">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteAddress(<?= $address['id'] ?>, '<?= htmlspecialchars($address['full_name']) ?>')" 
                                                class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors" 
                                                title="Delete Address">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Contact Info -->
                                <div class="space-y-3 mb-4">
                                    <div class="flex items-center gap-3 text-sm text-gray-600">
                                        <i class="fas fa-phone text-gray-400 w-4"></i>
                                        <span><?= htmlspecialchars($address['phone']) ?></span>
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-map-marker-alt text-gray-500 mt-1 flex-shrink-0"></i>
                                        <div>
                                            <p class="text-sm text-gray-700 font-medium mb-1"><?= htmlspecialchars($address['address']) ?></p>
                                            <p class="text-xs text-gray-500">
                                                <?= htmlspecialchars($address['city'] . ', ' . $address['state'] . ' ' . $address['postal_code']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <?php if (!empty($address['notes'])): ?>
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="flex items-start gap-2">
                                                <i class="fas fa-sticky-note text-gray-400 text-xs mt-0.5"></i>
                                                <p class="text-xs text-gray-600 italic">
                                                    <?= htmlspecialchars($address['notes']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-black pattern-bg text-white py-16 mt-12 relative overflow-hidden">
        <!-- Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-500"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <!-- Main Footer Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

                <!-- Enhanced Branding Section -->
                <div class="lg:col-span-2">
                    <div class="flex items-center space-x-4 mb-6">
                        <!-- Logo with glow and pulse -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-2xl glow-effect floating overflow-hidden">
                                <img src="../img/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
                            </div>
                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-blue-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Text Branding -->
                        <div>
                            <h2 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">Noble Home</h2>

                        </div>
                    </div>

                    <p class="text-gray-300 leading-relaxed mb-6 max-w-md">
                        Crafting exceptional living spaces with unmatched quality and attention to detail. Your dream home awaits with our expert construction and design services.
                    </p>

                    <!-- Contact Info -->
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                    <path d="m18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">noblehomeconst.ph@gmail.com</span>
                        </div>
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">0968 591 6536</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Quick Links
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <nav class="space-y-3">
                        <a href="index" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Home</a>
                        <a href="about" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">About Us</a>
                        <a href="contact" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Contact</a>
                    </nav>
                </div>

                <!-- Services -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Our Services
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <ul class="space-y-3 text-gray-300">
                        <li class="hover:text-orange-300 transition-colors cursor-pointer">Appointment</li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                    </ul>
                </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent mb-8"></div>

            <!-- Bottom Section -->
            <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
                <!-- Copyright -->
                <div class="text-center lg:text-left">
                    <p class="text-gray-400 text-sm">
                        © 2025 Noble Home Construction. All rights reserved.
                    </p>
                    <p class="text-gray-500 text-xs mt-1">
                        Licensed & Insured | PCAB License No. 12345
                    </p>
                </div>

                <!-- Enhanced Social Media -->
                <div class="flex items-center space-x-4">
                    <span class="text-gray-400 text-sm mr-2">Follow us:</span>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Facebook">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M22 12a10 10 0 10-11.63 9.88v-6.99H8.4v-2.89h1.97V9.91c0-1.95 1.16-3.03 2.93-3.03.85 0 1.74.15 1.74.15v1.91h-.98c-.97 0-1.27.6-1.27 1.21v1.45h2.16l-.35 2.89h-1.81v6.99A10 10 0 0022 12z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Instagram">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.5.5.6.2 1 .6 1.5 1.1.4.4.8.9 1.1 1.5.2.5.4 1.3.5 2.5.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.5-.2.6-.6 1-1.1 1.5-.4.4-.9.8-1.5 1.1-.5.2-1.3.4-2.5.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.5-.5-.6-.2-1-.6-1.5-1.1-.4-.4-.8-.9-1.1-1.5-.2-.5-.4-1.3-.5-2.5C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.5.2-.6.6-1 1.1-1.5.4-.4.9-.8 1.5-1.1.5-.2 1.3-.4 2.5-.5C8.4 2.2 8.8 2.2 12 2.2zm0 2.3c-3.1 0-3.5 0-4.7.1-.9.1-1.4.2-1.8.4-.5.2-.8.4-1.2.8s-.6.7-.8 1.2c-.2.4-.3.9-.4 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1.9.2 1.4.4 1.8.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2.9.3 1.8.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c.9-.1 1.4-.2 1.8-.4.5-.2.8-.4 1.2-.8s.6-.7.8-1.2c.2-.4.3-.9.4-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-.9-.2-1.4-.4-1.8-.2-.5-.4-.8-.8-1.2s-.7-.6-1.2-.8c-.4-.2-.9-.3-1.8-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.7a5.8 5.8 0 100 11.6 5.8 5.8 0 000-11.6zm0 9.5a3.7 3.7 0 110-7.4 3.7 3.7 0 010 7.4zm5.9-9.8a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="LinkedIn">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                        </svg>
                    </a>
                </div>

                <!-- Back to Top Button -->
                <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
                    class="w-12 h-12 bg-orange-500 hover:bg-orange-600 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Background Pattern -->
        <div class="absolute bottom-0 right-0 opacity-5">
            <svg width="200" height="200" viewBox="0 0 200 200" fill="none">
                <path d="M50 50h100v100H50z" stroke="currentColor" stroke-width="2" />
                <path d="M70 70h60v60H70z" stroke="currentColor" stroke-width="1" />
                <path d="M90 90h20v20H90z" stroke="currentColor" stroke-width="1" />
            </svg>
        </div>
    </footer>

    <script>
        function filterOrders() {
            const input = document.getElementById('orderSearch').value.toLowerCase();
            const items = document.querySelectorAll('.order-item');

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

        function filterByPaymentStatus(status) {
            const items = document.querySelectorAll('.order-item');
            const buttons = document.querySelectorAll('.payment-filter');

            // Update active button
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

        function viewOrder(orderId) {
            window.location.href = 'order_tracking.php?order_id=' + orderId;
        }



        // Billing Address Functions
        function toggleBillingDropdown() {
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

        function editAddress(addressId) {
            // Redirect to edit page with address ID
            window.location.href = `update_billing_add.php?edit=${addressId}`;
        }

        function deleteAddress(addressId, fullName) {
            // Show confirmation dialog
            if (confirm(`Are you sure you want to delete the address for "${fullName}"?\n\nThis action cannot be undone.`)) {
                // Show loading state
                const button = event.target.closest('button');
                const originalContent = button.innerHTML;
                button.innerHTML = '<div class="w-4 h-4 border-2 border-red-600 border-t-transparent rounded-full animate-spin"></div>';
                button.disabled = true;

                // Send delete request
                fetch('delete_billing_address.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            address_id: addressId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            showNotification('Address deleted successfully!', 'success');
                            // Reload page after short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // Show error message
                            showNotification(data.message || 'Failed to delete address', 'error');
                            // Restore button
                            button.innerHTML = originalContent;
                            button.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('An error occurred while deleting the address', 'error');
                        // Restore button
                        button.innerHTML = originalContent;
                        button.disabled = false;
                    });
            }
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;

            // Set colors based on type
            switch (type) {
                case 'success':
                    notification.className += ' bg-green-500 text-white';
                    break;
                case 'error':
                    notification.className += ' bg-red-500 text-white';
                    break;
                default:
                    notification.className += ' bg-blue-500 text-white';
            }

            notification.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                ${type === 'success' ? 
                    '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>' :
                    '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>'
                }
            </div>
            <span class="font-medium">${message}</span>
        </div>
    `;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Billing Modal Functions
        function openBillingModal() {
            document.getElementById('billingModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeBillingModal() {
            document.getElementById('billingModal').classList.add('hidden');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside
        document.getElementById('billingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBillingModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBillingModal();
            }
        });

        function updatePayment(orderId) {
            // Redirect to update payment page with order ID
            window.location.href = `update_payment.php?order_id=${orderId}`;
        }
    </script>

</body>

</html>