<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'dispatcher') {
    header("Location: " . BASE_URL . "/logisticdispatcherdashboard");
    exit();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location:" . BASE_URL . " /main");
    exit();
}

$scheduleSql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    db.courier_name,
    db.vehicle_id,
    db.booking_reference,
    db.tracking_number,
    db.booking_type,
    db.booking_status,
    db.delivery_proof_image,
    db.actual_delivery_time,
    ds.delivery_type,
    ds.created_by,
    ds.created_at,
    ds.item_type,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.status as order_status,
    o.final_total,
    (SELECT COUNT(*) FROM order_items WHERE order_id = ds.order_id) as total_items,
    (SELECT SUM(quantity) FROM order_items WHERE order_id = ds.order_id) as total_quantity,
    rr.reason as replacement_reason,
    rr.details as replacement_details,
    rr.replacement_quantity,
    rr.status as replacement_status,
    CASE 
        WHEN db.booking_status IN ('delivered', 'picked_up') THEN 'completed'
        WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) 
             AND ds.delivery_date < CURDATE() THEN 'overdue'
        WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) 
             AND ds.delivery_date = CURDATE() THEN 'today_pending'
        WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) 
             AND ds.delivery_date > CURDATE() THEN 'upcoming'
        ELSE 'upcoming'
    END as delivery_status,
    CASE 
        WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) 
             AND ds.delivery_date < CURDATE() THEN DATEDIFF(CURDATE(), ds.delivery_date)
        ELSE 0
    END as days_overdue
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id
LEFT JOIN replacement_requests rr ON ds.id = rr.delivery_schedule_id AND ds.item_type = 'replacement'
WHERE ds.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 3650 DAY)
ORDER BY ds.delivery_date DESC, ds.delivery_time ASC";

$scheduleStmt = $conn->prepare($scheduleSql);
$scheduleStmt->execute();
$schedules = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduleStmt->close();

$countSql = "SELECT 
    DATE(ds.delivery_date) as date, 
    COUNT(*) as count,
    COUNT(CASE WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) THEN 1 END) as pending_count,
    COUNT(CASE WHEN db.booking_status IN ('delivered', 'picked_up') THEN 1 END) as completed_count,
    COUNT(CASE WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) 
                AND ds.delivery_date < CURDATE() THEN 1 END) as overdue_count
FROM delivery_schedules ds
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id
WHERE ds.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 3650 DAY)
GROUP BY DATE(ds.delivery_date)";

$countStmt = $conn->prepare($countSql);
$countStmt->execute();
$deliveryCounts = $countStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$countStmt->close();

$deliveryCountsByDate = [];
foreach ($deliveryCounts as $data) {
    $deliveryCountsByDate[$data['date']] = $data;
}

$statsSql = "SELECT 
    COUNT(*) as total_scheduled,
    COUNT(CASE WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) 
                AND ds.delivery_date >= CURDATE() THEN 1 END) as pending_deliveries,
    COUNT(CASE WHEN db.booking_status IN ('delivered', 'picked_up') THEN 1 END) as completed_deliveries,
    COUNT(CASE WHEN ds.delivery_date = CURDATE() 
                AND (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) THEN 1 END) as today_deliveries,
    COUNT(CASE WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) 
                AND ds.delivery_date < CURDATE() THEN 1 END) as overdue_deliveries,
    COUNT(CASE WHEN ds.item_type = 'replacement' THEN 1 END) as replacement_deliveries
FROM delivery_schedules ds
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id
WHERE ds.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)";

