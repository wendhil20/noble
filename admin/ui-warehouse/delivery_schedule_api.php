<?php
// delivery_schedule.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$origin = isset($_GET['origin']) ? $_GET['origin'] : '';
$replacement_id = isset($_GET['replacement_id']) ? (int)$_GET['replacement_id'] : 0;

$is_replacement = ($origin === 'replacement' || $replacement_id > 0);

if ($order_id <= 0 || $item_id <= 0) {
    header("Location: order_list.php");
    exit();
}

// Get order details
$orderSql = "SELECT * FROM orders WHERE id = ? LIMIT 1";
$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: order_list.php");
    exit();
}

// Get item details (original or replacement)
if ($is_replacement && $replacement_id > 0) {
    // Get replacement request details
    $itemSql = "
        SELECT 
            rr.id as replacement_id,
            rr.order_id,
            rr.order_item_id,
            rr.replacement_quantity as quantity,
            rr.reason,
            rr.status,
            oi.id as item_id,
            oi.product_name,
            oi.price,
            oi.origin,
            COALESCE(sl.business_name, oi.manual_supplier_name) as supplier_name
        FROM replacement_requests rr
        LEFT JOIN order_items oi ON rr.order_item_id = oi.id
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
        WHERE rr.id = ? AND rr.order_id = ?
        LIMIT 1
    ";
    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->bind_param("ii", $replacement_id, $order_id);
} else {
    // Get original item details
    $itemSql = "
        SELECT 
            NULL as replacement_id,
            oi.order_id,
            oi.id as order_item_id,
            oi.quantity,
            NULL as reason,
            oi.tracking_status as status,
            oi.id as item_id,
            oi.product_name,
            oi.price,
            oi.origin,
            COALESCE(sl.business_name, oi.manual_supplier_name) as supplier_name
        FROM order_items oi
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
        WHERE oi.id = ? AND oi.order_id = ?
        LIMIT 1
    ";
    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->bind_param("ii", $item_id, $order_id);
}

$itemStmt->execute();
$item = $itemStmt->get_result()->fetch_assoc();
$itemStmt->close();

if (!$item) {
    header("Location: order_tracking.php?order_id=" . $order_id);
    exit();
}

// Check for existing delivery schedule
$existingScheduleSql = "
    SELECT ds.*, ts.truck_plate_number, ts.driver_name 
    FROM delivery_schedules ds
    LEFT JOIN truck_schedules ts ON ds.truck_schedule_id = ts.id
    WHERE ds.item_id = ? AND ds.order_id = ?
    ORDER BY ds.created_at DESC 
    LIMIT 1
";
$existingStmt = $conn->prepare($existingScheduleSql);
$existingStmt->bind_param("ii", $item['item_id'], $order_id);
$existingStmt->execute();
$existingSchedule = $existingStmt->get_result()->fetch_assoc();
$existingStmt->close();

// Get available trucks
$trucksSql = "
    SELECT ts.*, 
           COUNT(ds.id) as scheduled_deliveries,
           GROUP_CONCAT(CONCAT(o.customer_name, ' (', TIME(ds.delivery_time), ')') SEPARATOR ', ') as existing_deliveries
    FROM truck_schedules ts
    LEFT JOIN delivery_schedules ds ON ts.id = ds.truck_schedule_id 
        AND DATE(ds.delivery_date) = DATE(NOW()) 
        AND ds.delivery_status NOT IN ('delivered', 'cancelled')
    LEFT JOIN orders o ON ds.order_id = o.id
    WHERE ts.status = 'active'
    GROUP BY ts.id, ts.truck_plate_number, ts.driver_name, ts.capacity, ts.status
    ORDER BY scheduled_deliveries ASC, ts.truck_plate_number
