<?php
// replacement_booking.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

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

// Get delivery schedule details
$scheduleSql = "SELECT 
    ds.*,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.delivery_type as order_delivery_type
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
WHERE ds.id = ? AND ds.order_id = ? AND ds.item_type = 'replacement'";

$scheduleStmt = $conn->prepare($scheduleSql);
$scheduleStmt->bind_param("ii", $schedule_id, $order_id);
$scheduleStmt->execute();
$schedule = $scheduleStmt->get_result()->fetch_assoc();
$scheduleStmt->close();

if (!$schedule) {
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Get ALL replacement items for this delivery schedule
$replacementsSql = "SELECT 
    rr.*,
    oi.product_name,
    oi.variant_color,
    oi.size,
    oi.price,
    oi.product_id,
    oi.id as order_item_id
FROM replacement_requests rr
INNER JOIN order_items oi ON rr.order_item_id = oi.id
WHERE rr.delivery_schedule_id = ? AND rr.order_id = ?
ORDER BY oi.product_name";

$replacementsStmt = $conn->prepare($replacementsSql);
$replacementsStmt->bind_param("ii", $schedule_id, $order_id);
$replacementsStmt->execute();
$replacements = $replacementsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$replacementsStmt->close();

if (empty($replacements)) {
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Calculate totals
$totalQuantity = array_sum(array_column($replacements, 'replacement_quantity'));
$totalValue = 0;
foreach ($replacements as $replacement) {
    $totalValue += $replacement['price'] * $replacement['replacement_quantity'];
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

// Calculate total weight and volume for ALL replacement items
$total_weight_kg = 0;
$total_cubic_meters = 0;

foreach ($replacements as $replacement) {
    // Get product dimensions and weight
    $dimensionsSql = "SELECT 
        pv.weight,
        pv.weight_unit,
        pv.width,
        pv.height,
        pv.length,
        pv.dimension_unit
    FROM product_variants pv
    WHERE pv.product_id = ?
    LIMIT 1";
    
    $dimStmt = $conn->prepare($dimensionsSql);
    $dimStmt->bind_param("i", $replacement['product_id']);
    $dimStmt->execute();
    $dimensions = $dimStmt->get_result()->fetch_assoc();
    $dimStmt->close();
    
    // Calculate weight for this item
    if ($dimensions && $dimensions['weight']) {
        $weight = floatval($dimensions['weight']);
        $weight_unit = strtolower($dimensions['weight_unit']);
        $quantity = $replacement['replacement_quantity'];
        
        if ($weight_unit === 'kg') {
            $total_weight_kg += $weight * $quantity;
        } elseif ($weight_unit === 'g' || $weight_unit === 'grams') {
            $total_weight_kg += ($weight / 1000) * $quantity;
        } elseif ($weight_unit === 'lbs' || $weight_unit === 'lb') {
            $total_weight_kg += ($weight * 0.453592) * $quantity;
        }
    }
    
    // Calculate volume for this item
    if ($dimensions && $dimensions['width'] && $dimensions['height'] && $dimensions['length']) {
        $width = floatval($dimensions['width']);
        $height = floatval($dimensions['height']);
        $length = floatval($dimensions['length']);
        $dimension_unit = strtolower($dimensions['dimension_unit']);
        $quantity = $replacement['replacement_quantity'];
        
        // Convert to meters
        if ($dimension_unit === 'cm') {
            $width_m = $width / 100;
            $height_m = $height / 100;
            $length_m = $length / 100;
        } elseif ($dimension_unit === 'mm') {
            $width_m = $width / 1000;
            $height_m = $height / 1000;
            $length_m = $length / 1000;
        } elseif ($dimension_unit === 'in' || $dimension_unit === 'inch') {
            $width_m = $width * 0.0254;
            $height_m = $height * 0.0254;
            $length_m = $length * 0.0254;
        } elseif ($dimension_unit === 'ft' || $dimension_unit === 'feet') {
            $width_m = $width * 0.3048;
            $height_m = $height * 0.3048;
            $length_m = $length * 0.3048;
        } else {
            $width_m = $width;
            $height_m = $height;
            $length_m = $length;
        }
        
        $total_cubic_meters += ($width_m * $height_m * $length_m) * $quantity;
    }
}

$isPickup = strtolower($schedule['order_delivery_type']) === 'pickup';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $tracking_number = trim($_POST['tracking_number']);
    $courier_name = isset($_POST['courier_name']) ? trim($_POST['courier_name']) : 'Replacement Delivery';
    $booking_reference = trim($_POST['booking_reference']);
    $estimated_pickup = $_POST['estimated_pickup_time'];
    $booking_notes = trim($_POST['booking_notes']);
    $user_id = $_SESSION['noble_user'];
    
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
             booking_reference, estimated_pickup_time, booking_notes, 
             booking_status, created_by, pickup_person_name, pickup_person_contact, 
             driver_name, vehicle_plate_number, is_replacement)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, ?, ?, 1)
        ");
        
        $insertBooking->bind_param(
    "iissssssissss",
    $order_id,
    $schedule_id,
    $schedule['order_delivery_type'],
            $tracking_number,
            $courier_name,
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
        
        // Update ALL replacement requests for this delivery schedule
$updateReplacement = $conn->prepare("
    UPDATE replacement_requests 
    SET status = 'ready_for_pickup' 
    WHERE delivery_schedule_id = ? AND order_id = ?
");
$updateReplacement->bind_param("ii", $schedule_id, $order_id);
        
        if (!$updateReplacement->execute()) {
            throw new Exception("Failed to update replacement");
        }
        $updateReplacement->close();
        
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
        
        $conn->commit();
        
        $_SESSION['success_message'] = "Replacement booking created successfully!";
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
    <title>Book Replacement <?php echo ucfirst($replacement['order_delivery_type']); ?> - Noble Home</title>
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
            
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-lg border border-orange-300 p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="bg-white text-orange-600 px-3 py-1 rounded-full text-xs font-bold flex items-center">
                                <i class="fas fa-sync-alt mr-1"></i>
                                REPLACEMENT
                            </span>
                            <h1 class="text-3xl font-bold text-white flex items-center">
                                <i class="fas <?php echo $isPickup ? 'fa-hand-holding' : 'fa-truck'; ?> mr-3"></i>
                                <?php echo $isPickup ? 'Schedule Replacement Pickup' : 'Book Replacement Delivery'; ?>
                            </h1>
                        </div>
                        <p class="text-orange-100 text-sm flex items-center">
                            <span class="font-semibold mr-2">Order #<?php echo $replacement['order_id']; ?></span>
                            <span class="text-orange-200">•</span>
                            <span class="ml-2">Schedule #<?php echo $schedule_id; ?></span>
                            <span class="text-orange-200 mx-2">•</span>
                            <span><?php echo date('l, F d, Y', strtotime($schedule['delivery_date'])); ?></span>
                        </p>
                    </div>
                    
                    <!-- Quick Info -->
                    <div class="flex gap-3">
    <div class="bg-white bg-opacity-20 backdrop-blur rounded-lg px-5 py-3 text-center border border-white border-opacity-30">
        <div class="text-2xl font-bold text-white"><?php echo count($replacements); ?></div>
        <div class="text-xs text-orange-100 font-medium">Items</div>
    </div>
    <div class="bg-white bg-opacity-20 backdrop-blur rounded-lg px-5 py-3 text-center border border-white border-opacity-30">
        <div class="text-2xl font-bold text-white"><?php echo $totalQuantity; ?></div>
        <div class="text-xs text-orange-100 font-medium">Total Qty</div>
    </div>
    <div class="bg-white bg-opacity-20 backdrop-blur rounded-lg px-5 py-3 text-center border border-white border-opacity-30">
        <div class="text-2xl font-bold text-white">₱<?php echo number_format($totalValue, 2); ?></div>
        <div class="text-xs text-orange-100 font-medium">Total Value</div>
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
                <div class="bg-gradient-to-r from-orange-600 to-orange-700 p-6">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-file-alt mr-3"></i>
                        Replacement <?php echo $isPickup ? 'Pickup' : 'Booking'; ?> Information
                    </h2>
                    <p class="text-orange-100 text-sm mt-1">
                        <?php echo $isPickup ? 'Enter customer pickup details for replacement item' : 'Enter courier booking details for replacement delivery'; ?>
                    </p>
                </div>
                
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="create_booking">
                    
                    <div class="space-y-5">
                        
                        <?php if ($isPickup): ?>
                            <!-- PICKUP FORM -->
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-orange-500 mr-2"></i>
                                    Customer Name
                                </label>
                                <div class="bg-gray-50 border border-gray-300 rounded-lg p-3">
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($schedule['customer_name']); ?></p>
                                </div>
                            </div>
                            
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
                                           value="RPL-<?php echo $schedule_id; ?>-<?php echo date('Ymd'); ?>"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                                           placeholder="Enter replacement pickup reference">
                                </div>
                            </div>
                            
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
                                           value="Customer Pickup - Replacement"
                                           readonly
                                           class="w-full pl-11 pr-4 py-3 bg-gray-50 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-orange-500 mr-2"></i>
                                    Pickup Person Name <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="pickup_person_name" 
                                           required
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                                           placeholder="Enter name of person picking up replacement">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-phone text-orange-500 mr-2"></i>
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-phone text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="pickup_person_contact" 
                                           required
                                           value="<?php echo htmlspecialchars($schedule['mobile']); ?>"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                                           placeholder="e.g., 09171234567">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-car text-orange-500 mr-2"></i>
                                    Vehicle Plate Number <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-car text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="vehicle_plate_number"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all uppercase"
                                           placeholder="e.g., ABC 1234"
                                           style="text-transform: uppercase;">
                                </div>
                            </div>
                            
                        <?php else: ?>
                            <!-- DELIVERY FORM -->
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-barcode text-orange-500 mr-2"></i>
                                    Tracking Number <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-barcode text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="tracking_number" 
                                           required
                                           value="RPL-<?php echo $schedule_id; ?>-<?php echo date('Ymd'); ?>"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                                           placeholder="Enter tracking number from courier">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-truck text-orange-500 mr-2"></i>
                                    Courier Service <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-truck text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="courier_name" 
                                           required
                                           value="Replacement Delivery"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                                           placeholder="e.g., LBC, J&T, Lalamove, Grab">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-hashtag text-orange-500 mr-2"></i>
                                    Booking Reference <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-hashtag text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="booking_reference"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                                           placeholder="Courier's booking or waybill number">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-id-card text-orange-500 mr-2"></i>
                                    Driver Name <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-id-card text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="driver_name"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                                           placeholder="Enter driver's name">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-truck text-orange-500 mr-2"></i>
                                    Vehicle Plate Number <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas fa-truck text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="vehicle_plate_number"
                                           class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all uppercase"
                                           placeholder="e.g., ABC 1234"
                                           style="text-transform: uppercase;">
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-clock text-orange-500 mr-2"></i>
                                Estimated <?php echo $isPickup ? 'Pickup' : 'Delivery'; ?> Time <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-clock text-gray-400"></i>
                                </div>
                                <input type="datetime-local" 
                                       name="estimated_pickup_time"
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($schedule['delivery_date'] . ' ' . $schedule['delivery_time'])); ?>"
                                       class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-comment-alt text-orange-500 mr-2"></i>
                                Notes <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                            </label>
                            <textarea name="booking_notes"
          rows="4"
          class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all resize-none"
          placeholder="Replacement delivery - <?php echo count($replacements); ?> items">Replacement delivery for <?php echo count($replacements); ?> item(s). Multiple replacement requests included in this shipment.</textarea>
                        </div>
                        
                        <div class="bg-orange-50 border-2 border-orange-200 rounded-lg p-4">
                            <button type="button" 
                                    onclick="openDetailsModal()"
                                    class="w-full flex items-center justify-center gap-2 text-orange-700 hover:text-orange-800 font-semibold transition-colors">
                                <i class="fas fa-info-circle"></i>
                                <span>View Replacement Details & Customer Information</span>
                                <i class="fas fa-chevron-right text-sm"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-8 pt-6 border-t-2 border-gray-200">
                        <a href="delivery_date_orders.php?date=<?php echo $schedule['delivery_date']; ?>" 
                           class="flex-1 px-6 py-4 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-semibold text-center flex items-center justify-center gap-2">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </a>
                        <button type="submit" 
                                class="flex-1 bg-gradient-to-r from-orange-600 to-orange-700 text-white px-6 py-4 rounded-lg hover:from-orange-700 hover:to-orange-800 transition-all shadow-lg hover:shadow-xl font-bold flex items-center justify-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            <span>Confirm Replacement <?php echo $isPickup ? 'Pickup' : 'Booking'; ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-gradient-to-r from-orange-600 to-orange-700 p-6 rounded-t-xl z-10 flex items-center justify-between">
                <h3 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-sync-alt mr-3"></i>
                    Replacement Details
                </h3>
                <button onclick="closeDetailsModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Left Column -->
                    <div class="space-y-4">
                        
                        <!-- Replacement Info - Summary -->
<div class="bg-orange-50 border-2 border-orange-200 rounded-lg p-5">
    <h4 class="font-bold text-orange-900 mb-4 pb-3 border-b border-orange-200 flex items-center justify-between">
        <span class="flex items-center">
            <i class="fas fa-sync-alt text-orange-600 mr-2"></i>
            Replacement Summary
        </span>
        <span class="bg-orange-100 text-orange-800 text-xs font-bold px-3 py-1 rounded-full">
            <?php echo count($replacements); ?> Items
        </span>
    </h4>
    <div class="space-y-3 text-sm">
        <div>
            <p class="text-orange-700 text-xs mb-1">Schedule ID</p>
            <p class="font-bold text-orange-900">#<?php echo $schedule_id; ?></p>
        </div>
        <div>
            <p class="text-orange-700 text-xs mb-1">Order ID</p>
            <p class="font-bold text-orange-900">#<?php echo $order_id; ?></p>
        </div>
        <div>
            <p class="text-orange-700 text-xs mb-1">Total Items</p>
            <p class="font-bold text-orange-900"><?php echo count($replacements); ?> products</p>
        </div>
        <div>
            <p class="text-orange-700 text-xs mb-1">Total Quantity</p>
            <p class="font-bold text-orange-900"><?php echo $totalQuantity; ?> pieces</p>
        </div>
        <div>
            <p class="text-orange-700 text-xs mb-1">Total Value</p>
            <p class="font-bold text-orange-900 text-lg">₱<?php echo number_format($totalValue, 2); ?></p>
        </div>
        
        <!-- Reasons Summary -->
        <div class="pt-3 border-t border-orange-200">
            <p class="text-orange-700 text-xs mb-2 font-semibold">Replacement Reasons:</p>
            <div class="space-y-1">
                <?php 
                $reasons = array_count_values(array_column($replacements, 'reason'));
                foreach ($reasons as $reason => $count):
                ?>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-orange-900 capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $reason)); ?></span>
                    <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded-full font-bold"><?php echo $count; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
                        
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

                        <!-- Shipment Specs (Calculated from product_variants) -->
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 border-2 border-purple-200 rounded-lg p-5">
                            <h4 class="font-bold text-purple-900 mb-4 pb-3 border-b border-purple-200 flex items-center">
                                <i class="fas fa-box text-purple-600 mr-2"></i>
                                Shipment Specifications
                                <span class="ml-2 text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded-full">Replacement</span>
                            </h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-white rounded-lg p-4 border-2 border-purple-200">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-weight-hanging text-purple-500 text-2xl mb-2"></i>
                                        <span class="text-2xl font-bold text-purple-700">
                                            <?php echo number_format($total_weight_kg, 2); ?>
                                        </span>
                                        <span class="text-xs text-purple-600 font-semibold">kg</span>
                                        <span class="text-xs text-gray-500 mt-1">Total Weight</span>
