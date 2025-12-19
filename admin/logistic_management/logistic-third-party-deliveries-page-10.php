<?php
// third_party_deliveries.php
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
    header("Location: logistic-main-dashboard-page-1.php");
    exit();
}

// Handle delivery proof upload
if ($_POST && isset($_POST['upload_delivery_proof'])) {
    $delivery_id = $_POST['delivery_id'];
    
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/delivery_proof/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'delivery_' . $delivery_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $file_path)) {
            // Get item_id from delivery_schedules
            $getItemSql = "SELECT item_id FROM delivery_schedules WHERE id = ?";
            $getItemStmt = $conn->prepare($getItemSql);
            $getItemStmt->bind_param("i", $delivery_id);
            $getItemStmt->execute();
            $item_result = $getItemStmt->get_result()->fetch_assoc();
            $item_id = $item_result['item_id'];
            $getItemStmt->close();
            
            // Update delivery with proof and mark as delivered
            $updateSql = "UPDATE delivery_schedules SET delivery_proof = ?, delivered_at = NOW(), delivery_status = 'delivered' WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $file_name, $delivery_id);
            
            // Update order item tracking status
            $updateOrderItemSql = "UPDATE order_items SET tracking_status = 'delivered' WHERE id = ?";
            $updateOrderItemStmt = $conn->prepare($updateOrderItemSql);
            $updateOrderItemStmt->bind_param("i", $item_id);
            
            if ($updateStmt->execute() && $updateOrderItemStmt->execute()) {
                $success_message = "Delivery completed successfully with proof uploaded!";
            } else {
                $error_message = "Error completing delivery: " . $conn->error;
            }
            
            $updateStmt->close();
            $updateOrderItemStmt->close();
        } else {
            $error_message = "Error uploading delivery proof image.";
        }
    } else {
        $error_message = "Please select a valid image file for delivery proof.";
    }
}

// Handle canceling 3rd party assignment
if ($_POST && isset($_POST['cancel_third_party_assignment'])) {
    $delivery_id = $_POST['delivery_id'];
    
    $cancelSql = "UPDATE delivery_schedules SET delivery_type = NULL, delivery_status = 'scheduled' WHERE id = ?";
    $cancelStmt = $conn->prepare($cancelSql);
    $cancelStmt->bind_param("i", $delivery_id);
    
    if ($cancelStmt->execute()) {
        $success_message = "3rd party assignment canceled. Item returned to unassigned list.";
    } else {
        $error_message = "Error canceling 3rd party assignment: " . $conn->error;
    }
    $cancelStmt->close();
}

// Handle marking as out for delivery
if ($_POST && isset($_POST['mark_out_for_delivery'])) {
    $delivery_id = $_POST['delivery_id'];
    
    // Get item_id from delivery_schedules
    $getItemSql = "SELECT item_id FROM delivery_schedules WHERE id = ?";
    $getItemStmt = $conn->prepare($getItemSql);
    $getItemStmt->bind_param("i", $delivery_id);
    $getItemStmt->execute();
    $item_result = $getItemStmt->get_result()->fetch_assoc();
    $item_id = $item_result['item_id'];
    $getItemStmt->close();
    
    // Update delivery status
    $updateDeliverySql = "UPDATE delivery_schedules SET delivery_status = 'out_for_delivery' WHERE id = ?";
    $updateDeliveryStmt = $conn->prepare($updateDeliverySql);
    $updateDeliveryStmt->bind_param("i", $delivery_id);
    
    // Update order item tracking status
    $updateOrderItemSql = "UPDATE order_items SET tracking_status = 'out_for_delivery' WHERE id = ?";
    $updateOrderItemStmt = $conn->prepare($updateOrderItemSql);
    $updateOrderItemStmt->bind_param("i", $item_id);
    
    if ($updateDeliveryStmt->execute() && $updateOrderItemStmt->execute()) {
        $success_message = "Item marked as out for delivery!";
    } else {
        $error_message = "Error updating delivery status: " . $conn->error;
    }
    
    $updateDeliveryStmt->close();
    $updateOrderItemStmt->close();
}

// Convert date for display
$display_date = new DateTime($selected_date);
$formatted_date = $display_date->format('l, F j, Y');

