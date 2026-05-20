<?php
// scan_replacement.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$_SESSION['last_activity'] = time();

if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

$replacement_id = isset($_GET['replacement_id']) ? intval($_GET['replacement_id']) : 0;
$itemInfo = null;
$orderInfo = null;
$supplierInfo = null;

if ($replacement_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            rr.id as replacement_id,
            rr.order_id,
            rr.order_item_id,
            rr.replacement_quantity,
            rr.reason as replacement_reason,
            rr.po_number,
            rr.qr_code,
            rr.warehouse_location,
            rr.created_at as replacement_date,
            rr.status,
            oi.product_id,
            oi.product_name,
            oi.size,
            oi.variant_color,
            oi.codename,
            oi.descrip6,
            oi.descrip7,
            oi.supplier_id,
            oi.manual_supplier_name,
            sl.business_name,
            sl.primary_contact_name,
            sl.email_address,
            sl.phone_number,
            o.customer_name,
            o.email as customer_email,
            o.created_at as order_date,
            o.status as order_status
        FROM replacement_requests rr
        INNER JOIN order_items oi ON rr.order_item_id = oi.id
        INNER JOIN orders o ON rr.order_id = o.id
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
        WHERE rr.id = ?
    ");
    $stmt->bind_param("i", $replacement_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $itemInfo = $result->fetch_assoc();
    $stmt->close();

    if ($itemInfo) {
        $orderInfo = [
            'order_id' => $itemInfo['order_id'],
            'customer_name' => $itemInfo['customer_name'],
            'customer_email' => $itemInfo['customer_email'],
            'order_date' => $itemInfo['order_date'],
            'order_status' => $itemInfo['order_status'],
        ];
        $supplierInfo = [
            'name' => $itemInfo['supplier_id'] ? $itemInfo['business_name'] : $itemInfo['manual_supplier_name'],
            'contact' => $itemInfo['primary_contact_name'] ?? 'N/A',
            'email' => $itemInfo['email_address'] ?? 'N/A',
            'phone' => $itemInfo['phone_number'] ?? 'N/A',
        ];
    }
}

$currentStatus = $itemInfo['status'] ?? 'pending';
$isReceived = ($currentStatus === 'In Warehouse');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Replacement Item — <?php echo $itemInfo ? htmlspecialchars($itemInfo['product_name']) : 'Not Found'; ?>
    </title>
</head>

<body class="bg-gray-100 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- ── Page header ── -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="bg-red-500 rounded-lg p-2 flex-shrink-0">
                    <i class="fas fa-sync-alt text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Replacement Item</h1>
                    <p class="text-sm text-gray-500">QR code scan result</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <div
                    class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                    <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                </div>
                <div class="hidden sm:block text-right">
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($fullname); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars(ucfirst($user_level)); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Main ── -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-6 space-y-4">

        <?php if (!$itemInfo): ?>
            <!-- Not found -->
            <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
                <i class="fas fa-exclamation-triangle text-red-400 text-5xl mb-4 block"></i>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Replacement Item Not Found</h2>
                <p class="text-gray-500 mb-6">
                    <?php if ($replacement_id > 0): ?>
                        No replacement found with ID <span
                            class="font-mono font-semibold text-gray-800"><?php echo $replacement_id; ?></span>.
                    <?php else: ?>
                        Invalid or missing replacement ID in QR code.
                    <?php endif; ?>
                </p>
                <a href="<?= BASE_URL; ?>/receiverviewpoitems?po_number=<?php echo urlencode($itemInfo['po_number'] ?? ''); ?>"
                    class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-arrow-left"></i> Back to P.O. Items
                </a>
            </div>

        <?php else: ?>

            <!-- ── Scan success pill ── -->
            <div class="flex justify-center">
                <span
                    class="inline-flex items-center gap-2 bg-red-100 text-red-700 text-sm font-medium px-4 py-1.5 rounded-full">
                    <i class="fas fa-sync-alt"></i> Replacement item scanned successfully
                </span>
            </div>

            <!-- ── Replacement banner ── -->
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex gap-3">
                <i class="fas fa-sync-alt text-red-500 text-xl mt-0.5 flex-shrink-0"></i>
                <div class="space-y-0.5 text-sm">
                    <p class="font-bold text-red-800 text-base">REPLACEMENT ITEM</p>
                    <p class="text-red-700"><span class="font-semibold">Reason:</span>
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $itemInfo['replacement_reason']))); ?></p>
                    <p class="text-red-700"><span class="font-semibold">Original Order Item ID:</span>
                        #<?php echo $itemInfo['order_item_id']; ?></p>
                    <p class="text-red-700"><span class="font-semibold">Replacement Date:</span>
                        <?php echo date('M j, Y g:i A', strtotime($itemInfo['replacement_date'])); ?></p>
                </div>
            </div>

            <!-- ── Item details card ── -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-box text-red-500"></i>
                    <h2 class="font-semibold text-gray-900">Item Details</h2>
                </div>
                <div class="p-5 space-y-5">

                    <!-- Product name -->
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">Product Name</p>
                        <p class="text-xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($itemInfo['product_name']); ?>
                        </p>
                    </div>

                    <!-- Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Code Name</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($itemInfo['codename']); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Size & Color</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($itemInfo['size']); ?> ·
                                <?php echo htmlspecialchars($itemInfo['variant_color']); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Replacement Qty</p>
                            <p class="font-bold text-red-600 text-lg"><?php echo $itemInfo['replacement_quantity']; ?> <span
                                    class="text-sm font-normal text-gray-500"><?php echo htmlspecialchars($itemInfo['descrip6'] ?: 'pcs'); ?></span>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Replacement ID</p>
                            <p class="font-semibold text-gray-800">#<?php echo $itemInfo['replacement_id']; ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">P.O. Number</p>
                            <p class="font-semibold text-gray-800">
                                <?php echo $itemInfo['po_number'] ? htmlspecialchars($itemInfo['po_number']) : '<span class="text-gray-400 font-normal">Not assigned</span>'; ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Order ID</p>
                            <p class="font-semibold text-gray-800">#<?php echo $orderInfo['order_id']; ?></p>
                        </div>
                    </div>

                    <!-- Status row -->
                    <div
                        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <div class="flex items-center gap-3">
                            <div
                                class="<?php echo $isReceived ? 'bg-green-500' : 'bg-yellow-400'; ?> rounded-lg p-2 flex-shrink-0">
                                <i
                                    class="fas fa-<?php echo $isReceived ? 'check-circle' : 'clock'; ?> text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Status</p>
                                <p class="font-bold text-gray-900 text-base">
                                    <?php echo htmlspecialchars(ucfirst($currentStatus)); ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <?php if ($isReceived): ?>
                                        <i class="fas fa-check-circle text-green-500 mr-1"></i>Received and stored in warehouse
                                    <?php elseif ($currentStatus === 'pending'): ?>
                                        <i class="fas fa-clock text-yellow-500 mr-1"></i>Awaiting receipt confirmation
                                    <?php elseif ($currentStatus === 'approved'): ?>
                                        <i class="fas fa-check text-blue-500 mr-1"></i>Approved — awaiting receipt
                                    <?php else: ?>
                                        <i class="fas fa-info-circle mr-1"></i><?php echo htmlspecialchars($currentStatus); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($isReceived): ?>
                            <span
                                class="inline-flex items-center gap-2 bg-green-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
                                <i class="fas fa-check-circle"></i> Confirmed
                            </span>
                        <?php elseif (strtolower($currentStatus) === 'processing' && in_array(strtolower($user_level), ['warehouse', 'superadmin'])): ?>
                            <button onclick="updateReplacementStatus('In Warehouse')"
                                class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                                <i class="fas fa-warehouse"></i> Mark as In Warehouse
                            </button>
                        <?php else: ?>
                            <span
                                class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 text-sm font-medium px-4 py-2 rounded-lg border border-blue-200">
                                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(ucfirst($currentStatus)); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Warehouse location -->
                    <?php if (!empty($itemInfo['warehouse_location'])): ?>
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-blue-50 rounded-xl p-4 border border-blue-200">
                            <div class="flex items-center gap-3">
                                <div class="bg-blue-500 rounded-lg p-2 flex-shrink-0">
                                    <i class="fas fa-map-marker-alt text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-blue-600 font-medium uppercase tracking-wide">Warehouse Location</p>
                                    <p class="font-bold text-blue-900 text-lg">
                                        <?php echo htmlspecialchars($itemInfo['warehouse_location']); ?>
                                    </p>
                                </div>
                            </div>
                            <button onclick="openEditLocationModal()"
                                class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                    <?php else: ?>
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-yellow-50 rounded-xl p-4 border border-yellow-200">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-exclamation-triangle text-yellow-500 text-xl flex-shrink-0"></i>
                                <div>
                                    <p class="font-semibold text-yellow-900">No Location Set</p>
                                    <p class="text-sm text-yellow-700">Warehouse location has not been assigned yet.</p>
                                </div>
                            </div>
                            <button onclick="openSetLocationModal()"
                                class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                                <i class="fas fa-map-marker-alt"></i> Set Location
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Owner + Supplier (side by side on desktop) ── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <!-- Owner -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-user text-blue-500"></i>
                        <h2 class="font-semibold text-gray-900">Owner</h2>
                    </div>
                    <div class="p-5 space-y-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Customer Name</p>
                            <p class="font-semibold text-gray-800">
                                <?php echo htmlspecialchars($orderInfo['customer_name']); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Email</p>
                            <p class="font-semibold text-gray-800 break-all">
                                <?php echo htmlspecialchars($orderInfo['customer_email']); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Order Date</p>
                            <p class="font-semibold text-gray-800">
                                <?php echo date('M j, Y g:i A', strtotime($orderInfo['order_date'])); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Order Status</p>
                            <?php
                            $statusClass = match ($orderInfo['order_status']) {
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'processing' => 'bg-blue-100 text-blue-800',
                                default => 'bg-green-100 text-green-800',
                            };
                            ?>
                            <span
                                class="inline-block px-3 py-0.5 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars(ucfirst($orderInfo['order_status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Supplier -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-building text-purple-500"></i>
                        <h2 class="font-semibold text-gray-900">Supplier</h2>
                    </div>
                    <div class="p-5 space-y-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Business Name</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($supplierInfo['name']); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Contact Person</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($supplierInfo['contact']); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Email</p>
                            <p class="font-semibold text-gray-800 break-all">
                                <?php echo htmlspecialchars($supplierInfo['email']); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Phone</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($supplierInfo['phone']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Action button ── -->
            <div class="flex justify-center pb-2">
                <a href="<?= BASE_URL; ?>/receiverviewpoitems?po_number=<?php echo urlencode($itemInfo['po_number']); ?>"
                    class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-list"></i> View All P.O. Items
                </a>
            </div>

        <?php endif; ?>
    </div>

    <!-- ── Location modal ── -->
    <div id="locationModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-green-500"></i>
                    <span id="modalTitle">Set Location</span>
                </h3>
                <button onclick="closeLocationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Warehouse Location</label>
                    <input type="text" id="warehouseLocationInput" placeholder="e.g., Aisle A, Shelf 3, Bin 5"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <p class="text-xs text-gray-400 mt-1">Enter where this replacement item is physically stored.</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="saveLocation()"
                        class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> Save Location
                    </button>
                    <button onclick="closeLocationModal()"
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2.5 rounded-lg text-sm font-medium transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const replacementId = <?php echo $replacement_id; ?>;
        const BASE_URL = "<?php echo BASE_URL; ?>";
        function updateReplacementStatus(newStatus) {
            const messages = {
                'In Warehouse': 'This will confirm that the replacement item has been received and stored in the warehouse.',
            };
            const msg = messages[newStatus] || 'This will update the replacement status.';
            if (!confirm('Mark this replacement as "' + newStatus + '"?\n\n' + msg)) return;

            const btn = event.target.closest('button');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating…';

             fetch(BASE_URL + '/receiverupdatereplacement', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        replacement_id: replacementId,
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Status updated to: ' + newStatus);
                        window.location.reload();
                    } else {
                        alert('Failed: ' + (data.error || 'Unknown error'));
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }
                })
                .catch(() => {
                    alert('Request failed. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
        }

        function openSetLocationModal() {
            document.getElementById('modalTitle').textContent = 'Set Location';
            document.getElementById('warehouseLocationInput').value = '';
            document.getElementById('locationModal').classList.remove('hidden');
            document.getElementById('warehouseLocationInput').focus();
        }

        function openEditLocationModal() {
            document.getElementById('modalTitle').textContent = 'Edit Location';
            document.getElementById('warehouseLocationInput').value = '<?php echo htmlspecialchars($itemInfo['warehouse_location'] ?? '', ENT_QUOTES); ?>';
            document.getElementById('locationModal').classList.remove('hidden');
            document.getElementById('warehouseLocationInput').focus();
        }

        function closeLocationModal() {
            document.getElementById('locationModal').classList.add('hidden');
        }

        function saveLocation() {
            const location = document.getElementById('warehouseLocationInput').value.trim();
            if (!location) { alert('Please enter a warehouse location.'); return; }

            fetch(BASE_URL + '/receiverupdatelocation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: replacementId, warehouse_location: location, item_type: 'replacement' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeLocationModal();
                        window.location.reload();
                    } else {
                        alert('Failed to save: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => alert('Request failed. Please try again.'));
        }

        document.getElementById('locationModal').addEventListener('click', function (e) {
            if (e.target === this) closeLocationModal();
        });

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLocationModal(); });
    </script>

</body>

</html>