<span class="text-xs text-gray-400 mt-1">
    (All items combined)
</span>
                                    </div>
                                </div>
                                
                                <div class="bg-white rounded-lg p-4 border-2 border-purple-200">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-cube text-purple-500 text-2xl mb-2"></i>
                                        <span class="text-2xl font-bold text-purple-700">
                                            <?php echo number_format($total_cubic_meters, 3); ?>
                                        </span>
                                        <span class="text-xs text-purple-600 font-semibold">m³</span>
                                        <span class="text-xs text-gray-500 mt-1">Total Volume</span>
<span class="text-xs text-gray-400 mt-1">
    (All items combined)
</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
    <p class="text-xs text-blue-800 flex items-center">
        <i class="fas fa-info-circle mr-2"></i>
        Dimensions calculated from <?php echo count($replacements); ?> replacement item(s)
    </p>
</div>
                        </div>

                        <!-- Schedule Info -->
                        <div class="bg-green-50 border-2 border-green-200 rounded-lg p-5">
                            <h4 class="font-bold text-green-900 mb-4 pb-3 border-b border-green-200 flex items-center">
                                <i class="fas fa-calendar-check text-green-600 mr-2"></i>
                                Schedule Information
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
    <span class="text-green-700">Type</span>
    <span class="font-bold <?php echo $isPickup ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800'; ?> px-3 py-1 rounded-full">
        <i class="fas <?php echo $isPickup ? 'fa-hand-holding' : 'fa-truck'; ?> mr-1"></i>
        <?php echo ucfirst($schedule['order_delivery_type']); ?>
    </span>
