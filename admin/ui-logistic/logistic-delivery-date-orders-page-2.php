<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'dispatcher') {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location:" . BASE_URL . "/main");
    exit();
}

$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    header("Location:  " . BASE_URL . "/logistic");
    exit();
}

// SIMPLIFIED: No subqueries, no replacement_requests JOIN
$ordersSql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivery_status,
    ds.item_type,
    o.customer_name,
    o.delivery_type,
    o.email,
    o.mobile,
    o.address,
    o.status as order_status,
    o.final_total,
    o.delivery_fee,
    o.total_weight_kg,
    o.total_cubic_meters,
    o.assigned_vehicle_id,
    o.assigned_vehicle_type,
    tv.vehicle_type,
    tv.courier_name,
    db.id as booking_id,
    db.tracking_number,
    db.booking_status,
    db.courier_name as booking_courier_name,
    db.booking_reference,
    CASE 
        WHEN db.id IS NOT NULL THEN 'booked'
        WHEN ds.delivery_status = 'scheduled' THEN 'ready_for_booking'
        ELSE ds.delivery_status
    END as computed_status,
    (SELECT MIN(lt_from) FROM order_items WHERE order_id = o.id AND lt_from IS NOT NULL) as earliest_delivery,
    (SELECT MAX(lt_to) FROM order_items WHERE order_id = o.id AND lt_to IS NOT NULL) as latest_delivery
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
LEFT JOIN transportify_vehicle_list tv ON o.assigned_vehicle_id = tv.id
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id
WHERE ds.delivery_date = ?
ORDER BY ds.delivery_time ASC, ds.order_id ASC";

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

$stmt = $conn->prepare($ordersSql);
$stmt->bind_param("s", $selectedDate);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];

while ($row = $result->fetch_assoc()) {
    // GET ITEM COUNTS IN PHP
    $countSql = "SELECT COUNT(*) as total_items, IFNULL(SUM(quantity), 0) as total_quantity FROM order_items WHERE order_id = ?";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("i", $row['order_id']);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    $row['total_items'] = $countResult['total_items'] ?? 0;
    $row['total_quantity'] = $countResult['total_quantity'] ?? 0;

    // GET REPLACEMENT DATA IN PHP
    if ($row['item_type'] === 'replacement') {
        $repSql = "SELECT reason, details, replacement_quantity, status FROM replacement_requests WHERE delivery_schedule_id = ? LIMIT 1";
        $repStmt = $conn->prepare($repSql);
        $repStmt->bind_param("i", $row['delivery_id']);
        $repStmt->execute();
        $repResult = $repStmt->get_result()->fetch_assoc();
        $repStmt->close();

        $row['replacement_reason'] = $repResult['reason'] ?? null;
        $row['replacement_details'] = $repResult['details'] ?? null;
        $row['replacement_quantity'] = $repResult['replacement_quantity'] ?? null;
        $row['replacement_status'] = $repResult['status'] ?? null;
    } else {
        $row['replacement_reason'] = null;
        $row['replacement_details'] = null;
        $row['replacement_quantity'] = null;
        $row['replacement_status'] = null;
    }

    // APPLY SEARCH FILTER IN PHP
    if (
        $searchQuery === '' ||
        strpos((string) $row['order_id'], $searchQuery) !== false ||
        stripos($row['customer_name'], $searchQuery) !== false ||
        stripos($row['tracking_number'] ?? '', $searchQuery) !== false
    ) {
        $orders[] = $row;
    }
}

$stmt->close();

