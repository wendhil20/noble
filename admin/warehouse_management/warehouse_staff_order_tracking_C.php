<?php
// warehouse_staff_order_tracking_C.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: warehouse_staff_management_main.php");
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
    header("Location: warehouse_staff_management_main.php");
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $item_id = (int) $_POST['item_id'];
    $new_status = $_POST['tracking_status'];
    $item_type = $_POST['item_type'] ?? 'original';

    if ($item_type === 'replacement') {
        $replacement_id = (int) $_POST['replacement_id'];
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
            $validStatuses = [];
            if ($origin === 'local') {
                $validStatuses = ['processing'];
            } else {
                $validStatuses = ['processing', 'shipped_overseas', 'in_transit_international', 'customs_clearance'];
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

// Get order items
$itemsSql = "
    SELECT 
        oi.id, oi.order_id, oi.product_name, oi.codename, oi.type_name, oi.variant_color,
        oi.price, oi.quantity, oi.subtotal, oi.size, oi.descrip6, oi.descrip7, oi.origin,
        oi.supplier_id, oi.manual_supplier_name, oi.product_id, oi.delivery_fee_per_item, oi.item_total_delivery,
        COALESCE(sl.business_name, oi.manual_supplier_name) as supplier_name,
        COALESCE(oi.tracking_status, 'processing') as current_status,
        'original' as item_type, NULL as replacement_id, NULL as replacement_reason, NULL as replacement_quantity
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
    WHERE oi.order_id = ?
    UNION ALL
    SELECT 
        oi.id, oi.order_id, oi.product_name, oi.codename, oi.type_name, oi.variant_color,
        oi.price, rr.replacement_quantity as quantity, (rr.replacement_quantity * oi.price) as subtotal,
        oi.size, oi.descrip6, oi.descrip7, oi.origin, oi.supplier_id, oi.manual_supplier_name,
        oi.product_id, oi.delivery_fee_per_item, oi.item_total_delivery,
        COALESCE(sl.business_name, oi.manual_supplier_name) as supplier_name,
        COALESCE(rr.status, 'approved') as current_status,
        'replacement' as item_type, rr.id as replacement_id, rr.reason as replacement_reason, rr.replacement_quantity
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

// Group items
$groupedItems = ['local' => [], 'international' => []];
foreach ($items as $item) {
    $origin = $item['origin'] ?? 'local';
    $supplier = $item['supplier_name'] ?? 'Unknown Supplier';
    if (!isset($groupedItems[$origin][$supplier])) {
        $groupedItems[$origin][$supplier] = ['original' => [], 'replacement' => []];
    }
    $groupedItems[$origin][$supplier][$item['item_type']][] = $item;
}

// Scheduled delivery checks
$hasScheduledDeliveries = false;
$hasScheduledReplacements = false;

$scheduledCheckStmt = $conn->prepare("SELECT COUNT(*) as c FROM delivery_schedules WHERE order_id = ?");
$scheduledCheckStmt->bind_param("i", $order_id);
$scheduledCheckStmt->execute();
if ($scheduledCheckStmt->get_result()->fetch_assoc()['c'] > 0)
    $hasScheduledDeliveries = true;
$scheduledCheckStmt->close();

$scheduledReplStmt = $conn->prepare("SELECT COUNT(*) as c FROM delivery_schedules WHERE order_id = ? AND item_type = 'replacement'");
$scheduledReplStmt->bind_param("i", $order_id);
$scheduledReplStmt->execute();
if ($scheduledReplStmt->get_result()->fetch_assoc()['c'] > 0)
    $hasScheduledReplacements = true;
$scheduledReplStmt->close();

// Readiness tracking
$allItemsReadyForDelivery = true;
$totalItems = count($items);
$itemsReadyForDelivery = 0;
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

        if ($itemType === 'replacement') {
            $hasReplacements = true;
            $totalReplacements++;
            if ($status === 'In Warehouse') {
                $isReady = true;
                $replacementsReadyForDelivery++;
            } else {
                $allReplacementsReady = false;
            }
        } else {
            if ($status === 'In Warehouse')
                $isReady = true;
        }

        if ($isReady)
            $itemsReadyForDelivery++;
        else
            $allItemsReadyForDelivery = false;
    }
} else {
    $allItemsReadyForDelivery = false;
}

if ($totalReplacements === 0)
    $allReplacementsReady = false;

// Status definitions
$statusDefinitions = [
    'local' => [
        'pending' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Pending', 'description' => 'Order confirmed and being prepared', 'progress' => 16],
        'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Order confirmed and being prepared', 'progress' => 16],
        'In Warehouse' => ['icon' => 'fa-warehouse', 'color' => 'indigo', 'label' => 'In Warehouse', 'description' => 'Item received and stored in warehouse', 'progress' => 33],
        'scheduled' => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled', 'description' => 'Delivery has been scheduled', 'progress' => 50],
        'item_is_loaded' => ['icon' => 'fa-box-open', 'color' => 'teal', 'label' => 'Item Loaded', 'description' => 'Item loaded onto delivery vehicle', 'progress' => 58],
        'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Item ready for local delivery', 'progress' => 66],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Courier delivering to customer', 'progress' => 83],
        'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Customer received the item', 'progress' => 100],
        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Returned', 'description' => 'Order cancelled or returned', 'progress' => 0],
    ],
    'international' => [
        'pending' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Pending', 'description' => 'Order confirmed, supplier preparing', 'progress' => 9],
        'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Order confirmed, supplier preparing', 'progress' => 9],
        'shipped_overseas' => ['icon' => 'fa-ship', 'color' => 'purple', 'label' => 'Shipped from Overseas', 'description' => 'Item has left the overseas supplier', 'progress' => 20],
        'in_transit_international' => ['icon' => 'fa-plane', 'color' => 'yellow', 'label' => 'In Transit (International)', 'description' => 'Item is on the way (by sea/air)', 'progress' => 32],
        'customs_clearance' => ['icon' => 'fa-file-signature', 'color' => 'orange', 'label' => 'Customs Clearance', 'description' => 'Item undergoing customs inspection', 'progress' => 44],
        'In Warehouse' => ['icon' => 'fa-warehouse', 'color' => 'indigo', 'label' => 'In Warehouse', 'description' => 'Item received and stored in local warehouse', 'progress' => 55],
        'scheduled' => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled', 'description' => 'Delivery has been scheduled', 'progress' => 66],
        'item_is_loaded' => ['icon' => 'fa-box-open', 'color' => 'teal', 'label' => 'Item Loaded', 'description' => 'Item loaded onto delivery vehicle', 'progress' => 71],
        'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Item ready for local delivery', 'progress' => 77],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Courier delivering to customer', 'progress' => 88],
        'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Customer received the item', 'progress' => 100],
        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Returned', 'description' => 'Order cancelled or returned', 'progress' => 0],
    ],
];

