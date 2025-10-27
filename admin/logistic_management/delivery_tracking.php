<?php
// delivery_tracking.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Get booking details
$sql = "SELECT 
    db.*,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivery_type,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.final_total,
    o.status as order_status,
    tv.vehicle_type,
    tv.courier_name,
    dispatcher.fullname as dispatcher_name,
    dispatcher.email as dispatcher_email,
    (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id) as total_items,
    (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id AND tracking_status = 'ready_for_pickup') as ready_items,
    (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id AND tracking_status = 'item_is_loaded') as loaded_items
FROM delivery_bookings db
INNER JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
INNER JOIN orders o ON db.order_id = o.id
LEFT JOIN transportify_vehicle_list tv ON db.vehicle_id = tv.id
LEFT JOIN nobleaccount dispatcher ON db.dispatcher_id = dispatcher.id
WHERE db.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Get order items with their status
$itemsSql = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $booking['order_id']);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Get available warehouse dispatchers
$dispatchersSql = "SELECT 
    id, 
    fullname, 
    email,
    (SELECT COUNT(*) FROM delivery_bookings WHERE dispatcher_id = nobleaccount.id AND booking_status NOT IN ('delivered', 'picked_up', 'cancelled')) as active_bookings
FROM nobleaccount 
WHERE lvl = 'logistic' 
AND subrole = 'dispatcher' 
AND status = 'active'
ORDER BY active_bookings ASC, fullname ASC";
$dispatchersResult = $conn->query($dispatchersSql);
$dispatchers = $dispatchersResult->fetch_all(MYSQLI_ASSOC);

