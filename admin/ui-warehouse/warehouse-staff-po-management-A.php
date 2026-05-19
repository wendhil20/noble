<?php
//warehouse_staff_po_management_A.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_subrole(['warehouse_staff']);
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
    header("Location: " . BASE_URL . "/warehousestaff");
    exit();
}

$order = $orderResult->fetch_assoc();
$orderStmt->close();

$customer_name = $order['customer_name'];
$customer_email = $order['email'];

$itemStmt = $conn->prepare("
    SELECT 
        oi.id as item_id, oi.order_id, oi.product_id, oi.variant_id,
        oi.product_name, oi.size, oi.variant_color, oi.codename,
        oi.descrip6, oi.descrip7, oi.price as order_price,
        COALESCE(slp.supplier_price, oi.price) as current_price,
        oi.quantity,
        (COALESCE(slp.supplier_price, oi.price) * oi.quantity) as calculated_subtotal,
        oi.subtotal as original_subtotal, oi.origin, oi.supplier_id,
        p.product_name as product_full_name, p.codename as product_code,
        pv.namevariant, pv.color as variant_color_db, pv.size as variant_size_db,
        o.created_at as order_date, o.status as order_status, slp.supplier_price
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    LEFT JOIN products p ON pv.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id
    LEFT JOIN supp_link_products slp ON oi.variant_id = slp.variant_id
        AND oi.supplier_id = slp.supplier_id AND slp.status = 'active'
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

$newOrderTotal = 0;
foreach ($allItems as $item) $newOrderTotal += $item['calculated_subtotal'];

$unassignedCount = 0;
$primaryAvailableCount = 0;
$autoAssignedCount = 0;
$autoAssignedItems = [];

for ($i = 0; $i < count($allItems); $i++) {
    $allItems[$i]['linked_suppliers'] = [];
    $allItems[$i]['primary_supplier'] = null;

    $isUnassigned = (is_null($allItems[$i]['supplier_id']) || $allItems[$i]['supplier_id'] == 0);
    if ($isUnassigned) $unassignedCount++;

    if ($allItems[$i]['variant_id']) {
        $linkedSuppStmt = $conn->prepare("
            SELECT slp.supplier_id, slp.supplier_type, slp.supplier_price,
                   sl.business_name, sl.primary_contact_name, sl.email_address,
                   sl.phone_number, slp.status as link_status, sl.status as supplier_status
            FROM supp_link_products slp
            INNER JOIN supplier_list sl ON slp.supplier_id = sl.id
            WHERE slp.variant_id = ? AND slp.status = 'active' AND sl.status = 'active'
            ORDER BY CASE slp.supplier_type WHEN 'primary' THEN 1 WHEN 'secondary' THEN 2 ELSE 3 END ASC, sl.business_name ASC
        ");
        $linkedSuppStmt->bind_param("i", $allItems[$i]['variant_id']);
        $linkedSuppStmt->execute();
        $allItems[$i]['linked_suppliers'] = $linkedSuppStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $linkedSuppStmt->close();

        foreach ($allItems[$i]['linked_suppliers'] as $supplier) {
            if ($supplier['supplier_type'] === 'primary') {
                $allItems[$i]['primary_supplier'] = $supplier;
                if ($isUnassigned) {
                    $primaryAvailableCount++;
                    $autoAssignStmt = $conn->prepare("UPDATE order_items SET supplier_id = ? WHERE id = ?");
                    $autoAssignStmt->bind_param("ii", $supplier['supplier_id'], $allItems[$i]['item_id']);
                    if ($autoAssignStmt->execute()) {
                        $allItems[$i]['supplier_id'] = $supplier['supplier_id'];
                        $autoAssignedCount++;
                        $autoAssignedItems[] = [
                            'item_id'       => $allItems[$i]['item_id'],
                            'product_name'  => $allItems[$i]['product_name'],
                            'supplier_name' => $supplier['business_name']
                        ];
                        $unassignedCount--;
                    }
                    $autoAssignStmt->close();
                }
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>P.O Management — Order #<?= $order['id'] ?></title>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

<!-- Page Header -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-screen-xl mx-auto px-6 py-4 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/warehousestaff"
               class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-100 transition-colors">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-clipboard-list text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-lg font-semibold text-gray-900 leading-tight">P.O. Management</h1>
                <p class="text-sm text-gray-500">
                    Order #<?= $order['id'] ?> &mdash; <?= htmlspecialchars($customer_name) ?>
                    <span class="text-gray-400">(<?= htmlspecialchars($customer_email) ?>)</span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg">
                <?= count($allItems) ?> Items
            </span>
            <a href="<?= BASE_URL ?>/warehousestaffgeneratepo?order_id=<?= $order['id'] ?>"
               class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-file-invoice text-xs"></i>
                Generate P.O.
            </a>
        </div>
    </div>
</div>

<div class="max-w-screen-xl mx-auto px-6 py-6 space-y-4">

    <!-- Alert -->
    <div id="alertContainer"></div>

    <!-- Auto-assignment success -->
    <?php if ($autoAssignedCount > 0): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-start gap-3">
            <i class="fas fa-check-circle text-green-600 mt-0.5 shrink-0"></i>
            <div>
                <p class="text-sm font-semibold text-green-900">
                    <?= $autoAssignedCount ?> item<?= $autoAssignedCount > 1 ? 's' : '' ?> auto-assigned to primary suppliers
                </p>
                <div class="mt-2 space-y-1">
                    <?php foreach ($autoAssignedItems as $ai): ?>
                        <p class="text-xs text-green-700">
                            <i class="fas fa-arrow-right mr-1 text-green-500"></i>
                            <strong><?= htmlspecialchars($ai['product_name']) ?></strong> → <?= htmlspecialchars($ai['supplier_name']) ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bulk assign banner -->
    <?php if ($unassignedCount > 0 && $primaryAvailableCount > 0): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                    <i class="fas fa-magic text-blue-600 text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-blue-900">Bulk assignment available</p>
                    <p class="text-xs text-blue-700"><?= $primaryAvailableCount ?> items can be auto-assigned to their primary suppliers</p>
                </div>
            </div>
            <button onclick="assignAllToPrimarySuppliers()" id="bulkAssignBtn"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors whitespace-nowrap">
                <i class="fas fa-wand-magic-sparkles text-xs"></i>
                Assign all to primary
            </button>
        </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
            <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center shrink-0">
                <i class="fas fa-check-circle text-green-600 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Assigned</p>
                <p class="text-2xl font-semibold text-green-600"><?= count($allItems) - $unassignedCount ?></p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
            <div class="w-9 h-9 bg-amber-100 rounded-lg flex items-center justify-center shrink-0">
                <i class="fas fa-clock text-amber-600 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Unassigned</p>
                <p class="text-2xl font-semibold text-amber-600"><?= $unassignedCount ?></p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                <i class="fas fa-star text-blue-600 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Primary avail.</p>
                <p class="text-2xl font-semibold text-blue-600"><?= $primaryAvailableCount ?></p>
            </div>
        </div>
    </div>

    <!-- Order summary bar -->
    <div class="bg-white rounded-xl border border-gray-200 px-5 py-3 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center shrink-0">
                <i class="fas fa-dolly text-white text-xs"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">Order #<?= $order['id'] ?></p>
                <p class="text-xs text-gray-500">
                    <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?> &bull;
                    <span class="<?= $order['status'] === 'pending' ? 'text-amber-600' : 'text-green-600' ?> font-medium">
                        <?= ucfirst($order['status'] ?? 'pending') ?>
                    </span>
                </p>
            </div>
        </div>
        <div class="text-right">
            <?php if ($newOrderTotal != $order['total']): ?>
                <p class="text-xs text-gray-400 line-through">₱<?= number_format($order['total'], 2) ?></p>
                <p class="text-base font-semibold text-amber-600">₱<?= number_format($newOrderTotal, 2) ?></p>
            <?php else: ?>
                <p class="text-base font-semibold text-gray-900">₱<?= number_format($order['total'], 2) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items -->
    <?php if (!empty($allItems)): ?>
        <div class="space-y-3">
        <?php foreach ($allItems as $idx => $item):
            $isAssigned = !is_null($item['supplier_id']) && $item['supplier_id'] != 0;
            $priceChanged = $item['current_price'] != $item['order_price'];
            $subtotalChanged = $item['calculated_subtotal'] != $item['original_subtotal'];
            $hasLinked = !empty($item['linked_suppliers']);
        ?>
        <div class="bg-white rounded-xl border <?= $isAssigned ? 'border-gray-200' : 'border-amber-200' ?> overflow-hidden"
             id="item-<?= $item['item_id'] ?>">

            <!-- Item header -->
            <div class="px-5 py-4 flex items-start justify-between gap-4">
                <div class="flex items-start gap-3 flex-1 min-w-0">
                    <div class="w-9 h-9 rounded-lg <?= $isAssigned ? 'bg-green-100' : 'bg-amber-100' ?> flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fas <?= $isAssigned ? 'fa-check text-green-600' : 'fa-clock text-amber-500' ?> text-xs"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900">
                            <?= htmlspecialchars($item['product_name']) ?>
                            <?php if ($item['namevariant']): ?>
                                <span class="font-normal text-gray-400">— <?= htmlspecialchars($item['namevariant']) ?></span>
                            <?php endif; ?>
                        </p>
                        <!-- Chips -->
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">
                                <?= htmlspecialchars($item['codename']) ?>
                            </span>
                            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">
                                <?= htmlspecialchars($item['variant_size_db'] ?? $item['size']) ?>
                            </span>
                            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">
                                <?= htmlspecialchars($item['variant_color_db'] ?? $item['variant_color']) ?>
                            </span>
                            <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2 py-0.5 rounded">
                                Qty: <?= $item['quantity'] ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Price block -->
                <div class="text-right shrink-0">
                    <div class="text-xs text-gray-500 mb-0.5">Unit price</div>
                    <?php if ($priceChanged): ?>
                        <p class="text-xs text-gray-400 line-through">₱<?= number_format($item['order_price'], 2) ?></p>
                        <p class="text-sm font-semibold text-amber-600">₱<?= number_format($item['current_price'], 2) ?></p>
                    <?php else: ?>
                        <p class="text-sm font-semibold text-gray-800">₱<?= number_format($item['current_price'], 2) ?></p>
                    <?php endif; ?>
                    <div class="text-xs text-gray-400 mt-1">
                        Subtotal:
                        <?php if ($subtotalChanged): ?>
                            <span class="line-through">₱<?= number_format($item['original_subtotal'], 2) ?></span>
                            <span class="text-amber-600 font-medium ml-1">₱<?= number_format($item['calculated_subtotal'], 2) ?></span>
                        <?php else: ?>
                            <span class="text-gray-700 font-medium">₱<?= number_format($item['calculated_subtotal'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Assigned supplier indicator -->
            <?php if ($isAssigned): ?>
                <?php
                $csSql = $conn->prepare("SELECT business_name FROM supplier_list WHERE id = ?");
                $csSql->bind_param("i", $item['supplier_id']);
                $csSql->execute();
                $cs = $csSql->get_result()->fetch_assoc();
                $csSql->close();
                ?>
                <div class="mx-5 mb-3 flex items-center justify-between gap-3 bg-green-50 border border-green-200 rounded-lg px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm text-green-800">
                        <i class="fas fa-check-circle text-green-600 text-xs"></i>
                        <span>Assigned to <strong><?= htmlspecialchars($cs['business_name'] ?? 'Unknown') ?></strong></span>
                    </div>
                    <button onclick="unassignSupplier(<?= $item['item_id'] ?>)"
                        class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800 font-medium transition-colors">
                        <i class="fas fa-times text-xs"></i> Remove
                    </button>
                </div>
            <?php endif; ?>

            <!-- Suppliers toggle -->
            <div class="border-t border-gray-100">
                <button onclick="toggleSupplierDropdown(<?= $item['item_id'] ?>)"
                    class="w-full flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors text-left">
                    <span class="text-xs font-semibold text-gray-600 flex items-center gap-2">
                        <i class="fas fa-link text-gray-400"></i>
                        Linked Suppliers
                        <?php if ($hasLinked): ?>
                            <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded text-xs"><?= count($item['linked_suppliers']) ?></span>
                        <?php endif; ?>
                    </span>
                    <i id="dropdownIcon-<?= $item['item_id'] ?>" class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
                </button>

                <!-- Expandable supplier list -->
                <div id="supplierDropdown-<?= $item['item_id'] ?>" class="hidden border-t border-gray-100 bg-gray-50 px-5 py-4">
                    <?php if ($hasLinked): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <?php foreach ($item['linked_suppliers'] as $supp):
                                $isCurrent = $item['supplier_id'] == $supp['supplier_id'];
                                $isPrimary = $supp['supplier_type'] === 'primary';
                            ?>
                            <div class="bg-white rounded-lg border <?= $isCurrent ? 'border-green-400 ring-1 ring-green-300' : 'border-gray-200' ?> p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full <?= $isPrimary ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?>">
                                        <?= $isPrimary ? '⭐ Primary' : 'Secondary' ?>
                                    </span>
                                    <?php if ($isCurrent): ?>
                                        <i class="fas fa-check-circle text-green-500 text-sm"></i>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm font-semibold text-gray-900 mb-1"><?= htmlspecialchars($supp['business_name']) ?></p>
                                <div class="space-y-1 text-xs text-gray-500">
                                    <p><i class="fas fa-user mr-1.5"></i><?= htmlspecialchars($supp['primary_contact_name']) ?></p>
                                    <p class="truncate"><i class="fas fa-envelope mr-1.5"></i><?= htmlspecialchars($supp['email_address']) ?></p>
                                    <p><i class="fas fa-phone mr-1.5"></i><?= htmlspecialchars($supp['phone_number']) ?></p>
                                </div>
                                <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                                    <?php if ($supp['supplier_price']): ?>
                                        <span class="text-sm font-semibold text-green-600">₱<?= number_format($supp['supplier_price'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs text-amber-600 italic">No price set</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3 flex gap-2">
                                    <button onclick="assignLinkedSupplierById(<?= $item['item_id'] ?>, <?= $supp['supplier_id'] ?>)"
                                        <?= $isCurrent ? 'disabled' : '' ?>
                                        class="flex-1 text-xs font-medium py-1.5 rounded-lg transition-colors
                                               <?= $isCurrent ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-gray-900 hover:bg-gray-700 text-white' ?>">
                                        <i class="fas fa-check mr-1"></i> Assign
                                    </button>
                                    <button onclick="contactSupplierById('<?= htmlspecialchars($supp['email_address']) ?>')"
                                        class="flex-1 text-xs font-medium py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 transition-colors">
                                        <i class="fas fa-envelope mr-1"></i> Email
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                            <i class="fas fa-exclamation-triangle text-amber-500"></i>
                            No suppliers linked to this product.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-inbox text-gray-400 text-2xl"></i>
            </div>
            <p class="text-base font-semibold text-gray-700">No items found</p>
            <p class="text-sm text-gray-400 mt-1">This order appears to be empty.</p>
        </div>
    <?php endif; ?>

</div>

<script>
    function showAlert(message, type = 'info') {
        const colors = {
            success: 'bg-green-50 border-green-300 text-green-800',
            error:   'bg-red-50 border-red-300 text-red-800',
            info:    'bg-blue-50 border-blue-300 text-blue-800'
        };
        const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
        document.getElementById('alertContainer').innerHTML = `
            <div class="flex items-center gap-3 border rounded-xl px-4 py-3 text-sm ${colors[type]}">
                <i class="fas ${icons[type]}"></i><span>${message}</span>
            </div>`;
        setTimeout(() => document.getElementById('alertContainer').innerHTML = '', 5000);
    }

    function toggleSupplierDropdown(itemId) {
        const dropdown = document.getElementById(`supplierDropdown-${itemId}`);
        const icon     = document.getElementById(`dropdownIcon-${itemId}`);
        dropdown.classList.toggle('hidden');
        icon.style.transform = dropdown.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
    }

    function assignLinkedSupplierById(itemId, supplierId) {
        if (!confirm('Assign this supplier to the item?')) return;
        fetch('<?= BASE_URL ?>/warehousestaffsupplierassign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, supplier_id: parseInt(supplierId), type: 'linked' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { showAlert('Supplier assigned!', 'success'); setTimeout(() => location.reload(), 1000); }
            else showAlert(data.error || 'Failed to assign supplier', 'error');
        })
        .catch(e => showAlert('Error: ' + e.message, 'error'));
    }

    function contactSupplierById(email) {
        const orderId = <?= $order['id'] ?>;
        window.location.href = `mailto:${email}?subject=Purchase Order Inquiry - Order #${orderId}&body=Hello,%0D%0A%0D%0ARegarding Order #${orderId}.%0D%0A%0D%0AThank you.`;
    }

    function unassignSupplier(itemId) {
        if (!confirm('Remove the assigned supplier from this item?')) return;
        fetch('<?= BASE_URL ?>/warehousestaffsupplierassign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, supplier_id: null, type: 'unassign' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { showAlert('Supplier removed.', 'success'); setTimeout(() => location.reload(), 1000); }
            else showAlert(data.error || 'Failed to remove supplier', 'error');
        })
        .catch(e => showAlert('Error: ' + e.message, 'error'));
    }

    function assignAllToPrimarySuppliers() {
        if (!confirm('Auto-assign all unassigned items to their primary suppliers?')) return;
        const btn = document.getElementById('bulkAssignBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-2"></i>Processing…';
        btn.disabled = true;
        fetch('<?= BASE_URL ?>/warehousestaffbulkassign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: <?= $order['id'] ?>, type: 'primary_only' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert(`Assigned ${data.assigned_count} items to primary suppliers!`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.error || 'Failed to bulk assign', 'error');
                btn.innerHTML = '<i class="fas fa-wand-magic-sparkles text-xs mr-2"></i>Assign all to primary';
                btn.disabled = false;
            }
        })
        .catch(e => {
            showAlert('Error: ' + e.message, 'error');
            btn.disabled = false;
        });
    }
</script>
</body>
</html>