    <?php
    // dispatcher_view_booking.php
    session_name("nobleadmin");
    session_start();

    include '../../connection/connect.php';
    require_once '../role/roleaccount.php';
    require_role(['logistic']);

    if (!isset($_SESSION['noble_user'])) {
        header("Location: ../../loginpage/index.php");
        exit();
    }

    // Check if user is a dispatcher
    $user_subrole = $_SESSION['noble_subrole'] ?? '';
    if ($user_subrole !== 'dispatcher') {
        header("Location: logistic-main-dashboard-page-1.php");
        exit();
    }

    $dispatcher_id = $_SESSION['noble_id'];
    $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

    if (!$booking_id) {
        header("Location:  logistic-dispatcher-dashboard-page-13.php");
        exit();
    }

    // Check if there are other bookings for this order - GET ALL IMPORTANT DETAILS
    $checkBookingsSql = "SELECT 
        db.id,
        db.booking_type,
        db.booking_status,
        db.tracking_number,
        db.courier_name,
        db.booking_reference,
        db.estimated_pickup_time,
        db.actual_pickup_time,
        db.estimated_delivery_time,
        db.actual_delivery_time,
        db.pickup_person_name,
        db.pickup_person_contact,
        db.driver_name,
        db.vehicle_plate_number,
        db.dispatcher_id,
        db.created_at,
        db.updated_at,
        tv.vehicle_type,
        ds.delivery_date,
        ds.delivery_time
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

    // Get booking details - ensure it's assigned to this dispatcher
    $sql = "SELECT 
    db.*,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.id as delivery_schedule_id,
    o.customer_name,
    ds.item_type,
    o.email,
    o.mobile,
    o.address,
    o.final_total,
    tv.courier_name,
    tv.vehicle_type,
    -- Count items: if replacement, count replacement_requests, else count order_items
    CASE 
        WHEN ds.item_type = 'replacement' THEN 
            (SELECT COUNT(*) FROM replacement_requests WHERE delivery_schedule_id = ds.id)
        ELSE (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id)
    END as total_items,
    -- Count loaded items: if replacement, check replacement_requests status, else check order_items
    CASE 
        WHEN ds.item_type = 'replacement' THEN 
            (SELECT COUNT(*) FROM replacement_requests 
             WHERE delivery_schedule_id = ds.id 
             AND status IN ('item_is_loaded', 'out_for_delivery'))
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
    // Check if this is a replacement booking
$isReplacement = ($booking['item_type'] === 'replacement');

// If replacement, get ALL replacement details for this delivery schedule
$replacementDetails = [];
$totalReplacementQty = 0;
if ($isReplacement) {
    $replSql = "SELECT 
        rr.*,
        oi.product_name,
        oi.variant_color,
        oi.size,
        oi.price
    FROM replacement_requests rr
    INNER JOIN order_items oi ON rr.order_item_id = oi.id
    WHERE rr.delivery_schedule_id = ?
    ORDER BY oi.product_name";
    
    $replStmt = $conn->prepare($replSql);
    $replStmt->bind_param("i", $booking['delivery_schedule_id']);
    $replStmt->execute();
    $replacementDetails = $replStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $replStmt->close();
    
    // Calculate total replacement quantity
    foreach ($replacementDetails as $repl) {
        $totalReplacementQty += $repl['replacement_quantity'];
    }
}
    $stmt->close();

    if (!$booking) {
        $_SESSION['error_message'] = "Booking not found or not assigned to you";
        header("Location: logistic-dispatcher-dashboard-page-13.php");
        exit();
    }

   // Get order items
