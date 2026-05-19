<?php
// dispatcher_view_booking.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$user_subrole = $_SESSION['noble_subrole'] ?? '';
if ($user_subrole !== 'dispatcher') {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

$dispatcher_id = $_SESSION['noble_id'];
$booking_id    = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    header("Location: " . BASE_URL . "/logisticdispatcherdashboard");
    exit();
}

// Get all bookings for this order
$checkBookingsSql = "SELECT db.id, db.booking_type, db.booking_status, db.tracking_number,
        db.courier_name, db.booking_reference, db.estimated_pickup_time, db.actual_pickup_time,
        db.estimated_delivery_time, db.actual_delivery_time, db.pickup_person_name,
        db.pickup_person_contact, db.driver_name, db.vehicle_plate_number,
        db.dispatcher_id, db.created_at, db.updated_at,
        tv.vehicle_type, ds.delivery_date, ds.delivery_time
    FROM delivery_bookings db
    LEFT JOIN transportify_vehicle_list tv ON db.vehicle_id = tv.id
    LEFT JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
    WHERE db.order_id = (SELECT order_id FROM delivery_bookings WHERE id = ?)
    ORDER BY db.created_at DESC";
$checkStmt = $conn->prepare($checkBookingsSql);
$checkStmt->bind_param("i", $booking_id);
$checkStmt->execute();
$allBookings = $checkStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$checkStmt->close();

$hasMultipleBookings = count($allBookings) > 1;

// Get booking details
$sql = "SELECT db.*, ds.delivery_date, ds.delivery_time, ds.delivery_notes, ds.id as delivery_schedule_id,
        o.customer_name, ds.item_type, o.email, o.mobile, o.address, o.final_total,
        tv.courier_name, tv.vehicle_type,
        CASE WHEN ds.item_type = 'replacement'
            THEN (SELECT COUNT(*) FROM replacement_requests WHERE delivery_schedule_id = ds.id)
            ELSE (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id)
        END as total_items,
        CASE WHEN ds.item_type = 'replacement'
            THEN (SELECT COUNT(*) FROM replacement_requests WHERE delivery_schedule_id = ds.id AND status IN ('item_is_loaded','out_for_delivery'))
            ELSE (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id AND tracking_status = 'item_is_loaded')
        END as loaded_items
    FROM delivery_bookings db
    INNER JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
    INNER JOIN orders o ON db.order_id = o.id
    LEFT JOIN transportify_vehicle_list tv ON db.vehicle_id = tv.id
    WHERE db.id = ? AND db.dispatcher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $dispatcher_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    $_SESSION['error_message'] = "Booking not found or not assigned to you";
    header("Location: " . BASE_URL . "/logisticdispatcherdashboard");
    exit();
}

$isReplacement = ($booking['item_type'] === 'replacement');

// Get replacement details if applicable
$replacementDetails   = [];
$totalReplacementQty  = 0;
if ($isReplacement) {
    $replSql = "SELECT rr.*, oi.product_name, oi.variant_color, oi.size, oi.price
                FROM replacement_requests rr
                INNER JOIN order_items oi ON rr.order_item_id = oi.id
                WHERE rr.delivery_schedule_id = ?
                ORDER BY oi.product_name";
    $replStmt = $conn->prepare($replSql);
    $replStmt->bind_param("i", $booking['delivery_schedule_id']);
    $replStmt->execute();
    $replacementDetails = $replStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $replStmt->close();
    foreach ($replacementDetails as $r) $totalReplacementQty += $r['replacement_quantity'];
}

