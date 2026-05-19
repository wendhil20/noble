<?php
// delivery_booking.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);
require_once ROOT_PATH . "/admin/ui-warehouse/audit_trail_helper.php";

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

// ─── URL Parameters ───────────────────────────────────────────────────────────
$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
$order_id    = isset($_GET['order_id'])    ? intval($_GET['order_id'])    : 0;

if (!$schedule_id || !$order_id) {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

// ─── Fetch Schedule + Order ───────────────────────────────────────────────────
$sql = "SELECT 
    ds.*,
    o.customer_name, o.email, o.mobile, o.address,
    o.final_total, o.delivery_fee, o.delivery_type,
    o.total_cubic_meters, o.total_weight_kg,
    o.assigned_vehicle_id,
    tv.vehicle_type, tv.courier_name,
    tv.max_weight_capacity, tv.max_cubic_meter,
    (SELECT COUNT(*) FROM order_items WHERE order_id = ds.order_id) AS total_items,
    (SELECT SUM(quantity) FROM order_items WHERE order_id = ds.order_id) AS total_quantity
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
LEFT JOIN transportify_vehicle_list tv ON o.assigned_vehicle_id = tv.id
WHERE ds.id = ? AND ds.order_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $schedule_id, $order_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$schedule) {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

// ─── Already Booked? ──────────────────────────────────────────────────────────
$checkBooking = $conn->prepare("SELECT id FROM delivery_bookings WHERE delivery_schedule_id = ?");
$checkBooking->bind_param("i", $schedule_id);
$checkBooking->execute();
$existingBooking = $checkBooking->get_result()->fetch_assoc();
$checkBooking->close();

if ($existingBooking) {
    header("Location: " . BASE_URL . "/logisticdeliverytrack?booking_id=" . $existingBooking['id']);
    exit();
}

// ─── Order Items ──────────────────────────────────────────────────────────────
$itemsStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
$itemsStmt->bind_param("i", $order_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

$isPickup = strtolower($schedule['delivery_type']) === 'pickup';

// ─── Capacity Check ───────────────────────────────────────────────────────────
$weightOk = !$schedule['max_weight_capacity'] || ($schedule['total_weight_kg'] <= $schedule['max_weight_capacity']);
$volumeOk = !$schedule['max_cubic_meter']     || ($schedule['total_cubic_meters'] <= $schedule['max_cubic_meter']);

// ─── Handle Form Submission ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_booking') {
    $tracking_number       = trim($_POST['tracking_number']);
    $courier_name          = $schedule['courier_name'] ?? trim($_POST['courier_name']);
    $booking_reference     = trim($_POST['booking_reference']);
    $estimated_pickup      = $_POST['estimated_pickup_time'];
    $booking_notes         = trim($_POST['booking_notes']);
    $user_id               = $_SESSION['noble_user'];
    $pickup_person_name    = trim($_POST['pickup_person_name']    ?? '');
    $pickup_person_contact = trim($_POST['pickup_person_contact'] ?? '');
    $driver_name           = trim($_POST['driver_name']           ?? '');
    $vehicle_plate_number  = strtoupper(trim($_POST['vehicle_plate_number'] ?? ''));

    $conn->begin_transaction();

    try {
        // Insert booking
        $insertBooking = $conn->prepare("
            INSERT INTO delivery_bookings
            (order_id, delivery_schedule_id, booking_type, tracking_number, courier_name,
             vehicle_id, booking_reference, estimated_pickup_time, booking_notes,
             booking_status, created_by, pickup_person_name, pickup_person_contact,
             driver_name, vehicle_plate_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, ?, ?)
        ");
        $insertBooking->bind_param(
            "iisssisssissss",
            $order_id, $schedule_id, $schedule['delivery_type'],
            $tracking_number, $courier_name, $schedule['assigned_vehicle_id'],
            $booking_reference, $estimated_pickup, $booking_notes, $user_id,
            $pickup_person_name, $pickup_person_contact, $driver_name, $vehicle_plate_number
        );

        if (!$insertBooking->execute()) throw new Exception("Failed to create booking");
        $booking_id = $conn->insert_id;
        $insertBooking->close();

        // Audit trail
        logAuditTrail(
            $conn, 'CREATE_DELIVERY_BOOKING', 'delivery_bookings', $booking_id, $order_id, null, null,
            json_encode([
                'booking_type'    => $schedule['delivery_type'],
                'tracking_number' => $tracking_number,
                'courier_name'    => $courier_name,
                'pickup_person'   => $pickup_person_name,
                'pickup_contact'  => $pickup_person_contact,
                'driver_name'     => $driver_name,
                'vehicle_plate'   => $vehicle_plate_number,
            ]),
            "Created " . ucfirst($schedule['delivery_type']) . " booking with tracking #$tracking_number"
        );

        // Update items
        $upd = $conn->prepare("UPDATE order_items SET tracking_status = 'ready_for_pickup' WHERE order_id = ?");
        $upd->bind_param("i", $order_id);
        if (!$upd->execute()) throw new Exception("Failed to update items");
        $upd->close();

        // Update schedule
        $upd2 = $conn->prepare("UPDATE delivery_schedules SET delivery_status = 'booked' WHERE id = ?");
        $upd2->bind_param("i", $schedule_id);
        if (!$upd2->execute()) throw new Exception("Failed to update schedule");
        $upd2->close();

        // Update order
        $upd3 = $conn->prepare("UPDATE orders SET status = 'Ready for Pickup' WHERE id = ?");
        $upd3->bind_param("i", $order_id);
        if (!$upd3->execute()) throw new Exception("Failed to update order");
        $upd3->close();

        $conn->commit();

        $_SESSION['success_message'] = "Booking created successfully!";
        header("Location: " . BASE_URL . "/logisticdeliverytrack?booking_id=" . $booking_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error creating booking: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isPickup ? 'Schedule Pickup' : 'Book Delivery' ?> – Order #<?= $order_id ?></title>
</head>

<body class="bg-gray-100 min-h-screen">

<?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6">

    <!-- ═══ PAGE HEADER ══════════════════════════════════════════════════════ -->
    <div class="mb-5">
        <a href="<?= BASE_URL ?>/logisticdeliverydateorders?date=<?= $schedule['delivery_date'] ?>"
           class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-800 mb-3 transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Orders
        </a>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-5">
            <div class="flex flex-wrap items-center justify-between gap-4">

                <!-- Title -->
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center
                                <?= $isPickup ? 'bg-indigo-100' : 'bg-blue-100' ?>">
                        <i class="fas <?= $isPickup ? 'fa-hand-holding text-indigo-600' : 'fa-truck text-blue-600' ?> text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">
                            <?= $isPickup ? 'Schedule Pickup' : 'Book Delivery' ?>
                        </h1>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Order #<?= $order_id ?> &nbsp;·&nbsp;
                            <?= date('D, M d, Y', strtotime($schedule['delivery_date'])) ?>
                            &nbsp;·&nbsp;
                            <?= date('g:i A', strtotime($schedule['delivery_time'])) ?>
                        </p>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="flex gap-3 text-sm">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 text-center">
                        <div class="font-bold text-blue-800 text-lg"><?= $schedule['total_items'] ?></div>
                        <div class="text-blue-600 text-xs">Items</div>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-2 text-center">
                        <div class="font-bold text-green-800 text-lg">₱<?= number_format($schedule['final_total'], 2) ?></div>
                        <div class="text-green-600 text-xs">Total</div>
                    </div>
                    <?php if (!$weightOk || !$volumeOk): ?>
                        <div class="bg-red-50 border border-red-300 rounded-lg px-4 py-2 text-center flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                            <span class="text-red-700 text-xs font-semibold">Capacity Exceeded</span>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══ ERROR ALERT ═══════════════════════════════════════════════════════ -->
    <?php if (!empty($error_message)): ?>
        <div class="mb-5 flex items-start gap-3 bg-red-50 border border-red-300 text-red-800 rounded-lg px-4 py-3">
            <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
            <div>
                <div class="font-semibold text-sm">Error</div>
                <div class="text-sm"><?= htmlspecialchars($error_message) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══ MAIN LAYOUT: FORM (left) + ORDER SUMMARY (right) ════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- ── BOOKING FORM (3 cols) ────────────────────────────────────────── -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

                <!-- Form Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-file-alt text-blue-600"></i>
                    <h2 class="font-semibold text-gray-800">
                        <?= $isPickup ? 'Pickup Details' : 'Booking Details' ?>
                    </h2>
                </div>

                <form method="POST" class="px-6 py-5 space-y-5">
                    <input type="hidden" name="action" value="create_booking">

                    <?php if ($isPickup): ?>
                    <!-- ── PICKUP FIELDS ─────────────────────────────────── -->

                        <!-- Customer (read-only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-user mr-1 text-gray-400"></i> Customer
                            </label>
                            <div class="px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-semibold text-gray-800">
                                <?= htmlspecialchars($schedule['customer_name']) ?>
                            </div>
                        </div>

                        <!-- Reference Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-hashtag mr-1 text-gray-400"></i>
                                Reference Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="tracking_number" required
                                   placeholder="Internal pickup reference number"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Pickup Method (read-only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-hand-holding mr-1 text-gray-400"></i> Pickup Method
                            </label>
                            <input type="text" name="courier_name" value="Customer Pickup" readonly
                                   class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-semibold text-gray-700">
                        </div>

                        <!-- Pickup Person Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-user mr-1 text-gray-400"></i>
                                Pickup Person Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="pickup_person_name" required
                                   placeholder="Full name of the person picking up"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Pickup Person Contact -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-phone mr-1 text-gray-400"></i>
                                Contact Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="pickup_person_contact" required
                                   placeholder="e.g., 09171234567"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Vehicle Plate (optional) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-car mr-1 text-gray-400"></i>
                                Vehicle Plate <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <input type="text" name="vehicle_plate_number"
                                   placeholder="e.g., ABC 1234" style="text-transform:uppercase"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm uppercase
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                    <?php else: ?>
                    <!-- ── DELIVERY FIELDS ───────────────────────────────── -->

                        <!-- Tracking Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-barcode mr-1 text-gray-400"></i>
                                Tracking Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="tracking_number" required
                                   placeholder="Tracking number from courier"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-400 mt-1">Provided by the courier service</p>
                        </div>

                        <!-- Courier Name -->
                        <?php if (!$schedule['courier_name']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-truck mr-1 text-gray-400"></i>
                                    Courier Service <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="courier_name" required
                                       placeholder="e.g., LBC, J&T, Lalamove, Grab"
                                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        <?php else: ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-truck mr-1 text-gray-400"></i> Courier Service
                                </label>
                                <div class="flex items-center gap-3 bg-purple-50 border border-purple-200 rounded-lg px-3 py-2.5">
                                    <i class="fas fa-truck text-purple-500"></i>
                                    <span class="font-semibold text-purple-900 text-sm flex-1">
                                        <?= htmlspecialchars($schedule['courier_name']) ?>
                                    </span>
                                    <span class="text-xs bg-purple-200 text-purple-800 px-2 py-0.5 rounded-full font-medium">Pre-assigned</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Booking Reference -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-hashtag mr-1 text-gray-400"></i>
                                Booking Reference <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <input type="text" name="booking_reference"
                                   placeholder="Courier waybill or booking number"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Driver Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-id-card mr-1 text-gray-400"></i>
                                Driver Name <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <input type="text" name="driver_name"
                                   placeholder="Driver's full name"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Vehicle Plate -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-truck mr-1 text-gray-400"></i>
                                Vehicle Plate <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <input type="text" name="vehicle_plate_number"
                                   placeholder="e.g., ABC 1234" style="text-transform:uppercase"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm uppercase
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                    <?php endif; ?>

                    <!-- ── SHARED FIELDS ──────────────────────────────────── -->

                    <!-- Estimated Time -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-clock mr-1 text-gray-400"></i>
                            Estimated <?= $isPickup ? 'Pickup' : 'Delivery' ?> Time
                            <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <input type="datetime-local" name="estimated_pickup_time"
                               value="<?= date('Y-m-d\TH:i', strtotime($schedule['delivery_date'] . ' ' . $schedule['delivery_time'])) ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-sticky-note mr-1 text-gray-400"></i>
                            Notes <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <textarea name="booking_notes" rows="3" 
                                  placeholder="<?= $isPickup ? 'Special pickup instructions, parking info, etc.' : 'Special handling or delivery instructions...' ?>"
                                  class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm resize-none
                                         focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-2 border-t border-gray-100">
                        <a href="<?= BASE_URL ?>/logisticdeliverydateorders?date=<?= $schedule['delivery_date'] ?>"
                           class="flex-1 flex items-center justify-center gap-2
                                  bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-sm
                                  py-3 rounded-lg transition-colors">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit"
                                class="flex-1 flex items-center justify-center gap-2
                                       bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                                       text-white font-semibold text-sm py-3 rounded-lg
                                       transition-colors shadow-sm">
                            <i class="fas fa-check-circle"></i>
                            Confirm <?= $isPickup ? 'Pickup' : 'Booking' ?>
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- ── ORDER SUMMARY SIDEBAR (2 cols) ──────────────────────────────── -->
        <div class="lg:col-span-2 flex flex-col gap-5">

            <!-- Customer Info -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-user text-blue-500 text-sm"></i>
                    <h3 class="font-semibold text-gray-800 text-sm">Customer</h3>
                </div>
                <div class="px-5 py-4 space-y-2 text-sm">
                    <div class="font-semibold text-gray-900">
                        <?= htmlspecialchars($schedule['customer_name']) ?>
                    </div>
                    <?php if ($schedule['mobile']): ?>
                        <div class="text-gray-500 flex items-center gap-2">
                            <i class="fas fa-phone text-gray-400 w-4 text-center"></i>
                            <?= htmlspecialchars($schedule['mobile']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($schedule['email']): ?>
                        <div class="text-gray-500 flex items-center gap-2">
                            <i class="fas fa-envelope text-gray-400 w-4 text-center"></i>
                            <?= htmlspecialchars($schedule['email']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="text-gray-500 flex items-start gap-2">
                        <i class="fas fa-map-marker-alt text-gray-400 w-4 text-center mt-0.5"></i>
                        <span class="leading-snug"><?= htmlspecialchars($schedule['address']) ?></span>
                    </div>
                    <div class="pt-2 border-t border-gray-100">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full
                                     <?= $isPickup ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>">
                            <i class="fas <?= $isPickup ? 'fa-hand-holding' : 'fa-truck' ?>"></i>
                            <?= ucfirst($schedule['delivery_type']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-receipt text-green-500 text-sm"></i>
                    <h3 class="font-semibold text-gray-800 text-sm">Payment</h3>
                </div>
                <div class="px-5 py-4 space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span>₱<?= number_format($schedule['final_total'] - ($schedule['delivery_fee'] ?? 0), 2) ?></span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Delivery Fee</span>
                        <span class="text-blue-600">₱<?= number_format($schedule['delivery_fee'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-900 pt-2 border-t border-gray-100 text-base">
                        <span>Total</span>
                        <span class="text-green-600">₱<?= number_format($schedule['final_total'], 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Shipment Specs -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-box text-orange-500 text-sm"></i>
                    <h3 class="font-semibold text-gray-800 text-sm">Shipment</h3>
                </div>
                <div class="px-5 py-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-center">
                        <div class="font-bold text-orange-800 text-base">
                            <?= number_format($schedule['total_weight_kg'] ?? 0, 2) ?> kg
                        </div>
                        <div class="text-orange-600 text-xs mt-0.5">Weight</div>
                    </div>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-center">
                        <div class="font-bold text-orange-800 text-base">
                            <?= number_format($schedule['total_cubic_meters'] ?? 0, 3) ?> m³
                        </div>
                        <div class="text-orange-600 text-xs mt-0.5">Volume</div>
                    </div>
                </div>
            </div>

            <!-- Vehicle / Capacity -->
            <?php if ($schedule['assigned_vehicle_id']): ?>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-truck text-purple-500 text-sm"></i>
                        <h3 class="font-semibold text-gray-800 text-sm">Assigned Vehicle</h3>
                    </div>
                    <div class="px-5 py-4 text-sm space-y-2">
                        <div class="font-semibold text-gray-900"><?= htmlspecialchars($schedule['vehicle_type']) ?></div>
                        <div class="text-gray-500"><?= htmlspecialchars($schedule['courier_name']) ?></div>

                        <?php if ($schedule['max_weight_capacity'] || $schedule['max_cubic_meter']): ?>
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <?php if ($schedule['max_weight_capacity']): ?>
                                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-2 text-center">
                                        <div class="font-bold text-purple-800"><?= $schedule['max_weight_capacity'] ?> kg</div>
                                        <div class="text-xs text-purple-600">Max Weight</div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($schedule['max_cubic_meter']): ?>
                                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-2 text-center">
                                        <div class="font-bold text-purple-800"><?= $schedule['max_cubic_meter'] ?> m³</div>
                                        <div class="text-xs text-purple-600">Max Volume</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Capacity Status -->
                            <div class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold
                                        <?= ($weightOk && $volumeOk) ? 'bg-green-50 border border-green-300 text-green-800' : 'bg-red-50 border border-red-300 text-red-800' ?>">
                                <i class="fas <?= ($weightOk && $volumeOk) ? 'fa-check-circle text-green-500' : 'fa-exclamation-triangle text-red-500' ?>"></i>
                                <?= ($weightOk && $volumeOk) ? 'Capacity OK' : 'Capacity Exceeded!' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Order Items (collapsible) -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <button type="button" onclick="toggleItems()"
                        class="w-full px-5 py-3 border-b border-gray-100 flex items-center justify-between hover:bg-gray-50 transition">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-boxes text-gray-500 text-sm"></i>
                        <h3 class="font-semibold text-gray-800 text-sm">
                            Order Items
                            <span class="ml-1 text-gray-400 font-normal">(<?= count($items) ?>)</span>
                        </h3>
                    </div>
                    <i id="items-chevron" class="fas fa-chevron-down text-gray-400 text-xs transition-transform"></i>
                </button>

                <div id="items-list" class="hidden divide-y divide-gray-100 max-h-72 overflow-y-auto">
                    <?php foreach ($items as $idx => $item): ?>
                        <div class="px-5 py-3 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900 leading-tight">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </div>
                                    <?php if ($item['variant_color'] || $item['size']): ?>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            <?php if ($item['variant_color']): ?>
                                                <span><?= htmlspecialchars($item['variant_color']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($item['size']): ?>
                                                <span class="ml-1"><?= htmlspecialchars($item['size']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-xs text-gray-500">Qty: <strong><?= $item['quantity'] ?></strong></div>
                                    <div class="text-xs font-semibold text-blue-600">₱<?= number_format($item['subtotal'], 2) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div><!-- /sidebar -->
    </div><!-- /grid -->
</div>

<script>
    function toggleItems() {
        const list    = document.getElementById('items-list');
        const chevron = document.getElementById('items-chevron');
        const hidden  = list.classList.toggle('hidden');
        chevron.style.transform = hidden ? '' : 'rotate(180deg)';
    }
</script>

</body>
</html>