// Get statistics
$totalOrders = count($orders);
$deliveryOrders = count(array_filter($orders, fn($o) => $o['delivery_type'] === 'delivery'));
$pickupOrders = count(array_filter($orders, fn($o) => $o['delivery_type'] === 'pickup'));
$completedOrders = count(array_filter($orders, fn($o) => in_array($o['booking_status'], ['delivered', 'picked_up'])));
$pendingOrders = count(array_filter($orders, fn($o) => !in_array($o['booking_status'], ['delivered', 'picked_up'])));
$bookedOrders = count(array_filter($orders, fn($o) => $o['booking_id'] !== null));
$overdueOrders = count(array_filter($orders, fn($o) => $o['delivery_status'] === 'overdue'));

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliveries for <?php echo date('M d, Y', strtotime($selectedDate)); ?> - Noble Home</title>
    <style>
        @keyframes slide-in {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .animate-slide-in {
            animation: slide-in 0.3s ease-out;
        }


        .filter-btn {
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            transform: scale(1.05);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-[1920px] mx-auto px-6 lg:px-12 py-6">

        <!-- Header -->
        <div class="mb-6">
            <a href="<?= BASE_URL; ?>/logistic"
                class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-3 text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-calendar-day text-blue-600 mr-3"></i>
                Deliveries for <?php echo date('l, F d, Y', strtotime($selectedDate)); ?>
            </h1>
        </div>

        <!-- Search Bar -->
        <div class="mb-6">
            <form method="GET" action="" class="relative">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                <div class="relative flex items-center">
                    <!-- Search Icon -->
                    <i class="fas fa-search absolute left-4 text-gray-400 text-base pointer-events-none z-10"></i>

                    <!-- Input -->
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>"
                        placeholder="Search by Order ID, Customer Name, or Tracking Number..."
                        class="w-full pl-11 pr-32 py-3.5 bg-white border-2 border-gray-200 rounded-xl text-sm text-gray-800 placeholder-gray-400
                       focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 shadow-sm">

                    <!-- Right side actions -->
                    <div class="absolute right-2 flex items-center gap-1">
                        <?php if ($searchQuery): ?>
                            <a href="?date=<?php echo $selectedDate; ?>"
                                class="text-gray-400 hover:text-gray-600 transition-colors p-1.5 hover:bg-gray-100 rounded-lg">
                                <i class="fas fa-times text-sm"></i>
                            </a>
                        <?php endif; ?>

                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-semibold
                           px-4 py-2 rounded-lg transition-colors duration-150 flex items-center gap-2 shadow-sm">
                            <i class="fas fa-search text-xs"></i>
                            Search
                        </button>
                    </div>
                </div>

                <!-- Result Count Badge -->
                <?php if ($searchQuery): ?>
                    <div class="flex items-center gap-2 mt-2.5 ml-1">
                        <span
                            class="inline-flex items-center gap-1.5 bg-blue-50 border border-blue-200 text-blue-700 text-xs font-medium px-3 py-1 rounded-full">
                            <i class="fas fa-filter text-blue-400"></i>
                            Results for: <strong><?php echo htmlspecialchars($searchQuery); ?></strong>
                        </span>
                        <span class="text-xs text-gray-400"><?php echo $totalOrders; ?> order(s) found</span>
                    </div>
                <?php endif; ?>
            </form>
        </div>


        <!-- Filter Buttons -->
        <div class="mb-6">
            <div class="flex border-b border-gray-200">
                <button onclick="filterOrders('all')"
                    class="filter-btn active relative px-6 py-3 text-sm font-semibold text-blue-600 border-b-2 border-blue-600 flex items-center gap-2 transition-all duration-200"
                    id="filter-all">
                    <i class="fas fa-list text-black"></i>
                    All Orders
                    <span
                        class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs font-bold"><?php echo $totalOrders; ?></span>
                </button>

                <button onclick="filterOrders('delivery')"
                    class="filter-btn relative px-6 py-3 text-sm font-semibold text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 flex items-center gap-2 transition-all duration-200"
                    id="filter-delivery">
                    <i class="fas fa-truck text-black"></i>
                    Delivery
                    <span
                        class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs font-bold"><?php echo $deliveryOrders; ?></span>
                </button>

                <button onclick="filterOrders('pickup')"
                    class="filter-btn relative px-6 py-3 text-sm font-semibold text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 flex items-center gap-2 transition-all duration-200"
                    id="filter-pickup">
                    <i class="fas fa-hand-holding text-black"></i>
                    Pickup
                    <span
                        class="bg-green-500 text-white px-2 py-0.5 rounded-full text-xs font-bold"><?php echo $pickupOrders; ?></span>
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <i class="fas fa-shopping-cart text-blue-500 text-xl"></i>
                    <p class="text-2xl font-bold text-gray-900" id="stat-total"><?php echo $totalOrders; ?></p>
                </div>
                <p class="text-xs text-gray-600 mt-2">Total Orders</p>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    <p class="text-2xl font-bold text-gray-900" id="stat-completed"><?php echo $completedOrders; ?></p>
                </div>
                <p class="text-xs text-gray-600 mt-2">Completed</p>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <i class="fas fa-book text-purple-500 text-xl"></i>
                    <p class="text-2xl font-bold text-gray-900" id="stat-booked"><?php echo $bookedOrders; ?></p>
                </div>
                <p class="text-xs text-gray-600 mt-2">Booked</p>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <i class="fas fa-clock text-yellow-500 text-xl"></i>
                    <p class="text-2xl font-bold text-gray-900" id="stat-pending"><?php echo $pendingOrders; ?></p>
                </div>
                <p class="text-xs text-gray-600 mt-2">Pending</p>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                    <p class="text-2xl font-bold text-gray-900" id="stat-overdue"><?php echo $overdueOrders; ?></p>
                </div>
                <p class="text-xs text-gray-600 mt-2">Overdue</p>
            </div>
        </div>

        <!-- Orders Table -->
        <?php if (empty($orders)): ?>

            <div
                class="flex flex-col items-center justify-center bg-white rounded-xl border border-gray-200 py-20 px-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-calendar-times text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-700 mb-1">No Deliveries Scheduled</h3>
                <p class="text-sm text-gray-400">There are no deliveries scheduled for this date.</p>
            </div>

        <?php else: ?>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Order</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Tracking</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Customer</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Type</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Time</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Expected Delivery</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Items</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Weight / Vol</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Total</th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $hasBooking = $order['booking_id'] !== null;
                                $isCompleted = in_array($order['booking_status'], ['delivered', 'picked_up']);
                                $canBook = !$hasBooking && in_array($order['computed_status'], ['ready_for_booking', 'scheduled']);
                                $isReplacement = $order['item_type'] === 'replacement';

                                // Status badge
                                if ($isCompleted) {
                                    $statusBadge = '<span class="inline-flex items-center gap-1 bg-green-50 text-green-700 border border-green-200 px-2.5 py-1 rounded-full text-xs font-semibold"><i class="fas fa-check-circle"></i>Delivered</span>';
                                } elseif ($order['delivery_status'] === 'overdue') {
                                    $statusBadge = '<span class="inline-flex items-center gap-1 bg-red-50 text-red-700 border border-red-200 px-2.5 py-1 rounded-full text-xs font-semibold"><i class="fas fa-exclamation-triangle"></i>Overdue</span>';
                                } elseif ($hasBooking) {
                                    $statusBadge = '<span class="inline-flex items-center gap-1 bg-purple-50 text-purple-700 border border-purple-200 px-2.5 py-1 rounded-full text-xs font-semibold"><i class="fas fa-shipping-fast"></i>Booked</span>';
                                } else {
                                    $statusBadge = '<span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 rounded-full text-xs font-semibold"><i class="fas fa-clock"></i>Scheduled</span>';
                                }

                                // Type badge
                                $typeBadge = $order['delivery_type'] === 'pickup'
                                    ? '<span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-1 rounded-full text-xs font-semibold"><i class="fas fa-hand-holding"></i>Pickup</span>'
                                    : '<span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full text-xs font-semibold"><i class="fas fa-truck"></i>Delivery</span>';

                                // Booking URL
                                $bookingUrl = $isReplacement
                                    ? "<?= BASE_URL; ?>/logisticreplacementbook?schedule_id={$order['delivery_id']}&order_id={$order['order_id']}"
                                    : "<?= BASE_URL; ?>/logisticdeliverybook?schedule_id={$order['delivery_id']}&order_id={$order['order_id']}";
                                ?>

                                <tr class="order-row hover:bg-gray-50 transition-colors duration-100 cursor-pointer"
                                    data-delivery-type="<?php echo $order['delivery_type']; ?>"
                                    onclick="openDetailsModal(<?php echo htmlspecialchars(json_encode($order)); ?>)">

                                    <!-- Order -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex-shrink-0 w-9 h-9 bg-blue-50 border border-blue-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-shopping-cart text-blue-500 text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-900">#<?php echo $order['order_id']; ?></div>
                                                <?php if ($isReplacement): ?>
                                                    <span
                                                        class="inline-block mt-0.5 text-xs bg-orange-50 text-orange-700 border border-orange-200 px-1.5 py-0.5 rounded font-medium">Replacement</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Tracking -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <?php if ($order['tracking_number']): ?>
                                            <div
                                                class="inline-flex items-center gap-1.5 font-mono text-xs bg-purple-50 text-purple-800 border border-purple-200 px-2.5 py-1.5 rounded-lg">
                                                <i class="fas fa-barcode text-purple-400"></i>
                                                <?php echo htmlspecialchars($order['tracking_number']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">Not booked</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Customer -->
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($order['customer_name']); ?></div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            <?php echo htmlspecialchars($order['mobile']); ?></div>
                                    </td>

                                    <!-- Type -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <?php echo $typeBadge; ?>
                                    </td>

                                    <!-- Time -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span
                                            class="font-semibold text-gray-800"><?php echo date('g:i A', strtotime($order['delivery_time'])); ?></span>
                                    </td>

                                    <!-- Expected Delivery -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <?php if ($order['earliest_delivery'] && $order['latest_delivery']): ?>
                                            <div class="border-l-4 border-blue-400 bg-blue-50 rounded-r-lg px-3 py-1.5 text-xs">
                                                <span
                                                    class="text-gray-600 font-medium"><?php echo date('M d', strtotime($order['earliest_delivery'])); ?></span>
                                                <span class="text-gray-400 mx-1">–</span>
                                                <span
                                                    class="text-blue-700 font-bold"><?php echo date('M d, Y', strtotime($order['latest_delivery'])); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">Not set</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Items -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900"><?php echo $order['total_items']; ?> <span
                                                class="font-normal text-gray-500">items</span></div>
                                        <div class="text-xs text-gray-400 mt-0.5"><?php echo $order['total_quantity']; ?> pcs
                                            total</div>
                                    </td>

                                    <!-- Weight / Volume -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="space-y-1 text-xs text-gray-700">
                                            <div class="flex items-center gap-1.5">
                                                <i class="fas fa-weight text-orange-400 w-3 text-center"></i>
                                                <?php echo number_format($order['total_weight_kg'] ?? 0, 2); ?> kg
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <i class="fas fa-cube text-orange-400 w-3 text-center"></i>
                                                <?php echo number_format($order['total_cubic_meters'] ?? 0, 3); ?> m³
                                            </div>
                                            <?php if ($isReplacement): ?>
                                                <div class="text-orange-500 italic">(Replacement)</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Total -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span
                                            class="font-bold text-green-600">₱<?php echo number_format($order['final_total'], 2); ?></span>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <?php echo $statusBadge; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">

                                            <?php if ($canBook): ?>
                                                <a href="<?php echo $bookingUrl; ?>" onclick="event.stopPropagation()"
                                                    title="Book <?php echo $isReplacement ? 'Replacement' : 'Delivery'; ?>"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-blue-600 hover:bg-blue-50 hover:border-blue-300 transition-colors duration-150">
                                                    <i class="fas fa-calendar-check text-sm"></i>
                                                </a>
                                            <?php elseif ($hasBooking && !$isCompleted): ?>
                                                <a href="<?= BASE_URL ?>/logisticdeliverytrack?booking_id=<?php echo $order['booking_id']; ?>"
                                                    onclick="event.stopPropagation()" title="Manage Delivery"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-purple-600 hover:bg-purple-50 hover:border-purple-300 transition-colors duration-150">
                                                    <i class="fas fa-tasks text-sm"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($isReplacement): ?>
                                                <a href="<?= BASE_URL ?>/logisticreplacementitemsview?schedule_id=<?php echo $order['delivery_id']; ?>&order_id=<?php echo $order['order_id']; ?>"
                                                    onclick="event.stopPropagation()" title="View Replacement Items"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-orange-600 hover:bg-orange-50 hover:border-orange-300 transition-colors duration-150">
                                                    <i class="fas fa-sync-alt text-sm"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/logisticorderitemsview?order_id=<?php echo $order['order_id']; ?>"
                                                    onclick="event.stopPropagation()" title="View Items"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-green-600 hover:bg-green-50 hover:border-green-300 transition-colors duration-150">
                                                    <i class="fas fa-list text-sm"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!$isCompleted): ?>
                                                <button
                                                    onclick="event.stopPropagation(); openRescheduleModal(<?php echo $order['delivery_id']; ?>, <?php echo $order['order_id']; ?>, '<?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES); ?>', '<?php echo $order['delivery_date']; ?>', '<?php echo date('H:i', strtotime($order['delivery_time'])); ?>');"
                                                    title="Reschedule"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-orange-600 hover:bg-orange-50 hover:border-orange-300 transition-colors duration-150">
                                                    <i class="fas fa-calendar-alt text-sm"></i>
                                                </button>
                                            <?php endif; ?>

                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- No Results Message (hidden by default, shown via JS filter) -->
            <div id="noResultsMessage"
                class="hidden flex flex-col items-center justify-center bg-white rounded-xl border border-gray-200 py-20 px-8 text-center mt-4">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-search text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-700 mb-1">No Orders Found</h3>
                <p class="text-sm text-gray-400">No orders match the selected filter.</p>
            </div>

        <?php endif; ?>
    </div>

    <!-- Order Details Modal -->
    <div id="detailsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background-color: rgba(0, 0, 0, 0.6);">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto relative">
            <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-xl z-10">
                <div class="flex items-center justify-between text-white">
                    <h3 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-info-circle mr-3"></i>
                        Order Details
                    </h3>
                    <button onclick="closeDetailsModal()" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-6" id="modalContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="rescheduleModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 p-6 rounded-t-xl">
                <div class="flex items-center justify-between text-white">
                    <h3 class="text-xl font-bold flex items-center">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        Reschedule Delivery
                    </h3>
                    <button onclick="closeRescheduleModal()" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <form id="rescheduleForm" class="p-6">
                <input type="hidden" id="reschedule_delivery_id" name="delivery_id">
                <input type="hidden" id="reschedule_order_id" name="order_id">

                <div class="mb-5 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <p class="text-sm text-blue-900 font-semibold">Order #<span id="modal_order_id"></span></p>
                    <p class="text-xs text-blue-700 mt-1">Customer: <span id="modal_customer_name"></span></p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar text-orange-500 mr-2"></i>New Date
                    </label>
                    <input type="date" id="new_delivery_date" name="new_delivery_date" required
                        min="<?php echo date('Y-m-d'); ?>"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-clock text-orange-500 mr-2"></i>New Time
                    </label>
                    <input type="time" id="new_delivery_time" name="new_delivery_time" required
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200">
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-comment-alt text-orange-500 mr-2"></i>Reason (Optional)
                    </label>
                    <textarea id="reschedule_reason" name="reschedule_reason" rows="3"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 resize-none"></textarea>
                </div>

                <div id="rescheduleError" class="hidden mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <p class="text-sm text-red-700" id="rescheduleErrorMessage"></p>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeRescheduleModal()"
                        class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 font-semibold">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 text-white px-6 py-3 rounded-lg hover:from-orange-600 hover:to-orange-700 font-semibold">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentFilter = 'all';
        const allOrders = <?php echo json_encode($orders); ?>;

        function filterOrders(type) {
            currentFilter = type;
            const rows = document.querySelectorAll('.order-row');
            const noResultsMsg = document.getElementById('noResultsMessage');
            const ordersTable = document.querySelector('.bg-white.rounded-lg.border.border-gray-200.overflow-hidden');
            let visibleCount = 0;

            // Update button styles
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-blue-600', 'text-white');
                btn.classList.add('bg-white', 'text-gray-700', 'border-2', 'border-gray-300');
            });

            const activeBtn = document.getElementById(`filter-${type}`);
            activeBtn.classList.add('active', 'bg-blue-600', 'text-white');
            activeBtn.classList.remove('bg-white', 'text-gray-700', 'border-2', 'border-gray-300');

            // Filter rows
            rows.forEach(row => {
                const deliveryType = row.getAttribute('data-delivery-type');
                if (type === 'all' || deliveryType === type) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide no results message
            if (visibleCount === 0) {
                ordersTable.classList.add('hidden');
                noResultsMsg.classList.remove('hidden');
            } else {
                ordersTable.classList.remove('hidden');
                noResultsMsg.classList.add('hidden');
            }

            // Update statistics based on filter
            updateStatistics(type);
        }

        function updateStatistics(type) {
            let filteredOrders = allOrders;
            if (type !== 'all') {
                filteredOrders = allOrders.filter(o => o.delivery_type === type);
            }

            const total = filteredOrders.length;
            const completed = filteredOrders.filter(o => ['delivered', 'picked_up'].includes(o.booking_status)).length;
            const booked = filteredOrders.filter(o => o.booking_id !== null).length;
            const pending = filteredOrders.filter(o => !['delivered', 'picked_up'].includes(o.booking_status)).length;
            const overdue = filteredOrders.filter(o => o.delivery_status === 'overdue').length;

            document.getElementById('stat-total').textContent = total;
            document.getElementById('stat-completed').textContent = completed;
            document.getElementById('stat-booked').textContent = booked;
            document.getElementById('stat-pending').textContent = pending;
            document.getElementById('stat-overdue').textContent = overdue;
        }

        function openDetailsModal(order) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('modalContent');

            const hasBooking = order.booking_id !== null;
            const deliveryTypeLabel = order.delivery_type === 'pickup' ?
                '<span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-semibold"><i class="fas fa-hand-holding mr-2"></i>Pickup</span>' :
                '<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold"><i class="fas fa-truck mr-2"></i>Delivery</span>';

            const bookingSection = hasBooking ? `
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-5 mb-4">
                <h4 class="font-bold text-purple-900 mb-3 flex items-center">
                    <i class="fas fa-shipping-fast mr-2"></i>Booking Information
                </h4>
                <div class="grid grid-cols-2 gap-4 break-all">
                    ${order.tracking_number ? `<div><span class="text-sm text-purple-700">Tracking:</span><p class="font-bold text-purple-900">${order.tracking_number}</p></div>` : ''}
                    ${order.booking_courier_name ? `<div><span class="text-sm text-purple-700">Courier:</span><p class="font-bold text-purple-900">${order.booking_courier_name}</p></div>` : ''}
                    ${order.booking_reference ? `<div><span class="text-sm text-purple-700">Reference:</span><p class="font-bold text-purple-900">${order.booking_reference}</p></div>` : ''}
                    ${order.booking_status ? `<div><span class="text-sm text-purple-700">Status:</span><p class="font-bold text-purple-900 capitalize">${order.booking_status.replace('_', ' ')}</p></div>` : ''}
                </div>
            </div>
        ` : '';

            content.innerHTML = `
            <div class="space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-5">
    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
        <i class="fas fa-user-circle text-blue-600 mr-2"></i>Customer Information
    </h4>
    <div class="grid grid-cols-3 gap-4 text-sm">
        <div><span class="text-gray-600">Name:</span><p class="font-semibold text-gray-900">${order.customer_name}</p></div>
        <div><span class="text-gray-600">Mobile:</span><p class="font-semibold text-gray-900">${order.mobile || 'N/A'}</p></div>
        <div><span class="text-gray-600">Type:</span><p class="mt-1">${deliveryTypeLabel}</p></div>
        <div class="col-span-3"><span class="text-gray-600">Address:</span><p class="font-semibold text-gray-900">${order.address}</p></div>
    </div>
</div>

${order.earliest_delivery && order.latest_delivery ? `
<div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-lg p-5">
    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
        <i class="fas fa-calendar-check text-blue-600 mr-2"></i>Expected Delivery Range
    </h4>
    <div class="text-center py-2">
        <p class="text-gray-900 font-semibold text-lg">
            ${new Date(order.earliest_delivery).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
            <span class="text-gray-500 mx-2">to</span>
            <span class="text-blue-700 font-bold">${new Date(order.latest_delivery).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
        </p>
        <p class="text-xs text-gray-500 mt-1">Based on item lead times</p>
    </div>
</div>
` : ''}
                
                ${bookingSection}
                
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-5">
    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
        <i class="fas fa-box text-orange-600 mr-2"></i>
        ${order.item_type === 'replacement' ? 'Replacement' : 'Order'} Details
        ${order.item_type === 'replacement' ? '<span class="ml-2 text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded-full font-semibold">Replacement Items Only</span>' : ''}
    </h4>
    <div class="grid grid-cols-4 gap-4">
        <div class="text-center p-3 bg-white rounded border"><div class="text-2xl font-bold text-blue-600">${order.total_items}</div><div class="text-xs text-gray-600">Items</div></div>
        <div class="text-center p-3 bg-white rounded border"><div class="text-2xl font-bold text-green-600">${order.total_quantity}</div><div class="text-xs text-gray-600">Quantity</div></div>
        <div class="text-center p-3 bg-white rounded border">
            <div class="text-2xl font-bold text-orange-600">${parseFloat(order.total_weight_kg || 0).toFixed(2)} kg</div>
            <div class="text-xs text-gray-600">Weight</div>
            ${order.item_type === 'replacement' ? '<div class="text-xs text-orange-600 mt-1">(Replacements)</div>' : ''}
        </div>
        <div class="text-center p-3 bg-white rounded border">
            <div class="text-2xl font-bold text-orange-600">${parseFloat(order.total_cubic_meters || 0).toFixed(3)} m³</div>
            <div class="text-xs text-gray-600">Volume</div>
            ${order.item_type === 'replacement' ? '<div class="text-xs text-orange-600 mt-1">(Replacements)</div>' : ''}
        </div>
    </div>
</div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-5">
                    <h4 class="font-bold text-gray-900 mb-3">Payment Summary</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span>Subtotal:</span><span class="font-semibold">₱${parseFloat(order.final_total - (order.delivery_fee || 0)).toFixed(2)}</span></div>
                        <div class="flex justify-between"><span>Delivery Fee:</span><span class="font-semibold text-blue-600">₱${parseFloat(order.delivery_fee || 0).toFixed(2)}</span></div>
                        <div class="flex justify-between pt-2 border-t border-green-300"><span class="font-bold">Total:</span><span class="text-xl font-bold text-green-600">₱${parseFloat(order.final_total).toFixed(2)}</span></div>
                    </div>
                </div>
                
                ${order.delivery_notes ? `<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4"><i class="fas fa-sticky-note text-yellow-600 mr-2"></i><span class="font-semibold">Notes:</span> ${order.delivery_notes}</div>` : ''}

${order.item_type === 'replacement' && order.replacement_reason ? `
    <div class="bg-orange-50 border border-orange-200 rounded-lg p-5">
        <h4 class="font-bold text-orange-900 mb-3 flex items-center">
            <i class="fas fa-exchange-alt mr-2"></i>Replacement Information
        </h4>
        <div class="space-y-2 text-sm">
            <div><span class="text-orange-700 font-semibold">Reason:</span> <span class="text-gray-900">${order.replacement_reason}</span></div>
            ${order.replacement_details ? `<div><span class="text-orange-700 font-semibold">Details:</span> <span class="text-gray-900">${order.replacement_details}</span></div>` : ''}
            ${order.replacement_quantity ? `<div><span class="text-orange-700 font-semibold">Quantity:</span> <span class="text-gray-900">${order.replacement_quantity} pcs</span></div>` : ''}
            ${order.replacement_status ? `<div><span class="text-orange-700 font-semibold">Status:</span> <span class="text-gray-900 capitalize">${order.replacement_status.replace('_', ' ')}</span></div>` : ''}
        </div>
    </div>
` : ''}
            </div>
        `;

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openRescheduleModal(deliveryId, orderId, customerName, currentDate, currentTime) {
            document.getElementById('reschedule_delivery_id').value = deliveryId;
            document.getElementById('reschedule_order_id').value = orderId;
            document.getElementById('modal_order_id').textContent = orderId;
            document.getElementById('modal_customer_name').textContent = customerName;
            document.getElementById('new_delivery_date').value = currentDate;
            document.getElementById('new_delivery_time').value = currentTime;
            document.getElementById('rescheduleModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            document.getElementById('rescheduleForm').reset();
            document.getElementById('rescheduleError').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.getElementById('detailsModal').addEventListener('click', function (e) {
            if (e.target === this) closeDetailsModal();
        });

        document.getElementById('rescheduleModal').addEventListener('click', function (e) {
            if (e.target === this) closeRescheduleModal();
        });

        // Handle reschedule form submission
        document.getElementById('rescheduleForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Rescheduling...';
            document.getElementById('rescheduleError').classList.add('hidden');

            fetch('<?= BASE_URL ?>/logisticprocessreschedule', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const successMsg = document.createElement('div');
                        successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 animate-slide-in';
                        successMsg.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 text-xl"></i>
                        <div>
                            <p class="font-semibold">Reschedule Successful!</p>
                            <p class="text-sm">Delivery has been rescheduled.</p>
                        </div>
                    </div>
                `;
                        document.body.appendChild(successMsg);

                        setTimeout(() => {
                            closeRescheduleModal();
                            location.reload();
                        }, 1500);
                    } else {
                        document.getElementById('rescheduleErrorMessage').textContent = data.message || 'Failed to reschedule delivery.';
                        document.getElementById('rescheduleError').classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('rescheduleErrorMessage').textContent = 'An error occurred. Please try again.';
                    document.getElementById('rescheduleError').classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
        });
    </script>
</body>

</html>