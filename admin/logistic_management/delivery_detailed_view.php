<?php
// delivery_detailed_view.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get the selected date from URL parameter
$selected_date = $_GET['date'] ?? null;

// Validate date format
if (!$selected_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Handle truck driver assignment
if ($_POST && isset($_POST['assign_driver_to_truck'])) {
    $truck_schedule_id = $_POST['truck_schedule_id'];
    $driver_id = $_POST['driver_id'];
    
    // Update truck schedule with assigned driver
    $updateSql = "UPDATE truck_schedules SET assigned_driver_id = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("ii", $driver_id, $truck_schedule_id);
    
    if ($updateStmt->execute()) {
        $success_message = "Driver assigned to truck successfully!";
    } else {
        $error_message = "Error assigning driver to truck: " . $conn->error;
    }
    $updateStmt->close();
}

// Handle removing driver from truck
if ($_POST && isset($_POST['remove_driver_from_truck'])) {
    $truck_schedule_id = $_POST['truck_schedule_id'];
    
    $removeSql = "UPDATE truck_schedules SET assigned_driver_id = NULL WHERE id = ?";
    $removeStmt = $conn->prepare($removeSql);
    $removeStmt->bind_param("i", $truck_schedule_id);
    
    if ($removeStmt->execute()) {
        $success_message = "Driver removed from truck successfully!";
    } else {
        $error_message = "Error removing driver from truck: " . $conn->error;
    }
    $removeStmt->close();
}

// Handle assigning delivery items to truck
if ($_POST && isset($_POST['assign_items_to_truck'])) {
    $truck_schedule_id = $_POST['truck_schedule_id'];
    $delivery_ids = $_POST['delivery_ids'] ?? [];
    
    if (!empty($delivery_ids)) {
        $success_count = 0;
        foreach ($delivery_ids as $delivery_id) {
            $updateSql = "UPDATE delivery_schedules SET truck_schedule_id = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ii", $truck_schedule_id, $delivery_id);
            
            if ($updateStmt->execute()) {
                $success_count++;
            }
            $updateStmt->close();
        }
        
        $success_message = "Successfully assigned $success_count delivery items to truck!";
    } else {
        $error_message = "Please select delivery items to assign.";
    }
}

// Handle removing items from truck
if ($_POST && isset($_POST['remove_item_from_truck'])) {
    $delivery_id = $_POST['delivery_id'];
    
    $removeSql = "UPDATE delivery_schedules SET truck_schedule_id = NULL WHERE id = ?";
    $removeStmt = $conn->prepare($removeSql);
    $removeStmt->bind_param("i", $delivery_id);
    
    if ($removeStmt->execute()) {
        $success_message = "Item removed from truck successfully!";
    } else {
        $error_message = "Error removing item from truck: " . $conn->error;
    }
    $removeStmt->close();
}

// Convert date for display
$display_date = new DateTime($selected_date);
$formatted_date = $display_date->format('l, F j, Y');

// Get scheduled trucks with their assigned items and drivers
$scheduledTrucksSql = "SELECT 
    ts.id as truck_schedule_id,
    ts.truck_id,
    ts.scheduled_date,
    ts.max_capacity,
    ts.notes,
    ts.status,
    ts.assigned_driver_id,
    vl.make,
    vl.model,
    vl.truck_type,
    vl.photo_path as truck_photo,
    vl.weight_capacity,
    vl.volume_capacity,
    dl.first_name as driver_first_name,
    dl.last_name as driver_last_name,
    dl.contact_number as driver_contact,
    dl.photo_path as driver_photo,
    COUNT(ds.id) as assigned_items_count
FROM truck_schedules ts
INNER JOIN vehicle_list vl ON ts.truck_id = vl.plate_number
LEFT JOIN driver_list dl ON ts.assigned_driver_id = dl.id
LEFT JOIN delivery_schedules ds ON ts.id = ds.truck_schedule_id
WHERE ts.scheduled_date = ?
GROUP BY ts.id
ORDER BY ts.created_at";

$scheduledTrucksStmt = $conn->prepare($scheduledTrucksSql);
$scheduledTrucksStmt->bind_param("s", $selected_date);
$scheduledTrucksStmt->execute();
$scheduled_trucks = $scheduledTrucksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduledTrucksStmt->close();

