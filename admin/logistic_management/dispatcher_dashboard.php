<?php
// dispatcher_dashboard.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Check if user is a dispatcher
$user_subrole = $_SESSION['noble_subrole'] ?? '';
if ($user_subrole !== 'dispatcher') {
    header("Location: logistics_dashboard_view.php");
    exit();
}

$dispatcher_id = $_SESSION['noble_id'];
$dispatcher_name = $_SESSION['noble_name'] ?? 'Dispatcher';

// Get assigned bookings
$sql = "SELECT 
    db.*,
    ds.delivery_date,
    ds.delivery_time,
    o.customer_name,
    o.address,
    o.final_total,
    tv.courier_name,
    (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id) as total_items,
    (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id AND tracking_status = 'item_is_loaded') as loaded_items
FROM delivery_bookings db
INNER JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
INNER JOIN orders o ON db.order_id = o.id
LEFT JOIN transportify_vehicle_list tv ON db.vehicle_id = tv.id
WHERE db.dispatcher_id = ?
AND db.booking_status NOT IN ('delivered', 'picked_up', 'cancelled')
ORDER BY ds.delivery_date ASC, ds.delivery_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dispatcher_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count by status
$confirmed_count = 0;
$in_transit_count = 0;
foreach ($bookings as $booking) {
    if ($booking['booking_status'] === 'confirmed') $confirmed_count++;
    if ($booking['booking_status'] === 'in_transit') $in_transit_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assigned Deliveries - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .booking-card {
            transition: all 0.3s ease;
        }
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-truck text-blue-600 mr-3"></i>
                My Assigned Deliveries
            </h1>
            <p class="text-gray-600 mt-2">Welcome, <?php echo htmlspecialchars($dispatcher_name); ?></p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Assigned</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo count($bookings); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Ready to Load</p>
                        <p class="text-3xl font-bold text-yellow-600"><?php echo $confirmed_count; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-box text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">In Transit</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $in_transit_count; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-shipping-fast text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bookings List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">Active Deliveries</h2>
            </div>
            
            <div class="p-6">
                <?php if (empty($bookings)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                        <p class="text-gray-500 text-lg">No deliveries assigned yet</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $status_color = 'yellow';
                            $status_icon = 'clock';
                            if ($booking['booking_status'] === 'in_transit') {
                                $status_color = 'green';
                                $status_icon = 'shipping-fast';
                            }
                            $all_loaded = $booking['loaded_items'] === $booking['total_items'] && $booking['total_items'] > 0;
                            ?>
                            <div class="booking-card border-2 border-gray-200 rounded-lg p-4 hover:border-blue-300">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800 px-3 py-1 rounded-full text-sm font-semibold">
                                                <i class="fas fa-<?php echo $status_icon; ?> mr-1"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $booking['booking_status'])); ?>
                                            </span>
                                            <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-semibold">
                                                <?php echo ucfirst($booking['booking_type']); ?>
                                            </span>
                                            <?php if ($all_loaded): ?>
                                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                All Items Loaded
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <h3 class="text-lg font-bold text-gray-900 mb-1">
                                            Order #<?php echo $booking['order_id']; ?> - Booking #<?php echo $booking['id']; ?>
                                        </h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600 mb-3">
                                            <div>
                                                <i class="fas fa-user mr-2 text-gray-400"></i>
                                                <?php echo htmlspecialchars($booking['customer_name']); ?>
                                            </div>
                                            <div>
                                                <i class="fas fa-calendar mr-2 text-gray-400"></i>
                                                <?php echo date('M d, Y', strtotime($booking['delivery_date'])); ?> 
                                                at <?php echo date('g:i A', strtotime($booking['delivery_time'])); ?>
                                            </div>
                                            <div>
                                                <i class="fas fa-boxes mr-2 text-gray-400"></i>
                                                <?php echo $booking['total_items']; ?> items 
                                                (<?php echo $booking['loaded_items']; ?> loaded)
                                            </div>
                                            <div>
                                                <i class="fas fa-truck mr-2 text-gray-400"></i>
                                                <?php echo htmlspecialchars($booking['courier_name']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-sm text-gray-600">
                                            <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                                            <?php echo htmlspecialchars($booking['address']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="ml-4">
                                        <a href="dispatcher_view_booking.php?booking_id=<?php echo $booking['id']; ?>" 
                                           class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors font-semibold inline-flex items-center">
                                            <i class="fas fa-eye mr-2"></i>
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>