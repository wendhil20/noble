<?php
// delivery_date_orders.php
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
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Get all orders scheduled for delivery on the selected date
$ordersSql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivery_type,
    ds.delivery_status,
    ds.item_type,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.status as order_status,
    o.final_total,
    o.assigned_vehicle_id,
    o.assigned_vehicle_type,
    (SELECT COUNT(*) FROM order_items WHERE order_id = ds.order_id) as total_items,
    (SELECT SUM(quantity) FROM order_items WHERE order_id = ds.order_id) as total_quantity,
    (SELECT COUNT(*) FROM order_items WHERE order_id = ds.order_id AND tracking_status = 'ready_for_pickup') as ready_items,
    (SELECT COUNT(*) FROM order_items WHERE order_id = ds.order_id AND tracking_status = 'item_is_loaded') as loaded_items,
    tv.vehicle_type,
    tv.courier_name,
    db.id as booking_id,
    db.tracking_number,
    db.booking_status,
    db.courier_name,
    db.booking_reference,
    CASE 
        WHEN db.id IS NOT NULL THEN 'booked'
        WHEN ds.delivery_status = 'scheduled' THEN 'ready_for_booking'
        ELSE ds.delivery_status
    END as computed_status
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
LEFT JOIN transportify_vehicle_list tv ON o.assigned_vehicle_id = tv.id
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id
WHERE ds.delivery_date = ?
ORDER BY ds.delivery_time ASC, ds.order_id ASC";

