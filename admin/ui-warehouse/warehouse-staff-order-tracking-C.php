<?php
// warehouse_staff_order_tracking_C.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: " . BASE_URL . "/warehousestaff");
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
    header("Location: " . BASE_URL . "/warehousestaff");
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
        'pending'          => ['icon' => 'fa-cog',            'color' => 'blue',   'label' => 'Pending',                   'description' => 'Order confirmed and being prepared',         'progress' => 16],
        'processing'       => ['icon' => 'fa-cog',            'color' => 'blue',   'label' => 'Processing',                'description' => 'Order confirmed and being prepared',         'progress' => 16],
        'In Warehouse'     => ['icon' => 'fa-warehouse',      'color' => 'indigo', 'label' => 'In Warehouse',              'description' => 'Item received and stored in warehouse',      'progress' => 33],
        'scheduled'        => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled',                 'description' => 'Delivery has been scheduled',               'progress' => 50],
        'item_is_loaded'   => ['icon' => 'fa-box-open',       'color' => 'teal',   'label' => 'Item Loaded',               'description' => 'Item loaded onto delivery vehicle',         'progress' => 58],
        'ready_for_pickup' => ['icon' => 'fa-truck',          'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Item ready for local delivery',             'progress' => 66],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast',  'color' => 'orange', 'label' => 'Out for Delivery',          'description' => 'Courier delivering to customer',            'progress' => 83],
        'delivered'        => ['icon' => 'fa-check-circle',   'color' => 'green',  'label' => 'Delivered',                 'description' => 'Customer received the item',                'progress' => 100],
        'cancelled'        => ['icon' => 'fa-times-circle',   'color' => 'red',    'label' => 'Returned',                  'description' => 'Order cancelled or returned',               'progress' => 0],
    ],
    'international' => [
        'pending'                   => ['icon' => 'fa-cog',            'color' => 'blue',   'label' => 'Pending',                      'description' => 'Order confirmed, supplier preparing',              'progress' => 9],
        'processing'                => ['icon' => 'fa-cog',            'color' => 'blue',   'label' => 'Processing',                   'description' => 'Order confirmed, supplier preparing',              'progress' => 9],
        'shipped_overseas'          => ['icon' => 'fa-ship',           'color' => 'purple', 'label' => 'Shipped from Overseas',        'description' => 'Item has left the overseas supplier',             'progress' => 20],
        'in_transit_international'  => ['icon' => 'fa-plane',          'color' => 'yellow', 'label' => 'In Transit (International)',   'description' => 'Item is on the way (by sea/air)',                 'progress' => 32],
        'customs_clearance'         => ['icon' => 'fa-file-signature', 'color' => 'orange', 'label' => 'Customs Clearance',           'description' => 'Item undergoing customs inspection',              'progress' => 44],
        'In Warehouse'              => ['icon' => 'fa-warehouse',      'color' => 'indigo', 'label' => 'In Warehouse',                'description' => 'Item received and stored in local warehouse',     'progress' => 55],
        'scheduled'                 => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled',                   'description' => 'Delivery has been scheduled',                     'progress' => 66],
        'item_is_loaded'            => ['icon' => 'fa-box-open',       'color' => 'teal',   'label' => 'Item Loaded',                 'description' => 'Item loaded onto delivery vehicle',               'progress' => 71],
        'ready_for_pickup'          => ['icon' => 'fa-truck',          'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch',   'description' => 'Item ready for local delivery',                   'progress' => 77],
        'out_for_delivery'          => ['icon' => 'fa-shipping-fast',  'color' => 'orange', 'label' => 'Out for Delivery',            'description' => 'Courier delivering to customer',                  'progress' => 88],
        'delivered'                 => ['icon' => 'fa-check-circle',   'color' => 'green',  'label' => 'Delivered',                   'description' => 'Customer received the item',                      'progress' => 100],
        'cancelled'                 => ['icon' => 'fa-times-circle',   'color' => 'red',    'label' => 'Returned',                    'description' => 'Order cancelled or returned',                     'progress' => 0],
    ],
];

