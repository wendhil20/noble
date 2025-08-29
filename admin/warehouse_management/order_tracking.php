<?php
// order_tracking.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role([ 'superadmin', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $item_id = (int)$_POST['item_id'];
    $new_status = $_POST['tracking_status'];

    // Validate status based on origin
    $itemCheckSql = "SELECT origin FROM order_items WHERE id = ? AND order_id = ? LIMIT 1";
    $itemCheckStmt = $conn->prepare($itemCheckSql);
    $itemCheckStmt->bind_param("ii", $item_id, $order_id);
    $itemCheckStmt->execute();
    $itemData = $itemCheckStmt->get_result()->fetch_assoc();
    $itemCheckStmt->close();

    if ($itemData) {
        $origin = $itemData['origin'];

        // Define valid statuses for updating (excluding final statuses)
        $localStatuses = ['processing'];
        $internationalStatuses = ['processing', 'shipped_overseas', 'in_transit_international', 'customs_clearance'];

        $validStatuses = ($origin === 'local') ? $localStatuses : $internationalStatuses;

        if (in_array($new_status, $validStatuses)) {
            $updateSql = "UPDATE order_items SET tracking_status = ? WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("sii", $new_status, $item_id, $order_id);

            if ($updateStmt->execute()) {
                $success_message = "Status updated successfully";
            } else {
                $error_message = "Failed to update status";
            }
            $updateStmt->close();
        } else {
            $error_message = "Invalid status for this item type";
        }
    } else {
        $error_message = "Item not found";
    }
}

// Get order items with tracking information
$itemsSql = "
    SELECT 
        oi.*,
        COALESCE(sl.business_name, oi.manual_supplier_name) as supplier_name,
        COALESCE(oi.tracking_status, 'processing') as current_status
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
    WHERE oi.order_id = ?
    ORDER BY oi.origin, supplier_name, oi.product_name
";

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $order_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Group items by origin and supplier
$groupedItems = [
    'local' => [],
    'international' => []
];

foreach ($items as $item) {
    $origin = $item['origin'] ?? 'local'; // Default to local if not set
    $supplier = $item['supplier_name'] ?? 'Unknown Supplier';

    if (!isset($groupedItems[$origin][$supplier])) {
        $groupedItems[$origin][$supplier] = [];
    }

    $groupedItems[$origin][$supplier][] = $item;
}

// Status definitions
$statusDefinitions = [
    'local' => [
        'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Order confirmed and being prepared', 'progress' => 25],
        'ready_for_pickup' => ['icon' => 'fa-truck', 'color' => 'yellow', 'label' => 'Ready for Pickup/Dispatch', 'description' => 'Item ready for local delivery', 'progress' => 50],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Courier delivering to customer', 'progress' => 75],
        'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Customer received the item', 'progress' => 100],
        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Cancelled/Returned', 'description' => 'Order cancelled or returned', 'progress' => 0]
    ],
    'international' => [
        'processing' => ['icon' => 'fa-cog', 'color' => 'blue', 'label' => 'Processing', 'description' => 'Order confirmed, supplier preparing', 'progress' => 15],
        'shipped_overseas' => ['icon' => 'fa-ship', 'color' => 'purple', 'label' => 'Shipped from Overseas', 'description' => 'Item has left the overseas supplier', 'progress' => 30],
        'in_transit_international' => ['icon' => 'fa-plane', 'color' => 'yellow', 'label' => 'In Transit (International)', 'description' => 'Item is on the way (by sea/air)', 'progress' => 45],
        'customs_clearance' => ['icon' => 'fa-file-signature', 'color' => 'orange', 'label' => 'Customs Clearance', 'description' => 'Item undergoing customs inspection', 'progress' => 60],
        'in_local_warehouse' => ['icon' => 'fa-warehouse', 'color' => 'teal', 'label' => 'In Local Warehouse', 'description' => 'Item arrived and ready for dispatch', 'progress' => 75],
        'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'color' => 'orange', 'label' => 'Out for Delivery', 'description' => 'Courier delivering to customer', 'progress' => 90],
        'delivered' => ['icon' => 'fa-check-circle', 'color' => 'green', 'label' => 'Delivered', 'description' => 'Customer received the item', 'progress' => 100],
        'cancelled' => ['icon' => 'fa-times-circle', 'color' => 'red', 'label' => 'Cancelled/Returned', 'description' => 'Order cancelled or returned', 'progress' => 0]
    ]
];

