<?php
// delivery_booking.php
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

// Get delivery schedule and order details
$sql = "SELECT 
    ds.*,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.final_total,
    o.assigned_vehicle_id,
    o.delivery_type,
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $tracking_number = trim($_POST['tracking_number']);
    $courier_name = $schedule['courier_name'] ?? trim($_POST['courier_name']);
    $booking_reference = trim($_POST['booking_reference']);
    $estimated_pickup = $_POST['estimated_pickup_time'];
    $booking_notes = trim($_POST['booking_notes']);
    $user_id = $_SESSION['noble_user'];
    
    $conn->begin_transaction();
    
    try {
        // Create booking
        $insertBooking = $conn->prepare("
            INSERT INTO delivery_bookings 
            (order_id, delivery_schedule_id, booking_type, tracking_number, courier_name, 
             vehicle_id, booking_reference, estimated_pickup_time, booking_notes, 
             booking_status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)
        ");
        
        $insertBooking->bind_param(
            "iisssisssi",
            $order_id,
            $schedule_id,
            $schedule['delivery_type'],
            $tracking_number,
            $courier_name,
            $schedule['assigned_vehicle_id'],
            $booking_reference,
            $estimated_pickup,
            $booking_notes,
            $user_id
        );
        
        if (!$insertBooking->execute()) {
            throw new Exception("Failed to create booking");
        }
        $booking_id = $conn->insert_id;
        $insertBooking->close();
        
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
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <a href="delivery_date_orders.php?date=<?php echo $schedule['delivery_date']; ?>" 
               class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Orders
            </a>
            
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-calendar-check text-blue-600 mr-3"></i>
                Book <?php echo ucfirst($schedule['delivery_type']); ?>
            </h1>
            <p class="text-gray-600 mt-2">Order #<?php echo $order_id; ?> - <?php echo date('F d, Y', strtotime($schedule['delivery_date'])); ?></p>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Booking Form -->
            <div class="lg:col-span-2">
                <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <input type="hidden" name="action" value="create_booking">
                    
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-file-alt text-purple-600 mr-2"></i>
                        Booking Information
                    </h3>
                    
                    <!-- Tracking Number -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-barcode mr-1"></i>
                            Tracking Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="tracking_number" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter tracking number from courier">
                    </div>
                    
                    <!-- Courier Name (if not from vehicle list) -->
<?php if (!$schedule['courier_name']): ?>
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fas fa-truck mr-1"></i>
        Courier Service Name <span class="text-red-500">*</span>
    </label>
                        <input type="text" 
                               name="courier_name" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter courier service name">
                    </div>
                    <?php endif; ?>
                    
                    <!-- Booking Reference -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1"></i>
                            Booking Reference (Optional)
                        </label>
                        <input type="text" 
                               name="booking_reference"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Courier's booking reference number">
                    </div>
                    
                    <!-- Estimated Pickup Time -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1"></i>
                            Estimated Pickup/Delivery Time
                        </label>
                        <input type="datetime-local" 
                               name="estimated_pickup_time"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1"></i>
                            Booking Notes (Optional)
                        </label>
                        <textarea name="booking_notes"
                                  rows="4"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Any additional notes about the booking..."></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex gap-4">
                        <button type="submit" 
                                class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105 font-semibold">
                            <i class="fas fa-check-circle mr-2"></i>
                            Confirm Booking
                        </button>
                        <a href="delivery_date_orders.php?date=<?php echo $schedule['delivery_date']; ?>" 
                           class="px-6 py-4 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Order Summary Sidebar -->
            <div class="space-y-6">
                
                <!-- Order Info -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        Order Summary
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Order ID:</span>
                            <span class="font-semibold">#<?php echo $order_id; ?></span>
                        </div>
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Total Items:</span>
                            <span class="font-semibold"><?php echo $schedule['total_items']; ?></span>
                        </div>
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Total Quantity:</span>
                            <span class="font-semibold"><?php echo $schedule['total_quantity']; ?></span>
                        </div>
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Order Total:</span>
                            <span class="font-semibold">₱<?php echo number_format($schedule['final_total'], 2); ?></span>
                        </div>
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Type:</span>
                            <span class="font-semibold capitalize"><?php echo $schedule['delivery_type']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Scheduled:</span>
                            <span class="font-semibold">
                                <?php echo date('M d, Y g:i A', strtotime($schedule['delivery_date'] . ' ' . $schedule['delivery_time'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Info -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-user text-green-600 mr-2"></i>
                        Customer Details
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="text-gray-600 block mb-1">Name:</span>
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($schedule['customer_name']); ?></span>
                        </div>
                        <?php if ($schedule['mobile']): ?>
                        <div>
                            <span class="text-gray-600 block mb-1">Mobile:</span>
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($schedule['mobile']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-gray-600 block mb-1">Address:</span>
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($schedule['address']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Vehicle Info (if assigned) -->
                <?php if ($schedule['assigned_vehicle_id']): ?>
                <div class="bg-purple-50 rounded-xl shadow-sm border border-purple-200 p-6">
                    <h3 class="text-lg font-bold text-purple-900 mb-4 flex items-center">
                        <i class="fas fa-truck text-purple-600 mr-2"></i>
                        Vehicle Information
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="text-purple-700 block mb-1">Vehicle Type:</span>
                            <span class="font-semibold text-purple-900"><?php echo htmlspecialchars($schedule['vehicle_type']); ?></span>
                        </div>
                        <div>
    <span class="text-purple-700 block mb-1">Courier Service:</span>
    <span class="font-semibold text-purple-900"><?php echo htmlspecialchars($schedule['courier_name']); ?></span>
</div>
<?php if ($schedule['max_weight_capacity']): ?>
<div>
    <span class="text-purple-700 block mb-1">Max Weight:</span>
    <span class="font-semibold text-purple-900"><?php echo $schedule['max_weight_capacity']; ?> kg</span>
</div>
<?php endif; ?>
<?php if ($schedule['max_cubic_meter']): ?>
<div>
    <span class="text-purple-700 block mb-1">Max Volume:</span>
    <span class="font-semibold text-purple-900"><?php echo $schedule['max_cubic_meter']; ?> m³</span>
</div>
<?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Items List -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-boxes text-orange-600 mr-2"></i>
                        Order Items (<?php echo count($items); ?>)
                    </h3>
                    
                    <div class="max-h-96 overflow-y-auto space-y-3">
                        <?php foreach ($items as $item): ?>
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <div class="font-semibold text-gray-900 mb-1">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </div>
                            <div class="text-sm text-gray-600 space-y-1">
                                <?php if ($item['variant_color']): ?>
                                <div>Color: <?php echo htmlspecialchars($item['variant_color']); ?></div>
                                <?php endif; ?>
                                <?php if ($item['size']): ?>
                                <div>Size: <?php echo htmlspecialchars($item['size']); ?></div>
                                <?php endif; ?>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="font-medium">Qty: <?php echo $item['quantity']; ?></span>
                                    <span class="font-medium text-blue-600">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>