// Handle item status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'assign_dispatcher') {
        $dispatcher_id = !empty($_POST['dispatcher_id']) ? intval($_POST['dispatcher_id']) : null;
        
        $updateDispatcher = $conn->prepare("UPDATE delivery_bookings SET dispatcher_id = ? WHERE id = ?");
        $updateDispatcher->bind_param("ii", $dispatcher_id, $booking_id);
        
        if ($updateDispatcher->execute()) {
            if ($dispatcher_id) {
                $_SESSION['success_message'] = "Dispatcher assigned successfully!";
            } else {
                $_SESSION['success_message'] = "Dispatcher unassigned successfully!";
            }
        } else {
            $_SESSION['error_message'] = "Failed to assign dispatcher";
        }
        $updateDispatcher->close();
        
        header("Location: delivery_tracking.php?booking_id=" . $booking_id);
        exit();
    }
    
    if ($_POST['action'] === 'toggle_item_loaded') {
        $item_id = intval($_POST['item_id']);
        $current_status = $_POST['current_status'];
        
        $new_status = ($current_status === 'ready_for_pickup') ? 'item_is_loaded' : 'ready_for_pickup';
        
        $updateItem = $conn->prepare("UPDATE order_items SET tracking_status = ? WHERE id = ?");
        $updateItem->bind_param("si", $new_status, $item_id);
        
        if ($updateItem->execute()) {
            $_SESSION['success_message'] = "Item status updated!";
        }
        $updateItem->close();
        
        header("Location: delivery_tracking.php?booking_id=" . $booking_id);
        exit();
    }
    
    if ($_POST['action'] === 'mark_all_loaded') {
        $updateAll = $conn->prepare("UPDATE order_items SET tracking_status = 'item_is_loaded' WHERE order_id = ?");
        $updateAll->bind_param("i", $booking['order_id']);
        
        if ($updateAll->execute()) {
            $_SESSION['success_message'] = "All items marked as loaded!";
        }
        $updateAll->close();
        
        header("Location: delivery_tracking.php?booking_id=" . $booking_id);
        exit();
    }
    
    if ($_POST['action'] === 'update_status') {
        $new_booking_status = $_POST['booking_status'];
        $actual_pickup = !empty($_POST['actual_pickup_time']) ? $_POST['actual_pickup_time'] : null;
        $actual_delivery = !empty($_POST['actual_delivery_time']) ? $_POST['actual_delivery_time'] : null;
        
        $conn->begin_transaction();
        
        try {
            // Update booking
            $updateBooking = $conn->prepare("
                UPDATE delivery_bookings 
                SET booking_status = ?,
                    actual_pickup_time = ?,
                    actual_delivery_time = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateBooking->bind_param("sssi", $new_booking_status, $actual_pickup, $actual_delivery, $booking_id);
            $updateBooking->execute();
            $updateBooking->close();
            
            // Update order status based on booking status
            $order_status = 'Ready for Pickup';
            if ($new_booking_status === 'in_transit') {
                $order_status = 'Out for Delivery';
            } elseif ($new_booking_status === 'delivered') {
                $order_status = 'Delivered';
            } elseif ($new_booking_status === 'picked_up') {
                $order_status = 'Picked Up';
            }
            
            $updateOrder = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $updateOrder->bind_param("si", $order_status, $booking['order_id']);
            $updateOrder->execute();
            $updateOrder->close();
            
            // Update delivery schedule
            $ds_status = 'booked';
            if ($new_booking_status === 'in_transit') {
                $ds_status = 'out_for_delivery';
            } elseif ($new_booking_status === 'delivered') {
                $ds_status = 'delivered';
            } elseif ($new_booking_status === 'picked_up') {
                $ds_status = 'picked_up';
            }
            
            $updateDS = $conn->prepare("UPDATE delivery_schedules SET delivery_status = ? WHERE id = ?");
            $updateDS->bind_param("si", $ds_status, $booking['delivery_schedule_id']);
            $updateDS->execute();
            $updateDS->close();
            
            $conn->commit();
            $_SESSION['success_message'] = "Status updated successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error updating status: " . $e->getMessage();
        }
        
        header("Location: delivery_tracking.php?booking_id=" . $booking_id);
        exit();
    }
    
    if ($_POST['action'] === 'upload_proof') {
        if (isset($_FILES['delivery_proof']) && $_FILES['delivery_proof']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/delivery_proofs/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['delivery_proof'];
            $filename = 'proof_' . $booking_id . '_' . time();
            $webp_filename = $filename . '.webp';
            $webp_path = $upload_dir . $webp_filename;
            
            // Convert to WebP
            $image_info = getimagesize($file['tmp_name']);
            $mime_type = $image_info['mime'];
            
            switch ($mime_type) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($file['tmp_name']);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($file['tmp_name']);
                    break;
                default:
                    $_SESSION['error_message'] = "Unsupported image format";
                    header("Location: delivery_tracking.php?booking_id=" . $booking_id);
                    exit();
            }
            
            // Save as WebP
            if (imagewebp($image, $webp_path, 85)) {
                imagedestroy($image);
                
                // Update booking with proof
                $updateProof = $conn->prepare("
                    UPDATE delivery_bookings 
                    SET delivery_proof_image = ?,
                        booking_status = CASE 
                            WHEN booking_type = 'pickup' THEN 'picked_up'
                            ELSE 'delivered'
                        END,
                        actual_delivery_time = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updateProof->bind_param("si", $webp_filename, $booking_id);
                
                if ($updateProof->execute()) {
                    // Update order status
                    $final_status = ($booking['booking_type'] === 'pickup') ? 'Picked Up' : 'Delivered';
                    $updateOrder = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                    $updateOrder->bind_param("si", $final_status, $booking['order_id']);
                    $updateOrder->execute();
                    $updateOrder->close();
                    
                    // Update all items to final status
                    $item_status = ($booking['booking_type'] === 'pickup') ? 'picked_up' : 'delivered';
                    $updateItems = $conn->prepare("UPDATE order_items SET tracking_status = ? WHERE order_id = ?");
                    $updateItems->bind_param("si", $item_status, $booking['order_id']);
                    $updateItems->execute();
                    $updateItems->close();
                    
                    $_SESSION['success_message'] = "Delivery proof uploaded successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to update booking with proof";
                }
                $updateProof->close();
            } else {
                imagedestroy($image);
                $_SESSION['error_message'] = "Failed to convert image to WebP";
            }
        } else {
            $_SESSION['error_message'] = "No file uploaded or upload error";
        }
        
        header("Location: delivery_tracking.php?booking_id=" . $booking_id);
        exit();
    }
}

