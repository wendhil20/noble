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

// Get order items with tracking status and replacement info
$stmt = $conn->prepare("
    SELECT oi.*,
           rr.id as replacement_request_id,
           rr.status as replacement_status,
           rr.reason as replacement_reason,
           rr.created_at as replacement_requested_at,
           rr.delivery_schedule_id as replacement_delivery_id,
           rr.replacement_quantity,
           ds.delivery_status as replacement_delivery_status,
           ds.delivery_date as replacement_delivery_date,
           ds.delivery_time as replacement_delivery_time
    FROM order_items oi
    LEFT JOIN replacement_requests rr ON oi.id = rr.order_item_id
    LEFT JOIN delivery_schedules ds ON rr.delivery_schedule_id = ds.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];
while ($row = $items_result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();

// Function to get order level status steps
function getOrderStatusSteps($delivery_type = 'delivery') {
    $steps = [
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
        'ready_for_pickup' => [
            'name' => 'Ready',
            'description' => $delivery_type === 'pickup' ? 'Ready for pickup' : 'Ready for delivery',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        ]
    ];
    
    // Add delivery/pickup in progress step
    if ($delivery_type === 'pickup') {
        $steps['out_for_pickup'] = [
            'name' => 'Out for Pickup',
            'description' => 'Order ready to be picked up',
            'icon' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2'
        ];
    } else {
        $steps['out_for_delivery'] = [
            'name' => 'Out for Delivery',
            'description' => 'Order is being delivered',
            'icon' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2'
        ];
    }
    
    // Add final completion step
    if ($delivery_type === 'pickup') {
        $steps['picked_up'] = [
            'name' => 'Order Picked Up',
            'description' => 'All items have been picked up',
            'icon' => 'M5 13l4 4L19 7'
        ];
    } else {
        $steps['delivered'] = [
            'name' => 'Order Delivered',
            'description' => 'All items have been delivered',
            'icon' => 'M5 13l4 4L19 7'
        ];
    }
    
    return $steps;
}

// Function to get status steps for local products
function getLocalStatusSteps($delivery_type = 'delivery') {
    $steps = [
        'pending' => [
            'name' => 'Pending',
            'description' => 'Order placed',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'processing' => [
            'name' => 'Processing',
            'description' => 'Item being prepared',
            'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4'
        ],
        'in_warehouse' => [
            'name' => 'In Warehouse',
            'description' => 'Item in warehouse',
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'
        ],
        'scheduled' => [
            'name' => 'Scheduled',
            'description' => $delivery_type === 'pickup' ? 'Pickup scheduled' : 'Delivery scheduled',
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
        ],
        'ready_for_pickup' => [
            'name' => 'Ready',
            'description' => $delivery_type === 'pickup' ? 'Ready for pickup' : 'Ready for delivery',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'item_is_loaded' => [
            'name' => $delivery_type === 'pickup' ? 'Out for Pickup' : 'Out for Delivery',
            'description' => $delivery_type === 'pickup' ? 'Ready to be picked up' : 'Being delivered',
            'icon' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2'
        ]
    ];
    
    // Add final step based on delivery type
    if ($delivery_type === 'pickup') {
        $steps['picked_up'] = [
            'name' => 'Picked Up',
            'description' => 'Item picked up',
            'icon' => 'M5 13l4 4L19 7'
        ];
    } else {
        $steps['delivered'] = [
            'name' => 'Delivered',
            'description' => 'Item delivered',
            'icon' => 'M5 13l4 4L19 7'
        ];
    }
    
    return $steps;
}

// Function to get status steps for international products
function getInternationalStatusSteps($delivery_type = 'delivery') {
    $steps = [
        'pending' => [
            'name' => 'Pending',
            'description' => 'Order placed',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
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
        'in_warehouse' => [
            'name' => 'In Warehouse',
            'description' => 'In local warehouse',
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'
        ],
        'scheduled' => [
            'name' => 'Scheduled',
            'description' => $delivery_type === 'pickup' ? 'Pickup scheduled' : 'Delivery scheduled',
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
        ],
        'ready_for_pickup' => [
            'name' => 'Ready',
            'description' => $delivery_type === 'pickup' ? 'Ready for pickup' : 'Ready for delivery',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'item_is_loaded' => [
            'name' => $delivery_type === 'pickup' ? 'Out for Pickup' : 'Out for Delivery',
            'description' => $delivery_type === 'pickup' ? 'Ready to be picked up' : 'Being delivered',
            'icon' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2'
        ]
    ];
    
    // Add final step based on delivery type
    if ($delivery_type === 'pickup') {
        $steps['picked_up'] = [
            'name' => 'Picked Up',
            'description' => 'Item picked up',
            'icon' => 'M5 13l4 4L19 7'
        ];
    } else {
        $steps['delivered'] = [
            'name' => 'Delivered',
            'description' => 'Item delivered',
            'icon' => 'M5 13l4 4L19 7'
        ];
    }
    
    return $steps;
}

// Function to get replacement status steps
function getReplacementStatusSteps($delivery_type = 'delivery') {
    $steps = [
        'pending' => [
            'name' => 'Request Pending',
            'description' => 'Replacement request submitted',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'approved' => [
            'name' => 'Request Approved',
            'description' => 'Replacement approved',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'processing' => [
            'name' => 'Processing',
            'description' => 'Preparing replacement',
            'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'
        ],
        'ready_for_pickup' => [
            'name' => 'Ready',
            'description' => $delivery_type === 'pickup' ? 'Ready for pickup' : 'Ready for delivery',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'item_is_loaded' => [
            'name' => $delivery_type === 'pickup' ? 'Out for Pickup' : 'Out for Delivery',
            'description' => $delivery_type === 'pickup' ? 'Ready to be picked up' : 'Replacement being delivered',
            'icon' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2'
        ]
    ];
    
    // Add final step based on delivery type
    if ($delivery_type === 'pickup') {
        $steps['picked_up'] = [
            'name' => 'Picked Up',
            'description' => 'Replacement picked up',
            'icon' => 'M5 13l4 4L19 7'
        ];
    } else {
        $steps['delivered'] = [
            'name' => 'Delivered',
            'description' => 'Replacement delivered',
            'icon' => 'M5 13l4 4L19 7'
        ];
    }
    
    return $steps;
}

// Function to get current step index
function getCurrentStepIndex($status, $steps) {
    $status = strtolower(str_replace(' ', '_', $status));
    $step_keys = array_keys($steps);
    $index = array_search($status, $step_keys);
    return $index !== false ? $index : 0;
}

// Function to get order step index
function getOrderStepIndex($status, $delivery_type = 'delivery') {
    // Normalize status: convert to lowercase and replace spaces with underscores
    $status = strtolower(str_replace(' ', '_', $status));
    
    if ($delivery_type === 'pickup') {
        $steps = ['pending', 'ongoing', 'processing', 'ready_for_pickup', 'out_for_pickup', 'picked_up'];
    } else {
        $steps = ['pending', 'ongoing', 'processing', 'ready_for_pickup', 'out_for_delivery', 'delivered'];
    }
    
    $index = array_search($status, $steps);
    return $index !== false ? $index : 0;
}
// Function to check if item is eligible for replacement
function isEligibleForReplacement($item, $order) {
    $item_status = strtolower($item['tracking_status'] ?? 'processing');
    $delivery_type = strtolower($order['delivery_type'] ?? 'delivery');
    
    // Check if item is delivered or picked up based on delivery type
    $final_status = ($delivery_type === 'pickup') ? 'picked_up' : 'delivered';
    
    if ($item_status === $final_status) {
        // Optional: Add time window check if needed (7 days)
        $completion_date = $item['delivered_at'] ?? $item['picked_up_at'] ?? null;
        if ($completion_date) {
            $days_since_completion = (time() - strtotime($completion_date)) / (60 * 60 * 24);
            return $days_since_completion <= 7; // 7 days replacement window
        }
        // If no date, still allow replacement for completed items
        return true;
    }
    
    return false;
}

// Function to check if replacement was already requested
function hasReplacementRequest($item_id, $conn) {
    $stmt = $conn->prepare("SELECT id, status FROM replacement_requests WHERE order_item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $replacement = $result->fetch_assoc();
    $stmt->close();
    return $replacement;
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
                <a href="index-profile-page-6" class="flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
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
                        
                        <!-- Order Status -->
<span class="inline-flex items-center gap-1 px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-full
    <?php
    // Normalize status for comparison
    $order_status = strtolower(str_replace(' ', '_', $order['status']));
    if (in_array($order_status, ['delivered', 'picked_up'])) {
        echo 'bg-green-100 text-green-800';
    } elseif (in_array($order_status, ['out_for_delivery', 'out_for_pickup', 'ready_for_pickup'])) {
        echo 'bg-blue-100 text-blue-800';
    } elseif ($order_status === 'processing') {
        echo 'bg-yellow-100 text-yellow-800';
    } else {
        echo 'bg-gray-100 text-gray-800';
    }
    ?>">
    <?php
    // Display the status nicely formatted
    echo htmlspecialchars($order['status']);
    ?>
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
                $delivery_type = strtolower($order['delivery_type'] ?? 'delivery');
                $order_steps = getOrderStatusSteps($delivery_type);
                $current_order_index = getOrderStepIndex($order['status'], $delivery_type);
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
                $delivery_type = strtolower($order['delivery_type'] ?? 'delivery');
                $order_steps = getOrderStatusSteps($delivery_type);
                $current_order_index = getOrderStepIndex($order['status'], $delivery_type);
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
                    $delivery_type = strtolower($order['delivery_type'] ?? 'delivery');
                    
                    // Check replacement eligibility and status
                    $is_eligible_for_replacement = isEligibleForReplacement($item, $order);
                    $replacement_info = hasReplacementRequest($item['id'], $conn);
                    $has_replacement_request = !empty($replacement_info);
                    
                    // Determine if we should show replacement tracking
// Show tracking if there's a request and it's not rejected or just pending without approval
$show_replacement_tracking = $has_replacement_request && 
                           !in_array(strtolower($item['replacement_status'] ?? ''), ['rejected', '']);
                    
                    if ($origin === 'local') {
                        $steps = getLocalStatusSteps($delivery_type);
                        $origin_icon = '🏠';
                        $origin_label = 'Local';
                        $origin_color = 'bg-green-100 text-green-800';
                    } else {
                        $steps = getInternationalStatusSteps($delivery_type);
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
                                    <?php if ($show_replacement_tracking): ?>
    <?php
    $replacement_status = strtolower($item['replacement_status'] ?? '');
    $replacement_delivery_status = strtolower($item['replacement_delivery_status'] ?? '');
    
    // Determine current replacement status for display
    // Priority order: delivery_status > replacement_requests.status
    
    // Check delivery_schedules status first (most current)
    if ($replacement_delivery_status === 'delivered' || $replacement_delivery_status === 'picked_up') {
        $status_color = 'bg-green-100 text-green-800';
        $status_text = $replacement_delivery_status === 'picked_up' ? '✅ Replacement Picked Up' : '✅ Replacement Delivered';
    } elseif ($replacement_delivery_status === 'item_is_loaded' || $replacement_delivery_status === 'out_for_delivery') {
        $status_color = 'bg-blue-100 text-blue-800';
        $status_text = $delivery_type === 'pickup' ? '📦 Out for Pickup' : '🚚 Out for Delivery';
    } elseif ($replacement_delivery_status === 'ready_for_pickup') {
        $status_color = 'bg-purple-100 text-purple-800';
        $status_text = '📦 Ready for ' . ($delivery_type === 'pickup' ? 'Pickup' : 'Delivery');
    } 
    // Then check replacement_requests status
    elseif ($replacement_status === 'scheduled') {
        $status_color = 'bg-indigo-100 text-indigo-800';
        $status_text = '📅 Delivery Scheduled';
    } elseif ($replacement_status === 'in_warehouse' || $replacement_status === 'in warehouse') {
        $status_color = 'bg-purple-100 text-purple-800';
        $status_text = '🏭 In Warehouse';
    } elseif ($replacement_status === 'processing') {
        $status_color = 'bg-yellow-100 text-yellow-800';
        $status_text = '⚙️ Processing';
    } elseif ($replacement_status === 'approved') {
        $status_color = 'bg-green-100 text-green-800';
        $status_text = '✅ Approved';
    } elseif ($replacement_status === 'rejected') {
        $status_color = 'bg-red-100 text-red-800';
        $status_text = '❌ Rejected';
    } elseif ($replacement_status === 'pending') {
        $status_color = 'bg-orange-100 text-orange-800';
        $status_text = '🔄 Pending Review';
    } else {
        $status_color = 'bg-gray-100 text-gray-800';
        $status_text = '📋 ' . ucfirst(str_replace('_', ' ', $replacement_status));
    }
    ?>
    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full <?= $status_color ?> w-fit">
        <?= $status_text ?>
    </span>
                                    <?php elseif ($has_replacement_request): ?>
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

                        <!-- Replacement Tracking Section -->
                        <?php if ($show_replacement_tracking): ?>
                            <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="text-sm font-semibold text-orange-800">Replacement Progress</h5>
                                    <?php if ($item['replacement_reason']): ?>
                                    <span class="text-xs text-orange-600">
                                        Reason: <?= ucfirst(str_replace('_', ' ', $item['replacement_reason'])) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                               <?php
// Determine replacement tracking status - normalize to lowercase
$replacement_status_lower = strtolower($item['replacement_status'] ?? '');
$replacement_delivery_status_lower = strtolower($item['replacement_delivery_status'] ?? '');

// CRITICAL: Check replacement_requests.status FIRST for final states
// Because delivery_schedules might not be updated yet

// Priority 1: Check replacement_requests.status for final delivery states
if ($replacement_status_lower === 'delivered') {
    $replacement_tracking_status = 'delivered';
} elseif ($replacement_status_lower === 'picked_up') {
    $replacement_tracking_status = 'picked_up';
}
// Priority 2: Check delivery_schedules.delivery_status for final states (backup)
elseif ($replacement_delivery_status_lower === 'delivered') {
    $replacement_tracking_status = 'delivered';
} elseif ($replacement_delivery_status_lower === 'picked_up') {
    $replacement_tracking_status = 'picked_up';
}
// Priority 3: Check for out for delivery/pickup (either table)
elseif ($replacement_delivery_status_lower === 'item_is_loaded' || 
        $replacement_delivery_status_lower === 'out_for_delivery' ||
        $replacement_status_lower === 'out_for_delivery') {
    $replacement_tracking_status = 'item_is_loaded';
}
// Priority 4: Ready for pickup (either table)
elseif ($replacement_delivery_status_lower === 'ready_for_pickup' ||
        $replacement_status_lower === 'ready_for_pickup') {
    $replacement_tracking_status = 'ready_for_pickup';
}
// Priority 5: Scheduled state (has delivery_schedule_id)
elseif ($item['replacement_delivery_id'] && $replacement_status_lower === 'scheduled') {
    $replacement_tracking_status = 'ready_for_pickup'; // Scheduled means ready
}
// Priority 6: Warehouse state
elseif ($replacement_status_lower === 'in_warehouse' || $replacement_status_lower === 'in warehouse') {
    $replacement_tracking_status = 'processing'; // Map to processing step
}
// Priority 7: Processing state
elseif ($replacement_status_lower === 'processing') {
    $replacement_tracking_status = 'processing';
}
// Priority 8: Approved state
elseif ($replacement_status_lower === 'approved') {
    $replacement_tracking_status = 'approved';
}
// Priority 9: Pending state (default)
else {
    $replacement_tracking_status = 'pending';
}

$replacement_steps = getReplacementStatusSteps($delivery_type);
$replacement_current_index = getCurrentStepIndex($replacement_tracking_status, $replacement_steps);
?>

                                
                                <!-- Desktop Replacement Steps -->
                                <div class="hidden sm:flex items-center justify-between">
                                    <?php 
                                    $step_count = 0;
                                    $total_replacement_steps = count($replacement_steps);
                                    
                                    foreach ($replacement_steps as $step_key => $step_data): 
                                        $is_completed = $step_count <= $replacement_current_index;
                                        $is_current = $step_count === $replacement_current_index;
                                        $is_last = $step_count === $total_replacement_steps - 1;
                                    ?>
                                        <div class="flex flex-col items-center flex-1">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center mb-1
                                                <?php 
                                                if ($is_completed) {
                                                    echo $is_current ? 'bg-orange-500 text-white animate-pulse-slow' : 'bg-green-500 text-white';
                                                } else {
                                                    echo 'bg-gray-300 text-gray-500';
                                                }
                                                ?>">
                                                <?php if ($is_completed && !$is_current): ?>
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $step_data['icon'] ?>"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs font-medium <?= $is_completed ? 'text-orange-800' : 'text-gray-500' ?> text-center leading-tight">
                                                <?= $step_data['name'] ?>
                                            </p>
                                        </div>
                                        
                                        <?php if (!$is_last): ?>
                                            <div class="flex-1 h-0.5 mx-2 <?= $is_completed && $step_count < $replacement_current_index ? 'bg-green-500' : 'bg-gray-300' ?>"></div>
                                        <?php endif; ?>
                                        
                                    <?php 
                                        $step_count++;
                                    endforeach; 
                                    ?>
                                </div>
                                
                                <!-- Mobile Replacement Steps -->
                                <div class="sm:hidden mobile-item-steps">
                                    <div class="flex items-center space-x-3 pb-2" style="min-width: max-content;">
                                        <?php 
                                        $step_count = 0;
                                        $total_replacement_steps = count($replacement_steps);
                                        
                                        foreach ($replacement_steps as $step_key => $step_data): 
                                            $is_completed = $step_count <= $replacement_current_index;
                                            $is_current = $step_count === $replacement_current_index;
                                            $is_last = $step_count === $total_replacement_steps - 1;
                                        ?>
                                            <div class="flex flex-col items-center flex-shrink-0 w-14">
                                                <div class="w-6 h-6 rounded-full flex items-center justify-center mb-1
                                                    <?php 
                                                    if ($is_completed) {
                                                        echo $is_current ? 'bg-orange-500 text-white animate-pulse-slow' : 'bg-green-500 text-white';
                                                    } else {
                                                        echo 'bg-gray-300 text-gray-500';
                                                    }
                                                    ?>">
                                                    <?php if ($is_completed && !$is_current): ?>
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $step_data['icon'] ?>"/>
                                                        </svg>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-xs font-medium <?= $is_completed ? 'text-orange-800' : 'text-gray-500' ?> text-center leading-tight">
                                                    <?= $step_data['name'] ?>
                                                </p>
                                            </div>
                                            
                                            <?php if (!$is_last): ?>
                                                <div class="h-0.5 w-6 flex-shrink-0 <?= $is_completed && $step_count < $replacement_current_index ? 'bg-green-500' : 'bg-gray-300' ?> mt-3"></div>
                                            <?php endif; ?>
                                            
                                        <?php 
                                            $step_count++;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                                
                                <!-- Replacement Details -->
                                <?php if ($item['replacement_requested_at']): ?>
                                <div class="mt-2 text-xs text-orange-700">
                                    <strong>Requested:</strong> <?= date('M j, Y g:i A', strtotime($item['replacement_requested_at'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
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