if ($isReplacement) {
    // For replacements, get ALL items for this delivery schedule
    $itemsSql = "SELECT 
        oi.id,
        oi.product_name,
        oi.variant_color,
        oi.size,
        oi.price,
        oi.warehouse_location,
        oi.po_number,
        oi.qr_code,
        rr.id as replacement_id,
        rr.replacement_quantity as quantity,
        rr.reason as replacement_reason,
        rr.details as replacement_details,
        rr.status as replacement_status
    FROM order_items oi
    INNER JOIN replacement_requests rr ON oi.id = rr.order_item_id
    WHERE rr.delivery_schedule_id = ?
    ORDER BY oi.product_name";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $booking['delivery_schedule_id']);
} else {
        // For regular orders, show all items
        $itemsSql = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
        $itemsStmt = $conn->prepare($itemsSql);
        $itemsStmt->bind_param("i", $booking['order_id']);
    }
    $itemsStmt->execute();
    $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();

    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

        if ($_POST['action'] === 'toggle_item_loaded') {
    $item_id = intval($_POST['item_id']);
    $current_status = $_POST['current_status'];
    $is_replacement = isset($_POST['is_replacement']) ? intval($_POST['is_replacement']) : 0;
    $replacement_request_id = isset($_POST['replacement_request_id']) ? intval($_POST['replacement_request_id']) : 0;

    // If it's a replacement, ONLY update replacement_requests, NOT order_items
    if ($is_replacement && $replacement_request_id) {
        // Status flow: ready_for_pickup -> item_is_loaded -> ready_for_pickup
        if ($current_status === 'ready_for_pickup') {
            $replacement_status = 'item_is_loaded';
            $message = "Replacement item is now loaded!";
        } elseif ($current_status === 'item_is_loaded') {
            $replacement_status = 'ready_for_pickup';
            $message = "Replacement item marked as ready for pickup!";
        } else {
            // If coming from other statuses, go to ready_for_pickup first
            $replacement_status = 'ready_for_pickup';
            $message = "Replacement item is ready for pickup!";
        }

        // Update ONLY replacement_requests by its ID, NOT order_items
        $updateReplacement = $conn->prepare("UPDATE replacement_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateReplacement->bind_param("si", $replacement_status, $replacement_request_id);
        if ($updateReplacement->execute()) {
            $_SESSION['success_message'] = $message;
        }
        $updateReplacement->close();
    } else {
                // For regular items, update order_items tracking_status
                $new_status = ($current_status === 'ready_for_pickup') ? 'item_is_loaded' : 'ready_for_pickup';
                $updateItem = $conn->prepare("UPDATE order_items SET tracking_status = ? WHERE id = ?");
                $updateItem->bind_param("si", $new_status, $item_id);
                if ($updateItem->execute()) {
                    $_SESSION['success_message'] = "Item status updated!";
                }
                $updateItem->close();
            }

            header("Location: logistic-dispatcher-view-booking-page-12.php?booking_id=" . $booking_id);
            exit();
        }

        if ($_POST['action'] === 'mark_all_loaded') {
    if ($isReplacement) {
        // For replacements, update ALL replacement_requests for this delivery schedule
        $updateAll = $conn->prepare("
            UPDATE replacement_requests 
            SET status = 'item_is_loaded',
                updated_at = CURRENT_TIMESTAMP
            WHERE delivery_schedule_id = ?
        ");
        $updateAll->bind_param("i", $booking['delivery_schedule_id']);
    } else {
                // For regular orders, update all items in order_items
                $updateAll = $conn->prepare("
            UPDATE order_items 
            SET tracking_status = 'item_is_loaded' 
            WHERE order_id = ?
        ");
                $updateAll->bind_param("i", $booking['order_id']);
            }

            if ($updateAll->execute()) {
                $_SESSION['success_message'] = "All items marked as loaded!";
            }
            $updateAll->close();

            header("Location: logistic-dispatcher-view-booking-page-12.php?booking_id=" . $booking_id);
            exit();
        }

        if ($_POST['action'] === 'start_transit') {
            $conn->begin_transaction();

            try {
                // Update booking to in_transit
                $updateBooking = $conn->prepare("
            UPDATE delivery_bookings 
            SET booking_status = 'in_transit',
                actual_pickup_time = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
                $updateBooking->bind_param("i", $booking_id);
                $updateBooking->execute();
                $updateBooking->close();

                // Check if this is a replacement booking
if ($booking['item_type'] === 'replacement') {
    // Update ALL replacement requests for this delivery schedule to 'out_for_delivery'
    $updateReplacement = $conn->prepare("
        UPDATE replacement_requests 
        SET status = 'out_for_delivery',
            updated_at = CURRENT_TIMESTAMP
        WHERE delivery_schedule_id = ?
    ");
    $updateReplacement->bind_param("i", $booking['delivery_schedule_id']);
    $updateReplacement->execute();
    $updateReplacement->close();
}

                // Update order status based on booking type (skip if replacement)
                if ($booking['item_type'] !== 'replacement') {
                    $order_status = ($booking['booking_type'] === 'pickup') ? 'Out for Pickup' : 'Out for Delivery';
                    $updateOrder = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                    $updateOrder->bind_param("si", $order_status, $booking['order_id']);
                    $updateOrder->execute();
                    $updateOrder->close();
                }

                // Update delivery schedule based on booking type
                $delivery_status = ($booking['booking_type'] === 'pickup') ? 'out_for_pickup' : 'out_for_delivery';
                $updateDS = $conn->prepare("UPDATE delivery_schedules SET delivery_status = ? WHERE id = ?");
                $updateDS->bind_param("si", $delivery_status, $booking['delivery_schedule_id']);
                $updateDS->execute();
                $updateDS->close();

                $conn->commit();
                $action_text = ($booking['booking_type'] === 'pickup') ? 'Pickup' : 'Delivery';
                $_SESSION['success_message'] = "$action_text started! Status changed to In Transit.";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }

            header("Location: logistic-dispatcher-view-booking-page-12.php?booking_id=" . $booking_id);
            exit();
        }
    }

    $isInTransit = $booking['booking_status'] === 'in_transit';
    $isCompleted = in_array($booking['booking_status'], ['delivered', 'picked_up']);
    $allLoaded = $booking['loaded_items'] === $booking['total_items'] && $booking['total_items'] > 0;
    
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manage Delivery - Noble Home</title>
        <style>
            .item-card {
                transition: all 0.3s ease;
            }

            .item-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 6px 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .info-row:last-child {
                border-bottom: none;
            }

            /* Sticky sidebar with proper scrolling */
            .sidebar-sticky {
                position: sticky;
                top: 1rem;
                max-height: calc(100vh - 2rem);
                overflow-y: auto;
                overflow-x: hidden;
            }

            /* Custom scrollbar for sidebar */
            .sidebar-sticky::-webkit-scrollbar {
                width: 6px;
            }

            .sidebar-sticky::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }

            .sidebar-sticky::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 10px;
            }

            .sidebar-sticky::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        </style>
    </head>

    <body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
        <?php include '../navbar/top.php'; ?>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

            <!-- Header -->
            <div class="mb-4">
                <a href="logistic-dispatcher-dashboard-page-13.php"
                    class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-3">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to My Deliveries
                </a>

                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <?php if ($isReplacement): ?>
                        <i class="fas fa-sync-alt text-orange-600 mr-3"></i>
                        Manage Replacement <?php echo ucfirst($booking['booking_type']); ?>
                    <?php else: ?>
                        <i class="fas fa-truck-loading text-purple-600 mr-3"></i>
                        Manage <?php echo ucfirst($booking['booking_type']); ?>
                        <?php if ($hasMultipleBookings): ?>
                            <span class="ml-3 bg-blue-500 text-white text-sm px-3 py-1 rounded-full">
                                <?php echo count($allBookings); ?> Bookings
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 mt-1 text-sm flex items-center flex-wrap gap-2">
                    <span>Order #<?php echo $booking['order_id']; ?> - Booking #<?php echo $booking_id; ?></span>
                    <?php if ($isReplacement): ?>
                        <span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-xs font-bold">
                            <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Quick Stats Dashboard -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-blue-500">
                    <div class="text-xs text-gray-600 uppercase mb-1">Items Progress</div>
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo $booking['loaded_items']; ?><span class="text-lg text-gray-500">/<?php echo $booking['total_items']; ?></span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-purple-500">
                    <div class="text-xs text-gray-600 uppercase mb-1">Status</div>
                    <div class="text-base font-bold text-purple-900 capitalize">
                        <?php echo str_replace('_', ' ', $booking['booking_status']); ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-green-500">
                    <div class="text-xs text-gray-600 uppercase mb-1">Delivery Date</div>
                    <div class="text-sm font-bold text-gray-900">
                        <?php echo date('M d, Y', strtotime($booking['delivery_date'])); ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-orange-500">
                    <div class="text-xs text-gray-600 uppercase mb-1">Type</div>
                    <div class="text-base font-bold text-orange-900 capitalize">
                        <?php echo $booking['booking_type']; ?>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($hasMultipleBookings && !$isReplacement): ?>
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4 rounded-lg shadow-sm">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mt-1 mr-3 text-xl"></i>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-bold text-base">Multiple Bookings (<?php echo count($allBookings); ?> total)</h3>
                                <button onclick="toggleBookings()" id="toggleBookingsBtn"
                                    class="text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                                    <i class="fas fa-chevron-down mr-1"></i> Show Details
                                </button>
                            </div>

                            <div id="bookingsContainer" class="hidden space-y-3 mt-3">
                                <?php foreach ($allBookings as $otherBooking): ?>
                                    <div class="bg-white rounded-lg p-3 border-2 <?php echo $otherBooking['id'] == $booking_id ? 'border-blue-500 shadow-lg' : 'border-gray-300'; ?>">

                                        <!-- Header -->
                                        <div class="flex items-center justify-between mb-2 pb-2 border-b-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-base font-bold text-gray-900">
                                                    Booking #<?php echo $otherBooking['id']; ?>
                                                </span>
                                                <?php if ($otherBooking['id'] == $booking_id): ?>
                                                    <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full font-semibold">
                                                        <i class="fas fa-eye mr-1"></i>Current
                                                    </span>
                                                <?php endif; ?>
                                                <span class="capitalize px-2 py-1 rounded-full text-xs font-semibold <?php
                                                                                                                        echo $otherBooking['booking_type'] == 'delivery'
                                                                                                                            ? 'bg-purple-100 text-purple-700'
                                                                                                                            : 'bg-green-100 text-green-700';
                                                                                                                        ?>">
                                                    <i class="fas fa-<?php echo $otherBooking['booking_type'] == 'delivery' ? 'truck' : 'box'; ?> mr-1"></i>
                                                    <?php echo $otherBooking['booking_type']; ?>
                                                </span>
                                            </div>
                                            <?php if ($otherBooking['id'] != $booking_id): ?>
                                                <a href="logistic-dispatcher-view-booking-page-12.php?booking_id=<?php echo $otherBooking['id']; ?>"
                                                    class="bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600 transition-colors text-xs font-semibold">
                                                    <i class="fas fa-external-link-alt mr-1"></i>
                                                    View
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Booking Details Grid -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">

                                            <!-- Left Column -->
                                            <div class="space-y-1">
                                                <!-- Status -->
                                                <div class="info-row">
                                                    <span class="text-gray-600 font-medium text-xs">
                                                        <i class="fas fa-circle mr-1 <?php
                                                                                        echo $otherBooking['booking_status'] == 'delivered' ? 'text-green-500' : ($otherBooking['booking_status'] == 'in_transit' ? 'text-blue-500' : ($otherBooking['booking_status'] == 'cancelled' ? 'text-red-500' : ($otherBooking['booking_status'] == 'picked_up' ? 'text-purple-500' : 'text-yellow-500')));
                                                                                        ?>"></i>Status:
                                                    </span>
                                                    <span class="font-bold capitalize text-xs <?php
                                                                                                echo $otherBooking['booking_status'] == 'delivered' ? 'text-green-700' : ($otherBooking['booking_status'] == 'in_transit' ? 'text-blue-700' : ($otherBooking['booking_status'] == 'cancelled' ? 'text-red-700' : ($otherBooking['booking_status'] == 'picked_up' ? 'text-purple-700' : 'text-yellow-700')));
                                                                                                ?>">
                                                        <?php echo str_replace('_', ' ', $otherBooking['booking_status']); ?>
                                                    </span>
                                                </div>

                                                <!-- Dispatcher ID -->
                                                <?php if ($otherBooking['dispatcher_id']): ?>
                                                    <div class="info-row">
                                                        <span class="text-gray-600 font-medium text-xs">
                                                            <i class="fas fa-user-tie mr-1"></i>Dispatcher:
                                                        </span>
                                                        <span class="font-semibold text-gray-900 text-xs">
                                                            #<?php echo htmlspecialchars($otherBooking['dispatcher_id']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Handler (Pickup Person or Driver) -->
                                                <?php
                                                $handler_name = $otherBooking['booking_type'] === 'pickup'
                                                    ? $otherBooking['pickup_person_name']
                                                    : $otherBooking['driver_name'];
                                                $handler_label = $otherBooking['booking_type'] === 'pickup' ? 'Pickup Person' : 'Driver';

                                                if ($handler_name):
                                                ?>
                                                    <div class="info-row">
                                                        <span class="text-gray-600 font-medium text-xs">
                                                            <i class="fas fa-user mr-1"></i><?php echo $handler_label; ?>:
                                                        </span>
                                                        <span class="font-semibold text-gray-900 text-xs">
                                                            <?php echo htmlspecialchars($handler_name); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($otherBooking['vehicle_plate_number']): ?>
                                                    <div class="info-row">
                                                        <span class="text-gray-600 font-medium text-xs">
                                                            <i class="fas fa-car mr-1"></i>Plate:
                                                        </span>
                                                        <span class="font-semibold text-gray-900 bg-yellow-100 px-2 py-0.5 rounded text-xs">
                                                            <?php echo htmlspecialchars($otherBooking['vehicle_plate_number']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Right Column -->
                                            <div class="space-y-1">
                                                <?php if ($otherBooking['courier_name']): ?>
                                                    <div class="info-row">
                                                        <span class="text-gray-600 font-medium text-xs">
                                                            <i class="fas fa-shipping-fast mr-1"></i>Courier:
                                                        </span>
                                                        <span class="font-semibold text-gray-900 text-xs">
                                                            <?php echo htmlspecialchars($otherBooking['courier_name']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($otherBooking['tracking_number']): ?>
                                                    <div class="info-row">
                                                        <span class="text-gray-600 font-medium text-xs">
                                                            <i class="fas fa-barcode mr-1"></i>Tracking:
                                                        </span>
                                                        <span class="font-mono text-xs font-bold text-blue-600">
                                                            <?php echo htmlspecialchars($otherBooking['tracking_number']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($otherBooking['delivery_date']): ?>
                                                    <div class="info-row">
                                                        <span class="text-gray-600 font-medium text-xs">
                                                            <i class="fas fa-calendar mr-1"></i>Schedule:
                                                        </span>
                                                        <span class="font-semibold text-gray-900 text-xs">
                                                            <?php echo date('M d, Y', strtotime($otherBooking['delivery_date'])); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function toggleBookings() {
                        const container = document.getElementById('bookingsContainer');
                        const btn = document.getElementById('toggleBookingsBtn');

                        if (container.classList.contains('hidden')) {
                            container.classList.remove('hidden');
                            btn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i> Hide Details';
                        } else {
                            container.classList.add('hidden');
                            btn.innerHTML = '<i class="fas fa-chevron-down mr-1"></i> Show Details';
                        }
                    }
                </script>
            <?php endif; ?>

            <!-- Full Width Layout -->
            <div class="space-y-4">

                <!-- Tabbed Information Card - Below Quick Stats -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <!-- Tabs Header -->
                    <div class="border-b border-gray-200 overflow-x-auto">
                        <div class="flex">
                            <button onclick="switchTab('status')" id="tab-status" class="tab-button active px-4 py-3 text-sm font-semibold border-b-2 border-purple-600 text-purple-600 whitespace-nowrap">
                                <i class="fas fa-info-circle mr-1"></i> Status
                            </button>
                            <button onclick="switchTab('customer')" id="tab-customer" class="tab-button px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 whitespace-nowrap">
                                <i class="fas fa-user mr-1"></i> Customer
                            </button>
                            <button onclick="switchTab('handler')" id="tab-handler" class="tab-button px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 whitespace-nowrap">
                                <i class="fas fa-id-card mr-1"></i> <?php echo $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Driver'; ?>
                            </button>
                            <button onclick="switchTab('schedule')" id="tab-schedule" class="tab-button px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 whitespace-nowrap">
                                <i class="fas fa-calendar mr-1"></i> Schedule
                            </button>
                            <?php if ($booking['delivery_notes'] || $booking['booking_notes']): ?>
                                <button onclick="switchTab('notes')" id="tab-notes" class="tab-button px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 whitespace-nowrap">
                                    <i class="fas fa-sticky-note mr-1"></i> Notes
                                </button>
                            <?php endif; ?>
                            <?php if ($isReplacement): ?>
                                <button onclick="switchTab('replacement')" id="tab-replacement" class="tab-button px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 whitespace-nowrap">
                                    <i class="fas fa-sync-alt mr-1"></i> Replacement
                                </button>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-4">
                        <!-- Status Tab -->
                        <div id="content-status" class="tab-content break-words">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                                    <span class="text-xs text-gray-600 block mb-1">Booking Status</span>
                                    <span class="font-semibold text-sm text-purple-900 capitalize">
                                        <?php echo str_replace('_', ' ', $booking['booking_status']); ?>
                                    </span>
                                </div>

                                <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <span class="text-xs text-gray-600 block mb-1">Type</span>
                                    <span class="font-semibold text-sm text-blue-900 capitalize">
                                        <?php echo $booking['booking_type']; ?>
                                    </span>
                                </div>

                                <?php if ($booking['tracking_number']): ?>
                                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                        <span class="text-xs text-gray-600 block mb-1">Tracking Number</span>
                                        <span class="font-mono text-xs font-bold text-green-900">
                                            <?php echo htmlspecialchars($booking['tracking_number']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($booking['booking_reference']): ?>
                                    <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                        <span class="text-xs text-gray-600 block mb-1">Booking Reference</span>
                                        <span class="font-mono text-xs font-bold text-yellow-900">
                                            <?php echo htmlspecialchars($booking['booking_reference']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Customer Tab -->
                        <div id="content-customer" class="tab-content hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <span class="text-gray-600 block mb-1 font-medium text-xs">Name:</span>
                                    <span class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                                </div>
                                <?php if ($booking['mobile']): ?>
                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <span class="text-gray-600 block mb-1 font-medium text-xs">Mobile:</span>
                                        <a href="tel:<?php echo htmlspecialchars($booking['mobile']); ?>"
                                            class="font-semibold text-blue-600 hover:text-blue-800 text-sm flex items-center">
                                            <i class="fas fa-phone mr-2"></i>
                                            <?php echo htmlspecialchars($booking['mobile']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($booking['email']): ?>
                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <span class="text-gray-600 block mb-1 font-medium text-xs">Email:</span>
                                        <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>"
                                            class="font-semibold text-blue-600 hover:text-blue-800 text-sm break-all">
                                            <?php echo htmlspecialchars($booking['email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="p-3 bg-gray-50 rounded-lg md:col-span-2 lg:col-span-3">
                                    <span class="text-gray-600 block mb-1 font-medium text-xs">Delivery Address:</span>
                                    <span class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($booking['address']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Handler Tab -->
                        <div id="content-handler" class="tab-content hidden">
                            <?php
                            $handler_name = $booking['booking_type'] === 'pickup'
                                ? $booking['pickup_person_name']
                                : $booking['driver_name'];
                            $handler_contact = $booking['booking_type'] === 'pickup'
                                ? $booking['pickup_person_contact']
                                : null;
                            $handler_label = $booking['booking_type'] === 'pickup' ? 'Pickup Person' : 'Driver';

                            if ($handler_name || $booking['vehicle_plate_number']):
                            ?>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <?php if ($handler_name): ?>
                                        <div class="p-3 bg-gray-50 rounded-lg">
                                            <span class="text-gray-600 block mb-1 font-medium text-xs"><?php echo $handler_label; ?> Name:</span>
                                            <span class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($handler_name); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($handler_contact): ?>
                                        <div class="p-3 bg-gray-50 rounded-lg">
                                            <span class="text-gray-600 block mb-1 font-medium text-xs">Contact:</span>
                                            <a href="tel:<?php echo htmlspecialchars($handler_contact); ?>"
                                                class="font-semibold text-blue-600 hover:text-blue-800 text-sm flex items-center">
                                                <i class="fas fa-phone mr-2"></i>
                                                <?php echo htmlspecialchars($handler_contact); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($booking['vehicle_plate_number']): ?>
                                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                            <span class="text-gray-600 block mb-1 font-medium text-xs">Vehicle Plate Number:</span>
                                            <span class="font-bold text-gray-900 text-lg">
                                                <?php echo htmlspecialchars($booking['vehicle_plate_number']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-info-circle text-3xl mb-2"></i>
                                    <p class="text-sm">No handler information available yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Schedule Tab -->
                        <div id="content-schedule" class="tab-content hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div class="p-3 bg-blue-50 rounded-lg border border-blue-200 md:col-span-2">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <span class="text-xs text-gray-600 font-medium block mb-1">Scheduled Date:</span>
                                            <span class="font-bold text-blue-900"><?php echo date('M d, Y', strtotime($booking['delivery_date'])); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-600 font-medium block mb-1">Scheduled Time:</span>
                                            <span class="font-bold text-blue-900"><?php echo date('g:i A', strtotime($booking['delivery_time'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($booking['courier_name']): ?>
                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <span class="text-xs text-gray-600 block mb-1">Courier:</span>
                                        <span class="font-semibold text-sm"><?php echo htmlspecialchars($booking['courier_name']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($booking['vehicle_type']): ?>
                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <span class="text-xs text-gray-600 block mb-1">Vehicle Type:</span>
                                        <span class="font-semibold text-sm"><?php echo htmlspecialchars($booking['vehicle_type']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($booking['estimated_pickup_time'] || $booking['estimated_delivery_time']): ?>
                                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                        <h4 class="font-semibold text-gray-700 mb-2 text-xs uppercase">Estimated Times</h4>
                                        <?php if ($booking['estimated_pickup_time']): ?>
                                            <div class="flex justify-between text-xs mb-1">
                                                <span class="text-gray-600">Pickup:</span>
                                                <span class="font-semibold text-gray-900">
                                                    <?php echo date('M d, g:i A', strtotime($booking['estimated_pickup_time'])); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($booking['estimated_delivery_time']): ?>
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-600">Delivery:</span>
                                                <span class="font-semibold text-gray-900">
                                                    <?php echo date('M d, g:i A', strtotime($booking['estimated_delivery_time'])); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($booking['actual_pickup_time'] || $booking['actual_delivery_time']): ?>
                                    <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                                        <h4 class="font-semibold text-gray-700 mb-2 text-xs uppercase">Actual Times</h4>
                                        <?php if ($booking['actual_pickup_time']): ?>
                                            <div class="flex justify-between text-xs mb-1">
                                                <span class="text-gray-600">Picked up:</span>
                                                <span class="font-bold text-green-700">
                                                    <?php echo date('M d, g:i A', strtotime($booking['actual_pickup_time'])); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($booking['actual_delivery_time']): ?>
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-600">Delivered:</span>
                                                <span class="font-bold text-blue-700">
                                                    <?php echo date('M d, g:i A', strtotime($booking['actual_delivery_time'])); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Notes Tab -->
                        <?php if ($booking['delivery_notes'] || $booking['booking_notes']): ?>
                            <div id="content-notes" class="tab-content hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <?php if ($booking['delivery_notes']): ?>
                                        <div class="p-3 bg-orange-50 rounded-lg border border-orange-200">
                                            <h4 class="font-bold text-orange-900 mb-2 text-sm flex items-center">
                                                <i class="fas fa-clipboard text-orange-600 mr-2"></i>
                                                Delivery Notes
                                            </h4>
                                            <p class="text-xs text-orange-800 whitespace-pre-wrap"><?php echo htmlspecialchars($booking['delivery_notes']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($booking['booking_notes']): ?>
                                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                            <h4 class="font-bold text-blue-900 mb-2 text-sm flex items-center">
                                                <i class="fas fa-sticky-note text-blue-600 mr-2"></i>
                                                Booking Notes
                                            </h4>
                                            <p class="text-xs text-blue-800 whitespace-pre-wrap"><?php echo htmlspecialchars($booking['booking_notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <!-- Replacement Tab -->
<?php if ($isReplacement && !empty($replacementDetails)): ?>
    <div id="content-replacement" class="tab-content hidden">
        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <div class="p-3 bg-orange-50 rounded-lg border border-orange-200 text-center">
                <span class="text-xs text-gray-600 block mb-1">Total Items</span>
                <span class="font-bold text-2xl text-orange-900"><?php echo count($replacementDetails); ?></span>
            </div>
            
            <div class="p-3 bg-orange-50 rounded-lg border border-orange-200 text-center">
                <span class="text-xs text-gray-600 block mb-1">Total Quantity</span>
                <span class="font-bold text-2xl text-orange-900"><?php echo $totalReplacementQty; ?> pcs</span>
            </div>
            
            <div class="p-3 bg-orange-50 rounded-lg border border-orange-200 text-center">
                <span class="text-xs text-gray-600 block mb-1">Total Value</span>
                <span class="font-bold text-2xl text-orange-900">
                    ₱<?php 
                    $totalValue = 0;
                    foreach ($replacementDetails as $repl) {
                        $totalValue += $repl['price'] * $repl['replacement_quantity'];
                    }
                    echo number_format($totalValue, 2); 
                    ?>
                </span>
            </div>
        </div>
        
        <!-- Replacement Items List -->
        <div class="space-y-3 max-h-96 overflow-y-auto">
            <?php foreach ($replacementDetails as $index => $repl): ?>
            <div class="bg-orange-50 border-l-4 border-orange-500 rounded-lg p-3">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="bg-orange-200 text-orange-900 px-2 py-1 rounded text-xs font-bold">
                                #<?php echo $index + 1; ?>
                            </span>
                            <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-semibold">
                                Request #<?php echo $repl['id']; ?>
                            </span>
                        </div>
                        <p class="font-bold text-orange-900"><?php echo htmlspecialchars($repl['product_name']); ?></p>
                        <?php if ($repl['variant_color'] || $repl['size']): ?>
                        <p class="text-xs text-orange-700 mt-1">
                            <?php if ($repl['variant_color']): ?>
                                <span class="mr-2">Color: <?php echo htmlspecialchars($repl['variant_color']); ?></span>
                            <?php endif; ?>
                            <?php if ($repl['size']): ?>
                                <span>Size: <?php echo htmlspecialchars($repl['size']); ?></span>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-orange-900"><?php echo $repl['replacement_quantity']; ?> pcs</p>
                        <p class="text-xs text-orange-700">₱<?php echo number_format($repl['price'] * $repl['replacement_quantity'], 2); ?></p>
                    </div>
                </div>
                
                <div class="mt-2 pt-2 border-t border-orange-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-orange-700 font-semibold">Reason:</span>
                            <span class="text-orange-900 ml-1 capitalize"><?php echo str_replace('_', ' ', $repl['reason']); ?></span>
                        </div>
                        <div>
                            <span class="text-orange-700 font-semibold">Status:</span>
                            <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded ml-1 font-semibold capitalize">
                                <?php echo str_replace('_', ' ', $repl['status']); ?>
                            </span>
                        </div>
                        <?php if ($repl['details']): ?>
                        <div class="md:col-span-2">
                            <span class="text-orange-700 font-semibold">Details:</span>
                            <p class="text-orange-900 mt-1"><?php echo nl2br(htmlspecialchars($repl['details'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
                    </div>
                </div>

                <script>
                    function switchTab(tabName) {
                        // Hide all tab contents
                        document.querySelectorAll('.tab-content').forEach(content => {
                            content.classList.add('hidden');
                        });

                        // Remove active class from all tabs
                        document.querySelectorAll('.tab-button').forEach(button => {
                            button.classList.remove('active', 'border-purple-600', 'text-purple-600');
                            button.classList.add('border-transparent', 'text-gray-600');
                        });

                        // Show selected tab content
                        document.getElementById('content-' + tabName).classList.remove('hidden');

                        // Add active class to selected tab
                        const activeTab = document.getElementById('tab-' + tabName);
                        activeTab.classList.add('active', 'border-purple-600', 'text-purple-600');
                        activeTab.classList.remove('border-transparent', 'text-gray-600');
                    }
                </script>

<!-- Items Loading Header -->
<div class="flex items-center justify-between mb-3 flex-wrap gap-2">
    <h3 class="text-lg font-bold text-gray-900 flex items-center">
        <i class="fas fa-boxes text-orange-600 mr-2"></i>
        <?php echo $isReplacement ? 'Replacement Items' : 'Items to Load'; ?>
    </h3>

    <div class="flex items-center gap-2 flex-wrap">

        <!-- ✅ PRINT STICKER BUTTON + DOWNLOADED INDICATOR -->
        <div class="flex items-center gap-2">

            <!-- Downloaded Indicator (hidden by default, shown after print) -->
            <div id="stickerPrintedBadge" class="hidden items-center gap-1.5 bg-green-100 border border-green-300 text-green-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                <i class="fas fa-check-circle text-green-500"></i>
                <span id="stickerPrintedText">Sticker Printed</span>
            </div>

            <!-- Not Yet Printed Indicator (shown by default) -->
            <div id="stickerNotPrintedBadge" class="flex items-center gap-1.5 bg-yellow-100 border border-yellow-300 text-yellow-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                <i class="fas fa-exclamation-circle text-yellow-500"></i>
                <span>Sticker Not Yet Printed</span>
            </div>

            <!-- Print Sticker Button -->
            <a href="generate_shipping_sticker.php?booking_id=<?php echo $booking_id; ?>"
               target="_blank"
               id="printStickerBtn"
               onclick="markStickerOpened()"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors font-semibold text-sm shadow-sm">
                <i class="fas fa-tag"></i>
                Print Sticker
            </a>
        </div>

        <!-- Mark All Loaded Button (existing) -->
        <?php if (!$allLoaded && !$isCompleted && !$isInTransit): ?>
            <form method="POST" class="inline" onsubmit="return confirm('Mark all items as loaded?');">
                <input type="hidden" name="action" value="mark_all_loaded">
                <button type="submit"
                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors font-semibold text-sm">
                    <i class="fas fa-check-double mr-1"></i>
                    Mark All Loaded
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const printedBadge    = document.getElementById('stickerPrintedBadge');
    const notPrintedBadge = document.getElementById('stickerNotPrintedBadge');
    const printedText     = document.getElementById('stickerPrintedText');

    function showPrinted(timestamp) {
        printedBadge.classList.remove('hidden');
        printedBadge.style.display = 'flex';
        notPrintedBadge.classList.add('hidden');
        if (timestamp) {
            printedText.textContent = 'Printed: ' + timestamp;
        }
    }

    // Fetch print status from DB on page load
    fetch('get_sticker_print_status.php?booking_id=<?php echo $booking_id; ?>')
        .then(res => res.json())
        .then(data => {
            if (data.printed_at) {
                showPrinted(data.printed_at_formatted);
            }
        })
        .catch(err => console.error('Failed to fetch sticker status:', err));

    // Poll every 3s while page is open (in case user prints in new tab)
    const pollInterval = setInterval(function() {
        fetch('get_sticker_print_status.php?booking_id=<?php echo $booking_id; ?>')
            .then(res => res.json())
            .then(data => {
                if (data.printed_at) {
                    showPrinted(data.printed_at_formatted);
                    clearInterval(pollInterval);
                }
            });
    }, 3000);
})();

function markStickerOpened() {
    const notPrintedBadge = document.getElementById('stickerNotPrintedBadge');
    notPrintedBadge.innerHTML = '<i class="fas fa-external-link-alt text-blue-500 mr-1"></i><span class="text-blue-700">Sticker Page Opened</span>';
    notPrintedBadge.className = 'flex items-center gap-1.5 bg-blue-100 border border-blue-300 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-full';
}
</script>
                    <!-- Progress Bar -->
                    <div class="mb-3">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Loading Progress</span>
                            <span class="font-semibold"><?php echo $booking['loaded_items']; ?> / <?php echo $booking['total_items']; ?> items loaded</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="bg-gradient-to-r from-blue-500 to-green-500 h-3 rounded-full transition-all"
                                style="width: <?php echo $booking['total_items'] > 0 ? ($booking['loaded_items'] / $booking['total_items']) * 100 : 0; ?>%">
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-2">
                        <?php if ($isReplacement && !empty($items)): ?>
    <!-- Replacement Items Display -->
    <?php foreach ($items as $item): ?>
        <?php
        $isLoaded = $item['replacement_status'] === 'item_is_loaded';
        ?>
        <div class="item-card flex items-center justify-between p-3 rounded-lg border-2 <?php
            echo $isLoaded ? 'bg-orange-50 border-orange-300' : 'bg-yellow-50 border-yellow-300';
        ?>">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-bold">
                        <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT
                    </span>
                    <span class="bg-orange-200 text-orange-900 px-2 py-1 rounded text-xs font-bold">
                        Req #<?php echo $item['replacement_id']; ?>
                    </span>
                </div>
                <div class="flex items-center flex-wrap gap-x-4 gap-y-1">
                    <h4 class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                    <div class="text-xs text-gray-600 flex items-center gap-3">
                        <?php if ($item['variant_color']): ?>
                            <span>Color: <span class="font-medium"><?php echo htmlspecialchars($item['variant_color']); ?></span></span>
                        <?php endif; ?>
                        <?php if ($item['size']): ?>
                            <span>Size: <span class="font-medium"><?php echo htmlspecialchars($item['size']); ?></span></span>
                        <?php endif; ?>
                        <span>Qty: <span class="font-bold"><?php echo $item['quantity']; ?></span></span>
                    </div>
                </div>
                <div class="text-xs mt-1">
                    <span class="text-orange-700">Reason: </span>
                    <span class="font-semibold capitalize"><?php echo str_replace('_', ' ', $item['replacement_reason']); ?></span>
                </div>
                <?php if (!empty($item['warehouse_location'])): ?>
                    <div class="text-xs mt-1 flex items-center">
                        <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>
                        <span class="font-semibold text-gray-700">Location: </span>
                        <span class="ml-1 text-gray-900 bg-yellow-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($item['warehouse_location']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3">
                <?php if (!$isCompleted && !$isInTransit): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle_item_loaded">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="current_status" value="<?php echo $item['replacement_status']; ?>">
                        <input type="hidden" name="is_replacement" value="1">
                        <input type="hidden" name="replacement_request_id" value="<?php echo $item['replacement_id']; ?>">
                        <button type="submit"
                            class="<?php echo $item['replacement_status'] === 'item_is_loaded' ? 'bg-orange-500 hover:bg-orange-600' : 'bg-yellow-500 hover:bg-yellow-600'; ?> text-white px-4 py-2 rounded-lg transition-colors font-semibold text-sm">
                            <i class="fas <?php echo $item['replacement_status'] === 'item_is_loaded' ? 'fa-check' : 'fa-box'; ?> mr-1"></i>
                            <?php echo $item['replacement_status'] === 'item_is_loaded' ? 'Loaded' : 'Load'; ?>
                        </button>
                    </form>
                <?php else: ?>
                    <span class="<?php echo $item['replacement_status'] === 'item_is_loaded' ? 'bg-orange-500' : 'bg-yellow-500'; ?> text-white px-4 py-2 rounded-lg font-semibold text-sm">
                        <i class="fas <?php echo $item['replacement_status'] === 'item_is_loaded' ? 'fa-check' : 'fa-clock'; ?> mr-1"></i>
                        <?php echo $item['replacement_status'] === 'item_is_loaded' ? 'Loaded' : 'Pending'; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
                <!-- Regular Order Items Display -->
                <?php foreach ($items as $item): ?>
                    <?php
                                $isLoaded = $item['tracking_status'] === 'item_is_loaded';
                    ?>
                    <div class="item-card flex items-center justify-between p-3 rounded-lg border-2 <?php
                                                                                                    echo $isLoaded ? 'bg-blue-50 border-blue-300' : 'bg-yellow-50 border-yellow-300';
                                                                                                    ?>">
                        <div class="flex-1">
                            <div class="flex items-center flex-wrap gap-x-4 gap-y-1">
                                <h4 class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                <div class="text-xs text-gray-600 flex items-center gap-3">
                                    <?php if ($item['variant_color']): ?>
                                        <span>Color: <span class="font-medium"><?php echo htmlspecialchars($item['variant_color']); ?></span></span>
                                    <?php endif; ?>
                                    <?php if ($item['size']): ?>
                                        <span>Size: <span class="font-medium"><?php echo htmlspecialchars($item['size']); ?></span></span>
                                    <?php endif; ?>
                                    <span>Qty: <span class="font-bold"><?php echo $item['quantity']; ?></span></span>
                                </div>
                            </div>
                            <?php if (!empty($item['warehouse_location'])): ?>
                                <div class="text-xs mt-1 flex items-center">
                                    <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>
                                    <span class="font-semibold text-gray-700">Location: </span>
                                    <span class="ml-1 text-gray-900 bg-yellow-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($item['warehouse_location']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-3">
                            <?php if (!$isCompleted && !$isInTransit): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_item_loaded">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $item['tracking_status']; ?>">
                                    <button type="submit"
                                        class="<?php echo $isLoaded ? 'bg-blue-500 hover:bg-blue-600' : 'bg-yellow-500 hover:bg-yellow-600'; ?> text-white px-4 py-2 rounded-lg transition-colors font-semibold text-sm">
                                        <i class="fas <?php echo $isLoaded ? 'fa-check' : 'fa-box'; ?> mr-1"></i>
                                        <?php echo $isLoaded ? 'Loaded' : 'Load'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="<?php echo $isLoaded ? 'bg-blue-500' : 'bg-yellow-500'; ?> text-white px-4 py-2 rounded-lg font-semibold text-sm">
                                    <i class="fas <?php echo $isLoaded ? 'fa-check' : 'fa-clock'; ?> mr-1"></i>
                                    <?php echo $isLoaded ? 'Loaded' : 'Pending'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
                </div>
            </div>

            <!-- Start Transit Button -->
            <?php if ($allLoaded && !$isInTransit && !$isCompleted): ?>
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white mt-2">
                    <h3 class="text-lg font-bold mb-2 flex items-center">
                        <i class="fas fa-<?php echo $booking['booking_type'] === 'pickup' ? 'box' : 'truck-moving'; ?> mr-2"></i>
                        Ready to Start <?php echo $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery'; ?>
                    </h3>
                    <p class="mb-3 text-green-100 text-sm">
                        All items are loaded. Click below to mark as "In Transit"
                    </p>

                    <form method="POST" onsubmit="return confirm('Are you sure all items are loaded and ready to <?php echo $booking['booking_type'] === 'pickup' ? 'pick up' : 'deliver'; ?>?');">
                        <input type="hidden" name="action" value="start_transit">
                        <button type="submit"
                            class="bg-white text-green-600 px-6 py-2 rounded-lg hover:bg-green-50 transition-all shadow-md hover:shadow-lg font-bold">
                            <i class="fas fa-play-circle mr-2"></i>
                            Start <?php echo $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery'; ?> (In Transit)
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($isInTransit): ?>
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white mt-2">
                    <h3 class="text-lg font-bold mb-2 flex items-center">
                        <i class="fas fa-shipping-fast mr-2"></i>
                        <?php echo $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery'; ?> In Progress
                    </h3>
                    <p class="text-blue-100 text-sm">
                        This <?php echo $booking['booking_type']; ?> is currently in transit. Drive safely!
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </body>

    </html>