$replacementStatusDefinitions = [
    'approved'         => ['icon' => 'fa-check-circle',   'color' => 'green',  'label' => 'Approved',                'description' => 'Replacement request has been approved',          'progress' => 14],
    'processing'       => ['icon' => 'fa-cog',            'color' => 'blue',   'label' => 'Processing',              'description' => 'Replacement being prepared',                     'progress' => 28],
    'In Warehouse'     => ['icon' => 'fa-warehouse',      'color' => 'indigo', 'label' => 'In Warehouse',            'description' => 'Replacement received and stored in warehouse',   'progress' => 42],
    'scheduled'        => ['icon' => 'fa-calendar-check', 'color' => 'purple', 'label' => 'Scheduled',               'description' => 'Replacement delivery scheduled',                 'progress' => 57],
    'item_is_loaded'   => ['icon' => 'fa-box-open',       'color' => 'teal',   'label' => 'Item Loaded',             'description' => 'Replacement loaded onto delivery vehicle',       'progress' => 64],
    'ready_for_pickup' => ['icon' => 'fa-truck',          'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch','description' => 'Replacement ready for delivery',                'progress' => 71],
    'out_for_delivery' => ['icon' => 'fa-shipping-fast',  'color' => 'orange', 'label' => 'Out for Delivery',        'description' => 'Replacement being delivered',                    'progress' => 85],
    'delivered'        => ['icon' => 'fa-check-circle',   'color' => 'green',  'label' => 'Delivered',               'description' => 'Replacement delivered to customer',              'progress' => 100],
    'cancelled'        => ['icon' => 'fa-times-circle',   'color' => 'red',    'label' => 'Cancelled',               'description' => 'Replacement request cancelled',                  'progress' => 0],
];