</div>
<div class="flex justify-between items-center text-sm">
    <span class="text-green-700">Date</span>
    <span class="font-bold text-green-900">
        <?php echo date('M d, Y', strtotime($schedule['delivery_date'])); ?>
    </span>
</div>
<div class="flex justify-between items-center text-sm">
    <span class="text-green-700">Time</span>
    <span class="font-bold text-green-900">
        <?php echo date('g:i A', strtotime($schedule['delivery_time'])); ?>
    </span>
</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column - Product Details -->
<div>
    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-5 h-full">
        <h4 class="font-bold text-gray-900 mb-4 pb-3 border-b border-gray-300 flex items-center justify-between">
            <span class="flex items-center">
                <i class="fas fa-box-open text-gray-600 mr-2"></i>
                Replacement Items
            </span>
            <span class="bg-orange-100 text-orange-800 text-xs font-bold px-3 py-1 rounded-full">
                <?php echo count($replacements); ?> Products | Total Qty: <?php echo $totalQuantity; ?>
            </span>
        </h4>
        
        <div class="space-y-4 max-h-[600px] overflow-y-auto">
            <?php foreach ($replacements as $index => $replacement): ?>
            <div class="bg-white rounded-lg p-5 border-2 border-orange-200">
                <div class="flex items-start justify-between mb-3">
                    <h5 class="font-bold text-gray-900 text-base leading-tight flex-1">
                        <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-bold mr-2">
                            #<?php echo $index + 1; ?>
                        </span>
                        <?php echo htmlspecialchars($replacement['product_name']); ?>
                    </h5>
                    <span class="bg-orange-100 text-orange-800 text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap ml-2">
                        Qty: <?php echo $replacement['replacement_quantity']; ?>
                    </span>
                </div>
                
                <?php if ($replacement['variant_color'] || $replacement['size']): ?>
                <div class="space-y-1 mb-3 text-sm">
                    <?php if ($replacement['variant_color']): ?>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-palette text-blue-400 mr-2 w-4"></i>
                        <span class="font-semibold">Color:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($replacement['variant_color']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($replacement['size']): ?>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-ruler text-green-400 mr-2 w-4"></i>
                        <span class="font-semibold">Size:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($replacement['size']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Replacement Info -->
                <div class="bg-orange-50 border border-orange-200 rounded p-3 mb-3">
                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="text-orange-700 font-semibold">Request ID:</span>
                            <span class="text-orange-900 font-bold ml-2">#<?php echo $replacement['id']; ?></span>
                        </div>
                        <div>
                            <span class="text-orange-700 font-semibold">Reason:</span>
                            <span class="text-orange-900 ml-2 capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $replacement['reason'])); ?></span>
                        </div>
                        <?php if ($replacement['details']): ?>
                        <div>
                            <span class="text-orange-700 font-semibold">Details:</span>
                            <p class="text-orange-900 mt-1 text-xs"><?php echo nl2br(htmlspecialchars($replacement['details'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-orange-700 font-semibold">Status:</span>
                            <span class="inline-block bg-orange-100 text-orange-800 px-2 py-0.5 rounded-full text-xs font-bold ml-2">
                                <?php echo ucfirst($replacement['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Pricing -->
                <div class="pt-3 border-t-2 border-gray-200">
                    <div class="flex justify-between items-center text-sm mb-2">
                        <span class="text-gray-600">Unit Price</span>
                        <span class="font-bold text-gray-900">₱<?php echo number_format($replacement['price'], 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm mb-2">
                        <span class="text-gray-600">Quantity</span>
                        <span class="font-bold text-gray-900"><?php echo $replacement['replacement_quantity']; ?> pcs</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                        <span class="font-bold text-gray-900 text-sm">Subtotal</span>
                        <span class="text-lg font-bold text-orange-600">
                            ₱<?php echo number_format($replacement['price'] * $replacement['replacement_quantity'], 2); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Defect Images -->
                <?php if ($replacement['defect_image_overview'] || $replacement['defect_image_closeup'] || $replacement['defect_image_detail']): ?>
                <div class="mt-3 bg-red-50 border border-red-200 rounded p-3">
                    <h6 class="font-bold text-red-900 mb-2 flex items-center text-xs">
                        <i class="fas fa-images text-red-600 mr-2"></i>
                        Defect Evidence
                    </h6>
                    <div class="grid grid-cols-3 gap-2">
                        <?php if ($replacement['defect_image_overview']): ?>
                        <div class="text-center">
                            <div class="bg-white rounded p-2 border border-red-200">
                                <i class="fas fa-camera text-red-400 text-xl"></i>
                            </div>
                            <p class="text-xs text-red-700 font-semibold mt-1">Overview</p>
                        </div>
                        <?php endif; ?>
                        <?php if ($replacement['defect_image_closeup']): ?>
                        <div class="text-center">
                            <div class="bg-white rounded p-2 border border-red-200">
                                <i class="fas fa-search-plus text-red-400 text-xl"></i>
                            </div>
                            <p class="text-xs text-red-700 font-semibold mt-1">Close-up</p>
                        </div>
                        <?php endif; ?>
                        <?php if ($replacement['defect_image_detail']): ?>
                        <div class="text-center">
                            <div class="bg-white rounded p-2 border border-red-200">
                                <i class="fas fa-image text-red-400 text-xl"></i>
                            </div>
                            <p class="text-xs text-red-700 font-semibold mt-1">Detail</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Admin Notes -->
                <?php if ($replacement['admin_notes']): ?>
                <div class="mt-3 bg-blue-50 border border-blue-200 rounded p-3">
                    <h6 class="font-bold text-blue-900 mb-1 flex items-center text-xs">
                        <i class="fas fa-clipboard-list text-blue-600 mr-2"></i>
                        Admin Notes
                    </h6>
                    <p class="text-xs text-blue-900 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($replacement['admin_notes'])); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Grand Total -->
        <div class="mt-4 bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-orange-100 text-xs mb-1">Grand Total</p>
                    <p class="text-sm"><?php echo count($replacements); ?> items • <?php echo $totalQuantity; ?> pieces</p>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-bold">₱<?php echo number_format($totalValue, 2); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
                </div>
                
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
    
    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('detailsModal').classList.contains('hidden')) {
            closeDetailsModal();
        }
    });
    </script>
</body>
</html>