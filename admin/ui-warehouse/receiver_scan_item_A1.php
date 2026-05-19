<?php
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

$user_id   = $_SESSION['noble_id'];
$fullname  = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

$item_id    = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
$itemInfo   = null;
$orderInfo  = null;
$supplierInfo = null;
$hasBooking = false;
$bookingInfo = null;

if ($item_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            oi.id as item_id, oi.order_id, oi.product_id, oi.product_name,
            oi.size, oi.variant_color, oi.codename, oi.descrip6, oi.descrip7,
            oi.quantity, oi.po_number, oi.qr_code, oi.warehouse_location,
            oi.tracking_status, oi.supplier_id, oi.manual_supplier_name,
            sl.business_name, sl.primary_contact_name, sl.email_address, sl.phone_number,
            o.customer_name, o.email as customer_email,
            o.created_at as order_date, o.status as order_status
        FROM order_items oi
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE oi.id = ?
    ");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $itemInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($itemInfo && $itemInfo['order_id']) {
        $bStmt = $conn->prepare("
            SELECT db.id, db.booking_type, db.tracking_number, db.courier_name,
                   db.booking_reference, db.estimated_pickup_time, db.actual_pickup_time,
                   db.estimated_delivery_time, db.actual_delivery_time, db.booking_status,
                   db.pickup_person_name, db.pickup_person_contact,
                   db.driver_name, db.vehicle_plate_number,
                   ds.delivery_date, ds.delivery_time
            FROM delivery_bookings db
            INNER JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
            WHERE db.order_id = ? LIMIT 1
        ");
        $bStmt->bind_param("i", $itemInfo['order_id']);
        $bStmt->execute();
        $bookingInfo = $bStmt->get_result()->fetch_assoc();
        $hasBooking  = ($bookingInfo !== null);
        $bStmt->close();
    }

    if ($itemInfo) {
        $orderInfo = [
            'order_id'       => $itemInfo['order_id'],
            'customer_name'  => $itemInfo['customer_name'],
            'customer_email' => $itemInfo['customer_email'],
            'order_date'     => $itemInfo['order_date'],
            'order_status'   => $itemInfo['order_status'],
        ];
        $supplierInfo = [
            'name'    => $itemInfo['supplier_id'] ? $itemInfo['business_name'] : $itemInfo['manual_supplier_name'],
            'contact' => $itemInfo['primary_contact_name'] ?? 'N/A',
            'email'   => $itemInfo['email_address'] ?? 'N/A',
            'phone'   => $itemInfo['phone_number'] ?? 'N/A',
        ];
    }
}

