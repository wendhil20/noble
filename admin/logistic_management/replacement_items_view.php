<?php
// replacement_items_view.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

// Redirect dispatchers to their own dashboard
if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'dispatcher') {
    header("Location: dispatcher_dashboard.php");
    exit();
}

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
$scheduleSql = "SELECT 
    ds.*,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.final_total,
    o.status as order_status,
    o.delivery_type as order_delivery_type,
    o.created_at as order_created_at
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

// Get ALL replacement requests for this delivery schedule
$replacementsSql = "SELECT 
    rr.*,
    oi.product_name,
    oi.variant_color,
    oi.size,
    oi.price,
    oi.codename,
    oi.type_name,
    oi.warehouse_location,
    CASE 
        WHEN rr.status = 'pending' THEN 'Pending'
        WHEN rr.status = 'approved' THEN 'Approved'
        WHEN rr.status = 'ready_for_pickup' THEN 'Ready for Pickup'
        WHEN rr.status = 'item_is_loaded' THEN 'Item is Loaded'
        WHEN rr.status = 'out_for_delivery' THEN 'Out for Delivery'
        WHEN rr.status = 'delivered' THEN 'Delivered'
        WHEN rr.status = 'picked_up' THEN 'Picked Up'
        ELSE 'Unknown'
    END as status_display
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

// Get booking info if exists
$bookingSql = "SELECT * FROM delivery_bookings WHERE delivery_schedule_id = ? LIMIT 1";
$bookingStmt = $conn->prepare($bookingSql);
$bookingStmt->bind_param("i", $schedule_id);
$bookingStmt->execute();
$booking = $bookingStmt->get_result()->fetch_assoc();
$bookingStmt->close();

// Calculate statistics
$totalItems = count($replacements);
$totalQuantity = array_sum(array_column($replacements, 'replacement_quantity'));
$totalValue = 0;
foreach ($replacements as $replacement) {
    $totalValue += $replacement['price'] * $replacement['replacement_quantity'];
}

$statusCounts = [
    'pending' => 0,
    'approved' => 0,
    'ready_for_pickup' => 0,
    'item_is_loaded' => 0,
    'out_for_delivery' => 0,
    'delivered' => 0,
    'picked_up' => 0
];

