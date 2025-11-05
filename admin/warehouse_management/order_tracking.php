<?php
// order_tracking.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: order_list.php");
    exit();
}

// Get order details
$orderSql = "SELECT * FROM orders WHERE id = ? LIMIT 1";
$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: order_list.php");
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $item_id = (int)$_POST['item_id'];
    $new_status = $_POST['tracking_status'];
    $item_type = $_POST['item_type'] ?? 'original';

    // Note: 'In Warehouse' status should be set via QR code scanning (scan_item.php)
    // It is not included in manual updates for security and tracking accuracy

    if ($item_type === 'replacement') {
        $replacement_id = (int)$_POST['replacement_id'];
        $validReplacementStatuses = ['approved', 'processing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'cancelled'];

        if (in_array($new_status, $validReplacementStatuses)) {
            $updateSql = "UPDATE replacement_requests SET status = ? WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("sii", $new_status, $replacement_id, $order_id);

            if ($updateStmt->execute()) {
                $success_message = "Replacement status updated successfully";
            } else {
                $error_message = "Failed to update replacement status";
            }
            $updateStmt->close();
        } else {
            $error_message = "Invalid replacement status";
        }
    } else {
        $itemCheckSql = "SELECT origin FROM order_items WHERE id = ? AND order_id = ? LIMIT 1";
        $itemCheckStmt = $conn->prepare($itemCheckSql);
        $itemCheckStmt->bind_param("ii", $item_id, $order_id);
        $itemCheckStmt->execute();
        $itemData = $itemCheckStmt->get_result()->fetch_assoc();
        $itemCheckStmt->close();

        if ($itemData) {
            $origin = $itemData['origin'];

            // Only allow these specific status updates
            $validStatuses = [];
            if ($origin === 'local') {
                $validStatuses = ['processing']; // Only processing can be manually updated
            } else {
                $validStatuses = ['processing', 'shipped_overseas', 'in_transit_international', 'customs_clearance']; // Added customs_clearance
            }

            if (in_array($new_status, $validStatuses)) {
                $updateSql = "UPDATE order_items SET tracking_status = ? WHERE id = ? AND order_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("sii", $new_status, $item_id, $order_id);

                if ($updateStmt->execute()) {
                    $success_message = "Status updated successfully";
                } else {
                    $error_message = "Failed to update status";
                }
                $updateStmt->close();
            } else {
                $error_message = "Invalid status for this item type";
            }
        } else {
            $error_message = "Item not found";
        }
    }
}