// Get unassigned delivery items
$unassignedItemsSql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.item_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivered_at,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    oi.product_name,
    oi.quantity,
    oi.price,
    oi.variant_color,
    oi.size,
    oi.subtotal,
    CASE 
        WHEN ds.delivered_at IS NULL AND ds.delivery_date < CURDATE() THEN 'overdue'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date = CURDATE() THEN 'today_pending'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date > CURDATE() THEN 'upcoming'
        WHEN ds.delivered_at IS NOT NULL THEN 'completed'
    END as delivery_status
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
INNER JOIN order_items oi ON ds.item_id = oi.id
WHERE ds.delivery_date = ? AND ds.truck_schedule_id IS NULL AND ds.delivered_at IS NULL
ORDER BY ds.delivery_time ASC, ds.order_id ASC";

$unassignedItemsStmt = $conn->prepare($unassignedItemsSql);
$unassignedItemsStmt->bind_param("s", $selected_date);
$unassignedItemsStmt->execute();
$unassigned_items = $unassignedItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unassignedItemsStmt->close();

// Get available drivers
$driversSql = "SELECT 
    dl.id as driver_id,
    dl.first_name,
    dl.last_name,
    dl.contact_number,
    dl.email,
    dl.photo_path,
    dl.status
FROM driver_list dl
WHERE dl.status = 'active'
ORDER BY dl.first_name, dl.last_name";

$driversStmt = $conn->prepare($driversSql);
$driversStmt->execute();
$available_drivers = $driversStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$driversStmt->close();

// Get summary statistics
$statsSql = "SELECT 
    COUNT(*) as total_scheduled,
    COUNT(CASE WHEN ds.truck_schedule_id IS NOT NULL THEN 1 END) as assigned_to_trucks,
    COUNT(CASE WHEN ds.truck_schedule_id IS NULL THEN 1 END) as unassigned_items,
    COUNT(CASE WHEN ds.delivered_at IS NOT NULL THEN 1 END) as completed_deliveries
FROM delivery_schedules ds
WHERE ds.delivery_date = ?";

