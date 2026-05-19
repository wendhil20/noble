<?php
//warehouse_staff_generate_po_A-B.php (Enhanced with supplier change feature)
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['warehouse', 'superadmin', 'sales']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

if (!isset($_GET['order_id'])) {
    header("Location: " . BASE_URL . "/warehousestaff");
    exit();
}

$order_id = intval($_GET['order_id']);

$orderStmt = $conn->prepare("SELECT id, customer_name, email, created_at, status, total FROM orders WHERE id = ? LIMIT 1");
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();

if ($orderResult->num_rows === 0) {
    header("Location: ordering.php");
    exit();
}

$order = $orderResult->fetch_assoc();
$orderStmt->close();

$prepared_by = $_SESSION['noble_name'] ?? 'Unknown User';
$user_role = $_SESSION['noble_lvl'] ?? 'Unknown Role';
$user_id = $_SESSION['noble_id'] ?? null;

function generateCustomPONumber($supplier_id) {
    $date = date('mdY');
    $time = date('Gis');
    return 'NH' . $date . $time . $supplier_id;
}

$editing_po_id = isset($_GET['edit_po']) ? (int)$_GET['edit_po'] : 0;
$existing_po_data = null;

if ($editing_po_id > 0) {
    $poDataStmt = $conn->prepare("SELECT * FROM po_attachments WHERE id = ? AND order_id = ? LIMIT 1");
    $poDataStmt->bind_param("ii", $editing_po_id, $order_id);
    $poDataStmt->execute();
    $existing_po_data = $poDataStmt->get_result()->fetch_assoc();
    $poDataStmt->close();

    if (!$existing_po_data) {
        header("Location: " . BASE_URL . "/warehousestaffpomanagement?order_id=" . $order_id);
        exit();
    }
}

$poRejectionsSql = "SELECT id, supplier_name, superadmin_approval_status, approval_status, superadmin_rejection_reason, rejection_reason FROM po_attachments WHERE order_id = ? AND (superadmin_approval_status = 'rejected' OR approval_status = 'rejected')";
$poRejectionsStmt = $conn->prepare($poRejectionsSql);
$poRejectionsStmt->bind_param("i", $order_id);
$poRejectionsStmt->execute();
$rejectedPOs = $poRejectionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$poRejectionsStmt->close();

$rejectedSuppliers = [];
foreach ($rejectedPOs as $rejection) {
    $rejectedSuppliers[$rejection['supplier_name']] = $rejection;
}