// Helpers
function statusBadge(string $status): string {
    $map = [
        'pending'    => 'bg-amber-100 text-amber-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'completed'  => 'bg-green-100 text-green-800',
        'cancelled'  => 'bg-red-100 text-red-800',
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-700';
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $cls . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
}

function bookingStatusClass(string $s): string {
    return match($s) {
        'pending'    => 'bg-amber-100 text-amber-800',
        'confirmed'  => 'bg-blue-100 text-blue-800',
        'in_transit' => 'bg-purple-100 text-purple-800',
        'delivered'  => 'bg-green-100 text-green-800',
        'picked_up'  => 'bg-teal-100 text-teal-800',
        'cancelled'  => 'bg-red-100 text-red-800',
        default      => 'bg-gray-100 text-gray-700',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanned Item — <?php echo $itemInfo ? htmlspecialchars($itemInfo['product_name']) : 'Not Found'; ?></title>
</head>

<body class="bg-gray-50 min-h-screen">
<?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

<!-- ── Page header ── -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-green-100 text-green-700">
                <i class="fas fa-qrcode text-base"></i>
            </span>
            <div>
                <p class="text-sm font-semibold text-gray-900 leading-tight">Scanned Item</p>
                <p class="text-xs text-gray-500">QR code result</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-medium text-gray-800 leading-tight"><?php echo htmlspecialchars($fullname); ?></p>
                <p class="text-xs text-gray-500"><?php echo htmlspecialchars(ucfirst($user_level)); ?></p>
            </div>
            <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-sm font-semibold">
                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
            </span>
        </div>
    </div>
</div>

<!-- ── Main ── -->
<div class="max-w-3xl mx-auto px-4 py-6 space-y-4">

<?php if (!$itemInfo): ?>
    <!-- Not found -->
    <div class="bg-white rounded-xl border border-gray-200 p-10 text-center">
        <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-red-100 text-red-500 mb-4">
            <i class="fas fa-exclamation-triangle text-2xl"></i>
        </span>
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Item Not Found</h2>
        <p class="text-sm text-gray-500 mb-6">
            <?php echo $item_id > 0
                ? 'No item found with ID <span class="font-mono font-bold">' . $item_id . '</span>.'
                : 'Invalid or missing item ID in QR code.'; ?>
        </p>
        <a href="<?= BASE_URL; ?>/receiverviewpoitems"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm px-5 py-2.5 rounded-lg transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to P.O. Items
        </a>
    </div>

<?php else: ?>

    <!-- Success pill -->
    <div class="flex justify-center">
        <span class="inline-flex items-center gap-1.5 bg-green-50 border border-green-200 text-green-700 text-xs font-medium px-3 py-1 rounded-full">
            <i class="fas fa-check-circle"></i> QR Code Scanned Successfully
        </span>
    </div>

    <!-- ── Item Details ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-box text-green-600"></i>
            <h2 class="text-sm font-semibold text-gray-800">Item Details</h2>
        </div>

        <div class="px-5 py-4 border-b border-gray-100">
            <p class="text-xs text-gray-400 mb-0.5">Product Name</p>
            <p class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($itemInfo['product_name']); ?></p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 divide-x divide-y divide-gray-100">
            <?php
            $fields = [
                ['Code Name',   strtoupper($itemInfo['codename'])],
                ['Size',        $itemInfo['size']],
                ['Color',       $itemInfo['variant_color']],
                ['Quantity',    $itemInfo['quantity'] . ' ' . ($itemInfo['descrip6'] ?: 'pcs')],
                ['Item ID',     '#' . $itemInfo['item_id']],
                ['P.O. Number', $itemInfo['po_number'] ?: '—'],
                ['Order ID',    '#' . $orderInfo['order_id']],
            ];
            foreach ($fields as [$label, $value]):
            ?>
            <div class="px-4 py-3">
                <p class="text-xs text-gray-400 mb-0.5"><?php echo $label; ?></p>
                <p class="text-sm font-semibold text-gray-900 break-words"><?php echo htmlspecialchars($value); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Tracking Status ── -->
    <?php
    $currentStatus    = $itemInfo['tracking_status'] ?? 'Pending Receipt';
    $isInWarehouse    = ($currentStatus === 'In Warehouse');
    $isWarehouseUser  = in_array(strtolower($user_level), ['warehouse', 'superadmin']);

    $hasUnresolvedDefects = false;
    $canMarkInWarehouse   = false;
    if ($isWarehouseUser) {
        $dStmt = $conn->prepare("SELECT COUNT(*) FROM defect_reports WHERE order_item_id = ? AND status != 'resolved'");
        $dStmt->bind_param("i", $item_id);
        $dStmt->execute();
        $dStmt->bind_result($defectCount);
        $dStmt->fetch();
        $dStmt->close();
        $hasUnresolvedDefects = $defectCount > 0;
        $canMarkInWarehouse   = in_array($currentStatus, ['processing', 'customs_clearance']) && !$hasUnresolvedDefects;
    }

    $statusIcon = match(true) {
        $isInWarehouse              => ['fas fa-warehouse',     'text-green-600'],
        $currentStatus === 'In Transit' => ['fas fa-truck',     'text-blue-600'],
        default                     => ['fas fa-clock',         'text-amber-500'],
    };
    $statusText = match(true) {
        $isInWarehouse              => 'Package received and stored',
        $currentStatus === 'Pending Receipt' => 'Awaiting receipt confirmation',
        $currentStatus === 'In Transit'      => 'Package is on the way',
        default                              => $currentStatus,
    };
    ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="<?php echo $statusIcon[0] . ' ' . $statusIcon[1]; ?>"></i>
            <h2 class="text-sm font-semibold text-gray-800">Tracking Status</h2>
        </div>

        <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Current Status</p>
                <p class="text-sm font-semibold <?php echo $isInWarehouse ? 'text-green-700' : 'text-gray-900'; ?>">
                    <?php echo htmlspecialchars($currentStatus); ?>
                </p>
                <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($statusText); ?></p>
            </div>

            <div class="flex flex-wrap gap-2">
                <?php if ($isInWarehouse): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                        <i class="fas fa-check-circle"></i> Confirmed
                    </span>

                <?php elseif ($hasBooking): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                        <i class="fas fa-calendar-check"></i> Delivery Scheduled
                    </span>

                <?php elseif ($isWarehouseUser): ?>
                    <?php if ($canMarkInWarehouse): ?>
                        <button onclick="updateTrackingStatus('In Warehouse')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 transition-colors">
                            <i class="fas fa-warehouse"></i> Mark as In Warehouse
                        </button>
                        <button onclick="openDefectReportModal()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 transition-colors">
                            <i class="fas fa-exclamation-triangle"></i> Report Defect
                        </button>

                    <?php elseif ($hasUnresolvedDefects): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                            <i class="fas fa-exclamation-circle"></i> Defect Reported
                        </span>
                        <button onclick="viewItemDefects(<?php echo $item_id; ?>)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 transition-colors">
                            <i class="fas fa-eye"></i> View Defect Details
                        </button>

                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                            <i class="fas fa-info-circle"></i> Not ready for warehouse receipt
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Warehouse Location (only if In Warehouse) ── -->
    <?php if ($isInWarehouse): ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-map-marker-alt text-gray-500"></i>
            <h2 class="text-sm font-semibold text-gray-800">Warehouse Location</h2>
        </div>
        <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <?php if (!empty($itemInfo['warehouse_location'])): ?>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Assigned Location</p>
                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($itemInfo['warehouse_location']); ?></p>
                </div>
                <button onclick="openEditLocationModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition-colors">
                    <i class="fas fa-edit"></i> Edit Location
                </button>
            <?php else: ?>
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-amber-400"></i>
                    <div>
                        <p class="text-sm font-medium text-gray-700">No Location Set</p>
                        <p class="text-xs text-gray-400">Warehouse location has not been assigned yet.</p>
                    </div>
                </div>
                <button onclick="openSetLocationModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 transition-colors">
                    <i class="fas fa-map-marker-alt"></i> Set Location
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Order / Owner Info ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-user text-gray-500"></i>
            <h2 class="text-sm font-semibold text-gray-800">Owner Information</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
            <div class="px-5 py-3.5">
                <p class="text-xs text-gray-400 mb-0.5">Customer Name</p>
                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($orderInfo['customer_name']); ?></p>
            </div>
            <div class="px-5 py-3.5">
                <p class="text-xs text-gray-400 mb-0.5">Email Address</p>
                <p class="text-sm font-semibold text-gray-900 break-all"><?php echo htmlspecialchars($orderInfo['customer_email']); ?></p>
            </div>
            <div class="px-5 py-3.5 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-0.5">Order Date</p>
                <p class="text-sm font-semibold text-gray-900"><?php echo date('M j, Y g:i A', strtotime($orderInfo['order_date'])); ?></p>
            </div>
            <div class="px-5 py-3.5 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-0.5">Order Status</p>
                <?php echo statusBadge($orderInfo['order_status']); ?>
            </div>
        </div>
    </div>

    <!-- ── Delivery Booking ── -->
    <?php if ($hasBooking && $bookingInfo): ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-truck text-teal-600"></i>
                <h2 class="text-sm font-semibold text-gray-800">Delivery Booking</h2>
            </div>
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo bookingStatusClass($bookingInfo['booking_status']); ?>">
                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $bookingInfo['booking_status']))); ?>
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-x divide-gray-100">
            <?php
            $bookingFields = [
                ['Booking Type',   ucfirst($bookingInfo['booking_type'])],
                ['Scheduled',      $bookingInfo['delivery_date'] && $bookingInfo['delivery_time']
                                    ? date('M j, Y', strtotime($bookingInfo['delivery_date'])) . ' · ' . date('g:i A', strtotime($bookingInfo['delivery_time']))
                                    : '—'],
            ];

            if ($bookingInfo['booking_type'] === 'delivery') {
                if ($bookingInfo['courier_name'])        $bookingFields[] = ['Courier',          $bookingInfo['courier_name']];
                if ($bookingInfo['tracking_number'])     $bookingFields[] = ['Tracking No.',     $bookingInfo['tracking_number']];
                if ($bookingInfo['booking_reference'])   $bookingFields[] = ['Booking Ref.',     $bookingInfo['booking_reference']];
                if ($bookingInfo['driver_name'])         $bookingFields[] = ['Driver',           $bookingInfo['driver_name']];
                if ($bookingInfo['vehicle_plate_number'])$bookingFields[] = ['Plate No.',        $bookingInfo['vehicle_plate_number']];
            } else {
                if ($bookingInfo['pickup_person_name'])  $bookingFields[] = ['Pickup Person',    $bookingInfo['pickup_person_name']];
                if ($bookingInfo['pickup_person_contact'])$bookingFields[] = ['Contact',         $bookingInfo['pickup_person_contact']];
            }

            if ($bookingInfo['estimated_pickup_time'])  $bookingFields[] = ['Est. Pickup',   date('M j, Y g:i A', strtotime($bookingInfo['estimated_pickup_time']))];
            if ($bookingInfo['actual_pickup_time'])     $bookingFields[] = ['Actual Pickup', date('M j, Y g:i A', strtotime($bookingInfo['actual_pickup_time']))];
            if ($bookingInfo['estimated_delivery_time'])$bookingFields[] = ['Est. Delivery', date('M j, Y g:i A', strtotime($bookingInfo['estimated_delivery_time']))];
            if ($bookingInfo['actual_delivery_time'])   $bookingFields[] = ['Actual Delivery',date('M j, Y g:i A', strtotime($bookingInfo['actual_delivery_time']))];

            foreach ($bookingFields as [$label, $value]):
            ?>
            <div class="px-5 py-3.5">
                <p class="text-xs text-gray-400 mb-0.5"><?php echo $label; ?></p>
                <p class="text-sm font-semibold text-gray-900 break-words"><?php echo htmlspecialchars($value); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Supplier Info ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-truck-field-un text-gray-500"></i>
            <h2 class="text-sm font-semibold text-gray-800">Supplier Information</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-x divide-gray-100">
            <div class="px-5 py-3.5">
                <p class="text-xs text-gray-400 mb-0.5">Supplier Name</p>
                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($supplierInfo['name']); ?></p>
            </div>
            <div class="px-5 py-3.5">
                <p class="text-xs text-gray-400 mb-0.5">Contact Person</p>
                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($supplierInfo['contact']); ?></p>
            </div>
            <div class="px-5 py-3.5 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-0.5">Email</p>
                <p class="text-sm font-semibold text-gray-900 break-all"><?php echo htmlspecialchars($supplierInfo['email']); ?></p>
            </div>
            <div class="px-5 py-3.5 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-0.5">Phone</p>
                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($supplierInfo['phone']); ?></p>
            </div>
        </div>
    </div>

    <!-- ── Actions ── -->
    <div class="flex justify-center pb-6">
        <a href="<?= BASE_URL; ?>/receiverviewpoitems?po_number=<?php echo urlencode($itemInfo['po_number']); ?>"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            <i class="fas fa-list text-xs"></i> View All P.O. Items
        </a>
    </div>