$statsStmt = $conn->prepare($statsSql);
$statsStmt->bind_param("s", $selected_date);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Deliveries for <?php echo $formatted_date; ?> - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74',
                            400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c',
                            800: '#9a3412', 900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .truck-card {
            transition: all 0.3s ease;
        }
        .truck-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .assignment-modal {
            backdrop-filter: blur(5px);
        }
        .delivery-item {
            transition: all 0.3s ease;
        }
        .delivery-item:hover {
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-6 space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-clipboard-list text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Assign Deliveries</h1>
                        <p class="text-gray-600 mt-1"><?php echo $formatted_date; ?></p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center space-x-3">
                    <a href="assign_drivers.php?date=<?php echo $selected_date; ?>" 
                       class="bg-green-100 text-green-700 px-4 py-2 rounded-lg hover:bg-green-200 transition-colors flex items-center">
                        <i class="fas fa-truck mr-2"></i>
                        Schedule Trucks
                    </a>
                    <a href="logistics_dashboard_view.php" 
                       class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-blue-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-list text-blue-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Total Items</p>
                    <p class="text-xl font-bold text-gray-900"><?php echo $stats['total_scheduled']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-green-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-truck text-green-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Assigned to Trucks</p>
                    <p class="text-xl font-bold text-green-600"><?php echo $stats['assigned_to_trucks']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-yellow-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Unassigned</p>
                    <p class="text-xl font-bold text-yellow-600"><?php echo $stats['unassigned_items']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-purple-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-truck-loading text-purple-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Scheduled Trucks</p>
                    <p class="text-xl font-bold text-purple-600"><?php echo count($scheduled_trucks); ?></p>
                </div>
            </div>
        </div>

        <?php if (empty($scheduled_trucks)): ?>
            <!-- No Scheduled Trucks Message -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                <div class="text-gray-400 mb-6">
                    <i class="fas fa-truck text-6xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-700 mb-4">No Trucks Scheduled</h3>
                <p class="text-gray-500 mb-6">You need to schedule trucks first before assigning deliveries</p>
                <a href="assign_drivers.php?date=<?php echo $selected_date; ?>" 
                   class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition-colors inline-flex items-center">
                    <i class="fas fa-truck mr-2"></i>
                    Schedule Trucks
                </a>
            </div>
        <?php else: ?>
            <!-- Scheduled Trucks with Assignments -->
            <div class="space-y-8">
                <?php foreach ($scheduled_trucks as $truck): ?>
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200">
                        <!-- Truck Header -->
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-t-xl p-6">
                            <div class="flex items-center justify-between text-white">
                                <div class="flex items-center space-x-4">
                                    <div class="w-16 h-12 bg-white bg-opacity-20 rounded-lg overflow-hidden">
                                        <?php if ($truck['truck_photo'] && file_exists("../uploads/truck_photo_collection/" . $truck['truck_photo'])): ?>
                                            <img src="../uploads/truck_photo_collection/<?php echo htmlspecialchars($truck['truck_photo']); ?>" 
                                                 alt="Truck Photo" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fas fa-truck text-white text-xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($truck['truck_id']); ?></h2>
                                        <p class="text-blue-100"><?php echo htmlspecialchars($truck['make'] . ' ' . $truck['model']); ?></p>
                                        <p class="text-blue-200 text-sm"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $truck['truck_type']))); ?></p>
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <div class="text-sm text-blue-100">Assigned Items</div>
                                    <div class="text-3xl font-bold"><?php echo $truck['assigned_items_count']; ?></div>
                                    <?php if ($truck['max_capacity']): ?>
                                        <div class="text-xs text-blue-200">Max: <?php echo $truck['max_capacity']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Driver Assignment Section -->
                            <div class="mb-6">
                                <?php if ($truck['assigned_driver_id']): ?>
                                    <!-- Current Driver Display -->
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-semibold text-green-800 flex items-center">
                                                <i class="fas fa-user-check mr-2"></i>
                                                Assigned Driver
                                            </h4>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="truck_schedule_id" value="<?php echo $truck['truck_schedule_id']; ?>">
                                                <button type="submit" name="remove_driver_from_truck" 
                                                        onclick="return confirm('Remove driver from this truck?')"
                                                        class="text-red-600 hover:text-red-800 text-sm">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gray-200 rounded-full overflow-hidden flex-shrink-0">
                                                <?php if ($truck['driver_photo'] && file_exists("../uploads/driver_photo_collection/" . $truck['driver_photo'])): ?>
                                                    <img src="../uploads/driver_photo_collection/<?php echo htmlspecialchars($truck['driver_photo']); ?>" 
                                                         alt="Driver Photo" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-400"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="flex-1">
                                                <div class="font-medium text-green-800">
                                                    <?php echo htmlspecialchars($truck['driver_first_name'] . ' ' . $truck['driver_last_name']); ?>
                                                </div>
                                                <?php if ($truck['driver_contact']): ?>
                                                <div class="text-xs text-green-600">
                                                    <i class="fas fa-phone mr-1"></i>
                                                    <?php echo htmlspecialchars($truck['driver_contact']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Assign Driver Section -->
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-semibold text-yellow-800 flex items-center">
                                                <i class="fas fa-user-times mr-2"></i>
                                                No Driver Assigned
                                            </h4>
                                            <button onclick="openDriverModal(<?php echo $truck['truck_schedule_id']; ?>)" 
                                                    class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 transition-colors">
                                                <i class="fas fa-plus mr-1"></i>
                                                Assign Driver
                                            </button>
                                        </div>
                                        <p class="text-sm text-yellow-700">Please assign a driver to this truck before deliveries</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Delivery Items Assignment Section -->
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-boxes mr-2"></i>
                                    Delivery Items (<?php echo $truck['assigned_items_count']; ?>)
                                </h4>
                                <?php if (!empty($unassigned_items)): ?>
                                <button onclick="openItemsModal(<?php echo $truck['truck_schedule_id']; ?>)" 
                                        class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors text-sm">
                                    <i class="fas fa-plus mr-2"></i>
                                    Assign Items
                                </button>
                                <?php endif; ?>
                            </div>

                            <!-- Assigned Items List -->
                            <div id="truck-items-<?php echo $truck['truck_schedule_id']; ?>">
                                <?php
                                // Get items assigned to this truck
                                $truckItemsSql = "SELECT 
                                    ds.id as delivery_id,
                                    ds.order_id,
                                    ds.delivery_time,
                                    ds.delivery_notes,
                                    o.customer_name,
                                    o.address,
                                    o.mobile,
                                    oi.product_name,
                                    oi.quantity,
                                    oi.price,
                                    oi.subtotal,
                                    oi.variant_color,
                                    oi.size
                                FROM delivery_schedules ds
                                INNER JOIN orders o ON ds.order_id = o.id
                                INNER JOIN order_items oi ON ds.item_id = oi.id
                                WHERE ds.truck_schedule_id = ?
                                ORDER BY ds.delivery_time ASC";
                                
                                $truckItemsStmt = $conn->prepare($truckItemsSql);
                                $truckItemsStmt->bind_param("i", $truck['truck_schedule_id']);
                                $truckItemsStmt->execute();
                                $truck_items = $truckItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                $truckItemsStmt->close();
                                ?>
                                
                                <?php if (empty($truck_items)): ?>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                                        <div class="text-gray-400 mb-3">
                                            <i class="fas fa-box-open text-3xl"></i>
                                        </div>
                                        <p class="text-gray-600">No delivery items assigned to this truck</p>
                                        <?php if (!empty($unassigned_items)): ?>
                                        <button onclick="openItemsModal(<?php echo $truck['truck_schedule_id']; ?>)" 
                                                class="mt-3 bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors text-sm">
                                            <i class="fas fa-plus mr-2"></i>
                                            Assign Items
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <?php foreach ($truck_items as $item): ?>
                                            <div class="delivery-item bg-gray-50 border border-gray-200 rounded-lg p-4">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">
                                                        Order #<?php echo $item['order_id']; ?>
                                                    </span>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="delivery_id" value="<?php echo $item['delivery_id']; ?>">
                                                        <button type="submit" name="remove_item_from_truck" 
                                                                onclick="return confirm('Remove this item from truck?')"
                                                                class="text-red-600 hover:text-red-800 text-sm">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                
                                                <h5 class="font-semibold text-gray-900 mb-2 text-sm">
                                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                                </h5>
                                                
                                                <div class="text-xs text-gray-600 space-y-1 mb-3">
                                                    <div class="flex justify-between">
                                                        <span>Qty:</span>
                                                        <span class="font-medium"><?php echo $item['quantity']; ?></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span>Time:</span>
                                                        <span class="font-medium">
                                                            <?php 
                                                            $timeObj = DateTime::createFromFormat('H:i:s', $item['delivery_time']);
                                                            echo $timeObj->format('g:i A');
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span>Value:</span>
                                                        <span class="font-medium">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="bg-white rounded p-2 text-xs">
                                                    <div class="font-medium text-gray-800 mb-1">
                                                        <i class="fas fa-user mr-1"></i>
                                                        <?php echo htmlspecialchars($item['customer_name']); ?>
                                                    </div>
                                                    <div class="text-gray-600 text-xs">
                                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                                        <?php echo htmlspecialchars(substr($item['address'], 0, 50)) . (strlen($item['address']) > 50 ? '...' : ''); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Unassigned Items Section -->
            <?php if (!empty($unassigned_items)): ?>
            <div class="bg-white rounded-xl shadow-lg border border-yellow-200 mt-8">
                <div class="bg-gradient-to-r from-yellow-400 to-orange-500 rounded-t-xl p-6">
                    <div class="flex items-center justify-between text-white">
                        <div class="flex items-center space-x-4">
                            <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                                <i class="fas fa-exclamation-triangle text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold">Unassigned Items</h2>
                                <p class="text-yellow-100"><?php echo count($unassigned_items); ?> items need truck assignment</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($unassigned_items as $item): ?>
                            <?php
                            $isOverdue = $item['delivery_status'] === 'overdue';
                            $isToday = $item['delivery_status'] === 'today_pending';
                            
                            if ($isOverdue) {
                                $cardClass = 'bg-red-50 border-red-200';
                                $statusClass = 'bg-red-100 text-red-800';
                                $statusIcon = 'fa-exclamation-triangle';
                                $statusText = 'Overdue';
                            } elseif ($isToday) {
                                $cardClass = 'bg-blue-50 border-blue-200';
                                $statusClass = 'bg-blue-100 text-blue-800';
                                $statusIcon = 'fa-clock';
                                $statusText = 'Due Today';
                            } else {
                                $cardClass = 'bg-yellow-50 border-yellow-200';
                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                $statusIcon = 'fa-calendar';
                                $statusText = 'Scheduled';
                            }
                            ?>
                            
                            <div class="delivery-item <?php echo $cardClass; ?> border-2 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="<?php echo $statusClass; ?> px-2 py-1 rounded text-xs font-bold">
                                        <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                    <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">
                                        Order #<?php echo $item['order_id']; ?>
                                    </span>
                                </div>
                                
                                <h5 class="font-semibold text-gray-900 mb-2 text-sm">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </h5>
                                
                                <div class="text-xs text-gray-600 space-y-1 mb-3">
                                    <div class="flex justify-between">
                                        <span>Qty:</span>
                                        <span class="font-medium"><?php echo $item['quantity']; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Time:</span>
                                        <span class="font-medium">
                                            <?php 
                                            $timeObj = DateTime::createFromFormat('H:i:s', $item['delivery_time']);
                                            echo $timeObj->format('g:i A');
                                            ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Value:</span>
                                        <span class="font-medium">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                    </div>
                                </div>
                                
                                <div class="bg-white rounded p-2 text-xs">
                                    <div class="font-medium text-gray-800 mb-1">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($item['customer_name']); ?>
                                    </div>
                                    <div class="text-gray-600 text-xs">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <?php echo htmlspecialchars(substr($item['address'], 0, 50)) . (strlen($item['address']) > 50 ? '...' : ''); ?>
                                    </div>
                                    <?php if ($item['mobile']): ?>
                                    <div class="text-gray-600 text-xs mt-1">
                                        <i class="fas fa-phone mr-1"></i>
                                        <?php echo htmlspecialchars($item['mobile']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Driver Assignment Modal -->
    <div id="driverModal" class="fixed inset-0 bg-black bg-opacity-50 assignment-modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900">Assign Driver to Truck</h3>
                        <button type="button" onclick="closeDriverModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <form method="POST" id="driverAssignForm">
                        <input type="hidden" name="truck_schedule_id" id="driverModalTruckId">
                        <input type="hidden" name="assign_driver_to_truck" value="1">
                        
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-user mr-2 text-blue-500"></i>
                                Select Driver:
                            </h4>
                            
                            <?php if (empty($available_drivers)): ?>
                                <div class="text-center py-8">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-user-times text-4xl"></i>
                                    </div>
                                    <p class="text-gray-600">No available drivers found</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3 max-h-80 overflow-y-auto">
                                    <?php foreach ($available_drivers as $driver): ?>
                                        <label class="driver-option cursor-pointer">
                                            <input type="radio" name="driver_id" value="<?php echo $driver['driver_id']; ?>" 
                                                   class="sr-only driver-radio" required>
                                            <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors driver-selection-card">
                                                <div class="flex items-center space-x-4">
                                                    <div class="w-12 h-12 bg-gray-200 rounded-full overflow-hidden flex-shrink-0">
                                                        <?php if ($driver['photo_path'] && file_exists("../uploads/driver_photo_collection/" . $driver['photo_path'])): ?>
                                                            <img src="../uploads/driver_photo_collection/<?php echo htmlspecialchars($driver['photo_path']); ?>" 
                                                                 alt="Driver Photo" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex items-center justify-center">
                                                                <i class="fas fa-user text-gray-400"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="flex-1">
                                                        <div class="font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>
                                                        </div>
                                                        <?php if ($driver['contact_number']): ?>
                                                        <div class="text-sm text-gray-600">
                                                            <i class="fas fa-phone mr-1"></i>
                                                            <?php echo htmlspecialchars($driver['contact_number']); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if ($driver['email']): ?>
                                                        <div class="text-sm text-gray-600">
                                                            <i class="fas fa-envelope mr-1"></i>
                                                            <?php echo htmlspecialchars($driver['email']); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="text-blue-500 opacity-0 driver-check">
                                                        <i class="fas fa-check-circle text-xl"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($available_drivers)): ?>
                        <div class="flex items-center justify-end space-x-4 mt-8 pt-6 border-t">
                            <button type="button" onclick="closeDriverModal()" 
                                    class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors font-medium">
                                <i class="fas fa-check mr-2"></i>
                                Assign Driver
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Assignment Modal -->
    <div id="itemsModal" class="fixed inset-0 bg-black bg-opacity-50 assignment-modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900">Assign Delivery Items to Truck</h3>
                        <button type="button" onclick="closeItemsModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <form method="POST" id="itemsAssignForm">
                        <input type="hidden" name="truck_schedule_id" id="itemsModalTruckId">
                        <input type="hidden" name="assign_items_to_truck" value="1">
                        
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-boxes mr-2 text-purple-500"></i>
                                    Select Delivery Items:
                                </h4>
                                <div class="flex items-center space-x-3">
                                    <button type="button" onclick="selectAllItems()" 
                                            class="text-sm text-blue-600 hover:text-blue-800">
                                        Select All
                                    </button>
                                    <button type="button" onclick="deselectAllItems()" 
                                            class="text-sm text-gray-600 hover:text-gray-800">
                                        Deselect All
                                    </button>
                                    <span class="text-sm text-gray-600">
                                        Selected: <span id="selectedItemsCount">0</span>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (empty($unassigned_items)): ?>
                                <div class="text-center py-8">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-box-open text-4xl"></i>
                                    </div>
                                    <p class="text-gray-600">No unassigned delivery items available</p>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto">
                                    <?php foreach ($unassigned_items as $item): ?>
                                        <?php
                                        $isOverdue = $item['delivery_status'] === 'overdue';
                                        $isToday = $item['delivery_status'] === 'today_pending';
                                        
                                        if ($isOverdue) {
                                            $cardClass = 'bg-red-50 border-red-200';
                                        } elseif ($isToday) {
                                            $cardClass = 'bg-blue-50 border-blue-200';
                                        } else {
                                            $cardClass = 'bg-gray-50 border-gray-200';
                                        }
                                        ?>
                                        
                                        <label class="item-option cursor-pointer">
                                            <input type="checkbox" name="delivery_ids[]" value="<?php echo $item['delivery_id']; ?>" 
                                                   class="sr-only item-checkbox">
                                            <div class="item-selection-card <?php echo $cardClass; ?> border-2 rounded-lg p-4 hover:border-purple-300 transition-colors">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div class="text-xs font-medium text-gray-700">
                                                        Order #<?php echo $item['order_id']; ?>
                                                    </div>
                                                    <div class="text-purple-500 opacity-0 item-check">
                                                        <i class="fas fa-check-circle"></i>
                                                    </div>
                                                </div>
                                                
                                                <h5 class="font-semibold text-gray-900 mb-2 text-sm">
                                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                                </h5>
                                                
                                                <div class="text-xs text-gray-600 space-y-1 mb-2">
                                                    <div class="flex justify-between">
                                                        <span>Qty:</span>
                                                        <span class="font-medium"><?php echo $item['quantity']; ?></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span>Time:</span>
                                                        <span class="font-medium">
                                                            <?php 
                                                            $timeObj = DateTime::createFromFormat('H:i:s', $item['delivery_time']);
                                                            echo $timeObj->format('g:i A');
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="bg-white rounded p-2 text-xs">
                                                    <div class="font-medium text-gray-800">
                                                        <i class="fas fa-user mr-1"></i>
                                                        <?php echo htmlspecialchars($item['customer_name']); ?>
                                                    </div>
                                                    <div class="text-gray-600 text-xs">
                                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                                        <?php echo htmlspecialchars(substr($item['address'], 0, 40)) . (strlen($item['address']) > 40 ? '...' : ''); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($unassigned_items)): ?>
                        <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                            <button type="button" onclick="closeItemsModal()" 
                                    class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" id="assignItemsButton" disabled
                                    class="px-6 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium disabled:bg-gray-300 disabled:cursor-not-allowed">
                                <i class="fas fa-check mr-2"></i>
                                Assign Selected Items
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentTruckId = null;

        // Driver Modal Functions
        function openDriverModal(truckId) {
            currentTruckId = truckId;
            document.getElementById('driverModalTruckId').value = truckId;
            document.getElementById('driverModal').classList.remove('hidden');
            resetDriverSelection();
        }

        function closeDriverModal() {
            document.getElementById('driverModal').classList.add('hidden');
            currentTruckId = null;
            resetDriverSelection();
        }

        function resetDriverSelection() {
            document.querySelectorAll('.driver-radio').forEach(radio => {
                radio.checked = false;
            });
            document.querySelectorAll('.driver-selection-card').forEach(card => {
                card.classList.remove('border-blue-500', 'bg-blue-50');
                card.classList.add('border-gray-200');
            });
            document.querySelectorAll('.driver-check').forEach(check => {
                check.classList.add('opacity-0');
            });
        }

        // Items Modal Functions
        function openItemsModal(truckId) {
            currentTruckId = truckId;
            document.getElementById('itemsModalTruckId').value = truckId;
            document.getElementById('itemsModal').classList.remove('hidden');
            resetItemsSelection();
        }

        function closeItemsModal() {
            document.getElementById('itemsModal').classList.add('hidden');
            currentTruckId = null;
            resetItemsSelection();
        }

        function resetItemsSelection() {
            document.querySelectorAll('.item-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.querySelectorAll('.item-selection-card').forEach(card => {
                card.classList.remove('border-purple-500', 'bg-purple-50');
            });
            document.querySelectorAll('.item-check').forEach(check => {
                check.classList.add('opacity-0');
            });
            updateSelectedItemsCount();
        }

        function selectAllItems() {
            document.querySelectorAll('.item-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                const card = checkbox.closest('.item-option').querySelector('.item-selection-card');
                const check = checkbox.closest('.item-option').querySelector('.item-check');
                card.classList.remove('border-gray-200', 'border-red-200', 'border-blue-200');
                card.classList.add('border-purple-500', 'bg-purple-50');
                check.classList.remove('opacity-0');
            });
            updateSelectedItemsCount();
        }

        function deselectAllItems() {
            resetItemsSelection();
        }

        function updateSelectedItemsCount() {
            const selectedCount = document.querySelectorAll('.item-checkbox:checked').length;
            document.getElementById('selectedItemsCount').textContent = selectedCount;
            
            const assignButton = document.getElementById('assignItemsButton');
            if (selectedCount > 0) {
                assignButton.disabled = false;
                assignButton.classList.remove('disabled:bg-gray-300', 'disabled:cursor-not-allowed');
            } else {
                assignButton.disabled = true;
                assignButton.classList.add('disabled:bg-gray-300', 'disabled:cursor-not-allowed');
            }
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Driver selection
            document.querySelectorAll('.driver-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        // Reset all cards
                        document.querySelectorAll('.driver-selection-card').forEach(card => {
                            card.classList.remove('border-blue-500', 'bg-blue-50');
                            card.classList.add('border-gray-200');
                        });
                        document.querySelectorAll('.driver-check').forEach(check => {
                            check.classList.add('opacity-0');
                        });
                        
                        // Highlight selected card
                        const card = this.closest('.driver-option').querySelector('.driver-selection-card');
                        const check = this.closest('.driver-option').querySelector('.driver-check');
                        card.classList.remove('border-gray-200');
                        card.classList.add('border-blue-500', 'bg-blue-50');
                        check.classList.remove('opacity-0');
                    }
                });
            });

            // Item selection
            document.querySelectorAll('.item-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const card = this.closest('.item-option').querySelector('.item-selection-card');
                    const check = this.closest('.item-option').querySelector('.item-check');
                    
                    if (this.checked) {
                        card.classList.add('border-purple-500', 'bg-purple-50');
                        check.classList.remove('opacity-0');
                    } else {
                        card.classList.remove('border-purple-500', 'bg-purple-50');
                        check.classList.add('opacity-0');
                    }
                    
                    updateSelectedItemsCount();
                });
            });
        });

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!document.getElementById('driverModal').classList.contains('hidden')) {
                    closeDriverModal();
                }
                if (!document.getElementById('itemsModal').classList.contains('hidden')) {
                    closeItemsModal();
                }
            }
        });

        // Close modals on backdrop click
        document.getElementById('driverModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDriverModal();
            }
        });

        document.getElementById('itemsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeItemsModal();
            }
        });
    </script>
</body>
</html>