$isCompleted = in_array($booking['booking_status'], ['delivered', 'picked_up']);
$canMarkLoaded = $booking['ready_items'] > 0;
$allLoaded = $booking['loaded_items'] === $booking['total_items'] && $booking['total_items'] > 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo ucfirst($booking['booking_type']); ?> - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .item-card {
            transition: all 0.3s ease;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <a href="delivery_date_orders.php?date=<?php echo $booking['delivery_date']; ?>" 
               class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Orders
            </a>
            
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-tasks text-purple-600 mr-3"></i>
                Manage <?php echo ucfirst($booking['booking_type']); ?>
            </h1>
            <p class="text-gray-600 mt-2">Order #<?php echo $booking['order_id']; ?> - Booking #<?php echo $booking_id; ?></p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']);
            ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Booking Info Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        Booking Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4">
                            <span class="text-sm text-blue-700">Tracking Number</span>
                            <p class="font-mono font-bold text-lg text-blue-900"><?php echo htmlspecialchars($booking['tracking_number']); ?></p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4">
                            <span class="text-sm text-purple-700">Courier</span>
                            <p class="font-semibold text-lg text-purple-900"><?php echo htmlspecialchars($booking['courier_name']); ?></p>
                        </div>
                        <?php if ($booking['booking_reference']): ?>
                        <div class="bg-green-50 rounded-lg p-4">
                            <span class="text-sm text-green-700">Booking Reference</span>
                            <p class="font-semibold text-green-900"><?php echo htmlspecialchars($booking['booking_reference']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($booking['booking_type'] === 'pickup' && ($booking['pickup_person_name'] || $booking['pickup_person_contact'])): ?>
                            <?php if ($booking['pickup_person_name']): ?>
                            <div class="bg-indigo-50 rounded-lg p-4">
                                <span class="text-sm text-indigo-700">Pickup Person</span>
                                <p class="font-semibold text-lg text-indigo-900"><?php echo htmlspecialchars($booking['pickup_person_name']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['pickup_person_contact']): ?>
                            <div class="bg-indigo-50 rounded-lg p-4">
                                <span class="text-sm text-indigo-700">Contact Number</span>
                                <p class="font-semibold text-lg text-indigo-900"><?php echo htmlspecialchars($booking['pickup_person_contact']); ?></p>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($booking['booking_type'] === 'delivery' && $booking['driver_name']): ?>
                        <div class="bg-amber-50 rounded-lg p-4">
                            <span class="text-sm text-amber-700">Driver Name</span>
                            <p class="font-semibold text-lg text-amber-900"><?php echo htmlspecialchars($booking['driver_name']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($booking['vehicle_plate_number']): ?>
                        <div class="bg-orange-50 rounded-lg p-4">
                            <span class="text-sm text-orange-700">Vehicle Plate Number</span>
                            <p class="font-mono font-bold text-lg text-orange-900"><?php echo htmlspecialchars($booking['vehicle_plate_number']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Dispatcher Assignment Section -->
                    <div class="mt-6 border-t pt-6">
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-user-tie text-indigo-600 mr-2"></i>
                            Assigned Dispatcher
                        </h4>
                        
                        <?php if (!$isCompleted): ?>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="assign_dispatcher">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Select Dispatcher
                                </label>
                                <select name="dispatcher_id" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                        onchange="this.form.submit()">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($dispatchers as $dispatcher): ?>
                                    <option value="<?php echo $dispatcher['id']; ?>" 
                                            <?php echo $booking['dispatcher_id'] == $dispatcher['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dispatcher['fullname']); ?> 
                                        (<?php echo $dispatcher['active_bookings']; ?> active)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Shows number of active bookings per dispatcher</p>
                            </div>
                        </form>
                        <?php else: ?>
                            <?php if ($booking['dispatcher_name']): ?>
                            <div class="bg-indigo-50 rounded-lg p-4">
                                <span class="text-sm text-indigo-700">Dispatcher</span>
                                <p class="font-semibold text-lg text-indigo-900"><?php echo htmlspecialchars($booking['dispatcher_name']); ?></p>
                                <p class="text-sm text-indigo-600"><?php echo htmlspecialchars($booking['dispatcher_email']); ?></p>
                            </div>
                            <?php else: ?>
                            <p class="text-gray-500 italic">No dispatcher assigned</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Status Update Form -->
                    <?php if (!$isCompleted): ?>
                    <form method="POST" class="mt-6 border-t pt-6">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Booking Status
                                </label>
                                <select name="booking_status" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="confirmed" <?php echo $booking['booking_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="in_transit" <?php echo $booking['booking_status'] === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Actual Pickup Time
                                </label>
                                <input type="datetime-local" 
                                       name="actual_pickup_time"
                                       value="<?php echo $booking['actual_pickup_time'] ? date('Y-m-d\TH:i', strtotime($booking['actual_pickup_time'])) : ''; ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all shadow-md hover:shadow-lg font-semibold">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Update Status
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <!-- Delivery Proof Upload -->
                    <?php if ($allLoaded && !$booking['delivery_proof_image']): ?>
                    <form method="POST" enctype="multipart/form-data" class="mt-6 border-t pt-6">
                        <input type="hidden" name="action" value="upload_proof">
                        
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-camera text-green-600 mr-2"></i>
                            Upload Delivery Proof
                        </h4>
                        
                        <div class="mb-4">
                            <input type="file" 
                                   name="delivery_proof" 
                                   accept="image/*"
                                   required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                            <p class="text-xs text-gray-500 mt-1">Image will be converted to WebP format</p>
                        </div>
                        
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition-all shadow-md hover:shadow-lg font-semibold">
                            <i class="fas fa-upload mr-2"></i>
                            Upload Proof & Complete <?php echo ucfirst($booking['booking_type']); ?>
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <!-- Show Delivery Proof -->
                    <?php if ($booking['delivery_proof_image']): ?>
                    <div class="mt-6 border-t pt-6">
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                            Delivery Proof
                        </h4>
                        <img src="../../uploads/delivery_proofs/<?php echo htmlspecialchars($booking['delivery_proof_image']); ?>" 
                             alt="Delivery Proof"
                             class="w-full rounded-lg shadow-lg">
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Items Management -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-boxes text-orange-600 mr-2"></i>
                            Items Loading Status
                        </h3>
                        
                        <?php if ($canMarkLoaded && !$allLoaded && !$isCompleted): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="mark_all_loaded">
                            <button type="submit" 
                                    class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors text-sm font-semibold">
                                <i class="fas fa-check-double mr-1"></i>
                                Mark All Loaded
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mb-6">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Loading Progress</span>
                            <span class="font-semibold"><?php echo $booking['loaded_items']; ?> / <?php echo $booking['total_items']; ?> items loaded</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div class="bg-gradient-to-r from-blue-500 to-green-500 h-4 rounded-full transition-all" 
                                 style="width: <?php echo $booking['total_items'] > 0 ? ($booking['loaded_items'] / $booking['total_items']) * 100 : 0; ?>%">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Items List -->
                    <div class="space-y-3">
                        <?php foreach ($items as $item): ?>
                            <?php 
                            $isLoaded = $item['tracking_status'] === 'item_is_loaded';
                            $isReady = $item['tracking_status'] === 'ready_for_pickup';
                            $isFinal = in_array($item['tracking_status'], ['delivered', 'picked_up']);
                            ?>
                            <div class="item-card flex items-center justify-between p-4 rounded-lg border-2 <?php 
                                echo $isFinal ? 'bg-green-50 border-green-300' : 
                                     ($isLoaded ? 'bg-blue-50 border-blue-300' : 'bg-yellow-50 border-yellow-300'); 
                            ?>">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <?php if ($item['variant_color']): ?>
                                        <span class="mr-3">Color: <?php echo htmlspecialchars($item['variant_color']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($item['size']): ?>
                                        <span class="mr-3">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                        <?php endif; ?>
                                        <span class="font-medium">Qty: <?php echo $item['quantity']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <?php if ($isFinal): ?>
                                        <span class="bg-green-500 text-white px-4 py-2 rounded-lg font-semibold">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $item['tracking_status'])); ?>
                                        </span>
                                    <?php elseif (!$isCompleted): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_item_loaded">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $item['tracking_status']; ?>">
                                            <button type="submit" 
                                                    class="<?php echo $isLoaded ? 'bg-blue-500 hover:bg-blue-600' : 'bg-yellow-500 hover:bg-yellow-600'; ?> text-white px-4 py-2 rounded-lg transition-colors font-semibold">
                                                <i class="fas <?php echo $isLoaded ? 'fa-check' : 'fa-box'; ?> mr-1"></i>
                                                <?php echo $isLoaded ? 'Loaded' : 'Ready'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="<?php echo $isLoaded ? 'bg-blue-500' : 'bg-yellow-500'; ?> text-white px-4 py-2 rounded-lg font-semibold">
                                            <i class="fas <?php echo $isLoaded ? 'fa-check' : 'fa-clock'; ?> mr-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $item['tracking_status'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-6">
                
                <!-- Status Summary -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-chart-pie text-purple-600 mr-2"></i>
                        Status Summary
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                            <span class="text-sm text-gray-700">Booking Status</span>
                            <span class="font-semibold text-purple-900 capitalize">
                                <?php echo str_replace('_', ' ', $booking['booking_status']); ?>
                            </span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <span class="text-sm text-gray-700">Order Status</span>
                            <span class="font-semibold text-blue-900"><?php echo $booking['order_status']; ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                            <span class="text-sm text-gray-700"><?php echo ucfirst($booking['booking_type']); ?> Type</span>
                            <span class="font-semibold text-green-900 capitalize"><?php echo $booking['booking_type']; ?></span>
                        </div>
                        
                        <?php if ($booking['dispatcher_name']): ?>
                        <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
                            <span class="text-sm text-gray-700">Dispatcher</span>
                            <span class="font-semibold text-indigo-900"><?php echo htmlspecialchars($booking['dispatcher_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-clock text-blue-600 mr-2"></i>
                        Timeline
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Booking Created -->
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-white text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900">Booking Created</p>
                                <p class="text-sm text-gray-600"><?php echo date('M d, Y g:i A', strtotime($booking['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <!-- Estimated Pickup -->
                        <?php if ($booking['estimated_pickup_time']): ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar text-white text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900">Estimated Pickup</p>
                                <p class="text-sm text-gray-600"><?php echo date('M d, Y g:i A', strtotime($booking['estimated_pickup_time'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actual Pickup -->
                        <?php if ($booking['actual_pickup_time']): ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-white text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900">Actual Pickup</p>
                                <p class="text-sm text-gray-600"><?php echo date('M d, Y g:i A', strtotime($booking['actual_pickup_time'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Estimated Delivery -->
                        <?php if ($booking['estimated_delivery_time']): ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar text-white text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900">Estimated Delivery</p>
                                <p class="text-sm text-gray-600"><?php echo date('M d, Y g:i A', strtotime($booking['estimated_delivery_time'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actual Delivery -->
                        <?php if ($booking['actual_delivery_time']): ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-white text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900"><?php echo $booking['booking_type'] === 'pickup' ? 'Picked Up' : 'Delivered'; ?></p>
                                <p class="text-sm text-gray-600"><?php echo date('M d, Y g:i A', strtotime($booking['actual_delivery_time'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
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
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                        </div>
                        <?php if ($booking['mobile']): ?>
                        <div>
                            <span class="text-gray-600 block mb-1">Mobile:</span>
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($booking['mobile']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-gray-600 block mb-1">Address:</span>
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($booking['address']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-receipt text-orange-600 mr-2"></i>
                        Order Summary
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Order ID:</span>
                            <span class="font-semibold">#<?php echo $booking['order_id']; ?></span>
                        </div>
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Scheduled Date:</span>
                            <span class="font-semibold"><?php echo date('M d, Y', strtotime($booking['delivery_date'])); ?></span>
                        </div>
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Scheduled Time:</span>
                            <span class="font-semibold"><?php echo date('g:i A', strtotime($booking['delivery_time'])); ?></span>
                        </div>
                        <div class="flex justify-between pb-2 border-b">
                            <span class="text-gray-600">Total Items:</span>
                            <span class="font-semibold"><?php echo $booking['total_items']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Order Total:</span>
                            <span class="font-semibold text-lg">₱<?php echo number_format($booking['final_total'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($booking['booking_notes']): ?>
                <!-- Notes -->
                <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-6">
                    <h3 class="text-lg font-bold text-yellow-900 mb-3 flex items-center">
                        <i class="fas fa-sticky-note text-yellow-600 mr-2"></i>
                        Booking Notes
                    </h3>
                    <p class="text-sm text-yellow-800"><?php echo nl2br(htmlspecialchars($booking['booking_notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($booking['delivery_notes']): ?>
                <!-- Delivery Notes -->
                <div class="bg-orange-50 rounded-xl border border-orange-200 p-6">
                    <h3 class="text-lg font-bold text-orange-900 mb-3 flex items-center">
                        <i class="fas fa-clipboard text-orange-600 mr-2"></i>
                        Delivery Notes
                    </h3>
                    <p class="text-sm text-orange-800"><?php echo nl2br(htmlspecialchars($booking['delivery_notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>