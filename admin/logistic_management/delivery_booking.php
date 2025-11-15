<?php
// delivery_booking.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);
require_once '../warehouse_management/audit_trail_helper.php'; // ADD THIS LINE

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$schedule_id || !$order_id) {
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Get delivery schedule and order details
$sql = "SELECT 
    ds.*,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.final_total,
    o.delivery_fee,
    o.assigned_vehicle_id,
    o.delivery_type,
    o.total_cubic_meters,
    o.total_weight_kg,
    tv.vehicle_type,
    tv.courier_name,
    tv.max_weight_capacity,
    tv.max_cubic_meter,
    (SELECT COUNT(*) FROM order_items WHERE order_id = ds.order_id) as total_items,
    (SELECT SUM(quantity) FROM order_items WHERE order_id = ds.order_id) as total_quantity
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
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Check if already booked
$checkBooking = $conn->prepare("SELECT id FROM delivery_bookings WHERE delivery_schedule_id = ?");
$checkBooking->bind_param("i", $schedule_id);
$checkBooking->execute();
$existingBooking = $checkBooking->get_result()->fetch_assoc();
$checkBooking->close();

if ($existingBooking) {
    header("Location: delivery_tracking.php?booking_id=" . $existingBooking['id']);
    exit();
}

// Get order items
$itemsSql = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $order_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Determine if it's pickup or delivery
$isPickup = strtolower($schedule['delivery_type']) === 'pickup';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $tracking_number = trim($_POST['tracking_number']);
    $courier_name = $schedule['courier_name'] ?? trim($_POST['courier_name']);
    $booking_reference = trim($_POST['booking_reference']);
    $estimated_pickup = $_POST['estimated_pickup_time'];
    $booking_notes = trim($_POST['booking_notes']);
    $user_id = $_SESSION['noble_user'];
    
    // New fields
    $pickup_person_name = isset($_POST['pickup_person_name']) ? trim($_POST['pickup_person_name']) : null;
    $pickup_person_contact = isset($_POST['pickup_person_contact']) ? trim($_POST['pickup_person_contact']) : null;
    $driver_name = isset($_POST['driver_name']) ? trim($_POST['driver_name']) : null;
    $vehicle_plate_number = isset($_POST['vehicle_plate_number']) ? strtoupper(trim($_POST['vehicle_plate_number'])) : null;
    
    $conn->begin_transaction();
    
    try {
        // Create booking
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
            $order_id,
            $schedule_id,
            $schedule['delivery_type'],
            $tracking_number,
            $courier_name,
            $schedule['assigned_vehicle_id'],
            $booking_reference,
            $estimated_pickup,
            $booking_notes,
            $user_id,
            $pickup_person_name,
            $pickup_person_contact,
            $driver_name,
            $vehicle_plate_number
        );
        
        if (!$insertBooking->execute()) {
            throw new Exception("Failed to create booking");
        }
        $booking_id = $conn->insert_id;
        $insertBooking->close();
        
        // LOG AUDIT TRAIL - CREATE BOOKING
        logAuditTrail(
            $conn,
            'CREATE_DELIVERY_BOOKING',
            'delivery_bookings',
            $booking_id,
            $order_id,
            null, // no order_item_id for booking creation
            null, // no old value
            json_encode([
                'booking_type' => $schedule['delivery_type'],
                'tracking_number' => $tracking_number,
                'courier_name' => $courier_name,
                'pickup_person' => $pickup_person_name,
                'pickup_contact' => $pickup_person_contact,
                'driver_name' => $driver_name,
                'vehicle_plate' => $vehicle_plate_number
            ]),
            "Created " . ucfirst($schedule['delivery_type']) . " booking with tracking #$tracking_number"
        );
        
        // Update all order items to ready_for_pickup
        $updateItems = $conn->prepare("
            UPDATE order_items 
            SET tracking_status = 'ready_for_pickup' 
            WHERE order_id = ?
        ");
        $updateItems->bind_param("i", $order_id);
        
        if (!$updateItems->execute()) {
            throw new Exception("Failed to update items");
        }
        $updateItems->close();
        
        // Update delivery schedule status
        $updateSchedule = $conn->prepare("
            UPDATE delivery_schedules 
            SET delivery_status = 'booked' 
            WHERE id = ?
        ");
        $updateSchedule->bind_param("i", $schedule_id);
        
        if (!$updateSchedule->execute()) {
            throw new Exception("Failed to update schedule");
        }
        $updateSchedule->close();
        
        // Update order status
        $updateOrder = $conn->prepare("
            UPDATE orders 
            SET status = 'Ready for Pickup' 
            WHERE id = ?
        ");
        $updateOrder->bind_param("i", $order_id);
        
        if (!$updateOrder->execute()) {
            throw new Exception("Failed to update order");
        }
        $updateOrder->close();
        
        $conn->commit();
        
        $_SESSION['success_message'] = "Booking created successfully!";
        header("Location: delivery_tracking.php?booking_id=" . $booking_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error creating booking: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?php echo ucfirst($schedule['delivery_type']); ?> - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slideIn { animation: slideIn 0.3s ease-out; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        
        <!-- Header -->
        <div class="mb-6">
            <a href="delivery_date_orders.php?date=<?php echo $schedule['delivery_date']; ?>" 
               class="inline-flex items-center text-blue-600 hover:text-blue-700 mb-3 text-sm font-medium transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Orders
            </a>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas <?php echo $isPickup ? 'fa-hand-holding' : 'fa-truck'; ?> text-blue-600 mr-3"></i>
                            <?php echo $isPickup ? 'Schedule Pickup' : 'Book Delivery'; ?>
                        </h1>
                        <p class="text-gray-600 text-sm mt-2 flex items-center">
                            <span class="font-semibold mr-2">Order #<?php echo $order_id; ?></span>
                            <span class="text-gray-400">•</span>
                            <span class="ml-2"><?php echo date('l, F d, Y', strtotime($schedule['delivery_date'])); ?></span>
                        </p>
                    </div>
                    
                    <!-- Quick Info -->
                    <div class="flex gap-3">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg px-5 py-3 text-center border border-blue-200">
                            <div class="text-2xl font-bold text-blue-700"><?php echo $schedule['total_items']; ?></div>
                            <div class="text-xs text-blue-600 font-medium">Items</div>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg px-5 py-3 text-center border border-green-200">
                            <div class="text-2xl font-bold text-green-700">₱<?php echo number_format($schedule['final_total'], 2); ?></div>
                            <div class="text-xs text-green-600 font-medium">Total</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-5 py-4 rounded-lg mb-6 flex items-start animate-slideIn">
            <i class="fas fa-exclamation-circle mr-3 mt-0.5 text-lg"></i>
            <div>
                <p class="font-semibold">Error</p>
                <p class="text-sm"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-6">
            
            <!-- Main Booking Form Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-file-alt mr-3"></i>
                        <?php echo $isPickup ? 'Pickup Information' : 'Booking Information'; ?>
                    </h2>
                    <p class="text-blue-100 text-sm mt-1">
                        <?php echo $isPickup ? 'Enter customer pickup details' : 'Enter courier booking details'; ?>
                    </p>
                </div>
                
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="create_booking">
                    
                    <div class="space-y-5">
                        
                        <?php if ($isPickup): ?>
                            <!-- PICKUP FORM -->
                            
                            <!-- Customer Name (For Pickup) -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-blue-500 mr-2"></i>
                                    Customer Name
                                </label>
                                <div class="bg-gray-50 border border-gray-300 rounded-lg p-3">
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($schedule['customer_name']); ?></p>
                                </div>
                            </div>
                            
                            <!-- Reference Number (For Pickup) -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Reference Number <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-hashtag text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="tracking_number" 
                                           required
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                           placeholder="Enter pickup reference number">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Internal reference number for this pickup</p>
                            </div>
                            
                            <!-- Pickup Method -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Pickup Method
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-hand-holding text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="courier_name" 
                                           value="Customer Pickup"
                                           readonly
                                           class="w-full pl-11 pr-4 py-3 bg-gray-50 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold">
                                </div>
                            </div>
                            
                            <!-- Pickup Person Name -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-blue-500 mr-2"></i>
                                    Pickup Person Name <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="pickup_person_name" 
                                           required
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                           placeholder="Enter name of person picking up">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Full name of the person who will pick up the order</p>
                            </div>
                            
                            <!-- Pickup Person Contact -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-phone text-blue-500 mr-2"></i>
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-phone text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="pickup_person_contact" 
                                           required
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                           placeholder="e.g., 09171234567">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Contact number of the person picking up</p>
                            </div>
                            
                            <!-- Vehicle Plate Number (Pickup) -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-car text-blue-500 mr-2"></i>
                                    Vehicle Plate Number <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-car text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="vehicle_plate_number"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all uppercase"
                                           placeholder="e.g., ABC 1234"
                                           style="text-transform: uppercase;">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Plate number of the vehicle used for pickup</p>
                            </div>
                            
                        <?php else: ?>
                            <!-- DELIVERY FORM -->
                            
                            <!-- Tracking Number -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-barcode text-blue-500 mr-2"></i>
                                    Tracking Number <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-barcode text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="tracking_number" 
                                           required
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                           placeholder="Enter tracking number from courier">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">The tracking number provided by the courier service</p>
                            </div>
                            
                            <!-- Courier Name -->
                            <?php if (!$schedule['courier_name']): ?>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-truck text-blue-500 mr-2"></i>
                                    Courier Service <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-truck text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="courier_name" 
                                           required
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                           placeholder="e.g., LBC, J&T, Lalamove, Grab">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Name of the courier service handling this delivery</p>
                            </div>
                            <?php else: ?>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-truck text-blue-500 mr-2"></i>
                                    Courier Service
                                </label>
                                <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-4 flex items-center">
                                    <i class="fas fa-truck text-purple-600 text-xl mr-3"></i>
                                    <div class="flex-1">
                                        <span class="font-bold text-purple-900 text-lg"><?php echo htmlspecialchars($schedule['courier_name']); ?></span>
                                        <p class="text-xs text-purple-600 mt-0.5">Pre-assigned vehicle</p>
                                    </div>
                                    <span class="bg-purple-200 text-purple-800 text-xs font-semibold px-3 py-1 rounded-full">Assigned</span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Booking Reference -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-hashtag text-blue-500 mr-2"></i>
                                    Booking Reference <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-hashtag text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="booking_reference"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                           placeholder="Courier's booking or waybill number">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Additional reference number from the courier</p>
                            </div>
                            
                            <!-- Driver Name -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-id-card text-blue-500 mr-2"></i>
                                    Driver Name <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-id-card text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="driver_name"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                           placeholder="Enter driver's name">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Name of the delivery driver</p>
                            </div>
                            
                            <!-- Vehicle Plate Number (Delivery) -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-truck text-blue-500 mr-2"></i>
                                    Vehicle Plate Number <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-truck text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="vehicle_plate_number"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all uppercase"
                                           placeholder="e.g., ABC 1234"
                                           style="text-transform: uppercase;">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Plate number of the delivery vehicle</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Estimated Time (Both) -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-clock text-blue-500 mr-2"></i>
                                Estimated <?php echo $isPickup ? 'Pickup' : 'Delivery'; ?> Time <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-clock text-gray-400"></i>
                                </div>
                                <input type="datetime-local" 
                                       name="estimated_pickup_time"
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($schedule['delivery_date'] . ' ' . $schedule['delivery_time'])); ?>"
                                       class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Estimated time for <?php echo $isPickup ? 'customer pickup' : 'delivery'; ?></p>
                        </div>
                        
                        <!-- Notes (Both) -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-comment-alt text-blue-500 mr-2"></i>
                                Notes <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                            </label>
                            <textarea name="booking_notes"
                                      rows="4"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all resize-none"
                                      placeholder="<?php echo $isPickup ? 'Special instructions for pickup, parking information, etc...' : 'Special handling instructions, delivery notes, etc...'; ?>"></textarea>
                            <p class="text-xs text-gray-500 mt-2">Additional instructions or important information</p>
                        </div>
                        
                        <!-- Quick View Button -->
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
                            <button type="button" 
                                    onclick="openDetailsModal()"
                                    class="w-full flex items-center justify-center gap-2 text-blue-700 hover:text-blue-800 font-semibold transition-colors">
                                <i class="fas fa-info-circle"></i>
                                <span>View Order Details & Customer Information</span>
                                <i class="fas fa-chevron-right text-sm"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3 mt-8 pt-6 border-t-2 border-gray-200">
                        <a href="delivery_date_orders.php?date=<?php echo $schedule['delivery_date']; ?>" 
                           class="flex-1 px-6 py-4 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-semibold text-center flex items-center justify-center gap-2">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </a>
                        <button type="submit" 
                                class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl font-bold flex items-center justify-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            <span>Confirm <?php echo $isPickup ? 'Pickup' : 'Booking'; ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-blue-700 p-6 rounded-t-xl z-10 flex items-center justify-between">
                <h3 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-info-circle mr-3"></i>
                    Complete Order Information
                </h3>
                <button onclick="closeDetailsModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Left Column -->
                    <div class="space-y-4">
                        
                        <!-- Customer Info -->
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-5">
                            <h4 class="font-bold text-blue-900 mb-4 pb-3 border-b border-blue-200 flex items-center">
                                <i class="fas fa-user-circle text-blue-600 mr-2"></i>
                                Customer Information
                            </h4>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <p class="text-blue-700 text-xs mb-1">Name</p>
                                    <p class="font-bold text-blue-900"><?php echo htmlspecialchars($schedule['customer_name']); ?></p>
                                </div>
                                <?php if ($schedule['mobile']): ?>
                                <div>
                                    <p class="text-blue-700 text-xs mb-1">Mobile</p>
                                    <p class="font-bold text-blue-900">
                                        <i class="fas fa-phone text-green-500 mr-2"></i>
                                        <?php echo htmlspecialchars($schedule['mobile']); ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                                <?php if ($schedule['email']): ?>
                                <div>
                                    <p class="text-blue-700 text-xs mb-1">Email</p>
                                    <p class="font-bold text-blue-900">
                                        <i class="fas fa-envelope text-blue-500 mr-2"></i>
                                        <?php echo htmlspecialchars($schedule['email']); ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <p class="text-blue-700 text-xs mb-1">Address</p>
                                    <p class="font-semibold text-blue-900 leading-relaxed">
                                        <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                                        <?php echo htmlspecialchars($schedule['address']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="bg-green-50 border-2 border-green-200 rounded-lg p-5">
                            <h4 class="font-bold text-green-900 mb-4 pb-3 border-b border-green-200 flex items-center">
                                <i class="fas fa-receipt text-green-600 mr-2"></i>
                                Order Summary
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-green-700">Items</span>
                                    <span class="font-bold text-green-900"><?php echo $schedule['total_items']; ?> items (<?php echo $schedule['total_quantity']; ?> pcs)</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-green-700">Subtotal</span>
                                    <span class="font-bold text-green-900">₱<?php echo number_format($schedule['final_total'] - ($schedule['delivery_fee'] ?? 0), 2); ?></span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-green-700">Delivery Fee</span>
                                    <span class="font-bold text-blue-600">₱<?php echo number_format($schedule['delivery_fee'] ?? 0, 2); ?></span>
                                </div>
                                <div class="pt-3 border-t-2 border-green-300">
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold text-green-900">Total</span>
                                        <span class="text-2xl font-bold text-green-700">₱<?php echo number_format($schedule['final_total'], 2); ?></span>
                                    </div>
                                </div>
                                <div class="pt-3 border-t border-green-200 space-y-2">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-green-700">Type</span>
                                        <span class="font-bold <?php echo $isPickup ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800'; ?> px-3 py-1 rounded-full">
                                            <i class="fas <?php echo $isPickup ? 'fa-hand-holding' : 'fa-truck'; ?> mr-1"></i>
                                            <?php echo ucfirst($schedule['delivery_type']); ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-green-700">Scheduled</span>
                                        <span class="font-bold text-green-900">
                                            <?php echo date('M d, g:i A', strtotime($schedule['delivery_date'] . ' ' . $schedule['delivery_time'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shipment Specs -->
                        <div class="bg-gradient-to-br from-orange-50 to-amber-50 border-2 border-orange-200 rounded-lg p-5">
                            <h4 class="font-bold text-orange-900 mb-4 pb-3 border-b border-orange-200 flex items-center">
                                <i class="fas fa-box text-orange-600 mr-2"></i>
                                Shipment Specifications
                            </h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-white rounded-lg p-4 border-2 border-orange-200">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-weight-hanging text-orange-500 text-2xl mb-2"></i>
                                        <span class="text-2xl font-bold text-orange-700">
                                            <?php echo $schedule['total_weight_kg'] ? number_format($schedule['total_weight_kg'], 2) : '0.00'; ?>
                                        </span>
                                        <span class="text-xs text-orange-600 font-semibold">kg</span>
                                        <span class="text-xs text-gray-500 mt-1">Weight</span>
                                    </div>
                                </div>
                                
                                <div class="bg-white rounded-lg p-4 border-2 border-orange-200">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-cube text-orange-500 text-2xl mb-2"></i>
                                        <span class="text-2xl font-bold text-orange-700">
                                            <?php echo $schedule['total_cubic_meters'] ? number_format($schedule['total_cubic_meters'], 3) : '0.000'; ?>
                                        </span>
                                        <span class="text-xs text-orange-600 font-semibold">m³</span>
                                        <span class="text-xs text-gray-500 mt-1">Volume</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Info -->
                        <?php if ($schedule['assigned_vehicle_id']): ?>
                        <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-5">
                            <h4 class="font-bold text-purple-900 mb-4 pb-3 border-b border-purple-200 flex items-center">
                                <i class="fas fa-truck text-purple-600 mr-2"></i>
                                Assigned Vehicle
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-purple-700 text-xs mb-1">Vehicle Type</p>
                                    <p class="font-bold text-purple-900 text-lg"><?php echo htmlspecialchars($schedule['vehicle_type']); ?></p>
                                </div>
                                <div>
                                    <p class="text-purple-700 text-xs mb-1">Courier</p>
                                    <p class="font-bold text-purple-900"><?php echo htmlspecialchars($schedule['courier_name']); ?></p>
                                </div>
                                
                                <?php if ($schedule['max_weight_capacity'] || $schedule['max_cubic_meter']): ?>
                                <div class="pt-3 border-t border-purple-200">
                                    <p class="text-purple-700 text-xs mb-3 font-semibold">Capacity</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <?php if ($schedule['max_weight_capacity']): ?>
                                        <div class="bg-white rounded-lg p-3 text-center border border-purple-200">
                                            <div class="font-bold text-purple-900"><?php echo $schedule['max_weight_capacity']; ?> kg</div>
                                            <div class="text-xs text-purple-600">Max Weight</div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($schedule['max_cubic_meter']): ?>
                                        <div class="bg-white rounded-lg p-3 text-center border border-purple-200">
                                            <div class="font-bold text-purple-900"><?php echo $schedule['max_cubic_meter']; ?> m³</div>
                                            <div class="text-xs text-purple-600">Max Volume</div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Capacity Check -->
                                <?php 
                                $weightOk = !$schedule['max_weight_capacity'] || ($schedule['total_weight_kg'] <= $schedule['max_weight_capacity']);
                                $volumeOk = !$schedule['max_cubic_meter'] || ($schedule['total_cubic_meters'] <= $schedule['max_cubic_meter']);
                                ?>
                                
                                <div class="<?php echo ($weightOk && $volumeOk) ? 'bg-green-100 border-green-300 text-green-800' : 'bg-red-100 border-red-300 text-red-800'; ?> border-2 rounded-lg p-3 text-sm flex items-center font-semibold">
                                    <i class="fas <?php echo ($weightOk && $volumeOk) ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2 text-lg"></i>
                                    <span><?php echo ($weightOk && $volumeOk) ? 'Capacity Sufficient' : 'Warning: Capacity Exceeded!'; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Right Column - Order Items -->
                    <div>
                        <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-5 h-full">
                            <h4 class="font-bold text-gray-900 mb-4 pb-3 border-b border-gray-300 flex items-center justify-between">
                                <span class="flex items-center">
                                    <i class="fas fa-boxes text-gray-600 mr-2"></i>
                                    Order Items
                                </span>
                                <span class="bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1 rounded-full">
                                    <?php echo count($items); ?> Items
                                </span>
                            </h4>
                            
                            <div class="space-y-3 max-h-[600px] overflow-y-auto pr-2">
                                <?php foreach ($items as $index => $item): ?>
                                <div class="bg-white rounded-lg p-4 border-2 border-gray-200 hover:border-blue-300 hover:shadow-md transition-all">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <span class="text-blue-700 font-bold text-sm"><?php echo $index + 1; ?></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h5 class="font-bold text-gray-900 text-sm leading-tight mb-2">
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                            </h5>
                                            
                                            <?php if ($item['variant_color'] || $item['size']): ?>
                                            <div class="space-y-1 mb-3">
                                                <?php if ($item['variant_color']): ?>
                                                <div class="flex items-center text-xs text-gray-600">
                                                    <i class="fas fa-palette text-blue-400 mr-2"></i>
                                                    <span><?php echo htmlspecialchars($item['variant_color']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($item['size']): ?>
                                                <div class="flex items-center text-xs text-gray-600">
                                                    <i class="fas fa-ruler text-green-400 mr-2"></i>
                                                    <span><?php echo htmlspecialchars($item['size']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                                                <div class="flex items-center gap-2">
                                                    <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-bold">
                                                        Qty: <?php echo $item['quantity']; ?>
                                                    </span>
                                                </div>
                                                <span class="font-bold text-blue-600">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Close Button -->
                <div class="mt-6 pt-6 border-t-2 border-gray-200">
                    <button onclick="closeDetailsModal()" 
                            class="w-full bg-gradient-to-r from-gray-600 to-gray-700 text-white px-6 py-4 rounded-lg hover:from-gray-700 hover:to-gray-800 transition-all font-bold flex items-center justify-center gap-2">
                        <i class="fas fa-times-circle"></i>
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openDetailsModal() {
        document.getElementById('detailsModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside
    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('detailsModal').classList.contains('hidden')) {
            closeDetailsModal();
        }
    });
    </script>
</body>
</html>