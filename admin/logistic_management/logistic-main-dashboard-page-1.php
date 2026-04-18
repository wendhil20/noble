<?php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'dispatcher') {
    header("Location: logistic-dispatcher-dashboard-page-13.php");
    exit();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
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
    <title>Delivery Schedule View - Noble Home</title>
    <style>
        .calendar-day {
            transition: all 0.2s ease;
            cursor: pointer;
            min-height: 60px;
        }
        .calendar-day:hover {
            background-color: #e0f2fe;
            transform: scale(1.02);
        }
        .calendar-day.selected {
            background-color: #1976d2 !important;
            color: white;
        }
        .calendar-day.has-deliveries {
            background-color: #fff3e0;
            border: 2px solid #ff9800;
        }
        .calendar-day.has-overdue {
            background-color: #ffebee;
            border: 2px solid #f44336;
        }
        .calendar-day.busy {
            background-color: #f3e5f5;
            border: 2px solid #9c27b0;
        }
        .calendar-day.past-date {
            background-color: #f5f5f5;
            color: #666;
        }
        .delivery-badges {
            position: absolute;
            bottom: 2px;
            left: 2px;
            right: 2px;
            display: flex;
            flex-wrap: wrap;
            gap: 1px;
            justify-content: center;
        }
        .delivery-badge {
            font-size: 0.6rem;
            padding: 1px 3px;
            border-radius: 2px;
            font-weight: bold;
            min-width: 12px;
            text-align: center;
            line-height: 1.2;
        }
        .item-card {
            transition: all 0.3s ease;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .filter-btn.active {
            background-color: #3b82f6 !important;
            color: white !important;
        }
        .stat-card {
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .calendar-day.range-start,
.calendar-day.range-end {
    background-color: #1976d2 !important;
    color: white !important;
}
.calendar-day.in-range {
    background-color: #bbdefb !important;
    border-color: #90caf9 !important;
}
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-4 gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-truck text-blue-600"></i>
                        Delivery Schedule
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">Monitor and track all delivery schedules</p>
                </div>

                <!-- Filter Buttons -->
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="filterDeliveries('all')"
                        class="filter-btn active text-sm px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-all flex items-center gap-1.5">
                        <i class="fas fa-list text-xs"></i> All
                    </button>
                    <button type="button" onclick="filterDeliveries('overdue')"
                        class="filter-btn text-sm px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 transition-all flex items-center gap-1.5">
                        <i class="fas fa-exclamation-triangle text-xs"></i> Overdue
                        <?php if($stats['overdue_deliveries'] > 0): ?>
                        <span class="bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full leading-none"><?php echo $stats['overdue_deliveries']; ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" onclick="filterDeliveries('today')"
                        class="filter-btn text-sm px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 transition-all flex items-center gap-1.5">
                        <i class="fas fa-calendar-day text-xs"></i> Today
                        <?php if($stats['today_deliveries'] > 0): ?>
                        <span class="bg-blue-500 text-white text-xs px-1.5 py-0.5 rounded-full leading-none"><?php echo $stats['today_deliveries']; ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" onclick="filterDeliveries('upcoming')"
                        class="filter-btn text-sm px-3 py-1.5 rounded-lg border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 transition-all flex items-center gap-1.5">
                        <i class="fas fa-clock text-xs"></i> Upcoming
                    </button>
                    <button type="button" onclick="filterDeliveries('replacement')"
                        class="filter-btn text-sm px-3 py-1.5 rounded-lg border border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 transition-all flex items-center gap-1.5">
                        <i class="fas fa-exchange-alt text-xs"></i> Replacements
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
            <div class="stat-card bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar text-blue-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['total_scheduled']; ?></span>
                </div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Scheduled</p>
            </div>

            <div class="stat-card bg-white rounded-xl border border-red-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 bg-red-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-red-600"><?php echo $stats['overdue_deliveries']; ?></span>
                </div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue</p>
            </div>

            <div class="stat-card bg-white rounded-xl border border-yellow-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 bg-yellow-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_deliveries']; ?></span>
                </div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending</p>
            </div>

            <div class="stat-card bg-white rounded-xl border border-green-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['completed_deliveries']; ?></span>
                </div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Completed</p>
            </div>

            <div class="stat-card bg-white rounded-xl border border-purple-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 bg-purple-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-truck-fast text-purple-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['today_deliveries']; ?></span>
                </div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Today</p>
            </div>

            <div class="stat-card bg-white rounded-xl border border-orange-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 bg-orange-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exchange-alt text-orange-500 text-sm"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['replacement_deliveries']; ?></span>
                </div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Replacements</p>
            </div>
        </div>

        <!-- Calendar + Details -->
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-5 mb-6">
            <!-- Calendar Controls -->
<div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
    <button type="button" id="prevMonth" class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded-lg transition-colors">
        <i class="fas fa-chevron-left text-gray-500 text-xs"></i>
    </button>

    <div class="flex items-center gap-1.5 flex-wrap justify-center">
        <!-- Month Dropdown -->
        <select id="monthSelect" onchange="jumpToMonthYear()"
            class="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white text-gray-700 cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-400">
            <?php
            $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            foreach ($months as $i => $m) {
                echo "<option value=\"$i\">$m</option>";
            }
            ?>
        </select>

        <!-- Year Dropdown -->
        <select id="yearSelect" onchange="jumpToMonthYear()"
            class="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white text-gray-700 cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-400">
            <?php
            $currentYear = date('Y');
            for ($y = $currentYear - 2; $y <= $currentYear + 5; $y++) {
                echo "<option value=\"$y\">$y</option>";
            }
            ?>
        </select>

        <!-- Today Button -->
        <button type="button" onclick="goToToday()"
            class="text-xs px-2.5 py-1 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors font-medium">
            Today
        </button>
    </div>

    <button type="button" id="nextMonth" class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded-lg transition-colors">
        <i class="fas fa-chevron-right text-gray-500 text-xs"></i>
    </button>
</div>

    <!-- Quick Jump to Date -->
    <div class="flex items-center gap-1 mb-3">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-1.5 top-1/2 -translate-y-1/2 text-black text-xs"></i>
            <input type="text" id="customerSearch" placeholder="      Search customer or order"
                oninput="handleSearch(this.value)"
                class="w-full pl-3 pr-3 py-1.5 text-black text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-400 bg-white">
        </div>
        <input type="date" id="quickJumpDate" onchange="quickJumpToDate(this.value)"
            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-700 cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-400">
    </div>

<!-- Range Mode Toggle -->
<div class="flex items-center gap-2 mb-3">
    <label class="flex items-center gap-1.5 cursor-pointer select-none">
       <div class="relative">
    <input type="checkbox" id="rangeToggle" onchange="toggleRangeMode()" class="sr-only">
    <div id="rangeTrack" 
        class="w-8 h-4 bg-gray-200 rounded-full transition-colors flex items-center px-0.5">
        <div id="rangeThumb" 
            class="w-3 h-3 bg-white rounded-full shadow transition-transform">
        </div>
    </div>
</div>
        <span class="text-xs text-gray-500">Date range mode</span>
    </label>
    <span id="rangeLabel" class="text-xs text-blue-500 font-medium hidden"></span>
    <button type="button" id="clearRangeBtn" onclick="clearRange()" class="hidden text-xs text-red-400 hover:text-red-600 transition-colors">
        <i class="fas fa-times mr-1"></i>Clear
    </button>
</div>

            <!-- Calendar -->
            <div class="xl:col-span-3">
                <div class="bg-white rounded-xl border border-gray-200 p-5 h-full">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-blue-500"></i>
                        Delivery Calendar
                    </h3>

                    <div class="flex items-center justify-between mb-4">
                  
                        <h4 id="currentMonth" class="text-sm font-semibold text-gray-800"></h4>
                      
                    </div>

                    <div class="grid grid-cols-7 gap-1 mb-1">
                        <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day): ?>
                        <div class="text-center text-xs font-medium text-gray-400 py-1"><?php echo $day; ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div id="calendar-grid" class="grid grid-cols-7 gap-1"></div>

                    <!-- Legend -->
                    <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-2">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span class="w-3 h-3 bg-orange-100 border border-orange-400 rounded shrink-0"></span> Has deliveries
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span class="w-3 h-3 bg-red-100 border border-red-400 rounded shrink-0"></span> Has overdue
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span class="w-3 h-3 bg-purple-100 border border-purple-400 rounded shrink-0"></span> Busy (10+)
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span class="w-3 h-3 bg-blue-600 rounded shrink-0"></span> Selected
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Details Panel -->
            <div class="xl:col-span-2">
                <div class="bg-white rounded-xl border border-gray-200 h-full flex flex-col">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                                <i class="fas fa-list-ul text-purple-500"></i>
                                <span id="details-title">All Deliveries</span>
                            </h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span id="items-count" class="bg-purple-50 text-purple-700 border border-purple-100 px-2 py-0.5 rounded-full text-xs font-medium">
                                    <?php echo count($schedules); ?> items
                                </span>
                                <span id="filter-status" class="text-xs text-gray-400"></span>
                            </div>
                        </div>
                        <button type="button" id="navigateBtn" onclick="navigateToDetailedView()"
                            class="hidden text-xs bg-blue-500 text-white px-3 py-1.5 rounded-lg hover:bg-blue-600 transition-colors flex items-center gap-1.5">
                            <i class="fas fa-external-link-alt"></i> View Details
                        </button>
                    </div>
                    <div id="delivery-details-container" class="flex-1 overflow-y-auto max-h-[700px]"></div>
                </div>
            </div>
        </div>

        <!-- Overdue Table -->
        <?php
        $overdueSchedules = array_filter($schedules, fn($s) => $s['delivery_status'] === 'overdue');
        usort($overdueSchedules, fn($a, $b) => $b['days_overdue'] - $a['days_overdue']);
        ?>

        <?php if (!empty($overdueSchedules)): ?>
        <div id="overdue-section" class="mb-5">
            <div class="bg-white border border-red-200 rounded-xl overflow-hidden">
                <div class="bg-red-50 px-4 py-3 flex items-center gap-3 border-b border-red-100 cursor-pointer"
                     onclick="toggleOverduePanel()">
                    <span class="relative flex h-2.5 w-2.5 flex-shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                    </span>
                    <i class="fas fa-exclamation-circle text-red-500 text-sm"></i>
                    <span class="text-red-700 text-sm font-semibold">
                        <?php echo count($overdueSchedules); ?> Overdue
                        <?php echo count($overdueSchedules) > 1 ? 'Deliveries' : 'Delivery'; ?>
                    </span>
                    <span class="text-red-400 text-xs">— action needed</span>
                    <i class="fas fa-chevron-up text-red-400 text-xs ml-auto" id="overdue-toggle-icon"></i>
                </div>

                <div id="overdue-panel-body" class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-red-50 text-red-700 border-b border-red-100">
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Order</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Customer</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Sched. Date</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Status</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Courier</th>
                                <th class="px-3 py-2 text-left font-semibold">Address</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Mobile</th>
                                <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Total</th>
                                <th class="px-3 py-2 text-center font-semibold whitespace-nowrap">Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdueSchedules as $i => $schedule): ?>
                            <tr class="border-b border-red-50 hover:bg-red-50 transition-colors <?php echo $i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'; ?>">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="font-bold text-red-700">#<?php echo $schedule['order_id']; ?></span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-800">
                                    <?php echo htmlspecialchars($schedule['customer_name']); ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    <i class="fas fa-calendar text-gray-300 mr-1"></i>
                                    <?php echo date('M d, Y', strtotime($schedule['delivery_date'])); ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <?php if ($schedule['booking_status']): ?>
                                        <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full text-xs capitalize">
                                            <?php echo str_replace('_', ' ', $schedule['booking_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full text-xs">No booking</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    <?php if ($schedule['courier_name']): ?>
                                        <i class="fas fa-truck text-gray-300 mr-1"></i>
                                        <?php echo htmlspecialchars($schedule['courier_name']); ?>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-gray-600 max-w-[200px] truncate">
                                    <i class="fas fa-location-dot text-gray-300 mr-1"></i>
                                    <?php echo htmlspecialchars($schedule['address']); ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <?php if ($schedule['mobile']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($schedule['mobile']); ?>"
                                           class="text-blue-500 hover:underline">
                                            <i class="fas fa-phone text-gray-300 mr-1"></i>
                                            <?php echo htmlspecialchars($schedule['mobile']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap font-medium text-gray-700">
                                    ₱<?php echo number_format($schedule['final_total'], 2); ?>
                                </td>
                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                    <span class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full font-bold">
                                        <?php echo $schedule['days_overdue']; ?>d
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Completed Deliveries -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i>
                <h3 class="text-base font-semibold text-gray-800">Completed Deliveries</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-green-50 border-b border-green-100 text-xs text-gray-600 uppercase tracking-wide">
                            <th class="px-5 py-3 text-left font-semibold">Order ID</th>
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

            <div id="no-completed-message" class="hidden p-10 text-center">
                <i class="fas fa-inbox text-5xl text-gray-200 mb-3 block"></i>
                <p class="text-sm font-medium text-gray-500">No Completed Deliveries Yet</p>
                <p class="text-xs text-gray-400 mt-1">Completed deliveries will appear here</p>
            </div>
        </div>

    </div><!-- end max-w wrapper -->

    <script>
        let rangeMode = false;
let rangeStart = null;
let rangeEnd = null;
let searchQuery = '';

function jumpToMonthYear() {
    const month = parseInt(document.getElementById('monthSelect').value);
    const year = parseInt(document.getElementById('yearSelect').value);
    currentDate = new Date(year, month, 1);
    updateCalendarHeader();
    generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
}

function goToToday() {
    currentDate = new Date();
    updateCalendarHeader();
    generateCalendar(currentDate.getFullYear(), currentDate.getMonth());

    // Auto-select today
    const todayStr = getTodayString();
    const todayEl = document.querySelector(`[data-date="${todayStr}"]`);
    if (todayEl) selectDate(todayStr, todayEl);
}

function quickJumpToDate(dateString) {
    if (!dateString) return;
    const [year, month] = dateString.split('-').map(Number);
    currentDate = new Date(year, month - 1, 1);
    updateCalendarHeader();
    generateCalendar(currentDate.getFullYear(), currentDate.getMonth());

    setTimeout(() => {
        const el = document.querySelector(`[data-date="${dateString}"]`);
        if (el) selectDate(dateString, el);
    }, 50);
}

function toggleRangeMode() {
    rangeMode = document.getElementById('rangeToggle').checked;
    const track = document.getElementById('rangeTrack');
    const thumb = document.getElementById('rangeThumb');
    track.style.backgroundColor = rangeMode ? '#3b82f6' : '';
    thumb.style.transform = rangeMode ? 'translateX(16px)' : '';
    if (!rangeMode) clearRange();
}

function clearRange() {
    rangeStart = null;
    rangeEnd = null;
    document.getElementById('rangeLabel').classList.add('hidden');
    document.getElementById('clearRangeBtn').classList.add('hidden');
    document.querySelectorAll('.calendar-day').forEach(el => {
        el.classList.remove('in-range', 'range-start', 'range-end');
    });
    updateDeliveryDetails();
}

function handleSearch(query) {
    searchQuery = query.trim().toLowerCase();
    updateDeliveryDetails(selectedDate);
}

function handleRangeClick(dateString, element) {
    if (!rangeStart || (rangeStart && rangeEnd)) {
        // Start fresh
        clearRange();
        rangeStart = dateString;
        element.classList.add('range-start');
        document.getElementById('rangeLabel').textContent = `From: ${formatDisplayDate(dateString)}`;
        document.getElementById('rangeLabel').classList.remove('hidden');
    } else {
        // Set end
        if (dateString < rangeStart) {
            rangeEnd = rangeStart;
            rangeStart = dateString;
        } else {
            rangeEnd = dateString;
        }
        highlightRange();
        document.getElementById('rangeLabel').textContent =
            `${new Date(rangeStart + 'T00:00:00').toLocaleDateString('en-US', {month:'short',day:'numeric'})} → ${new Date(rangeEnd + 'T00:00:00').toLocaleDateString('en-US', {month:'short',day:'numeric'})}`;
        document.getElementById('clearRangeBtn').classList.remove('hidden');
        updateDeliveryDetailsForRange();
    }
}

function highlightRange() {
    document.querySelectorAll('.calendar-day').forEach(el => {
        el.classList.remove('in-range', 'range-start', 'range-end');
        const d = el.dataset.date;
        if (!d) return;
        if (d === rangeStart) el.classList.add('range-start');
        else if (d === rangeEnd) el.classList.add('range-end');
        else if (d > rangeStart && d < rangeEnd) el.classList.add('in-range');
    });
}

function updateDeliveryDetailsForRange() {
    const filtered = schedules.filter(s =>
        s.delivery_date >= rangeStart && s.delivery_date <= rangeEnd
    );
    const count = filtered.length;
    document.getElementById('details-title').textContent = `Range: ${count} Deliverie${count !== 1 ? 's' : ''}`;
    renderDeliveryList(filtered);
}


        function dismissBanner() {
            const banner = document.getElementById('overdue-banner');
            if (banner) {
                banner.style.transition = 'opacity 0.3s ease, max-height 0.3s ease';
                banner.style.opacity = '0';
                setTimeout(() => banner.style.display = 'none', 300);
            }
        }

        function scrollToPanel() {
            const panel = document.getElementById('overdue-panel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function toggleOverduePanel() {
            const body = document.getElementById('overdue-panel-body');
            const icon = document.getElementById('overdue-toggle-icon');
            const isHidden = body.style.display === 'none';
            body.style.display = isHidden ? 'block' : 'none';
            icon.className = isHidden
                ? 'fas fa-chevron-up text-red-400 text-xs ml-auto'
                : 'fas fa-chevron-down text-red-400 text-xs ml-auto';
        }

        const deliveryCounts = <?php echo json_encode($deliveryCountsByDate); ?>;
        const schedules = <?php echo json_encode($schedules); ?>;

        let currentDate = new Date();
        let selectedDate = null;
        let currentFilter = 'all';

        function getCurrentMonthRange() {
            const now = new Date();
            const year = now.getFullYear();
            const month = now.getMonth();
            const endOfMonth = new Date(year, month + 1, 0);
            const startDateString = year + '-' + String(month + 1).padStart(2, '0') + '-01';
            const endDateString = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(endOfMonth.getDate()).padStart(2, '0');
            return { startDateString, endDateString };
        }

        function filterSchedulesByCurrentMonth(schedules) {
            const { startDateString, endDateString } = getCurrentMonthRange();
            return schedules.filter(schedule =>
                schedule.delivery_date >= startDateString &&
                schedule.delivery_date <= endDateString
            );
        }

        function generateCalendar(year, month) {
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();

            const calendarGrid = document.getElementById('calendar-grid');
            calendarGrid.innerHTML = '';

            for (let i = 0; i < startingDayOfWeek; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'p-3';
                calendarGrid.appendChild(emptyDay);
            }

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let day = 1; day <= daysInMonth; day++) {
                const dateString = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day relative p-3 text-center rounded-lg border border-gray-200 bg-white flex flex-col justify-center items-center';

                const dayNumber = document.createElement('div');
                dayNumber.textContent = day;
                dayNumber.className = 'font-semibold';
                dayElement.appendChild(dayNumber);
                dayElement.dataset.date = dateString;

                const currentDateOnly = new Date(year, month, day);
                currentDateOnly.setHours(0, 0, 0, 0);

                const deliveryData = deliveryCounts[dateString];

                if (currentDateOnly < today) dayElement.className += ' past-date';

                if (deliveryData) {
                    const totalCount = deliveryData.count;
                    const overdueCount = deliveryData.overdue_count;
                    const completedCount = deliveryData.completed_count;
                    const pendingCount = deliveryData.pending_count;

                    const badgesContainer = document.createElement('div');
                    badgesContainer.className = 'delivery-badges';

                    if (overdueCount > 0) {
                        dayElement.className += ' has-overdue';
                        const overdueBadge = document.createElement('div');
                        overdueBadge.className = 'delivery-badge bg-red-500 text-white';
                        overdueBadge.textContent = overdueCount;
                        overdueBadge.title = `${overdueCount} overdue`;
                        badgesContainer.appendChild(overdueBadge);
                    }
                    if (pendingCount > 0) {
                        const pendingBadge = document.createElement('div');
                        pendingBadge.className = 'delivery-badge bg-yellow-500 text-white';
                        pendingBadge.textContent = pendingCount;
                        pendingBadge.title = `${pendingCount} pending`;
                        badgesContainer.appendChild(pendingBadge);
                    }
                    if (completedCount > 0) {
                        const completedBadge = document.createElement('div');
                        completedBadge.className = 'delivery-badge bg-green-500 text-white';
                        completedBadge.textContent = completedCount;
                        completedBadge.title = `${completedCount} completed`;
                        badgesContainer.appendChild(completedBadge);
                    }

                    if (totalCount >= 10) {
                        dayElement.className += ' busy';
                    } else if (overdueCount === 0 && (pendingCount > 0 || completedCount > 0)) {
                        dayElement.className += ' has-deliveries';
                    }

                    dayElement.appendChild(badgesContainer);
                }

                dayElement.addEventListener('click', function () {
                    selectDate(dateString, dayElement);
                });

                calendarGrid.appendChild(dayElement);
            }
        }

        function selectDate(dateString, element) {
    if (rangeMode) {
        handleRangeClick(dateString, element);
        return;
    }
    const previousSelected = document.querySelector('.calendar-day.selected');
    if (previousSelected) previousSelected.classList.remove('selected');
    element.classList.add('selected');
    selectedDate = dateString;

    const navigateBtn = document.getElementById('navigateBtn');
    const dateSchedules = schedules.filter(s => s.delivery_date === dateString);
    navigateBtn.classList.toggle('hidden', dateSchedules.length === 0);

    updateDeliveryDetails(dateString);
}

        function navigateToDetailedView() {
            if (selectedDate) {
                window.location.href = `logistic-delivery-date-orders-page-2.php?date=${selectedDate}`;
            }
        }

        function filterDeliveries(filterType) {
            currentFilter = filterType;

            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            const activeBtn = event.target.closest('button');
            activeBtn.classList.add('active');

            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) previousSelected.classList.remove('selected');
            selectedDate = null;

            document.getElementById('navigateBtn').classList.add('hidden');
            updateDeliveryDetails();
        }



        function updateDeliveryDetails(selectedDate = null) {
            const detailsContainer = document.getElementById('delivery-details-container');
            const itemsCountSpan = document.getElementById('items-count');
            const detailsTitle = document.getElementById('details-title');
            const filterStatus = document.getElementById('filter-status');

            let filteredSchedules = schedules;

            if (selectedDate) {
                filteredSchedules = schedules.filter(s => s.delivery_date === selectedDate);
                detailsTitle.textContent = `Deliveries for ${formatDisplayDate(selectedDate)}`;
                filterStatus.textContent = '';
            } else {
                if (currentFilter === 'overdue') {
                    filteredSchedules = schedules.filter(s => s.delivery_status === 'overdue');
                    detailsTitle.textContent = 'Overdue Deliveries';
                    filterStatus.textContent = 'Showing overdue items only';
                } else if (currentFilter === 'today') {
                    filteredSchedules = schedules.filter(s => s.delivery_status === 'today_pending' ||
                        (s.delivery_date === getTodayString() && s.delivery_status === 'completed'));
                    detailsTitle.textContent = "Today's Deliveries";
                    filterStatus.textContent = "Showing today's deliveries only";
                } else if (currentFilter === 'upcoming') {
                    filteredSchedules = schedules.filter(s => s.delivery_status === 'upcoming');
                    detailsTitle.textContent = 'Upcoming Deliveries';
                    filterStatus.textContent = 'Showing upcoming deliveries only';
                } else if (currentFilter === 'replacement') {
                    filteredSchedules = schedules.filter(s => s.item_type === 'replacement');
                    detailsTitle.textContent = 'Replacement Deliveries';
                    filterStatus.textContent = 'Showing replacement deliveries only';
                } else {
                    filteredSchedules = filterSchedulesByCurrentMonth(schedules);
                    const currentMonthName = new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' });
                    detailsTitle.textContent = `All Deliveries - ${currentMonthName}`;
                    filterStatus.textContent = 'Showing current month deliveries only';
                }
            }

              // ✅ ILAGAY DITO — pagkatapos ng lahat ng filters, bago ang empty-check
    if (searchQuery) {
        filteredSchedules = filteredSchedules.filter(s =>
            s.customer_name?.toLowerCase().includes(searchQuery) ||
            String(s.order_id).includes(searchQuery) ||
            s.address?.toLowerCase().includes(searchQuery)
        );
        detailsTitle.textContent = `Search: "${searchQuery}"`;
        filterStatus.textContent = `${filteredSchedules.length} result(s)`;
    }

            itemsCountSpan.textContent = `${filteredSchedules.length} items`;

            if (filteredSchedules.length === 0) {
                let emptyMessage = 'No deliveries found';
                if (selectedDate) emptyMessage = `No deliveries scheduled for ${formatDisplayDate(selectedDate)}`;
                else if (currentFilter === 'overdue') emptyMessage = 'No overdue deliveries';
                else if (currentFilter === 'today') emptyMessage = 'No deliveries scheduled for today';
                else if (currentFilter === 'upcoming') emptyMessage = 'No upcoming deliveries';
                else if (currentFilter === 'replacement') emptyMessage = 'No replacement deliveries';
                else if (currentFilter === 'all') {
                    const currentMonthName = new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' });
                    emptyMessage = `No deliveries scheduled for ${currentMonthName}`;
                }

                detailsContainer.innerHTML = `
                    <div class="p-8 text-center">
                        <i class="fas fa-calendar-day text-5xl text-gray-200 mb-3 block"></i>
                        <p class="text-sm font-medium text-gray-500 mb-1">${emptyMessage}</p>
                        ${selectedDate || currentFilter !== 'all' ? `
                            <button type="button" onclick="showAllDeliveries()" class="mt-3 text-xs text-blue-500 hover:text-blue-700">
                                <i class="fas fa-list mr-1"></i>View All Deliveries
                            </button>
                        ` : ''}
                    </div>
                `;
                return;
            }

            const schedulesByDate = {};
            filteredSchedules.forEach(schedule => {
                const date = schedule.delivery_date;
                if (!schedulesByDate[date]) schedulesByDate[date] = {};
                const time = schedule.delivery_time;
                if (!schedulesByDate[date][time]) schedulesByDate[date][time] = [];
                schedulesByDate[date][time].push(schedule);
            });

            let html = '';

            const sortedDates = Object.keys(schedulesByDate).sort((a, b) => {
                if (currentFilter === 'overdue') return new Date(b) - new Date(a);
                return new Date(a) - new Date(b);
            });

            sortedDates.forEach(date => {
                const dateSchedules = schedulesByDate[date];
                const dateObj = new Date(date + 'T00:00:00');
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                let dateClass = '';
                let dateIcon = 'fa-calendar';

                const hasOverdueItems = Object.values(dateSchedules).some(timeSchedules =>
                    timeSchedules.some(schedule => schedule.delivery_status === 'overdue')
                );

                if (hasOverdueItems) {
                    dateClass = 'bg-red-50 border-red-200';
                    dateIcon = 'fa-exclamation-triangle text-red-600';
                } else if (dateObj.getTime() === today.getTime()) {
                    dateClass = 'bg-blue-50 border-blue-200';
                    dateIcon = 'fa-calendar-day text-blue-600';
                } else {
                    dateClass = 'bg-green-50 border-green-200';
                    dateIcon = 'fa-calendar text-green-600';
                }

                const totalDateItems = Object.values(dateSchedules).reduce((sum, timeItems) => sum + timeItems.length, 0);

                html += `
                    <div class="border-b border-gray-200">
                        <div class="${dateClass} px-6 py-4 border-l-4">
                            <h4 class="text-lg font-bold text-gray-800 flex items-center">
                                <i class="fas ${dateIcon} mr-3"></i>
                                ${formatDisplayDate(date)}
                                <span class="ml-3 bg-white bg-opacity-70 px-3 py-1 rounded-full text-sm font-medium">
                                    ${totalDateItems} items
                                </span>
                                ${hasOverdueItems ? `<span class="ml-2 bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold">OVERDUE</span>` : ''}
                            </h4>
                        </div>
                `;

                const sortedTimes = Object.keys(dateSchedules).sort();

                sortedTimes.forEach(time => {
                    const timeSchedules = dateSchedules[time];

                    html += `
                        <div class="bg-gray-50 px-6 py-2">
                            <h5 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                                <i class="fas fa-clock mr-2 text-gray-500"></i>
                                ${formatTime(time)} (${timeSchedules.length} items)
                            </h5>
                        </div>
                        <div class="p-4 space-y-3">
                    `;

                    timeSchedules.forEach(schedule => {
                        const isCompleted = schedule.booking_status === 'delivered' || schedule.booking_status === 'picked_up';
                        const isOverdue = schedule.delivery_status === 'overdue';
                        const isToday = schedule.delivery_status === 'today_pending';

                        let statusClass, statusIcon, statusText, statusBg;

                        if (isCompleted) {
                            statusClass = 'bg-green-100 text-green-800 border-green-200';
                            statusIcon = 'fa-check-circle';
                            statusText = 'Delivered';
                            statusBg = 'bg-green-50 border-green-200';
                        } else if (isOverdue) {
                            statusClass = 'bg-red-100 text-red-800 border-red-200';
                            statusIcon = 'fa-exclamation-triangle';
                            statusText = `Overdue (${schedule.days_overdue} days)`;
                            statusBg = 'bg-red-50 border-red-200';
                        } else if (isToday) {
                            statusClass = 'bg-blue-100 text-blue-800 border-blue-200';
                            statusIcon = 'fa-clock';
                            statusText = 'Due Today';
                            statusBg = 'bg-blue-50 border-blue-200';
                        } else {
                            statusClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                            statusIcon = 'fa-clock';
                            statusText = 'Scheduled';
                            statusBg = 'bg-yellow-50 border-yellow-200';
                        }

                        html += `
        <div class="item-card ${statusBg} border-2 rounded-lg p-4 hover:shadow-md">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <h6 class="font-semibold text-gray-900 text-xl mb-2 flex items-center">
                        <i class="fas fa-shopping-cart mr-2 text-blue-600"></i>
                        Order #${schedule.order_id}
                        ${schedule.item_type === 'replacement' ? `
                            <span class="bg-orange-500 text-white px-2 py-1 rounded-full text-xs font-bold ml-2">
                                <i class="fas fa-exchange-alt mr-1"></i>REPLACEMENT
                            </span>
                        ` : ''}
                    </h6>
                    <div class="flex items-center space-x-3 mb-2">
                        ${schedule.booking_status && schedule.booking_status !== 'pending' ? `
                            <span class="bg-indigo-100 text-indigo-700 border border-indigo-200 px-2 py-1 rounded text-xs font-medium">
                                <i class="fas fa-info-circle mr-1"></i>${schedule.booking_status.replace('_', ' ').toUpperCase()}
                            </span>
                        ` : ''}
                        <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs font-medium">
                            <i class="fas fa-boxes mr-1"></i>${schedule.total_items} item(s)
                        </span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm text-gray-700 mb-3">
                <div class="bg-white bg-opacity-50 p-2 rounded">
                    <span class="font-medium text-gray-600">Total Items:</span>
                    <span class="font-semibold">${schedule.total_items}</span>
                </div>
                <div class="bg-white bg-opacity-50 p-2 rounded">
                    <span class="font-medium text-gray-600">Total Quantity:</span>
                    <span class="font-semibold">${schedule.total_quantity}</span>
                </div>
                <div class="bg-white bg-opacity-50 p-2 rounded col-span-2">
                    <span class="font-medium text-gray-600">Order Total:</span>
                    <span class="font-semibold">₱${parseFloat(schedule.final_total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                </div>
            </div>
            <div class="bg-white bg-opacity-50 rounded-lg p-3 space-y-2 break-all">
                <div class="flex items-center">
                    <i class="fas fa-user mr-3 text-gray-500 w-4"></i>
                    <span class="font-medium text-gray-800">${escapeHtml(schedule.customer_name)}</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-map-marker-alt mr-3 text-gray-500 w-4 mt-1"></i>
                    <span class="text-gray-700 text-sm flex-1">${escapeHtml(schedule.address)}</span>
                </div>
                ${schedule.mobile ? `
                <div class="flex items-center">
                    <i class="fas fa-phone mr-3 text-gray-500 w-4"></i>
                    <span class="text-gray-700 text-sm">${escapeHtml(schedule.mobile)}</span>
                </div>` : ''}
                ${schedule.courier_name ? `
                <div class="flex items-center">
                    <i class="fas fa-shipping-fast mr-3 text-gray-500 w-4"></i>
                    <span class="text-gray-700 text-sm">Courier: ${escapeHtml(schedule.courier_name)}</span>
                </div>` : ''}
                ${schedule.tracking_number ? `
                <div class="flex items-center">
                    <i class="fas fa-barcode mr-3 text-gray-500 w-4"></i>
                    <span class="text-gray-700 text-sm">Tracking: ${escapeHtml(schedule.tracking_number)}</span>
                </div>` : ''}
                ${schedule.booking_reference ? `
                <div class="flex items-center">
                    <i class="fas fa-receipt mr-3 text-gray-500 w-4"></i>
                    <span class="text-gray-700 text-sm">Booking Ref: ${escapeHtml(schedule.booking_reference)}</span>
                </div>` : ''}
                ${schedule.booking_type && schedule.booking_type !== 'delivery' ? `
                <div class="flex items-center">
                    <i class="fas fa-hand-holding-box mr-3 text-gray-500 w-4"></i>
                    <span class="text-gray-700 text-sm capitalize">Type: ${schedule.booking_type}</span>
                </div>` : ''}
                ${schedule.delivery_notes ? `
                <div class="mt-2 bg-yellow-100 p-2 rounded border border-yellow-200">
                    <div class="flex items-start">
                        <i class="fas fa-sticky-note mr-2 text-yellow-600 mt-1"></i>
                        <span class="text-gray-700 text-sm flex-1">${escapeHtml(schedule.delivery_notes)}</span>
                    </div>
                </div>` : ''}
                ${isCompleted && schedule.actual_delivery_time ? `
                <div class="mt-2 bg-green-100 p-2 rounded border border-green-200">
                    <div class="flex items-center">
                        <i class="fas fa-check-double mr-2 text-green-600"></i>
                        <span class="text-green-700 text-sm">Delivered: ${formatDateTime(schedule.actual_delivery_time)}</span>
                    </div>
                </div>` : ''}
                ${schedule.item_type === 'replacement' && schedule.replacement_reason ? `
                <div class="mt-3 bg-orange-50 border border-orange-200 rounded-lg p-3">
                    <h6 class="font-semibold text-orange-800 mb-2 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>Replacement Details
                    </h6>
                    <div class="space-y-1 text-sm text-gray-700">
                        <div><span class="font-medium">Reason:</span> ${escapeHtml(schedule.replacement_reason)}</div>
                        ${schedule.replacement_details ? `<div><span class="font-medium">Details:</span> ${escapeHtml(schedule.replacement_details)}</div>` : ''}
                        <div><span class="font-medium">Quantity:</span> ${schedule.replacement_quantity}</div>
                        ${schedule.replacement_status ? `<div><span class="font-medium">Status:</span> <span class="capitalize">${schedule.replacement_status.replace('_', ' ')}</span></div>` : ''}
                    </div>
                </div>` : ''}
            </div>
        </div>`;
                    });

                    html += '</div>';
                });

                html += '</div>';
            });

            detailsContainer.innerHTML = html;
        }

        function showAllDeliveries() {
            currentFilter = 'all';
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector('button[onclick="filterDeliveries(\'all\')"]').classList.add('active');

            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) previousSelected.classList.remove('selected');
            selectedDate = null;
            document.getElementById('navigateBtn').classList.add('hidden');
            updateDeliveryDetails();
        }

        function formatDisplayDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const dateOnly = new Date(date);
            dateOnly.setHours(0, 0, 0, 0);

            if (dateOnly.getTime() === today.getTime()) {
                return 'Today - ' + date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            } else if (dateOnly.getTime() === today.getTime() - 86400000) {
                return 'Yesterday - ' + date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            } else if (dateOnly.getTime() === today.getTime() + 86400000) {
                return 'Tomorrow - ' + date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            } else {
                return date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            }
        }

        function formatTime(timeString) {
            const time = new Date('2000-01-01 ' + timeString);
            return time.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function formatDateTime(datetimeString) {
            const date = new Date(datetimeString);
            return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function getTodayString() {
            const today = new Date();
            return today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

function updateCalendarHeader() {
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    document.getElementById('currentMonth').textContent = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();

    // Sync dropdowns
    const ms = document.getElementById('monthSelect');
    const ys = document.getElementById('yearSelect');
    if (ms) ms.value = currentDate.getMonth();
    if (ys) ys.value = currentDate.getFullYear();
}
        function initializeCalendar() {
            updateCalendarHeader();
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());

            document.getElementById('prevMonth').addEventListener('click', function () {
                currentDate.setMonth(currentDate.getMonth() - 1);
                updateCalendarHeader();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            });

            document.getElementById('nextMonth').addEventListener('click', function () {
                currentDate.setMonth(currentDate.getMonth() + 1);
                updateCalendarHeader();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            });
        }

        function populateCompletedDeliveriesTable() {
            const completedSchedules = schedules.filter(s => s.delivery_status === 'completed');
            const tableBody = document.getElementById('completed-table-body');
            const noMessage = document.getElementById('no-completed-message');

            if (completedSchedules.length === 0) {
                tableBody.innerHTML = '';
                noMessage.classList.remove('hidden');
                return;
            }

            noMessage.classList.add('hidden');

            completedSchedules.sort((a, b) => {
                if (!a.actual_delivery_time || !b.actual_delivery_time) return 0;
                return new Date(b.actual_delivery_time) - new Date(a.actual_delivery_time);
            });

            let html = '';

            completedSchedules.forEach(schedule => {
                const deliveryDate = new Date(schedule.delivery_date + 'T00:00:00');
                const completedDate = schedule.actual_delivery_time ? new Date(schedule.actual_delivery_time) : null;

                html += `
                <tr class="border-b border-gray-100 hover:bg-green-50 transition-colors">
                    <td class="px-5 py-3 text-sm font-medium text-blue-600">
                        <a href="#" class="hover:underline">#${schedule.order_id}</a>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-800">${escapeHtml(schedule.customer_name)}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">
                        ${deliveryDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">
                        ${completedDate ? completedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '<span class="text-gray-300">—</span>'}
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">
                        ${schedule.courier_name ? escapeHtml(schedule.courier_name) : '<span class="text-gray-300">—</span>'}
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">
                        ${schedule.tracking_number ? escapeHtml(schedule.tracking_number) : '<span class="text-gray-300">—</span>'}
                    </td>
                    <td class="px-5 py-3 text-sm font-semibold text-gray-900">
                        ₱${parseFloat(schedule.final_total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </td>
                    <td class="px-5 py-3 text-sm">
                        <span class="bg-green-100 text-green-700 border border-green-200 px-2.5 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1.5">
                            <i class="fas fa-check-circle"></i>
                            ${schedule.booking_status === 'delivered' ? 'Delivered' : 'Picked Up'}
                        </span>
                    </td>
                </tr>`;
            });

            tableBody.innerHTML = html;
        }

        document.addEventListener('DOMContentLoaded', function () {
            initializeCalendar();
            updateDeliveryDetails();
            populateCompletedDeliveriesTable();
        });
    </script>
</body>
</html>