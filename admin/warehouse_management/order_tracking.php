<?php
// order_tracking.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role([ 'superadmin', 'warehouse']);

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
    WHERE rr.order_id = ? AND rr.status IN ('approved', 'processing', 'ready_for_pickup', 'out_for_delivery', 'delivered')
    
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

// Status definitions
$statusDefinitions = [
    'local' => [
        'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Order confirmed and being prepared', 'progress' => 25],
        'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Item ready for local delivery', 'progress' => 50],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Courier delivering to customer', 'progress' => 75],
        'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Customer received the item', 'progress' => 100],
        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Returned', 'description' => 'Order cancelled or returned', 'progress' => 0]
    ],
    'international' => [
        'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Order confirmed, supplier preparing', 'progress' => 15],
        'shipped_overseas' => ['icon' => 'fa-ship', 'color' => 'purple', 'label' => 'Shipped from Overseas', 'description' => 'Item has left the overseas supplier', 'progress' => 30],
        'in_transit_international' => ['icon' => 'fa-plane', 'color' => 'yellow', 'label' => 'In Transit (International)', 'description' => 'Item is on the way (by sea/air)', 'progress' => 45],
        'customs_clearance' => ['icon' => 'fa-file-signature', 'color' => 'orange', 'label' => 'Customs Clearance', 'description' => 'Item undergoing customs inspection', 'progress' => 60],
        'in_local_warehouse' => ['icon' => 'fa-warehouse', 'color' => 'teal', 'label' => 'In Local Warehouse', 'description' => 'Item arrived and ready for dispatch', 'progress' => 75],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Courier delivering to customer', 'progress' => 90],
        'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Customer received the item', 'progress' => 100],
        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Returned', 'description' => 'Order cancelled or returned', 'progress' => 0]
    ]
];

$replacementStatusDefinitions = [
    'approved' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Approved', 'description' => 'Replacement request has been approved', 'progress' => 20],
    'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Replacement being prepared', 'progress' => 40],
    'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Replacement ready for delivery', 'progress' => 60],
    'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Replacement being delivered', 'progress' => 80],
    'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Replacement delivered to customer', 'progress' => 100],
    'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Cancelled', 'description' => 'Replacement request cancelled', 'progress' => 0]
];

// Define selectable statuses (limited as per requirements)
$selectableStatuses = [
    'local' => ['processing'], // Only processing can be manually updated
    'international' => ['processing', 'shipped_overseas', 'in_transit_international', 'customs_clearance'] // Added customs_clearance
];

$selectableReplacementStatuses = ['approved', 'processing']; // Only these can be manually updated
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
            from { width: 0%; }
        }
        
        .status-badge {
            background: linear-gradient(135deg, var(--bg-from), var(--bg-to));
            backdrop-filter: blur(10px);
        }
        
        .supplier-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.3);
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

        <?php
        function renderItemGroup($items, $origin, $supplier, $statusDefinitions, $selectableStatuses, $replacementStatusDefinitions, $selectableReplacementStatuses, $order_id) {
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
        
        function renderSingleItem($item, $origin, $statusDef, $selectableStatuses, $order_id, $isReplacement) {
            $currentStatus = $item['current_status'];
            $statusInfo = $statusDef[$currentStatus] ?? ($isReplacement ? $statusDef['approved'] : $statusDef['processing']);
            
            $itemClass = $isReplacement ? 'replacement-item' : '';
            
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
            
            // Schedule button logic based on requirements
            $canSchedule = false;
            if ($isReplacement) {
                // For replacements: only show schedule button when status is 'processing'
                $canSchedule = ($currentStatus === 'processing');
            } else {
                // For original items:
                // Local: show when status is 'processing'  
                // International: show only when status is 'customs_clearance'
                if ($origin === 'local') {
                    $canSchedule = ($currentStatus === 'processing');
                } else {
                    // International items: only show schedule button for customs_clearance
                    $canSchedule = ($currentStatus === 'customs_clearance');
                }
            }
            
            if ($canSchedule) {
                $scheduleOrigin = $isReplacement ? 'replacement' : $origin;
                echo "<a href='delivery_schedule.php?order_id={$order_id}&item_id={$item['id']}&origin={$scheduleOrigin}" . ($isReplacement ? "&replacement_id={$item['replacement_id']}" : "") . "'";
                echo " class='bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center justify-center space-x-2 hover:scale-105'>";
                echo "<i class='fas fa-calendar-alt'></i>";
                echo "<span>Schedule Delivery</span>";
                echo "</a>";
            }
            
            // Status update form - limited based on requirements
            $canUpdate = false;
            if ($isReplacement) {
                // For replacements: only allow updates for 'approved' and 'processing'
                global $selectableReplacementStatuses;
                $canUpdate = in_array($currentStatus, $selectableReplacementStatuses);
                $statusOptionsToUse = $selectableReplacementStatuses;
            } else {
                // For original items: limited status updates only
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
            
            echo "</div>";
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

    <!-- Floating Back Button -->
    <div class="floating-action">
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
    </script>
</body>
</html>