$selectableStatuses = [
    'local'         => ['pending', 'processing'],
    'international' => ['pending', 'processing', 'shipped_overseas', 'in_transit_international', 'customs_clearance'],
];
$selectableReplacementStatuses = ['approved', 'processing'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking — Order #<?php echo $order_id; ?></title>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

            <!-- Left: title + breadcrumb -->
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/warehousestaff"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-base font-semibold text-gray-900 leading-tight">
                        Order Tracking &mdash; #<?php echo $order_id; ?>
                    </h1>
                    <p class="text-xs text-gray-400 mt-0.5">
                        <?php echo htmlspecialchars($order['customer_name']); ?>
                    </p>
                </div>
            </div>

            <!-- Right: stat pills -->
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-50 border border-violet-200 text-xs font-medium text-violet-700">
                    <i class="fas fa-circle-dot text-[10px]"></i>
                    <?php echo ucfirst($order['status']); ?>
                </span>

                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 border border-gray-200 text-xs font-medium text-gray-600">
                    <i class="fas fa-box text-[10px]"></i>
                    <?php echo count($items); ?> items
                </span>

                <?php if (!$hasScheduledDeliveries): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg
                        <?php echo $allItemsReadyForDelivery ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-blue-50 border border-blue-200 text-blue-700'; ?>
                        text-xs font-medium">
                        <i class="fas fa-warehouse text-[10px]"></i>
                        <?php echo $itemsReadyForDelivery; ?>/<?php echo $totalItems; ?> ready
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 border border-purple-200 text-xs font-medium text-purple-700">
                        <i class="fas fa-calendar-check text-[10px]"></i>
                        Delivery Scheduled
                    </span>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- ── Main ────────────────────────────────────────────────── -->
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-4">

    <!-- Alert: success -->
    <?php if (isset($success_message)): ?>
        <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
            <i class="fas fa-check-circle text-green-500"></i>
            <span class="text-sm text-green-800 font-medium"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Alert: error -->
    <?php if (isset($error_message)): ?>
        <div class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            <span class="text-sm text-red-800 font-medium"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <!-- ── Delivery Banners ─────────────────────────────────── -->

    <?php if ($totalItems > 0 && !$allItemsReadyForDelivery && !$hasScheduledDeliveries): ?>
        <!-- Waiting banner -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-clock text-blue-600 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-blue-900">Waiting for Items to be Ready</p>
                    <p class="text-xs text-blue-700 mt-0.5 mb-3">All items must reach <strong>In Warehouse</strong> status before scheduling delivery.</p>
                    <div class="bg-white rounded-lg px-3 py-2.5 border border-blue-100">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="text-gray-600 font-medium">Delivery Readiness</span>
                            <span class="text-blue-700 font-semibold"><?php echo $itemsReadyForDelivery; ?> / <?php echo $totalItems; ?> items</span>
                        </div>
                        <div class="w-full bg-blue-100 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-500"
                                 style="width:<?php echo $totalItems > 0 ? round(($itemsReadyForDelivery / $totalItems) * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($allItemsReadyForDelivery && $totalItems > 0 && !$hasScheduledDeliveries): ?>
        <!-- Ready banner -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-green-900">All Items Ready for Delivery</p>
                    <p class="text-xs text-green-700 mt-0.5">All items are in the warehouse. You can now schedule delivery.</p>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/warehousestaffdeliveryschedule?order_id=<?php echo $order_id; ?>&schedule_all=true"
               class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg transition-colors shrink-0">
                <i class="fas fa-calendar-check"></i> Schedule Delivery
            </a>
        </div>

    <?php elseif ($hasScheduledDeliveries && $totalItems > 0): ?>
        <!-- Scheduled banner -->
        <?php
        $scheduleDetailStmt = $conn->prepare("SELECT delivery_date, delivery_time, delivery_notes FROM delivery_schedules WHERE order_id = ? ORDER BY delivery_date, delivery_time LIMIT 1");
        $scheduleDetailStmt->bind_param("i", $order_id);
        $scheduleDetailStmt->execute();
        $scheduleDetail = $scheduleDetailStmt->get_result()->fetch_assoc();
        $scheduleDetailStmt->close();
        if ($scheduleDetail):
            $dt = new DateTime($scheduleDetail['delivery_date'] . ' ' . $scheduleDetail['delivery_time']);
        ?>
        <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-calendar-check text-purple-600 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-purple-900">Delivery Scheduled</p>
                    <div class="flex flex-wrap gap-4 mt-2">
                        <div>
                            <p class="text-xs text-purple-600 font-medium">Date</p>
                            <p class="text-sm font-bold text-purple-900"><?php echo $dt->format('l, F j, Y'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-purple-600 font-medium">Time</p>
                            <p class="text-sm font-bold text-purple-900"><?php echo $dt->format('g:i A'); ?></p>
                        </div>
                    </div>
                    <?php if (!empty($scheduleDetail['delivery_notes'])): ?>
                        <p class="text-xs text-purple-700 mt-2 bg-white rounded-lg px-3 py-2 border border-purple-100">
                            <i class="fas fa-sticky-note mr-1"></i><?php echo htmlspecialchars($scheduleDetail['delivery_notes']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ── Replacement Banners ─────────────────────────────── -->

    <?php if ($hasReplacements && $allReplacementsReady && !$hasScheduledReplacements): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-sync-alt text-red-600 text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-red-900">All Replacement Items Ready!</p>
                    <p class="text-xs text-red-700 mt-0.5"><?php echo $totalReplacements; ?> replacement item(s) are in the warehouse.</p>
                </div>
            </div>
            <a href="warehouse_staff_delivery_schedule_C-A.php?order_id=<?php echo $order_id; ?>&schedule_replacements=true"
               class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition-colors shrink-0">
                <i class="fas fa-calendar-check"></i> Schedule Replacement Delivery
            </a>
        </div>

    <?php elseif ($hasReplacements && !$allReplacementsReady && !$hasScheduledReplacements): ?>
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-clock text-orange-600 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-orange-900">Waiting for Replacement Items</p>
                    <p class="text-xs text-orange-700 mt-0.5 mb-3">Replacement delivery can be scheduled once all items reach <strong>In Warehouse</strong> status.</p>
                    <div class="bg-white rounded-lg px-3 py-2.5 border border-orange-100">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="text-gray-600 font-medium">Replacement Progress</span>
                            <span class="text-orange-700 font-semibold"><?php echo $replacementsReadyForDelivery; ?> / <?php echo $totalReplacements; ?> ready</span>
                        </div>
                        <div class="w-full bg-orange-100 rounded-full h-2">
                            <div class="bg-orange-500 h-2 rounded-full transition-all duration-500"
                                 style="width:<?php echo $totalReplacements > 0 ? round(($replacementsReadyForDelivery / $totalReplacements) * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Local Products ──────────────────────────────────── -->
    <?php if (!empty($groupedItems['local'])): ?>
        <div>
            <!-- Section Header -->
            <div class="flex items-center gap-2 mb-3">
                <div class="w-7 h-7 rounded-lg bg-green-500 flex items-center justify-center">
                    <i class="fas fa-cubes text-white text-xs"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900">Local Products</h2>
                    <p class="text-xs text-green-600">Faster fulfillment times</p>
                </div>
            </div>

            <?php foreach ($groupedItems['local'] as $supplier => $supplierItems): ?>
                <?php if (empty($supplierItems['original']) && empty($supplierItems['replacement'])) continue; ?>

                <!-- Supplier Group -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
                    <!-- Supplier Header -->
                    <div class="flex items-center justify-between px-4 py-3 bg-green-600">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center">
                                <i class="fas fa-building text-white text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($supplier); ?></p>
                                <p class="text-xs text-green-100">Local Supplier</p>
                            </div>
                        </div>
                        <span class="text-xs text-green-100">
                            <?php echo count($supplierItems['original']) + count($supplierItems['replacement']); ?> item(s)
                        </span>
                    </div>

                    <!-- Items -->
                    <div class="divide-y divide-gray-100">
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

    <!-- ── International Products ─────────────────────────── -->
    <?php if (!empty($groupedItems['international'])): ?>
        <div>
            <div class="flex items-center gap-2 mb-3">
                <div class="w-7 h-7 rounded-lg bg-blue-500 flex items-center justify-center">
                    <i class="fas fa-globe text-white text-xs"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900">International Products</h2>
                    <p class="text-xs text-blue-600">Extended shipping times</p>
                </div>
            </div>

            <?php foreach ($groupedItems['international'] as $supplier => $supplierItems): ?>
                <?php if (empty($supplierItems['original']) && empty($supplierItems['replacement'])) continue; ?>

                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
                    <div class="flex items-center justify-between px-4 py-3 bg-blue-600">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center">
                                <i class="fas fa-building text-white text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($supplier); ?></p>
                                <p class="text-xs text-blue-100">International Supplier</p>
                            </div>
                        </div>
                        <span class="text-xs text-blue-100">
                            <?php echo count($supplierItems['original']) + count($supplierItems['replacement']); ?> item(s)
                        </span>
                    </div>
                    <div class="divide-y divide-gray-100">
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

    <!-- ── No Items ────────────────────────────────────────── -->
    <?php if (empty($groupedItems['local']) && empty($groupedItems['international'])): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-12 text-center">
            <div class="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-box-open text-gray-400 text-xl"></i>
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">No Items Found</h3>
            <p class="text-sm text-gray-500 mb-5">No items found for this order.</p>
            <a href="warehouse_staff_management_main.php"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-arrow-left text-xs"></i> Back to Orders
            </a>
        </div>
    <?php endif; ?>

    <!-- ── Status Legend ───────────────────────────────────── -->
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-500 text-sm"></i> Status Tracking Guide
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- Local -->
            <div class="bg-green-50 rounded-xl p-4 border border-green-100">
                <h4 class="text-xs font-bold text-green-700 mb-3 flex items-center gap-1.5">
                    <i class="fas fa-home"></i> Local Products
                </h4>
                <div class="space-y-2">
                    <?php foreach ($statusDefinitions['local'] as $status => $info): ?>
                        <div class="flex items-start gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-700 shrink-0 mt-0.5">
                                <i class="fas <?php echo $info['icon']; ?> text-[9px]"></i>
                                <?php echo $info['label']; ?>
                            </span>
                            <span class="text-xs text-gray-500 leading-relaxed"><?php echo $info['description']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- International -->
            <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                <h4 class="text-xs font-bold text-blue-700 mb-3 flex items-center gap-1.5">
                    <i class="fas fa-globe"></i> International Products
                </h4>
                <div class="space-y-2">
                    <?php foreach ($statusDefinitions['international'] as $status => $info): ?>
                        <div class="flex items-start gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-700 shrink-0 mt-0.5">
                                <i class="fas <?php echo $info['icon']; ?> text-[9px]"></i>
                                <?php echo $info['label']; ?>
                            </span>
                            <span class="text-xs text-gray-500 leading-relaxed"><?php echo $info['description']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Replacements -->
            <div class="bg-red-50 rounded-xl p-4 border border-red-100">
                <h4 class="text-xs font-bold text-red-700 mb-3 flex items-center gap-1.5">
                    <i class="fas fa-sync-alt"></i> Replacement Items
                </h4>
                <div class="space-y-2">
                    <?php foreach ($replacementStatusDefinitions as $status => $info): ?>
                        <div class="flex items-start gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-700 shrink-0 mt-0.5">
                                <i class="fas <?php echo $info['icon']; ?> text-[9px]"></i>
                                <?php echo $info['label']; ?>
                            </span>
                            <span class="text-xs text-gray-500 leading-relaxed"><?php echo $info['description']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

</div><!-- end max-w-6xl -->

<!-- ── Floating Actions ────────────────────────────────────── -->
<div class="fixed bottom-5 right-5 z-40 flex flex-col items-end gap-2">
    <?php if ($allItemsReadyForDelivery && $totalItems > 0 && !$hasScheduledDeliveries): ?>
        <a href="warehouse_staff_delivery_schedule_C-A.php?order_id=<?php echo $order_id; ?>&schedule_all=true"
           class="inline-flex items-center gap-2 px-5 py-3 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
            <i class="fas fa-calendar-check"></i> Schedule Delivery
        </a>
    <?php endif; ?>

    
    <a href="<?= BASE_URL; ?>/warehousestaff"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium rounded-xl shadow-lg hover:shadow-xl transition-all">
        <i class="fas fa-list text-xs"></i> All Orders
    </a>
</div>

<!-- ── Progress Modal ────────────────────────────────────────── -->
<div id="progressModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                <i class="fas fa-route text-blue-500"></i> Tracking Timeline
            </h3>
            <button onclick="closeProgressModal()"
                class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center transition-colors text-gray-400">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-5">
            <div id="progressContent"></div>
        </div>
    </div>
</div>

<!-- ── Assign Receiver Modal ─────────────────────────────────── -->
<div id="assignReceiverModal" class="fixed inset-0 z-50 items-center justify-center p-4" style="display:none; background-color: rgba(0,0,0,0.5);">
    <div class="bg-white rounded-xl shadow-xl max-w-sm w-full">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                <i class="fas fa-user-check text-blue-500"></i> Assign Warehouse Receiver
            </h3>
            <button onclick="closeAssignReceiverModal()"
                class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center transition-colors text-gray-400">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <p class="text-xs text-gray-500 mb-1">P.O. Number</p>
                <p id="assignModalPoNumber" class="text-sm font-mono font-bold text-gray-900 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200"></p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Select Receiver <span class="text-red-500">*</span>
                </label>
                <select id="assignReceiverSelect"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Receiver --</option>
                    <?php
                    $receiversResult = $conn->query("SELECT id, fullname FROM nobleaccount WHERE subrole = 'warehouse_receiver' AND status = 'active' ORDER BY fullname ASC");
                    if ($receiversResult) {
                        while ($recv = $receiversResult->fetch_assoc()):
                    ?>
                    <option value="<?php echo $recv['id']; ?>"><?php echo htmlspecialchars($recv['fullname']); ?></option>
                    <?php endwhile; } ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button onclick="submitAssignReceiver()"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-user-check"></i> Assign
                </button>
                <button onclick="closeAssignReceiverModal()"
                    class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// ── renderSingleItem ───────────────────────────────────────────────────────
function renderSingleItem($item, $origin, $statusDef, $selectableStatuses, $order_id, $isReplacement, $replacementStatusDefinitions, $selectableReplacementStatuses, $conn)
{
    $currentStatus = $item['current_status'];
    $statusInfo    = $statusDef[$currentStatus] ?? ($isReplacement ? $statusDef['approved'] : $statusDef['processing']);
    $isReplacementBool = $isReplacement ? 'true' : 'false';

    // Defect check
    $defectCountStmt = $conn->prepare("SELECT COUNT(*) as defect_count FROM defect_reports WHERE order_item_id = ?");
    $defectCountStmt->bind_param("i", $item['id']);
    $defectCountStmt->execute();
    $hasDefects = (int) $defectCountStmt->get_result()->fetch_assoc()['defect_count'] > 0;
    $defectCountStmt->close();
?>
<div class="px-4 py-4 hover:bg-gray-50 transition-colors cursor-pointer"
     onclick="openProgressModal('<?php echo $item['id']; ?>', '<?php echo $origin; ?>', '<?php echo $currentStatus; ?>', <?php echo $isReplacementBool; ?>)">

    <!-- Replacement badge -->
    <?php if ($isReplacement): ?>
        <div class="flex items-center justify-between mb-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200">
                <i class="fas fa-sync-alt text-[9px]"></i> REPLACEMENT ITEM
            </span>
            <span class="text-xs text-gray-500">
                Reason: <strong class="text-gray-700"><?php echo htmlspecialchars(ucfirst($item['replacement_reason'])); ?></strong>
            </span>
        </div>
    <?php endif; ?>

    <!-- Defect warning -->
    <?php if ($hasDefects): ?>
        <div class="flex items-center justify-between mb-3 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2">
            <div class="flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-orange-500 text-xs"></i>
                <span class="text-xs font-semibold text-orange-800">Defect Reported</span>
            </div>
            <button onclick="event.stopPropagation(); viewItemDefects(<?php echo $item['id']; ?>);"
                class="text-xs text-orange-600 hover:text-orange-700 underline font-medium">
                View Details
            </button>
        </div>
    <?php endif; ?>

    <div class="flex flex-col sm:flex-row sm:items-center gap-3">

        <!-- Item Info -->
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($item['product_name']); ?></p>
            <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                <span><i class="fas fa-boxes mr-1"></i>Qty: <strong class="text-gray-700"><?php echo $item['quantity']; ?></strong></span>
                <span><i class="fas fa-peso-sign mr-1"></i>Price: <strong class="text-gray-700"><?php echo number_format((float) $item['price'], 2); ?></strong></span>
                <?php if ($item['codename']): ?>
                    <span><i class="fas fa-tag mr-1"></i>Code: <strong class="text-gray-700"><?php echo htmlspecialchars($item['codename']); ?></strong></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status & Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 shrink-0" onclick="event.stopPropagation();">

            <!-- Status badge -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                bg-<?php echo $statusInfo['color']; ?>-50 border border-<?php echo $statusInfo['color']; ?>-200 text-<?php echo $statusInfo['color']; ?>-700">
                <i class="fas <?php echo $statusInfo['icon']; ?> text-[10px]"></i>
                <?php echo $statusInfo['label']; ?>
            </span>

            <!-- Update form -->
            <?php
            $canUpdate = false;
            $statusOptionsToUse = [];
            if ($isReplacement) {
                $canUpdate = in_array($currentStatus, $selectableReplacementStatuses);
                $statusOptionsToUse = $selectableReplacementStatuses;
                $defForOptions = $statusDef;
            } else {
                $canUpdate = in_array($currentStatus, $selectableStatuses);
                $statusOptionsToUse = $selectableStatuses;
                $defForOptions = $statusDef;
            }
            ?>
            <?php if ($canUpdate): ?>
                <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                    <?php if ($isReplacement): ?>
                        <input type="hidden" name="item_type" value="replacement">
                        <input type="hidden" name="replacement_id" value="<?php echo $item['replacement_id']; ?>">
                    <?php endif; ?>
                    <select name="tracking_status"
                        class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($statusOptionsToUse as $s): ?>
                            <?php $info = $defForOptions[$s]; ?>
                            <option value="<?php echo $s; ?>" <?php echo ($currentStatus === $s) ? 'selected' : ''; ?>>
                                <?php echo $info['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="update_status"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                        <i class="fas fa-sync-alt text-[9px]"></i> Update
                    </button>
                </form>
            <?php endif; ?>

            <!-- Resolve Defect -->
            <?php if (!$isReplacement && $hasDefects && $currentStatus !== 'cancelled'): ?>
                <?php
                $defectStatusStmt = $conn->prepare("SELECT status FROM defect_reports WHERE order_item_id = ? AND status != 'resolved' LIMIT 1");
                $defectStatusStmt->bind_param("i", $item['id']);
                $defectStatusStmt->execute();
                $hasUnresolved = (bool) $defectStatusStmt->get_result()->fetch_assoc();
                $defectStatusStmt->close();
                ?>
                <?php if ($hasUnresolved): ?>
                    <button onclick="event.stopPropagation(); resolveDefect(<?php echo $item['id']; ?>, <?php echo $order_id; ?>);"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium rounded-lg transition-colors">
                        <i class="fas fa-check-circle text-[9px]"></i> Resolve Defect
                    </button>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- PO for Replacements -->
    <?php if ($isReplacement && in_array($currentStatus, ['approved', 'processing'])): ?>
        <?php
        $replPoStmt = $conn->prepare("SELECT po_number FROM replacement_requests WHERE id = ?");
        $replPoStmt->bind_param("i", $item['replacement_id']);
        $replPoStmt->execute();
        $replPoResult = $replPoStmt->get_result()->fetch_assoc();
        $replPoStmt->close();
        ?>
        <div class="mt-3">
            <?php if (empty($replPoResult['po_number'])): ?>
                <button onclick="generateReplacementPO(<?php echo $item['replacement_id']; ?>)"
                    class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors">
                    <i class="fas fa-file-invoice"></i> Generate P.O. Number
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
                    <div class="bg-green-50 border border-green-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs text-green-600 font-medium">P.O. Generated</p>
                            <p class="text-xs font-mono font-bold text-green-900"><?php echo htmlspecialchars($replPoResult['po_number']); ?></p>
                            <p class="text-xs text-gray-500 mt-0.5"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($replRecv['receiver_name']); ?></p>
                        </div>
                        <a href="receiver_view_po_items_A.php?po_number=<?php echo urlencode($replPoResult['po_number']); ?>"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors shrink-0">
                            <i class="fas fa-qrcode text-[9px]"></i> View QR
                        </a>
                    </div>
                <?php else: ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2.5">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <p class="text-xs text-blue-600 font-medium">P.O. Generated</p>
                                <p class="text-xs font-mono font-bold text-blue-900"><?php echo htmlspecialchars($replPoResult['po_number']); ?></p>
                            </div>
                        </div>
                        <button onclick="event.stopPropagation(); openAssignReceiverModal(<?php echo $item['replacement_id']; ?>, '<?php echo htmlspecialchars($replPoResult['po_number'], ENT_QUOTES); ?>')"
                            class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                            <i class="fas fa-user-check text-[9px]"></i> Assign Receiver
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
<?php
}
?>

<script>
    const statusDefinitions = <?php echo json_encode($statusDefinitions); ?>;
    const replacementStatusDefinitions = <?php echo json_encode($replacementStatusDefinitions); ?>;

    function openProgressModal(itemId, origin, currentStatus, isReplacement = false) {
        const modal   = document.getElementById('progressModal');
        const content = document.getElementById('progressContent');
        const statuses    = isReplacement ? replacementStatusDefinitions : statusDefinitions[origin];
        const statusKeys  = Object.keys(statuses);
        const currentIndex = statusKeys.indexOf(currentStatus);
        const progressPct  = statuses[currentStatus]?.progress ?? 0;
        const accentColor  = isReplacement ? '#ef4444' : '#3b82f6';

        let html = `
            <div class="mb-4 flex items-center justify-between">
                <p style="font-size:13px;font-weight:600;color:#111827;margin:0;">
                    ${isReplacement ? 'Replacement' : 'Item'} Timeline
                </p>
                ${isReplacement ? `<span style="font-size:11px;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;padding:2px 8px;border-radius:999px;font-weight:500;">↺ Replacement</span>` : ''}
            </div>
            <div style="position:relative;padding-left:28px;">
                <div style="position:absolute;left:9px;top:8px;bottom:8px;width:2px;background:#e5e7eb;"></div>
                <div style="position:absolute;left:9px;top:8px;width:2px;height:${((currentIndex + 1) / statusKeys.length) * 100}%;background:${accentColor};transition:height .4s ease;"></div>
        `;

        statusKeys.forEach((status, index) => {
            const info     = statuses[status];
            const isActive = index <= currentIndex;
            const isCurrent = status === currentStatus;

            html += `
                <div style="display:flex;align-items:flex-start;margin-bottom:14px;position:relative;">
                    <div style="position:absolute;left:-19px;top:3px;width:18px;height:18px;border-radius:50%;
                        background:${isActive ? accentColor : '#fff'};border:2px solid ${isActive ? accentColor : '#d1d5db'};
                        display:flex;align-items:center;justify-content:center;z-index:1;">
                        <i class="fas ${info.icon}" style="font-size:8px;color:${isActive ? '#fff' : '#9ca3af'};"></i>
                    </div>
                    <div style="flex:1;">
                        <p style="font-size:13px;font-weight:${isCurrent ? '600' : '400'};color:${isActive ? '#111827' : '#9ca3af'};margin:0 0 2px;">
                            ${info.label}
                        </p>
                        <p style="font-size:11px;color:${isActive ? '#6b7280' : '#d1d5db'};margin:0 0 ${isCurrent ? '5px' : '0'};">
                            ${info.description}
                        </p>
                        ${isCurrent ? `<span style="font-size:11px;color:${accentColor};background:${isReplacement ? '#fef2f2' : '#eff6ff'};border:1px solid ${isReplacement ? '#fecaca' : '#bfdbfe'};padding:2px 8px;border-radius:999px;font-weight:500;">
                            <i class="fas fa-map-marker-alt" style="font-size:9px;margin-right:3px;"></i>Current
                        </span>` : ''}
                    </div>
                </div>
            `;
        });

        html += `
            </div>
            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-top:12px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;">
                    <span style="color:#6b7280;font-weight:500;">Overall Progress</span>
                    <span style="color:${accentColor};font-weight:700;">${progressPct}%</span>
                </div>
                <div style="background:#e5e7eb;border-radius:999px;height:6px;overflow:hidden;">
                    <div style="width:${progressPct}%;height:100%;background:${accentColor};border-radius:999px;transition:width .4s ease;"></div>
                </div>
            </div>
        `;

        content.innerHTML = html;
        modal.style.display = 'flex';
        document.body.classList.add('overflow-hidden');
    }

    function closeProgressModal() {
        document.getElementById('progressModal').style.display = 'none';
        document.body.classList.remove('overflow-hidden');
    }

    document.getElementById('progressModal').addEventListener('click', function(e) {
        if (e.target === this) closeProgressModal();
    });

    // Defects
    function viewItemDefects(itemId) {
        fetch(`<?= BASE_URL; ?>/warehousestaffreportdefect?item_id=${itemId}`)
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
        const stColors  = { pending: 'yellow', acknowledged: 'blue', replacement_requested: 'purple', resolved: 'green' };

        const defectsHTML = defects.map(d => {
            const c  = sevColors[d.severity] || 'gray';
            const sc = stColors[d.status]    || 'gray';
            return `
                <div class="border border-gray-100 rounded-xl p-4">
                    <div class="flex items-center gap-2 flex-wrap mb-3">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-${c}-50 text-${c}-700 border border-${c}-200">
                            <i class="fas fa-exclamation-circle text-[9px]"></i>${d.severity.toUpperCase()}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-${sc}-50 text-${sc}-700 border border-${sc}-200">
                            ${d.status.replace('_',' ').toUpperCase()}
                        </span>
                        <span class="ml-auto text-xs text-gray-400">Qty: <strong class="text-gray-600">${d.quantity_defective}</strong></span>
                    </div>
                    <p class="text-sm font-medium text-gray-900 mb-1">${d.defect_type}</p>
                    <p class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 mb-3">${d.defect_description}</p>
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span><i class="fas fa-user mr-1"></i>${d.reporter_name}</span>
                        <span><i class="fas fa-calendar mr-1"></i>${new Date(d.reported_at).toLocaleString()}</span>
                    </div>
                </div>
            `;
        }).join('');

        document.body.insertAdjacentHTML('beforeend', `
            <div id="defectsViewModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle text-orange-400"></i> Defect Reports (${defects.length})
                        </h3>
                        <button onclick="closeDefectsModal()" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 transition-colors">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-5"><div class="space-y-3">${defectsHTML}</div></div>
                    <div class="px-5 py-4 border-t border-gray-100">
                        <button onclick="closeDefectsModal()" class="w-full px-4 py-2 text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">Close</button>
                    </div>
                </div>
            </div>
        `);
        document.body.classList.add('overflow-hidden');
        document.getElementById('defectsViewModal').addEventListener('click', function(e) {
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

        fetch('<?= BASE_URL; ?>/warehousestaffassignreceiverreplace', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ replacement_id: currentAssignReplacementId, receiver_id: receiverId, po_number: currentAssignPoNumber })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert('Receiver assigned successfully!'); closeAssignReceiverModal(); window.location.reload(); }
            else alert('Failed to assign receiver: ' + (data.error || 'Unknown error'));
        })
        .catch(() => alert('Failed to assign receiver. Please try again.'));
    }

    document.getElementById('assignReceiverModal').addEventListener('click', function(e) {
        if (e.target === this) closeAssignReceiverModal();
    });

    // Generate P.O.
    function generateReplacementPO(replacementId) {
        if (!confirm('Generate P.O. number for this replacement item?')) return;

        fetch('<?= BASE_URL; ?>/warehousestaffgeneratereplacement', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ replacement_id: replacementId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert('P.O. Number generated: ' + data.po_number); window.location.reload(); }
            else alert('Failed to generate P.O. number: ' + (data.error || 'Unknown error'));
        })
        .catch(() => alert('Failed to generate P.O. number. Please try again.'));
    }

    // Resolve Defect
    function resolveDefect(itemId, orderId) {
        if (!confirm('Mark this defect as resolved?')) return;

        fetch('<?= BASE_URL; ?>/warehousestaffresolvedefect', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_item_id: itemId, order_id: orderId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert('Defect marked as resolved!'); window.location.reload(); }
            else alert('Failed to resolve defect: ' + (data.error || 'Unknown error'));
        })
        .catch(() => alert('Failed to resolve defect. Please try again.'));
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeProgressModal(); closeDefectsModal(); closeAssignReceiverModal(); }
    });
</script>
</body>
</html>