// Get order items with tracking information
$itemsSql = "
    SELECT 
        oi.id,
        oi.order_id,
        CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
        CAST(oi.codename AS CHAR) COLLATE utf8mb4_unicode_ci as codename,
        CAST(oi.type_name AS CHAR) COLLATE utf8mb4_unicode_ci as type_name,
        CAST(oi.variant_color AS CHAR) COLLATE utf8mb4_unicode_ci as variant_color,
        oi.price,
        oi.quantity,
        oi.subtotal,
        CAST(oi.size AS CHAR) COLLATE utf8mb4_unicode_ci as size,
        CAST(oi.descrip6 AS CHAR) COLLATE utf8mb4_unicode_ci as descrip6,
        CAST(oi.descrip7 AS CHAR) COLLATE utf8mb4_unicode_ci as descrip7,
        CAST(oi.origin AS CHAR) COLLATE utf8mb4_unicode_ci as origin,
        oi.supplier_id,
        CAST(oi.manual_supplier_name AS CHAR) COLLATE utf8mb4_unicode_ci as manual_supplier_name,
        oi.product_id,
        oi.delivery_fee_per_item,
        oi.item_total_delivery,
        CAST(COALESCE(sl.business_name, oi.manual_supplier_name) AS CHAR) COLLATE utf8mb4_unicode_ci as supplier_name,
        CAST(COALESCE(oi.tracking_status, 'processing') AS CHAR) COLLATE utf8mb4_unicode_ci as current_status,
        CAST('original' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
        NULL as replacement_id,
        CAST(NULL AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason,
        NULL as replacement_quantity
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
    WHERE oi.order_id = ?
    
    UNION ALL
    
SELECT 
    oi.id,
    oi.order_id,
    CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
    CAST(oi.codename AS CHAR) COLLATE utf8mb4_unicode_ci as codename,
    CAST(oi.type_name AS CHAR) COLLATE utf8mb4_unicode_ci as type_name,
    CAST(oi.variant_color AS CHAR) COLLATE utf8mb4_unicode_ci as variant_color,
    oi.price,
    rr.replacement_quantity as quantity,
    (rr.replacement_quantity * oi.price) as subtotal,
    CAST(oi.size AS CHAR) COLLATE utf8mb4_unicode_ci as size,
    CAST(oi.descrip6 AS CHAR) COLLATE utf8mb4_unicode_ci as descrip6,
    CAST(oi.descrip7 AS CHAR) COLLATE utf8mb4_unicode_ci as descrip7,
    CAST(oi.origin AS CHAR) COLLATE utf8mb4_unicode_ci as origin,
    oi.supplier_id,
    CAST(oi.manual_supplier_name AS CHAR) COLLATE utf8mb4_unicode_ci as manual_supplier_name,
    oi.product_id,
    oi.delivery_fee_per_item,
    oi.item_total_delivery,
    CAST(COALESCE(sl.business_name, oi.manual_supplier_name) AS CHAR) COLLATE utf8mb4_unicode_ci as supplier_name,
    CAST(COALESCE(rr.status, 'approved') AS CHAR) COLLATE utf8mb4_unicode_ci as current_status,
    CAST('replacement' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
    rr.id as replacement_id,
    CAST(rr.reason AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason,
    rr.replacement_quantity
FROM replacement_requests rr
LEFT JOIN order_items oi ON rr.order_item_id = oi.id
LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
WHERE rr.order_id = ? AND rr.status IN ('approved', 'processing', 'In Warehouse', 'scheduled', 'ready_for_pickup', 'out_for_delivery', 'delivered')
    
    ORDER BY origin, supplier_name, product_name, item_type
";

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("ii", $order_id, $order_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Group items by origin and supplier
$groupedItems = [
    'local' => [],
    'international' => []
];

foreach ($items as $item) {
    $origin = $item['origin'] ?? 'local';
    $supplier = $item['supplier_name'] ?? 'Unknown Supplier';

    if (!isset($groupedItems[$origin][$supplier])) {
        $groupedItems[$origin][$supplier] = [
            'original' => [],
            'replacement' => []
        ];
    }

    $itemType = $item['item_type'];
    $groupedItems[$origin][$supplier][$itemType][] = $item;
}

// Check if order has any scheduled deliveries
$hasScheduledDeliveries = false;
$hasScheduledReplacements = false;

// Check for any scheduled deliveries
$scheduledCheckSql = "SELECT COUNT(*) as scheduled_count FROM delivery_schedules WHERE order_id = ?";
$scheduledCheckStmt = $conn->prepare($scheduledCheckSql);
$scheduledCheckStmt->bind_param("i", $order_id);
$scheduledCheckStmt->execute();
$scheduledResult = $scheduledCheckStmt->get_result()->fetch_assoc();
$scheduledCheckStmt->close();

if ($scheduledResult['scheduled_count'] > 0) {
    $hasScheduledDeliveries = true;
}

// Check specifically for scheduled replacement deliveries
$scheduledReplCheckSql = "SELECT COUNT(*) as repl_scheduled FROM delivery_schedules WHERE order_id = ? AND item_type = 'replacement'";
$scheduledReplCheckStmt = $conn->prepare($scheduledReplCheckSql);
$scheduledReplCheckStmt->bind_param("i", $order_id);
$scheduledReplCheckStmt->execute();
$scheduledReplResult = $scheduledReplCheckStmt->get_result()->fetch_assoc();
$scheduledReplCheckStmt->close();

if ($scheduledReplResult['repl_scheduled'] > 0) {
    $hasScheduledReplacements = true;
}

$allItemsReadyForDelivery = true;
$totalItems = count($items);
$itemsReadyForDelivery = 0;

// Separate tracking for replacements
$allReplacementsReady = true;
$totalReplacements = 0;
$replacementsReadyForDelivery = 0;
$hasReplacements = false;

if ($totalItems > 0) {
    foreach ($items as $item) {
        $isReady = false;
        $itemType = $item['item_type'];
        $origin = $item['origin'] ?? 'local';
        $status = $item['current_status'];

        // Check if item meets delivery readiness criteria
        if ($itemType === 'replacement') {
            $hasReplacements = true;
            $totalReplacements++;
            
            // Replacement items: must be in "In Warehouse" status
            if ($status === 'In Warehouse') {
                $isReady = true;
                $replacementsReadyForDelivery++;
            } else {
                $allReplacementsReady = false;
            }
        } elseif ($origin === 'local') {
            // Local items: must be in "In Warehouse" status
            if ($status === 'In Warehouse') {
                $isReady = true;
            }
        } elseif ($origin === 'international') {
            // International items: must be in "In Warehouse" status
            if ($status === 'In Warehouse') {
                $isReady = true;
            }
        }

        if ($isReady) {
            $itemsReadyForDelivery++;
        } else {
            $allItemsReadyForDelivery = false;
        }
    }
} else {
    $allItemsReadyForDelivery = false;
}

// If no replacements exist, set allReplacementsReady to false
if ($totalReplacements === 0) {
    $allReplacementsReady = false;
}

// Status definitions
$statusDefinitions = [
    'local' => [
        'pending' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'pending', 'description' => 'Order confirmed and being prepared', 'progress' => 16],
        'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Order confirmed and being prepared', 'progress' => 16],
        'In Warehouse' => ['icon' => 'fa-warehouse', 'color' => 'indigo', 'label' => 'In Warehouse', 'description' => 'Item received and stored in warehouse', 'progress' => 33],
        'scheduled' => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled', 'description' => 'Delivery has been scheduled', 'progress' => 50],
        'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Item ready for local delivery', 'progress' => 66],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Courier delivering to customer', 'progress' => 83],
        'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Customer received the item', 'progress' => 100],
        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Returned', 'description' => 'Order cancelled or returned', 'progress' => 0]
    ],
    'international' => [
        'pending' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'pending', 'description' => 'Order confirmed, supplier preparing', 'progress' => 9],
        'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Order confirmed, supplier preparing', 'progress' => 9],
        'shipped_overseas' => ['icon' => 'fa-ship', 'color' => 'purple', 'label' => 'Shipped from Overseas', 'description' => 'Item has left the overseas supplier', 'progress' => 20],
        'in_transit_international' => ['icon' => 'fa-plane', 'color' => 'yellow', 'label' => 'In Transit (International)', 'description' => 'Item is on the way (by sea/air)', 'progress' => 32],
        'customs_clearance' => ['icon' => 'fa-file-signature', 'color' => 'orange', 'label' => 'Customs Clearance', 'description' => 'Item undergoing customs inspection', 'progress' => 44],
        'In Warehouse' => ['icon' => 'fa-warehouse', 'color' => 'indigo', 'label' => 'In Warehouse', 'description' => 'Item received and stored in local warehouse', 'progress' => 55],
        'scheduled' => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled', 'description' => 'Delivery has been scheduled', 'progress' => 66],
        'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Item ready for local delivery', 'progress' => 77],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Courier delivering to customer', 'progress' => 88],
        'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Customer received the item', 'progress' => 100],
        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Returned', 'description' => 'Order cancelled or returned', 'progress' => 0]
    ]
];

$replacementStatusDefinitions = [
    'approved' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Approved', 'description' => 'Replacement request has been approved', 'progress' => 14],
    'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Replacement being prepared', 'progress' => 28],
    'In Warehouse' => ['icon' => 'fa-warehouse', 'color' => 'indigo', 'label' => 'In Warehouse', 'description' => 'Replacement received and stored in warehouse', 'progress' => 42],
    'scheduled' => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled', 'description' => 'Replacement delivery scheduled', 'progress' => 57],
    'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Replacement ready for delivery', 'progress' => 71],
    'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Replacement being delivered', 'progress' => 85],
    'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Replacement delivered to customer', 'progress' => 100],
    'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Cancelled', 'description' => 'Replacement request cancelled', 'progress' => 0]
];

