<?php
// scan_item.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get user info from session or database
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

// Set user variables
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
$itemInfo = null;
$orderInfo = null;
$supplierInfo = null;

if ($item_id > 0) {
    // Get item information
    $stmt = $conn->prepare("
    SELECT 
        oi.id as item_id,
        oi.order_id,
        oi.product_id,
        oi.product_name,
        oi.size,
        oi.variant_color,
        oi.codename,
        oi.descrip6,
        oi.descrip7,
        oi.quantity,
        oi.po_number,
        oi.qr_code,
        oi.warehouse_location,
        oi.tracking_status,
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
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE oi.id = ?
");

    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $itemInfo = $result->fetch_assoc();
    $stmt->close();

    // Check if there's a delivery booking for this order
    $hasBooking = false;
    $bookingInfo = null;
    if ($itemInfo && $itemInfo['order_id']) {
        $bookingCheck = $conn->prepare("
        SELECT 
            db.id,
            db.booking_type,
            db.tracking_number,
            db.courier_name,
            db.booking_reference,
            db.estimated_pickup_time,
            db.actual_pickup_time,
            db.estimated_delivery_time,
            db.actual_delivery_time,
            db.booking_status,
            db.pickup_person_name,
            db.pickup_person_contact,
            db.driver_name,
            db.vehicle_plate_number,
            ds.delivery_date,
            ds.delivery_time
        FROM delivery_bookings db
        INNER JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
        WHERE db.order_id = ?
        LIMIT 1
    ");
        $bookingCheck->bind_param("i", $itemInfo['order_id']);
        $bookingCheck->execute();
        $bookingResult = $bookingCheck->get_result();
        $bookingInfo = $bookingResult->fetch_assoc();
        $hasBooking = ($bookingInfo !== null);
        $bookingCheck->close();
    }

    if ($itemInfo) {
        $orderInfo = [
            'order_id' => $itemInfo['order_id'],
            'customer_name' => $itemInfo['customer_name'],
            'customer_email' => $itemInfo['customer_email'],
            'order_date' => $itemInfo['order_date'],
            'order_status' => $itemInfo['order_status']
        ];

        $supplierInfo = [
            'name' => $itemInfo['supplier_id'] ? $itemInfo['business_name'] : $itemInfo['manual_supplier_name'],
            'contact' => $itemInfo['primary_contact_name'] ?? 'N/A',
            'email' => $itemInfo['email_address'] ?? 'N/A',
            'phone' => $itemInfo['phone_number'] ?? 'N/A'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanned Item Info - <?php echo $itemInfo ? htmlspecialchars($itemInfo['product_name']) : 'Not Found'; ?>
    </title>

</head>

<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header - Mobile Optimized -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
            <div class="py-3 sm:py-4">
                <!-- Mobile Layout -->
                <div class="flex flex-col space-y-3 sm:hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="bg-green-500 p-2 rounded-lg">
                                <i class="fas fa-qrcode text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-gray-900">Scanned Item</h1>
                                <p class="text-xs text-gray-600">QR code scan</p>
                            </div>
                        </div>
                        <div
                            class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-sm">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-1 text-gray-700">
                                <i class="fas fa-user text-primary-600"></i>
                                <span
                                    class="font-medium truncate max-w-[120px]"><?php echo htmlspecialchars($fullname); ?></span>
                            </div>
                            <div class="flex items-center space-x-1 text-gray-600">
                                <i class="fas fa-shield-alt"></i>
                                <span><?php echo htmlspecialchars(ucfirst($user_level)); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Desktop Layout -->
                <div
                    class="hidden sm:flex justify-between items-center gap-4 bg-white border border-gray-100 rounded-xl px-6 py-4">

                    <!-- Left: Icon + Title -->
                    <div class="flex items-center gap-3.5">
                        <div class="bg-green-500 w-12 h-12 rounded-lg flex items-center justify-center shrink-0">
                            <i class="fas fa-qrcode text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900 leading-tight">Scanned Item Information</h1>
                            <p class="text-sm text-gray-500 mt-0.5">Item details from QR code scan</p>
                        </div>
                    </div>

                    <!-- Right: User info + Avatar -->
                    <div class="flex items-center gap-3 shrink-0">

                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900 leading-tight">
                                <?php echo htmlspecialchars($fullname); ?>
                            </p>
                            <div class="flex items-center justify-end mt-0.5 mb-0.5">
                                <span
                                    class="inline-flex items-center bg-blue-50 text-blue-600 text-xs font-medium px-2 py-0.5 rounded-full">
                                    <i class="fas fa-shield-alt mr-1 text-[10px]"></i>
                                    <?php echo htmlspecialchars(ucfirst($user_level)); ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-400">
                                <?php echo date('M j, Y · g:i A'); ?>
                            </p>
                        </div>

                        <div
                            class="w-11 h-11 rounded-full bg-blue-50 border border-gray-100 flex items-center justify-center shrink-0">
                            <span class="text-blue-600 font-medium text-base">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8 py-4 sm:py-8">

        <?php if (!$itemInfo): ?>
            <!-- Item Not Found -->
            <div class="bg-white rounded-xl shadow-lg border border-red-200 p-6 sm:p-12 text-center">
                <div class="text-red-500 mb-4">
                    <i class="fas fa-exclamation-triangle text-4xl sm:text-6xl"></i>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Item Not Found</h2>
                <p class="text-sm sm:text-base text-gray-600 mb-6">
                    <?php if ($item_id > 0): ?>
                        No item found with ID: <span class="font-mono font-bold"><?php echo $item_id; ?></span>
                    <?php else: ?>
                        Invalid or missing item ID in QR code.
                    <?php endif; ?>
                </p>
                <a href="receiver_view_po_items_A.php"
                    class="inline-flex items-center space-x-2 bg-primary-600 hover:bg-primary-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg transition-colors duration-200 text-sm sm:text-base">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to P.O. Items</span>
                </a>
            </div>
        <?php else: ?>

            <!-- Success Badge -->
            <div class="mb-4 sm:mb-6 flex justify-center">
                <div
                    class="inline-flex items-center space-x-2 bg-green-100 text-green-800 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full text-xs sm:text-sm border border-gray-400">
                    <i class="fas fa-check-circle"></i>
                    <span class="font-medium">QR Code Scanned Successfully</span>
                </div>
            </div>

            <!-- Item Information Card -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-4 sm:mb-6">
                <!-- Header -->
                <div class="bg-green-600 px-4 sm:px-6 py-3 sm:py-4">
                    <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-box mr-2 sm:mr-3"></i>
                        Item Details
                    </h2>
                </div>

                <!-- Content -->
                <div class="p-4 sm:p-6">
                    <!-- Product Name -->
                    <div class="mb-4 sm:mb-6 pb-4 sm:pb-6 border-b border-gray-200">
                        <div class="text-xs sm:text-sm text-gray-600 mb-1">Product Name</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-900 break-words">
                            <?php echo htmlspecialchars($itemInfo['product_name']); ?>
                        </div>
                    </div>

                    <!-- Grid Information -->
                    <div
                        class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-gray-100 border border-gray-100 rounded-xl overflow-hidden mb-4 sm:mb-6">

                        <!-- Left Column -->
                        <div class="bg-white space-y-px">

                            <div class="px-4 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                                    <i class="fas fa-barcode mr-1.5"></i>Code Name
                                </p>
                                <p class="text-base font-semibold text-gray-900 uppercase">
                                    <?php echo htmlspecialchars($itemInfo['codename']); ?>
                                </p>
                            </div>

                            <div class="px-4 py-3.5 border-t border-gray-100">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                                    <i class="fas fa-ruler mr-1.5"></i>Size & Color
                                </p>
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($itemInfo['size']); ?>
                                    </span>
                                    <span class="w-px h-4 bg-gray-200"></span>
                                    <span class="text-base font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($itemInfo['variant_color']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="px-4 py-3.5 border-t border-gray-100">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                                    <i class="fas fa-box mr-1.5"></i>Quantity
                                </p>
                                <p class="text-base font-semibold text-gray-900">
                                    <?php echo $itemInfo['quantity']; ?>
                                    <span class="text-sm font-normal text-gray-500">
                                        <?php echo htmlspecialchars($itemInfo['descrip6'] ?: 'pcs'); ?>
                                    </span>
                                </p>
                            </div>

                        </div>

                        <!-- Right Column -->
                        <div class="bg-white space-y-px">

                            <div class="px-4 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                                    <i class="fas fa-hashtag mr-1.5"></i>Item ID
                                </p>
                                <p class="text-base font-semibold text-gray-900">
                                    <?php echo $itemInfo['item_id']; ?>
                                </p>
                            </div>

                            <div class="px-4 py-3.5 border-t border-gray-100">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                                    <i class="fas fa-file-invoice mr-1.5"></i>P.O. Number
                                </p>
                                <p class="text-base font-semibold text-gray-900">
                                    <?php if ($itemInfo['po_number']): ?>
                                        <?php echo htmlspecialchars($itemInfo['po_number']); ?>
                                    <?php else: ?>
                                        <span class="text-sm font-normal text-gray-400">Not assigned</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="px-4 py-3.5 border-t border-gray-100">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                                    <i class="fas fa-shopping-cart mr-1.5"></i>Order ID
                                </p>
                                <p class="text-base font-semibold text-gray-900">
                                    #<?php echo $orderInfo['order_id']; ?>
                                </p>
                            </div>

                        </div>

                    </div>

                    <!-- Tracking Status Section -->
                    <?php
                    $currentStatus = $itemInfo['tracking_status'] ?? 'Pending Receipt';
                    $isInWarehouse = ($currentStatus === 'In Warehouse');
                    ?>
<!-- Tracking Status -->
<div class="border border-gray-100 rounded-xl overflow-hidden mb-4 sm:mb-6 bg-gray-100">

    <!-- Section Header -->
    <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex items-center gap-2">
        <div class="w-7 h-7 rounded-md flex items-center justify-center <?php echo $isInWarehouse ? 'bg-gray-500' : 'bg-indigo-50'; ?>">
            <i class="fas fa-<?php echo $isInWarehouse ? 'warehouse' : 'shipping-fast'; ?> text-xs <?php echo $isInWarehouse ? 'text-white' : 'text-indigo-500'; ?>"></i>
        </div>
        <h2 class="text-sm font-medium text-gray-700">Tracking Status</h2>
    </div>

    <div class="bg-white px-4 sm:px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

        <!-- Status Info -->
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Current Status</p>
            <p class="text-base font-semibold <?php echo $isInWarehouse ? 'text-green-700' : 'text-indigo-700'; ?> mb-1">
                <?php echo htmlspecialchars($currentStatus); ?>
            </p>
            <p class="text-xs text-gray-500 flex items-center gap-1">
                <?php if ($isInWarehouse): ?>
                    <i class="fas fa-check-circle text-green-400"></i> Package has been received and stored
                <?php elseif ($currentStatus === 'Pending Receipt'): ?>
                    <i class="fas fa-clock text-yellow-400"></i> Awaiting receipt confirmation
                <?php elseif ($currentStatus === 'In Transit'): ?>
                    <i class="fas fa-truck text-blue-400"></i> Package is on the way
                <?php else: ?>
                    <i class="fas fa-info-circle text-gray-400"></i> <?php echo htmlspecialchars($currentStatus); ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col gap-2 sm:items-end">
            <?php if ($isInWarehouse): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                    <i class="fas fa-check-circle"></i> Confirmed
                </span>

            <?php elseif ($hasBooking): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                    <i class="fas fa-calendar-check"></i> Delivery Scheduled
                </span>

            <?php elseif (strtolower($user_level) === 'warehouse' || strtolower($user_level) === 'superadmin'): ?>
                <?php
                    $defectCheckSql = "SELECT COUNT(*) as unresolved_count FROM defect_reports WHERE order_item_id = ? AND status != 'resolved'";
                    $defectCheckStmt = $conn->prepare($defectCheckSql);
                    $defectCheckStmt->bind_param("i", $item_id);
                    $defectCheckStmt->execute();
                    $defectCheckResult = $defectCheckStmt->get_result()->fetch_assoc();
                    $defectCheckStmt->close();
                    $hasUnresolvedDefects = (int) $defectCheckResult['unresolved_count'] > 0;
                    $canMarkInWarehouse = in_array($currentStatus, ['processing', 'customs_clearance']) && !$hasUnresolvedDefects;
                ?>

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
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">
                        <i class="fas fa-info-circle"></i> Not ready for warehouse receipt
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Warehouse Location -->
<?php if ($isInWarehouse): ?>
<div class="border border-gray-100 rounded-xl overflow-hidden mb-4 sm:mb-6 bg-gray-100">

    <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex items-center gap-2">
        <div class="w-7 h-7 rounded-md bg-gray-500 flex items-center justify-center">
            <i class="fas fa-map-marker-alt text-white text-xs"></i>
        </div>
        <h2 class="text-sm font-medium text-gray-700">Warehouse Location</h2>
    </div>

    <div class="bg-white px-4 sm:px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <?php if (!empty($itemInfo['warehouse_location'])): ?>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Assigned Location</p>
                <p class="text-base font-semibold text-gray-900">
                    <?php echo htmlspecialchars($itemInfo['warehouse_location']); ?>
                </p>
            </div>
            <button onclick="openEditLocationModal()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition-colors">
                <i class="fas fa-edit"></i> Edit Location
            </button>

        <?php else: ?>
            <div class="flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-yellow-400 text-sm"></i>
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


                    <!-- Owner Information -->
                    <div class="border border-gray-100 rounded-xl overflow-hidden mb-4 sm:mb-6 bg-gray-100">

                        <!-- Section Header -->
                        <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-md bg-gray-500 flex items-center justify-center">
                               <i class="fa-solid fa-user text-white text-xs"></i>
                            </div>
                            <h2 class="text-sm font-medium text-gray-700">Owner Information</h2>
                        </div>

                        <!-- Grid Fields -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-gray-100">

                            <div class="bg-white px-4 sm:px-5 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Customer Name</p>
                                <p class="text-sm font-semibold text-gray-900 break-words">
                                    <?php echo htmlspecialchars($orderInfo['customer_name']); ?>
                                </p>
                            </div>

                            <div class="bg-white px-4 sm:px-5 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Email Address</p>
                                <p class="text-sm font-semibold text-gray-900 break-all">
                                    <?php echo htmlspecialchars($orderInfo['customer_email']); ?>
                                </p>
                            </div>

                            <div class="bg-white px-4 sm:px-5 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Order Date</p>
                                <p class="text-sm font-semibold text-gray-900">
                                    <?php echo date('M j, Y g:i A', strtotime($orderInfo['order_date'])); ?>
                                </p>
                            </div>

                            <div class="bg-white px-4 sm:px-5 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Order Status</p>
                                <?php
                                $status = $orderInfo['order_status'];
                                $badge = match ($status) {
                                    'pending' => 'bg-yellow-50 text-yellow-700 border border-yellow-200',
                                    'processing' => 'bg-blue-50 text-blue-700 border border-blue-200',
                                    'completed' => 'bg-green-50 text-green-700 border border-green-200',
                                    'cancelled' => 'bg-red-50 text-red-700 border border-red-200',
                                    default => 'bg-gray-50 text-gray-600 border border-gray-200',
                                };
                                ?>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge; ?>">
                                    <?php echo htmlspecialchars(ucfirst($status)); ?>
                                </span>
                            </div>

                        </div>
                    </div>

                    <!-- Delivery Booking Information -->
                    <?php if ($hasBooking && $bookingInfo): ?>
                        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-4 sm:mb-6">
                            <div class="bg-gradient-to-r from-teal-500 to-teal-600 px-4 sm:px-6 py-3 sm:py-4">
                                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-truck mr-2 sm:mr-3"></i>
                                    Delivery Booking Information
                                </h2>
                            </div>

                            <div class="p-4 sm:p-6">
                                <!-- Booking Status Badge -->
                                <div class="mb-4 sm:mb-6 flex justify-center">
                                    <span class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-medium
                <?php
                switch ($bookingInfo['booking_status']) {
                    case 'pending':
                        echo 'bg-yellow-100 text-yellow-800';
                        break;
                    case 'confirmed':
                        echo 'bg-blue-100 text-blue-800';
                        break;
                    case 'in_transit':
                        echo 'bg-purple-100 text-purple-800';
                        break;
                    case 'delivered':
                        echo 'bg-green-100 text-green-800';
                        break;
                    case 'picked_up':
                        echo 'bg-teal-100 text-teal-800';
                        break;
                    case 'cancelled':
                        echo 'bg-red-100 text-red-800';
                        break;
                    default:
                        echo 'bg-gray-100 text-gray-800';
                }
                ?>">
                                        <i class="fas fa-circle text-xs"></i>
                                        <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $bookingInfo['booking_status']))); ?></span>
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                                    <!-- Booking Type -->
                                    <div>
                                        <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                            <i class="fas fa-tag mr-1"></i>Booking Type
                                        </div>
                                        <div class="text-base sm:text-lg font-semibold text-gray-900">
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm
                        <?php echo $bookingInfo['booking_type'] === 'delivery' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                                <i
                                                    class="fas fa-<?php echo $bookingInfo['booking_type'] === 'delivery' ? 'shipping-fast' : 'hand-holding-box'; ?> mr-2"></i>
                                                <?php echo htmlspecialchars(ucfirst($bookingInfo['booking_type'])); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Scheduled Date & Time -->
                                    <div>
                                        <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                            <i class="fas fa-calendar-alt mr-1"></i>Scheduled Date & Time
                                        </div>
                                        <div class="text-base sm:text-lg font-semibold text-gray-900">
                                            <?php
                                            if ($bookingInfo['delivery_date'] && $bookingInfo['delivery_time']) {
                                                echo date('M j, Y', strtotime($bookingInfo['delivery_date'])) . ' at ' .
                                                    date('g:i A', strtotime($bookingInfo['delivery_time']));
                                            } else {
                                                echo '<span class="text-gray-400">Not scheduled</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <?php if ($bookingInfo['booking_type'] === 'delivery'): ?>
                                        <!-- Courier Information (for delivery type) -->
                                        <?php if ($bookingInfo['courier_name']): ?>
                                            <div>
                                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-building mr-1"></i>Courier Service
                                                </div>
                                                <div class="text-base sm:text-lg font-semibold text-gray-900 break-words">
                                                    <?php echo htmlspecialchars($bookingInfo['courier_name']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($bookingInfo['tracking_number']): ?>
                                            <div>
                                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-barcode mr-1"></i>Tracking Number
                                                </div>
                                                <div class="text-base sm:text-lg font-semibold text-gray-900 font-mono break-all">
                                                    <?php echo htmlspecialchars($bookingInfo['tracking_number']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($bookingInfo['booking_reference']): ?>
                                            <div>
                                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-receipt mr-1"></i>Booking Reference
                                                </div>
                                                <div class="text-base sm:text-lg font-semibold text-gray-900 font-mono break-all">
                                                    <?php echo htmlspecialchars($bookingInfo['booking_reference']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($bookingInfo['driver_name']): ?>
                                            <div>
                                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-user-tie mr-1"></i>Driver Name
                                                </div>
                                                <div class="text-base sm:text-lg font-semibold text-gray-900 break-words">
                                                    <?php echo htmlspecialchars($bookingInfo['driver_name']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($bookingInfo['vehicle_plate_number']): ?>
                                            <div>
                                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-car mr-1"></i>Vehicle Plate Number
                                                </div>
                                                <div class="text-base sm:text-lg font-semibold text-gray-900 font-mono">
                                                    <?php echo htmlspecialchars($bookingInfo['vehicle_plate_number']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <!-- Pickup Information (for pickup type) -->
                                        <?php if ($bookingInfo['pickup_person_name']): ?>
                                            <div>
                                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-user mr-1"></i>Pickup Person
                                                </div>
                                                <div class="text-base sm:text-lg font-semibold text-gray-900 break-words">
                                                    <?php echo htmlspecialchars($bookingInfo['pickup_person_name']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($bookingInfo['pickup_person_contact']): ?>
                                            <div>
                                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-phone mr-1"></i>Contact Number
                                                </div>
                                                <div class="text-base sm:text-lg font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($bookingInfo['pickup_person_contact']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Estimated Times -->
                                    <?php if ($bookingInfo['estimated_pickup_time']): ?>
                                        <div>
                                            <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                <i class="fas fa-clock mr-1"></i>Estimated Pickup Time
                                            </div>
                                            <div class="text-base sm:text-lg font-semibold text-gray-900">
                                                <?php echo date('M j, Y g:i A', strtotime($bookingInfo['estimated_pickup_time'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($bookingInfo['actual_pickup_time']): ?>
                                        <div>
                                            <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                <i class="fas fa-check-circle mr-1 text-green-500"></i>Actual Pickup Time
                                            </div>
                                            <div class="text-base sm:text-lg font-semibold text-green-700">
                                                <?php echo date('M j, Y g:i A', strtotime($bookingInfo['actual_pickup_time'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($bookingInfo['estimated_delivery_time']): ?>
                                        <div>
                                            <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                <i class="fas fa-clock mr-1"></i>Estimated Delivery Time
                                            </div>
                                            <div class="text-base sm:text-lg font-semibold text-gray-900">
                                                <?php echo date('M j, Y g:i A', strtotime($bookingInfo['estimated_delivery_time'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($bookingInfo['actual_delivery_time']): ?>
                                        <div>
                                            <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                                <i class="fas fa-check-circle mr-1 text-green-500"></i>Actual Delivery Time
                                            </div>
                                            <div class="text-base sm:text-lg font-semibold text-green-700">
                                                <?php echo date('M j, Y g:i A', strtotime($bookingInfo['actual_delivery_time'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Supplier Information -->
                    <div class="border border-gray-100 rounded-xl overflow-hidden mb-4 sm:mb-6 bg-gray-100">

                        <!-- Section Header -->
                        <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-md bg-gray-500 flex items-center justify-center">
                              <i class="fa-solid fa-truck-field-un text-white"></i>
                            </div>
                            <h2 class="text-sm font-medium text-gray-700">Supplier Information</h2>
                        </div>

                        <!-- Grid Fields -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-gray-100">

                            <div class="bg-white px-4 sm:px-5 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Supplier Name</p>
                                <p class="text-sm font-semibold text-gray-900 break-words">
                                    <?php echo htmlspecialchars($supplierInfo['name']); ?>
                                </p>
                            </div>

                            <div class="bg-white px-4 sm:px-5 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Contact Person</p>
                                <p class="text-sm font-semibold text-gray-900 break-words">
                                    <?php echo htmlspecialchars($supplierInfo['contact']); ?>
                                </p>
                            </div>

                            <div class="bg-white px-4 sm:px-5 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Email</p>
                                <p class="text-sm font-semibold text-gray-900 break-all">
                                    <?php echo htmlspecialchars($supplierInfo['email']); ?>
                                </p>
                            </div>

                            <div class="bg-white px-4 sm:px-5 py-3.5">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Phone</p>
                                <p class="text-sm font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($supplierInfo['phone']); ?>
                                </p>
                            </div>

                        </div>
                    </div>
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center">
                        <a href="receiver_view_po_items_A.php?po_number=<?php echo urlencode($itemInfo['po_number']); ?>"
                            class="inline-flex items-center justify-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg transition-colors duration-200 shadow-lg text-sm sm:text-base">
                            <i class="fas fa-list"></i>
                            <span>View All P.O. Items</span>
                        </a>
                    </div>

                <?php endif; ?>
            </div>

            <!-- Set/Edit Location Modal -->
            <div id="locationModal" class="modal">
                <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-3 sm:mx-4">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 sm:px-6 py-3 sm:py-4 rounded-t-xl">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg sm:text-xl font-bold text-white">
                                <i class="fas fa-map-marker-alt mr-2"></i><span id="modalTitle">Set Location</span>
                            </h3>
                            <button onclick="closeLocationModal()" class="text-white hover:text-gray-200">
                                <i class="fas fa-times text-lg sm:text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-4 sm:p-6">
                        <div class="mb-4 sm:mb-6">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>Warehouse Location
                            </label>
                            <input type="text" id="warehouseLocationInput" placeholder="e.g., Aisle A, Shelf 3, Bin 5"
                                class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm sm:text-base">
                            <p class="text-xs text-gray-500 mt-1">Enter the physical location where this item is stored
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                            <button onclick="saveLocation()"
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 sm:py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 text-sm sm:text-base">
                                <i class="fas fa-save"></i>
                                <span>Save Location</span>
                            </button>
                            <button onclick="closeLocationModal()"
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2.5 sm:py-2 rounded-lg transition-colors duration-200 text-sm sm:text-base">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    z-index: 1000;
                }

                .modal.active {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                /* Prevent text overflow on small screens */
                @media (max-width: 640px) {
                    .break-words {
                        word-break: break-word;
                        overflow-wrap: break-word;
                    }

                    .break-all {
                        word-break: break-all;
                    }
                }
            </style>

            <script>
                const itemId = <?php echo $item_id; ?>;

                function updateTrackingStatus(newStatus) {
                    if (!confirm('Are you sure you want to update the tracking status to "' + newStatus + '"?\n\nThis will:\n• Mark the item as In Warehouse\n• Update received status\n• Record receipt date and time')) {
                        return;
                    }

                    // Show loading state
                    const button = event.target.closest('button');
                    const originalContent = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';

                    fetch('receiver_update_tracking_status_A1-1.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            item_id: itemId,
                            tracking_status: newStatus,
                            mark_as_received: true  // Add this flag
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('✓ Tracking status updated successfully!\n\nNew status: ' + newStatus);
                                window.location.reload();
                            } else {
                                alert('✗ Failed to update status: ' + (data.error || 'Unknown error'));
                                button.disabled = false;
                                button.innerHTML = originalContent;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('✗ Failed to update tracking status. Please try again.');
                            button.disabled = false;
                            button.innerHTML = originalContent;
                        });
                }

                function openSetLocationModal() {
                    document.getElementById('modalTitle').textContent = 'Set Location';
                    document.getElementById('warehouseLocationInput').value = '';
                    document.getElementById('locationModal').classList.add('active');
                    document.getElementById('warehouseLocationInput').focus();
                }

                function openEditLocationModal() {
                    document.getElementById('modalTitle').textContent = 'Edit Location';
                    document.getElementById('warehouseLocationInput').value = '<?php echo htmlspecialchars($itemInfo['warehouse_location'] ?? '', ENT_QUOTES); ?>';
                    document.getElementById('locationModal').classList.add('active');
                    document.getElementById('warehouseLocationInput').focus();
                }

                function closeLocationModal() {
                    document.getElementById('locationModal').classList.remove('active');
                }

                function saveLocation() {
                    const location = document.getElementById('warehouseLocationInput').value.trim();

                    if (!location) {
                        alert('Please enter warehouse location');
                        return;
                    }

                    // Send to server
                    fetch('receiver_update_location_A3.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            item_id: itemId,
                            warehouse_location: location
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Location saved successfully!');
                                closeLocationModal();
                                window.location.reload();
                            } else {
                                alert('Failed to save: ' + (data.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Failed to save location');
                        });
                }

                // Close modal when clicking outside
                document.getElementById('locationModal').addEventListener('click', function (e) {
                    if (e.target === this) {
                        closeLocationModal();
                    }
                });

                // Handle escape key
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        closeLocationModal();
                    }
                });

                // Defect Reporting Functions
                function openDefectReportModal() {
                    // Check if modal already exists
                    let modal = document.getElementById('defectReportModal');
                    if (!modal) {
                        // Create modal HTML
                        const modalHTML = `
                <div id="defectReportModal" class="modal">
                    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-3 sm:mx-4 max-h-[90vh] overflow-auto">
                        <div class="bg-gradient-to-r from-red-500 to-red-600 px-4 sm:px-6 py-3 sm:py-4 rounded-t-xl sticky top-0 z-10">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg sm:text-xl font-bold text-white">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>Report Item Defect
                                </h3>
                                <button onclick="closeDefectReportModal()" class="text-white hover:text-gray-200">
                                    <i class="fas fa-times text-lg sm:text-xl"></i>
                                </button>
                            </div>
                        </div>

                        <div class="p-4 sm:p-6">
                            <form id="defectReportForm" class="space-y-4">
                                <!-- Defect Type -->
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-tag mr-1 text-red-600"></i>Defect Type *
                                    </label>
                                    <select id="defectType" required
                                        class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm sm:text-base">
                                        <option value="">Select defect type...</option>
                                        <option value="damaged">Damaged/Broken</option>
                                        <option value="wrong_item">Wrong Item</option>
                                        <option value="missing_parts">Missing Parts</option>
                                        <option value="quality_issue">Quality Issue</option>
                                        <option value="size_mismatch">Size/Color Mismatch</option>
                                        <option value="manufacturing_defect">Manufacturing Defect</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <!-- Quantity Defective -->
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-boxes mr-1 text-red-600"></i>Quantity Defective *
                                    </label>
                                    <input type="number" id="quantityDefective" min="1" value="1" required
                                        class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm sm:text-base">
                                </div>

                                <!-- Severity -->
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-exclamation-circle mr-1 text-red-600"></i>Severity Level *
                                    </label>
                                    <select id="severity" required
                                        class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm sm:text-base">
                                        <option value="minor">Minor - Cosmetic issues only</option>
                                        <option value="moderate" selected>Moderate - Affects functionality</option>
                                        <option value="severe">Severe - Item unusable</option>
                                    </select>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-comment-alt mr-1 text-red-600"></i>Detailed Description *
                                    </label>
                                    <textarea id="defectDescription" rows="4" required
                                        placeholder="Describe the defect in detail..."
                                        class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm sm:text-base resize-none"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Provide as much detail as possible about the defect</p>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 pt-4 border-t border-gray-200">
                                    <button type="submit"
                                        class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 sm:py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 text-sm sm:text-base">
                                        <i class="fas fa-paper-plane"></i>
                                        <span>Submit Report</span>
                                    </button>
                                    <button type="button" onclick="closeDefectReportModal()"
                                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2.5 sm:py-2 rounded-lg transition-colors duration-200 text-sm sm:text-base">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                `;

                        // Insert modal into body
                        document.body.insertAdjacentHTML('beforeend', modalHTML);
                        modal = document.getElementById('defectReportModal');

                        // Setup form submission
                        document.getElementById('defectReportForm').addEventListener('submit', handleDefectReportSubmit);

                        // Close on outside click
                        modal.addEventListener('click', function (e) {
                            if (e.target === this) {
                                closeDefectReportModal();
                            }
                        });
                    }

                    modal.classList.add('active');
                    document.body.classList.add('overflow-hidden');
                }

                function closeDefectReportModal() {
                    const modal = document.getElementById('defectReportModal');
                    if (modal) {
                        modal.classList.remove('active');
                        document.body.classList.remove('overflow-hidden');
                        // Reset form
                        document.getElementById('defectReportForm').reset();
                    }
                }

                function handleDefectReportSubmit(e) {
                    e.preventDefault();

                    const submitButton = e.target.querySelector('button[type="submit"]');
                    const originalContent = submitButton.innerHTML;
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';

                    const reportData = {
                        item_id: itemId,
                        defect_type: document.getElementById('defectType').value,
                        quantity_defective: parseInt(document.getElementById('quantityDefective').value),
                        severity: document.getElementById('severity').value,
                        defect_description: document.getElementById('defectDescription').value
                    };

                    fetch('warehouse_staff_report_defect_C-B.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(reportData)
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('✓ Defect report submitted successfully!\n\nThe report has been logged and will be reviewed.');
                                closeDefectReportModal();
                                // Reload to show defect indicator
                                window.location.reload();
                            } else {
                                alert('✗ Failed to submit report: ' + (data.error || 'Unknown error'));
                                submitButton.disabled = false;
                                submitButton.innerHTML = originalContent;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('✗ Failed to submit defect report. Please try again.');
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalContent;
                        });
                }

                // Load and display defect reports for this item
                function loadDefectReports() {
                    fetch(`warehouse_staff_report_defect_C-B.php?item_id=${itemId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.defects && data.defects.length > 0) {
                                displayDefectWarning(data.defects);
                            }
                        })
                        .catch(error => console.error('Error loading defects:', error));
                }

                function displayDefectWarning(defects) {
                    // Filter out resolved defects - only show unresolved ones
                    const unresolvedDefects = defects.filter(d => d.status !== 'resolved');

                    const trackingSection = document.querySelector('.bg-gradient-to-r.from-green-50, .bg-gradient-to-r.from-indigo-50');
                    if (trackingSection && unresolvedDefects.length > 0) {
                        const warningHTML = `
            <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="flex items-start">
                    <div class="bg-red-500 p-2 sm:p-3 rounded-lg mr-3 sm:mr-4 flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-white text-lg sm:text-2xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs sm:text-sm text-red-700 font-medium mb-1 sm:mb-2">
                            DEFECT REPORTED
                        </div>
                        <div class="text-lg sm:text-xl font-bold text-red-900 mb-2">
                            ${unresolvedDefects.length} Unresolved Defect Report${unresolvedDefects.length > 1 ? 's' : ''}
                        </div>
                        <div class="space-y-2">
                            ${unresolvedDefects.map(d => `
                                <div class="bg-white rounded p-2 text-sm">
                                    <div class="font-semibold text-red-800">${d.defect_type}</div>
                                    <div class="text-gray-600">${d.defect_description}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Reported by ${d.reporter_name} on ${new Date(d.reported_at).toLocaleDateString()}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
                        trackingSection.insertAdjacentHTML('beforebegin', warningHTML);
                    }
                }

                // Load defects on page load
                document.addEventListener('DOMContentLoaded', loadDefectReports);
            </script>
</body>

</html>