<?php endif; ?>
</div>

<!-- ── Location Modal ── -->
<div id="locationModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white rounded-xl w-full max-w-md shadow-xl">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900"><i class="fas fa-map-marker-alt mr-2 text-green-600"></i><span id="modalTitle">Set Location</span></h3>
            <button onclick="closeLocationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Warehouse Location</label>
                <input type="text" id="warehouseLocationInput"
                    placeholder="e.g. Aisle A, Shelf 3, Bin 5"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <p class="text-xs text-gray-400 mt-1">Enter the physical location where this item is stored.</p>
            </div>
            <div class="flex gap-2 pt-1">
                <button onclick="saveLocation()"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-save text-xs"></i> Save Location
                </button>
                <button onclick="closeLocationModal()"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm py-2 rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Defect Report Modal ── -->
<div id="defectModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white rounded-xl w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white">
            <h3 class="text-sm font-semibold text-gray-900"><i class="fas fa-exclamation-triangle mr-2 text-red-500"></i>Report Item Defect</h3>
            <button onclick="closeDefectModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5">
            <form id="defectReportForm" class="space-y-4" onsubmit="handleDefectSubmit(event)">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Defect Type <span class="text-red-500">*</span></label>
                    <select id="defectType" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">Select defect type…</option>
                        <option value="damaged">Damaged / Broken</option>
                        <option value="wrong_item">Wrong Item</option>
                        <option value="missing_parts">Missing Parts</option>
                        <option value="quality_issue">Quality Issue</option>
                        <option value="size_mismatch">Size / Color Mismatch</option>
                        <option value="manufacturing_defect">Manufacturing Defect</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Quantity Defective <span class="text-red-500">*</span></label>
                    <input type="number" id="quantityDefective" min="1" value="1" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Severity Level <span class="text-red-500">*</span></label>
                    <select id="severity" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="minor">Minor — cosmetic issues only</option>
                        <option value="moderate" selected>Moderate — affects functionality</option>
                        <option value="severe">Severe — item unusable</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
                    <textarea id="defectDescription" rows="4" required
                        placeholder="Describe the defect in detail…"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 rounded-lg transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane text-xs"></i> Submit Report
                    </button>
                    <button type="button" onclick="closeDefectModal()"
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm py-2 rounded-lg transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const itemId = <?php echo $item_id; ?>;
const BASE_URL = '<?= BASE_URL; ?>';

