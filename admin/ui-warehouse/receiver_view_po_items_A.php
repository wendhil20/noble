<?php
// view_po_items.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);
require_subrole(['warehouse_receiver']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$sessionUser = $_SESSION['noble_user'];
$user_id = $_SESSION['noble_id'] ?? null;
$fullname = '';

if (is_array($sessionUser)) {
    if (!$user_id) {
        $user_id = $sessionUser['id'] ?? $sessionUser['user_id'] ?? null;
    }
    $fullname = $sessionUser['fullname'] ?? $sessionUser['name'] ?? '';
}

if (!$user_id && isset($_SESSION['noble_id'])) {
    $user_id = $_SESSION['noble_id'];
}

$po_number = isset($_GET['po_number']) ? trim($_GET['po_number']) : '';
$is_replacement_context = isset($_GET['replacement_id']);
$orderItems = [];
$orderInfo = null;
$supplierInfo = null;

if (!empty($po_number)) {
    $itemStmt = $conn->prepare("
        SELECT 
            oi.id as item_id, oi.order_id, oi.product_id, oi.product_name,
            oi.size, oi.variant_color, oi.codename, oi.descrip6, oi.descrip7,
            oi.quantity, oi.po_number, oi.qr_code, oi.warehouse_location,
            oi.supplier_id, oi.manual_supplier_name,
            'original' as item_type, NULL as replacement_id, NULL as replacement_reason,
            sl.business_name, sl.primary_contact_name, sl.email_address,
            sl.phone_number, sl.business_address,
            o.customer_name, o.email as customer_email,
            o.created_at as order_date, o.status as order_status
        FROM order_items oi
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE oi.po_number = ?

        UNION ALL

        SELECT 
            rr.id as item_id, rr.order_id, oi.product_id, oi.product_name,
            oi.size, oi.variant_color, oi.codename, oi.descrip6, oi.descrip7,
            rr.replacement_quantity as quantity, rr.po_number, rr.qr_code, rr.warehouse_location,
            oi.supplier_id, oi.manual_supplier_name,
            'replacement' as item_type, rr.id as replacement_id, rr.reason as replacement_reason,
            sl.business_name, sl.primary_contact_name, sl.email_address,
            sl.phone_number, sl.business_address,
            o.customer_name, o.email as customer_email,
            o.created_at as order_date, o.status as order_status
        FROM replacement_requests rr
        LEFT JOIN order_items oi ON rr.order_item_id = oi.id
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
        LEFT JOIN orders o ON rr.order_id = o.id
        WHERE rr.po_number = ?

        ORDER BY item_id
    ");

    $itemStmt->bind_param("ss", $po_number, $po_number);
    $itemStmt->execute();
    $result = $itemStmt->get_result();
    $orderItems = $result->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();

    if (!empty($orderItems)) {
        $firstItem = $orderItems[0];
        $orderInfo = [
            'order_id' => $firstItem['order_id'],
            'customer_name' => $firstItem['customer_name'],
            'customer_email' => $firstItem['customer_email'],
            'order_date' => $firstItem['order_date'],
            'order_status' => $firstItem['order_status'],
        ];
        $supplierInfo = [
            'name' => $firstItem['supplier_id'] ? $firstItem['business_name'] : $firstItem['manual_supplier_name'],
            'contact' => $firstItem['primary_contact_name'] ?? 'N/A',
            'email' => $firstItem['email_address'] ?? 'N/A',
            'phone' => $firstItem['phone_number'] ?? 'N/A',
            'address' => $firstItem['business_address'] ?? 'N/A',
            'is_manual' => !$firstItem['supplier_id'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P.O. Items — QR Management</title>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-screen-xl mx-auto px-4 py-6 space-y-6">

        <!-- Page Header -->
        <div class="flex items-center gap-4 no-print">
            <div class="bg-blue-600 p-3 rounded-xl">
                <i class="fas fa-qrcode text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">P.O. Items & QR Management</h1>
                <p class="text-sm text-gray-500">Generate QR codes and manage warehouse locations</p>
            </div>
        </div>

        <!-- Alert -->
        <div id="alertContainer"></div>

        <?php if (!empty($po_number)): ?>

            <?php if (empty($orderItems)): ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
                    <i class="fas fa-inbox text-5xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-700 font-medium">No items found for P.O. <span
                            class="font-mono bg-gray-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($po_number); ?></span>
                    </p>
                    <p class="text-sm text-gray-400 mt-1">Check the P.O. number and try again.</p>
                </div>

            <?php else: ?>

                <!-- P.O. Summary Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

                    <!-- P.O. Title Row -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-file-invoice text-blue-600"></i>
                            <span class="text-sm text-gray-500 font-medium uppercase tracking-wide">Purchase Order</span>
                        </div>
                        <span
                            class="font-mono text-lg font-bold text-gray-900"><?php echo htmlspecialchars($po_number); ?></span>
                    </div>

                    <hr class="border-gray-100">

                    <!-- Order + Supplier Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <!-- Order Info -->
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Order Details</p>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Order ID</span>
                                    <span class="font-medium text-gray-900">#<?php echo $orderInfo['order_id']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Customer</span>
                                    <span
                                        class="font-medium text-gray-900"><?php echo htmlspecialchars($orderInfo['customer_name']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Email</span>
                                    <span
                                        class="font-medium text-gray-900 truncate max-w-[180px]"><?php echo htmlspecialchars($orderInfo['customer_email']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Date</span>
                                    <span
                                        class="font-medium text-gray-900"><?php echo date('M j, Y g:i A', strtotime($orderInfo['order_date'])); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Status</span>
                                    <?php
                                    $statusClasses = [
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'processing' => 'bg-blue-100 text-blue-700',
                                        'completed' => 'bg-green-100 text-green-700',
                                    ];
                                    $statusClass = $statusClasses[$orderInfo['order_status']] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($orderInfo['order_status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Supplier Info -->
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Supplier</p>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Name</span>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-medium text-gray-900"><?php echo htmlspecialchars($supplierInfo['name']); ?></span>
                                        <span
                                            class="text-xs px-2 py-0.5 rounded-full <?php echo $supplierInfo['is_manual'] ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'; ?>">
                                            <?php echo $supplierInfo['is_manual'] ? 'Manual' : 'Linked'; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!$supplierInfo['is_manual']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Contact</span>
                                        <span
                                            class="font-medium text-gray-900"><?php echo htmlspecialchars($supplierInfo['contact']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Email</span>
                                        <span
                                            class="font-medium text-gray-900"><?php echo htmlspecialchars($supplierInfo['email']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Phone</span>
                                        <span
                                            class="font-medium text-gray-900"><?php echo htmlspecialchars($supplierInfo['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Total Items</span>
                                    <span class="font-semibold text-blue-600"><?php echo count($orderItems); ?> item(s)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: Viewer + Badges -->
                    <div class="border-t border-gray-100 pt-4 flex flex-wrap items-center gap-3">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-eye mr-1"></i>
                            Viewed by <strong class="text-gray-700"><?php echo htmlspecialchars($fullname); ?></strong>
                            &mdash; <?php echo date('M j, Y g:i A'); ?>
                        </span>

                        <?php
                        $replAssignSql = "SELECT COUNT(*) as cnt FROM replacement_requests WHERE po_number = ? AND receiver_id = ?";
                        $replAssignStmt = $conn->prepare($replAssignSql);
                        $replAssignStmt->bind_param("si", $po_number, $user_id);
                        $replAssignStmt->execute();
                        $replAssignCount = $replAssignStmt->get_result()->fetch_assoc()['cnt'];
                        $replAssignStmt->close();

                        if ($replAssignCount > 0): ?>
                            <span
                                class="inline-flex items-center gap-1.5 bg-red-50 border border-red-200 text-red-700 text-xs font-medium px-3 py-1.5 rounded-full">
                                <i class="fas fa-sync-alt"></i>
                                Assigned as receiver for <?php echo $replAssignCount; ?> replacement(s)
                            </span>
                        <?php endif; ?>

                        <?php if ($is_replacement_context): ?>
                            <span
                                class="inline-flex items-center gap-1.5 bg-orange-50 border border-orange-200 text-orange-700 text-xs font-medium px-3 py-1.5 rounded-full">
                                <i class="fas fa-sync-alt"></i>
                                Viewing as Replacement Receiver
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                // Reception progress checks
                $allReceivedSql = "SELECT 
                    (SELECT COUNT(*) FROM order_items WHERE po_number = ?) +
                    (SELECT COUNT(*) FROM replacement_requests WHERE po_number = ?) as total,
                    (SELECT COUNT(*) FROM order_items WHERE po_number = ? AND received_status = 'received') +
                    (SELECT COUNT(*) FROM replacement_requests WHERE po_number = ? AND received_status = 'received') as received_count";
                $allReceivedStmt = $conn->prepare($allReceivedSql);
                $allReceivedStmt->bind_param("ssss", $po_number, $po_number, $po_number, $po_number);
                $allReceivedStmt->execute();
                $receivedStats = $allReceivedStmt->get_result()->fetch_assoc();
                $allReceivedStmt->close();

                $allItemsReceived = ($receivedStats['total'] == $receivedStats['received_count']) && $receivedStats['total'] > 0;
                $someItemsReceived = $receivedStats['received_count'] > 0;
                $current_user_id = $user_id;

                $assignmentCheckSql = "SELECT status, completed_at FROM po_receiver_assignments WHERE po_number = ? AND receiver_id = ? LIMIT 1";
                $assignmentCheckStmt = $conn->prepare($assignmentCheckSql);
                $assignmentCheckStmt->bind_param("si", $po_number, $current_user_id);
                $assignmentCheckStmt->execute();
                $assignmentStatus = $assignmentCheckStmt->get_result()->fetch_assoc();
                $assignmentCheckStmt->close();

                $poStatusSql = "SELECT all_items_received, all_items_received_at FROM po_attachments WHERE po_number = ? LIMIT 1";
                $poStatusStmt = $conn->prepare($poStatusSql);
                $poStatusStmt->bind_param("s", $po_number);
                $poStatusStmt->execute();
                $poStatus = $poStatusStmt->get_result()->fetch_assoc();
                $poStatusStmt->close();

                $alreadyMarkedComplete = false;
                $completionDate = null;

                if ($assignmentStatus && $assignmentStatus['status'] == 'completed') {
                    $alreadyMarkedComplete = true;
                    $completionDate = $assignmentStatus['completed_at'];
                } elseif ($poStatus && $poStatus['all_items_received'] == 1) {
                    $alreadyMarkedComplete = true;
                    $completionDate = $poStatus['all_items_received_at'];
                }
                ?>

                <!-- Reception Progress Banner -->
                <?php if (($someItemsReceived || $alreadyMarkedComplete) && !$is_replacement_context): ?>
                    <div
                        class="bg-white rounded-xl border <?php echo $alreadyMarkedComplete ? 'border-green-300' : ($allItemsReceived ? 'border-green-300' : 'border-blue-200'); ?> p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div
                                    class="<?php echo $alreadyMarkedComplete || $allItemsReceived ? 'bg-green-500' : 'bg-blue-500'; ?> p-2.5 rounded-lg shrink-0">
                                    <i
                                        class="fas fa-<?php echo $alreadyMarkedComplete ? 'check-double' : ($allItemsReceived ? 'check-circle' : 'tasks'); ?> text-white text-lg"></i>
                                </div>
                                <div class="space-y-2">
                                    <p class="font-semibold text-gray-900">
                                        <?php
                                        if ($alreadyMarkedComplete)
                                            echo 'P.O. Completely Received';
                                        elseif ($allItemsReceived)
                                            echo 'All Items Received';
                                        else
                                            echo 'Reception In Progress';
                                        ?>
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm text-gray-600">
                                            <strong><?php echo $receivedStats['received_count']; ?></strong> of
                                            <strong><?php echo $receivedStats['total']; ?></strong> items received
                                        </span>
                                        <div class="flex-1 min-w-[120px] bg-gray-100 rounded-full h-2">
                                            <div class="<?php echo ($alreadyMarkedComplete || $allItemsReceived) ? 'bg-green-500' : 'bg-blue-500'; ?> h-2 rounded-full"
                                                style="width: <?php echo ($receivedStats['received_count'] / $receivedStats['total']) * 100; ?>%">
                                            </div>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-700">
                                            <?php echo round(($receivedStats['received_count'] / $receivedStats['total']) * 100); ?>%
                                        </span>
                                    </div>
                                    <?php if ($alreadyMarkedComplete && $completionDate): ?>
                                        <p class="text-xs text-green-600">
                                            <i class="fas fa-check-double mr-1"></i>
                                            Marked complete on <?php echo date('M j, Y g:i A', strtotime($completionDate)); ?>
                                        </p>
                                    <?php elseif ($allItemsReceived): ?>
                                        <p class="text-xs text-green-600">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Ready to mark as completely received
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($alreadyMarkedComplete): ?>
                                <span
                                    class="inline-flex items-center gap-2 bg-green-100 text-green-700 border border-green-300 px-4 py-2 rounded-lg text-sm font-semibold">
                                    <i class="fas fa-check-circle"></i> Complete
                                </span>
                            <?php elseif ($allItemsReceived): ?>
                                <button onclick="markPOAsCompletelyReceived('<?php echo htmlspecialchars($po_number, ENT_QUOTES); ?>')"
                                    class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors flex items-center gap-2 whitespace-nowrap">
                                    <i class="fas fa-clipboard-check"></i>
                                    Mark as Completely Received
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Replacement Received Notice -->
                <?php if ($is_replacement_context && $someItemsReceived): ?>
                    <div class="bg-green-50 border border-green-300 rounded-xl p-5 flex items-center gap-4">
                        <div class="bg-green-500 p-2.5 rounded-lg">
                            <i class="fas fa-check-double text-white text-lg"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-green-900">Replacement Completed</p>
                            <p class="text-sm text-green-700 mt-0.5">This replacement item has been received and stored in the
                                warehouse.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Items Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($orderItems as $index => $item): ?>

                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden flex flex-col">

                            <!-- Card Header -->
                            <div class="bg-red-600 px-4 py-3 flex items-center justify-between">
                                <span class="text-white font-semibold text-sm">Item #<?php echo $index + 1; ?></span>
                                <?php if (!empty($item['qr_code'])): ?>
                                    <span class="bg-white bg-opacity-20 text-white text-xs font-medium px-2 py-0.5 rounded-full">
                                        <i class="fas fa-check-circle mr-1"></i>QR Ready
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Replacement Badge -->
                            <?php if (isset($item['item_type']) && $item['item_type'] === 'replacement'): ?>
                                <div
                                    class="mx-4 mt-3 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-sync-alt text-orange-500 text-sm"></i>
                                        <span class="text-orange-800 font-semibold text-xs uppercase tracking-wide">Replacement</span>
                                    </div>
                                    <?php if (!empty($item['replacement_reason'])): ?>
                                        <span class="text-xs text-orange-600 bg-orange-100 px-2 py-0.5 rounded">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $item['replacement_reason']))); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Item Details -->
                            <div class="p-4 flex-1 space-y-4">
                                <h3 class="font-bold text-gray-900 leading-snug">
                                    <?php echo htmlspecialchars($item['product_name']); ?></h3>

                                <div class="space-y-1.5 text-sm text-gray-600">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-barcode w-4 text-gray-400"></i>
                                        <span><?php echo htmlspecialchars($item['codename']); ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-ruler w-4 text-gray-400"></i>
                                        <span><?php echo htmlspecialchars($item['size']); ?> &bull;
                                            <?php echo htmlspecialchars($item['variant_color']); ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-box w-4 text-gray-400"></i>
                                        <span>Qty: <?php echo $item['quantity']; ?>
                                            <?php echo htmlspecialchars($item['descrip6'] ?: 'pcs'); ?></span>
                                    </div>
                                    <?php if (!empty($item['warehouse_location'])): ?>
                                        <div class="flex items-start gap-2 bg-blue-50 rounded-lg px-3 py-2 mt-2">
                                            <i class="fas fa-map-marker-alt text-blue-500 mt-0.5 w-4 shrink-0"></i>
                                            <div>
                                                <p class="text-xs text-gray-400 mb-0.5">Location</p>
                                                <p class="font-semibold text-blue-700 text-sm">
                                                    <?php echo htmlspecialchars($item['warehouse_location']); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- QR Code Preview -->
                                <?php if (!empty($item['qr_code'])): ?>
                                    <div class="flex flex-col items-center gap-2 py-2">
                                        <div class="border border-gray-200 rounded-lg p-2 bg-white">
                                            <div id="qr-display-<?php echo $item['item_id']; ?>"></div>
                                        </div>
                                        <p class="text-xs font-mono text-gray-400 text-center break-all">
                                            <?php echo htmlspecialchars($item['qr_code']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="px-4 pb-4 space-y-2">
                                <?php if (empty($item['qr_code'])): ?>
                                    <button
                                        onclick="openQRModal(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES); ?>', '<?php echo $item['item_type']; ?>')"
                                        class="w-full bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                                        <i class="fas fa-qrcode"></i>
                                        Generate QR Code
                                    </button>
                                <?php else: ?>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button
                                            onclick="downloadQR(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['qr_code'], ENT_QUOTES); ?>')"
                                            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-3 py-2 rounded-lg transition-colors flex items-center justify-center gap-1.5">
                                            <i class="fas fa-download text-xs"></i> Download
                                        </button>
                                        <button
                                            onclick="openEditLocationModal(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['warehouse_location'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES); ?>', '<?php echo $item['item_type']; ?>')"
                                            class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-3 py-2 rounded-lg transition-colors flex items-center justify-center gap-1.5">
                                            <i class="fas fa-map-marker-alt text-xs"></i> Location
                                        </button>
                                    </div>
                                    <button onclick="resetQR(<?php echo $item['item_id']; ?>, '<?php echo $item['item_type']; ?>')"
                                        class="w-full bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center justify-center gap-2">
                                        <i class="fas fa-times-circle text-xs"></i>
                                        Reset QR & Location
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>

                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        <?php endif; ?>

    </div><!-- end container -->


    <!-- ===== QR Generation Modal ===== -->
    <div id="qrModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="bg-green-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-white font-bold text-lg flex items-center gap-2">
                    <i class="fas fa-qrcode"></i> Generate QR Code
                </h3>
                <button onclick="closeQRModal()" class="text-white hover:text-green-200 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-5">
                <p id="modalItemName" class="font-semibold text-gray-900 text-base"></p>

                <!-- QR Preview -->
                <div class="flex flex-col items-center gap-3 bg-gray-50 rounded-xl p-4">
                    <div class="bg-white border border-gray-200 rounded-lg p-3">
                        <div id="qrcode"></div>
                    </div>
                    <p id="qrCodeValue" class="text-xs font-mono text-gray-500 text-center break-all"></p>
                </div>

                <!-- Note -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5 shrink-0"></i>
                    <p class="text-sm text-blue-800">You can assign the warehouse location after scanning this QR code.
                    </p>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button id="saveQRBtn" onclick="saveQRCode()"
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> Save QR Code
                    </button>
                    <button onclick="closeQRModal()"
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- ===== Edit Location Modal ===== -->
    <div id="editLocationModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="bg-amber-500 px-6 py-4 flex items-center justify-between">
                <h3 class="text-white font-bold text-lg flex items-center gap-2">
                    <i class="fas fa-map-marker-alt"></i> Edit Location
                </h3>
                <button onclick="closeEditLocationModal()" class="text-white hover:text-amber-200 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-5">
                <p id="editModalItemName" class="font-semibold text-gray-900 text-base"></p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-map-marker-alt mr-1 text-amber-500"></i> Warehouse Location
                    </label>
                    <input type="text" id="editWarehouseLocation" placeholder="e.g., Aisle A, Shelf 3, Bin 5"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent">
                    <p class="text-xs text-gray-400 mt-1.5">Update the physical storage location of this item.</p>
                </div>

                <div class="flex gap-3">
                    <button onclick="updateLocation()"
                        class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> Update Location
                    </button>
                    <button onclick="closeEditLocationModal()"
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        let currentItemId = null;
        let currentQRCode = null;

        document.addEventListener('DOMContentLoaded', function () {
            <?php foreach ($orderItems as $item): ?>
                <?php if (!empty($item['qr_code'])): ?>
                    generateQRCodeDisplay(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['qr_code'], ENT_QUOTES); ?>');
                <?php endif; ?>
            <?php endforeach; ?>
        });

        function generateQRCodeDisplay(itemId, qrValue) {
            const el = document.getElementById('qr-display-' + itemId);
            if (el) {
                el.innerHTML = '';
                new QRCode(el, { text: qrValue, width: 100, height: 100 });
            }
        }

        function showAlert(message, type = 'info') {
            const colors = {
                success: 'bg-green-50 border-green-300 text-green-800',
                error: 'bg-red-50 border-red-300 text-red-800',
                info: 'bg-blue-50 border-blue-300 text-blue-800',
            };
            const container = document.getElementById('alertContainer');
            container.innerHTML = `
                <div class="border rounded-lg px-4 py-3 text-sm font-medium ${colors[type] || colors.info}">
                    ${message}
                </div>`;
            setTimeout(() => container.innerHTML = '', 5000);
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function openQRModal(itemId, productName, itemType = 'original') {
            currentItemId = itemId;
            window.currentItemType = itemType;

            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            currentQRCode = itemType === 'replacement'
                ? `${window.location.origin}${basePath}receiverscanreplacement?replacement_id=${itemId}`
                : `${window.location.origin}${basePath}receiverscanitem?item_id=${itemId}`;

            const typeLabel = itemType === 'replacement' ? ' [REPLACEMENT]' : '';
            document.getElementById('modalItemName').textContent = productName + typeLabel;
            document.getElementById('qrcode').innerHTML = '';
            new QRCode(document.getElementById('qrcode'), { text: currentQRCode, width: 200, height: 200 });
            document.getElementById('qrCodeValue').textContent = currentQRCode;
            document.getElementById('qrModal').classList.remove('hidden');
        }

        function closeQRModal() {
            document.getElementById('qrModal').classList.add('hidden');
            currentItemId = null;
            currentQRCode = null;
        }

        function saveQRCode() {
            if (!currentItemId || !currentQRCode) return;

            const saveBtn = document.getElementById('saveQRBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('<?= BASE_URL ?>/receiversaveqr', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: currentItemId, qr_code: currentQRCode, item_type: window.currentItemType || 'original' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert('QR code saved! Reloading...', 'success');
                        closeQRModal();
                        setTimeout(() => window.location.reload(true), 500);
                    } else {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save QR Code';
                        showAlert('Failed to save: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(err => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Save QR Code';
                    showAlert('Error: ' + err.message, 'error');
                });
        }

        function openEditLocationModal(itemId, currentLocation, productName, itemType = 'original') {
            currentItemId = itemId;
            window.currentEditItemType = itemType;
            document.getElementById('editModalItemName').textContent = productName;
            document.getElementById('editWarehouseLocation').value = currentLocation;
            document.getElementById('editLocationModal').classList.remove('hidden');
        }

        function closeEditLocationModal() {
            document.getElementById('editLocationModal').classList.add('hidden');
            currentItemId = null;
        }

        function updateLocation() {
            const location = document.getElementById('editWarehouseLocation').value.trim();
            if (!location || !currentItemId) { alert('Please enter a warehouse location.'); return; }

            fetch('receiver_update_location_A3.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: currentItemId, warehouse_location: location, item_type: window.currentEditItemType || 'original' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Location updated! Reloading...', 'success');
                        closeEditLocationModal();
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        showAlert('Failed to update: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function downloadQR(itemId, qrValue) {
            const itemCard = document.querySelector(`#qr-display-${itemId}`);
            if (!itemCard) return;

            const parentCard = itemCard.closest('.bg-white.rounded-xl');
            if (!parentCard) return;

            const productName = parentCard.querySelector('h3')?.textContent.trim() || 'Unknown';
            const barcodeEl = parentCard.querySelector('.fa-barcode');
            const codename = barcodeEl ? barcodeEl.parentElement.textContent.trim() : 'N/A';
            const rulerEl = parentCard.querySelector('.fa-ruler');
            const specification = rulerEl ? rulerEl.parentElement.textContent.trim() : 'N/A';
            const boxEl = parentCard.querySelector('.fa-box');
            const quantity = boxEl ? boxEl.parentElement.textContent.trim() : 'N/A';
            const locationEl = parentCard.querySelector('.text-blue-700');
            const location = locationEl ? locationEl.textContent.trim() : 'Not Set';

            const qrCanvas = itemCard.querySelector('canvas');
            if (!qrCanvas) { alert('QR canvas not found'); return; }

            const finalCanvas = document.createElement('canvas');
            const ctx = finalCanvas.getContext('2d');
            const qrSize = 300, padding = 20, infoHeight = 280;
            finalCanvas.width = qrSize + padding * 2;
            finalCanvas.height = qrSize + infoHeight + padding * 3;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);
            ctx.strokeStyle = '#e5e7eb';
            ctx.lineWidth = 2;
            ctx.strokeRect(5, 5, finalCanvas.width - 10, finalCanvas.height - 10);
            ctx.drawImage(qrCanvas, padding, padding, qrSize, qrSize);

            let yPos = qrSize + padding * 2 + 10;
            ctx.fillStyle = '#111827';
            ctx.font = 'bold 18px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('ITEM INFORMATION', finalCanvas.width / 2, yPos);
            yPos += 25;
            ctx.strokeStyle = '#d1d5db';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(padding, yPos);
            ctx.lineTo(finalCanvas.width - padding, yPos);
            ctx.stroke();
            yPos += 25;

            function wrapText(text, maxWidth) {
                const words = text.split(' ');
                const lines = [];
                let line = words[0];
                for (let i = 1; i < words.length; i++) {
                    if (ctx.measureText(line + ' ' + words[i]).width < maxWidth) line += ' ' + words[i];
                    else { lines.push(line); line = words[i]; }
                }
                lines.push(line);
                return lines;
            }

            ctx.textAlign = 'left';
            const labelX = padding + 5, valueX = padding + 80, maxW = finalCanvas.width - valueX - padding - 5;

            function drawRow(label, value) {
                ctx.font = 'bold 13px Arial';
                ctx.fillStyle = '#374151';
                ctx.fillText(label, labelX, yPos);
                ctx.font = '13px Arial';
                ctx.fillStyle = '#111827';
                const lines = wrapText(value, maxW);
                lines.forEach((l, i) => ctx.fillText(l, valueX, yPos + i * 18));
                yPos += lines.length * 18 + 8;
            }

            drawRow('Product:', productName);
            drawRow('Code:', codename);
            drawRow('Spec:', specification);
            drawRow('Quantity:', quantity);

            ctx.fillStyle = '#dbeafe';
            ctx.fillRect(padding, yPos - 18, finalCanvas.width - padding * 2, 28);
            ctx.font = 'bold 13px Arial';
            ctx.fillStyle = '#1e40af';
            ctx.fillText('Location:', labelX, yPos);
            ctx.fillStyle = '#1e3a8a';
            wrapText(location, maxW).forEach((l, i) => ctx.fillText(l, valueX, yPos + i * 16));
            yPos += 43;
            ctx.font = '10px monospace';
            ctx.fillStyle = '#6b7280';
            ctx.textAlign = 'center';
            ctx.fillText(qrValue, finalCanvas.width / 2, yPos);

            finalCanvas.toBlob(blob => {
                if (!blob) return;
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `QR_${productName.replace(/[^a-z0-9]/gi, '_').substring(0, 30)}_Item${itemId}.png`;
                document.body.appendChild(a);
                a.click();
                setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(url); }, 100);
                showAlert('QR code downloaded!', 'success');
            }, 'image/png');
        }

        function resetQR(itemId, itemType = 'original') {
            if (!confirm('Reset the QR code and location for this item? This cannot be undone.')) return;

            fetch('<?= BASE_URL ?>/receiverresetqr', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: itemId, item_type: itemType })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert('QR code reset! Reloading...', 'success');
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        showAlert('Failed to reset: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(err => showAlert('Error: ' + err.message, 'error'));
        }

        function markPOAsCompletelyReceived(poNumber) {
            if (!confirm('Mark this P.O. as completely received?\n\nThis will update the status and notify relevant departments.')) return;

            fetch('<?= BASE_URL ?>/receivermarkpo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ po_number: poNumber })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ P.O. marked as completely received!\n\n' + data.message);
                        window.location.reload();
                    } else {
                        alert('✗ Failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => alert('✗ Failed to mark P.O. as complete. Please try again.'));
        }

        // Close modals on backdrop click or Escape
        ['qrModal', 'editLocationModal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function (e) {
                if (e.target === this) {
                    if (id === 'qrModal') closeQRModal();
                    else closeEditLocationModal();
                }
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') { closeQRModal(); closeEditLocationModal(); }
        });
    </script>

</body>

</html>