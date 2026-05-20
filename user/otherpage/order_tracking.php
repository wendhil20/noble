<?php

require_once(ROOT_PATH . '/connection/connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ' . BASE_URL . '/googlecallback');
  exit;
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
  header('Location: ' . BASE_URL . '/profile');
  exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? null;

// Get order details with billing address, lead time, and delivery schedule
$stmt = $conn->prepare("
    SELECT o.*, ba.latitude as delivery_lat, ba.longitude as delivery_lng,
        ba.full_name as delivery_name, ba.address as delivery_address,
        ba.city as delivery_city, ba.state as delivery_state,
        (SELECT MIN(lt_from) FROM order_items WHERE order_id = o.id AND lt_from IS NOT NULL) as earliest_delivery,
        (SELECT MAX(lt_to) FROM order_items WHERE order_id = o.id AND lt_to IS NOT NULL) as latest_delivery,
        (SELECT MIN(ds.delivery_date) FROM delivery_schedules ds WHERE ds.order_id = o.id) as scheduled_delivery_date,
        (SELECT MIN(ds.delivery_time) FROM delivery_schedules ds WHERE ds.order_id = o.id AND ds.delivery_date = (SELECT MIN(delivery_date) FROM delivery_schedules WHERE order_id = o.id)) as scheduled_delivery_time
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

// Get delivery booking information
$booking_info = null;
$stmt = $conn->prepare("
    SELECT db.*, ds.delivery_date, ds.delivery_time, ds.delivery_type
    FROM delivery_bookings db
    INNER JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
    WHERE db.order_id = ?
    ORDER BY db.created_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$booking_result = $stmt->get_result();
if ($booking_result->num_rows > 0) {
  $booking_info = $booking_result->fetch_assoc();
}
$stmt->close();


// Function to get order level status steps
function getOrderStatusSteps($delivery_type = 'delivery')
{
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
function getLocalStatusSteps($delivery_type = 'delivery')
{
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
function getInternationalStatusSteps($delivery_type = 'delivery')
{
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
function getReplacementStatusSteps($delivery_type = 'delivery')
{
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
function getCurrentStepIndex($status, $steps)
{
  $status = strtolower(str_replace(' ', '_', $status));
  $step_keys = array_keys($steps);
  $index = array_search($status, $step_keys);
  return $index !== false ? $index : 0;
}

// Function to get order step index
function getOrderStepIndex($status, $delivery_type = 'delivery')
{
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
function isEligibleForReplacement($item, $order)
{
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
function hasReplacementRequest($item_id, $conn)
{
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

  <?php include ROOT_PATH . '/user/navbar/top.php'; ?>

  <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet" />
  <script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
  <style>
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
    }

    /* Hide leaflet routing machine instructions by default */
    .leaflet-routing-container {
      background: white;
      padding: 8px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

<body class=" min-h-screen">


  <div class="container mx-auto px-3 sm:px-4 py-4 sm:py-8 max-w-6xl">
    <!-- Header -->
    <div class="mb-6 sm:mb-8">
      <div class="flex items-center gap-3 sm:gap-4 mb-4">
        <a href="<?= BASE_URL ?>/order"
          class="flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
          <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </a>
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Order Tracking</h1>
          <p class="text-sm sm:text-base text-gray-600">Track your order progress</p>
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
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            </svg>
            <span><?= number_format($order['delivery_distance'], 2) ?> km</span>
          </div>
        </div>

        <!-- Map Container -->
        <div id="deliveryMap" class="w-full"></div>

        <!-- Route Info -->
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">

          <!-- From: Warehouse -->
          <div class="flex items-start gap-3 p-3 bg-green-50 border border-green-200 rounded-xl">
            <div class="w-9 h-9 bg-green-500 rounded-lg flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs text-green-600 font-medium mb-0.5">From</p>
              <p class="text-sm font-bold text-green-800">Warehouse</p>
              <p class="text-xs text-green-600 break-words leading-relaxed">
                <?= htmlspecialchars($delivery_settings['location_name']) ?>
              </p>
            </div>
          </div>

          <!-- To: Your Address -->
          <div class="flex items-start gap-3 p-3 bg-blue-50 border border-blue-200 rounded-xl">
            <div class="w-9 h-9 bg-blue-500 rounded-lg flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs text-blue-600 font-medium mb-0.5">To</p>
              <p class="text-sm font-bold text-blue-800">Your Address</p>
              <p class="text-xs text-blue-600 break-words leading-relaxed">
                <?= htmlspecialchars($order['delivery_address']) ?>, <?= htmlspecialchars($order['delivery_city']) ?>
              </p>
            </div>
          </div>

        </div>
      </div>
    <?php endif; ?>

    <!-- Order Summary Card -->
    <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 animate-slide-in">

      <!-- Top: Order Info -->
      <div class="flex items-center gap-3 sm:gap-4 mb-4">
        <div class="w-12 h-12 sm:w-14 sm:h-14 bg-primary/10 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6 sm:w-7 sm:h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Order #<?= $order['id'] ?></h2>
          <p class="text-xs sm:text-sm text-gray-500"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
          <p class="text-xs text-gray-400"><?= count($order_items) ?> item(s)</p>
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-gray-100 mb-4"></div>

      <!-- Bottom: Total + Badges -->
      <div class="flex items-center justify-between gap-3">

        <!-- Total Amount -->
        <div>
          <p class="text-xs text-gray-500 mb-0.5">Total Amount</p>
          <p class="text-2xl sm:text-3xl font-bold text-gray-900">₱<?= number_format($order['final_total'], 2) ?></p>
        </div>

        <!-- Status Badges -->
        <div class="flex flex-col items-end gap-2">

          <!-- Payment Status -->
          <?php
          $payment_status = $order['payment_status'] ?? 'pending';
          $pay_lower = strtolower($payment_status);
          if ($pay_lower === 'verified') {
            $pay_color = 'bg-green-100 text-green-800';
            $pay_icon = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
          } elseif ($pay_lower === 'rejected') {
            $pay_color = 'bg-red-100 text-red-800';
            $pay_icon = 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z';
          } else {
            $pay_color = 'bg-yellow-100 text-yellow-800';
            $pay_icon = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
          }
          ?>
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full <?= $pay_color ?>">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $pay_icon ?>" />
            </svg>
            Payment: <?= ucfirst($payment_status) ?>
          </span>

          <!-- Order Status -->
          <?php
          $order_status = strtolower(str_replace(' ', '_', $order['status']));
          if (in_array($order_status, ['delivered', 'picked_up', 'completed'])) {
            $status_color = 'bg-green-100 text-green-800';
            $status_icon = 'M5 13l4 4L19 7';
          } elseif (in_array($order_status, ['out_for_delivery', 'out_for_pickup'])) {
            $status_color = 'bg-blue-100 text-blue-800';
            $status_icon = 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2';
          } elseif ($order_status === 'ready_for_pickup') {
            $status_color = 'bg-blue-100 text-blue-800';
            $status_icon = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
          } elseif ($order_status === 'processing') {
            $status_color = 'bg-yellow-100 text-yellow-800';
            $status_icon = 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15';
          } else {
            $status_color = 'bg-gray-100 text-gray-700';
            $status_icon = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
          }
          ?>
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full <?= $status_color ?>">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $status_icon ?>" />
            </svg>
            <?= htmlspecialchars($order['status']) ?>
          </span>

        </div>
      </div>
    </div>

    <!-- Expected Delivery Date - Dynamic based on available information -->
    <?php

    $has_actual_delivery = $booking_info && $booking_info['actual_delivery_time'];
    $has_estimated_delivery = $booking_info && $booking_info['estimated_delivery_time'];
    $has_scheduled_delivery = $order['scheduled_delivery_date'];
    $has_lead_time = $order['earliest_delivery'] && $order['latest_delivery'];

    $show_delivery_info = $has_actual_delivery || $has_estimated_delivery || $has_scheduled_delivery || $has_lead_time;
    ?>

    <?php if ($show_delivery_info): ?>
      <div class="bg-white p-4 sm:p-6 mb-6 sm:mb-8 shadow-lg rounded-xl">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-5">
          <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-500 rounded-xl flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg sm:text-xl font-bold text-gray-900">
              <?php
              if ($has_actual_delivery)
                echo "Delivered On";
              elseif ($has_estimated_delivery || $has_scheduled_delivery)
                echo "Scheduled Delivery";
              else
                echo "Expected Delivery Range";
              ?>
            </h3>
            <p class="text-xs sm:text-sm text-gray-500">
              <?php
              if ($has_actual_delivery)
                echo "Order completed";
              elseif ($has_estimated_delivery)
                echo "Estimated by courier";
              elseif ($has_scheduled_delivery)
                echo "Confirmed delivery schedule";
              else
                echo "Based on item lead times";
              ?>
            </p>
          </div>
        </div>

        <!-- Priority 1: Actual Delivery -->
        <?php if ($has_actual_delivery): ?>
          <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <div>
                <p class="text-xs text-green-600 font-medium mb-0.5">Delivered on</p>
                <p class="text-xl font-bold text-green-700">
                  <?= date('M d, Y', strtotime($booking_info['actual_delivery_time'])) ?></p>
                <p class="text-sm text-green-600">at <?= date('g:i A', strtotime($booking_info['actual_delivery_time'])) ?>
                </p>
              </div>
            </div>
          </div>

          <!-- Priority 2: Estimated Delivery -->
        <?php elseif ($has_estimated_delivery): ?>
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="flex-1">
                <p class="text-xs text-blue-600 font-medium mb-0.5">Estimated delivery</p>
                <p class="text-xl font-bold text-blue-700">
                  <?= date('M d, Y', strtotime($booking_info['estimated_delivery_time'])) ?></p>
                <p class="text-sm text-blue-600">at
                  <?= date('g:i A', strtotime($booking_info['estimated_delivery_time'])) ?></p>
              </div>
              <?php if ($booking_info['courier_name']): ?>
                <div class="shrink-0 text-right">
                  <p class="text-xs text-gray-500 mb-1">Courier</p>
                  <span
                    class="inline-flex items-center gap-1 px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-semibold">
                    <?= htmlspecialchars($booking_info['courier_name']) ?>
                  </span>
                </div>
              <?php endif; ?>
            </div>
            <?php
            $estimated_date = new DateTime($booking_info['estimated_delivery_time']);
            $today = new DateTime();
            $days_until = $today->diff($estimated_date)->days;
            $is_future = $estimated_date > $today;
            if ($is_future && $days_until > 0):
              ?>
              <div class="mt-3 pt-3 border-t border-blue-200">
                <p class="text-sm text-blue-700 font-semibold"><?= $days_until ?> day<?= $days_until > 1 ? 's' : '' ?> until
                  delivery</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Priority 3: Scheduled Delivery -->
        <?php elseif ($has_scheduled_delivery): ?>
          <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-indigo-500 rounded-full flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <p class="text-xs text-indigo-600 font-medium mb-0.5">Scheduled delivery</p>
                <p class="text-xl font-bold text-indigo-700">
                  <?= date('M d, Y', strtotime($order['scheduled_delivery_date'])) ?></p>
                <?php if ($order['scheduled_delivery_time']): ?>
                  <p class="text-sm text-indigo-600">at <?= date('g:i A', strtotime($order['scheduled_delivery_time'])) ?></p>
                <?php endif; ?>
              </div>
            </div>
            <?php
            $scheduled_date = new DateTime($order['scheduled_delivery_date']);
            $today = new DateTime();
            $days_until = $today->diff($scheduled_date)->days;
            $is_future = $scheduled_date > $today;
            if ($is_future && $days_until > 0):
              ?>
              <div class="mt-3 pt-3 border-t border-indigo-200">
                <p class="text-sm text-indigo-700 font-semibold"><?= $days_until ?> day<?= $days_until > 1 ? 's' : '' ?> until
                  scheduled delivery</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Priority 4: Lead Time Range -->
        <?php else: ?>
          <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
            <div class="flex items-center justify-between gap-4">
              <div class="flex-1 text-center p-3 bg-white rounded-lg border border-gray-200">
                <p class="text-xs text-gray-500 mb-1">Earliest</p>
                <p class="text-base font-bold text-gray-800"><?= date('M d, Y', strtotime($order['earliest_delivery'])) ?>
                </p>
              </div>
              <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
              </svg>
              <div class="flex-1 text-center p-3 bg-white rounded-lg border border-blue-200">
                <p class="text-xs text-blue-500 mb-1">Latest</p>
                <p class="text-base font-bold text-blue-700"><?= date('M d, Y', strtotime($order['latest_delivery'])) ?></p>
              </div>
            </div>
            <?php
            $latest_date = new DateTime($order['latest_delivery']);
            $today = new DateTime();
            $days_until = $today->diff($latest_date)->days;
            $is_future = $latest_date > $today;
            if ($is_future && $days_until > 0):
              ?>
              <div class="mt-3 pt-3 border-t border-gray-200">
                <p class="text-sm text-gray-600 font-semibold"><?= $days_until ?> day<?= $days_until > 1 ? 's' : '' ?> until
                  expected delivery</p>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Booking Details -->
        <?php if ($booking_info): ?>
          <div class="border border-gray-200 rounded-xl overflow-hidden mb-4">
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center gap-2">
              <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              <h4 class="text-sm font-bold text-gray-700">Booking Details</h4>
            </div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">

              <?php if ($booking_info['tracking_number']): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                  <p class="text-xs text-gray-500 mb-1">Tracking Number</p>
                  <p class="font-mono font-bold text-gray-900 text-sm break-all">
                    <?= htmlspecialchars($booking_info['tracking_number']) ?></p>
                </div>
              <?php endif; ?>

              <?php if ($booking_info['courier_name']): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                  <p class="text-xs text-gray-500 mb-1">Courier Service</p>
                  <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($booking_info['courier_name']) ?></p>
                </div>
              <?php endif; ?>

              <?php if ($booking_info['booking_reference']): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                  <p class="text-xs text-gray-500 mb-1">Booking Reference</p>
                  <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($booking_info['booking_reference']) ?>
                  </p>
                </div>
              <?php endif; ?>

              <?php if ($booking_info['driver_name']): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                  <p class="text-xs text-gray-500 mb-1">Driver</p>
                  <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($booking_info['driver_name']) ?></p>
                </div>
              <?php endif; ?>

              <?php if ($booking_info['vehicle_plate_number']): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                  <p class="text-xs text-gray-500 mb-1">Vehicle Plate</p>
                  <p class="font-semibold text-gray-900 text-sm">
                    <?= htmlspecialchars($booking_info['vehicle_plate_number']) ?></p>
                </div>
              <?php endif; ?>

              <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500 mb-1">Booking Status</p>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold
            <?php
            switch ($booking_info['booking_status']) {
              case 'delivered':
              case 'picked_up':
                echo 'bg-green-100 text-green-800';
                break;
              case 'in_transit':
                echo 'bg-blue-100 text-blue-800';
                break;
              case 'confirmed':
                echo 'bg-purple-100 text-purple-800';
                break;
              default:
                echo 'bg-gray-100 text-gray-800';
            }
            ?>">
                  <?= ucfirst(str_replace('_', ' ', $booking_info['booking_status'])) ?>
                </span>
              </div>

            </div>
          </div>
        <?php endif; ?>

        <!-- Info Note -->
        <div class="flex items-start gap-2 p-3 bg-blue-50 rounded-lg text-xs sm:text-sm text-blue-700">
          <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p>
            <?php
            if ($has_actual_delivery)
              echo "Your order has been successfully delivered. Thank you for your purchase!";
            elseif ($has_estimated_delivery || $has_scheduled_delivery)
              echo "This is the confirmed delivery schedule. You'll be notified of any changes.";
            else
              echo "This is an estimated timeframe based on supplier lead times. We'll update you once delivery is scheduled.";
            ?>
          </p>
        </div>

      </div>
    <?php endif; ?>



    <!-- Order Level Status -->
    <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 animate-slide-in">
      <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-4 sm:mb-6">Order Status</h3>

      <div class="flex flex-col">
        <?php
        $delivery_type = strtolower($order['delivery_type'] ?? 'delivery');
        $order_steps = getOrderStatusSteps($delivery_type);
        $normalized_status = strtolower(str_replace(' ', '_', $order['status']));

        if ($normalized_status === 'completed') {
          $current_order_index = count($order_steps);
        } else {
          $current_order_index = getOrderStepIndex($order['status'], $delivery_type);
        }

        $step_count = 0;
        $total_steps = count($order_steps);

        foreach ($order_steps as $step_key => $step_data):
          $is_completed = $step_count < $current_order_index;
          $is_current = $step_count === $current_order_index && $normalized_status !== 'completed';
          $is_last = $step_count === $total_steps - 1;

          if ($normalized_status === 'completed' && $is_last) {
            $is_completed = true;
            $is_current = false;
          }

          $circle_bg = $is_completed ? '#22c55e' : ($is_current ? '#3b82f6' : '#e5e7eb');
          $icon_color = ($is_completed || $is_current) ? 'white' : '#9ca3af';
          $text_color = $is_completed ? '#111827' : ($is_current ? '#3b82f6' : '#9ca3af');
          $desc_color = ($is_completed || $is_current) ? '#6b7280' : '#d1d5db';
          $line_color = ($step_count < $current_order_index) ? '#22c55e' : '#e5e7eb';
          ?>

          <div style="display:flex; align-items:flex-start; gap:12px;">
            <!-- Circle + connector -->
            <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
              <div
                style="width:28px; height:28px; border-radius:50%; background:<?= $circle_bg ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <?php if ($is_completed): ?>
                  <svg style="width:14px;height:14px;" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                  </svg>
                <?php else: ?>
                  <svg style="width:14px;height:14px;" fill="none" stroke="<?= $icon_color ?>" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $step_data['icon'] ?>" />
                  </svg>
                <?php endif; ?>
              </div>

              <?php if (!$is_last): ?>
                <div style="width:2px; min-height:28px; background:<?= $line_color ?>;"></div>
              <?php endif; ?>
            </div>

            <!-- Text -->
            <div style="padding-top:3px; padding-bottom:<?= $is_last ? '0' : '4px' ?>;">
              <p
                style="font-size:13px; font-weight:600; color:<?= $text_color ?>; margin:0; display:flex; align-items:center; gap:8px;">
                <?= $step_data['name'] ?>
                <?php if ($is_current): ?>
                  <span
                    style="font-size:11px; background:#3b82f6; color:white; padding:2px 8px; border-radius:999px; font-weight:500;">Current</span>
                <?php endif; ?>
              </p>
              <p style="font-size:12px; color:<?= $desc_color ?>; margin:2px 0 0;">
                <?= $step_data['description'] ?>
              </p>
            </div>
          </div>

          <?php
          $step_count++;
        endforeach;
        ?>
      </div>

      <!-- Feedback Section -->
      <?php
      $all_items_completed = true;
      $delivery_type = strtolower($order['delivery_type'] ?? 'delivery');
      $completion_status = ($delivery_type === 'pickup') ? 'picked_up' : 'delivered';

      foreach ($order_items as $item) {
        $item_status = strtolower($item['tracking_status'] ?? '');
        if ($item_status !== $completion_status) {
          $all_items_completed = false;
          break;
        }
      }

      $stmt = $conn->prepare("SELECT id, rating, feedback_text, delivery_rating, product_quality_rating, created_at FROM order_feedback WHERE order_id = ?");
      $stmt->bind_param("i", $order_id);
      $stmt->execute();
      $feedback_result = $stmt->get_result();
      $existing_feedback = $feedback_result->fetch_assoc();
      $stmt->close();

      $order_status_lower = strtolower(str_replace(' ', '_', $order['status']));
      $show_feedback_button = $all_items_completed &&
        !in_array($order_status_lower, ['completed']) &&
        !$existing_feedback;
      ?>

      <?php if ($show_feedback_button): ?>
        <div class="mt-6 border-t pt-6">
          <div class="bg-green-50 border-green-200 rounded-xl p-4 mb-4">
            <div class="flex items-start gap-3">
              <div class="shrink-0">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="flex-1">
                <h4 class="font-semibold text-green-900 mb-1">All items have been
                  <?= $delivery_type === 'pickup' ? 'picked up' : 'delivered' ?>!
                </h4>
                <p class="text-sm text-green-700">Help us improve by sharing your experience with this order.</p>
              </div>
            </div>
          </div>
          <button onclick="openFeedbackModal()"
            class="w-full bg-green-500 text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
            Complete Order & Leave Feedback
          </button>
        </div>
      <?php endif; ?>


      <?php if ($existing_feedback): ?>
        <div class="mt-6 border-t pt-6">
          <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
              <h4 class="font-semibold text-green-800 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Order Completed
              </h4>
              <span class="text-xs text-green-600">
                <?= date('M j, Y', strtotime($existing_feedback['created_at'])) ?>
              </span>
            </div>

            <div class="mb-3">
              <p class="text-xs font-medium text-green-700 mb-1">Overall Experience</p>
              <div class="flex items-center gap-1">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <svg class="w-5 h-5 <?= $i <= $existing_feedback['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"
                    fill="currentColor" viewBox="0 0 20 20">
                    <path
                      d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                <?php endfor; ?>
                <span class="ml-2 text-sm font-semibold text-green-800"><?= $existing_feedback['rating'] ?>/5</span>
              </div>
            </div>

            <?php if ($existing_feedback['delivery_rating'] || $existing_feedback['product_quality_rating']): ?>
              <div class="grid grid-cols-2 gap-3 mb-3">
                <?php if ($existing_feedback['delivery_rating']): ?>
                  <div>
                    <p class="text-xs font-medium text-green-700 mb-1">
                      <?= $delivery_type === 'pickup' ? 'Pickup' : 'Delivery' ?> Service
                    </p>
                    <div class="flex items-center gap-1">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <svg
                          class="w-4 h-4 <?= $i <= $existing_feedback['delivery_rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"
                          fill="currentColor" viewBox="0 0 20 20">
                          <path
                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                      <?php endfor; ?>
                    </div>
                  </div>
                <?php endif; ?>
                <?php if ($existing_feedback['product_quality_rating']): ?>
                  <div>
                    <p class="text-xs font-medium text-green-700 mb-1">Product Quality</p>
                    <div class="flex items-center gap-1">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <svg
                          class="w-4 h-4 <?= $i <= $existing_feedback['product_quality_rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"
                          fill="currentColor" viewBox="0 0 20 20">
                          <path
                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                      <?php endfor; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($existing_feedback['feedback_text']): ?>
              <div class="border-t border-green-200 pt-3">
                <p class="text-sm text-green-700 italic">"<?= htmlspecialchars($existing_feedback['feedback_text']) ?>"</p>
              </div>
            <?php endif; ?>

            <p class="text-xs text-green-600 mt-3 flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
              </svg>
              Thank you for your feedback!
            </p>
          </div>
        </div>
      <?php endif; ?>
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

      <div class="space-y-4 sm:space-y-6">
        <?php foreach ($order_items as $index => $item): ?>

          <?php if (!$show_item_tracking): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 sm:p-4">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                  <span class="text-xs sm:text-sm font-bold text-gray-700"><?= $index + 1 ?></span>
                </div>
                <div class="flex-1 min-w-0">
                  <h4 class="font-semibold text-gray-900 text-sm sm:text-base truncate">
                    <?= htmlspecialchars($item['product_name']) ?>
                  </h4>
                  <p class="text-xs sm:text-sm text-gray-600 truncate">
                    <?= htmlspecialchars($item['variant_color']) ?> - <?= htmlspecialchars($item['size']) ?>
                  </p>
                  <p class="text-xs text-gray-500">Qty: <?= $item['quantity'] ?></p>
                  <?php if ($item['lt_from'] && $item['lt_to']): ?>
                    <div class="mt-1 flex items-center gap-1 text-xs text-blue-600">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      <span><?= date('M d', strtotime($item['lt_from'])) ?> -
                        <?= date('M d, Y', strtotime($item['lt_to'])) ?></span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php
          $origin = strtolower($item['origin'] ?? 'local');
          $current_status = $item['tracking_status'] ?? 'processing';
          $delivery_type = strtolower($order['delivery_type'] ?? 'delivery');

          $is_eligible_for_replacement = isEligibleForReplacement($item, $order);
          $replacement_info = hasReplacementRequest($item['id'], $conn);
          $has_replacement_request = !empty($replacement_info);

          $show_replacement_tracking = $has_replacement_request &&
            !in_array(strtolower($item['replacement_status'] ?? ''), ['rejected', '']);

          if ($origin === 'local') {
            $steps = getLocalStatusSteps($delivery_type);
            $origin_icon = '';
            $origin_label = 'Local';
            $origin_color = 'bg-green-100 text-green-800';
          } else {
            $steps = getInternationalStatusSteps($delivery_type);
            $origin_icon = '';
            $origin_label = 'International';
            $origin_color = 'bg-blue-100 text-blue-800';
          }

          $current_step_index = getCurrentStepIndex($current_status, $steps);
          ?>

          <div class="border border-gray-100 rounded-xl p-3 sm:p-4 hover:shadow-md transition-shadow">

            <!-- Item Header -->
            <div
              class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 <?= $show_item_tracking ? 'mb-4' : '' ?>">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                  <span class="text-xs sm:text-sm font-bold text-gray-700"><?= $index + 1 ?></span>
                </div>
                <div class="flex-1 min-w-0">
                  <h4 class="font-semibold text-gray-900 text-sm sm:text-base truncate">
                    <?= htmlspecialchars($item['product_name']) ?>
                  </h4>
                  <p class="text-xs sm:text-sm text-gray-600 truncate">
                    <?= htmlspecialchars($item['variant_color']) ?> -
                    <?= htmlspecialchars($item['size']) ?>
                  </p>
                  <p class="text-xs text-gray-500">Qty: <?= $item['quantity'] ?></p>
                </div>
              </div>

              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="flex flex-wrap gap-2">
                  <span
                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full <?= $origin_color ?> w-fit">
                    <?= $origin_icon ?>   <?= $origin_label ?>
                  </span>

                  <?php if ($show_replacement_tracking): ?>
                    <?php
                    $replacement_status = strtolower($item['replacement_status'] ?? '');
                    $replacement_delivery_status = strtolower($item['replacement_delivery_status'] ?? '');

                    if ($replacement_status === 'delivered') {
                      $status_color = 'bg-green-100 text-green-800';
                      $status_text = 'Replacement Delivered';
                    } elseif ($replacement_status === 'picked_up') {
                      $status_color = 'bg-green-100 text-green-800';
                      $status_text = 'Replacement Picked Up';
                    } elseif ($replacement_delivery_status === 'delivered') {
                      $status_color = 'bg-green-100 text-green-800';
                      $status_text = 'Replacement Delivered';
                    } elseif ($replacement_delivery_status === 'picked_up') {
                      $status_color = 'bg-green-100 text-green-800';
                      $status_text = 'Replacement Picked Up';
                    } elseif ($replacement_delivery_status === 'item_is_loaded' || $replacement_delivery_status === 'out_for_delivery') {
                      $status_color = 'bg-blue-100 text-blue-800';
                      $status_text = $delivery_type === 'pickup' ? 'Out for Pickup' : 'Out for Delivery';
                    } elseif ($replacement_delivery_status === 'ready_for_pickup') {
                      $status_color = 'bg-purple-100 text-purple-800';
                      $status_text = 'Ready for ' . ($delivery_type === 'pickup' ? 'Pickup' : 'Delivery');
                    } elseif ($replacement_status === 'scheduled') {
                      $status_color = 'bg-indigo-100 text-indigo-800';
                      $status_text = 'Delivery Scheduled';
                    } elseif ($replacement_status === 'in_warehouse' || $replacement_status === 'in warehouse') {
                      $status_color = 'bg-purple-100 text-purple-800';
                      $status_text = 'In Warehouse';
                    } elseif ($replacement_status === 'processing') {
                      $status_color = 'bg-yellow-100 text-yellow-800';
                      $status_text = 'Processing';
                    } elseif ($replacement_status === 'approved') {
                      $status_color = 'bg-green-100 text-green-800';
                      $status_text = 'Approved';
                    } elseif ($replacement_status === 'rejected') {
                      $status_color = 'bg-red-100 text-red-800';
                      $status_text = 'Rejected';
                    } elseif ($replacement_status === 'pending') {
                      $status_color = 'bg-orange-500 text-white';
                      $status_text = 'Pending Review';
                    } else {
                      $status_color = 'bg-gray-100 text-gray-800';
                      $status_text = ucfirst(str_replace('_', ' ', $replacement_status));
                    }
                    ?>
                    <span
                      class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full <?= $status_color ?> w-fit">
                      <?= $status_text ?>
                    </span>
                  <?php elseif ($has_replacement_request): ?>
                    <span
                      class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800 w-fit">
                      Replacement Requested
                    </span>
                  <?php elseif ($is_eligible_for_replacement): ?>
                    <a href="<?= BASE_URL ?>/replacement?order_id=<?= $order_id ?>&item_id=<?= $item['id'] ?>"
                      class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors cursor-pointer w-fit">
                      Request Replacement
                    </a>
                  <?php endif; ?>
                </div>
                <span
                  class="text-base sm:text-lg font-bold text-gray-900">₱<?= number_format($item['subtotal'], 2) ?></span>
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
                $replacement_status_lower = strtolower($item['replacement_status'] ?? '');
                $replacement_delivery_status_lower = strtolower($item['replacement_delivery_status'] ?? '');

                if ($replacement_status_lower === 'delivered') {
                  $replacement_tracking_status = 'delivered';
                } elseif ($replacement_status_lower === 'picked_up') {
                  $replacement_tracking_status = 'picked_up';
                } elseif ($replacement_delivery_status_lower === 'delivered') {
                  $replacement_tracking_status = 'delivered';
                } elseif ($replacement_delivery_status_lower === 'picked_up') {
                  $replacement_tracking_status = 'picked_up';
                } elseif (
                  $replacement_delivery_status_lower === 'item_is_loaded' ||
                  $replacement_delivery_status_lower === 'out_for_delivery' ||
                  $replacement_status_lower === 'out_for_delivery'
                ) {
                  $replacement_tracking_status = 'item_is_loaded';
                } elseif (
                  $replacement_delivery_status_lower === 'ready_for_pickup' ||
                  $replacement_status_lower === 'ready_for_pickup'
                ) {
                  $replacement_tracking_status = 'ready_for_pickup';
                } elseif ($item['replacement_delivery_id'] && $replacement_status_lower === 'scheduled') {
                  $replacement_tracking_status = 'ready_for_pickup';
                } elseif ($replacement_status_lower === 'in_warehouse' || $replacement_status_lower === 'in warehouse') {
                  $replacement_tracking_status = 'processing';
                } elseif ($replacement_status_lower === 'processing') {
                  $replacement_tracking_status = 'processing';
                } elseif ($replacement_status_lower === 'approved') {
                  $replacement_tracking_status = 'approved';
                } else {
                  $replacement_tracking_status = 'pending';
                }

                $replacement_steps = getReplacementStatusSteps($delivery_type);
                $replacement_current_index = getCurrentStepIndex($replacement_tracking_status, $replacement_steps);
                ?>

                <!-- Replacement Steps -->
                <div class="flex flex-col">
                  <?php
                  $step_count = 0;
                  $total_replacement_steps = count($replacement_steps);
                  foreach ($replacement_steps as $step_key => $step_data):
                    $is_completed = $step_count < $replacement_current_index;
                    $is_current = $step_count === $replacement_current_index;
                    $is_last = $step_count === $total_replacement_steps - 1;
                    $r_circle_bg = $is_completed ? '#22c55e' : ($is_current ? '#f97316' : '#e5e7eb');
                    $r_icon_color = ($is_completed || $is_current) ? 'white' : '#9ca3af';
                    $r_text_color = $is_completed ? '#111827' : ($is_current ? '#ea580c' : '#9ca3af');
                    $r_desc_color = ($is_completed || $is_current) ? '#6b7280' : '#d1d5db';
                    $r_line_color = ($step_count < $replacement_current_index) ? '#22c55e' : '#e5e7eb';
                    ?>
                    <div style="display:flex; align-items:flex-start; gap:10px;">
                      <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                        <div
                          style="width:22px; height:22px; border-radius:50%; background:<?= $r_circle_bg ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                          <?php if ($is_completed): ?>
                            <svg style="width:11px;height:11px;" fill="none" stroke="white" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                          <?php else: ?>
                            <svg style="width:11px;height:11px;" fill="none" stroke="<?= $r_icon_color ?>" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="<?= $step_data['icon'] ?>" />
                            </svg>
                          <?php endif; ?>
                        </div>
                        <?php if (!$is_last): ?>
                          <div style="width:2px; min-height:22px; background:<?= $r_line_color ?>;"></div>
                        <?php endif; ?>
                      </div>
                      <div style="padding-top:2px; padding-bottom:<?= $is_last ? '0' : '4px' ?>;">
                        <p style="font-size:12px; font-weight:600; color:<?= $r_text_color ?>; margin:0;">
                          <?= $step_data['name'] ?>
                          <?php if ($is_current): ?>
                            <span
                              style="font-size:10px; background:#f97316; color:white; padding:1px 6px; border-radius:999px; font-weight:500; margin-left:4px;">Current</span>
                          <?php endif; ?>
                        </p>
                        <p style="font-size:11px; color:<?= $r_desc_color ?>; margin:1px 0 0;">
                          <?= $step_data['description'] ?>
                        </p>
                      </div>
                    </div>
                    <?php $step_count++; endforeach; ?>
                </div>

                <?php if ($item['replacement_requested_at']): ?>
                  <div class="mt-2 text-xs text-orange-700">
                    <strong>Requested:</strong>
                    <?= date('M j, Y g:i A', strtotime($item['replacement_requested_at'])) ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <!-- ✅ DITO ILAGAY -- Item Tracking Steps -->

            <?php
            $replacement_final = in_array($replacement_tracking_status ?? '', ['delivered', 'picked_up']);
            if ($show_item_tracking && !($show_replacement_tracking && $replacement_final)):
              ?>
              <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 mb-3 uppercase tracking-wide">Item Tracking</p>
                <div class="flex flex-col">
                  <?php
                  $step_count = 0;
                  $total_item_steps = count($steps);
                  foreach ($steps as $step_key => $step_data):
                    $is_completed = $step_count < $current_step_index;
                    $is_current = $step_count === $current_step_index;
                    $is_last = $step_count === $total_item_steps - 1;
                    $i_circle_bg = $is_completed ? '#22c55e' : ($is_current ? '#3b82f6' : '#e5e7eb');
                    $i_icon_color = ($is_completed || $is_current) ? 'white' : '#9ca3af';
                    $i_text_color = $is_completed ? '#111827' : ($is_current ? '#3b82f6' : '#9ca3af');
                    $i_desc_color = ($is_completed || $is_current) ? '#6b7280' : '#d1d5db';
                    $i_line_color = ($step_count < $current_step_index) ? '#22c55e' : '#e5e7eb';
                    ?>
                    <div style="display:flex; align-items:flex-start; gap:10px;">
                      <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                        <div
                          style="width:22px; height:22px; border-radius:50%; background:<?= $i_circle_bg ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                          <?php if ($is_completed): ?>
                            <svg style="width:11px;height:11px;" fill="none" stroke="white" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                          <?php else: ?>
                            <svg style="width:11px;height:11px;" fill="none" stroke="<?= $i_icon_color ?>" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="<?= $step_data['icon'] ?>" />
                            </svg>
                          <?php endif; ?>
                        </div>
                        <?php if (!$is_last): ?>
                          <div style="width:2px; min-height:22px; background:<?= $i_line_color ?>;"></div>
                        <?php endif; ?>
                      </div>
                      <div style="padding-top:2px; padding-bottom:<?= $is_last ? '0' : '4px' ?>;">
                        <p style="font-size:12px; font-weight:600; color:<?= $i_text_color ?>; margin:0;">
                          <?= $step_data['name'] ?>
                          <?php if ($is_current): ?>
                            <span
                              style="font-size:10px; background:#3b82f6; color:white; padding:1px 6px; border-radius:999px; font-weight:500; margin-left:4px;">Current</span>
                          <?php endif; ?>
                        </p>
                        <p style="font-size:11px; color:<?= $i_desc_color ?>; margin:1px 0 0;">
                          <?= $step_data['description'] ?>
                        </p>
                      </div>
                    </div>
                    <?php $step_count++; endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

          </div><!-- end item card -->
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Additional Information -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">

      <!-- Delivery Information -->
      <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 animate-slide-in">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            </svg>
          </div>
          <h3 class="text-base sm:text-lg font-bold text-gray-900">Delivery Information</h3>
        </div>

        <div class="space-y-3">
          <!-- Name -->
          <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
            <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <div>
              <p class="text-xs text-gray-500 mb-0.5">Recipient</p>
              <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($order['customer_name']) ?></p>
            </div>
          </div>

          <!-- Address -->
          <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
            <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <div>
              <p class="text-xs text-gray-500 mb-0.5">Address</p>
              <p class="text-sm font-semibold text-gray-900 break-words"><?= htmlspecialchars($order['address']) ?></p>
            </div>
          </div>

          <!-- Mobile -->
          <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
            <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
            <div>
              <p class="text-xs text-gray-500 mb-0.5">Contact Number</p>
              <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($order['mobile']) ?></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Summary -->
      <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 animate-slide-in">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
          <h3 class="text-base sm:text-lg font-bold text-gray-900">Order Summary</h3>
        </div>

        <div class="space-y-2">
          <!-- Subtotal -->
          <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
              <span class="text-sm text-gray-600">Subtotal</span>
            </div>
            <span class="text-sm font-semibold text-gray-900">₱<?= number_format($order['subtotal'] ?? 0, 2) ?></span>
          </div>

          <!-- Delivery Fee -->
          <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
              </svg>
              <span class="text-sm text-gray-600">Delivery Fee</span>
            </div>
            <span
              class="text-sm font-semibold text-gray-900">₱<?= number_format($order['delivery_fee'] ?? 0, 2) ?></span>
          </div>

          <!-- VAT -->
          <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
              </svg>
              <span class="text-sm text-gray-600">VAT</span>
            </div>
            <span class="text-sm font-semibold text-gray-900">₱<?= number_format($order['vat_amount'] ?? 0, 2) ?></span>
          </div>

          <!-- Total -->
          <div class="flex justify-between items-center p-3 bg-green-50 border border-green-200 rounded-lg mt-3">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="text-sm font-bold text-green-800">Total</span>
            </div>
            <span class="text-lg font-bold text-green-700">₱<?= number_format($order['final_total'], 2) ?></span>
          </div>
        </div>
      </div>

    </div>

    <!-- Mobile-friendly spacing at bottom -->
    <div class="pb-6 sm:pb-0"></div>
  </div>

  <?php include ROOT_PATH . '/user/navbar/footer.php' ?>

  <!-- Leaflet JavaScript -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

  <!-- Leaflet Routing Machine JavaScript -->
  <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

  <script>
    // Map initialization
    <?php if ($show_map): ?>
      document.addEventListener('DOMContentLoaded', function () {
        const MAPBOX_TOKEN = 'pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ';
        mapboxgl.accessToken = MAPBOX_TOKEN;

        const warehouseLng = <?= $delivery_settings['longitude'] ?>;
        const warehouseLat = <?= $delivery_settings['latitude'] ?>;
        const deliveryLng = <?= $order['delivery_lng'] ?>;
        const deliveryLat = <?= $order['delivery_lat'] ?>;

        const map = new mapboxgl.Map({
          container: 'deliveryMap',
          style: 'mapbox://styles/mapbox/streets-v12',
          center: [deliveryLng, deliveryLat],
          zoom: 12
        });

        map.addControl(new mapboxgl.NavigationControl(), 'top-right');

        // Warehouse marker (green)
        const warehouseEl = document.createElement('div');
        warehouseEl.innerHTML = `
            <div style="background:#10B981;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,0.3);">
                <i class="fa-solid fa-location-arrow text-xl text-white"></i>
            </div>`;

        new mapboxgl.Marker({ element: warehouseEl })
          .setLngLat([warehouseLng, warehouseLat])
          .setPopup(new mapboxgl.Popup({ offset: 25 }).setHTML(`
                <div style="min-width:180px;">
                    <p style="font-weight:700;color:#10B981;margin:0 0 4px;">Warehouse</p>
                    <p style="font-size:12px;color:#666;margin:0;"><?= htmlspecialchars($delivery_settings['location_name']) ?></p>
                </div>`))
          .addTo(map);

        // Delivery marker (blue)
        const deliveryEl = document.createElement('div');
        deliveryEl.innerHTML = `
            <div style="background:#3B82F6;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,0.3);">
                <i class="fa-solid fa-location-crosshairs text-xl text-white"></i>
            </div>`;

        new mapboxgl.Marker({ element: deliveryEl })
          .setLngLat([deliveryLng, deliveryLat])
          .setPopup(new mapboxgl.Popup({ offset: 25 }).setHTML(`
                <div style="min-width:180px;">
                    <p style="font-weight:700;color:#3B82F6;margin:0 0 4px;">Delivery Address</p>
                    <p style="font-weight:600;font-size:13px;margin:0 0 2px;"><?= htmlspecialchars($order['customer_name']) ?></p>
                    <p style="font-size:12px;color:#666;margin:0;"><?= htmlspecialchars($order['delivery_address']) ?>, <?= htmlspecialchars($order['delivery_city']) ?></p>
                </div>`))
          .addTo(map);

        // Draw route line via Directions API
        map.on('load', async () => {
          try {
            const res = await fetch(
              `https://api.mapbox.com/directions/v5/mapbox/driving/${warehouseLng},${warehouseLat};${deliveryLng},${deliveryLat}?geometries=geojson&access_token=${MAPBOX_TOKEN}`
            );
            const data = await res.json();
            const route = data.routes[0].geometry;

            map.addSource('route', { type: 'geojson', data: { type: 'Feature', geometry: route } });
            map.addLayer({
              id: 'route',
              type: 'line',
              source: 'route',
              layout: { 'line-join': 'round', 'line-cap': 'round' },
              paint: { 'line-color': '#FF6B35', 'line-width': 5, 'line-opacity': 0.8 }
            });

            // Fit map to show both markers + route
            const bounds = new mapboxgl.LngLatBounds();
            route.coordinates.forEach(c => bounds.extend(c));
            map.fitBounds(bounds, { padding: 60 });
          } catch (e) {
            console.error('Route error:', e);
            // Fallback: just fit markers
            const bounds = new mapboxgl.LngLatBounds()
              .extend([warehouseLng, warehouseLat])
              .extend([deliveryLng, deliveryLat]);
            map.fitBounds(bounds, { padding: 60 });
          }
        });
      });
    <?php endif; ?>

    // Add smooth scroll animation for page load
    document.addEventListener('DOMContentLoaded', function () {
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
            container.scrollTo({
              left: container.scrollLeft + scrollLeft,
              behavior: 'smooth'
            });
          }, 200);
        }
      });
    });

    // Handle orientation change for mobile
    window.addEventListener('orientationchange', function () {
      setTimeout(() => {
        // Re-center current steps after orientation change
        const mobileItemSteps = document.querySelectorAll('.mobile-item-steps');
        mobileItemSteps.forEach(container => {
          const currentStep = container.querySelector('.animate-pulse-slow');
          if (currentStep) {
            const containerRect = container.getBoundingClientRect();
            const stepRect = currentStep.getBoundingClientRect();
            const scrollLeft = stepRect.left - containerRect.left - containerRect.width / 2 + stepRect.width / 2;
            container.scrollTo({
              left: container.scrollLeft + scrollLeft,
              behavior: 'smooth'
            });
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

  <!-- Feedback Modal -->
  <div id="feedbackModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-2xl font-bold text-gray-900">Complete Your Order</h3>
          <button onclick="closeFeedbackModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form id="feedbackForm" class="space-y-6">
          <input type="hidden" name="order_id" value="<?= $order_id ?>">

          <!-- Overall Rating -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">Overall Experience *</label>
            <div class="flex items-center gap-2">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" onclick="setRating('overall', <?= $i ?>)"
                  class="rating-star overall-rating transition-all hover:scale-110" data-value="<?= $i ?>">
                  <svg class="w-10 h-10 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                    <path
                      d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </button>
              <?php endfor; ?>
              <span id="overallRatingText" class="ml-2 text-sm text-gray-600"></span>
            </div>
            <input type="hidden" name="rating" id="overallRatingInput" required>
          </div>

          <!-- Delivery Rating -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">
              <?= $delivery_type === 'pickup' ? 'Pickup' : 'Delivery' ?> Service
            </label>
            <div class="flex items-center gap-2">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" onclick="setRating('delivery', <?= $i ?>)"
                  class="rating-star delivery-rating transition-all hover:scale-110" data-value="<?= $i ?>">
                  <svg class="w-8 h-8 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                    <path
                      d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </button>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="delivery_rating" id="deliveryRatingInput">
          </div>

          <!-- Product Quality Rating -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">Product Quality</label>
            <div class="flex items-center gap-2">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" onclick="setRating('product', <?= $i ?>)"
                  class="rating-star product-rating transition-all hover:scale-110" data-value="<?= $i ?>">
                  <svg class="w-8 h-8 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                    <path
                      d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </button>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="product_quality_rating" id="productRatingInput">
          </div>

          <!-- Feedback Text -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">
              Your Feedback (Optional)
            </label>
            <textarea name="feedback_text" rows="4"
              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
              placeholder="Share your experience with us..."></textarea>
          </div>

          <!-- Error Message -->
          <div id="feedbackError"
            class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm"></div>

          <!-- Submit Button -->
          <div class="flex gap-3">
            <button type="button" onclick="closeFeedbackModal()"
              class="flex-1 px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors">
              Cancel
            </button>
            <button type="submit" id="submitFeedbackBtn"
              class="flex-1 bg-green-500 text-white font-semibold py-3 px-6 rounded-xl hover:shadow-lg transition-all">
              Submit & Complete Order
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Rating functionality
    const ratingLabels = {
      1: 'Poor',
      2: 'Fair',
      3: 'Good',
      4: 'Very Good',
      5: 'Excellent'
    };

    function setRating(type, value) {
      const stars = document.querySelectorAll(`.${type}-rating`);
      const input = document.getElementById(`${type}RatingInput`);

      stars.forEach((star, index) => {
        const svg = star.querySelector('svg');
        if (index < value) {
          svg.classList.remove('text-gray-300');
          svg.classList.add('text-yellow-400');
        } else {
          svg.classList.remove('text-yellow-400');
          svg.classList.add('text-gray-300');
        }
      });

      input.value = value;

      if (type === 'overall') {
        document.getElementById('overallRatingText').textContent = ratingLabels[value];
      }
    }

    function openFeedbackModal() {
      const modal = document.getElementById('feedbackModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.style.overflow = 'hidden';
    }
    function closeFeedbackModal() {
      const modal = document.getElementById('feedbackModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = 'auto';
    }
    // Handle form submission
    document.getElementById('feedbackForm').addEventListener('submit', async function (e) {
      e.preventDefault();

      const overallRating = document.getElementById('overallRatingInput').value;

      if (!overallRating) {
        document.getElementById('feedbackError').textContent = 'Please provide an overall rating';
        document.getElementById('feedbackError').classList.remove('hidden');
        return;
      }

      document.getElementById('feedbackError').classList.add('hidden');
      const submitBtn = document.getElementById('submitFeedbackBtn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      const formData = new FormData(this);

      try {
        const response = await fetch('<?= BASE_URL ?>/feedback', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          closeFeedbackModal();
          // Show success message and reload
          alert('Thank you for your feedback! Your order has been completed.');
          window.location.reload();
        } else {
          document.getElementById('feedbackError').textContent = result.message || 'Failed to submit feedback';
          document.getElementById('feedbackError').classList.remove('hidden');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit & Complete Order';
        }
      } catch (error) {
        console.error('Error:', error);
        document.getElementById('feedbackError').textContent = 'An error occurred. Please try again.';
        document.getElementById('feedbackError').classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit & Complete Order';
      }
    });

    // Close modal on escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeFeedbackModal();
      }
    });
  </script>
</body>

</html>