// ── Tracking status ──
function updateTrackingStatus(newStatus) {
    if (!confirm('Mark this item as "' + newStatus + '"?\n\nThis will record receipt date and time.')) return;
    const btn = event.target.closest('button');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Updating…';
    fetch(BASE_URL + '/receiverupdatetrackingstatus', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_id: itemId, tracking_status: newStatus, mark_as_received: true })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { alert('Status updated to: ' + newStatus); location.reload(); }
        else { alert('Error: ' + (d.error || 'Unknown error')); btn.disabled = false; btn.innerHTML = orig; }
    })
    .catch(() => { alert('Request failed. Please try again.'); btn.disabled = false; btn.innerHTML = orig; });
}

// ── Location modal ──
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
    const loc = document.getElementById('warehouseLocationInput').value.trim();
    if (!loc) { alert('Please enter a warehouse location.'); return; }
    fetch(BASE_URL + '/receiverupdatelocation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_id: itemId, warehouse_location: loc })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { closeLocationModal(); location.reload(); }
        else { alert('Error: ' + (d.error || 'Unknown error')); }
    })
    .catch(() => alert('Failed to save location.'));
}

// ── Defect modal ──
function openDefectReportModal() {
    document.getElementById('defectModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}
function closeDefectModal() {
    document.getElementById('defectModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    document.getElementById('defectReportForm').reset();
}
function handleDefectSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Submitting…';
    fetch(BASE_URL + '/warehousestaffreportdefect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            item_id:             itemId,
            defect_type:         document.getElementById('defectType').value,
            quantity_defective:  parseInt(document.getElementById('quantityDefective').value),
            severity:            document.getElementById('severity').value,
            defect_description:  document.getElementById('defectDescription').value,
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { alert('Defect report submitted successfully.'); closeDefectModal(); location.reload(); }
        else { alert('Error: ' + (d.error || 'Unknown error')); btn.disabled = false; btn.innerHTML = orig; }
    })
    .catch(() => { alert('Request failed. Please try again.'); btn.disabled = false; btn.innerHTML = orig; });
}

// Close modals on backdrop click / Escape
['locationModal', 'defectModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', e => { if (e.target.id === id) { e.target.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }});
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeLocationModal();
        closeDefectModal();
    }
});
</script>
</body>
</html>