// Get Lalamove deliveries
$lalamoveSql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.item_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivered_at,
    ds.delivery_status,
    ds.delivery_type,
    ds.delivery_proof,
    ds.item_type,
    ds.replacement_id,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    CASE 
        WHEN ds.item_type = 'replacement' THEN CONCAT('[REPLACEMENT] ', oi.product_name)
        ELSE oi.product_name
    END as product_name,
    oi.quantity,
    oi.price,
    oi.variant_color,
    oi.size,
    oi.subtotal,
    rr.reason as replacement_reason,
    rr.status as replacement_status,
    CASE 
        WHEN ds.delivered_at IS NULL AND ds.delivery_date < CURDATE() THEN 'overdue'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date = CURDATE() THEN 'today_pending'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date > CURDATE() THEN 'upcoming'
        WHEN ds.delivered_at IS NOT NULL THEN 'completed'
    END as priority_status
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
INNER JOIN order_items oi ON ds.item_id = oi.id
LEFT JOIN replacement_requests rr ON ds.replacement_id = rr.id
WHERE ds.delivery_date = ? AND ds.delivery_type = 'lalamove'
ORDER BY ds.item_type DESC, ds.delivery_time ASC, ds.order_id ASC";

$lalamoveStmt = $conn->prepare($lalamoveSql);
$lalamoveStmt->bind_param("s", $selected_date);
$lalamoveStmt->execute();
$lalamove_items = $lalamoveStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$lalamoveStmt->close();

// Get other 3rd party deliveries
$thirdPartySql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.item_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivered_at,
    ds.delivery_status,
    ds.delivery_type,
    ds.delivery_proof,
    ds.item_type,
    ds.replacement_id,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    CASE 
        WHEN ds.item_type = 'replacement' THEN CONCAT('[REPLACEMENT] ', oi.product_name)
        ELSE oi.product_name
    END as product_name,
    oi.quantity,
    oi.price,
    oi.variant_color,
    oi.size,
    oi.subtotal,
    rr.reason as replacement_reason,
    rr.status as replacement_status,
    CASE 
        WHEN ds.delivered_at IS NULL AND ds.delivery_date < CURDATE() THEN 'overdue'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date = CURDATE() THEN 'today_pending'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date > CURDATE() THEN 'upcoming'
        WHEN ds.delivered_at IS NOT NULL THEN 'completed'
    END as priority_status
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
INNER JOIN order_items oi ON ds.item_id = oi.id
LEFT JOIN replacement_requests rr ON ds.replacement_id = rr.id
WHERE ds.delivery_date = ? AND ds.delivery_type = 'third_party'
ORDER BY ds.item_type DESC, ds.delivery_time ASC, ds.order_id ASC";

$thirdPartyStmt = $conn->prepare($thirdPartySql);
$thirdPartyStmt->bind_param("s", $selected_date);
$thirdPartyStmt->execute();
$third_party_items = $thirdPartyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$thirdPartyStmt->close();

// Get summary statistics
$statsSql = "SELECT 
    COUNT(CASE WHEN ds.delivery_type = 'lalamove' THEN 1 END) as total_lalamove,
    COUNT(CASE WHEN ds.delivery_type = 'third_party' THEN 1 END) as total_third_party,
    COUNT(CASE WHEN ds.delivery_type = 'lalamove' AND ds.delivered_at IS NOT NULL THEN 1 END) as completed_lalamove,
    COUNT(CASE WHEN ds.delivery_type = 'third_party' AND ds.delivered_at IS NOT NULL THEN 1 END) as completed_third_party,
    COUNT(CASE WHEN ds.delivery_type IN ('lalamove', 'third_party') AND ds.delivery_status = 'out_for_delivery' THEN 1 END) as out_for_delivery,
    COUNT(CASE WHEN ds.delivery_type IN ('lalamove', 'third_party') AND ds.delivery_status = 'third_party_assigned' THEN 1 END) as pending_pickup,
    COUNT(CASE WHEN ds.delivery_type = 'lalamove' AND ds.item_type = 'replacement' THEN 1 END) as lalamove_replacements,
    COUNT(CASE WHEN ds.delivery_type = 'third_party' AND ds.item_type = 'replacement' THEN 1 END) as third_party_replacements,
    COUNT(CASE WHEN ds.delivery_type IN ('lalamove', 'third_party') AND ds.item_type = 'replacement' THEN 1 END) as total_replacements