// Define selectable statuses (limited as per requirements)
$selectableStatuses = [
    'local' => ['pending','processing'], // Only processing can be manually updated (In Warehouse is auto-updated via QR scan)
    'international' => ['pending','processing', 'shipped_overseas', 'in_transit_international', 'customs_clearance'] // Can edit up to customs_clearance (In Warehouse is auto-updated via QR scan)
];

$selectableReplacementStatuses = ['approved', 'processing']; // Only these can be manually updated (In Warehouse is auto-updated via QR scan)
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Order #<?php echo $order_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .status-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .replacement-item {
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
            border-left: 4px solid #ef4444;
            position: relative;
        }

        .replacement-item::before {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            bottom: -2px;
            left: -2px;
            background: linear-gradient(45deg, #ef4444, #dc2626);
            border-radius: 12px;
            z-index: -1;
            opacity: 0.1;
        }

        .modal-backdrop {
            backdrop-filter: blur(8px);
        }

        .progress-animation {
            animation: progressFill 1s ease-out forwards;
        }

        @keyframes progressFill {
            from {
                width: 0%;
            }
        }

        .status-badge {
            background: linear-gradient(135deg, var(--bg-from), var(--bg-to));
            backdrop-filter: blur(10px);
        }

        .supplier-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .origin-header {
            background: linear-gradient(135deg, var(--gradient-from), var(--gradient-to));
        }

        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 40;
        }

        @media (max-width: 640px) {
            .floating-action {
                bottom: 1rem;
                right: 1rem;
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .8;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-white to-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header Section -->
    <div class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                    <div class="flex items-center space-x-4">
                        <a href="order_list.php" class="bg-gray-100 hover:bg-gray-200 p-3 rounded-xl transition-all duration-200 hover:scale-105">
                            <i class="fas fa-arrow-left text-gray-600"></i>
                        </a>
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4 rounded-xl shadow-lg">
                            <i class="fas fa-route text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Order Tracking</h1>
                            <p class="text-gray-600 mt-1">Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                        <div class="bg-gradient-to-r from-purple-100 to-purple-50 px-6 py-3 rounded-xl border border-purple-200">
                            <div class="text-center">
                                <span class="text-purple-700 font-semibold">Status</span>
                                <div class="text-purple-900 font-bold text-lg"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-r from-green-100 to-green-50 px-6 py-3 rounded-xl border border-green-200">
                            <div class="text-center">
                                <span class="text-green-700 font-semibold">Items</span>
                                <div class="text-green-900 font-bold text-lg"><?php echo count($items); ?></div>
                            </div>
                        </div>
                        <?php if (!$hasScheduledDeliveries): ?>
                            <div class="bg-gradient-to-r from-<?php echo $allItemsReadyForDelivery ? 'green' : 'blue'; ?>-100 to-<?php echo $allItemsReadyForDelivery ? 'green' : 'blue'; ?>-50 px-6 py-3 rounded-xl border border-<?php echo $allItemsReadyForDelivery ? 'green' : 'blue'; ?>-200">
                                <div class="text-center">
                                    <span class="text-<?php echo $allItemsReadyForDelivery ? 'green' : 'blue'; ?>-700 font-semibold">
                                        <i class="fas fa-warehouse mr-1"></i>Ready Items
                                    </span>
                                    <div class="text-<?php echo $allItemsReadyForDelivery ? 'green' : 'blue'; ?>-900 font-bold text-lg">
                                        <?php echo $itemsReadyForDelivery; ?> / <?php echo $totalItems; ?>
                                    </div>
                                    <?php if ($allItemsReadyForDelivery): ?>
                                        <div class="text-xs text-green-600 font-medium mt-1">
                                            <i class="fas fa-check-circle mr-1"></i>Ready to Schedule
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-gradient-to-r from-purple-100 to-purple-50 px-6 py-3 rounded-xl border border-purple-200">
                                <div class="text-center">
                                    <span class="text-purple-700 font-semibold">
                                        <i class="fas fa-calendar-check mr-1"></i>Delivery Status
                                    </span>
                                    <div class="text-purple-900 font-bold text-lg">Scheduled</div>
                                    <div class="text-xs text-purple-600 font-medium mt-1">
                                        <i class="fas fa-truck mr-1"></i>In Progress
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4 shadow-sm">
                <div class="flex items-center">
                    <div class="bg-green-100 rounded-full p-2 mr-3">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <span class="text-green-800 font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 shadow-sm">
                <div class="flex items-center">
                    <div class="bg-red-100 rounded-full p-2 mr-3">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                    </div>
                    <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
        <?php endif; ?>


        <!-- Delivery Readiness Banner -->
        <?php if ($totalItems > 0 && !$allItemsReadyForDelivery && !$hasScheduledDeliveries): ?>
            <div class="mb-6 bg-blue-50 border-2 border-blue-300 rounded-xl p-6 shadow-sm">
                <div class="flex items-start">
                    <div class="bg-blue-100 rounded-full p-3 mr-4">
                        <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-blue-900 font-bold text-lg mb-2">Waiting for Items to be Ready</h3>
                        <p class="text-blue-800 mb-3">
                            Delivery can be scheduled once all items reach the required status:
                        </p>
                        <ul class="text-sm text-blue-700 mb-3 space-y-1 ml-4">
                            <li><i class="fas fa-check-circle mr-2"></i><strong>Local items:</strong> In Warehouse</li>
                            <li><i class="fas fa-check-circle mr-2"></i><strong>International items:</strong> In Warehouse</li>
                            <li><i class="fas fa-check-circle mr-2"></i><strong>Replacements:</strong> In Warehouse</li>
                        </ul>
                        <div class="bg-white rounded-lg p-4 border border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-blue-900">Delivery Readiness</span>
                                <span class="text-sm font-bold text-blue-600"><?php echo $itemsReadyForDelivery; ?> of <?php echo $totalItems; ?> items ready</span>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-3 overflow-hidden">
                                <div class="bg-gradient-to-r from-blue-400 to-blue-600 h-3 rounded-full transition-all duration-500"
                                    style="width: <?php echo $totalItems > 0 ? round(($itemsReadyForDelivery / $totalItems) * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($allItemsReadyForDelivery && $totalItems > 0 && !$hasScheduledDeliveries): ?>
            <div class="mb-6 bg-green-50 border-2 border-green-300 rounded-xl p-6 shadow-sm">
                <div class="flex items-start">
                    <div class="bg-green-100 rounded-full p-3 mr-4 animate-pulse">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-green-900 font-bold text-lg mb-2">
                            <i class="fas fa-warehouse mr-2"></i>All Items Ready for Delivery!
                        </h3>
                        <p class="text-green-800 mb-3">
                            All items have been received and are now stored in the warehouse. You can now schedule the delivery.
                        </p>
                        <a href="delivery_schedule.php?order_id=<?php echo $order_id; ?>&schedule_all=true"
                            class="inline-flex items-center space-x-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 hover:scale-105 shadow-lg">
                            <i class="fas fa-calendar-check"></i>
                            <span>Schedule Delivery for All Items</span>
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($hasScheduledDeliveries && $totalItems > 0): ?>
            <?php
            // Get scheduled delivery details
            $scheduleDetailSql = "SELECT delivery_date, delivery_time, delivery_notes 
                          FROM delivery_schedules 
                          WHERE order_id = ? 
                          ORDER BY delivery_date, delivery_time 
                          LIMIT 1";
            $scheduleDetailStmt = $conn->prepare($scheduleDetailSql);
            $scheduleDetailStmt->bind_param("i", $order_id);
            $scheduleDetailStmt->execute();
            $scheduleDetail = $scheduleDetailStmt->get_result()->fetch_assoc();
            $scheduleDetailStmt->close();

            if ($scheduleDetail):
                $deliveryDateTime = new DateTime($scheduleDetail['delivery_date'] . ' ' . $scheduleDetail['delivery_time']);
                $formattedDate = $deliveryDateTime->format('l, F j, Y');
                $formattedTime = $deliveryDateTime->format('g:i A');
            ?>
                <div class="mb-6 bg-purple-50 border-2 border-purple-300 rounded-xl p-6 shadow-sm">
                    <div class="flex items-start">
                        <div class="bg-purple-100 rounded-full p-3 mr-4">
                            <i class="fas fa-calendar-check text-purple-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-purple-900 font-bold text-lg mb-2">
                                <i class="fas fa-truck mr-2"></i>Delivery Scheduled
                            </h3>
                            <p class="text-purple-800 mb-3">
                                This order has been scheduled for delivery. Items are being prepared for dispatch.
                            </p>
                            <div class="bg-white rounded-lg p-4 border border-purple-200">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center text-purple-700 mb-2">
                                            <i class="fas fa-calendar text-purple-600 mr-2"></i>
                                            <span class="text-sm font-medium">Scheduled Date</span>
                                        </div>
                                        <p class="text-purple-900 font-bold text-lg"><?php echo $formattedDate; ?></p>
                                    </div>
                                    <div>
                                        <div class="flex items-center text-purple-700 mb-2">
                                            <i class="fas fa-clock text-purple-600 mr-2"></i>
                                            <span class="text-sm font-medium">Scheduled Time</span>
                                        </div>
                                        <p class="text-purple-900 font-bold text-lg"><?php echo $formattedTime; ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($scheduleDetail['delivery_notes'])): ?>
                                    <div class="mt-4 pt-4 border-t border-purple-200">
                                        <div class="flex items-center text-purple-700 mb-2">
                                            <i class="fas fa-sticky-note text-purple-600 mr-2"></i>
                                            <span class="text-sm font-medium">Delivery Notes</span>
                                        </div>
                                        <p class="text-purple-800"><?php echo htmlspecialchars($scheduleDetail['delivery_notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Replacement Items Ready Banner -->
<?php if ($hasReplacements && $allReplacementsReady && !$hasScheduledReplacements): ?>
            <div class="mb-6 bg-red-50 border-2 border-red-300 rounded-xl p-6 shadow-sm">
                <div class="flex items-start">
                    <div class="bg-red-100 rounded-full p-3 mr-4 animate-pulse">
                        <i class="fas fa-sync-alt text-red-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-red-900 font-bold text-lg mb-2">
                            <i class="fas fa-warehouse mr-2"></i>All Replacement Items Ready!
                        </h3>
                        <p class="text-red-800 mb-3">
                            All <?php echo $totalReplacements; ?> replacement item(s) have been received and are now stored in the warehouse. You can schedule delivery for replacements separately.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="delivery_schedule.php?order_id=<?php echo $order_id; ?>&schedule_replacements=true"
                                class="inline-flex items-center space-x-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 hover:scale-105 shadow-lg">
                                <i class="fas fa-calendar-check"></i>
                                <span>Schedule Replacement Delivery (<?php echo $totalReplacements; ?> items)</span>
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($hasReplacements && !$allReplacementsReady && !$hasScheduledReplacements): ?>
            <div class="mb-6 bg-orange-50 border-2 border-orange-300 rounded-xl p-6 shadow-sm">
                <div class="flex items-start">
                    <div class="bg-orange-100 rounded-full p-3 mr-4">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-orange-900 font-bold text-lg mb-2">
                            Waiting for Replacement Items
                        </h3>
                        <p class="text-orange-800 mb-3">
                            Some replacement items are still being processed. Delivery can be scheduled once all replacements reach "In Warehouse" status.
                        </p>
                        <div class="bg-white rounded-lg p-4 border border-orange-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-orange-900">
                                    <i class="fas fa-sync-alt mr-2"></i>Replacement Progress
                                </span>
                                <span class="text-sm font-bold text-orange-600">
                                    <?php echo $replacementsReadyForDelivery; ?> of <?php echo $totalReplacements; ?> ready
                                </span>
                            </div>
                            <div class="w-full bg-orange-200 rounded-full h-3 overflow-hidden">
                                <div class="bg-gradient-to-r from-red-400 to-red-600 h-3 rounded-full transition-all duration-500"
                                    style="width: <?php echo $totalReplacements > 0 ? round(($replacementsReadyForDelivery / $totalReplacements) * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        function renderItemGroup($items, $origin, $supplier, $statusDefinitions, $selectableStatuses, $replacementStatusDefinitions, $selectableReplacementStatuses, $order_id)
        {
            if (empty($items['original']) && empty($items['replacement'])) return '';

            $originConfig = [
                'local' => [
                    'color' => 'green',
                    'icon' => 'fa-home',
                    'emoji' => '🏠',
                    'gradient' => 'from-green-500 to-green-600'
                ],
                'international' => [
                    'color' => 'blue',
                    'icon' => 'fa-globe',
                    'emoji' => '🌏',
                    'gradient' => 'from-blue-500 to-blue-600'
                ]
            ];

            $config = $originConfig[$origin];

            echo "<div class='supplier-card rounded-2xl shadow-lg border border-gray-200 mb-8 overflow-hidden'>";
            echo "<div class='origin-header p-6 bg-gradient-to-r {$config['gradient']} text-white'>";
            echo "<div class='flex items-center justify-between'>";
            echo "<div class='flex items-center space-x-4'>";
            echo "<div class='bg-white bg-opacity-20 p-3 rounded-xl'>";
            echo "<i class='fas fa-building text-2xl'></i>";
            echo "</div>";
            echo "<div>";
            echo "<h3 class='text-xl font-bold'>" . htmlspecialchars($supplier) . "</h3>";
            echo "<p class='text-white text-opacity-90'>{$config['emoji']} " . ucfirst($origin) . " Supplier</p>";
            echo "</div>";
            echo "</div>";
            echo "<div class='text-white text-opacity-90'>";
            echo "<span class='text-sm'>" . (count($items['original']) + count($items['replacement'])) . " items</span>";
            echo "</div>";
            echo "</div>";
            echo "</div>";

            echo "<div class='p-6'>";
            echo "<div class='space-y-4'>";

            // Render original items
            foreach ($items['original'] as $item) {
                renderSingleItem($item, $origin, $statusDefinitions, $selectableStatuses, $order_id, false);
            }

            // Render replacement items
            foreach ($items['replacement'] as $item) {
                renderSingleItem($item, $origin, $replacementStatusDefinitions, $selectableReplacementStatuses, $order_id, true);
            }

            echo "</div>";
            echo "</div>";
            echo "</div>";
        }

        function renderSingleItem($item, $origin, $statusDef, $selectableStatuses, $order_id, $isReplacement)
{
    global $conn; // Add this to access database connection
    
    $currentStatus = $item['current_status'];
    $statusInfo = $statusDef[$currentStatus] ?? ($isReplacement ? $statusDef['approved'] : $statusDef['processing']);

    $itemClass = $isReplacement ? 'replacement-item' : '';
    
    // Check for defect reports
    $defectCountSql = "SELECT COUNT(*) as defect_count FROM defect_reports WHERE order_item_id = ?";
    $defectCountStmt = $conn->prepare($defectCountSql);
    $defectCountStmt->bind_param("i", $item['id']);
    $defectCountStmt->execute();
    $defectCountResult = $defectCountStmt->get_result()->fetch_assoc();
    $defectCountStmt->close();
    $hasDefects = (int)$defectCountResult['defect_count'] > 0;

    echo "<div class='status-card bg-white rounded-xl p-6 border border-gray-200 cursor-pointer {$itemClass}' onclick=\"openProgressModal('{$item['id']}', '{$origin}', '{$currentStatus}', " . ($isReplacement ? 'true' : 'false') . ")\">";

    if ($isReplacement) {
        echo "<div class='mb-4 flex items-center justify-between'>";
        echo "<div class='flex items-center space-x-2'>";
        echo "<span class='bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-semibold'>";
        echo "<i class='fas fa-sync-alt mr-1'></i>REPLACEMENT ITEM";
        echo "</span>";
        echo "</div>";
        echo "<div class='text-sm text-gray-600'>";
        echo "<span class='font-medium'>Reason:</span> " . htmlspecialchars(ucfirst($item['replacement_reason']));
        echo "</div>";
        echo "</div>";
    }
    
    // ADD DEFECT WARNING
    if ($hasDefects) {
        echo "<div class='mb-4 bg-orange-50 border-l-4 border-orange-500 rounded p-3'>";
        echo "<div class='flex items-center space-x-2'>";
        echo "<i class='fas fa-exclamation-triangle text-orange-600 text-lg'></i>";
        echo "<span class='text-orange-800 font-semibold'>Defect Reported</span>";
        echo "<button onclick='event.stopPropagation(); viewItemDefects({$item['id']});' class='ml-auto text-orange-600 hover:text-orange-700 underline text-sm'>";
        echo "View Details";
        echo "</button>";
        echo "</div>";
        echo "</div>";
    }

    echo "<div class='flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0'>";
    echo "<div class='flex-1 min-w-0'>";
    echo "<h4 class='font-bold text-gray-900 text-lg mb-2'>" . htmlspecialchars($item['product_name']) . "</h4>";
    echo "<div class='flex flex-wrap items-center gap-4 text-sm text-gray-600'>";
    echo "<span class='flex items-center'><i class='fas fa-boxes mr-2'></i>Qty: <strong class='ml-1'>{$item['quantity']}</strong></span>";
    echo "<span class='flex items-center'><i class='fas fa-peso-sign mr-2'></i>Price: <strong class='ml-1'>" . number_format((float)$item['price'], 2) . "</strong></span>";
    if ($item['codename']) {
        echo "<span class='flex items-center'><i class='fas fa-tag mr-2'></i>Code: <strong class='ml-1'>" . htmlspecialchars($item['codename']) . "</strong></span>";
    }
    echo "</div>";
    echo "</div>";

    echo "<div class='flex flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-4' onclick='event.stopPropagation();'>";

    // Status badge
    echo "<div class='text-center'>";
    echo "<div class='status-badge inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold mb-2' style='--bg-from: rgb(var(--{$statusInfo['color']}-100)); --bg-to: rgb(var(--{$statusInfo['color']}-50));'>";
    echo "<i class='fas {$statusInfo['icon']} mr-2 text-{$statusInfo['color']}-600'></i>";
    echo "<span class='text-{$statusInfo['color']}-800'>{$statusInfo['label']}</span>";
    echo "</div>";
    echo "<div class='text-xs text-gray-600'>{$statusInfo['description']}</div>";
    echo "</div>";

    echo "<div class='flex flex-col space-y-2'>";

    // Status update form
    $canUpdate = false;
    if ($isReplacement) {
        global $selectableReplacementStatuses;
        $canUpdate = in_array($currentStatus, $selectableReplacementStatuses);
        $statusOptionsToUse = $selectableReplacementStatuses;
    } else {
        $canUpdate = in_array($currentStatus, $selectableStatuses);
        $statusOptionsToUse = $selectableStatuses;
    }

    if ($canUpdate) {
        echo "<form method='POST' class='flex items-center space-x-2'>";
        echo "<input type='hidden' name='item_id' value='{$item['id']}'>";
        if ($isReplacement) {
            echo "<input type='hidden' name='item_type' value='replacement'>";
            echo "<input type='hidden' name='replacement_id' value='{$item['replacement_id']}'>";
        }
        echo "<select name='tracking_status' class='px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500'>";

        foreach ($statusOptionsToUse as $status) {
            $info = $statusDef[$status];
            $selected = ($currentStatus === $status) ? 'selected' : '';
            echo "<option value='{$status}' {$selected}>{$info['label']}</option>";
        }
        echo "</select>";
        echo "<button type='submit' name='update_status' class='bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2 hover:scale-105'>";
        echo "<i class='fas fa-sync-alt'></i>";
        echo "<span>Update</span>";
        echo "</button>";
        echo "</form>";
    }
    
    // ADD RESOLVE DEFECT BUTTON (only for items with unresolved defects)
if (!$isReplacement && $hasDefects && $currentStatus !== 'cancelled') {
    // Check defect status
    $defectStatusSql = "SELECT status FROM defect_reports WHERE order_item_id = ? AND status != 'resolved' LIMIT 1";
    $defectStatusStmt = $conn->prepare($defectStatusSql);
    $defectStatusStmt->bind_param("i", $item['id']);
    $defectStatusStmt->execute();
    $defectStatusResult = $defectStatusStmt->get_result()->fetch_assoc();
    $defectStatusStmt->close();
    
    if ($defectStatusResult) {
        echo "<button onclick='event.stopPropagation(); resolveDefect({$item['id']}, {$order_id});' class='bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2'>";
        echo "<i class='fas fa-check-circle'></i>";
        echo "<span>Mark Defect as Resolved</span>";
        echo "</button>";
    }
}

    echo "</div>";
    
    // ADD P.O. NUMBER GENERATION FOR REPLACEMENTS
    if ($isReplacement && in_array($currentStatus, ['approved', 'processing'])) {
        // Check if replacement has P.O. number
        $replPoCheckSql = "SELECT po_number FROM replacement_requests WHERE id = ?";
        $replPoCheckStmt = $conn->prepare($replPoCheckSql);
        $replPoCheckStmt->bind_param("i", $item['replacement_id']);
        $replPoCheckStmt->execute();
        $replPoResult = $replPoCheckStmt->get_result()->fetch_assoc();
        $replPoCheckStmt->close();
        
        if (empty($replPoResult['po_number'])) {
            echo "<button onclick='generateReplacementPO({$item['replacement_id']})' class='w-full bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center justify-center space-x-2 mt-2'>";
            echo "<i class='fas fa-file-invoice'></i>";
            echo "<span>Generate P.O. Number</span>";
            echo "</button>";
        } else {
            echo "<div class='bg-green-50 border-2 border-green-200 rounded-lg p-3 text-center mt-2'>";
            echo "<div class='text-xs text-green-700 mb-1 font-semibold'>P.O. Number Generated</div>";
            echo "<div class='font-mono font-bold text-green-900 text-sm mb-2'>" . htmlspecialchars($replPoResult['po_number']) . "</div>";
            echo "<a href='view_po_items.php?po_number=" . urlencode($replPoResult['po_number']) . "' class='inline-flex items-center space-x-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-medium transition-colors duration-200'>";
            echo "<i class='fas fa-qrcode'></i>";
            echo "<span>Generate QR Code</span>";
            echo "</a>";
            echo "</div>";
        }
    }
    
    echo "</div>";
    echo "</div>";
    echo "</div>";
}
        ?>

        <!-- Local Products Section -->
        <?php if (!empty($groupedItems['local'])): ?>
            <div class="mb-12">
                <div class="flex items-center mb-8">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 p-4 rounded-2xl mr-4 shadow-lg">
                        <i class="fas fa-home text-white text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">🏠 Local Products</h2>
                        <p class="text-green-600 font-medium">Faster fulfillment times</p>
                    </div>
                </div>

                <?php foreach ($groupedItems['local'] as $supplier => $supplierItems): ?>
                    <?php renderItemGroup($supplierItems, 'local', $supplier, $statusDefinitions['local'], $selectableStatuses['local'], $replacementStatusDefinitions, $selectableReplacementStatuses, $order_id); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- International Products Section -->
        <?php if (!empty($groupedItems['international'])): ?>
            <div class="mb-12">
                <div class="flex items-center mb-8">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4 rounded-2xl mr-4 shadow-lg">
                        <i class="fas fa-globe text-white text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">🌏 International Products</h2>
                        <p class="text-blue-600 font-medium">Extended shipping times</p>
                    </div>
                </div>

                <?php foreach ($groupedItems['international'] as $supplier => $supplierItems): ?>
                    <?php renderItemGroup($supplierItems, 'international', $supplier, $statusDefinitions['international'], $selectableStatuses['international'], $replacementStatusDefinitions, $selectableReplacementStatuses, $order_id); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- No Items Found -->
        <?php if (empty($groupedItems['local']) && empty($groupedItems['international'])): ?>
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-12 text-center">
                <div class="text-gray-500">
                    <div class="bg-gray-100 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-box-open text-4xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">No Items Found</h3>
                    <p class="text-gray-600 mb-6">No items found for this order.</p>
                    <a href="order_list.php" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 inline-flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Orders</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Status Legend -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                Status Tracking Guide
            </h3>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Local Products Legend -->
                <div class="bg-green-50 rounded-xl p-6 border border-green-200">
                    <h4 class="font-bold text-green-700 mb-4 flex items-center">
                        <i class="fas fa-home mr-2"></i>
                        🏠 Local Products
                    </h4>
                    <div class="space-y-3">
                        <?php foreach ($statusDefinitions['local'] as $status => $info): ?>
                            <div class="flex items-center space-x-3">
                                <div class="bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-800 px-3 py-1 rounded-full text-sm font-medium min-w-max">
                                    <i class="fas <?php echo $info['icon']; ?> mr-1"></i>
                                    <span><?php echo $info['label']; ?></span>
                                </div>
                                <span class="text-sm text-gray-600"><?php echo $info['description']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- International Products Legend -->
                <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
                    <h4 class="font-bold text-blue-700 mb-4 flex items-center">
                        <i class="fas fa-globe mr-2"></i>
                        🌏 International Products
                    </h4>
                    <div class="space-y-3">
                        <?php foreach ($statusDefinitions['international'] as $status => $info): ?>
                            <div class="flex items-center space-x-3">
                                <div class="bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-800 px-3 py-1 rounded-full text-sm font-medium min-w-max">
                                    <i class="fas <?php echo $info['icon']; ?> mr-1"></i>
                                    <span><?php echo $info['label']; ?></span>
                                </div>
                                <span class="text-sm text-gray-600"><?php echo $info['description']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Replacement Items Legend -->
                <div class="bg-red-50 rounded-xl p-6 border border-red-200">
                    <h4 class="font-bold text-red-700 mb-4 flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i>
                        🔄 Replacement Items
                    </h4>
                    <div class="space-y-3">
                        <?php foreach ($replacementStatusDefinitions as $status => $info): ?>
                            <div class="flex items-center space-x-3">
                                <div class="bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-800 px-3 py-1 rounded-full text-sm font-medium min-w-max">
                                    <i class="fas <?php echo $info['icon']; ?> mr-1"></i>
                                    <span><?php echo $info['label']; ?></span>
                                </div>
                                <span class="text-sm text-gray-600"><?php echo $info['description']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Buttons -->
    <div class="floating-action flex flex-col space-y-3">
        <?php if ($allItemsReadyForDelivery && $totalItems > 0 && !$hasScheduledDeliveries): ?>
            <!-- Schedule All Delivery Button - Only shows when all items are In Warehouse -->
            <a href="delivery_schedule.php?order_id=<?php echo $order_id; ?>&schedule_all=true"
                class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-4 rounded-full shadow-xl hover:shadow-2xl transition-all duration-200 flex items-center space-x-3 hover:scale-110 animate-pulse">
                <i class="fas fa-calendar-check text-xl"></i>
                <span class="font-bold">Schedule All Delivery</span>
            </a>
        <?php endif; ?>

        <!-- Back Button -->
        <a href="order_list.php" class="bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white p-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-200 flex items-center space-x-2 hover:scale-110">
            <i class="fas fa-list"></i>
            <span class="hidden sm:inline">All Orders</span>
        </a>
    </div>

    <!-- Progress Modal -->
    <div id="progressModal" class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-route mr-3 text-blue-600"></i>
                    Tracking Progress
                </h3>
                <button onclick="closeProgressModal()" class="text-gray-500 hover:text-gray-700 p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-6">
                <div id="progressContent">
                    <!-- Progress content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Status definitions for JavaScript
        const statusDefinitions = <?php echo json_encode($statusDefinitions); ?>;
        const replacementStatusDefinitions = <?php echo json_encode($replacementStatusDefinitions); ?>;

        function openProgressModal(itemId, origin, currentStatus, isReplacement = false) {
            const modal = document.getElementById('progressModal');
            const content = document.getElementById('progressContent');

            // Generate progress content
            const statuses = isReplacement ? replacementStatusDefinitions : statusDefinitions[origin];
            const statusKeys = Object.keys(statuses);
            const currentIndex = statusKeys.indexOf(currentStatus);

            let progressHTML = `
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h4 class="text-lg font-bold text-gray-900">
                            ${isReplacement ? 'Replacement' : 'Item'} Progress Timeline
                        </h4>
                        ${isReplacement ? '<span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-semibold flex items-center"><i class="fas fa-sync-alt mr-1"></i>REPLACEMENT</span>' : ''}
                    </div>
                    <div class="relative pl-12">
                        <div class="absolute left-6 top-0 bottom-0 w-1 bg-gray-200 rounded-full"></div>
                        <div class="absolute left-6 top-0 w-1 ${isReplacement ? 'bg-red-500' : 'bg-blue-500'} rounded-full transition-all duration-1500 progress-animation" style="height: ${((currentIndex + 1) / statusKeys.length) * 100}%;"></div>
            `;

            statusKeys.forEach((status, index) => {
                const statusInfo = statuses[status];
                const isActive = index <= currentIndex;
                const isCurrent = status === currentStatus;

                progressHTML += `
                    <div class="relative flex items-start mb-8 last:mb-0">
                        <div class="absolute -left-8 flex-shrink-0 w-8 h-8 rounded-full border-4 ${isActive ? `${isReplacement ? 'bg-red-500 border-red-500' : 'bg-blue-500 border-blue-500'}` : 'bg-white border-gray-300'} flex items-center justify-center z-10 shadow-lg transition-all duration-300">
                            <i class="fas ${statusInfo.icon} ${isActive ? 'text-white' : 'text-gray-400'} text-sm"></i>
                        </div>
                        <div class="ml-6 flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="text-base font-bold ${isActive ? 'text-gray-900' : 'text-gray-500'}">${statusInfo.label}</h5>
                                <span class="text-sm font-medium ${isActive ? (isReplacement ? 'text-red-600' : 'text-blue-600') : 'text-gray-400'}">${statusInfo.progress}%</span>
                            </div>
                            <p class="text-sm ${isActive ? 'text-gray-600' : 'text-gray-400'} mb-3">${statusInfo.description}</p>
                            ${isCurrent ? `<span class="inline-flex items-center ${isReplacement ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'} text-xs font-bold px-3 py-1 rounded-full"><i class="fas fa-map-marker-alt mr-1"></i>Current Status</span>` : ''}
                        </div>
                    </div>
                `;
            });

            progressHTML += `
                    </div>
                </div>
                <div class="bg-gradient-to-r ${isReplacement ? 'from-red-50 to-red-100' : 'from-blue-50 to-blue-100'} rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-base font-bold text-gray-900">Overall Progress</span>
                        <span class="text-xl font-bold ${isReplacement ? 'text-red-600' : 'text-blue-600'}">${statuses[currentStatus].progress}%</span>
                    </div>
                    <div class="w-full bg-gray-300 rounded-full h-4 overflow-hidden">
                        <div class="bg-gradient-to-r ${isReplacement ? 'from-red-400 to-red-500' : 'from-blue-400 to-blue-500'} h-4 rounded-full transition-all duration-1500 progress-animation shadow-inner" style="width: ${statuses[currentStatus].progress}%"></div>
                    </div>
                </div>
            `;

            content.innerHTML = progressHTML;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeProgressModal() {
            const modal = document.getElementById('progressModal');
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Close modal when clicking outside
        document.getElementById('progressModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProgressModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProgressModal();
            }
        });

        // Form submission handling
        document.querySelectorAll('select[name="tracking_status"]').forEach(select => {
            select.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = this.closest('form');
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.click();
                    }
                }
            });
        });

        // Defect viewing functions
        function viewItemDefects(itemId) {
            fetch(`report_defect.php?item_id=${itemId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.defects) {
                        showDefectsModal(data.defects);
                    } else {
                        alert('Failed to load defect reports');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load defect reports');
                });
        }

        function showDefectsModal(defects) {
            let modalHTML = `
                <div id="defectsViewModal" class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                        <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-orange-50 to-red-50">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-3 text-orange-600"></i>
                                Defect Reports (${defects.length})
                            </h3>
                            <button onclick="closeDefectsModal()" class="text-gray-500 hover:text-gray-700 p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6">
                            <div class="space-y-4">
            `;

            defects.forEach((defect, index) => {
                const severityColors = {
                    'minor': 'yellow',
                    'moderate': 'orange',
                    'severe': 'red'
                };
                const color = severityColors[defect.severity] || 'gray';
                const statusColors = {
                    'pending': 'yellow',
                    'acknowledged': 'blue',
                    'replacement_requested': 'purple',
                    'resolved': 'green'
                };
                const statusColor = statusColors[defect.status] || 'gray';

                modalHTML += `
                    <div class="bg-${color}-50 border-2 border-${color}-200 rounded-xl p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-2">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-${color}-100 text-${color}-800">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        ${defect.severity.toUpperCase()}
                                    </span>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-${statusColor}-100 text-${statusColor}-800">
                                        ${defect.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </div>
                                <h4 class="font-bold text-${color}-900 text-lg">${defect.defect_type}</h4>
                            </div>
                            <div class="text-right text-sm text-gray-600">
                                <div><i class="fas fa-boxes mr-1"></i>Qty: <strong>${defect.quantity_defective}</strong></div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg p-3 mb-3">
                            <div class="text-sm text-gray-700">${defect.defect_description}</div>
                        </div>
                        
                        <div class="flex items-center justify-between text-xs text-gray-600">
                            <div>
                                <i class="fas fa-user mr-1"></i>
                                Reported by <strong>${defect.reporter_name}</strong>
                            </div>
                            <div>
                                <i class="fas fa-calendar mr-1"></i>
                                ${new Date(defect.reported_at).toLocaleString()}
                            </div>
                        </div>
                    </div>
                `;
            });

            modalHTML += `
                            </div>
                        </div>
                        <div class="p-6 border-t border-gray-200 bg-gray-50">
                            <button onclick="closeDefectsModal()" 
                                class="w-full bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            const existingModal = document.getElementById('defectsViewModal');
            if (existingModal) existingModal.remove();

            // Add new modal
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            document.body.classList.add('overflow-hidden');

            // Close on outside click
            document.getElementById('defectsViewModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDefectsModal();
                }
            });
        }

        function closeDefectsModal() {
            const modal = document.getElementById('defectsViewModal');
            if (modal) {
                modal.remove();
                document.body.classList.remove('overflow-hidden');
            }
        }

        // Handle escape key for all modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDefectsModal();
            }
        });

        // ADD THIS NEW FUNCTION
        function generateReplacementPO(replacementId) {
            if (!confirm('Generate P.O. number for this replacement item?\n\nThis will allow you to generate QR codes and track this replacement in the warehouse.')) {
                return;
            }
            
            fetch('generate_replacement_po.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    replacement_id: replacementId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ P.O. Number generated successfully!\n\nP.O. Number: ' + data.po_number + '\n\nYou can now search for this P.O. number in "View P.O. Items" to generate QR codes.');
                    window.location.reload();
                } else {
                    alert('✗ Failed to generate P.O. number: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('✗ Failed to generate P.O. number. Please try again.');
            });
        }

        // Resolve Defect Function
function resolveDefect(itemId, orderId) {
    if (!confirm('Mark this defect as resolved?\n\nThis will allow the item to be marked as "In Warehouse" when scanned.')) {
        return;
    }

    fetch('resolve_defect.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_item_id: itemId,
            order_id: orderId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Defect marked as resolved!\n\nThe item can now be marked as "In Warehouse" when scanned.');
            window.location.reload();
        } else {
            alert('✗ Failed to resolve defect: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('✗ Failed to resolve defect. Please try again.');
    });
}
    </script>
</body>

</html>