foreach ($replacements as $item) {
    if (isset($statusCounts[$item['status']])) {
        $statusCounts[$item['status']]++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Replacement Items - Order #<?php echo $order_id; ?> - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .item-row {
            transition: all 0.2s ease;
        }
        .item-row:hover {
            background-color: #fff7ed;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-orange-50 to-orange-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <?php if ($schedule['delivery_date']): ?>
            <a href="delivery_date_orders.php?date=<?php echo $schedule['delivery_date']; ?>" 
               class="inline-flex items-center text-orange-600 hover:text-orange-800 mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Orders
            </a>
            <?php else: ?>
            <a href="logistics_dashboard_view.php" 
               class="inline-flex items-center text-orange-600 hover:text-orange-800 mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
            <?php endif; ?>
            
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-sync-alt text-orange-600 mr-3"></i>
                        Replacement Items
                    </h1>
                    <p class="text-gray-600 mt-2">
                        Order #<?php echo $order_id; ?> - Schedule #<?php echo $schedule_id; ?> - <?php echo htmlspecialchars($schedule['customer_name']); ?>
                        <span class="ml-2 bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-semibold">
                            <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT
                        </span>
                    </p>
                </div>
                
                <?php if ($booking): ?>
                <a href="delivery_tracking.php?booking_id=<?php echo $booking['id']; ?>" 
                   class="bg-gradient-to-r from-orange-500 to-orange-600 text-white px-6 py-3 rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-tasks mr-2"></i>
                    Manage Delivery
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
            <div class="bg-white rounded-lg border-2 border-orange-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-orange-600"><?php echo $totalItems; ?></p>
                    <p class="text-xs text-gray-600 mt-1">Total Items</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg border-2 border-orange-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-orange-600"><?php echo $totalQuantity; ?></p>
                    <p class="text-xs text-gray-600 mt-1">Total Qty</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg border-2 border-gray-200 p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-600"><?php echo $statusCounts['pending'] + $statusCounts['approved']; ?></p>
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

        <!-- Replacement Summary Banner -->
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-lg border border-orange-300 p-6 mb-8">
            <div class="flex items-center justify-between text-white">
                <div>
                    <h2 class="text-2xl font-bold mb-2">Replacement Summary</h2>
                    <p class="text-orange-100">Schedule for <?php echo date('l, F d, Y', strtotime($schedule['delivery_date'])); ?> at <?php echo date('g:i A', strtotime($schedule['delivery_time'])); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-4xl font-bold">₱<?php echo number_format($totalValue, 2); ?></p>
                    <p class="text-orange-100 text-sm">Total Replacement Value</p>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-200 bg-orange-50">
                <h2 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-sync-alt text-orange-600 mr-2"></i>
                    Replacement Items List (<?php echo $totalItems; ?>)
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
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Replacement Info
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
                        <?php foreach ($replacements as $item): ?>
                            <?php
                                $statusColors = [
                                    'pending' => 'bg-gray-100 text-gray-800 border-gray-300',
                                    'approved' => 'bg-gray-100 text-gray-800 border-purple-300',
                                    'ready_for_pickup' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                    'item_is_loaded' => 'bg-blue-100 text-blue-800 border-blue-300',
                                    'out_for_delivery' => 'bg-purple-100 text-purple-800 border-purple-300',
                                    'delivered' => 'bg-green-100 text-green-800 border-green-300',
                                    'picked_up' => 'bg-green-100 text-green-800 border-green-300'
                                ];
                                
                                $statusIcons = [
                                    'pending' => 'fa-clock',
                                    'approved' => 'fa-check',
                                    'ready_for_pickup' => 'fa-box',
                                    'item_is_loaded' => 'fa-check-circle',
                                    'out_for_delivery' => 'fa-truck',
                                    'delivered' => 'fa-check-double',
                                    'picked_up' => 'fa-check-double'
                                ];
                                
                                $colorClass = $statusColors[$item['status']] ?? 'bg-gray-100 text-gray-800 border-gray-300';
                                $iconClass = $statusIcons[$item['status']] ?? 'fa-question';
                            ?>
                            <tr class="item-row">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <?php if ($item['codename']): ?>
                                    <div class="text-sm text-gray-500 mt-1">Code: <?php echo htmlspecialchars($item['codename']); ?></div>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-semibold">
                                            <i class="fas fa-sync-alt mr-1"></i>Request #<?php echo $item['id']; ?>
                                        </span>
                                    </div>
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
                                        <?php if ($item['warehouse_location']): ?>
                                        <div class="flex items-center mt-2">
                                            <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>
                                            <span class="font-medium">Location:</span>
                                            <span class="ml-1 bg-yellow-100 px-2 py-0.5 rounded text-xs"><?php echo htmlspecialchars($item['warehouse_location']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm space-y-1">
                                        <div class="bg-red-50 border border-red-200 rounded p-2">
                                            <span class="text-xs font-semibold text-red-700">Reason:</span>
                                            <p class="text-red-900 capitalize"><?php echo str_replace('_', ' ', $item['reason']); ?></p>
                                        </div>
                                        <?php if ($item['details']): ?>
                                        <div class="bg-blue-50 border border-blue-200 rounded p-2 mt-1">
                                            <span class="text-xs font-semibold text-blue-700">Details:</span>
                                            <p class="text-blue-900 text-xs"><?php echo nl2br(htmlspecialchars($item['details'])); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-lg font-bold text-orange-600"><?php echo $item['replacement_quantity']; ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-semibold text-gray-900">₱<?php echo number_format($item['price'], 2); ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-lg font-bold text-blue-600">₱<?php echo number_format($item['price'] * $item['replacement_quantity'], 2); ?></span>
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
                    <tfoot class="bg-orange-50 border-t-2 border-orange-300">
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-right font-bold text-gray-900">
                                Replacement Total:
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-2xl font-bold text-orange-600">₱<?php echo number_format($totalValue, 2); ?></span>
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
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($schedule['customer_name']); ?></span>
                    </div>
                    <?php if ($schedule['email']): ?>
                    <div>
                        <span class="text-gray-600 block mb-1">Email:</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($schedule['email']); ?></span>
                    </div>
                    <?php endif; ?>
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
            
            <!-- Replacement Schedule Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-sync-alt text-orange-600 mr-2"></i>
                    Replacement Schedule
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Order ID:</span>
                        <span class="font-semibold">#<?php echo $order_id; ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Schedule ID:</span>
                        <span class="font-semibold">#<?php echo $schedule_id; ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Order Status:</span>
                        <span class="font-semibold"><?php echo $schedule['order_status']; ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Order Date:</span>
                        <span class="font-semibold"><?php echo date('M d, Y g:i A', strtotime($schedule['order_created_at'])); ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Scheduled Date:</span>
                        <span class="font-semibold"><?php echo date('M d, Y', strtotime($schedule['delivery_date'])); ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Scheduled Time:</span>
                        <span class="font-semibold"><?php echo date('g:i A', strtotime($schedule['delivery_time'])); ?></span>
                    </div>
                    <div class="flex justify-between pb-2 border-b">
                        <span class="text-gray-600">Delivery Type:</span>
                        <span class="font-semibold capitalize"><?php echo $schedule['order_delivery_type']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Items:</span>
                        <span class="font-semibold text-orange-600"><?php echo $totalItems; ?> items (<?php echo $totalQuantity; ?> pcs)</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($booking): ?>
        <!-- Booking Information -->
        <div class="bg-orange-50 rounded-xl shadow-sm border border-orange-200 p-6 mt-6">
            <h3 class="text-lg font-bold text-orange-900 mb-4 flex items-center">
                <i class="fas fa-shipping-fast text-orange-600 mr-2"></i>
                Active Replacement Booking
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php if ($booking['tracking_number']): ?>
                <div class="bg-white rounded-lg p-4">
                    <span class="text-sm text-orange-700 block mb-1">Tracking Number</span>
                    <span class="font-mono font-bold text-orange-900"><?php echo htmlspecialchars($booking['tracking_number']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-lg p-4">
                    <span class="text-sm text-orange-700 block mb-1">Courier</span>
                    <span class="font-semibold text-orange-900"><?php echo htmlspecialchars($booking['courier_name']); ?></span>
                </div>
                
                <div class="bg-white rounded-lg p-4">
                    <span class="text-sm text-orange-700 block mb-1">Booking Status</span>
                    <span class="font-semibold text-orange-900 capitalize"><?php echo str_replace('_', ' ', $booking['booking_status']); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>