FROM delivery_schedules ds
WHERE ds.delivery_date = ? AND ds.delivery_type IN ('lalamove', 'third_party')";

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
    <title>3rd Party Deliveries - <?php echo $formatted_date; ?> - Noble Home</title>
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
        .delivery-item {
            transition: all 0.3s ease;
        }
        .delivery-item:hover {
            transform: translateY(-1px);
        }
        .status-third_party_assigned { @apply bg-purple-100 text-purple-800; }
        .status-out_for_delivery { @apply bg-orange-100 text-orange-800; }
        .status-delivered { @apply bg-green-100 text-green-800; }
        .modal-backdrop {
            backdrop-filter: blur(5px);
        }

        .modal-backdrop {
            backdrop-filter: blur(5px);
        }
        
        .replacement-item {
            border-left: 4px solid #ef4444 !important;
            position: relative;
        }
        
        .replacement-indicator {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            z-index: 10;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-purple-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-6 space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-shipping-fast text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">3rd Party Deliveries</h1>
                        <p class="text-gray-600 mt-1"><?php echo $formatted_date; ?></p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center space-x-3">
                    <a href="logistic-delivery-detailed-view-page-14.php?date=<?php echo $selected_date; ?>" 
                       class="bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition-colors flex items-center">
                        <i class="fas fa-truck mr-2"></i>
                        Back to Assignments
                    </a>
                    <a href="logistic-main-dashboard-page-1.php" 
                       class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Dashboard
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
        <div class="grid grid-cols-1 md:grid-cols-7 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-pink-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-motorcycle text-pink-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Lalamove</p>
                    <p class="text-xl font-bold text-pink-600"><?php echo $stats['total_lalamove']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-blue-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-truck text-blue-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Other 3rd Party</p>
                    <p class="text-xl font-bold text-blue-600"><?php echo $stats['total_third_party']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-purple-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-clock text-purple-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Pending Pickup</p>
                    <p class="text-xl font-bold text-purple-600"><?php echo $stats['pending_pickup']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-orange-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-shipping-fast text-orange-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Out for Delivery</p>
                    <p class="text-xl font-bold text-orange-600"><?php echo $stats['out_for_delivery']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-green-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-check text-green-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Lalamove Done</p>
                    <p class="text-xl font-bold text-green-600"><?php echo $stats['completed_lalamove']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-emerald-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-check-double text-emerald-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">3rd Party Done</p>
                    <p class="text-xl font-bold text-emerald-600"><?php echo $stats['completed_third_party']; ?></p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-red-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-exchange-alt text-red-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Total Replacements</p>
                    <p class="text-xl font-bold text-red-600"><?php echo $stats['total_replacements']; ?></p>
                </div>
            </div>
        </div>

        <!-- Lalamove Section -->
        <div class="bg-white rounded-xl shadow-lg border border-pink-200 mb-8">
            <div class="bg-gradient-to-r from-pink-400 to-pink-600 rounded-t-xl p-6">
                <div class="flex items-center justify-between text-white">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                            <i class="fas fa-motorcycle text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold">Lalamove Deliveries</h2>
                            <p class="text-pink-100"><?php echo count($lalamove_items); ?> items assigned</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <?php if (empty($lalamove_items)): ?>
                    <div class="text-center py-12">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-motorcycle text-6xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Lalamove Deliveries</h3>
                        <p class="text-gray-500">No items have been assigned to Lalamove for this date</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($lalamove_items as $item): ?>
                            <?php
                            $status = $item['delivery_status'] ?? 'third_party_assigned';
                            $statusLabels = [
                                'third_party_assigned' => 'Assigned to Lalamove',
                                'out_for_delivery' => 'Out for Delivery',
                                'delivered' => 'Delivered'
                            ];
                            ?>
                            
                            <?php
                            $cardClass = "delivery-item bg-pink-50 border-2 border-pink-200 rounded-lg p-4";
                            if ($item['item_type'] === 'replacement') {
                                $cardClass .= " replacement-item";
                            }
                            ?>
                            <div class="<?php echo $cardClass; ?>">
                                <?php if ($item['item_type'] === 'replacement'): ?>
                                <div class="replacement-indicator">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <?php endif; ?>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        <span class="bg-pink-100 text-pink-800 px-2 py-1 rounded text-xs font-medium">
                                            Order #<?php echo $item['order_id']; ?>
                                        </span>
                                        <?php if ($item['item_type'] === 'replacement'): ?>
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">
                                            <i class="fas fa-exchange-alt mr-1"></i>
                                            REPLACEMENT
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="status-<?php echo $status; ?> px-2 py-1 rounded text-xs font-bold">
                                            <?php echo $statusLabels[$status] ?? ucfirst($status); ?>
                                        </span>
                                        <?php if ($status !== 'delivered'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="delivery_id" value="<?php echo $item['delivery_id']; ?>">
                                            <button type="submit" name="cancel_third_party_assignment" 
                                                    onclick="return confirm('Cancel Lalamove assignment?')"
                                                    class="text-red-600 hover:text-red-800 text-sm" title="Cancel Assignment">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <h5 class="font-semibold text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </h5>
                                
                                <?php if ($item['replacement_reason']): ?>
                                <div class="text-xs text-red-600 mb-2 bg-red-50 p-2 rounded">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Replacement Reason:</strong> <?php echo ucfirst(str_replace('_', ' ', $item['replacement_reason'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                
                                <div class="text-sm text-gray-600 space-y-1 mb-3">
                                    <div class="flex justify-between">
                                        <span>Quantity:</span>
                                        <span class="font-medium"><?php echo $item['quantity']; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Delivery Time:</span>
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
                                    <?php if ($item['variant_color']): ?>
                                    <div class="flex justify-between">
                                        <span>Color:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($item['variant_color']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($item['size']): ?>
                                    <div class="flex justify-between">
                                        <span>Size:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($item['size']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="bg-white rounded-lg p-3 text-sm mb-4">
                                    <div class="font-medium text-gray-800 mb-1">
                                        <i class="fas fa-user mr-1 text-pink-500"></i>
                                        <?php echo htmlspecialchars($item['customer_name']); ?>
                                    </div>
                                    <div class="text-gray-600 text-xs">
                                        <i class="fas fa-map-marker-alt mr-1 text-pink-500"></i>
                                        <?php echo htmlspecialchars($item['address']); ?>
                                    </div>
                                    <?php if ($item['mobile']): ?>
                                    <div class="text-gray-600 text-xs mt-1">
                                        <i class="fas fa-phone mr-1 text-pink-500"></i>
                                        <?php echo htmlspecialchars($item['mobile']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex flex-col space-y-2">
                                    <?php if ($status === 'third_party_assigned'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="delivery_id" value="<?php echo $item['delivery_id']; ?>">
                                            <button type="submit" name="mark_out_for_delivery" 
                                                    class="w-full bg-orange-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-orange-600 transition-colors">
                                                <i class="fas fa-motorcycle mr-2"></i>
                                                Mark Out for Delivery
                                            </button>
                                        </form>
                                    <?php elseif ($status === 'out_for_delivery'): ?>
                                        <button onclick="openProofModal(<?php echo $item['delivery_id']; ?>)" 
                                                class="w-full bg-green-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-green-600 transition-colors">
                                            <i class="fas fa-camera mr-2"></i>
                                            Upload Delivery Proof
                                        </button>
                                    <?php elseif ($status === 'delivered' && $item['delivery_proof']): ?>
                                        <button onclick="viewProof('<?php echo htmlspecialchars($item['delivery_proof']); ?>')" 
                                                class="w-full bg-blue-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-600 transition-colors">
                                            <i class="fas fa-eye mr-2"></i>
                                            View Delivery Proof
                                        </button>
                                        <?php if ($item['delivered_at']): ?>
                                        <div class="text-xs text-green-600 text-center">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Delivered: <?php echo date('M j, Y g:i A', strtotime($item['delivered_at'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Other 3rd Party Section -->
        <div class="bg-white rounded-xl shadow-lg border border-blue-200">
            <div class="bg-gradient-to-r from-blue-400 to-indigo-600 rounded-t-xl p-6">
                <div class="flex items-center justify-between text-white">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                            <i class="fas fa-truck text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold">Other 3rd Party Deliveries</h2>
                            <p class="text-blue-100"><?php echo count($third_party_items); ?> items assigned</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <?php if (empty($third_party_items)): ?>
                    <div class="text-center py-12">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-truck text-6xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No 3rd Party Deliveries</h3>
                        <p class="text-gray-500">No items have been assigned to other 3rd party services for this date</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($third_party_items as $item): ?>
                            <?php
                            $status = $item['delivery_status'] ?? 'third_party_assigned';
                            $statusLabels = [
                                'third_party_assigned' => 'Assigned to 3rd Party',
                                'out_for_delivery' => 'Out for Delivery',
                                'delivered' => 'Delivered'
                            ];
                            ?>
                            
                            <?php
                            $cardClass = "delivery-item bg-blue-50 border-2 border-blue-200 rounded-lg p-4";
                            if ($item['item_type'] === 'replacement') {
                                $cardClass .= " replacement-item";
                            }
                            ?>
                            <div class="<?php echo $cardClass; ?>">
                                <?php if ($item['item_type'] === 'replacement'): ?>
                                <div class="replacement-indicator">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <?php endif; ?>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">
                                            Order #<?php echo $item['order_id']; ?>
                                        </span>
                                        <?php if ($item['item_type'] === 'replacement'): ?>
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">
                                            <i class="fas fa-exchange-alt mr-1"></i>
                                            REPLACEMENT
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="status-<?php echo $status; ?> px-2 py-1 rounded text-xs font-bold">
                                            <?php echo $statusLabels[$status] ?? ucfirst($status); ?>
                                        </span>
                                        <?php if ($status !== 'delivered'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="delivery_id" value="<?php echo $item['delivery_id']; ?>">
                                            <button type="submit" name="cancel_third_party_assignment" 
                                                    onclick="return confirm('Cancel 3rd party assignment?')"
                                                    class="text-red-600 hover:text-red-800 text-sm" title="Cancel Assignment">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <h5 class="font-semibold text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </h5>
                                
                                <?php if ($item['replacement_reason']): ?>
                                <div class="text-xs text-red-600 mb-2 bg-red-50 p-2 rounded">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Replacement Reason:</strong> <?php echo ucfirst(str_replace('_', ' ', $item['replacement_reason'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="text-sm text-gray-600 space-y-1 mb-3">
                                    <div class="flex justify-between">
                                        <span>Quantity:</span>
                                        <span class="font-medium"><?php echo $item['quantity']; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Delivery Time:</span>
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
                                    <?php if ($item['variant_color']): ?>
                                    <div class="flex justify-between">
                                        <span>Color:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($item['variant_color']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($item['size']): ?>
                                    <div class="flex justify-between">
                                        <span>Size:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($item['size']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="bg-white rounded-lg p-3 text-sm mb-4">
                                    <div class="font-medium text-gray-800 mb-1">
                                        <i class="fas fa-user mr-1 text-blue-500"></i>
                                        <?php echo htmlspecialchars($item['customer_name']); ?>
                                    </div>
                                    <div class="text-gray-600 text-xs">
                                        <i class="fas fa-map-marker-alt mr-1 text-blue-500"></i>
                                        <?php echo htmlspecialchars($item['address']); ?>
                                    </div>
                                    <?php if ($item['mobile']): ?>
                                    <div class="text-gray-600 text-xs mt-1">
                                        <i class="fas fa-phone mr-1 text-blue-500"></i>
                                        <?php echo htmlspecialchars($item['mobile']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex flex-col space-y-2">
                                    <?php if ($status === 'third_party_assigned'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="delivery_id" value="<?php echo $item['delivery_id']; ?>">
                                            <button type="submit" name="mark_out_for_delivery" 
                                                    class="w-full bg-orange-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-orange-600 transition-colors">
                                                <i class="fas fa-truck mr-2"></i>
                                                Mark Out for Delivery
                                            </button>
                                        </form>
                                    <?php elseif ($status === 'out_for_delivery'): ?>
                                        <button onclick="openProofModal(<?php echo $item['delivery_id']; ?>)" 
                                                class="w-full bg-green-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-green-600 transition-colors">
                                            <i class="fas fa-camera mr-2"></i>
                                            Upload Delivery Proof
                                        </button>
                                    <?php elseif ($status === 'delivered' && $item['delivery_proof']): ?>
                                        <button onclick="viewProof('<?php echo htmlspecialchars($item['delivery_proof']); ?>')" 
                                                class="w-full bg-blue-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-600 transition-colors">
                                            <i class="fas fa-eye mr-2"></i>
                                            View Delivery Proof
                                        </button>
                                        <?php if ($item['delivered_at']): ?>
                                        <div class="text-xs text-green-600 text-center">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Delivered: <?php echo date('M j, Y g:i A', strtotime($item['delivered_at'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delivery Proof Upload Modal -->
    <div id="proofModal" class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900">Upload Delivery Proof</h3>
                        <button type="button" onclick="closeProofModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <form method="POST" enctype="multipart/form-data" id="proofUploadForm">
                        <input type="hidden" name="delivery_id" id="proofModalDeliveryId">
                        <input type="hidden" name="upload_delivery_proof" value="1">
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-camera mr-2"></i>
                                Select delivery proof image:
                            </label>
                            <input type="file" name="proof_image" accept="image/*" required
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, GIF (max 10MB)</p>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <button type="button" onclick="closeProofModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-medium">
                                <i class="fas fa-upload mr-2"></i>
                                Upload & Complete Delivery
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Proof View Modal -->
    <div id="viewProofModal" class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900">
                            <i class="fas fa-image mr-2"></i>
                            Delivery Proof
                        </h3>
                        <button type="button" onclick="closeViewProofModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6 text-center">
                    <img id="proofImage" src="" alt="Delivery Proof" class="max-w-full max-h-96 mx-auto rounded-lg shadow-lg">
                    <div class="mt-4">
                        <button onclick="downloadProof()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-download mr-2"></i>
                            Download Image
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentDeliveryId = null;
        let currentProofImage = null;

        // Proof Modal Functions
        function openProofModal(deliveryId) {
            currentDeliveryId = deliveryId;
            document.getElementById('proofModalDeliveryId').value = deliveryId;
            document.getElementById('proofModal').classList.remove('hidden');
        }

        function closeProofModal() {
            document.getElementById('proofModal').classList.add('hidden');
            currentDeliveryId = null;
            // Reset form
            document.getElementById('proofUploadForm').reset();
        }

        function viewProof(imageName) {
            currentProofImage = imageName;
            const imagePath = '../../uploads/delivery_proof/' + imageName;
            document.getElementById('proofImage').src = imagePath;
            document.getElementById('viewProofModal').classList.remove('hidden');
        }

        function closeViewProofModal() {
            document.getElementById('viewProofModal').classList.add('hidden');
            currentProofImage = null;
        }

        function downloadProof() {
            if (currentProofImage) {
                const link = document.createElement('a');
                link.href = '../../uploads/delivery_proof/' + currentProofImage;
                link.download = currentProofImage;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!document.getElementById('proofModal').classList.contains('hidden')) {
                    closeProofModal();
                }
                if (!document.getElementById('viewProofModal').classList.contains('hidden')) {
                    closeViewProofModal();
                }
            }
        });

        // Close modals on backdrop click
        document.getElementById('proofModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProofModal();
            }
        });

        document.getElementById('viewProofModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewProofModal();
            }
        });

        // File upload validation
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.querySelector('input[name="proof_image"]');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        // Check file size (10MB limit)
                        if (file.size > 10 * 1024 * 1024) {
                            alert('File size must be less than 10MB');
                            this.value = '';
                            return;
                        }
                        
                        // Check file type
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Only JPG, PNG, and GIF files are allowed');
                            this.value = '';
                            return;
                        }
                    }
                });
            }
        });

        // Auto-refresh page every 30 seconds to show updated statuses
        setInterval(function() {
            // Only refresh if no modals are open
            if (document.getElementById('proofModal').classList.contains('hidden') && 
                document.getElementById('viewProofModal').classList.contains('hidden')) {
                location.reload();
            }
        }, 30000);

        // Show loading state on form submission
        document.getElementById('proofUploadForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
        });
    </script>
</body>
</html>