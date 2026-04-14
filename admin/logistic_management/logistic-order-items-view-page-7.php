<?php
// order_items_view.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

// Redirect dispatchers to their own dashboard
if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'dispatcher') {
    header("Location: logistic-dispatcher-dashboard-page-13.php");
    exit();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    header("Location: logistic-main-dashboard-page-1.php");
    exit();
}

// Get order details
$orderSql = "SELECT 
    o.*,
    ds.delivery_date,
    ds.delivery_time,
    ds.id as schedule_id
FROM orders o
LEFT JOIN delivery_schedules ds ON o.id = ds.order_id
WHERE o.id = ?
LIMIT 1";

$stmt = $conn->prepare($orderSql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: logistic-main-dashboard-page-1.php");
    exit();
}

// Get order items with detailed information
$itemsSql = "SELECT 
    oi.*,
    CASE 
        WHEN oi.tracking_status = 'pending' THEN 'Pending'
        WHEN oi.tracking_status = 'scheduled' THEN 'Ready to book'
        WHEN oi.tracking_status = 'ready_for_pickup' THEN 'Ready for Pickup'
        WHEN oi.tracking_status = 'item_is_loaded' THEN 'Item is Loaded'
        WHEN oi.tracking_status = 'out_for_delivery' THEN 'Out for Delivery'
        WHEN oi.tracking_status = 'delivered' THEN 'Delivered'
        WHEN oi.tracking_status = 'picked_up' THEN 'Picked Up'
        ELSE 'Unknown'
    END as status_display
FROM order_items oi
WHERE oi.order_id = ?
ORDER BY oi.id";

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $order_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Get booking info if exists
$bookingSql = "SELECT * FROM delivery_bookings WHERE order_id = ? LIMIT 1";
$bookingStmt = $conn->prepare($bookingSql);
$bookingStmt->bind_param("i", $order_id);
$bookingStmt->execute();
$booking = $bookingStmt->get_result()->fetch_assoc();
$bookingStmt->close();

// Calculate statistics
$totalItems = count($items);
$statusCounts = [
    'pending' => 0,
    'ready_for_pickup' => 0,
    'item_is_loaded' => 0,
    'out_for_delivery' => 0,
    'delivered' => 0,
    'picked_up' => 0
];

