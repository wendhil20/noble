<?php
// dispatcher_dashboard.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

// Check if user is a dispatcher
$user_subrole = $_SESSION['noble_subrole'] ?? '';
if ($user_subrole !== 'dispatcher') {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

$dispatcher_id = $_SESSION['noble_id'];
$dispatcher_name = $_SESSION['noble_name'] ?? 'Dispatcher';

// Get assigned bookings
$sql = "SELECT 
    db.*,
    ds.delivery_date,
    ds.delivery_time,
    ds.item_type,
    o.customer_name,
    o.address,
    o.final_total,
    tv.courier_name,
    -- Count items: if replacement, count replacement_requests, else count order_items
    CASE 
        WHEN ds.item_type = 'replacement' THEN 
            (SELECT COUNT(*) FROM replacement_requests WHERE delivery_schedule_id = ds.id)
        ELSE (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id)
    END as total_items,
    -- Count loaded items: if replacement, check replacement_requests status, else check order_items
    CASE 
        WHEN ds.item_type = 'replacement' THEN 
            (SELECT COUNT(*) FROM replacement_requests 
             WHERE delivery_schedule_id = ds.id 
             AND status IN ('item_is_loaded', 'out_for_delivery'))
        ELSE (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id AND tracking_status = 'item_is_loaded')
    END as loaded_items
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
    
    <style>
        .booking-card {
            transition: all 0.3s ease;
        }

        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        /* Mobile optimizations */
        @media (max-width: 640px) {
            .booking-card:hover {
                transform: none;
            }

            /* Better text wrapping on mobile */
            .break-words {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }

            /* Prevent horizontal scroll */
            body {
                overflow-x: hidden;
            }
        }

        /* Ensure badges don't break layout */
        .whitespace-nowrap {
            white-space: nowrap;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-6 md:py-8">

        <!-- Header -->
        <div class="mb-6 sm:mb-8">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-900 flex items-center flex-wrap gap-2">
                <i class="fas fa-truck text-blue-600"></i>
                <span>My Assigned Deliveries</span>
            </h1>
            <p class="text-sm sm:text-base text-gray-600 mt-2">Welcome, <?php echo htmlspecialchars($dispatcher_name); ?></p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 md:gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm text-gray-600 mb-1">Total Assigned</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900"><?php echo count($bookings); ?></p>
                    </div>
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-clipboard-list text-blue-600 text-lg sm:text-xl"></i>
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
            <div class="p-4 sm:p-6 border-b border-gray-200">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900">Active Deliveries</h2>
            </div>

            <div class="p-4 sm:p-6">
                <?php if (empty($bookings)): ?>
                    <div class="text-center py-8 sm:py-12 px-4">
                        <i class="fas fa-inbox text-gray-300 text-4xl sm:text-6xl mb-4"></i>
                        <p class="text-gray-500 text-base sm:text-lg">No deliveries assigned yet</p>
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
                            <div class="booking-card border-2 border-gray-200 rounded-lg p-3 sm:p-4 hover:border-blue-300">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
                                    <div class="flex-1">
                                        <!-- Status Badges -->
                                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                                            <span class="bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-semibold whitespace-nowrap">
                                                <i class="fas fa-<?php echo $status_icon; ?> mr-1"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $booking['booking_status'])); ?>
                                            </span>
                                            <span class="bg-purple-100 text-purple-800 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-semibold whitespace-nowrap">
                                                <?php echo ucfirst($booking['booking_type']); ?>
                                            </span>
                                            <?php if ($all_loaded): ?>
                                                <span class="bg-green-100 text-green-800 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-semibold whitespace-nowrap">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    All Items Loaded
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Order Title -->
                                        <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2 flex items-center flex-wrap gap-2">
                                            <span class="break-all">Order #<?php echo $booking['order_id']; ?> - Booking #<?php echo $booking['id']; ?></span>
                                            <?php if ($booking['item_type'] === 'replacement'): ?>
                                                <span class="bg-orange-100 text-orange-800 px-2 sm:px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap">
                                                    <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT
                                                </span>
                                            <?php endif; ?>
                                        </h3>

                                        <!-- Details Grid -->
                                        <div class="grid grid-cols-1 gap-2 text-xs sm:text-sm text-gray-600 mb-3">
                                            <div class="flex items-start gap-2">
                                                <i class="fas fa-user mt-0.5 text-gray-400 flex-shrink-0"></i>
                                                <span class="break-words"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <i class="fas fa-calendar mt-0.5 text-gray-400 flex-shrink-0"></i>
                                                <span class="break-words">
                                                    <?php echo date('M d, Y', strtotime($booking['delivery_date'])); ?>
                                                    at <?php echo date('g:i A', strtotime($booking['delivery_time'])); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <i class="fas fa-boxes mt-0.5 text-gray-400 flex-shrink-0"></i>
                                                <span><?php echo $booking['total_items']; ?> items (<?php echo $booking['loaded_items']; ?> loaded)</span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <i class="fas fa-truck mt-0.5 text-gray-400 flex-shrink-0"></i>
                                                <span class="break-words"><?php echo htmlspecialchars($booking['courier_name']); ?></span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <i class="fas fa-map-marker-alt mt-0.5 text-gray-400 flex-shrink-0"></i>
                                                <span class="break-words"><?php echo htmlspecialchars($booking['address']); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Button -->
                                    <div class="w-full sm:w-auto sm:ml-4">
                                        <a href="logistic-dispatcher-view-booking-page-12.php?booking_id=<?php echo $booking['id']; ?>"
                                            class="bg-blue-500 text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors font-semibold inline-flex items-center justify-center w-full sm:w-auto text-sm sm:text-base">
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