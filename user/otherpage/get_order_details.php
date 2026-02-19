<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo '<div class="text-center py-8">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <p class="text-red-600 font-medium">No order ID provided</p>
    </div>';
    exit;
}

$order_id = (int)$_GET['order_id'];

// ✅ Get order details with proper query
$stmt = $conn->prepare("SELECT * FROM variant_tracking WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="text-center py-8">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <p class="text-red-600 font-medium">Waiting for processing</p>
    </div>';
    exit;
}

$order = $result->fetch_assoc();
$stmt->close();

// ✅ Calculate final total
$subtotal = $order['subtotal'] ?? 0;
$discount = $order['discount'] ?? 0;
$vat_amount = $order['vat_amount'] ?? 0;
$shipping_fee = $order['shipping_fee'] ?? 0;
$delivery_fee = $order['delivery_fee'] ?? 0;

// Use existing final_total if available, otherwise calculate
$final_total = $order['final_total'] ?? ($subtotal - $discount + $vat_amount + $shipping_fee + $delivery_fee);

// Get customer info (mobile and address)
$customer_stmt = $conn->prepare("SELECT mobile, customer_address FROM variant_tracking WHERE order_id = ? LIMIT 1");
$customer_stmt->bind_param("i", $order_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer_info = $customer_result->fetch_assoc();
$customer_stmt->close();

// Get order items with descriptions from variant_tracking table
$stmt = $conn->prepare("
    SELECT 
        oi.*,
        vt.description1,
        vt.description2
    FROM order_items oi
    LEFT JOIN variant_tracking vt ON oi.order_id = vt.order_id AND oi.id = vt.order_item_id
    WHERE oi.order_id = ?
    GROUP BY oi.id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// Get the latest status for this order
$latest_status_stmt = $conn->prepare("SELECT status, completed_at FROM variant_tracking WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$latest_status_stmt->bind_param("i", $order_id);
$latest_status_stmt->execute();
$latest_status_result = $latest_status_stmt->get_result();
$current_order_status = $latest_status_result->fetch_assoc();
$latest_status_stmt->close();

// Get all tracking logs
$tracking_stmt = $conn->prepare("
    SELECT DISTINCT vt.place, vt.status, vt.timestamp, vt.driver_name, vt.truck_plate as plate_number, vt.id
    FROM variant_tracking vt
    WHERE vt.order_id = ?
    ORDER BY vt.id ASC
");

$tracking_stmt->bind_param("i", $order_id);
$tracking_stmt->execute();
$tracking_result = $tracking_stmt->get_result();

$all_tracking_logs = [];
while ($row = $tracking_result->fetch_assoc()) {
    $all_tracking_logs[] = $row;
}
$tracking_stmt->close();

// Process tracking progression function
function processTrackingProgression($all_logs)
{
    $progression = [];
    $place_status = [];

    foreach ($all_logs as $log) {
        $place = $log['place'] ?? '';

        if (!isset($place_status[$place])) {
            $place_status[$place] = [
                'place' => $place,
                'current_status' => 'pending',
                'timestamp' => null,
                'driver_name' => $log['driver_name'] ?? null,
                'plate_number' => $log['plate_number'] ?? null,
                'logs' => []
            ];
        }

        $place_status[$place]['logs'][] = $log;

        // Check status priority: complete > reached > ongoing > pending
        if (($log['status'] ?? '') === 'complete') {
            $place_status[$place]['current_status'] = 'complete';
            $place_status[$place]['timestamp'] = $log['timestamp'] ?? null;
        } elseif (($log['status'] ?? '') === 'reached' && $place_status[$place]['current_status'] !== 'complete') {
            $place_status[$place]['current_status'] = 'reached';
            $place_status[$place]['timestamp'] = $log['timestamp'] ?? null;
        } elseif (($log['status'] ?? '') === 'ongoing' && !in_array($place_status[$place]['current_status'], ['complete', 'reached'])) {
            $place_status[$place]['current_status'] = 'ongoing';
            $place_status[$place]['timestamp'] = $log['timestamp'] ?? null;
        }
    }

    $progression = array_values($place_status);

    $last_completed_index = -1;
    $last_reached_index = -1;
    $ongoing_found = false;

    // Find the last completed/reached status
    for ($i = 0; $i < count($progression); $i++) {
        if ($progression[$i]['current_status'] === 'complete') {
            $last_completed_index = $i;
        } elseif ($progression[$i]['current_status'] === 'reached') {
            $last_reached_index = $i;
        } elseif ($progression[$i]['current_status'] === 'ongoing') {
            $ongoing_found = true;
        }
    }

    // Logic for setting next status to ongoing if no ongoing found
    for ($i = 0; $i < count($progression); $i++) {
        // Check if this place has ongoing status in logs
        $has_ongoing_in_logs = false;
        foreach ($progression[$i]['logs'] as $log) {
            if (($log['status'] ?? '') === 'ongoing') {
                $has_ongoing_in_logs = true;
                break;
            }
        }

        if ($has_ongoing_in_logs) {
            $progression[$i]['current_status'] = 'ongoing';
            foreach ($progression[$i]['logs'] as $log) {
                if (($log['status'] ?? '') === 'ongoing') {
                    $progression[$i]['timestamp'] = $log['timestamp'] ?? null;
                    break;
                }
            }
        }

        // Set next location to ongoing if no ongoing found and this is next in sequence
        if (
            !$ongoing_found && $i > max($last_completed_index, $last_reached_index) &&
            $progression[$i]['current_status'] === 'pending' && !empty($progression[$i]['logs'])
        ) {
            $progression[$i]['current_status'] = 'ongoing';
            $progression[$i]['timestamp'] = $progression[$i]['logs'][0]['timestamp'] ?? null;
            $ongoing_found = true;
        }

        // Reset future statuses to pending if ongoing found
        if ($ongoing_found && !in_array($progression[$i]['current_status'], ['complete', 'reached', 'ongoing'])) {
            $progression[$i]['current_status'] = 'pending';
            $progression[$i]['timestamp'] = null;
        }
    }

    return $progression;
}

// Status badge function
function getStatusBadge($status)
{
    $status_lower = strtolower($status ?? '');

    switch ($status_lower) {
        case 'pending':
            return '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                Pending
            </span>';
        case 'ongoing':
            return '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span>
                Ongoing
            </span>';
        case 'reached':
            return '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full bg-green-100 text-green-800">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                Reached
            </span>';
        case 'complete':
            return '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full bg-emerald-100 text-emerald-800">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Complete
            </span>';
        case 'arrival':
            return '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full bg-purple-100 text-purple-800">
                <span class="w-1.5 h-1.5 bg-purple-500 rounded-full"></span>
                Arrival
            </span>';
        case 'departure':
            return '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>
                Departure
            </span>';
        default:
            return '<span class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                ' . htmlspecialchars($status ?? 'Unknown') . '
            </span>';
    }
}

// Function to get status with icon (for Order Summary)
function getOrderStatusDisplay($status)
{
    switch (strtolower($status ?? '')) {
        case 'complete':
            return '<span class="text-emerald-600">✓ Complete</span>';
        case 'reached':
            return '<span class="text-green-600">✓ Reached</span>';
        case 'ongoing':
            return '<span class="text-blue-600">⟳ Ongoing</span>';
        case 'pending':
            return '<span class="text-gray-600">○ Pending</span>';
        default:
            return '<span class="text-gray-600">' . ucfirst($status ?? 'Unknown') . '</span>';
    }
}
$order = $conn->query("SELECT *, DATE_FORMAT(estimated_arrival_date, '%M %d, %Y') AS formatted_eta FROM orders WHERE id = $order_id")->fetch_assoc();

$tracking_progression = processTrackingProgression($all_tracking_logs);
$system_steps = ['Pending', 'Ongoing', 'Arrival', 'Customs'];




?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Order Tracking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="p-6">
        <!-- Order Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-orange-500">Order Tracking</h1>
                    <p class="text-gray-600">Order #<?php echo $order_id; ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Order Date</p>
                    <p class="font-medium"><?php echo date('M j, Y g:i A', strtotime($order['created_at'] ?? 'now')); ?></p>
                </div>
            </div>

            <!-- Customer Info -->
            <?php if ($customer_info && (isset($customer_info['mobile']) || isset($customer_info['customer_address']))): ?>
                <div class="text-sm text-gray-700 space-y-1">
                    <?php if (isset($customer_info['mobile']) && !empty($customer_info['mobile'])): ?>
                        <p><strong>Mobile:</strong> <?= htmlspecialchars($customer_info['mobile']) ?></p>
                    <?php endif; ?>
                    <?php if (isset($customer_info['customer_address']) && !empty($customer_info['customer_address'])): ?>
                        <p><strong>Address:</strong> <?= htmlspecialchars($customer_info['customer_address']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['reference_no'])): ?>
                <div class="flex justify-between text-red-500">
                    <span>Reference Number:</span>
                    <span><?php echo htmlspecialchars($order['reference_no']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-orange-500">Order Items</h2>
            <div class="space-y-4">
                <?php foreach ($items as $item): ?>
                    <div class="flex items-center justify-between border-b pb-4">
                        <div class="flex items-center space-x-4">
                            <div>
                                <h3 class="font-medium"><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown Product'); ?></h3>
                                <p class="text-sm text-gray-600">
                                    Color: <?php echo htmlspecialchars($item['variant_color'] ?? 'N/A'); ?> |
                                    Size: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?> |
                                    Qty: <?php echo $item['quantity'] ?? 0; ?>
                                </p>
                                <?php if (!empty($item['description1']) || !empty($item['description2'])): ?>
                                    <div class="mt-1 text-sm text-gray-700">
                                        <?php if (!empty($item['description1'])): ?>
                                            <p><?php echo htmlspecialchars($item['description1']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($item['description2'])): ?>
                                            <p><?php echo htmlspecialchars($item['description2']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-medium">₱<?php echo number_format($item['subtotal'] ?? 0, 2); ?></p>
                            <p class="text-sm text-gray-500">₱<?php echo number_format($item['price'] ?? 0, 2); ?> each</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Payment Breakdown -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-orange-500">Payment Breakdown</h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Subtotal:</span>
                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Discount:</span>
                    <span class="text-red-600">-₱<?php echo number_format($discount, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span>VAT (12%):</span>
                    <span>₱<?php echo number_format($vat_amount, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Shipping Fee:</span>
                    <span>₱<?php echo number_format($shipping_fee, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Delivery Fee:</span>
                    <span>₱<?php echo number_format($delivery_fee, 2); ?></span>
                </div>
                <div class="border-t pt-2 flex justify-between font-semibold">
                    <span class="text-green-500">Total Amount:</span>
                    <span class="text-green-500">₱<?php echo number_format($final_total, 2); ?></span>
                </div>

            </div>
        </div>

        <!-- Order Summary -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Order Date:</p>
                    <p class="font-medium"><?php echo date('M j, Y g:i A', strtotime($order['created_at'] ?? 'now')); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Status:</p>
                    <p class="font-medium"><?php echo getOrderStatusDisplay($current_order_status['status'] ?? 'pending'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Order Item ID:</p>
                    <p class="font-medium"><?php echo $order_id; ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Amount:</p>
                    <p class="font-medium">₱<?php echo number_format($final_total, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Tracking Progress -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-medium text-orange-600 flex items-center gap-1 text-lg mt-3">
                <svg class="w-6 h-6 text-black " fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11a4 4 0 100-8 4 4 0 000 8z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c4.418 0 8 3.582 8 8 0 4.5-8 10-8 10S4 15.5 4 11c0-4.418 3.582-8 8-8z" />
                </svg>
                Tracking Progress
            </h3>

            <div class="space-y-6">
                <?php foreach ($tracking_progression as $index => $step):
                    $is_system_step = in_array($step['place'] ?? '', $system_steps);
                ?>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 mt-1">
                            <?php if (($step['current_status'] ?? '') === 'complete'): ?>
                                <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            <?php elseif (($step['current_status'] ?? '') === 'reached'): ?>
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            <?php elseif (($step['current_status'] ?? '') === 'ongoing'): ?>
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                                </div>
                            <?php else: ?>
                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                    <div class="w-2 h-2 bg-gray-500 rounded-full"></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($index < count($tracking_progression) - 1): ?>
                                <div class="w-0.5 h-16 bg-gray-200 ml-4 mt-2"></div>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="font-medium text-gray-900"><?= htmlspecialchars($step['place'] ?? 'Unknown') ?></h3>
                                <?= getStatusBadge($step['current_status'] ?? 'pending'); ?>
                            </div>

                            <?php if (!empty($step['timestamp'])): ?>
                                <p class="text-sm text-gray-500 mb-1">
                                    <?= date('M j, Y g:i A', strtotime($step['timestamp'])) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!$is_system_step && !empty($step['driver_name'])): ?>
                                <p class="text-sm text-gray-600">
                                    Driver: <?= htmlspecialchars($step['driver_name']) ?>
                                    <?php if (!empty($step['plate_number'])): ?>
                                        | Plate: <?= htmlspecialchars($step['plate_number']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <?php if (($step['place'] ?? '') === 'Pending'): ?>
                                <div class="mt-2 text-gray-800 border-l-4 border-black p-3 rounded text-sm flex items-start space-x-2">
                                    <svg class="w-5 h-5 mt-0.5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3C7.03 3 3 7.03 3 12s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z"></path>
                                    </svg>
                                    <div>
                                        <strong>Your order is currently awaiting processing.</strong><br>
                                        We've received your order and it's now queued for preparation.
                                    </div>
                                </div>
                            <?php elseif (($step['place'] ?? '') === 'Ongoing'): ?>
                                <div class="mt-2 text-black border-l-4 border-black p-3 rounded text-sm flex items-start space-x-2">
                                    <svg class="w-5 h-5 mt-0.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-4H5l7-8v4h4l-7 8z" />
                                    </svg>
                                    <div>
                                        <strong>Your order is currently being coordinated from our China supplier.</strong><br>
                                        We're arranging pickup and booking for international shipment. Please allow some time while the supplier prepares your item.


                                    </div>
                                </div>
                            <?php elseif (($step['place'] ?? '') === 'Arrival'): ?>
                                <div class="mt-2 text-black border-l-4 border-black p-3 rounded text-sm flex items-start space-x-2">
                                    <svg class="w-5 h-5 mt-0.5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10l1.5 2L9 6"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16h16v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z" />
                                    </svg>
                                    <div>
                                        <strong>Your item has arrived in the Philippines.</strong><br>
                                        Items from China take <strong>1–2 months</strong> to arrive. Final delivery is being arranged.
                                        <?php if (!empty($order['formatted_eta'])): ?>
                                            <div class="mt-2 text-sm text-red-700">
                                                <strong>Estimated Arrival:</strong> <?= htmlspecialchars($order['formatted_eta']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif (($step['place'] ?? '') === 'Customs'): ?>
                                <div class="mt-2 text-black border-l-4 border-black p-3 rounded text-sm flex items-start space-x-2">
                                    <svg class="w-5 h-5 mt-0.5 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 16h6"></path>
                                        <circle cx="12" cy="12" r="9" stroke-width="2" fill="none"></circle>
                                    </svg>
                                    <div>
                                        <strong>Your item is undergoing customs clearance in the Philippines.</strong><br>
                                        Items imported from China are inspected by customs. <strong>This process may take days to weeks</strong> depending on volume and regulations. We'll update you once cleared.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>


        <!-- Footer Note -->
        <div class="mt-8 text-center text-sm text-gray-600">
            <p>For any questions or concerns, please contact our support team.</p>
            <p class="mt-1">Thank you for choosing our service!</p>
        </div>
    </div>

    <!-- Auto-refresh script -->
    <script>
        // Auto-refresh every 5 minutes to check for status updates
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes = 300,000 milliseconds

        // Add smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add click handler for contact support
        document.querySelector('button:first-of-type').addEventListener('click', function() {
            // You can customize this to open a modal, redirect to contact page, etc.
            alert('Contact Support: Please call our hotline or send us an email for assistance.');
        });
    </script>
</body>

</html>