$stmt = $conn->prepare($ordersSql);
$stmt->bind_param("s", $selectedDate);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics for this date
$totalOrders = count($orders);
$completedOrders = count(array_filter($orders, fn($o) => in_array($o['booking_status'], ['delivered', 'picked_up'])));
$pendingOrders = count(array_filter($orders, fn($o) => !in_array($o['booking_status'], ['delivered', 'picked_up'])));
$bookedOrders = count(array_filter($orders, fn($o) => $o['booking_id'] !== null));
$overdueOrders = count(array_filter($orders, fn($o) => $o['delivery_status'] === 'overdue' && $o['delivered_at'] === null));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliveries for <?php echo date('M d, Y', strtotime($selectedDate)); ?> - Noble Home</title>
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
        .order-card {
            transition: all 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <a href="main_dashboard.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-2">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Dashboard
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-calendar-day text-blue-600 mr-3"></i>
                        Deliveries for <?php echo date('l, F d, Y', strtotime($selectedDate)); ?>
                    </h1>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg border-2 border-blue-200 p-4">
                    <div class="flex items-center justify-between">
                        <i class="fas fa-shopping-cart text-blue-500 text-2xl"></i>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $totalOrders; ?></p>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">Total Orders</p>
                </div>
                
                <div class="bg-white rounded-lg border-2 border-green-200 p-4">
                    <div class="flex items-center justify-between">
                        <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $completedOrders; ?></p>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">Completed</p>
                </div>
                
                <div class="bg-white rounded-lg border-2 border-yellow-200 p-4">
                    <div class="flex items-center justify-between">
                        <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $pendingOrders; ?></p>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">Pending</p>
                </div>
                
                <?php if ($overdueOrders > 0): ?>
                <div class="bg-white rounded-lg border-2 border-red-200 p-4">
                    <div class="flex items-center justify-between">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $overdueOrders; ?></p>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">Overdue</p>
                </div>
                <div class="bg-white rounded-lg border-2 border-purple-200 p-4">
    <div class="flex items-center justify-between">
        <i class="fas fa-book text-purple-500 text-2xl"></i>
        <p class="text-3xl font-bold text-gray-900"><?php echo $bookedOrders; ?></p>
    </div>
    <p class="text-sm text-gray-600 mt-2">Booked</p>
</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-calendar-times text-6xl"></i>
                </div>
                <h3 class="text-2xl font-semibold text-gray-700 mb-2">No Deliveries Scheduled</h3>
                <p class="text-gray-500 mb-6">There are no deliveries scheduled for this date.</p>
                <a href="logistics_dashboard_view.php" class="inline-flex items-center bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Return to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-list mr-2"></i>
                        Orders List (<?php echo $totalOrders; ?>)
                    </h2>
                </div>
                
                <div class="divide-y divide-gray-200">
                    <?php foreach ($orders as $order): ?>
    <?php
$hasBooking = $order['booking_id'] !== null;
$isCompleted = in_array($order['booking_status'], ['delivered', 'picked_up']);
$isBooked = $order['booking_id'] !== null;
$canBook = !$hasBooking && in_array($order['computed_status'], ['ready_for_booking', 'scheduled']);
$isToday = $order['delivery_status'] === 'today_pending';
$isOverdue = $order['delivery_status'] === 'overdue';

if ($isCompleted) {
                                $statusClass = 'bg-green-100 text-green-800 border-green-200';
                                $statusIcon = 'fa-check-circle';
                                $statusText = 'Delivered';
                                $cardBg = 'bg-green-50';
                                $borderColor = 'border-green-200';
                            } elseif ($isOverdue) {
                                $statusClass = 'bg-red-100 text-red-800 border-red-200';
                                $statusIcon = 'fa-exclamation-triangle';
                                $statusText = 'Overdue (' . $order['days_overdue'] . ' days)';
                                $cardBg = 'bg-red-50';
                                $borderColor = 'border-red-200';
                            } elseif ($isToday) {
                                $statusClass = 'bg-blue-100 text-blue-800 border-blue-200';
                                $statusIcon = 'fa-clock';
                                $statusText = 'Due Today';
                                $cardBg = 'bg-blue-50';
                                $borderColor = 'border-blue-200';
                            } else {
                                $statusClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                $statusIcon = 'fa-clock';
                                $statusText = 'Scheduled';
                                $cardBg = 'bg-yellow-50';
                                $borderColor = 'border-yellow-200';
                            }
                        ?>
                        
                        <div class="order-card p-6 <?php echo $cardBg; ?> hover:bg-opacity-50">
    <div class="flex items-start justify-between mb-4">
        <div class="flex-1">
                                    <!-- Order Header -->
                                    <div class="flex items-center space-x-3 mb-3">
                                        <h3 class="text-xl font-bold text-gray-900">
                                            <i class="fas fa-shopping-cart mr-2 text-blue-600"></i>
                                            Order #<?php echo $order['order_id']; ?>
                                        </h3>
                                        
                                        <?php if ($order['item_type'] === 'replacement'): ?>
                                            <span class="bg-orange-500 text-white px-3 py-1 rounded-full text-xs font-bold">
                                                <i class="fas fa-exchange-alt mr-1"></i>REPLACEMENT
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="<?php echo $statusClass; ?> border px-3 py-1 rounded-full text-xs font-medium">
                                            <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
                                            <?php echo $statusText; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Customer Info -->
                                    <div class="bg-white bg-opacity-70 rounded-lg p-4 mb-4 border-2 <?php echo $borderColor; ?>">
                                        <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                                            <i class="fas fa-user-circle mr-2 text-blue-600"></i>
                                            Customer Information
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <span class="font-medium text-gray-600">Name:</span>
                                                <p class="text-gray-900 font-semibold"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                            </div>
                                            <?php if ($order['mobile']): ?>
                                            <div>
                                                <span class="font-medium text-gray-600">Mobile:</span>
                                                <p class="text-gray-900"><?php echo htmlspecialchars($order['mobile']); ?></p>
                                            </div>
                                            <?php endif; ?>
                                            <div class="md:col-span-2">
                                                <span class="font-medium text-gray-600">Address:</span>
                                                <p class="text-gray-900"><?php echo htmlspecialchars($order['address']); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Booking Information (if exists) -->
<?php if ($hasBooking): ?>
<div class="bg-purple-100 border-2 border-purple-300 rounded-lg p-4 mb-4">
    <h4 class="font-semibold text-purple-900 mb-3 flex items-center">
        <i class="fas fa-shipping-fast mr-2"></i>
        Booking Information
    </h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <?php if ($order['tracking_number']): ?>
        <div>
            <span class="font-medium text-purple-700">Tracking #:</span>
            <p class="text-purple-900 font-mono font-bold"><?php echo htmlspecialchars($order['tracking_number']); ?></p>
        </div>
        <?php endif; ?>
        <?php if ($order['courier_name']): ?>
        <div>
            <span class="font-medium text-purple-700">Courier:</span>
            <p class="text-purple-900 font-semibold"><?php echo htmlspecialchars($order['courier_name']); ?></p>
        </div>
        <?php endif; ?>
        <?php if ($order['booking_reference']): ?>
        <div class="md:col-span-2">
            <span class="font-medium text-purple-700">Reference:</span>
            <p class="text-purple-900"><?php echo htmlspecialchars($order['booking_reference']); ?></p>
        </div>
        <?php endif; ?>
        <div>
            <span class="font-medium text-purple-700">Booking Status:</span>
            <p class="text-purple-900 font-semibold capitalize">
                <?php echo str_replace('_', ' ', $order['booking_status']); ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Items Loading Status -->
<div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 mb-4">
    <h4 class="font-semibold text-blue-900 mb-3 flex items-center">
        <i class="fas fa-boxes mr-2"></i>
        Items Status
    </h4>
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-gray-900"><?php echo $order['total_items']; ?></div>
            <div class="text-xs text-gray-600 mt-1">Total Items</div>
        </div>
        <div class="bg-white rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-yellow-600"><?php echo $order['ready_items']; ?></div>
            <div class="text-xs text-gray-600 mt-1">Ready</div>
        </div>
        <div class="bg-white rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-green-600"><?php echo $order['loaded_items']; ?></div>
            <div class="text-xs text-gray-600 mt-1">Loaded</div>
        </div>
    </div>
</div>
                                    
                                    <!-- Order Details -->
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                        <div class="bg-white bg-opacity-70 p-3 rounded-lg border <?php echo $borderColor; ?>">
                                            <span class="text-xs text-gray-600">Delivery Time</span>
                                            <p class="font-semibold text-gray-900">
                                                <?php echo date('g:i A', strtotime($order['delivery_time'])); ?>
                                            </p>
                                        </div>
                                        <div class="bg-white bg-opacity-70 p-3 rounded-lg border <?php echo $borderColor; ?>">
                                            <span class="text-xs text-gray-600">Total Items</span>
                                            <p class="font-semibold text-gray-900"><?php echo $order['total_items']; ?></p>
                                        </div>
                                        <div class="bg-white bg-opacity-70 p-3 rounded-lg border <?php echo $borderColor; ?>">
                                            <span class="text-xs text-gray-600">Total Quantity</span>
                                            <p class="font-semibold text-gray-900"><?php echo $order['total_quantity']; ?></p>
                                        </div>
                                        <div class="bg-white bg-opacity-70 p-3 rounded-lg border <?php echo $borderColor; ?>">
                                            <span class="text-xs text-gray-600">Order Total</span>
                                            <p class="font-semibold text-gray-900">
                                                ₱<?php echo number_format($order['final_total'], 2); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Additional Info -->
                                    <div class="flex flex-wrap gap-4 text-sm">
                                        <?php if (isset($order['assigned_vehicle_type']) && $order['assigned_vehicle_type']): ?>
<div class="flex items-center text-gray-700">
    <i class="fas fa-truck mr-2 text-gray-500"></i>
    <span>Vehicle: <strong><?php echo htmlspecialchars($order['assigned_vehicle_type']); ?></strong></span>
</div>
<?php endif; ?>
                                    </div>
                                    
                                    <?php if ($order['delivery_notes']): ?>
                                    <div class="mt-3 bg-yellow-100 border border-yellow-300 rounded-lg p-3">
                                        <div class="flex items-start">
                                            <i class="fas fa-sticky-note text-yellow-600 mr-2 mt-1"></i>
                                            <div>
                                                <span class="font-semibold text-yellow-800">Notes:</span>
                                                <p class="text-gray-700 text-sm"><?php echo htmlspecialchars($order['delivery_notes']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Action Buttons -->
        <div class="mt-4 flex flex-wrap gap-3">
            <?php if ($canBook): ?>
                <a href="delivery_booking.php?schedule_id=<?php echo $order['delivery_id']; ?>&order_id=<?php echo $order['order_id']; ?>" 
                   class="inline-flex items-center bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-calendar-check mr-2"></i>
                    Book <?php echo $order['delivery_type'] === 'pickup' ? 'Pickup' : 'Delivery'; ?>
                </a>
            <?php elseif ($hasBooking && !$isCompleted): ?>
                <a href="delivery_tracking.php?booking_id=<?php echo $order['booking_id']; ?>" 
                   class="inline-flex items-center bg-gradient-to-r from-purple-500 to-purple-600 text-white px-6 py-3 rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-tasks mr-2"></i>
                    Manage <?php echo $order['delivery_type'] === 'pickup' ? 'Pickup' : 'Delivery'; ?>
                </a>
            <?php endif; ?>
            
            <a href="order_items_view.php?order_id=<?php echo $order['order_id']; ?>" 
               class="inline-flex items-center bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                <i class="fas fa-list mr-2"></i>
                View Items (<?php echo $order['total_items']; ?>)
            </a>
        </div>
    </div>
</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>