// Get items
if ($isReplacement) {
    $itemsSql = "SELECT oi.id, oi.product_name, oi.variant_color, oi.size, oi.price,
                        oi.warehouse_location, oi.po_number, oi.qr_code,
                        rr.id as replacement_id, rr.replacement_quantity as quantity,
                        rr.reason as replacement_reason, rr.details as replacement_details,
                        rr.status as replacement_status
                 FROM order_items oi
                 INNER JOIN replacement_requests rr ON oi.id = rr.order_item_id
                 WHERE rr.delivery_schedule_id = ?
                 ORDER BY oi.product_name";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $booking['delivery_schedule_id']);
} else {
    $itemsSql  = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $booking['order_id']);
}
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'toggle_item_loaded') {
        $item_id               = intval($_POST['item_id']);
        $current_status        = $_POST['current_status'];
        $is_replacement        = intval($_POST['is_replacement'] ?? 0);
        $replacement_request_id = intval($_POST['replacement_request_id'] ?? 0);

        if ($is_replacement && $replacement_request_id) {
            $replacement_status = ($current_status === 'ready_for_pickup') ? 'item_is_loaded' : 'ready_for_pickup';
            $message = $replacement_status === 'item_is_loaded' ? "Replacement item is now loaded!" : "Replacement item marked as ready for pickup!";
            $upd = $conn->prepare("UPDATE replacement_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->bind_param("si", $replacement_status, $replacement_request_id);
            if ($upd->execute()) $_SESSION['success_message'] = $message;
            $upd->close();
        } else {
            $new_status = ($current_status === 'ready_for_pickup') ? 'item_is_loaded' : 'ready_for_pickup';
            $upd = $conn->prepare("UPDATE order_items SET tracking_status = ? WHERE id = ?");
            $upd->bind_param("si", $new_status, $item_id);
            if ($upd->execute()) $_SESSION['success_message'] = "Item status updated!";
            $upd->close();
        }
        header("Location: " . BASE_URL . "/logisticdispatcherviewbooking?booking_id=" . $booking_id);
        exit();
    }

    if ($_POST['action'] === 'mark_all_loaded') {
        if ($isReplacement) {
            $upd = $conn->prepare("UPDATE replacement_requests SET status = 'item_is_loaded', updated_at = CURRENT_TIMESTAMP WHERE delivery_schedule_id = ?");
            $upd->bind_param("i", $booking['delivery_schedule_id']);
        } else {
            $upd = $conn->prepare("UPDATE order_items SET tracking_status = 'item_is_loaded' WHERE order_id = ?");
            $upd->bind_param("i", $booking['order_id']);
        }
        if ($upd->execute()) $_SESSION['success_message'] = "All items marked as loaded!";
        $upd->close();
        header("Location: " . BASE_URL . "/logisticdispatcherviewbooking?booking_id=" . $booking_id);
        exit();
    }

    if ($_POST['action'] === 'start_transit') {
        $conn->begin_transaction();
        try {
            $upd = $conn->prepare("UPDATE delivery_bookings SET booking_status = 'in_transit', actual_pickup_time = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->bind_param("i", $booking_id);
            $upd->execute();
            $upd->close();

            if ($isReplacement) {
                $upd = $conn->prepare("UPDATE replacement_requests SET status = 'out_for_delivery', updated_at = CURRENT_TIMESTAMP WHERE delivery_schedule_id = ?");
                $upd->bind_param("i", $booking['delivery_schedule_id']);
                $upd->execute();
                $upd->close();
            } else {
                $order_status = ($booking['booking_type'] === 'pickup') ? 'Out for Pickup' : 'Out for Delivery';
                $upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $upd->bind_param("si", $order_status, $booking['order_id']);
                $upd->execute();
                $upd->close();
            }

            $delivery_status = ($booking['booking_type'] === 'pickup') ? 'out_for_pickup' : 'out_for_delivery';
            $upd = $conn->prepare("UPDATE delivery_schedules SET delivery_status = ? WHERE id = ?");
            $upd->bind_param("si", $delivery_status, $booking['delivery_schedule_id']);
            $upd->execute();
            $upd->close();

            $conn->commit();
            $action_text = ($booking['booking_type'] === 'pickup') ? 'Pickup' : 'Delivery';
            $_SESSION['success_message'] = "$action_text started! Status changed to In Transit.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
        header("Location: " . BASE_URL . "/logisticdispatcherviewbooking?booking_id=" . $booking_id);
        exit();
    }
}

$isInTransit = $booking['booking_status'] === 'in_transit';
$isCompleted = in_array($booking['booking_status'], ['delivered', 'picked_up']);
$allLoaded   = $booking['loaded_items'] === $booking['total_items'] && $booking['total_items'] > 0;
$loadPercent = $booking['total_items'] > 0 ? ($booking['loaded_items'] / $booking['total_items']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Delivery - Noble Home</title>
</head>
<body class="bg-gray-100 min-h-screen">

    <?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

    <div class="max-w-5xl mx-auto px-4 py-8 space-y-5">

        <!-- Header -->
        <div>
            <a href="<?= BASE_URL ?>/logisticdispatcherdashboard"
               class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 mb-4">
                <i class="fas fa-arrow-left"></i> Back to My Deliveries
            </a>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold text-gray-900">
                            <?= $isReplacement ? 'Replacement' : 'Manage' ?> <?= ucfirst($booking['booking_type']) ?>
                        </h1>
                        <?php if ($isReplacement): ?>
                            <span class="inline-flex items-center gap-1.5 bg-orange-100 text-orange-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                <i class="fas fa-sync-alt"></i> REPLACEMENT
                            </span>
                        <?php endif; ?>
                        <?php if ($hasMultipleBookings && !$isReplacement): ?>
                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                <i class="fas fa-layer-group"></i> <?= count($allBookings) ?> Bookings
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-500 text-sm mt-0.5">Order #<?= $booking['order_id'] ?> &middot; Booking #<?= $booking_id ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white rounded-lg border-l-4 border-blue-500 p-4">
                <p class="text-xs text-gray-500 uppercase mb-1">Items Progress</p>
                <p class="text-2xl font-bold text-gray-900"><?= $booking['loaded_items'] ?><span class="text-lg text-gray-400">/<?= $booking['total_items'] ?></span></p>
            </div>
            <div class="bg-white rounded-lg border-l-4 border-purple-500 p-4">
                <p class="text-xs text-gray-500 uppercase mb-1">Status</p>
                <p class="text-base font-bold text-purple-900 capitalize"><?= str_replace('_', ' ', $booking['booking_status']) ?></p>
            </div>
            <div class="bg-white rounded-lg border-l-4 border-green-500 p-4">
                <p class="text-xs text-gray-500 uppercase mb-1">Delivery Date</p>
                <p class="text-sm font-bold text-gray-900"><?= date('M d, Y', strtotime($booking['delivery_date'])) ?></p>
            </div>
            <div class="bg-white rounded-lg border-l-4 border-orange-500 p-4">
                <p class="text-xs text-gray-500 uppercase mb-1">Type</p>
                <p class="text-base font-bold text-orange-900 capitalize"><?= $booking['booking_type'] ?></p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-50 border border-green-300 text-green-800 text-sm px-4 py-3 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-50 border border-red-300 text-red-800 text-sm px-4 py-3 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Multiple Bookings Banner -->
        <?php if ($hasMultipleBookings && !$isReplacement): ?>
        <div class="bg-white rounded-xl border border-blue-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4">
                <div class="flex items-center gap-3">
                    <i class="fas fa-info-circle text-blue-500"></i>
                    <p class="font-semibold text-gray-800 text-sm">Multiple Bookings (<?= count($allBookings) ?> total)</p>
                </div>
                <button onclick="document.getElementById('bookingsContainer').classList.toggle('hidden')"
                        class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                    Toggle Details
                </button>
            </div>
            <div id="bookingsContainer" class="hidden divide-y divide-gray-100 px-5 pb-4 space-y-3">
                <?php foreach ($allBookings as $ob): ?>
                <div class="pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-sm text-gray-900">Booking #<?= $ob['id'] ?></span>
                            <?php if ($ob['id'] == $booking_id): ?>
                                <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full font-semibold">Current</span>
                            <?php endif; ?>
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold capitalize <?= $ob['booking_type'] === 'delivery' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700' ?>">
                                <?= $ob['booking_type'] ?>
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-gray-100 text-gray-700 capitalize">
                                <?= str_replace('_', ' ', $ob['booking_status']) ?>
                            </span>
                        </div>
                        <?php if ($ob['id'] != $booking_id): ?>
                            <a href="<?= BASE_URL ?>/logisticdispatcherviewbooking?booking_id=<?= $ob['id'] ?>"
                               class="text-xs bg-blue-500 text-white px-3 py-1.5 rounded-lg hover:bg-blue-600 transition-colors font-semibold">
                                View
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs text-gray-600">
                        <?php if ($ob['driver_name'] ?? $ob['pickup_person_name']): ?>
                            <span><?= $ob['booking_type'] === 'pickup' ? 'Person' : 'Driver' ?>: <strong class="text-gray-900"><?= htmlspecialchars($ob['driver_name'] ?? $ob['pickup_person_name']) ?></strong></span>
                        <?php endif; ?>
                        <?php if ($ob['vehicle_plate_number']): ?>
                            <span>Plate: <strong class="text-gray-900"><?= htmlspecialchars($ob['vehicle_plate_number']) ?></strong></span>
                        <?php endif; ?>
                        <?php if ($ob['tracking_number']): ?>
                            <span>Tracking: <strong class="font-mono text-blue-600"><?= htmlspecialchars($ob['tracking_number']) ?></strong></span>
                        <?php endif; ?>
                        <?php if ($ob['delivery_date']): ?>
                            <span>Date: <strong class="text-gray-900"><?= date('M d, Y', strtotime($ob['delivery_date'])) ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabbed Info Card -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <!-- Tab Headers -->
            <div class="flex overflow-x-auto border-b border-gray-200" id="tabHeaders">
                <?php
                $tabs = [
                    ['id' => 'status',   'label' => 'Status',   'icon' => 'fa-info-circle'],
                    ['id' => 'customer', 'label' => 'Customer', 'icon' => 'fa-user'],
                    ['id' => 'handler',  'label' => $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Driver', 'icon' => 'fa-id-card'],
                    ['id' => 'schedule', 'label' => 'Schedule', 'icon' => 'fa-calendar'],
                ];
                if ($booking['delivery_notes'] || $booking['booking_notes']) {
                    $tabs[] = ['id' => 'notes', 'label' => 'Notes', 'icon' => 'fa-sticky-note'];
                }
                if ($isReplacement && !empty($replacementDetails)) {
                    $tabs[] = ['id' => 'replacement', 'label' => 'Replacement', 'icon' => 'fa-sync-alt'];
                }
                foreach ($tabs as $i => $tab): ?>
                <button onclick="switchTab('<?= $tab['id'] ?>')" id="tab-<?= $tab['id'] ?>"
                        class="tab-btn flex-shrink-0 px-4 py-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap <?= $i === 0 ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-800' ?>">
                    <i class="fas <?= $tab['icon'] ?> mr-1"></i><?= $tab['label'] ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Tab Contents -->
            <div class="p-5">

                <!-- Status -->
                <div id="content-status" class="tab-content">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Booking Status</p>
                            <p class="font-semibold text-purple-900 capitalize text-sm"><?= str_replace('_', ' ', $booking['booking_status']) ?></p>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Type</p>
                            <p class="font-semibold text-blue-900 capitalize text-sm"><?= $booking['booking_type'] ?></p>
                        </div>
                        <?php if ($booking['tracking_number']): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Tracking Number</p>
                            <p class="font-mono font-bold text-green-900 text-xs"><?= htmlspecialchars($booking['tracking_number']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['booking_reference']): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Booking Reference</p>
                            <p class="font-mono font-bold text-yellow-900 text-xs"><?= htmlspecialchars($booking['booking_reference']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer -->
                <div id="content-customer" class="tab-content hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Name</p>
                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($booking['customer_name']) ?></p>
                        </div>
                        <?php if ($booking['mobile']): ?>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Mobile</p>
                            <a href="tel:<?= htmlspecialchars($booking['mobile']) ?>" class="font-semibold text-blue-600 hover:text-blue-800 text-sm flex items-center gap-2">
                                <i class="fas fa-phone text-xs"></i><?= htmlspecialchars($booking['mobile']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['email']): ?>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Email</p>
                            <a href="mailto:<?= htmlspecialchars($booking['email']) ?>" class="font-semibold text-blue-600 hover:text-blue-800 text-sm break-all">
                                <?= htmlspecialchars($booking['email']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="bg-gray-50 rounded-lg p-3 sm:col-span-2 md:col-span-3">
                            <p class="text-xs text-gray-400 mb-1">Delivery Address</p>
                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($booking['address']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Handler -->
                <div id="content-handler" class="tab-content hidden">
                    <?php
                    $handler_name    = $booking['booking_type'] === 'pickup' ? $booking['pickup_person_name'] : $booking['driver_name'];
                    $handler_contact = $booking['booking_type'] === 'pickup' ? $booking['pickup_person_contact'] : null;
                    $handler_label   = $booking['booking_type'] === 'pickup' ? 'Pickup Person' : 'Driver';
                    ?>
                    <?php if ($handler_name || $booking['vehicle_plate_number']): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <?php if ($handler_name): ?>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1"><?= $handler_label ?> Name</p>
                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($handler_name) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($handler_contact): ?>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Contact</p>
                            <a href="tel:<?= htmlspecialchars($handler_contact) ?>" class="font-semibold text-blue-600 hover:text-blue-800 text-sm flex items-center gap-2">
                                <i class="fas fa-phone text-xs"></i><?= htmlspecialchars($handler_contact) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['vehicle_plate_number']): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Vehicle Plate</p>
                            <p class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($booking['vehicle_plate_number']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-info-circle text-3xl mb-2 block"></i>
                        <p class="text-sm">No handler information available yet.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Schedule -->
                <div id="content-schedule" class="tab-content hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 sm:col-span-2">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Scheduled Date</p>
                                    <p class="font-bold text-blue-900"><?= date('M d, Y', strtotime($booking['delivery_date'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Scheduled Time</p>
                                    <p class="font-bold text-blue-900"><?= date('g:i A', strtotime($booking['delivery_time'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php if ($booking['courier_name']): ?>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Courier</p>
                            <p class="font-semibold text-sm"><?= htmlspecialchars($booking['courier_name']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['vehicle_type']): ?>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Vehicle Type</p>
                            <p class="font-semibold text-sm"><?= htmlspecialchars($booking['vehicle_type']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['estimated_pickup_time'] || $booking['estimated_delivery_time']): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-gray-600 uppercase mb-2">Estimated Times</p>
                            <?php if ($booking['estimated_pickup_time']): ?>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-500">Pickup:</span>
                                <span class="font-semibold"><?= date('M d, g:i A', strtotime($booking['estimated_pickup_time'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking['estimated_delivery_time']): ?>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Delivery:</span>
                                <span class="font-semibold"><?= date('M d, g:i A', strtotime($booking['estimated_delivery_time'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['actual_pickup_time'] || $booking['actual_delivery_time']): ?>
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-gray-600 uppercase mb-2">Actual Times</p>
                            <?php if ($booking['actual_pickup_time']): ?>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-500">Picked up:</span>
                                <span class="font-bold text-green-700"><?= date('M d, g:i A', strtotime($booking['actual_pickup_time'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking['actual_delivery_time']): ?>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Delivered:</span>
                                <span class="font-bold text-blue-700"><?= date('M d, g:i A', strtotime($booking['actual_delivery_time'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($booking['delivery_notes'] || $booking['booking_notes']): ?>
                <div id="content-notes" class="tab-content hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php if ($booking['delivery_notes']): ?>
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                            <p class="font-semibold text-orange-900 text-sm mb-2 flex items-center gap-2"><i class="fas fa-clipboard text-orange-500"></i> Delivery Notes</p>
                            <p class="text-xs text-orange-800 whitespace-pre-wrap"><?= htmlspecialchars($booking['delivery_notes']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['booking_notes']): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="font-semibold text-blue-900 text-sm mb-2 flex items-center gap-2"><i class="fas fa-sticky-note text-blue-500"></i> Booking Notes</p>
                            <p class="text-xs text-blue-800 whitespace-pre-wrap"><?= htmlspecialchars($booking['booking_notes']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Replacement Tab -->
                <?php if ($isReplacement && !empty($replacementDetails)): ?>
                <div id="content-replacement" class="tab-content hidden">
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500 mb-1">Total Items</p>
                            <p class="text-2xl font-bold text-orange-700"><?= count($replacementDetails) ?></p>
                        </div>
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500 mb-1">Total Qty</p>
                            <p class="text-2xl font-bold text-orange-700"><?= $totalReplacementQty ?> pcs</p>
                        </div>
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500 mb-1">Total Value</p>
                            <p class="text-2xl font-bold text-orange-700">₱<?php
                                $tv = 0;
                                foreach ($replacementDetails as $r) $tv += $r['price'] * $r['replacement_quantity'];
                                echo number_format($tv, 2);
                            ?></p>
                        </div>
                    </div>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        <?php foreach ($replacementDetails as $i => $repl): ?>
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="bg-orange-200 text-orange-900 text-xs font-bold px-2 py-0.5 rounded-full">#<?= $i + 1 ?></span>
                                        <span class="text-xs text-orange-600 font-medium">Req #<?= $repl['id'] ?></span>
                                    </div>
                                    <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($repl['product_name']) ?></p>
                                    <div class="flex gap-3 text-xs text-gray-500 mt-0.5">
                                        <?php if ($repl['variant_color']): ?><span>Color: <?= htmlspecialchars($repl['variant_color']) ?></span><?php endif; ?>
                                        <?php if ($repl['size']): ?><span>Size: <?= htmlspecialchars($repl['size']) ?></span><?php endif; ?>
                                    </div>
                                    <div class="flex gap-4 text-xs mt-1">
                                        <span class="text-gray-500">Reason: <span class="font-medium text-gray-700 capitalize"><?= str_replace('_', ' ', $repl['reason']) ?></span></span>
                                        <span class="text-gray-500">Status: <span class="font-medium text-orange-700 capitalize"><?= str_replace('_', ' ', $repl['status']) ?></span></span>
                                    </div>
                                    <?php if ($repl['details']): ?>
                                    <p class="text-xs text-gray-500 mt-1"><?= nl2br(htmlspecialchars($repl['details'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right ml-3">
                                    <p class="font-bold text-orange-700"><?= $repl['replacement_quantity'] ?> pcs</p>
                                    <p class="text-xs text-gray-500">₱<?= number_format($repl['price'] * $repl['replacement_quantity'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Items Loading Section -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-boxes text-orange-500"></i>
                    <?= $isReplacement ? 'Replacement Items' : 'Items to Load' ?>
                </h3>
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Sticker Status -->
                    <div id="stickerNotPrintedBadge" class="inline-flex items-center gap-1.5 bg-yellow-100 border border-yellow-300 text-yellow-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <i class="fas fa-exclamation-circle"></i> Sticker Not Yet Printed
                    </div>
                    <div id="stickerPrintedBadge" class="hidden items-center gap-1.5 bg-green-100 border border-green-300 text-green-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <i class="fas fa-check-circle"></i> <span id="stickerPrintedText">Sticker Printed</span>
                    </div>

                    <a href="<?= BASE_URL ?>/generateshippingsticker?booking_id=<?= $booking_id ?>"
                       target="_blank"
                       onclick="markStickerOpened()"
                       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                        <i class="fas fa-tag"></i> Print Sticker
                    </a>

                    <?php if (!$allLoaded && !$isCompleted && !$isInTransit): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Mark all items as loaded?');">
                        <input type="hidden" name="action" value="mark_all_loaded">
                        <button type="submit" class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                            <i class="fas fa-check-double"></i> Mark All Loaded
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="px-5 pt-4">
                <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                    <span>Loading Progress</span>
                    <span class="font-semibold"><?= $booking['loaded_items'] ?> / <?= $booking['total_items'] ?> loaded</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                    <div class="bg-gradient-to-r from-blue-500 to-green-500 h-2.5 rounded-full transition-all" style="width: <?= $loadPercent ?>%"></div>
                </div>
            </div>

            <!-- Items List -->
            <div class="px-5 pb-5 space-y-2">
                <?php foreach ($items as $item):
                    $isReplItem = $isReplacement;
                    $loaded     = $isReplItem
                        ? ($item['replacement_status'] === 'item_is_loaded')
                        : ($item['tracking_status'] === 'item_is_loaded');
                    $curStatus  = $isReplItem ? $item['replacement_status'] : $item['tracking_status'];
                    $borderCls  = $loaded
                        ? ($isReplItem ? 'border-orange-300 bg-orange-50' : 'border-blue-300 bg-blue-50')
                        : 'border-yellow-300 bg-yellow-50';
                ?>
                <div class="flex items-center justify-between p-3 rounded-lg border-2 <?= $borderCls ?> hover:shadow-sm transition-shadow">
                    <div class="flex-1">
                        <?php if ($isReplItem): ?>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT
                            </span>
                            <span class="text-xs text-gray-500">Req #<?= $item['replacement_id'] ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex items-center flex-wrap gap-x-4 gap-y-0.5">
                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($item['product_name']) ?></p>
                            <div class="flex items-center gap-3 text-xs text-gray-500">
                                <?php if ($item['variant_color']): ?><span>Color: <strong><?= htmlspecialchars($item['variant_color']) ?></strong></span><?php endif; ?>
                                <?php if ($item['size']): ?><span>Size: <strong><?= htmlspecialchars($item['size']) ?></strong></span><?php endif; ?>
                                <span>Qty: <strong><?= $item['quantity'] ?></strong></span>
                            </div>
                        </div>
                        <?php if ($isReplItem): ?>
                        <p class="text-xs text-orange-600 mt-0.5">Reason: <span class="font-medium capitalize"><?= str_replace('_', ' ', $item['replacement_reason']) ?></span></p>
                        <?php endif; ?>
                        <?php if (!empty($item['warehouse_location'])): ?>
                        <p class="text-xs mt-0.5 flex items-center gap-1">
                            <i class="fas fa-map-marker-alt text-red-400"></i>
                            <span class="bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded"><?= htmlspecialchars($item['warehouse_location']) ?></span>
                        </p>
                        <?php endif; ?>
                    </div>

                    <div class="ml-3">
                        <?php if (!$isCompleted && !$isInTransit): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle_item_loaded">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <input type="hidden" name="current_status" value="<?= $curStatus ?>">
                            <?php if ($isReplItem): ?>
                            <input type="hidden" name="is_replacement" value="1">
                            <input type="hidden" name="replacement_request_id" value="<?= $item['replacement_id'] ?>">
                            <?php endif; ?>
                            <button type="submit"
                                    class="text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors <?= $loaded ? ($isReplItem ? 'bg-orange-500 hover:bg-orange-600' : 'bg-blue-500 hover:bg-blue-600') : 'bg-yellow-500 hover:bg-yellow-600' ?>">
                                <i class="fas <?= $loaded ? 'fa-check' : 'fa-box' ?> mr-1"></i>
                                <?= $loaded ? 'Loaded' : 'Load' ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-white px-4 py-2 rounded-lg text-sm font-semibold <?= $loaded ? ($isReplItem ? 'bg-orange-400' : 'bg-blue-400') : 'bg-yellow-400' ?>">
                            <i class="fas <?= $loaded ? 'fa-check' : 'fa-clock' ?> mr-1"></i>
                            <?= $loaded ? 'Loaded' : 'Pending' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Start Transit / In Transit Banner -->
        <?php if ($allLoaded && !$isInTransit && !$isCompleted): ?>
        <div class="bg-green-500 rounded-xl p-5 text-white">
            <h3 class="font-bold text-lg mb-1 flex items-center gap-2">
                <i class="fas fa-<?= $booking['booking_type'] === 'pickup' ? 'box' : 'truck-moving' ?>"></i>
                Ready to Start <?= $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery' ?>
            </h3>
            <p class="text-green-100 text-sm mb-4">All items are loaded. Click below to mark as "In Transit".</p>
            <form method="POST" onsubmit="return confirm('Are you sure all items are loaded and ready to <?= $booking['booking_type'] === 'pickup' ? 'pick up' : 'deliver' ?>?');">
                <input type="hidden" name="action" value="start_transit">
                <button type="submit" class="bg-white text-green-600 font-bold px-6 py-2 rounded-lg hover:bg-green-50 transition-colors">
                    <i class="fas fa-play-circle mr-2"></i>
                    Start <?= $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery' ?> (In Transit)
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($isInTransit): ?>
        <div class="bg-blue-500 rounded-xl p-5 text-white">
            <h3 class="font-bold text-lg mb-1 flex items-center gap-2">
                <i class="fas fa-shipping-fast"></i>
                <?= $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery' ?> In Progress
            </h3>
            <p class="text-blue-100 text-sm">This <?= $booking['booking_type'] ?> is currently in transit. Drive safely!</p>
        </div>
        <?php endif; ?>

    </div>

    <script>
    // Tab switching
    function switchTab(name) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('border-purple-600', 'text-purple-600');
            b.classList.add('border-transparent', 'text-gray-500');
        });
        document.getElementById('content-' + name).classList.remove('hidden');
        const btn = document.getElementById('tab-' + name);
        btn.classList.add('border-purple-600', 'text-purple-600');
        btn.classList.remove('border-transparent', 'text-gray-500');
    }

    // Sticker print status
    (function() {
        const printed    = document.getElementById('stickerPrintedBadge');
        const notPrinted = document.getElementById('stickerNotPrintedBadge');
        const printedTxt = document.getElementById('stickerPrintedText');

        function showPrinted(ts) {
            printed.classList.remove('hidden');
            printed.style.display = 'inline-flex';
            notPrinted.classList.add('hidden');
            if (ts) printedTxt.textContent = 'Printed: ' + ts;
        }

        function checkStatus() {
            fetch('<?= BASE_URL ?>/getstickerprintstatus?booking_id=<?= $booking_id ?>')
                .then(r => r.json())
                .then(d => { if (d.printed_at) { showPrinted(d.printed_at_formatted); clearInterval(poll); } })
                .catch(() => {});
        }

        checkStatus();
        const poll = setInterval(checkStatus, 3000);
    })();

    function markStickerOpened() {
        const nb = document.getElementById('stickerNotPrintedBadge');
        nb.innerHTML = '<i class="fas fa-external-link-alt mr-1"></i><span>Sticker Page Opened</span>';
        nb.className = 'inline-flex items-center gap-1.5 bg-blue-100 border border-blue-300 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-full';
    }
    </script>

</body>
</html>