$replacementStatusDefinitions = [
    'approved' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Approved', 'description' => 'Replacement request has been approved', 'progress' => 14],
    'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Replacement being prepared', 'progress' => 28],
    'In Warehouse' => ['icon' => 'fa-warehouse', 'color' => 'indigo', 'label' => 'In Warehouse', 'description' => 'Replacement received and stored in warehouse', 'progress' => 42],
    'scheduled' => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled', 'description' => 'Replacement delivery scheduled', 'progress' => 57],
    'item_is_loaded' => ['icon' => 'fa-box-open', 'color' => 'teal', 'label' => 'Item Loaded', 'description' => 'Replacement loaded onto delivery vehicle', 'progress' => 64],
    'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Replacement ready for delivery', 'progress' => 71],
    'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Replacement being delivered', 'progress' => 85],
    'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Replacement delivered to customer', 'progress' => 100],
    'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Cancelled', 'description' => 'Replacement request cancelled', 'progress' => 0],
];

$selectableStatuses = [
    'local' => ['pending', 'processing'],
    'international' => ['pending', 'processing', 'shipped_overseas', 'in_transit_international', 'customs_clearance'],
];
$selectableReplacementStatuses = ['approved', 'processing'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Order #<?php echo $order_id; ?></title>
    <style>
        .status-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, .1), 0 4px 6px -2px rgba(0, 0, 0, .05);
        }

        .replacement-item {
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
            opacity: .1;
        }

        .status-badge {
            background: linear-gradient(135deg, var(--bg-from), var(--bg-to));
            backdrop-filter: blur(10px);
        }

        .supplier-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, .9) 0%, rgba(255, 255, 255, .6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, .3);
        }

        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 40;
        }

        @media (max-width:640px) {
            .floating-action {
                bottom: 1rem;
                right: 1rem;
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .8
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(.4, 0, .6, 1) infinite;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- ── Header ──────────────────────────────────────────────────── -->
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

                <div class="flex items-center gap-3">
                    <a href="warehouse_staff_management_main.php"
                        class="w-8 h-8 rounded-lg border border-gray-100 bg-white hover:bg-gray-50 flex items-center justify-center transition-colors">
                        <i class="fas fa-arrow-left text-gray-500 text-sm"></i>
                    </a>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-route text-blue-500 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900 leading-tight">Order Tracking</h1>
                        <p class="text-xs text-gray-400">
                            Order #<?php echo $order_id; ?> &middot;
                            <?php echo htmlspecialchars($order['customer_name']); ?>
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <div class="px-4 py-2 rounded-lg border border-purple-200 bg-purple-50 text-center min-w-[80px]">
                        <p class="text-xs font-medium text-purple-500 mb-0.5">Status</p>
                        <p class="text-sm font-semibold text-purple-800">
                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                        </p>
                    </div>

                    <div class="px-4 py-2 rounded-lg border border-green-200 bg-green-50 text-center min-w-[80px]">
                        <p class="text-xs font-medium text-green-500 mb-0.5">Items</p>
                        <p class="text-sm font-semibold text-green-800"><?php echo count($items); ?></p>
                    </div>

                    <?php if (!$hasScheduledDeliveries): ?>
                        <?php $rc = $allItemsReadyForDelivery ? 'green' : 'blue'; ?>
                        <div
                            class="px-4 py-2 rounded-lg border border-<?php echo $rc; ?>-200 bg-<?php echo $rc; ?>-50 text-center min-w-[80px]">
                            <p class="text-xs font-medium text-<?php echo $rc; ?>-500 mb-0.5">
                                <i class="fas fa-warehouse mr-1"></i>Ready Items
                            </p>
                            <p class="text-sm font-semibold text-<?php echo $rc; ?>-800">
                                <?php echo $itemsReadyForDelivery; ?> / <?php echo $totalItems; ?>
                            </p>
                            <?php if ($allItemsReadyForDelivery): ?>
                                <p class="text-xs text-green-500 mt-0.5"><i class="fas fa-check-circle mr-1"></i>Ready to
                                    Schedule</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="px-4 py-2 rounded-lg border border-purple-200 bg-purple-50 text-center min-w-[80px]">
                            <p class="text-xs font-medium text-purple-500 mb-0.5"><i
                                    class="fas fa-calendar-check mr-1"></i>Delivery</p>
                            <p class="text-sm font-semibold text-purple-800">Scheduled</p>
                            <p class="text-xs text-purple-400 mt-0.5"><i class="fas fa-truck mr-1"></i>In Progress</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Main Content ─────────────────────────────────────────────── -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if (isset($success_message)): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4 shadow-sm">
                <div class="flex items-center">
                    <div class="bg-green-100 rounded-full p-2 mr-3"><i class="fas fa-check-circle text-green-600"></i></div>
                    <span class="text-green-800 font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 shadow-sm">
                <div class="flex items-center">
                    <div class="bg-red-100 rounded-full p-2 mr-3"><i class="fas fa-exclamation-circle text-red-600"></i>
                    </div>
                    <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Delivery Readiness Banners -->
        <?php if ($totalItems > 0 && !$allItemsReadyForDelivery && !$hasScheduledDeliveries): ?>
            <div class="mb-6 bg-blue-50 border-2 border-blue-300 rounded-xl p-6 shadow-sm">
                <div class="flex items-start">
                    <div class="bg-blue-100 rounded-full p-3 mr-4">
                        <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-blue-900 font-bold text-lg mb-2">Waiting for Items to be Ready</h3>
                        <p class="text-blue-800 mb-3">Delivery can be scheduled once all items reach the required status:
                        </p>
                        <ul class="text-sm text-blue-700 mb-3 space-y-1 ml-4">
                            <li><i class="fas fa-check-circle mr-2"></i><strong>Local items:</strong> In Warehouse</li>
                            <li><i class="fas fa-check-circle mr-2"></i><strong>International items:</strong> In Warehouse
                            </li>
                            <li><i class="fas fa-check-circle mr-2"></i><strong>Replacements:</strong> In Warehouse</li>
                        </ul>
                        <div class="bg-white rounded-lg p-4 border border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-blue-900">Delivery Readiness</span>
                                <span class="text-sm font-bold text-blue-600"><?php echo $itemsReadyForDelivery; ?> of
                                    <?php echo $totalItems; ?> items ready</span>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-3 overflow-hidden">
                                <div class="bg-blue-500 h-3 rounded-full transition-all duration-500"
                                    style="width:<?php echo $totalItems > 0 ? round(($itemsReadyForDelivery / $totalItems) * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($allItemsReadyForDelivery && $totalItems > 0 && !$hasScheduledDeliveries): ?>
            <div
                class="border border-green-200 bg-green-50 rounded-xl px-4 sm:px-5 py-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-md bg-green-100 flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fas fa-check-circle text-green-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-green-800 mb-0.5"><i class="fas fa-warehouse mr-1.5"></i>All
                            Items Ready for Delivery</p>
                        <p class="text-xs text-green-600">All items have been received and stored in the warehouse. You can
                            now schedule the delivery.</p>
                    </div>
                </div>
                <a href="warehouse_staff_delivery_schedule_C-A.php?order_id=<?php echo $order_id; ?>&schedule_all=true"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-medium bg-green-500 hover:bg-green-600 text-white transition-colors shrink-0">
                    <i class="fas fa-calendar-check"></i>
                    <span>Schedule Delivery</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>

        <?php elseif ($hasScheduledDeliveries && $totalItems > 0): ?>
            <?php
            $scheduleDetailStmt = $conn->prepare("SELECT delivery_date, delivery_time, delivery_notes FROM delivery_schedules WHERE order_id = ? ORDER BY delivery_date, delivery_time LIMIT 1");
            $scheduleDetailStmt->bind_param("i", $order_id);
            $scheduleDetailStmt->execute();
            $scheduleDetail = $scheduleDetailStmt->get_result()->fetch_assoc();
            $scheduleDetailStmt->close();
            if ($scheduleDetail):
                $dt = new DateTime($scheduleDetail['delivery_date'] . ' ' . $scheduleDetail['delivery_time']);
                ?>
                <div class="mb-6 bg-purple-50 border-2 border-purple-300 rounded-xl p-6 shadow-sm">
                    <div class="flex items-start">
                        <div class="bg-purple-100 rounded-full p-3 mr-4">
                            <i class="fas fa-calendar-check text-purple-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-purple-900 font-bold text-lg mb-2"><i class="fas fa-truck mr-2"></i>Delivery
                                Scheduled</h3>
                            <p class="text-purple-800 mb-3">This order has been scheduled for delivery. Items are being prepared
                                for dispatch.</p>
                            <div class="bg-white rounded-lg p-4 border border-purple-200">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center text-purple-700 mb-2">
                                            <i class="fas fa-calendar text-purple-600 mr-2"></i>
                                            <span class="text-sm font-medium">Scheduled Date</span>
                                        </div>
                                        <p class="text-purple-900 font-bold text-lg"><?php echo $dt->format('l, F j, Y'); ?></p>
                                    </div>
                                    <div>
                                        <div class="flex items-center text-purple-700 mb-2">
                                            <i class="fas fa-clock text-purple-600 mr-2"></i>
                                            <span class="text-sm font-medium">Scheduled Time</span>
                                        </div>
                                        <p class="text-purple-900 font-bold text-lg"><?php echo $dt->format('g:i A'); ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($scheduleDetail['delivery_notes'])): ?>
                                    <div class="mt-4 pt-4 border-t border-purple-200">
                                        <div class="flex items-center text-purple-700 mb-2">
                                            <i class="fas fa-sticky-note text-purple-600 mr-2"></i>
                                            <span class="text-sm font-medium">Delivery Notes</span>
                                        </div>
                                        <p class="text-purple-800">
                                            <?php echo htmlspecialchars($scheduleDetail['delivery_notes']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Replacement Banners -->
        <?php if ($hasReplacements && $allReplacementsReady && !$hasScheduledReplacements): ?>
            <div class="mb-6 bg-red-50 border-2 border-red-300 rounded-xl p-6 shadow-sm">
                <div class="flex items-start">
                    <div class="bg-red-100 rounded-full p-3 mr-4 animate-pulse">
                        <i class="fas fa-sync-alt text-red-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-red-900 font-bold text-lg mb-2"><i class="fas fa-warehouse mr-2"></i>All Replacement
                            Items Ready!</h3>
                        <p class="text-red-800 mb-3">All <?php echo $totalReplacements; ?> replacement item(s) have been
                            received and stored in the warehouse.</p>
                        <a href="warehouse_staff_delivery_schedule_C-A.php?order_id=<?php echo $order_id; ?>&schedule_replacements=true"
                            class="inline-flex items-center space-x-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 hover:scale-105 shadow-lg">
                            <i class="fas fa-calendar-check"></i>
                            <span>Schedule Replacement Delivery (<?php echo $totalReplacements; ?> items)</span>
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
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
                        <h3 class="text-orange-900 font-bold text-lg mb-2">Waiting for Replacement Items</h3>
                        <p class="text-orange-800 mb-3">Some replacement items are still being processed. Delivery can be
                            scheduled once all replacements reach "In Warehouse" status.</p>
                        <div class="bg-white rounded-lg p-4 border border-orange-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-orange-900"><i
                                        class="fas fa-sync-alt mr-2"></i>Replacement Progress</span>
                                <span class="text-sm font-bold text-orange-600"><?php echo $replacementsReadyForDelivery; ?>
                                    of <?php echo $totalReplacements; ?> ready</span>
                            </div>
                            <div class="w-full bg-orange-200 rounded-full h-3 overflow-hidden">
                                <div class="bg-gradient-to-r from-red-400 to-red-600 h-3 rounded-full transition-all duration-500"
                                    style="width:<?php echo $totalReplacements > 0 ? round(($replacementsReadyForDelivery / $totalReplacements) * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Local Products ──────────────────────────────────────── -->
        <?php if (!empty($groupedItems['local'])): ?>
            <div class="mb-12">
                <div class="flex items-center mb-8">
                    <div class="bg-green-500 p-4 rounded-2xl mr-4 ">
                        <i class="fa-solid fa-cubes text-white text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">Local Products</h2>
                        <p class="text-green-600 font-medium">Faster fulfillment times</p>
                    </div>
                </div>

                <?php foreach ($groupedItems['local'] as $supplier => $supplierItems): ?>
                    <?php if (empty($supplierItems['original']) && empty($supplierItems['replacement']))
                        continue; ?>
                    <!-- Supplier Card -->
                    <div class="supplier-card rounded-2xl border border-gray-200 mb-8 overflow-hidden">
                        <!-- Card Header -->
                        <div class="p-3 text-white" style="background: linear-gradient(to right, #22c55e, #16a34a);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="bg-white p-2 rounded-xl">
                                        <i class="fas fa-building text-4xl text-black"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold"><?php echo htmlspecialchars($supplier); ?></h3>
                                        <p class="text-white text-opacity-90">Local Supplier</p>
                                    </div>
                                </div>
                                <span class="text-sm text-white text-opacity-90">
                                    <?php echo count($supplierItems['original']) + count($supplierItems['replacement']); ?>
                                    items
                                </span>
                            </div>
                        </div>
                        <!-- Card Items -->
                        <div class="p-6 space-y-4">
                            <?php foreach ($supplierItems['original'] as $item): ?>
                                <?php renderSingleItem($item, 'local', $statusDefinitions['local'], $selectableStatuses['local'], $order_id, false, $replacementStatusDefinitions, $selectableReplacementStatuses, $conn); ?>
                            <?php endforeach; ?>
                            <?php foreach ($supplierItems['replacement'] as $item): ?>
                                <?php renderSingleItem($item, 'local', $replacementStatusDefinitions, $selectableReplacementStatuses, $order_id, true, $replacementStatusDefinitions, $selectableReplacementStatuses, $conn); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ── International Products ─────────────────────────────── -->
        <?php if (!empty($groupedItems['international'])): ?>
            <div class="mb-12">
                <div class="flex items-center mb-8">
                    <div class="p-4 rounded-2xl mr-4 shadow-lg"
                        style="background: linear-gradient(to right, #3b82f6, #2563eb);">
                        <i class="fas fa-globe text-white text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">🌏 International Products</h2>
                        <p class="text-blue-600 font-medium">Extended shipping times</p>
                    </div>
                </div>

                <?php foreach ($groupedItems['international'] as $supplier => $supplierItems): ?>
                    <?php if (empty($supplierItems['original']) && empty($supplierItems['replacement']))
                        continue; ?>
                    <!-- Supplier Card -->
                    <div class="supplier-card rounded-2xl shadow-lg border border-gray-200 mb-8 overflow-hidden">
                        <!-- Card Header -->
                        <div class="p-6 text-white" style="background: linear-gradient(to right, #3b82f6, #2563eb);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                                        <i class="fas fa-building text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold"><?php echo htmlspecialchars($supplier); ?></h3>
                                        <p class="text-white text-opacity-90">International Supplier</p>
                                    </div>
                                </div>
                                <span class="text-sm text-white text-opacity-90">
                                    <?php echo count($supplierItems['original']) + count($supplierItems['replacement']); ?>
                                    items
                                </span>
                            </div>
                        </div>
                        <!-- Card Items -->
                        <div class="p-6 space-y-4">
                            <?php foreach ($supplierItems['original'] as $item): ?>
                                <?php renderSingleItem($item, 'international', $statusDefinitions['international'], $selectableStatuses['international'], $order_id, false, $replacementStatusDefinitions, $selectableReplacementStatuses, $conn); ?>
                            <?php endforeach; ?>
                            <?php foreach ($supplierItems['replacement'] as $item): ?>
                                <?php renderSingleItem($item, 'international', $replacementStatusDefinitions, $selectableReplacementStatuses, $order_id, true, $replacementStatusDefinitions, $selectableReplacementStatuses, $conn); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- No Items -->
        <?php if (empty($groupedItems['local']) && empty($groupedItems['international'])): ?>
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-12 text-center">
                <div class="text-gray-500">
                    <div class="bg-gray-100 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-box-open text-4xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">No Items Found</h3>
                    <p class="text-gray-600 mb-6">No items found for this order.</p>
                    <a href="warehouse_staff_management_main.php"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 inline-flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Orders</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Status Legend ──────────────────────────────────────── -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-3"></i>Status Tracking Guide
            </h3>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="bg-green-50 rounded-xl p-6 border border-green-200">
                    <h4 class="font-bold text-green-700 mb-4 flex items-center">
                        <i class="fas fa-home mr-2"></i>🏠 Local Products
                    </h4>
                    <div class="space-y-3">
                        <?php foreach ($statusDefinitions['local'] as $status => $info): ?>
                            <div class="flex items-center space-x-3">
                                <div
                                    class="bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-800 px-3 py-1 rounded-full text-sm font-medium min-w-max">
                                    <i class="fas <?php echo $info['icon']; ?> mr-1"></i><?php echo $info['label']; ?>
                                </div>
                                <span class="text-sm text-gray-600"><?php echo $info['description']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
                    <h4 class="font-bold text-blue-700 mb-4 flex items-center">
                        <i class="fas fa-globe mr-2"></i>🌏 International Products
                    </h4>
                    <div class="space-y-3">
                        <?php foreach ($statusDefinitions['international'] as $status => $info): ?>
                            <div class="flex items-center space-x-3">
                                <div
                                    class="bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-800 px-3 py-1 rounded-full text-sm font-medium min-w-max">
                                    <i class="fas <?php echo $info['icon']; ?> mr-1"></i><?php echo $info['label']; ?>
                                </div>
                                <span class="text-sm text-gray-600"><?php echo $info['description']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-red-50 rounded-xl p-6 border border-red-200">
                    <h4 class="font-bold text-red-700 mb-4 flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i>🔄 Replacement Items
                    </h4>
                    <div class="space-y-3">
                        <?php foreach ($replacementStatusDefinitions as $status => $info): ?>
                            <div class="flex items-center space-x-3">
                                <div
                                    class="bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-800 px-3 py-1 rounded-full text-sm font-medium min-w-max">
                                    <i class="fas <?php echo $info['icon']; ?> mr-1"></i><?php echo $info['label']; ?>
                                </div>
                                <span class="text-sm text-gray-600"><?php echo $info['description']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div><!-- end max-w-7xl -->

    <!-- ── Floating Buttons ──────────────────────────────────────── -->
    <div class="floating-action flex flex-col space-y-3">
        <?php if ($allItemsReadyForDelivery && $totalItems > 0 && !$hasScheduledDeliveries): ?>
            <a href="warehouse_staff_delivery_schedule_C-A.php?order_id=<?php echo $order_id; ?>&schedule_all=true"
                class="bg-green-500 hover:bg-green-600 text-white px-6 py-4 rounded-full shadow-xl hover:shadow-2xl transition-all duration-200 flex items-center space-x-3 hover:scale-110 animate-pulse">
                <i class="fas fa-calendar-check text-xl"></i>
                <span class="font-bold">Schedule All Delivery</span>
            </a>
        <?php endif; ?>
        <a href="warehouse_staff_management_main.php"
            class="bg-gray-700 hover:from-gray-700 hover:to-gray-800 text-white p-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-200 flex items-center space-x-2 hover:scale-110">
            <i class="fas fa-list"></i>
            <span class="hidden sm:inline">All Orders</span>
        </a>
    </div>

    <!-- ── Progress Modal ────────────────────────────────────────── -->
    <div id="progressModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-route text-blue-500 text-sm"></i> Tracking Progress
                </h3>
                <button onclick="closeProgressModal()"
                    class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-gray-400 text-sm"></i>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-5">
                <div id="progressContent"></div>
            </div>
        </div>
    </div>

    <!-- ── Assign Receiver Modal ─────────────────────────────────── -->
    <div id="assignReceiverModal"
    class="fixed inset-0 z-50 items-center justify-center p-4"
    style="display:none; background-color: rgba(0,0,0,0.5);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="flex items-center justify-between p-6 border border-gray-200 bg-blue-50">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-user-check mr-3 text-blue-600"></i> Assign Warehouse Receiver
                </h3>
                <button onclick="closeAssignReceiverModal()"
                    class="text-gray-500 hover:text-gray-700 p-2 hover:bg-gray-100 rounded-full">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <div class="text-sm text-gray-600 mb-1">P.O. Number:</div>
                    <div id="assignModalPoNumber"
                        class="font-mono font-bold text-gray-900 text-sm bg-gray-50 px-3 py-2 rounded border"></div>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-check mr-1"></i> Select Receiver <span class="text-red-500">*</span>
                    </label>
                    <select id="assignReceiverSelect"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Receiver --</option>
                        <?php
                        $receiversResult = $conn->query("SELECT id, fullname FROM nobleaccount WHERE subrole = 'warehouse_receiver' AND status = 'active' ORDER BY fullname ASC");
                        if ($receiversResult) {
                            while ($recv = $receiversResult->fetch_assoc()):
                                ?>
                                <option value="<?php echo $recv['id']; ?>"><?php echo htmlspecialchars($recv['fullname']); ?>
                                </option>
                                <?php
                            endwhile;
                        }
                        ?>
                    </select>
                </div>
                <div class="flex space-x-3">
                    <button onclick="submitAssignReceiver()"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center justify-center space-x-2">
                        <i class="fas fa-user-check"></i><span>Assign Receiver</span>
                    </button>
                    <button onclick="closeAssignReceiverModal()"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium transition-colors duration-200">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php
    // ── renderSingleItem function ──────────────────────────────────────────────────
    function renderSingleItem($item, $origin, $statusDef, $selectableStatuses, $order_id, $isReplacement, $replacementStatusDefinitions, $selectableReplacementStatuses, $conn)
    {
        $currentStatus = $item['current_status'];
        $statusInfo = $statusDef[$currentStatus] ?? ($isReplacement ? $statusDef['approved'] : $statusDef['processing']);
        $itemClass = $isReplacement ? 'replacement-item' : '';

        // Defect check
        $defectCountStmt = $conn->prepare("SELECT COUNT(*) as defect_count FROM defect_reports WHERE order_item_id = ?");
        $defectCountStmt->bind_param("i", $item['id']);
        $defectCountStmt->execute();
        $hasDefects = (int) $defectCountStmt->get_result()->fetch_assoc()['defect_count'] > 0;
        $defectCountStmt->close();

        $isReplacementBool = $isReplacement ? 'true' : 'false';
        ?>

        <div class="status-card bg-white rounded-xl p-6 border border-gray-200 cursor-pointer <?php echo $itemClass; ?>"
            onclick="openProgressModal('<?php echo $item['id']; ?>', '<?php echo $origin; ?>', '<?php echo $currentStatus; ?>', <?php echo $isReplacementBool; ?>)">

            <?php if ($isReplacement): ?>
                <div class="mb-4 flex items-center justify-between">
                    <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-semibold">
                        <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT ITEM
                    </span>
                    <div class="text-sm text-gray-600">
                        <span class="font-medium">Reason:</span>
                        <?php echo htmlspecialchars(ucfirst($item['replacement_reason'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasDefects): ?>
                <div class="mb-4 bg-orange-50 border-l-4 border-orange-500 rounded p-3">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-exclamation-triangle text-orange-600 text-lg"></i>
                        <span class="text-orange-800 font-semibold">Defect Reported</span>
                        <button onclick="event.stopPropagation(); viewItemDefects(<?php echo $item['id']; ?>);"
                            class="ml-auto text-orange-600 hover:text-orange-700 underline text-sm">
                            View Details
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">

                <!-- Item Info -->
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-gray-900 text-lg mb-2"><?php echo htmlspecialchars($item['product_name']); ?>
                    </h4>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                        <span class="flex items-center"><i class="fas fa-boxes mr-2"></i>Qty: <strong
                                class="ml-1"><?php echo $item['quantity']; ?></strong></span>
                        <span class="flex items-center"><i class="fas fa-peso-sign mr-2"></i>Price: <strong
                                class="ml-1"><?php echo number_format((float) $item['price'], 2); ?></strong></span>
                        <?php if ($item['codename']): ?>
                            <span class="flex items-center"><i class="fas fa-tag mr-2"></i>Code: <strong
                                    class="ml-1"><?php echo htmlspecialchars($item['codename']); ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status & Actions -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-4"
                    onclick="event.stopPropagation();">

                    <!-- Status Badge -->
                    <div class="text-center">
                        <div
                            class="status-badge inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold mb-2">
                            <i
                                class="fas <?php echo $statusInfo['icon']; ?> mr-2 text-<?php echo $statusInfo['color']; ?>-600"></i>
                            <span
                                class="text-<?php echo $statusInfo['color']; ?>-800"><?php echo $statusInfo['label']; ?></span>
                        </div>
                        <div class="text-xs text-gray-600"><?php echo $statusInfo['description']; ?></div>
                    </div>

                    <div class="flex flex-col space-y-2">

                        <!-- Status Update Form -->
                        <?php
                        $canUpdate = false;
                        $statusOptionsToUse = [];
                        if ($isReplacement) {
                            $canUpdate = in_array($currentStatus, $selectableReplacementStatuses);
                            $statusOptionsToUse = $selectableReplacementStatuses;
                            $defForOptions = $statusDef; // replacementStatusDefinitions already passed as $statusDef
                        } else {
                            $canUpdate = in_array($currentStatus, $selectableStatuses);
                            $statusOptionsToUse = $selectableStatuses;
                            $defForOptions = $statusDef;
                        }
                        ?>
                        <?php if ($canUpdate): ?>
                            <form method="POST" class="flex items-center space-x-2">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <?php if ($isReplacement): ?>
                                    <input type="hidden" name="item_type" value="replacement">
                                    <input type="hidden" name="replacement_id" value="<?php echo $item['replacement_id']; ?>">
                                <?php endif; ?>
                                <select name="tracking_status"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <?php foreach ($statusOptionsToUse as $s): ?>
                                        <?php $info = $defForOptions[$s]; ?>
                                        <option value="<?php echo $s; ?>" <?php echo ($currentStatus === $s) ? 'selected' : ''; ?>>
                                            <?php echo $info['label']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_status"
                                    class="bg-blue-500 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2 hover:scale-105">
                                    <i class="fas fa-sync-alt"></i><span>Update</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Resolve Defect Button -->
                        <?php if (!$isReplacement && $hasDefects && $currentStatus !== 'cancelled'): ?>
                            <?php
                            $defectStatusStmt = $conn->prepare("SELECT status FROM defect_reports WHERE order_item_id = ? AND status != 'resolved' LIMIT 1");
                            $defectStatusStmt->bind_param("i", $item['id']);
                            $defectStatusStmt->execute();
                            $hasUnresolved = (bool) $defectStatusStmt->get_result()->fetch_assoc();
                            $defectStatusStmt->close();
                            ?>
                            <?php if ($hasUnresolved): ?>
                                <button
                                    onclick="event.stopPropagation(); resolveDefect(<?php echo $item['id']; ?>, <?php echo $order_id; ?>);"
                                    class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2">
                                    <i class="fas fa-check-circle"></i><span>Mark Defect as Resolved</span>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div><!-- end flex flex-col space-y-2 -->

                </div><!-- end Status & Actions -->
            </div><!-- end flex item row -->

            <!-- P.O. for Replacements -->
            <?php if ($isReplacement && in_array($currentStatus, ['approved', 'processing'])): ?>
                <?php
                $replPoStmt = $conn->prepare("SELECT po_number FROM replacement_requests WHERE id = ?");
                $replPoStmt->bind_param("i", $item['replacement_id']);
                $replPoStmt->execute();
                $replPoResult = $replPoStmt->get_result()->fetch_assoc();
                $replPoStmt->close();
                ?>
                <?php if (empty($replPoResult['po_number'])): ?>
                    <button onclick="generateReplacementPO(<?php echo $item['replacement_id']; ?>)"
                        class="w-full bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center justify-center space-x-2 mt-2">
                        <i class="fas fa-file-invoice"></i><span>Generate P.O. Number</span>
                    </button>
                <?php else: ?>
                    <?php
                    $replRecvStmt = $conn->prepare("SELECT rr.receiver_id, na.fullname as receiver_name FROM replacement_requests rr LEFT JOIN nobleaccount na ON rr.receiver_id = na.id WHERE rr.id = ?");
                    $replRecvStmt->bind_param("i", $item['replacement_id']);
                    $replRecvStmt->execute();
                    $replRecv = $replRecvStmt->get_result()->fetch_assoc();
                    $replRecvStmt->close();
                    ?>
                    <?php if (!empty($replRecv['receiver_id'])): ?>
                        <div class="bg-green-50 border-2 border-green-200 rounded-lg p-3 text-center mt-2">
                            <div class="text-xs text-green-700 mb-1 font-semibold">P.O. Number Generated</div>
                            <div class="font-mono font-bold text-green-900 text-sm mb-1">
                                <?php echo htmlspecialchars($replPoResult['po_number']); ?>
                            </div>
                            <div class="text-xs text-gray-600 mb-2"><i class="fas fa-user mr-1"></i>Receiver:
                                <strong><?php echo htmlspecialchars($replRecv['receiver_name']); ?></strong>
                            </div>
                            <a href="receiver_view_po_items_A.php?po_number=<?php echo urlencode($replPoResult['po_number']); ?>"
                                class="inline-flex items-center space-x-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-medium transition-colors duration-200">
                                <i class="fas fa-qrcode"></i><span>View QR Codes</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-3 mt-2">
                            <div class="text-xs text-blue-700 mb-1 font-semibold">P.O. Number Generated</div>
                            <div class="font-mono font-bold text-blue-900 text-sm mb-2">
                                <?php echo htmlspecialchars($replPoResult['po_number']); ?>
                            </div>
                            <div class="text-xs text-blue-700 mb-2">Assign a warehouse receiver to process this replacement:</div>
                           <button
    onclick="event.stopPropagation(); openAssignReceiverModal(<?php echo $item['replacement_id']; ?>, '<?php echo htmlspecialchars($replPoResult['po_number'], ENT_QUOTES); ?>')"
    class="w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-xs font-medium transition-colors duration-200 flex items-center justify-center space-x-1">
    <i class="fas fa-user-check"></i><span>Assign Receiver</span>
</button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- end status-card -->
        <?php
    }
    ?>

    <script>
        const statusDefinitions = <?php echo json_encode($statusDefinitions); ?>;
        const replacementStatusDefinitions = <?php echo json_encode($replacementStatusDefinitions); ?>;

        function openProgressModal(itemId, origin, currentStatus, isReplacement = false) {
            const modal = document.getElementById('progressModal');
            const content = document.getElementById('progressContent');

            const statuses = isReplacement ? replacementStatusDefinitions : statusDefinitions[origin];
            const statusKeys = Object.keys(statuses);
            const currentIndex = statusKeys.indexOf(currentStatus);
            const progressPct = statuses[currentStatus].progress;

            let progressHTML = `
            <div style="padding:0 0 1rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
                    <p style="font-size:13px;font-weight:500;margin:0;">${isReplacement ? 'Replacement' : 'Item'} Progress Timeline</p>
                    ${isReplacement ? `<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;color:#b91c1c;background:#fef2f2;border:.5px solid #fecaca;padding:2px 8px;border-radius:999px;">↺ Replacement</span>` : ''}
                </div>
                <div style="position:relative;padding-left:2rem;">
                    <div style="position:absolute;left:10px;top:0;bottom:0;width:2px;background:#e5e7eb;"></div>
                    <div style="position:absolute;left:10px;top:0;width:2px;height:${((currentIndex + 1) / statusKeys.length) * 100}%;background:${isReplacement ? '#f87171' : '#60a5fa'};transition:height .4s ease;"></div>
        `;

            statusKeys.forEach((status, index) => {
                const statusInfo = statuses[status];
                const isActive = index <= currentIndex;
                const isCurrent = status === currentStatus;
                const activeColor = isReplacement ? '#ef4444' : '#3b82f6';
                const activeBg = isReplacement ? '#fef2f2' : '#eff6ff';
                const activeBorder = isReplacement ? '#fecaca' : '#bfdbfe';
                const activeText = isReplacement ? '#b91c1c' : '#1d4ed8';

                progressHTML += `
                <div style="display:flex;align-items:flex-start;margin-bottom:1rem;position:relative;">
                    <div style="position:absolute;left:-31px;top:2px;width:20px;height:20px;border-radius:50%;
                                background:${isActive ? activeColor : '#fff'};
                                border:1.5px solid ${isActive ? activeColor : '#d1d5db'};
                                display:flex;align-items:center;justify-content:center;
                                font-size:10px;color:${isActive ? '#fff' : '#9ca3af'};flex-shrink:0;z-index:1;">
                        <i class="fas ${statusInfo.icon}" style="font-size:9px;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                            <span style="font-size:13px;font-weight:${isCurrent ? '600' : '400'};color:${isActive ? '#111827' : '#9ca3af'};">
                                ${statusInfo.label}
                            </span>
                        </div>
                        <p style="font-size:12px;margin:2px 0 ${isCurrent ? '6px' : '0'};color:${isActive ? '#6b7280' : '#d1d5db'};">
                            ${statusInfo.description}
                        </p>
                        ${isCurrent ? `<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;
                            color:${activeText};background:${activeBg};border:.5px solid ${activeBorder};padding:2px 8px;border-radius:999px;">
                            <i class="fas fa-map-marker-alt" style="font-size:9px;"></i> Current Status</span>` : ''}
                    </div>
                </div>
            `;
            });

            progressHTML += `
                </div>
            </div>
            <div style="background:#f9fafb;border:.5px solid #e5e7eb;border-radius:10px;padding:.875rem 1rem;margin-top:.5rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <span style="font-size:13px;color:#6b7280;">Overall Progress</span>
                    <span style="font-size:13px;font-weight:600;color:${isReplacement ? '#ef4444' : '#3b82f6'};">${progressPct}%</span>
                </div>
                <div style="background:#e5e7eb;border-radius:999px;height:6px;overflow:hidden;">
                    <div style="width:${progressPct}%;height:100%;background:${isReplacement ? '#f87171' : '#60a5fa'};border-radius:999px;transition:width .4s ease;"></div>
                </div>
            </div>
        `;

            content.innerHTML = progressHTML;
            modal.style.display = 'flex';
            document.body.classList.add('overflow-hidden');
        }

        function closeProgressModal() {
            document.getElementById('progressModal').style.display = 'none';
            document.body.classList.remove('overflow-hidden');
        }

        document.getElementById('progressModal').addEventListener('click', function (e) {
            if (e.target === this) closeProgressModal();
        });

        // Defects
        function viewItemDefects(itemId) {
            fetch(`warehouse_staff_report_defect_C-B.php?item_id=${itemId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.defects) showDefectsModal(data.defects);
                    else alert('Failed to load defect reports');
                })
                .catch(() => alert('Failed to load defect reports'));
        }

        function showDefectsModal(defects) {
            const existing = document.getElementById('defectsViewModal');
            if (existing) existing.remove();

            const sevColors = { minor: 'yellow', moderate: 'orange', severe: 'red' };
            const stColors = { pending: 'yellow', acknowledged: 'blue', replacement_requested: 'purple', resolved: 'green' };

            const defectsHTML = defects.map(d => {
                const c = sevColors[d.severity] || 'gray';
                const sc = stColors[d.status] || 'gray';
                return `
                <div class="border border-gray-100 rounded-xl p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-${c}-50 text-${c}-700 border border-${c}-200">
                                <i class="fas fa-exclamation-circle"></i>${d.severity.toUpperCase()}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-${sc}-50 text-${sc}-700 border border-${sc}-200">
                                ${d.status.replace('_', ' ').toUpperCase()}
                            </span>
                        </div>
                        <div class="text-xs text-gray-400 shrink-0 ml-2">Qty: <strong class="text-gray-600">${d.quantity_defective}</strong></div>
                    </div>
                    <p class="text-sm font-medium text-gray-900 mb-2">${d.defect_type}</p>
                    <p class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 mb-3">${d.defect_description}</p>
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span><i class="fas fa-user mr-1"></i>${d.reporter_name}</span>
                        <span><i class="fas fa-calendar mr-1"></i>${new Date(d.reported_at).toLocaleString()}</span>
                    </div>
                </div>
            `;
            }).join('');

            document.body.insertAdjacentHTML('beforeend', `
            <div id="defectsViewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle text-orange-400 text-sm"></i>
                            Defect Reports (${defects.length})
                        </h3>
                        <button onclick="closeDefectsModal()" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center transition-colors">
                            <i class="fas fa-times text-gray-400 text-sm"></i>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-5"><div class="space-y-3">${defectsHTML}</div></div>
                    <div class="px-5 py-4 border-t border-gray-100">
                        <button onclick="closeDefectsModal()" class="w-full px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">Close</button>
                    </div>
                </div>
            </div>
        `);
            document.body.classList.add('overflow-hidden');
            document.getElementById('defectsViewModal').addEventListener('click', function (e) {
                if (e.target === this) closeDefectsModal();
            });
        }

        function closeDefectsModal() {
            const m = document.getElementById('defectsViewModal');
            if (m) { m.remove(); document.body.classList.remove('overflow-hidden'); }
        }

        // Assign Receiver
        let currentAssignReplacementId = null;
        let currentAssignPoNumber = null;

function openAssignReceiverModal(replacementId, poNumber) {
    // IDAGDAG ITO — isara muna ang progress modal kung bukas
    closeProgressModal();
    
    currentAssignReplacementId = replacementId;
    currentAssignPoNumber = poNumber;
    document.getElementById('assignModalPoNumber').textContent = poNumber;
    document.getElementById('assignReceiverSelect').value = '';
    document.getElementById('assignReceiverModal').style.display = 'flex';
    document.body.classList.add('overflow-hidden');
}

function closeAssignReceiverModal() {
    document.getElementById('assignReceiverModal').style.display = 'none';
    document.body.classList.remove('overflow-hidden');
    currentAssignReplacementId = null;
    currentAssignPoNumber = null;
}
        function submitAssignReceiver() {
            const receiverId = document.getElementById('assignReceiverSelect').value;
            if (!receiverId) { alert('Please select a receiver.'); return; }

            fetch('warehouse_staff_assign_replacement_receiver_C3.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ replacement_id: currentAssignReplacementId, receiver_id: receiverId, po_number: currentAssignPoNumber })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { alert('✓ Receiver assigned successfully!'); closeAssignReceiverModal(); window.location.reload(); }
                    else alert('✗ Failed to assign receiver: ' + (data.error || 'Unknown error'));
                })
                .catch(() => alert('✗ Failed to assign receiver. Please try again.'));
        }

        document.getElementById('assignReceiverModal').addEventListener('click', function (e) {
            if (e.target === this) closeAssignReceiverModal();
        });

        // Generate P.O.
        function generateReplacementPO(replacementId) {
            if (!confirm('Generate P.O. number for this replacement item?\n\nThis will allow you to generate QR codes and track this replacement in the warehouse.')) return;

            fetch('warehouse_staff_generate_replacement_po_C1.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ replacement_id: replacementId })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { alert('✓ P.O. Number generated successfully!\n\nP.O. Number: ' + data.po_number); window.location.reload(); }
                    else alert('✗ Failed to generate P.O. number: ' + (data.error || 'Unknown error'));
                })
                .catch(() => alert('✗ Failed to generate P.O. number. Please try again.'));
        }

        // Resolve Defect
        function resolveDefect(itemId, orderId) {
            if (!confirm('Mark this defect as resolved?\n\nThis will allow the item to be marked as "In Warehouse" when scanned.')) return;

            fetch('warehouse_staff_resolve_defect_C2.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_item_id: itemId, order_id: orderId })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { alert('✓ Defect marked as resolved!'); window.location.reload(); }
                    else alert('✗ Failed to resolve defect: ' + (data.error || 'Unknown error'));
                })
                .catch(() => alert('✗ Failed to resolve defect. Please try again.'));
        }

        // Keyboard
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeProgressModal(); closeDefectsModal(); }
        });

        document.querySelectorAll('select[name="tracking_status"]').forEach(select => {
            select.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); this.closest('form')?.querySelector('button[type="submit"]')?.click(); }
            });
        });
    </script>
</body>

</html>