$itemStmt = $conn->prepare("
    SELECT 
        oi.id as item_id, oi.order_id, oi.product_id, oi.variant_id,
        oi.product_name, oi.size, oi.variant_color, oi.codename,
        oi.descrip6, oi.descrip7, oi.price as order_price, oi.quantity,
        oi.subtotal as original_subtotal, oi.origin, oi.supplier_id, oi.po_number,
        slp.supplier_price,
        COALESCE(slp.supplier_price, oi.price) as current_price,
        (COALESCE(slp.supplier_price, oi.price) * oi.quantity) as calculated_subtotal,
        sl.business_name, sl.primary_contact_name, sl.email_address,
        sl.phone_number, sl.business_address,
        pv.namevariant, pv.color as variant_color_db, pv.size as variant_size_db
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    LEFT JOIN supp_link_products slp ON oi.variant_id = slp.variant_id 
        AND oi.supplier_id = slp.supplier_id AND slp.status = 'active'
    WHERE oi.order_id = ? AND oi.supplier_id IS NOT NULL
    ORDER BY oi.id
");
$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

$suppliersStmt = $conn->prepare("SELECT id, business_name, primary_contact_name, email_address, phone_number FROM supplier_list WHERE status = 'active' ORDER BY business_name ASC");
$suppliersStmt->execute();
$availableSuppliers = $suppliersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$suppliersStmt->close();

$supplierGroups = [];
foreach ($allItems as $item) {
    $supplierKey = strval($item['supplier_id']);

    if (!isset($supplierGroups[$supplierKey])) {
        $existing_po = null;
        $po_attachment_id = null;
        $po_approval_status = null;
        $po_superadmin_approval_status = null;

        $poAttachmentSql = "SELECT id, po_number, approval_status, superadmin_approval_status FROM po_attachments WHERE order_id = ? AND supplier_name = (SELECT business_name FROM supplier_list WHERE id = ? LIMIT 1) ORDER BY id DESC LIMIT 1";
        $poAttachmentStmt = $conn->prepare($poAttachmentSql);
        $poAttachmentStmt->bind_param("ii", $order_id, $item['supplier_id']);
        $poAttachmentStmt->execute();
        $poAttachmentResult = $poAttachmentStmt->get_result();

        if ($poAttachmentRow = $poAttachmentResult->fetch_assoc()) {
            $po_attachment_id = $poAttachmentRow['id'];
            $existing_po = $poAttachmentRow['po_number'];
            $po_approval_status = $poAttachmentRow['approval_status'];
            $po_superadmin_approval_status = $poAttachmentRow['superadmin_approval_status'];
        }
        $poAttachmentStmt->close();

        if (!$existing_po) {
            foreach ($allItems as $checkItem) {
                $checkKey = strval($checkItem['supplier_id']);
                if ($checkKey === $supplierKey && !empty($checkItem['po_number'])) {
                    $existing_po = $checkItem['po_number'];
                    $poAttachmentSql2 = "SELECT id, approval_status, superadmin_approval_status FROM po_attachments WHERE order_id = ? AND po_number = ? LIMIT 1";
                    $poAttachmentStmt2 = $conn->prepare($poAttachmentSql2);
                    $poAttachmentStmt2->bind_param("is", $order_id, $existing_po);
                    $poAttachmentStmt2->execute();
                    $poAttachmentResult2 = $poAttachmentStmt2->get_result();
                    if ($poAttachmentRow2 = $poAttachmentResult2->fetch_assoc()) {
                        $po_attachment_id = $poAttachmentRow2['id'];
                        $po_approval_status = $poAttachmentRow2['approval_status'];
                        $po_superadmin_approval_status = $poAttachmentRow2['superadmin_approval_status'];
                    }
                    $poAttachmentStmt2->close();
                    break;
                }
            }
        }

        $supplierGroups[$supplierKey] = [
            'supplier_info' => [
                'name' => $item['business_name'],
                'contact' => $item['primary_contact_name'] ?? '',
                'email' => $item['email_address'] ?? '',
                'phone' => $item['phone_number'] ?? '',
                'address' => $item['business_address'] ?? '',
                'supplier_id' => $item['supplier_id'],
                'existing_po' => $existing_po,
                'po_attachment_id' => $po_attachment_id,
                'po_approval_status' => $po_approval_status,
                'po_superadmin_approval_status' => $po_superadmin_approval_status
            ],
            'items' => []
        ];
    }

    $supplierGroups[$supplierKey]['items'][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate P.O. — Order #<?php echo $order['id']; ?></title>
</head>

<body class="bg-slate-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- Top Nav Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-20 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">

                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>/warehousestaffpomanagement?order_id=<?= $order['id'] ?>"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors"
                        title="Back">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/warehouseheadstaffpomanagement?order_id=<?= $order['id'] ?>"
                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-500 hover:bg-blue-600 transition-colors"
                        title="View P.O. Files">
                        <i class="fas fa-file-excel text-white text-xs"></i>
                    </a>

                    <div class="w-px h-5 bg-slate-200"></div>

                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 flex items-center justify-center rounded-lg bg-emerald-500">
                            <i class="fas fa-file-invoice text-white text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 leading-none">Generate Purchase Order</p>
                            <p class="text-xs text-slate-400 leading-none mt-0.5">
                                Order #<?= $order['id'] ?> &mdash; <?= htmlspecialchars($order['customer_name']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <span class="text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-full">
                    P.O. Generator
                </span>
            </div>
        </div>
    </nav>

    <!-- Main -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

        <!-- Alert -->
        <div id="alertContainer"></div>

        <?php if (empty($supplierGroups)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                <div class="w-14 h-14 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-amber-500 text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-slate-800 mb-1">No Suppliers Assigned</h3>
                <p class="text-sm text-slate-500 mb-5">Assign suppliers to order items before generating purchase orders.</p>
                <a href="warehouse_staff_po_management_A.php?order_id=<?= $order['id'] ?>"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-arrow-left text-xs"></i>
                    Back to P.O. Management
                </a>
            </div>

        <?php else: ?>

            <!-- ─── SUPPLIER LIST ─────────────────────────────────────────── -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <!-- Section Header -->
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Suppliers & Items</h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <?= count($supplierGroups) ?> supplier(s) assigned to this order
                        </p>
                    </div>
                    <span class="text-xs text-slate-400">Click a supplier row to expand items</span>
                </div>

                <div class="divide-y divide-slate-100">
                    <?php foreach ($supplierGroups as $supplierKey => $supplierData): ?>
                        <?php
                        $info = $supplierData['supplier_info'];
                        $isRejected = isset($rejectedSuppliers[$info['name']]);
                        $isFullyApproved = ($info['po_superadmin_approval_status'] == 'approved' && $info['po_approval_status'] == 'approved');
                        $isPending = (($info['po_superadmin_approval_status'] == 'pending' || $info['po_approval_status'] == 'pending') && !$isRejected);
                        $hasPO = !empty($info['existing_po']);
                        ?>

                        <div class="supplier-group" data-supplier="<?= htmlspecialchars($supplierKey) ?>">

                            <!-- Supplier Row -->
                            <div class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50 transition-colors cursor-pointer"
                                 onclick="toggleItemsList('<?= $supplierKey ?>')">

                                <!-- Left: Supplier Avatar + Info -->
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0
                                    <?= $isRejected ? 'bg-red-100' : ($isFullyApproved ? 'bg-emerald-100' : 'bg-slate-100') ?>">
                                    <i class="fas fa-building text-sm
                                        <?= $isRejected ? 'text-red-500' : ($isFullyApproved ? 'text-emerald-600' : 'text-slate-500') ?>"></i>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-semibold text-slate-800">
                                            <?= htmlspecialchars($info['name']) ?>
                                        </span>

                                        <!-- Status Badge -->
                                        <?php if ($isRejected): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                <i class="fas fa-times-circle text-xs"></i> Rejected
                                            </span>
                                        <?php elseif ($isFullyApproved): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                                <i class="fas fa-check-circle text-xs"></i> Approved
                                            </span>
                                        <?php elseif ($isPending): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                                <i class="fas fa-clock text-xs"></i> Pending
                                            </span>
                                        <?php elseif ($hasPO): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                <i class="fas fa-file-alt text-xs"></i> P.O. Created
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                                No P.O. Yet
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex items-center gap-3 mt-0.5 text-xs text-slate-400">
                                        <?php if ($info['contact']): ?>
                                            <span><i class="fas fa-user mr-1"></i><?= htmlspecialchars($info['contact']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($info['email']): ?>
                                            <span><i class="fas fa-envelope mr-1"></i><?= htmlspecialchars($info['email']) ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-box mr-1"></i><?= count($supplierData['items']) ?> item(s)</span>
                                    </div>

                                    <?php if ($hasPO): ?>
                                        <div class="mt-1">
                                            <span class="text-xs font-mono text-slate-500">
                                                P.O.: <?= htmlspecialchars($info['existing_po']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Right: Action Buttons -->
                                <div class="flex items-center gap-2 flex-shrink-0" onclick="event.stopPropagation()">
                                    <?php if (!empty($info['po_attachment_id'])): ?>
                                        <?php if ($isFullyApproved): ?>
                                            <a href="<?= BASE_URL ?>/warehouseheadstaffpomanagement?order_id=<?= $order['id'] ?>"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded-lg transition-colors">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button disabled class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-200 text-slate-400 text-xs font-medium rounded-lg cursor-not-allowed">
                                                <i class="fas fa-lock"></i> Locked
                                            </button>
                                        <?php elseif ($isPending): ?>
                                            <button disabled class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-200 text-amber-600 text-xs font-medium rounded-lg cursor-not-allowed">
                                                <i class="fas fa-clock"></i> Awaiting Approval
                                            </button>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>/warehouseheadstaffpomanagement?order_id=<?= $order['id'] ?>&edit_po=<?= $info['po_attachment_id'] ?>"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 
                                                    <?= $isRejected ? 'bg-red-500 hover:bg-red-600' : 'bg-blue-500 hover:bg-blue-600' ?> 
                                                    text-white text-xs font-medium rounded-lg transition-colors">
                                                <i class="fas fa-edit"></i>
                                                <?= $isRejected ? 'Fix P.O.' : 'Edit P.O.' ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button onclick="selectSupplier('<?= htmlspecialchars($supplierKey) ?>')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-medium rounded-lg transition-colors">
                                            <i class="fas fa-file-invoice"></i> Generate P.O.
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($hasPO): ?>
                                        <button onclick="resetPONumber('<?= htmlspecialchars($supplierKey) ?>')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 hover:border-red-300 hover:text-red-600 text-slate-500 text-xs font-medium rounded-lg transition-colors"
                                            title="Reset P.O.">
                                            <i class="fas fa-redo-alt"></i> Reset
                                        </button>
                                    <?php endif; ?>

                                    <div class="w-7 h-7 flex items-center justify-center text-slate-400">
                                        <i id="itemsIcon-<?= $supplierKey ?>" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Rejection Reason Banner -->
                            <?php if ($isRejected): ?>
                                <?php $rejection = $rejectedSuppliers[$info['name']]; ?>
                                <div class="mx-5 mb-3 flex items-start gap-3 p-3 bg-red-50 border border-red-200 rounded-lg text-xs">
                                    <i class="fas fa-exclamation-circle text-red-500 mt-0.5 flex-shrink-0"></i>
                                    <div>
                                        <p class="font-semibold text-red-800 mb-0.5">Rejection Reason</p>
                                        <p class="text-red-700">
                                            <?php
                                            if ($rejection['superadmin_approval_status'] == 'rejected') {
                                                echo '<strong>Superadmin:</strong> ' . htmlspecialchars($rejection['superadmin_rejection_reason']);
                                            } elseif ($rejection['approval_status'] == 'rejected') {
                                                echo '<strong>Document Controller:</strong> ' . htmlspecialchars($rejection['rejection_reason']);
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Expandable Items List -->
                            <div id="itemsList-<?= $supplierKey ?>" class="hidden">
                                <div class="bg-slate-50 border-t border-slate-100 px-5 py-4">

                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                                        Items (<?= count($supplierData['items']) ?>)
                                    </p>

                                    <div class="space-y-3">
                                        <?php foreach ($supplierData['items'] as $item): ?>
                                            <div class="bg-white rounded-lg border border-slate-200 overflow-hidden"
                                                 data-item-id="<?= $item['item_id'] ?>">

                                                <!-- Item Main Row -->
                                                <div class="flex items-start gap-4 p-4">
                                                    <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                                        <i class="fas fa-cube text-slate-400 text-xs"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-semibold text-slate-800">
                                                            <?= htmlspecialchars($item['product_name']) ?>
                                                            <?php if ($item['namevariant']): ?>
                                                                <span class="font-normal text-slate-400">— <?= htmlspecialchars($item['namevariant']) ?></span>
                                                            <?php endif; ?>
                                                        </p>

                                                        <!-- Details chips -->
                                                        <div class="flex flex-wrap gap-2 mt-2">
                                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs">
                                                                Code: <?= htmlspecialchars($item['codename']) ?>
                                                            </span>
                                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs">
                                                                Size: <?= htmlspecialchars($item['variant_size_db'] ?? $item['size']) ?>
                                                            </span>
                                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs">
                                                                Color: <?= htmlspecialchars($item['variant_color_db'] ?? $item['variant_color']) ?>
                                                            </span>
                                                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs font-medium">
                                                                Qty: <?= $item['quantity'] ?>
                                                            </span>
                                                            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded text-xs font-medium">
                                                                ₱<?= number_format($item['current_price'], 2) ?> / unit
                                                            </span>
                                                            <span class="px-2 py-0.5 bg-slate-800 text-white rounded text-xs font-semibold">
                                                                Total: ₱<?= number_format($item['calculated_subtotal'], 2) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Change Supplier Toggle -->
                                                <div class="border-t border-slate-100">
                                                    <button onclick="toggleSupplierOptions(<?= $item['item_id'] ?>)"
                                                        class="w-full flex items-center justify-between px-4 py-2.5 bg-amber-50 hover:bg-amber-100 transition-colors text-left">
                                                        <span class="text-xs font-medium text-amber-700 flex items-center gap-1.5">
                                                            <i class="fas fa-exchange-alt"></i>
                                                            Change Supplier for this Item
                                                        </span>
                                                        <i id="supplierIcon-<?= $item['item_id'] ?>" class="fas fa-chevron-down text-amber-500 text-xs transition-transform duration-200"></i>
                                                    </button>

                                                    <!-- Supplier Options -->
                                                    <div id="supplierOptions-<?= $item['item_id'] ?>" class="hidden">
                                                        <div class="p-4 bg-white border-t border-amber-100">
                                                            <?php
                                                            $linkedSuppStmt = $conn->prepare("
                                                                SELECT slp.supplier_id, slp.supplier_type, slp.supplier_price,
                                                                       sl.business_name, sl.primary_contact_name, sl.email_address
                                                                FROM supp_link_products slp
                                                                INNER JOIN supplier_list sl ON slp.supplier_id = sl.id
                                                                WHERE slp.variant_id = ? AND slp.status = 'active' AND sl.status = 'active'
                                                                ORDER BY CASE slp.supplier_type WHEN 'primary' THEN 1 WHEN 'secondary' THEN 2 ELSE 3 END ASC
                                                            ");
                                                            $linkedSuppStmt->bind_param("i", $item['variant_id']);
                                                            $linkedSuppStmt->execute();
                                                            $availSuppliers = $linkedSuppStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                                            $linkedSuppStmt->close();
                                                            ?>

                                                            <?php if (!empty($availSuppliers)): ?>
                                                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                                                                    Available Suppliers
                                                                </p>
                                                                <div class="space-y-2">
                                                                    <?php foreach ($availSuppliers as $supplier): ?>
                                                                        <div class="flex items-center justify-between p-3 border border-slate-200 rounded-lg hover:border-slate-300 transition-colors">
                                                                            <div>
                                                                                <div class="flex items-center gap-2">
                                                                                    <span class="text-sm font-medium text-slate-800">
                                                                                        <?= htmlspecialchars($supplier['business_name']) ?>
                                                                                    </span>
                                                                                    <span class="text-xs px-1.5 py-0.5 rounded font-medium
                                                                                        <?= $supplier['supplier_type'] === 'primary' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' ?>">
                                                                                        <?= $supplier['supplier_type'] === 'primary' ? '⭐ Primary' : 'Secondary' ?>
                                                                                    </span>
                                                                                </div>
                                                                                <div class="flex items-center gap-3 mt-0.5 text-xs text-slate-400">
                                                                                    <?php if ($supplier['supplier_price']): ?>
                                                                                        <span class="font-semibold text-emerald-600">
                                                                                            ₱<?= number_format($supplier['supplier_price'], 2) ?>
                                                                                        </span>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($supplier['primary_contact_name']): ?>
                                                                                        <span><?= htmlspecialchars($supplier['primary_contact_name']) ?></span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <button onclick="reassignItemSupplier(<?= $item['item_id'] ?>, <?= $supplier['supplier_id'] ?>, 'linked')"
                                                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-medium rounded-lg transition-colors ml-3">
                                                                                <i class="fas fa-check"></i> Assign
                                                                            </button>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                                                    <i class="fas fa-exclamation-triangle text-amber-500 text-xs"></i>
                                                                    <p class="text-xs text-amber-700">No linked suppliers available for this item.</p>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            </div>


            <!-- ─── P.O. DETAILS FORM ─────────────────────────────────────── -->
            <div id="poDetailsForm" class="bg-white rounded-xl border border-slate-200 overflow-hidden hidden">

                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-edit text-emerald-600 text-sm"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">
                            <?= $existing_po_data ? 'Edit Purchase Order' : 'Purchase Order Details' ?>
                        </h2>
                        <p class="text-xs text-slate-400" id="formSupplierLabel">Fill in the details below</p>
                    </div>
                </div>

                <div class="p-5 space-y-4">

                    <?php if ($existing_po_data): ?>
                        <div class="flex items-start gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs">
                            <i class="fas fa-info-circle text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <div>
                                <p class="font-semibold text-amber-800">Editing Existing P.O.</p>
                                <p class="text-amber-700">File: <?= htmlspecialchars($existing_po_data['original_filename']) ?></p>
                                <p class="text-amber-700">Uploaded: <?= date('M j, Y g:i A', strtotime($existing_po_data['uploaded_at'])) ?></p>
                            </div>
                        </div>
                        <input type="hidden" id="editingPoId" value="<?= $editing_po_id ?>">
                    <?php endif; ?>

                    <!-- Prepared By -->
                    <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-600">
                        <i class="fas fa-user-edit text-slate-400"></i>
                        <span>Prepared by:</span>
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($prepared_by) ?></span>
                        <span class="text-slate-300">|</span>
                        <span class="capitalize text-slate-500"><?= htmlspecialchars($user_role) ?></span>
                    </div>

                    <!-- Form Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Payment Terms</label>
                            <input type="text" id="paymentTerms"
                                placeholder="e.g., After 7–14 Days"
                                value="<?= $existing_po_data ? htmlspecialchars($existing_po_data['payment_terms']) : '' ?>"
                                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition-shadow placeholder-slate-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Delivery Details</label>
                            <input type="text" id="deliveryDetails"
                                placeholder="e.g., Pickup at warehouse"
                                value="<?= $existing_po_data ? htmlspecialchars($existing_po_data['delivery_details']) : '' ?>"
                                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition-shadow placeholder-slate-300">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Conditions & Special Instructions</label>
                        <textarea id="conditions" rows="3"
                            placeholder="Enter any special conditions or instructions..."
                            class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition-shadow placeholder-slate-300 resize-none"><?= $existing_po_data ? htmlspecialchars($existing_po_data['conditions']) : '' ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Additional Notes</label>
                        <textarea id="additionalNotes" rows="3"
                            placeholder="Enter any additional notes..."
                            class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition-shadow placeholder-slate-300 resize-none"><?= $existing_po_data ? htmlspecialchars($existing_po_data['additional_notes']) : '' ?></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                        <?php if ($existing_po_data): ?>
                            <a href="warehouse_staff_po_management_A.php?order_id=<?= $order_id ?>"
                                class="inline-flex items-center gap-2 px-4 py-2 border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium rounded-lg transition-colors">
                                <i class="fas fa-times text-xs"></i> Cancel
                            </a>
                        <?php endif; ?>
                        <button onclick="generatePO()"
                            class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                            <i class="fas fa-file-download text-xs"></i>
                            <?= $existing_po_data ? 'Update P.O.' : 'Generate P.O.' ?>
                        </button>
                    </div>
                </div>
            </div>


            <!-- ─── ITEMS PREVIEW ─────────────────────────────────────────── -->
            <div id="itemsPreview" class="bg-white rounded-xl border border-slate-200 overflow-hidden hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-800">
                        <i class="fas fa-list text-slate-400 mr-2"></i>Items in this P.O.
                    </h2>
                </div>
                <div id="previewContent" class="p-5"></div>
            </div>

        <?php endif; ?>
    </div><!-- /main -->


    <script>
        let selectedSupplier = null;
        const supplierData = <?= json_encode($supplierGroups) ?>;

        /* ── Alert ── */
        function showAlert(message, type = 'info') {
            const colors = {
                success: 'bg-emerald-50 border-emerald-300 text-emerald-800',
                error:   'bg-red-50 border-red-300 text-red-800',
                info:    'bg-blue-50 border-blue-300 text-blue-800'
            };
            const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
            document.getElementById('alertContainer').innerHTML = `
                <div class="flex items-center gap-3 border rounded-lg px-4 py-3 text-sm ${colors[type]}">
                    <i class="fas ${icons[type]}"></i>
                    <span>${message}</span>
                </div>`;
            setTimeout(() => document.getElementById('alertContainer').innerHTML = '', 5000);
        }

        /* ── Toggle items list ── */
        function toggleItemsList(supplierKey) {
            const list = document.getElementById(`itemsList-${supplierKey}`);
            const icon = document.getElementById(`itemsIcon-${supplierKey}`);
            const isOpen = !list.classList.contains('hidden');
            list.classList.toggle('hidden');
            icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        }

        /* ── Toggle supplier options ── */
        function toggleSupplierOptions(itemId) {
            const opts = document.getElementById(`supplierOptions-${itemId}`);
            const icon = document.getElementById(`supplierIcon-${itemId}`);
            const isOpen = !opts.classList.contains('hidden');
            opts.classList.toggle('hidden');
            icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        }

        /* ── Select supplier → show form ── */
        function selectSupplier(supplierKey) {
            selectedSupplier = supplierKey;
            const supplier = supplierData[supplierKey];

            document.getElementById('formSupplierLabel').textContent =
                `Generating for: ${supplier.supplier_info.name}`;
            document.getElementById('poDetailsForm').classList.remove('hidden');
            document.getElementById('itemsPreview').classList.remove('hidden');

            updateItemsPreview(supplierKey);
            document.getElementById('poDetailsForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        /* ── Reassign supplier ── */
        function reassignItemSupplier(itemId, supplierId, type) {
            if (!confirm('Reassign this item to the selected supplier?')) return;
            fetch('<?= BASE_URL ?>/warehousestaffsupplierassign', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: itemId, supplier_id: supplierId, type })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('Supplier reassigned. Reloading...', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.error || 'Failed to reassign supplier', 'error');
                }
            })
            .catch(e => showAlert('Error: ' + e.message, 'error'));
        }

        /* ── Reset P.O. ── */
        function resetPONumber(supplierKey) {
            if (!confirm('Reset the P.O. number for this supplier?')) return;
            const supplier = supplierData[supplierKey];
            const itemIds = supplier.items.map(i => i.item_id);
            fetch('<?= BASE_URL ?>/warehousestaffresetponumber', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_ids: itemIds })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('P.O. reset successfully.', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('Failed: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(() => showAlert('Failed to reset P.O. number', 'error'));
        }

        /* ── Update items preview table ── */
        function updateItemsPreview(supplierKey) {
            const supplier = supplierData[supplierKey];
            let rows = '';
            supplier.items.forEach(item => {
                const unit  = parseFloat(item.current_price || 0);
                const total = parseFloat(item.calculated_subtotal || 0);
                const fmt   = v => v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                rows += `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium text-slate-800">${item.product_name}${item.namevariant ? ` <span class="text-slate-400 font-normal">— ${item.namevariant}</span>` : ''}</p>
                            <p class="text-xs text-slate-400">${item.codename}</p>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            ${item.variant_size_db || item.size} / ${item.variant_color_db || item.variant_color}
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">${item.descrip6 || 'pcs'}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-blue-600">${item.quantity}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">₱${fmt(unit)}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">₱${fmt(total)}</td>
                    </tr>`;
            });

            document.getElementById('previewContent').innerHTML = `
                <div class="mb-3 flex items-center gap-2 text-sm text-slate-600">
                    <i class="fas fa-building text-slate-400"></i>
                    <strong>${supplier.supplier_info.name}</strong>
                    <span class="text-slate-400">— ${supplier.items.length} item(s)</span>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Product</th>
                                <th class="px-4 py-2.5 text-left">Spec</th>
                                <th class="px-4 py-2.5 text-left">Unit</th>
                                <th class="px-4 py-2.5 text-left">Qty</th>
                                <th class="px-4 py-2.5 text-left">Unit Price</th>
                                <th class="px-4 py-2.5 text-left">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">${rows}</tbody>
                    </table>
                </div>`;
        }

        /* ── Generate P.O. ── */
        function generatePO() {
            const editingPoId = document.getElementById('editingPoId')?.value || 0;
            if (!editingPoId && !selectedSupplier) {
                showAlert('Please select a supplier first.', 'error');
                return;
            }
            showAlert(editingPoId ? 'Updating P.O. — please wait...' : 'Saving P.O. — please wait...', 'info');

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= BASE_URL ?>/warehousestaffgeneratepoexcel';

            const fields = [
                ['order_id',        <?= $order['id'] ?>],
                ['supplier_key',    editingPoId ? '<?= $existing_po_data ? addslashes($existing_po_data['supplier_name'] ?? '') : '' ?>' : selectedSupplier],
                ['payment_terms',   document.getElementById('paymentTerms').value],
                ['delivery_details',document.getElementById('deliveryDetails').value],
                ['conditions',      document.getElementById('conditions').value],
                ['additional_notes',document.getElementById('additionalNotes').value],
                ['prepared_by',     '<?= htmlspecialchars($prepared_by) ?>'],
                ['editing_po_id',   editingPoId]
            ];
            fields.forEach(([name, value]) => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = name; inp.value = value;
                form.appendChild(inp);
            });
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        /* ── Auto-open when editing ── */
        <?php if ($existing_po_data): ?>
        window.addEventListener('DOMContentLoaded', () => {
            const supplierName = '<?= addslashes($existing_po_data['supplier_name']) ?>';
            let foundKey = null;
            Object.keys(supplierData).forEach(key => {
                if (supplierData[key].supplier_info.name === supplierName) foundKey = key;
            });
            if (foundKey) {
                selectedSupplier = foundKey;
                document.getElementById('poDetailsForm').classList.remove('hidden');
                document.getElementById('itemsPreview').classList.remove('hidden');
                updateItemsPreview(foundKey);
                document.getElementById('poDetailsForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
        <?php endif; ?>
    </script>

</body>
</html>