foreach ($items as $item) {
    if (isset($statusCounts[$item['tracking_status']])) {
        $statusCounts[$item['tracking_status']]++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Items - Order #<?php echo $order_id; ?> - Noble Home</title>
    <style>
        .item-row {
            transition: all 0.2s ease;
        }
        .item-row:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <?php if ($order['delivery_date']): ?>
            <a href="logistic-delivery-date-orders-page-2.php?date=<?php echo $order['delivery_date']; ?>" 
               class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Orders
            </a>
            <?php else: ?>
            <a href="logistic-main-dashboard-page-1.php" 
               class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
            <?php endif; ?>
            
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-list text-orange-600 mr-3"></i>
                        Order Items
                    </h1>
                    <p class="text-gray-600 mt-2">Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                </div>
                
                <?php if ($booking): ?>
                <a href="logistic-delivery-tracking-page-5.php?booking_id=<?php echo $booking['id']; ?>" 
                   class="bg-gradient-to-r from-purple-500 to-purple-600 text-white px-6 py-3 rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-tasks mr-2"></i>
                    Manage Delivery
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-lg border-2 border-gray-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-900"><?php echo $totalItems; ?></p>
                    <p class="text-xs text-gray-600 mt-1">Total Items</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg border-2 border-gray-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-600"><?php echo $statusCounts['pending']; ?></p>
                    <p class="text-xs text-gray-600 mt-1">Pending</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg border-2 border-yellow-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $statusCounts['ready_for_pickup']; ?></p>
                    <p class="text-xs text-gray-600 mt-1">Ready</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg border-2 border-blue-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-blue-600"><?php echo $statusCounts['item_is_loaded']; ?></p>
                    <p class="text-xs text-gray-600 mt-1">Loaded</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg border-2 border-purple-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-purple-600"><?php echo $statusCounts['out_for_delivery']; ?></p>
                    <p class="text-xs text-gray-600 mt-1">In Transit</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg border-2 border-green-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-green-600"><?php echo $statusCounts['delivered'] + $statusCounts['picked_up']; ?></p>
                    <p class="text-xs text-gray-600 mt-1">Completed</p>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-boxes text-orange-600 mr-2"></i>
                    Items List (<?php echo $totalItems; ?>)
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Product
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Details
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Quantity
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Price
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Subtotal
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($items as $item): ?>
                            <?php
                                $statusColors = [
                                    'pending' => 'bg-gray-100 text-gray-800 border-gray-300',
                                    'scheduled' => 'bg-gray-100 text-gray-800 border-purple-300',
                                    'ready_for_pickup' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                    'item_is_loaded' => 'bg-blue-100 text-blue-800 border-blue-300',
                                    'out_for_delivery' => 'bg-purple-100 text-purple-800 border-purple-300',
                                    'delivered' => 'bg-green-100 text-green-800 border-green-300',
                                    'picked_up' => 'bg-green-100 text-green-800 border-green-300'
                                ];
                                
                                $statusIcons = [
                                    'pending' => 'fa-clock',
                                    'scheduled' => 'fa-book',
                                    'ready_for_pickup' => 'fa-box',
                                    'item_is_loaded' => 'fa-check',
                                    'out_for_delivery' => 'fa-truck',
                                    'delivered' => 'fa-check-circle',
                                    'picked_up' => 'fa-check-circle'
                                ];
                                
                                $colorClass = $statusColors[$item['tracking_status']] ?? 'bg-gray-100 text-gray-800 border-gray-300';
                                $iconClass = $statusIcons[$item['tracking_status']] ?? 'fa-question';
                            ?>
                            <tr class="item-row">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <?php if ($item['codename']): ?>
                                    <div class="text-sm text-gray-500 mt-1">Code: <?php echo htmlspecialchars($item['codename']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-700 space-y-1">
                                        <?php if ($item['variant_color']): ?>
                                        <div><span class="font-medium">Color:</span> <?php echo htmlspecialchars($item['variant_color']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($item['size']): ?>
                                        <div><span class="font-medium">Size:</span> <?php echo htmlspecialchars($item['size']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($item['type_name']): ?>
                                        <div><span class="font-medium">Type:</span> <?php echo htmlspecialchars($item['type_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-lg font-bold text-gray-900"><?php echo $item['quantity']; ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-semibold text-gray-900">₱<?php echo number_format($item['price'], 2); ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-lg font-bold text-blue-600">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center">
                                        <span class="<?php echo $colorClass; ?> border px-3 py-2 rounded-lg text-xs font-semibold inline-flex items-center">
                                            <i class="fas <?php echo $iconClass; ?> mr-2"></i>
                                            <?php echo $item['status_display']; ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-right font-bold text-gray-900">
                                Total:
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-2xl font-bold text-blue-600">₱<?php echo number_format($order['final_total'], 2); ?></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Order Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
            <!-- Customer Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-user text-green-600 mr-2"></i>
                    Customer Information
                </h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-gray-600 block mb-1">Name:</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <?php if ($order['email']): ?>
                    <div>
                        <span class="text-gray-600 block mb-1">Email:</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($order['email']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['mobile']): ?>
                    <div>
                        <span class="text-gray-600 block mb-1">Mobile:</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($order['mobile']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div>
                        <span class="text-gray-600 block mb-1">Address:</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($order['address']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Order Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-receipt text-blue-600 mr-2"></i>
                    Order Details
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Order ID:</span>
                        <span class="font-semibold">#<?php echo $order['id']; ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Status:</span>
                        <span class="font-semibold"><?php echo $order['status']; ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Order Date:</span>
                        <span class="font-semibold"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                    <?php if ($order['delivery_date']): ?>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Delivery Date:</span>
                        <span class="font-semibold"><?php echo date('M d, Y', strtotime($order['delivery_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['delivery_time']): ?>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Delivery Time:</span>
                        <span class="font-semibold"><?php echo date('g:i A', strtotime($order['delivery_time'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Delivery Type:</span>
                        <span class="font-semibold capitalize"><?php echo $order['delivery_type']; ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Payment Method:</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($order['mode_payment']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Items:</span>
                        <span class="font-semibold"><?php echo $totalItems; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($booking): ?>
        <!-- Booking Information -->
        <div class="bg-purple-50 rounded-xl shadow-sm border border-purple-200 p-6 mt-6">
            <h3 class="text-lg font-bold text-purple-900 mb-4 flex items-center">
                <i class="fas fa-shipping-fast text-purple-600 mr-2"></i>
                Active Booking Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php if ($booking['tracking_number']): ?>
                <div class="bg-white rounded-lg p-4">
                    <span class="text-sm text-purple-700 block mb-1">Tracking Number</span>
                    <span class="font-mono font-bold text-purple-900"><?php echo htmlspecialchars($booking['tracking_number']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-lg p-4">
                    <span class="text-sm text-purple-700 block mb-1">Courier</span>
                    <span class="font-semibold text-purple-900"><?php echo htmlspecialchars($booking['courier_name']); ?></span>
                </div>
                
                <div class="bg-white rounded-lg p-4">
                    <span class="text-sm text-purple-700 block mb-1">Booking Status</span>
                    <span class="font-semibold text-purple-900 capitalize"><?php echo str_replace('_', ' ', $booking['booking_status']); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>