$statsStmt = $conn->prepare($statsSql);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Schedule - Noble Home</title>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

    <!-- ── Page Header ── -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between py-4 gap-3">

                <!-- Title -->
                <div>
                    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-truck text-blue-600"></i>
                        Delivery Schedule
                    </h1>
                    <p class="text-xs text-gray-400 mt-0.5">Monitor and track all delivery schedules</p>
                </div>

                <!-- Filter Buttons -->
                <div class="flex flex-wrap gap-2">
                    <button onclick="filterDeliveries('all')"
                        class="filter-btn active text-xs px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 flex items-center gap-1.5">
                        <i class="fas fa-list"></i> All
                    </button>

                    <button onclick="filterDeliveries('overdue')"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 flex items-center gap-1.5">
                        <i class="fas fa-exclamation-triangle"></i> Overdue
                        <?php if ($stats['overdue_deliveries'] > 0): ?>
                            <span class="bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full leading-none">
                                <?= $stats['overdue_deliveries'] ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <button onclick="filterDeliveries('today')"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center gap-1.5">
                        <i class="fas fa-calendar-day"></i> Today
                        <?php if ($stats['today_deliveries'] > 0): ?>
                            <span class="bg-blue-500 text-white text-xs px-1.5 py-0.5 rounded-full leading-none">
                                <?= $stats['today_deliveries'] ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <button onclick="filterDeliveries('upcoming')"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg border border-green-200 bg-green-50 text-green-600 hover:bg-green-100 flex items-center gap-1.5">
                        <i class="fas fa-clock"></i> Upcoming
                    </button>

                    <button onclick="filterDeliveries('replacement')"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg border border-orange-200 bg-orange-50 text-orange-600 hover:bg-orange-100 flex items-center gap-1.5">
                        <i class="fas fa-exchange-alt"></i> Replacements
                    </button>
                </div>

            </div>
        </div>
    </div>

    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

        <!-- ── Stat Cards ── -->
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar text-blue-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-800"><?= $stats['total_scheduled'] ?></span>
                </div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Scheduled</p>
            </div>

            <div class="bg-white border border-red-100 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-red-600"><?= $stats['overdue_deliveries'] ?></span>
                </div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Overdue</p>
            </div>

            <div class="bg-white border border-yellow-100 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 bg-yellow-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-800"><?= $stats['pending_deliveries'] ?></span>
                </div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Pending</p>
            </div>

            <div class="bg-white border border-green-100 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-800"><?= $stats['completed_deliveries'] ?></span>
                </div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Completed</p>
            </div>

            <div class="bg-white border border-purple-100 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 bg-purple-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-truck-fast text-purple-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-800"><?= $stats['today_deliveries'] ?></span>
                </div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Today</p>
            </div>

            <div class="bg-white border border-orange-100 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 bg-orange-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exchange-alt text-orange-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-800"><?= $stats['replacement_deliveries'] ?></span>
                </div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Replacements</p>
            </div>

        </div>

        <!-- ── Calendar + Side Panel ── -->
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

            <!-- Calendar -->
            <div class="xl:col-span-3 bg-white border border-gray-200 rounded-xl p-5">

                <!-- Calendar Toolbar -->
                <div class="flex flex-col gap-3 mb-4">

                    <!-- Row 1: Nav + Month/Year + Today -->
                    <div class="flex items-center justify-between gap-2">
                        <button id="prevMonth"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100">
                            <i class="fas fa-chevron-left text-gray-400 text-xs"></i>
                        </button>

                        <div class="flex items-center gap-1.5">
                            <select id="monthSelect" onchange="jumpToMonthYear()"
                                class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-400">
                                <?php
                                $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                foreach ($months as $i => $m)
                                    echo "<option value=\"$i\">$m</option>";
                                ?>
                            </select>

                            <select id="yearSelect" onchange="jumpToMonthYear()"
                                class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-400">
                                <?php
                                $cy = date('Y');
                                for ($y = $cy - 2; $y <= $cy + 5; $y++)
                                    echo "<option value=\"$y\">$y</option>";
                                ?>
                            </select>

                            <button onclick="goToToday()"
                                class="text-xs px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                                Today
                            </button>
                        </div>

                        <button id="nextMonth"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100">
                            <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                        </button>
                    </div>

                    <!-- Row 2: Search + Date Jump -->
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <i
                                class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input type="text" id="customerSearch" placeholder="Search customer or order..."
                                oninput="handleSearch(this.value)"
                                class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-400">
                        </div>
                        <input type="date" id="quickJumpDate" onchange="quickJumpToDate(this.value)"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-400">
                    </div>

                    <!-- Row 3: Range toggle -->
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <div class="relative">
                                <input type="checkbox" id="rangeToggle" onchange="toggleRangeMode()"
                                    class="sr-only peer">
                                <div
                                    class="w-8 h-4 bg-gray-200 peer-checked:bg-blue-500 rounded-full transition-colors">
                                </div>
                                <div
                                    class="absolute top-0.5 left-0.5 w-3 h-3 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4">
                                </div>
                            </div>
                            <span class="text-xs text-gray-500">Date range</span>
                        </label>
                        <span id="rangeLabel" class="text-xs text-blue-500 font-medium hidden"></span>
                        <button id="clearRangeBtn" onclick="clearRange()"
                            class="hidden text-xs text-red-400 hover:text-red-600">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>

                </div>

                <!-- Day Headers -->
                <div class="grid grid-cols-7 mb-1">
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
                        <div class="text-center text-xs font-medium text-gray-400 py-1"><?= $d ?></div>
                    <?php endforeach; ?>
                </div>

                <!-- Calendar Grid -->
                <div id="calendar-grid" class="grid grid-cols-7 gap-1"></div>

                <!-- Legend -->
                <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 sm:grid-cols-4 gap-y-2 gap-x-3">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="w-3 h-3 rounded bg-orange-100 border border-orange-400 shrink-0"></span>
                        Has deliveries
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="w-3 h-3 rounded bg-red-100 border border-red-400 shrink-0"></span>
                        Has overdue
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="w-3 h-3 rounded bg-purple-100 border border-purple-400 shrink-0"></span>
                        Busy (10+)
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="w-3 h-3 rounded bg-blue-600 shrink-0"></span>
                        Selected
                    </div>
                </div>

            </div>

            <!-- Side Panel -->
            <div class="xl:col-span-2 bg-white border border-gray-200 rounded-xl flex flex-col">

                <!-- Panel Header -->
                <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-list-ul text-purple-500"></i>
                            <span id="details-title">All Deliveries</span>
                        </h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span id="items-count"
                                class="text-xs bg-purple-50 text-purple-700 border border-purple-100 px-2 py-0.5 rounded-full font-medium">
                                <?= count($schedules) ?> items
                            </span>
                            <span id="filter-status" class="text-xs text-gray-400"></span>
                        </div>
                    </div>
                    <button id="navigateBtn" onclick="navigateToDetailedView()"
                        class="hidden text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 flex items-center gap-1.5 shrink-0">
                        <i class="fas fa-external-link-alt"></i> View Details
                    </button>
                </div>

                <!-- Panel Body -->
                <div id="delivery-details-container"
                    class="flex-1 overflow-y-auto max-h-[640px] divide-y divide-gray-50"></div>

            </div>

        </div>

        <!-- ── Overdue Table ── -->
        <?php
        $overdueSchedules = array_filter($schedules, fn($s) => $s['delivery_status'] === 'overdue');
        usort($overdueSchedules, fn($a, $b) => $b['days_overdue'] - $a['days_overdue']);
        ?>

        <?php if (!empty($overdueSchedules)): ?>
            <div class="bg-white border border-red-200 rounded-xl overflow-hidden">

                <!-- Overdue Header (toggle) -->
                <button onclick="toggleOverduePanel()"
                    class="w-full flex items-center gap-3 px-5 py-3 bg-red-50 border-b border-red-100 text-left">
                    <span class="relative flex h-2.5 w-2.5 shrink-0">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                    </span>
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span class="text-sm font-semibold text-red-700">
                        <?= count($overdueSchedules) ?> Overdue
                        <?= count($overdueSchedules) > 1 ? 'Deliveries' : 'Delivery' ?>
                    </span>
                    <span class="text-xs text-red-400">— action needed</span>
                    <i id="overdue-toggle-icon" class="fas fa-chevron-up text-red-400 text-xs ml-auto"></i>
                </button>

                <!-- Overdue Table Body -->
                <div id="overdue-panel-body" class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-red-50 border-b border-red-100 text-red-700">
                                <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Order</th>
                                <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Customer</th>
                                <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Sched. Date</th>
                                <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Booking Status</th>
                                <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Courier</th>
                                <th class="px-4 py-2 text-left font-semibold">Address</th>
                                <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Mobile</th>
                                <th class="px-4 py-2 text-right font-semibold whitespace-nowrap">Total</th>
                                <th class="px-4 py-2 text-center font-semibold whitespace-nowrap">Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdueSchedules as $i => $s): ?>
                                <tr
                                    class="border-b border-red-50 hover:bg-red-50 <?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                                    <td class="px-4 py-2 font-bold text-red-700 whitespace-nowrap">#<?= $s['order_id'] ?></td>
                                    <td class="px-4 py-2 font-medium text-gray-800 whitespace-nowrap">
                                        <?= htmlspecialchars($s['customer_name']) ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500 whitespace-nowrap">
                                        <?= date('M d, Y', strtotime($s['delivery_date'])) ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <?php if ($s['booking_status']): ?>
                                            <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full capitalize">
                                                <?= str_replace('_', ' ', $s['booking_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full">No booking</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500 whitespace-nowrap">
                                        <?= $s['courier_name'] ? htmlspecialchars($s['courier_name']) : '<span class="text-gray-300">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500 max-w-xs truncate"><?= htmlspecialchars($s['address']) ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <?php if ($s['mobile']): ?>
                                            <a href="tel:<?= htmlspecialchars($s['mobile']) ?>"
                                                class="text-blue-500 hover:underline">
                                                <?= htmlspecialchars($s['mobile']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-300">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium text-gray-700 whitespace-nowrap">
                                        ₱<?= number_format($s['final_total'], 2) ?>
                                    </td>
                                    <td class="px-4 py-2 text-center whitespace-nowrap">
                                        <span class="bg-red-500 text-white px-2 py-0.5 rounded-full font-bold">
                                            <?= $s['days_overdue'] ?>d
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        <?php endif; ?>

        <!-- ── Completed Deliveries ── -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i>
                <h3 class="text-sm font-semibold text-gray-800">Completed Deliveries</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-green-50 border-b border-green-100 text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3 text-left font-semibold">Order</th>
                            <th class="px-5 py-3 text-left font-semibold">Customer</th>
                            <th class="px-5 py-3 text-left font-semibold">Delivery Date</th>
                            <th class="px-5 py-3 text-left font-semibold">Completed</th>
                            <th class="px-5 py-3 text-left font-semibold">Courier</th>
                            <th class="px-5 py-3 text-left font-semibold">Tracking No.</th>
                            <th class="px-5 py-3 text-left font-semibold">Amount</th>
                            <th class="px-5 py-3 text-left font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody id="completed-table-body"></tbody>
                </table>
            </div>

            <div id="no-completed-message" class="hidden py-12 text-center">
                <i class="fas fa-inbox text-4xl text-gray-200 mb-3 block"></i>
                <p class="text-sm font-medium text-gray-400">No Completed Deliveries Yet</p>
                <p class="text-xs text-gray-300 mt-1">Completed deliveries will appear here</p>
            </div>

        </div>

    </div><!-- end wrapper -->

    <script>
        // ─── State ───────────────────────────────────────────────────────────────
        const deliveryCounts = <?= json_encode($deliveryCountsByDate) ?>;
        const schedules = <?= json_encode($schedules) ?>;

        let currentDate = new Date();
        let selectedDate = null;
        let currentFilter = 'all';
        let searchQuery = '';
        let rangeMode = false;
        let rangeStart = null;
        let rangeEnd = null;

        // ─── Calendar ────────────────────────────────────────────────────────────
        function initCalendar() {
            updateCalendarHeader();
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());

            document.getElementById('prevMonth').addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                updateCalendarHeader();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            });

            document.getElementById('nextMonth').addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                updateCalendarHeader();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            });
        }

        function updateCalendarHeader() {
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const ms = document.getElementById('monthSelect');
            const ys = document.getElementById('yearSelect');
            if (ms) ms.value = currentDate.getMonth();
            if (ys) ys.value = currentDate.getFullYear();
        }

        function generateCalendar(year, month) {
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '';

            // Empty leading cells
            for (let i = 0; i < firstDay; i++) {
                grid.appendChild(Object.assign(document.createElement('div'), { className: 'p-1' }));
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dateObj = new Date(year, month, day);
                const isPast = dateObj < today;
                const data = deliveryCounts[dateStr];

                // Base classes — only Tailwind
                let base = 'relative cursor-pointer rounded-lg border text-center flex flex-col items-center justify-center min-h-[52px] p-1 hover:scale-105 transition-transform';

                if (dateStr === selectedDate) {
                    base += ' bg-blue-600 border-blue-600 text-white [&_*]:text-white';
                } else if (data?.overdue_count > 0) {
                    base += ' bg-red-50 border-red-400';
                } else if (data?.count >= 10) {
                    base += ' bg-purple-50 border-purple-400';
                } else if (data) {
                    base += ' bg-orange-50 border-orange-400';
                } else if (isPast) {
                    base += ' bg-gray-50 border-gray-200 text-gray-400';
                } else {
                    base += ' bg-white border-gray-200 hover:bg-blue-50';
                }

                const el = document.createElement('div');
                el.className = base;
                el.dataset.date = dateStr;

                // Day number
                const num = document.createElement('div');
                num.className = 'text-xs font-semibold leading-none  text-inherit';
                num.textContent = day;
                el.appendChild(num);

                // Badges row
                if (data) {
                    const badges = document.createElement('div');
                    badges.className = 'flex gap-0.5 mt-0.5 flex-wrap justify-center';

                    const badge = (count, color) => {
                        const b = document.createElement('span');
                        b.className = `text-[9px] font-bold px-1 rounded ${color} text-white leading-tight`;
                        b.textContent = count;
                        return b;
                    };

                    if (data.overdue_count > 0) badges.appendChild(badge(data.overdue_count, 'bg-red-500'));
                    if (data.pending_count > 0) badges.appendChild(badge(data.pending_count, 'bg-yellow-500'));
                    if (data.completed_count > 0) badges.appendChild(badge(data.completed_count, 'bg-green-500'));

                    el.appendChild(badges);
                }

                el.addEventListener('click', () => {
                    if (rangeMode) { handleRangeClick(dateStr, el); return; }
                    selectedDate = dateStr;
                    document.getElementById('navigateBtn').classList.toggle(
                        'hidden', !schedules.some(s => s.delivery_date === dateStr)
                    );
                    // I-regenerate para ma-apply lahat ng classes nang tama
                    generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
                    updateDeliveryDetails(dateStr);
                });

                grid.appendChild(el);
            }
        }

        function jumpToMonthYear() {
            const m = parseInt(document.getElementById('monthSelect').value);
            const y = parseInt(document.getElementById('yearSelect').value);
            currentDate = new Date(y, m, 1);
            updateCalendarHeader();
            generateCalendar(y, m);
        }

        function goToToday() {
            currentDate = new Date();
            updateCalendarHeader();
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            const todayStr = getTodayString();
            const el = document.querySelector(`[data-date="${todayStr}"]`);
            if (el) el.click();
        }

        function quickJumpToDate(val) {
            if (!val) return;
            const [y, m] = val.split('-').map(Number);
            currentDate = new Date(y, m - 1, 1);
            updateCalendarHeader();
            generateCalendar(y, m - 1);
            setTimeout(() => {
                const el = document.querySelector(`[data-date="${val}"]`);
                if (el) el.click();
            }, 50);
        }

        // ─── Range Mode ───────────────────────────────────────────────────────────
        function toggleRangeMode() {
            rangeMode = document.getElementById('rangeToggle').checked;
            if (!rangeMode) clearRange();
        }

        function clearRange() {
            rangeStart = rangeEnd = null;
            document.getElementById('rangeLabel').classList.add('hidden');
            document.getElementById('clearRangeBtn').classList.add('hidden');
            updateDeliveryDetails();
        }

        function handleRangeClick(dateStr, el) {
            if (!rangeStart || (rangeStart && rangeEnd)) {
                clearRange();
                rangeStart = dateStr;
                document.getElementById('rangeLabel').textContent = `From: ${fmtDate(dateStr)}`;
                document.getElementById('rangeLabel').classList.remove('hidden');
            } else {
                rangeEnd = dateStr < rangeStart ? (rangeStart = dateStr, rangeEnd = rangeStart, dateStr) : dateStr;
                if (dateStr < rangeStart) { [rangeStart, rangeEnd] = [dateStr, rangeStart]; }
                else rangeEnd = dateStr;
                document.getElementById('rangeLabel').textContent =
                    `${fmtDate(rangeStart)} → ${fmtDate(rangeEnd)}`;
                document.getElementById('clearRangeBtn').classList.remove('hidden');
                const filtered = schedules.filter(s => s.delivery_date >= rangeStart && s.delivery_date <= rangeEnd);
                document.getElementById('details-title').textContent = `Range: ${filtered.length} deliverie(s)`;
                document.getElementById('items-count').textContent = `${filtered.length} items`;
                renderDeliveryList(filtered);
            }
        }

        // ─── Filters ─────────────────────────────────────────────────────────────
        function filterDeliveries(type) {
            currentFilter = type;
            selectedDate = null;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active', 'bg-blue-600', 'text-white'));
            event.target.closest('button').classList.add('active');
            document.getElementById('navigateBtn').classList.add('hidden');
            updateDeliveryDetails();
        }

        function handleSearch(q) {
            searchQuery = q.trim().toLowerCase();
            updateDeliveryDetails(selectedDate);
        }

        // ─── Details Panel ────────────────────────────────────────────────────────
        function updateDeliveryDetails(date = null) {
            const title = document.getElementById('details-title');
            const countSpan = document.getElementById('items-count');
            const statusSpan = document.getElementById('filter-status');
            let filtered = schedules;

            if (date) {
                filtered = schedules.filter(s => s.delivery_date === date);
                title.textContent = `Deliveries for ${fmtDate(date)}`;
                statusSpan.textContent = '';
            } else {
                const monthRange = getCurrentMonthRange();
                if (currentFilter === 'overdue') { filtered = schedules.filter(s => s.delivery_status === 'overdue'); title.textContent = 'Overdue Deliveries'; }
                else if (currentFilter === 'today') { filtered = schedules.filter(s => s.delivery_status === 'today_pending' || (s.delivery_date === getTodayString() && s.delivery_status === 'completed')); title.textContent = "Today's Deliveries"; }
                else if (currentFilter === 'upcoming') { filtered = schedules.filter(s => s.delivery_status === 'upcoming'); title.textContent = 'Upcoming Deliveries'; }
                else if (currentFilter === 'replacement') { filtered = schedules.filter(s => s.item_type === 'replacement'); title.textContent = 'Replacement Deliveries'; }
                else {
                    filtered = schedules.filter(s => s.delivery_date >= monthRange.start && s.delivery_date <= monthRange.end);
                    title.textContent = `All – ${new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' })}`;
                }
                statusSpan.textContent = '';
            }

            if (searchQuery) {
                filtered = filtered.filter(s =>
                    s.customer_name?.toLowerCase().includes(searchQuery) ||
                    String(s.order_id).includes(searchQuery) ||
                    s.address?.toLowerCase().includes(searchQuery)
                );
                title.textContent = `Search: "${searchQuery}"`;
                statusSpan.textContent = `${filtered.length} result(s)`;
            }

            countSpan.textContent = `${filtered.length} items`;
            renderDeliveryList(filtered);
        }

        function renderDeliveryList(items) {
            const container = document.getElementById('delivery-details-container');

            if (!items.length) {
                container.innerHTML = `
                    <div class="py-12 text-center">
                        <i class="fas fa-calendar-day text-4xl text-gray-200 mb-3 block"></i>
                        <p class="text-sm text-gray-400">No deliveries found</p>
                    </div>`;
                return;
            }

            // Group by date → time
            const grouped = {};
            items.forEach(s => {
                if (!grouped[s.delivery_date]) grouped[s.delivery_date] = {};
                if (!grouped[s.delivery_date][s.delivery_time]) grouped[s.delivery_date][s.delivery_time] = [];
                grouped[s.delivery_date][s.delivery_time].push(s);
            });

            let html = '';
            Object.keys(grouped).sort((a, b) =>
                currentFilter === 'overdue' ? new Date(b) - new Date(a) : new Date(a) - new Date(b)
            ).forEach(date => {
                const dateObj = new Date(date + 'T00:00:00');
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const hasOverdue = Object.values(grouped[date]).flat().some(s => s.delivery_status === 'overdue');
                const total = Object.values(grouped[date]).reduce((n, arr) => n + arr.length, 0);

                const dateBarColor = hasOverdue
                    ? 'bg-red-50 border-l-4 border-red-400'
                    : dateObj.getTime() === today.getTime()
                        ? 'bg-blue-50 border-l-4 border-blue-400'
                        : 'bg-gray-50 border-l-4 border-gray-300';

                html += `
                    <div>
                        <div class="${dateBarColor} px-4 py-2 flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-700">${fmtDate(date)}</span>
                            <span class="ml-auto text-xs text-gray-400">${total} item(s)</span>
                            ${hasOverdue ? `<span class="text-xs bg-red-500 text-white px-2 py-0.5 rounded-full font-bold">OVERDUE</span>` : ''}
                        </div>`;

                Object.keys(grouped[date]).sort().forEach(time => {
                    html += `
                        <div class="px-4 py-1.5 bg-gray-50 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500">
                                <i class="fas fa-clock mr-1"></i>${fmtTime(time)} · ${grouped[date][time].length} item(s)
                            </p>
                        </div>
                        <div class="p-3 space-y-2">`;

                    grouped[date][time].forEach(s => {
                        const done = s.booking_status === 'delivered' || s.booking_status === 'picked_up';
                        const overdue = s.delivery_status === 'overdue';
                        const today_p = s.delivery_status === 'today_pending';

                        let cardBorder, badgeClass, badgeText;
                        if (done) { cardBorder = 'border-green-200 bg-green-50'; badgeClass = 'bg-green-100 text-green-700'; badgeText = 'Delivered'; }
                        else if (overdue) { cardBorder = 'border-red-200 bg-red-50'; badgeClass = 'bg-red-100 text-red-700'; badgeText = `Overdue · ${s.days_overdue}d`; }
                        else if (today_p) { cardBorder = 'border-blue-200 bg-blue-50'; badgeClass = 'bg-blue-100 text-blue-700'; badgeText = 'Due Today'; }
                        else { cardBorder = 'border-yellow-200 bg-yellow-50'; badgeClass = 'bg-yellow-100 text-yellow-700'; badgeText = 'Scheduled'; }

                        html += `
                        <div class="border ${cardBorder} rounded-lg p-3 text-xs space-y-1.5">

                            <!-- Order + badges row -->
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="font-bold text-gray-800">
                                    Order #${s.order_id}
                                    ${s.item_type === 'replacement' ? `<span class="ml-1 bg-orange-500 text-white px-1.5 py-0.5 rounded text-xs">Replacement</span>` : ''}
                                </span>
                                <span class="${badgeClass} px-2 py-0.5 rounded-full font-medium">${badgeText}</span>
                            </div>

                            <!-- Customer + address -->
                            <div class="flex items-start gap-1.5">
                                <i class="fas fa-user text-gray-300 mt-0.5 w-3"></i>
                                <span class="text-gray-700 font-medium">${esc(s.customer_name)}</span>
                            </div>
                            <div class="flex items-start gap-1.5">
                                <i class="fas fa-map-marker-alt text-gray-300 mt-0.5 w-3"></i>
                                <span class="text-gray-500">${esc(s.address)}</span>
                            </div>
                            ${s.mobile ? `<div class="flex items-center gap-1.5"><i class="fas fa-phone text-gray-300 w-3"></i><span class="text-gray-500">${esc(s.mobile)}</span></div>` : ''}

                            <!-- Order totals -->
                            <div class="flex gap-3 pt-1 border-t border-white/50">
                                <span class="text-gray-400">Items: <b class="text-gray-700">${s.total_items}</b></span>
                                <span class="text-gray-400">Qty: <b class="text-gray-700">${s.total_quantity}</b></span>
                                <span class="text-gray-400">Total: <b class="text-gray-700">₱${parseFloat(s.final_total).toLocaleString('en-US', { minimumFractionDigits: 2 })}</b></span>
                            </div>

                            <!-- Courier / Tracking -->
                            ${s.courier_name ? `<div class="flex items-center gap-1.5"><i class="fas fa-truck text-gray-300 w-3"></i><span class="text-gray-500">${esc(s.courier_name)}</span></div>` : ''}
                            ${s.tracking_number ? `<div class="flex items-center gap-1.5"><i class="fas fa-barcode text-gray-300 w-3"></i><span class="text-gray-500">${esc(s.tracking_number)}</span></div>` : ''}
                            ${s.booking_reference ? `<div class="flex items-center gap-1.5"><i class="fas fa-receipt text-gray-300 w-3"></i><span class="text-gray-500">Ref: ${esc(s.booking_reference)}</span></div>` : ''}

                            <!-- Notes -->
                            ${s.delivery_notes ? `<div class="bg-yellow-100 border border-yellow-200 rounded p-2 flex gap-1.5"><i class="fas fa-sticky-note text-yellow-500 mt-0.5"></i><span class="text-gray-600">${esc(s.delivery_notes)}</span></div>` : ''}

                            <!-- Actual delivery time -->
                            ${done && s.actual_delivery_time ? `<div class="bg-green-100 border border-green-200 rounded p-2 text-green-700"><i class="fas fa-check-double mr-1"></i>Delivered: ${fmtDateTime(s.actual_delivery_time)}</div>` : ''}

                            <!-- Replacement details -->
                            ${s.item_type === 'replacement' && s.replacement_reason ? `
                            <div class="bg-orange-50 border border-orange-200 rounded p-2 space-y-0.5">
                                <p class="font-semibold text-orange-700"><i class="fas fa-info-circle mr-1"></i>Replacement Details</p>
                                <p class="text-gray-600">Reason: ${esc(s.replacement_reason)}</p>
                                ${s.replacement_details ? `<p class="text-gray-600">Details: ${esc(s.replacement_details)}</p>` : ''}
                                <p class="text-gray-600">Qty: ${s.replacement_quantity}</p>
                                ${s.replacement_status ? `<p class="text-gray-600 capitalize">Status: ${s.replacement_status.replace('_', ' ')}</p>` : ''}
                            </div>` : ''}

                        </div>`;
                    });

                    html += `</div>`;
                });

                html += `</div>`;
            });

            container.innerHTML = html;
        }

        // ─── Completed Table ──────────────────────────────────────────────────────
        function populateCompletedTable() {
            const done = schedules.filter(s => s.delivery_status === 'completed')
                .sort((a, b) => new Date(b.actual_delivery_time || 0) - new Date(a.actual_delivery_time || 0));
            const body = document.getElementById('completed-table-body');
            const none = document.getElementById('no-completed-message');

            if (!done.length) { none.classList.remove('hidden'); return; }
            none.classList.add('hidden');

            body.innerHTML = done.map((s, i) => {
                const dd = new Date(s.delivery_date + 'T00:00:00');
                const cd = s.actual_delivery_time ? new Date(s.actual_delivery_time) : null;
                return `
                <tr class="border-b border-gray-100 hover:bg-green-50 transition-colors ${i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'}">
                    <td class="px-5 py-3 text-sm font-medium text-blue-600">#${s.order_id}</td>
                    <td class="px-5 py-3 text-sm text-gray-700">${esc(s.customer_name)}</td>
                    <td class="px-5 py-3 text-sm text-gray-500">${dd.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                    <td class="px-5 py-3 text-sm text-gray-500">${cd ? cd.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '<span class="text-gray-300">—</span>'}</td>
                    <td class="px-5 py-3 text-sm text-gray-500">${s.courier_name ? esc(s.courier_name) : '<span class="text-gray-300">—</span>'}</td>
                    <td class="px-5 py-3 text-sm text-gray-500">${s.tracking_number ? esc(s.tracking_number) : '<span class="text-gray-300">—</span>'}</td>
                    <td class="px-5 py-3 text-sm font-semibold text-gray-800">₱${parseFloat(s.final_total).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                    <td class="px-5 py-3">
                        <span class="text-xs bg-green-100 text-green-700 border border-green-200 px-2.5 py-1 rounded-full font-semibold inline-flex items-center gap-1">
                            <i class="fas fa-check-circle"></i>
                            ${s.booking_status === 'delivered' ? 'Delivered' : 'Picked Up'}
                        </span>
                    </td>
                </tr>`;
            }).join('');
        }

        // ─── Overdue Toggle ───────────────────────────────────────────────────────
        function toggleOverduePanel() {
            const body = document.getElementById('overdue-panel-body');
            const icon = document.getElementById('overdue-toggle-icon');
            const hidden = body.style.display === 'none';
            body.style.display = hidden ? '' : 'none';
            icon.className = hidden
                ? 'fas fa-chevron-up text-red-400 text-xs ml-auto'
                : 'fas fa-chevron-down text-red-400 text-xs ml-auto';
        }

        // ─── Navigate ─────────────────────────────────────────────────────────────
        function navigateToDetailedView() {
            if (selectedDate) window.location.href = `<?= BASE_URL ?>/logisticdeliverydateorders?date=${selectedDate}`;
        }

        // ─── Helpers ─────────────────────────────────────────────────────────────
        function getTodayString() {
            const d = new Date();
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }

        function getCurrentMonthRange() {
            const now = new Date();
            const y = now.getFullYear(), m = now.getMonth();
            const end = new Date(y, m + 1, 0).getDate();
            const pad = n => String(n).padStart(2, '0');
            return {
                start: `${y}-${pad(m + 1)}-01`,
                end: `${y}-${pad(m + 1)}-${pad(end)}`
            };
        }

        function fmtDate(str) {
            const d = new Date(str + 'T00:00:00');
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const diff = d - today;
            if (diff === 0) return 'Today – ' + d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            if (diff === -86400000) return 'Yesterday – ' + d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            if (diff === 86400000) return 'Tomorrow – ' + d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }

        function fmtTime(t) {
            return new Date('2000-01-01 ' + t).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function fmtDateTime(dt) {
            return new Date(dt).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function esc(text) {
            if (!text) return '';
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        // ─── Init ─────────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            initCalendar();
            updateDeliveryDetails();
            populateCompletedTable();
        });
    </script>
</body>

</html>