// Define selectable statuses (excluding final statuses)
$selectableStatuses = [
    'local' => ['processing'],
    'international' => ['processing', 'shipped_overseas', 'in_transit_international', 'customs_clearance']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Order #<?php echo $order_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .progress-step {
            transition: all 0.3s ease;
        }

        .progress-step.active {
            transform: scale(1.1);
        }

        .progress-line {
            transition: width 0.5s ease;
        }

        .modal-backdrop {
            backdrop-filter: blur(4px);
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
                    <a href="order_list.php" class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-arrow-left text-gray-600"></i>
                    </a>
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-route text-white text-xl sm:text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Order Tracking</h1>
                        <p class="text-gray-600 mt-1 text-sm sm:text-base">Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    </div>
                </div>
                <div class="flex sm:flex-col space-x-2 sm:space-x-0 sm:space-y-2 sm:text-right">
                    <div class="bg-purple-50 px-3 sm:px-4 py-2 rounded-lg flex-1 sm:flex-none">
                        <span class="text-purple-700 font-medium text-sm sm:text-base">Status: <?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                    </div>
                    <div class="bg-purple-50 px-3 sm:px-4 py-2 rounded-lg flex-1 sm:flex-none">
                        <span class="text-purple-700 font-medium text-sm sm:text-base"><?php echo count($items); ?> Items</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8">

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="mb-4 sm:mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="text-green-800 text-sm sm:text-base"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-4 sm:mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                    <span class="text-red-800 text-sm sm:text-base"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Local Products -->
        <?php if (!empty($groupedItems['local'])): ?>
            <div class="mb-6 sm:mb-8">
                <div class="flex items-center mb-4 sm:mb-6">
                    <div class="bg-green-100 p-2 rounded-lg mr-3">
                        <i class="fas fa-home text-green-600 text-lg sm:text-xl"></i>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">🏠 Local Products</h2>
                    <span class="ml-2 sm:ml-3 bg-green-100 text-green-800 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium">Faster fulfillment</span>
                </div>

                <?php foreach ($groupedItems['local'] as $supplier => $supplierItems): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-4 sm:mb-6 overflow-hidden">
                        <div class="bg-green-50 px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
                            <h3 class="text-base sm:text-lg font-bold text-gray-900 flex items-center">
                                <i class="fas fa-building mr-2 text-green-600"></i>
                                <span class="truncate"><?php echo htmlspecialchars($supplier); ?></span>
                            </h3>
                        </div>
                        <div class="p-4 sm:p-6">
                            <div class="space-y-3 sm:space-y-4">
                                <?php foreach ($supplierItems as $item): ?>
                                    <div class="border border-gray-200 rounded-lg p-3 sm:p-4 cursor-pointer hover:shadow-md transition-shadow duration-200" onclick="openProgressModal('<?php echo $item['id']; ?>', 'local', '<?php echo $item['current_status']; ?>')">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-medium text-gray-900 text-sm sm:text-base truncate"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                                <p class="text-xs sm:text-sm text-gray-600 mt-1">Quantity: <?php echo $item['quantity']; ?> | Price: ₱<?php echo number_format((float)$item['price'], 2); ?></p>
                                            </div>
                                            <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                                                <div class="text-center sm:text-right">
                                                    <?php
                                                    $currentStatus = $item['current_status'];
                                                    $statusInfo = $statusDefinitions['local'][$currentStatus] ?? $statusDefinitions['local']['processing'];
                                                    ?>
                                                    <div class="bg-<?php echo $statusInfo['color']; ?>-100 text-<?php echo $statusInfo['color']; ?>-800 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium mb-1 sm:mb-2">
                                                        <i class="fas <?php echo $statusInfo['icon']; ?> mr-1"></i>
                                                        <span class="hidden sm:inline"><?php echo $statusInfo['label']; ?></span>
                                                        <span class="sm:hidden"><?php echo substr($statusInfo['label'], 0, 10) . (strlen($statusInfo['label']) > 10 ? '...' : ''); ?></span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 hidden sm:block"><?php echo $statusInfo['description']; ?></p>
                                                </div>
                                                <div class="flex items-center space-x-2" onclick="event.stopPropagation();">
                                                    <?php if ($currentStatus === 'processing'): ?>
                                                        <a href="delivery_schedule.php?order_id=<?php echo $order_id; ?>&item_id=<?php echo $item['id']; ?>&origin=local"
                                                            class="bg-green-600 hover:bg-green-700 text-white px-2 sm:px-3 py-1 sm:py-2 rounded-md text-xs sm:text-sm">
                                                            <i class="fas fa-calendar-alt mr-1"></i>
                                                            <span class="hidden sm:inline">Schedule</span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (in_array($currentStatus, $selectableStatuses['local'])): ?>
                                                        <form method="POST" class="flex items-center space-x-1 sm:space-x-2">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                            <select name="tracking_status" class="px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-md text-xs sm:text-sm">
                                                                <?php foreach ($selectableStatuses['local'] as $status): ?>
                                                                    <?php $info = $statusDefinitions['local'][$status]; ?>
                                                                    <option value="<?php echo $status; ?>" <?php echo ($currentStatus === $status) ? 'selected' : ''; ?>>
                                                                        <?php echo $info['label']; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" name="update_status" class="bg-blue-600 hover:bg-blue-700 text-white px-2 sm:px-3 py-1 sm:py-2 rounded-md text-xs sm:text-sm">
                                                                <span class="hidden sm:inline">Update</span>
                                                                <i class="fas fa-check sm:hidden"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- International Products -->
        <?php if (!empty($groupedItems['international'])): ?>
            <div class="mb-6 sm:mb-8">
                <div class="flex items-center mb-4 sm:mb-6">
                    <div class="bg-blue-100 p-2 rounded-lg mr-3">
                        <i class="fas fa-globe text-blue-600 text-lg sm:text-xl"></i>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">🌏 International Products</h2>
                    <span class="ml-2 sm:ml-3 bg-blue-100 text-blue-800 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium">Longer lead times</span>
                </div>

                <?php foreach ($groupedItems['international'] as $supplier => $supplierItems): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-4 sm:mb-6 overflow-hidden">
                        <div class="bg-blue-50 px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
                            <h3 class="text-base sm:text-lg font-bold text-gray-900 flex items-center">
                                <i class="fas fa-building mr-2 text-blue-600"></i>
                                <span class="truncate"><?php echo htmlspecialchars($supplier); ?></span>
                            </h3>
                        </div>
                        <div class="p-4 sm:p-6">
                            <div class="space-y-3 sm:space-y-4">
                                <?php foreach ($supplierItems as $item): ?>
                                    <div class="border border-gray-200 rounded-lg p-3 sm:p-4 cursor-pointer hover:shadow-md transition-shadow duration-200" onclick="openProgressModal('<?php echo $item['id']; ?>', 'international', '<?php echo $item['current_status']; ?>')">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-medium text-gray-900 text-sm sm:text-base truncate"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                                <p class="text-xs sm:text-sm text-gray-600 mt-1">Quantity: <?php echo $item['quantity']; ?> | Price: ₱<?php echo number_format((float)$item['price'], 2); ?></p>
                                            </div>
                                            <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                                                <div class="text-center sm:text-right">
                                                    <?php
                                                    $currentStatus = $item['current_status'];
                                                    $statusInfo = $statusDefinitions['international'][$currentStatus] ?? $statusDefinitions['international']['processing'];
                                                    ?>
                                                    <div class="bg-<?php echo $statusInfo['color']; ?>-100 text-<?php echo $statusInfo['color']; ?>-800 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium mb-1 sm:mb-2">
                                                        <i class="fas <?php echo $statusInfo['icon']; ?> mr-1"></i>
                                                        <span class="hidden sm:inline"><?php echo $statusInfo['label']; ?></span>
                                                        <span class="sm:hidden"><?php echo substr($statusInfo['label'], 0, 10) . (strlen($statusInfo['label']) > 10 ? '...' : ''); ?></span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 hidden sm:block"><?php echo $statusInfo['description']; ?></p>
                                                </div>
                                                <div class="flex items-center space-x-2" onclick="event.stopPropagation();">
                                                    <?php if ($currentStatus === 'customs_clearance'): ?>
                                                        <a href="delivery_schedule.php?order_id=<?php echo $order_id; ?>&item_id=<?php echo $item['id']; ?>&origin=international"
                                                            class="bg-blue-600 hover:bg-blue-700 text-white px-2 sm:px-3 py-1 sm:py-2 rounded-md text-xs sm:text-sm">
                                                            <i class="fas fa-calendar-alt mr-1"></i>
                                                            <span class="hidden sm:inline">Schedule</span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (in_array($currentStatus, $selectableStatuses['international'])): ?>
                                                        <form method="POST" class="flex items-center space-x-1 sm:space-x-2">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                            <select name="tracking_status" class="px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-md text-xs sm:text-sm">
                                                                <?php foreach ($selectableStatuses['international'] as $status): ?>
                                                                    <?php $info = $statusDefinitions['international'][$status]; ?>
                                                                    <option value="<?php echo $status; ?>" <?php echo ($currentStatus === $status) ? 'selected' : ''; ?>>
                                                                        <?php echo $info['label']; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" name="update_status" class="bg-blue-600 hover:bg-blue-700 text-white px-2 sm:px-3 py-1 sm:py-2 rounded-md text-xs sm:text-sm">
                                                                <span class="hidden sm:inline">Update</span>
                                                                <i class="fas fa-check sm:hidden"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($groupedItems['local']) && empty($groupedItems['international'])): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 sm:p-12 text-center">
                <div class="text-gray-500">
                    <i class="fas fa-box-open text-4xl sm:text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium mb-2">No Items Found</h3>
                    <p class="text-sm mb-4">No items found for this order.</p>
                    <a href="order_list.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Orders
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Status Legend -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Status Tracking Guide</h3>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                <div>
                    <h4 class="font-medium text-green-700 mb-3">🏠 Local Products</h4>
                    <div class="space-y-2">
                        <?php foreach ($statusDefinitions['local'] as $status => $info): ?>
                            <div class="flex items-center space-x-2 sm:space-x-3">
                                <div class="bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-800 px-2 py-1 rounded text-xs font-medium">
                                    <i class="fas <?php echo $info['icon']; ?> mr-1"></i>
                                    <span class="hidden sm:inline"><?php echo $info['label']; ?></span>
                                </div>
                                <span class="text-xs sm:text-sm text-gray-600"><?php echo $info['description']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <h4 class="font-medium text-blue-700 mb-3">🌏 International Products</h4>
                    <div class="space-y-2">
                        <?php foreach ($statusDefinitions['international'] as $status => $info): ?>
                            <div class="flex items-center space-x-2 sm:space-x-3">
                                <div class="bg-<?php echo $info['color']; ?>-100 text-<?php echo $info['color']; ?>-800 px-2 py-1 rounded text-xs font-medium">
                                    <i class="fas <?php echo $info['icon']; ?> mr-1"></i>
                                    <span class="hidden sm:inline"><?php echo $info['label']; ?></span>
                                </div>
                                <span class="text-xs sm:text-sm text-gray-600"><?php echo $info['description']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div id="progressModal" class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200 flex-shrink-0">
                <h3 class="text-lg sm:text-xl font-bold text-gray-900">Tracking Progress</h3>
                <button onclick="closeProgressModal()" class="text-gray-500 hover:text-gray-700 p-1">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <div id="progressContent">
                    <!-- Progress content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Status definitions for JavaScript
        const statusDefinitions = <?php echo json_encode($statusDefinitions); ?>;

        function openProgressModal(itemId, origin, currentStatus) {
            const modal = document.getElementById('progressModal');
            const content = document.getElementById('progressContent');

            // Generate progress content
            const statuses = statusDefinitions[origin];
            const statusKeys = Object.keys(statuses);
            const currentIndex = statusKeys.indexOf(currentStatus);

            let progressHTML = `
                <div class="mb-6">
                    <h4 class="text-base font-medium text-gray-900 mb-4">Item Progress Timeline</h4>
                    <div class="relative pl-8">
                        <div class="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                        <div class="absolute left-3 top-0 w-0.5 bg-blue-600 transition-all duration-1000" style="height: ${((currentIndex + 1) / statusKeys.length) * 100}%;"></div>
            `;

            statusKeys.forEach((status, index) => {
                const statusInfo = statuses[status];
                const isActive = index <= currentIndex;
                const isCurrent = status === currentStatus;

                progressHTML += `
                    <div class="relative flex items-start mb-6 last:mb-0">
                        <div class="absolute -left-5 flex-shrink-0 w-6 h-6 rounded-full border-2 ${isActive ? `bg-blue-600 border-blue-600` : 'bg-white border-gray-300'} flex items-center justify-center z-10 shadow-sm">
                            <i class="fas ${statusInfo.icon} ${isActive ? 'text-white' : 'text-gray-400'} text-xs"></i>
                        </div>
                        <div class="ml-4 flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <h5 class="text-sm font-medium ${isActive ? 'text-gray-900' : 'text-gray-500'}">${statusInfo.label}</h5>
                                <span class="text-xs text-gray-500 ml-2">${statusInfo.progress}%</span>
                            </div>
                            <p class="text-xs ${isActive ? 'text-gray-600' : 'text-gray-400'} mt-1 pr-2">${statusInfo.description}</p>
                            ${isCurrent ? '<span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded-full mt-2">Current Status</span>' : ''}
                        </div>
                    </div>
                `;
            });

            progressHTML += `
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 mt-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-gray-700">Overall Progress</span>
                        <span class="text-sm font-bold text-blue-600">${statuses[currentStatus].progress}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2.5 rounded-full transition-all duration-1000 ease-out" style="width: ${statuses[currentStatus].progress}%"></div>
                    </div>
                </div>
            `;

            content.innerHTML = progressHTML;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');

            // Trigger animations after a small delay
            setTimeout(() => {
                const progressBar = content.querySelector('.bg-gradient-to-r');
                const timeline = content.querySelector('.bg-blue-600');
                if (progressBar) progressBar.style.width = `${statuses[currentStatus].progress}%`;
                if (timeline) timeline.style.height = `${((currentIndex + 1) / statusKeys.length) * 100}%`;
            }, 100);
        }

        function closeProgressModal() {
            const modal = document.getElementById('progressModal');
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function openScheduleModal(itemId, type) {
            const modal = document.getElementById('scheduleModal');
            document.getElementById('scheduleItemId').textContent = itemId;
            document.getElementById('scheduleType').textContent = type;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Close modals when clicking outside
        document.getElementById('progressModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProgressModal();
            }
        });

        document.getElementById('scheduleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeScheduleModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProgressModal();
                closeScheduleModal();
            }
        });

        // Prevent form submission on Enter key in selects
        document.querySelectorAll('select[name="tracking_status"]').forEach(select => {
            select.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = this.closest('form');
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.click();
                    }
                }
            });
        });
    </script>
</body>

</html>