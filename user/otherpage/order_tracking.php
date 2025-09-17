<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    header('Location: profile.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? null;

// Get order details with billing address
$stmt = $conn->prepare("
    SELECT o.*, ba.latitude as delivery_lat, ba.longitude as delivery_lng,
           ba.full_name as delivery_name, ba.address as delivery_address,
           ba.city as delivery_city, ba.state as delivery_state
    FROM orders o
    LEFT JOIN billing_addresses ba ON o.billing_address_id = ba.id
    WHERE o.id = ? AND o.email = ?
");
$stmt->bind_param("is", $order_id, $user_email);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    header('Location: profile.php');
    exit;
}

$order = $order_result->fetch_assoc();
$stmt->close();

// Get warehouse/delivery settings (origin point)
$stmt = $conn->prepare("SELECT * FROM delivery_settings ORDER BY id DESC LIMIT 1");
$stmt->execute();
$delivery_settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get order items with tracking status
$stmt = $conn->prepare("
    SELECT * FROM order_items 
    WHERE order_id = ?
    ORDER BY id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];
while ($row = $items_result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();

// Function to get order level status steps - FIXED
function getOrderStatusSteps() {
    return [
        'pending' => [
            'name' => 'Order Pending',
            'description' => 'Order placed and awaiting confirmation',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'ongoing' => [
            'name' => 'Order Ongoing',
            'description' => 'Order confirmed and in progress',
            'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4'
        ],
        'processing' => [
            'name' => 'Order Processing',
            'description' => 'Items are being prepared',
            'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'
        ],
        'completed' => [
            'name' => 'Order Completed',
            'description' => 'All items have been delivered',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        ]
    ];
}

// Function to get status steps for local products
function getLocalStatusSteps() {
    return [
        'processing' => [
            'name' => 'Processing',
            'description' => 'Item being prepared',
            'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4'
        ],
        'ready_for_pickup' => [
            'name' => 'Ready for Pickup',
            'description' => 'Ready for delivery',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'out_for_delivery' => [
            'name' => 'Out for Delivery',
            'description' => 'Being delivered',
            'icon' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2'
        ],
        'delivered' => [
            'name' => 'Delivered',
            'description' => 'Item received',
            'icon' => 'M5 13l4 4L19 7'
        ]
    ];
}

// Function to get status steps for international products
function getInternationalStatusSteps() {
    return [
        'processing' => [
            'name' => 'Processing',
            'description' => 'Supplier preparing',
            'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4'
        ],
        'shipped_overseas' => [
            'name' => 'Shipped Overseas',
            'description' => 'Left supplier',
            'icon' => 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8'
        ],
        'in_transit_international' => [
            'name' => 'In Transit',
            'description' => 'On the way (sea/air)',
            'icon' => 'M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z'
        ],
        'customs_clearance' => [
            'name' => 'Customs',
            'description' => 'Customs inspection',
            'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
        ],
        'in_local_warehouse' => [
            'name' => 'Local Warehouse',
            'description' => 'Ready for dispatch',
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'
        ],
        'out_for_delivery' => [
            'name' => 'Out for Delivery',
            'description' => 'Being delivered',
            'icon' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2'
        ],
        'delivered' => [
            'name' => 'Delivered',
            'description' => 'Item received',
            'icon' => 'M5 13l4 4L19 7'
        ]
    ];
}

// Function to get current step index
function getCurrentStepIndex($status, $steps) {
    $status = strtolower(str_replace(' ', '_', $status));
    $step_keys = array_keys($steps);
    $index = array_search($status, $step_keys);
    return $index !== false ? $index : 0;
}

// Function to get order step index - FIXED
function getOrderStepIndex($status) {
    $status = strtolower($status);
    $steps = ['pending', 'ongoing', 'processing', 'completed']; // Updated to include all 4 statuses
    $index = array_search($status, $steps);
    return $index !== false ? $index : 0;
}

// Function to check if item is eligible for replacement
function isEligibleForReplacement($item, $order) {
    $item_status = strtolower($item['tracking_status'] ?? 'processing');
    
    // Check if item is delivered - that's the only requirement
    if ($item_status === 'delivered') {
        // Optional: Add time window check if needed (7 days)
        $delivery_date = $item['delivered_at'] ?? null;
        if ($delivery_date) {
            $days_since_delivery = (time() - strtotime($delivery_date)) / (60 * 60 * 24);
            return $days_since_delivery <= 7; // 7 days replacement window
        }
        // If no delivery date, still allow replacement for delivered items
        return true;
    }
    
    return false;
}

// Function to check if replacement was already requested
function hasReplacementRequest($item_id, $conn) {
    $stmt = $conn->prepare("SELECT id FROM replacement_requests WHERE order_item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_request = $result->num_rows > 0;
    $stmt->close();
    return $has_request;
}

// Check if order status allows showing item tracking - FIXED
$show_item_tracking = !in_array(strtolower($order['status']), ['pending']);

// Check if we have coordinates for both origin and destination
$show_map = $delivery_settings && 
           $order['delivery_lat'] && 
           $order['delivery_lng'] && 
           $delivery_settings['latitude'] && 
           $delivery_settings['longitude'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Order Tracking - Order #<?= $order['id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin=""/>
    
    <!-- Leaflet Routing Machine CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#FF6B35',
                        secondary: '#F7931E',
                        accent: '#FFE15D',
                        neutral: '#1F2937',
                        success: '#10B981',
                        warning: '#F59E0B',
                        error: '#EF4444'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-slide-in {
            animation: slideInUp 0.6s ease-out;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .compact-step {
            transition: all 0.3s ease;
        }
        
        .compact-step:hover {
            transform: translateY(-2px);
        }

        /* Map styles */
        #deliveryMap {
            height: 400px;
            border-radius: 12px;
            z-index: 1;
        }

        /* Hide leaflet routing machine instructions by default */
        .leaflet-routing-container {
            background: white;
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .leaflet-routing-container h2 {
            font-size: 14px;
            margin: 0 0 8px 0;
            color: #374151;
            font-weight: 600;
        }

        /* Mobile-specific styles */
        @media (max-width: 768px) {
            #deliveryMap {
                height: 300px;
            }
            
            .mobile-vertical-steps {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .mobile-vertical-steps .flex-1 {
                flex: none;
                width: 100%;
            }
            
            .mobile-step-item {
                display: flex;
                align-items: center;
                width: 100%;
                padding: 12px 0;
            }
            
            .mobile-step-line {
                width: 2px;
                height: 40px;
                margin: 0 19px;
                flex-shrink: 0;
            }
            
            .mobile-step-content {
                margin-left: 16px;
                flex: 1;
            }
            
            .mobile-item-steps {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .mobile-item-steps::-webkit-scrollbar {
                display: none;
            }
            
            .mobile-item-steps {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen font-poppins">
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-3 sm:px-4 py-4 sm:py-8 max-w-6xl">
        <!-- Header -->
        <div class="mb-6 sm:mb-8">
            <div class="flex items-center gap-3 sm:gap-4 mb-4">
                <a href="profile.php" class="flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Order Tracking</h1>
                    <p class="text-sm sm:text-base text-gray-600">Track your order progress</p>
                </div>
            </div>
        </div>

        <!-- Order Summary Card -->
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 animate-slide-in">
            <div class="flex flex-col gap-4 sm:gap-6">
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Order #<?= $order['id'] ?></h2>
                        <p class="text-sm sm:text-base text-gray-600"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
                        <p class="text-xs sm:text-sm text-gray-500"><?= count($order_items) ?> item(s)</p>
                    </div>
                </div>
                
                <div class="flex items-center justify-between border-t pt-4">
                    <div>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900">₱<?= number_format($order['final_total'], 2) ?></p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <!-- Payment Status -->
                        <span class="inline-flex items-center gap-1 px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-full
                            <?php
                            $payment_status = $order['payment_status'] ?? 'pending';
                            switch (strtolower($payment_status)) {
                                case 'verified':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'rejected':
                                    echo 'bg-red-100 text-red-800';
                                    break;
                                case 'pending':
                                default:
                                    echo 'bg-yellow-100 text-yellow-800';
                                    break;
                            }
                            ?>">
                            Pay: <?= ucfirst($payment_status) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Route Map -->
        <?php if ($show_map): ?>
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 animate-slide-in">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg sm:text-xl font-bold text-gray-900">Delivery Route</h3>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    </svg>
                    <span><?= number_format($order['delivery_distance'], 2) ?> km</span>
                </div>
            </div>
            
            <!-- Map Container -->
            <div id="deliveryMap" class="w-full"></div>
            
            <!-- Route Info -->
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-3 p-3 bg-green-50 rounded-lg">
                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-green-800">From: Warehouse</p>
                        <p class="text-xs text-green-600"><?= htmlspecialchars($delivery_settings['location_name']) ?></p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 p-3 bg-blue-50 rounded-lg">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-blue-800">To: Your Address</p>
                        <p class="text-xs text-blue-600"><?= htmlspecialchars($order['delivery_address']) ?>, <?= htmlspecialchars($order['delivery_city']) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Order Level Status -->
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 animate-slide-in">
            <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-4 sm:mb-6">Order Status</h3>
            
            <!-- Desktop/Tablet Horizontal Steps -->
            <div class="hidden sm:flex items-center justify-between relative">
                <?php 
                $order_steps = getOrderStatusSteps();
                $current_order_index = getOrderStepIndex($order['status']);
                $step_count = 0;
                $total_steps = count($order_steps);
                
                foreach ($order_steps as $step_key => $step_data): 
                    $is_completed = $step_count <= $current_order_index;
                    $is_current = $step_count === $current_order_index;
                    $is_last = $step_count === $total_steps - 1;
                ?>
                    <div class="flex flex-col items-center flex-1">
                        <!-- Status Circle -->
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mb-3 z-10
                            <?php 
                            if ($is_completed) {
                                echo $is_current ? 'bg-primary text-white animate-pulse-slow' : 'bg-success text-white';
                            } else {
                                echo 'bg-gray-200 text-gray-400';
                            }
                            ?>">
                            <?php if ($is_completed && !$is_current): ?>
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            <?php else: ?>
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $step_data['icon'] ?>"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Status Text -->
                        <div class="text-center">
                            <h4 class="text-sm font-semibold <?= $is_completed ? 'text-gray-900' : 'text-gray-400' ?>">
                                <?= $step_data['name'] ?>
                            </h4>
                            <p class="text-xs <?= $is_completed ? 'text-gray-600' : 'text-gray-400' ?> mt-1">
                                <?= $step_data['description'] ?>
                            </p>
                            <?php if ($is_current): ?>
                                <span class="inline-block mt-2 px-2 py-1 text-xs font-medium bg-primary text-white rounded-full">
                                    Current
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Connecting Line -->
                    <?php if (!$is_last): ?>
                        <div class="flex-1 h-0.5 mx-4 <?= $is_completed && $step_count < $current_order_index ? 'bg-success' : 'bg-gray-200' ?>"></div>
                    <?php endif; ?>
                    
                <?php 
                    $step_count++;
                endforeach; 
                ?>
            </div>

            <!-- Mobile Vertical Steps -->
            <div class="sm:hidden">
                <?php 
                $order_steps = getOrderStatusSteps();
                $current_order_index = getOrderStepIndex($order['status']);
                $step_count = 0;
                $total_steps = count($order_steps);
                
                foreach ($order_steps as $step_key => $step_data): 
                    $is_completed = $step_count <= $current_order_index;
                    $is_current = $step_count === $current_order_index;
                    $is_last = $step_count === $total_steps - 1;
                ?>
                    <div class="mobile-step-item">
                        <!-- Status Circle -->
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                            <?php 
                            if ($is_completed) {
                                echo $is_current ? 'bg-primary text-white animate-pulse-slow' : 'bg-success text-white';
                            } else {
                                echo 'bg-gray-200 text-gray-400';
                            }
                            ?>">
                            <?php if ($is_completed && !$is_current): ?>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $step_data['icon'] ?>"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Status Text -->
                        <div class="mobile-step-content">
                            <h4 class="text-sm font-semibold <?= $is_completed ? 'text-gray-900' : 'text-gray-400' ?>">
                                <?= $step_data['name'] ?>
                            </h4>
                            <p class="text-xs <?= $is_completed ? 'text-gray-600' : 'text-gray-400' ?>">
                                <?= $step_data['description'] ?>
                            </p>
                            <?php if ($is_current): ?>
                                <span class="inline-block mt-1 px-2 py-1 text-xs font-medium bg-primary text-white rounded-full">
                                    Current
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Vertical Connecting Line -->
                    <?php if (!$is_last): ?>
                        <div class="mobile-step-line <?= $is_completed && $step_count < $current_order_index ? 'bg-success' : 'bg-gray-200' ?>"></div>
                    <?php endif; ?>
                    
                <?php 
                    $step_count++;
                endforeach; 
                ?>
            </div>
        </div>

        <!-- Items List -->
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 animate-slide-in">
            <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-4 sm:mb-6">
                <?php if ($show_item_tracking): ?>
                    Items Tracking
                <?php else: ?>
                    Order Items
                <?php endif; ?>
            </h3>
            
            <!-- Show pending message if order is pending -->
            <?php if (!$show_item_tracking): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 sm:p-4 mb-4 sm:mb-6">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h4 class="text-sm font-semibold text-yellow-800">Tracking Pending</h4>
                            <p class="text-xs text-yellow-700">Item tracking will be available once your order is confirmed.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="space-y-4 sm:space-y-6">
                <?php foreach ($order_items as $index => $item): ?>
                    <?php
                    $origin = strtolower($item['origin'] ?? 'local');
                    $current_status = $item['tracking_status'] ?? 'processing';
                    
                    // Check replacement eligibility
                    $is_eligible_for_replacement = isEligibleForReplacement($item, $order);
                    $has_replacement_request = hasReplacementRequest($item['id'], $conn);
                    
                    if ($origin === 'local') {
                        $steps = getLocalStatusSteps();
                        $origin_icon = '🏠';
                        $origin_label = 'Local';
                        $origin_color = 'bg-green-100 text-green-800';
                    } else {
                        $steps = getInternationalStatusSteps();
                        $origin_icon = '🌏';
                        $origin_label = 'International';
                        $origin_color = 'bg-blue-100 text-blue-800';
                    }
                    
                    $current_step_index = getCurrentStepIndex($current_status, $steps);
                    ?>
                    
                    <div class="border border-gray-100 rounded-xl p-3 sm:p-4 hover:shadow-md transition-shadow">
                        <!-- Item Header -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 <?= $show_item_tracking ? 'mb-4' : '' ?>">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs sm:text-sm font-bold text-gray-700"><?= $index + 1 ?></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-semibold text-gray-900 text-sm sm:text-base truncate"><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <p class="text-xs sm:text-sm text-gray-600 truncate"><?= htmlspecialchars($item['variant_color']) ?> - <?= htmlspecialchars($item['size']) ?></p>
                                    <p class="text-xs text-gray-500">Qty: <?= $item['quantity'] ?></p>
                                </div>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full <?= $origin_color ?> w-fit">
                                        <?= $origin_icon ?> <?= $origin_label ?>
                                    </span>
                                    
                                    <!-- Replacement Button/Status -->
                                    <?php if ($has_replacement_request): ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800 w-fit">
                                            🔄 Replacement Requested
                                        </span>
                                    <?php elseif ($is_eligible_for_replacement): ?>
                                        <a href="replacement_request.php?order_id=<?= $order_id ?>&item_id=<?= $item['id'] ?>" 
                                           class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors cursor-pointer w-fit">
                                            🔄 Request Replacement
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <span class="text-base sm:text-lg font-bold text-gray-900">₱<?= number_format($item['subtotal'], 2) ?></span>
                            </div>
                        </div>
                        
                        <!-- Show tracking steps only if order is not pending -->
                        <?php if ($show_item_tracking): ?>
                            <!-- Desktop/Tablet Horizontal Steps -->
                            <div class="hidden sm:flex items-center justify-between">
                                <?php 
                                $step_keys = array_keys($steps);
                                $step_count = 0;
                                $total_item_steps = count($steps);
                                
                                foreach ($steps as $step_key => $step_data): 
                                    $is_completed = $step_count <= $current_step_index;
                                    $is_current = $step_count === $current_step_index;
                                    $is_last = $step_count === $total_item_steps - 1;
                                ?>
                                    <div class="flex flex-col items-center flex-1 compact-step">
                                        <!-- Mini Status Circle -->
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mb-2
                                            <?php 
                                            if ($is_completed) {
                                                if (strtolower($step_key) === 'delivered') {
                                                    echo 'bg-success text-white';
                                                } else {
                                                    echo $is_current ? 'bg-primary text-white animate-pulse-slow' : 'bg-success text-white';
                                                }
                                            } else {
                                                echo 'bg-gray-200 text-gray-400';
                                            }
                                            ?>">
                                            <?php if ($is_completed): ?>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            <?php else: ?>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $step_data['icon'] ?>"/>
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Mini Status Text -->
                                        <div class="text-center">
                                            <p class="text-xs font-medium <?= $is_completed ? 'text-gray-900' : 'text-gray-400' ?>">
                                                <?= $step_data['name'] ?>
                                            </p>
                                            <?php if ($is_current): ?>
                                                <div class="w-2 h-2 bg-primary rounded-full mx-auto mt-1"></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Mini Connecting Line -->
                                    <?php if (!$is_last): ?>
                                        <div class="flex-1 h-0.5 mx-2 <?= $is_completed && $step_count < $current_step_index ? 'bg-success' : 'bg-gray-200' ?>"></div>
                                    <?php endif; ?>
                                    
                                <?php 
                                    $step_count++;
                                endforeach; 
                                ?>
                            </div>

                            <!-- Mobile Horizontal Scrollable Steps -->
                            <div class="sm:hidden mobile-item-steps">
                                <div class="flex items-center space-x-4 pb-2" style="min-width: max-content;">
                                    <?php 
                                    $step_keys = array_keys($steps);
                                    $step_count = 0;
                                    $total_item_steps = count($steps);
                                    
                                    foreach ($steps as $step_key => $step_data): 
                                        $is_completed = $step_count <= $current_step_index;
                                        $is_current = $step_count === $current_step_index;
                                        $is_last = $step_count === $total_item_steps - 1;
                                    ?>
                                        <div class="flex flex-col items-center flex-shrink-0 w-16">
                                            <!-- Mini Status Circle -->
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center mb-2
                                                <?php 
                                                if ($is_completed) {
                                                    if (strtolower($step_key) === 'delivered') {
                                                        echo 'bg-success text-white';
                                                    } else {
                                                        echo $is_current ? 'bg-primary text-white animate-pulse-slow' : 'bg-success text-white';
                                                    }
                                                } else {
                                                    echo 'bg-gray-200 text-gray-400';
                                                }
                                                ?>">
                                                <?php if ($is_completed): ?>
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $step_data['icon'] ?>"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Mini Status Text -->
                                            <div class="text-center">
                                                <p class="text-xs font-medium <?= $is_completed ? 'text-gray-900' : 'text-gray-400' ?> leading-tight">
                                                    <?= $step_data['name'] ?>
                                                </p>
                                                <?php if ($is_current): ?>
                                                    <div class="w-1.5 h-1.5 bg-primary rounded-full mx-auto mt-1"></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Mini Connecting Line -->
                                        <?php if (!$is_last): ?>
                                            <div class="h-0.5 w-8 flex-shrink-0 <?= $is_completed && $step_count < $current_step_index ? 'bg-success' : 'bg-gray-200' ?> mt-4"></div>
                                        <?php endif; ?>
                                        
                                    <?php 
                                        $step_count++;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <!-- Delivery Information -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 animate-slide-in">
                <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-3 sm:mb-4">Delivery Information</h3>
                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 text-sm sm:text-base"><?= htmlspecialchars($order['customer_name']) ?></p>
                            <p class="text-sm text-gray-600 break-words"><?= htmlspecialchars($order['address']) ?></p>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($order['mobile']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 animate-slide-in">
                <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-3 sm:mb-4">Order Summary</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="text-gray-900 font-medium">₱<?= number_format($order['subtotal'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Delivery Fee:</span>
                        <span class="text-gray-900 font-medium">₱<?= number_format($order['delivery_fee'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">VAT:</span>
                        <span class="text-gray-900 font-medium">₱<?= number_format($order['vat_amount'] ?? 0, 2) ?></span>
                    </div>
                    <div class="border-t pt-2 flex justify-between font-bold">
                        <span class="text-gray-900">Total:</span>
                        <span class="text-gray-900">₱<?= number_format($order['final_total'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile-friendly spacing at bottom -->
        <div class="pb-6 sm:pb-0"></div>
    </div>

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
            crossorigin=""></script>
    
    <!-- Leaflet Routing Machine JavaScript -->
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <script>
        // Map initialization
        <?php if ($show_map): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            const map = L.map('deliveryMap').setView([<?= $order['delivery_lat'] ?>, <?= $order['delivery_lng'] ?>], 13);

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Define coordinates
            const warehouseCoords = [<?= $delivery_settings['latitude'] ?>, <?= $delivery_settings['longitude'] ?>];
            const deliveryCoords = [<?= $order['delivery_lat'] ?>, <?= $order['delivery_lng'] ?>];

            // Create custom icons
            const warehouseIcon = L.divIcon({
                html: `
                    <div style="
                        background-color: #10B981; 
                        width: 30px; 
                        height: 30px; 
                        border-radius: 50%; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center;
                        border: 3px solid white;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
                    ">
                        <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                `,
                className: 'custom-warehouse-icon',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });

            const deliveryIcon = L.divIcon({
                html: `
                    <div style="
                        background-color: #3B82F6; 
                        width: 30px; 
                        height: 30px; 
                        border-radius: 50%; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center;
                        border: 3px solid white;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
                    ">
                        <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                `,
                className: 'custom-delivery-icon',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });

            // Add markers
            L.marker(warehouseCoords, { icon: warehouseIcon })
                .addTo(map)
                .bindPopup(`
                    <div style="min-width: 200px;">
                        <h3 style="margin: 0 0 8px 0; color: #10B981; font-weight: bold;">📦 Warehouse</h3>
                        <p style="margin: 0; font-size: 12px; color: #666;">
                            <?= htmlspecialchars($delivery_settings['location_name']) ?>
                        </p>
                    </div>
                `);

            L.marker(deliveryCoords, { icon: deliveryIcon })
                .addTo(map)
                .bindPopup(`
                    <div style="min-width: 200px;">
                        <h3 style="margin: 0 0 8px 0; color: #3B82F6; font-weight: bold;">🏠 Delivery Address</h3>
                        <p style="margin: 0 0 4px 0; font-weight: bold;"><?= htmlspecialchars($order['customer_name']) ?></p>
                        <p style="margin: 0; font-size: 12px; color: #666;">
                            <?= htmlspecialchars($order['delivery_address']) ?>, <?= htmlspecialchars($order['delivery_city']) ?>
                        </p>
                    </div>
                `);

            // Add routing
            const routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(warehouseCoords[0], warehouseCoords[1]),
                    L.latLng(deliveryCoords[0], deliveryCoords[1])
                ],
                routeWhileDragging: false,
                addWaypoints: false,
                createMarker: function() { return null; }, // Don't create default markers
                lineOptions: {
                    styles: [
                        { color: '#FF6B35', weight: 6, opacity: 0.7 },
                        { color: '#ffffff', weight: 2, opacity: 1 }
                    ]
                },
                router: L.Routing.osrmv1({
                    serviceUrl: 'https://router.project-osrm.org/route/v1'
                }),
                formatter: new L.Routing.Formatter({
                    language: 'en',
                    units: 'metric'
                }),
                show: false, // Hide the instruction panel initially
                collapsible: true
            }).addTo(map);

            // Fit bounds to show both markers and route
            setTimeout(() => {
                const group = new L.featureGroup([
                    L.marker(warehouseCoords),
                    L.marker(deliveryCoords)
                ]);
                map.fitBounds(group.getBounds().pad(0.1));
            }, 1000);

            // Handle route found event
            routingControl.on('routesfound', function(e) {
                const routes = e.routes;
                const summary = routes[0].summary;
                
                // Update distance display if needed
                console.log('Route distance:', (summary.totalDistance / 1000).toFixed(2) + ' km');
                console.log('Route time:', Math.round(summary.totalTime / 60) + ' minutes');
            });
        });
        <?php endif; ?>

        // Add smooth scroll animation for page load
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate-slide-in');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease-out';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Auto-scroll mobile item tracking to current step
            const mobileItemSteps = document.querySelectorAll('.mobile-item-steps');
            mobileItemSteps.forEach(container => {
                const currentStep = container.querySelector('.animate-pulse-slow');
                if (currentStep) {
                    // Small delay to ensure proper rendering
                    setTimeout(() => {
                        const containerRect = container.getBoundingClientRect();
                        const stepRect = currentStep.getBoundingClientRect();
                        const scrollLeft = stepRect.left - containerRect.left - containerRect.width / 2 + stepRect.width / 2;
                        container.scrollTo({ left: container.scrollLeft + scrollLeft, behavior: 'smooth' });
                    }, 200);
                }
            });
        });

        // Handle orientation change for mobile
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                // Re-center current steps after orientation change
                const mobileItemSteps = document.querySelectorAll('.mobile-item-steps');
                mobileItemSteps.forEach(container => {
                    const currentStep = container.querySelector('.animate-pulse-slow');
                    if (currentStep) {
                        const containerRect = container.getBoundingClientRect();
                        const stepRect = currentStep.getBoundingClientRect();
                        const scrollLeft = stepRect.left - containerRect.left - containerRect.width / 2 + stepRect.width / 2;
                        container.scrollTo({ left: container.scrollLeft + scrollLeft, behavior: 'smooth' });
                    }
                });

                // Resize map if it exists
                <?php if ($show_map): ?>
                if (typeof map !== 'undefined') {
                    setTimeout(() => {
                        map.invalidateSize();
                    }, 500);
                }
                <?php endif; ?>
            }, 300);
        });
    </script>
</body>
</html>