";
$trucksResult = $conn->query($trucksSql);
$availableTrucks = $trucksResult ? $trucksResult->fetch_all(MYSQLI_ASSOC) : [];

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_delivery'])) {
    $delivery_date = $_POST['delivery_date'];
    $delivery_time = $_POST['delivery_time'];
    $delivery_type = $_POST['delivery_type'];
    $delivery_notes = $_POST['delivery_notes'];
    $truck_schedule_id = ($delivery_type === 'company') ? (int)$_POST['truck_schedule_id'] : null;
    $created_by = $_SESSION['noble_user']['email'] ?? $_SESSION['noble_user']['id'] ?? '';

    try {
        $conn->begin_transaction();

        if ($existingSchedule) {
            // Update existing schedule
            $updateSql = "
                UPDATE delivery_schedules SET 
                    delivery_date = ?, 
                    delivery_time = ?, 
                    delivery_type = ?, 
                    delivery_notes = ?,
                    truck_schedule_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssssii", $delivery_date, $delivery_time, $delivery_type, $delivery_notes, $truck_schedule_id, $existingSchedule['id']);
            
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update delivery schedule");
            }
            $updateStmt->close();
            
            $schedule_id = $existingSchedule['id'];
            $action = "updated";
        } else {
            // Create new schedule
            $insertSql = "
                INSERT INTO delivery_schedules 
                (order_id, item_id, delivery_date, delivery_time, delivery_type, delivery_notes, truck_schedule_id, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iissssiss", $order_id, $item['item_id'], $delivery_date, $delivery_time, $delivery_type, $delivery_notes, $truck_schedule_id, $created_by);
            
            if (!$insertStmt->execute()) {
                throw new Exception("Failed to create delivery schedule");
            }
            $insertStmt->close();
            
            $schedule_id = $conn->insert_id;
            $action = "scheduled";
        }

        // Update item status based on type
        if ($is_replacement && $replacement_id > 0) {
            // Update replacement request status
            $statusUpdateSql = "UPDATE replacement_requests SET status = 'ready_for_pickup' WHERE id = ?";
            $statusStmt = $conn->prepare($statusUpdateSql);
            $statusStmt->bind_param("i", $replacement_id);
        } else {
            // Update original item status  
            $statusUpdateSql = "UPDATE order_items SET tracking_status = 'ready_for_pickup' WHERE id = ?";
            $statusStmt = $conn->prepare($statusUpdateSql);
            $statusStmt->bind_param("i", $item['item_id']);
        }
        
        if (!$statusStmt->execute()) {
            throw new Exception("Failed to update item status");
        }
        $statusStmt->close();

        // If company delivery, update truck info in delivery_schedules
        if ($delivery_type === 'company' && $truck_schedule_id) {
            $truckInfoSql = "
                UPDATE delivery_schedules ds
                JOIN truck_schedules ts ON ds.truck_schedule_id = ts.id
                SET ds.assigned_truck = ts.truck_plate_number,
                    ds.assigned_driver_id = NULL
                WHERE ds.id = ?
            ";
            $truckStmt = $conn->prepare($truckInfoSql);
            $truckStmt->bind_param("i", $schedule_id);
            $truckStmt->execute();
            $truckStmt->close();
        }

        $conn->commit();
        $success_message = "Delivery " . $action . " successfully!";
        
        // Refresh the existing schedule data
        $existingStmt = $conn->prepare($existingScheduleSql);
        $existingStmt->bind_param("ii", $item['item_id'], $order_id);
        $existingStmt->execute();
        $existingSchedule = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Scheduling - <?php echo $is_replacement ? 'Replacement' : 'Item'; ?> #<?php echo $item['item_id']; ?></title>
    <style>
        .replacement-gradient {
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
            border-left: 4px solid #ef4444;
        }
        
        .pulse-replacement {
            animation: pulse-red 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse-red {
            0%, 100% { opacity: 1; }
            50% { opacity: .7; }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-4 sm:py-6 space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <a href="order_tracking.php?order_id=<?php echo $order_id; ?>" 
                       class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-arrow-left text-gray-600"></i>
                    </a>
                    <div class="bg-gradient-to-r <?php echo $is_replacement ? 'from-red-500 to-red-600' : 'from-blue-500 to-blue-600'; ?> p-3 rounded-xl shadow-lg">
                        <i class="fas <?php echo $is_replacement ? 'fa-exchange-alt' : 'fa-calendar-alt'; ?> text-white text-xl sm:text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
                            <?php echo $is_replacement ? 'Replacement' : 'Item'; ?> Delivery Scheduling
                        </h1>
                        <p class="text-gray-600 mt-1 text-sm sm:text-base">
                            Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($order['customer_name']); ?>
                            <?php if ($is_replacement): ?>
                                <span class="ml-2 bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>REPLACEMENT
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8">
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="text-green-800"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                    <span class="text-red-800"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Item Information -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden <?php echo $is_replacement ? 'replacement-gradient' : ''; ?>">
            <div class="px-6 py-4 border-b border-gray-200 <?php echo $is_replacement ? 'bg-red-50' : 'bg-gray-50'; ?>">
                <h2 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas <?php echo $is_replacement ? 'fa-exchange-alt text-red-600' : 'fa-box text-blue-600'; ?> mr-2"></i>
                    <?php echo $is_replacement ? 'Replacement' : 'Item'; ?> Information
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Product Name</label>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($item['product_name']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Supplier</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($item['supplier_name'] ?? 'Unknown'); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Origin</label>
                            <p class="text-gray-900 capitalize">
                                <i class="fas <?php echo ($item['origin'] === 'local') ? 'fa-home text-green-600' : 'fa-globe text-blue-600'; ?> mr-1"></i>
                                <?php echo $item['origin'] ?? 'Local'; ?>
                            </p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Quantity</label>
                            <p class="text-gray-900 font-medium"><?php echo $item['quantity']; ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Unit Price</label>
                            <p class="text-gray-900 font-medium">₱<?php echo number_format((float)$item['price'], 2); ?></p>
                        </div>
                        <?php if ($is_replacement && !empty($item['reason'])): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Replacement Reason</label>
                                <p class="text-red-700 capitalize font-medium"><?php echo htmlspecialchars($item['reason']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Schedule (if any) -->
        <?php if ($existingSchedule): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    Current Schedule
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-blue-700">Date:</span>
                        <span class="text-blue-900 ml-2"><?php echo date('M j, Y', strtotime($existingSchedule['delivery_date'])); ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-blue-700">Time:</span>
                        <span class="text-blue-900 ml-2"><?php echo date('g:i A', strtotime($existingSchedule['delivery_time'])); ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-blue-700">Type:</span>
                        <span class="text-blue-900 ml-2 capitalize"><?php echo $existingSchedule['delivery_type']; ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-blue-700">Status:</span>
                        <span class="text-blue-900 ml-2 capitalize"><?php echo $existingSchedule['delivery_status'] ?? 'scheduled'; ?></span>
                    </div>
                    <?php if ($existingSchedule['assigned_truck']): ?>
                        <div>
                            <span class="font-medium text-blue-700">Truck:</span>
                            <span class="text-blue-900 ml-2"><?php echo htmlspecialchars($existingSchedule['assigned_truck']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($existingSchedule['delivery_notes'])): ?>
                        <div class="md:col-span-2">
                            <span class="font-medium text-blue-700">Notes:</span>
                            <span class="text-blue-900 ml-2"><?php echo htmlspecialchars($existingSchedule['delivery_notes']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Scheduling Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-calendar-plus mr-2 text-primary-600"></i>
                    <?php echo $existingSchedule ? 'Update' : 'Schedule'; ?> Delivery
                </h2>
            </div>
            
            <form method="POST" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Delivery Date -->
                    <div>
                        <label for="delivery_date" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1 text-gray-500"></i>
                            Delivery Date *
                        </label>
                        <input type="date" id="delivery_date" name="delivery_date" 
                               value="<?php echo $existingSchedule['delivery_date'] ?? date('Y-m-d', strtotime('+1 day')); ?>"
                               min="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               required>
                    </div>

                    <!-- Delivery Time -->
                    <div>
                        <label for="delivery_time" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1 text-gray-500"></i>
                            Delivery Time *
                        </label>
                        <input type="time" id="delivery_time" name="delivery_time" 
                               value="<?php echo $existingSchedule['delivery_time'] ?? '09:00'; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               required>
                    </div>

                    <!-- Delivery Type -->
                    <div>
                        <label for="delivery_type" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-truck mr-1 text-gray-500"></i>
                            Delivery Type *
                        </label>
                        <select id="delivery_type" name="delivery_type" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                onchange="toggleTruckSelection()" required>
                            <option value="">Select delivery type</option>
                            <option value="company" <?php echo ($existingSchedule['delivery_type'] ?? '') === 'company' ? 'selected' : ''; ?>>
                                Company Vehicle
                            </option>
                            <option value="third_party" <?php echo ($existingSchedule['delivery_type'] ?? '') === 'third_party' ? 'selected' : ''; ?>>
                                Third Party
                            </option>
                        </select>
                    </div>

                    <!-- Truck Selection (shown only for company delivery) -->
                    <div id="truck_selection" style="display: <?php echo ($existingSchedule['delivery_type'] ?? '') === 'company' ? 'block' : 'none'; ?>;">
                        <label for="truck_schedule_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-truck-moving mr-1 text-gray-500"></i>
                            Select Truck *
                        </label>
                        <select id="truck_schedule_id" name="truck_schedule_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">Select a truck</option>
                            <?php foreach ($availableTrucks as $truck): ?>
                                <option value="<?php echo $truck['id']; ?>" 
                                        <?php echo ($existingSchedule['truck_schedule_id'] ?? 0) == $truck['id'] ? 'selected' : ''; ?>
                                        data-deliveries="<?php echo $truck['scheduled_deliveries']; ?>">
                                    <?php echo htmlspecialchars($truck['truck_plate_number']); ?> 
                                    - <?php echo htmlspecialchars($truck['driver_name']); ?>
                                    (<?php echo $truck['scheduled_deliveries']; ?> scheduled)
                                    <?php if ($truck['scheduled_deliveries'] > 0): ?>
                                        - <?php echo htmlspecialchars($truck['existing_deliveries']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Delivery Notes -->
                <div class="mt-6">
                    <label for="delivery_notes" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-sticky-note mr-1 text-gray-500"></i>
                        Delivery Notes
                    </label>
                    <textarea id="delivery_notes" name="delivery_notes" rows="4" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                              placeholder="Special instructions, address details, or other notes..."><?php echo htmlspecialchars($existingSchedule['delivery_notes'] ?? ''); ?></textarea>
                </div>

                <!-- Submit Buttons -->
                <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                    <a href="order_tracking.php?order_id=<?php echo $order_id; ?>" 
                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg transition-colors duration-200 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Tracking
                    </a>
                    
                    <button type="submit" name="schedule_delivery" 
                            class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg transition-colors duration-200 flex items-center">
                        <i class="fas <?php echo $existingSchedule ? 'fa-edit' : 'fa-calendar-plus'; ?> mr-2"></i>
                        <?php echo $existingSchedule ? 'Update Schedule' : 'Schedule Delivery'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleTruckSelection() {
            const deliveryType = document.getElementById('delivery_type').value;
            const truckSelection = document.getElementById('truck_selection');
            const truckSelect = document.getElementById('truck_schedule_id');
            
            if (deliveryType === 'company') {
                truckSelection.style.display = 'block';
                truckSelect.setAttribute('required', 'required');
            } else {
                truckSelection.style.display = 'none';
                truckSelect.removeAttribute('required');
                truckSelect.value = '';
            }
        }

        // Set minimum date to tomorrow
        document.getElementById('delivery_date').min = new Date().toISOString().split('T')[0];
        
        // Validate form before submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const deliveryType = document.getElementById('delivery_type').value;
            const truckId = document.getElementById('truck_schedule_id').value;
            
            if (deliveryType === 'company' && !truckId) {
                e.preventDefault();
                alert('Please select a truck for company delivery.');
                return false;
            }
        });

        // Initialize truck selection visibility
        toggleTruckSelection();
    </script>
</body>
</html>