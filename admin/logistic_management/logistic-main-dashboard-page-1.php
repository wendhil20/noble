<?php
//main_dashboard.php
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

// Get delivery schedules including past dates for overdue tracking
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

// Get delivery counts by date for calendar display (including past dates)
$countSql = "SELECT 
    DATE(ds.delivery_date) as date, 
    COUNT(*) as count,
    COUNT(CASE WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) THEN 1 END) as pending_count,
    COUNT(CASE WHEN db.booking_status IN ('delivered', 'picked_up') THEN 1 END) as completed_count,
    COUNT(CASE WHEN (db.booking_status IS NULL OR db.booking_status NOT IN ('delivered', 'picked_up')) 
                AND ds.delivery_date < CURDATE() THEN 1 END) as overdue_count
FROM delivery_schedules ds
LEFT JOIN delivery_bookings db ON ds.id = db.delivery_schedule_id
WHERE ds.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 3650 DAY)
GROUP BY DATE(ds.delivery_date)";

$countStmt = $conn->prepare($countSql);
$countStmt->execute();
$deliveryCounts = $countStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$countStmt->close();

// Convert to associative array for easier lookup
$deliveryCountsByDate = [];
foreach ($deliveryCounts as $data) {
    $deliveryCountsByDate[$data['date']] = $data;
}

// Get summary statistics including overdue
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
WHERE ds.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

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
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-transparent">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-4 space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">

                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Delivery Schedule View</h1>
                        <p class="text-gray-600 mt-1">View and monitor all delivery schedules</p>
                    </div>
                </div>

                <!-- Quick Filter Buttons -->
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="filterDeliveries('all')"
                        class="filter-btn bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors active">
                        <i class="fas fa-list mr-1"></i>All
                    </button>
                    <button type="button" onclick="filterDeliveries('overdue')"
                        class="filter-btn bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors">
                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                    </button>
                    <button type="button" onclick="filterDeliveries('today')"
                        class="filter-btn bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition-colors">
                        <i class="fas fa-calendar-day mr-1"></i>Today
                    </button>
                    <button type="button" onclick="filterDeliveries('upcoming')"
                        class="filter-btn bg-green-100 text-green-700 px-4 py-2 rounded-lg hover:bg-green-200 transition-colors">
                        <i class="fas fa-clock mr-1"></i>Upcoming
                    </button>
                    <button type="button" onclick="filterDeliveries('replacement')"
                        class="filter-btn bg-orange-100 text-orange-700 px-4 py-2 rounded-lg hover:bg-orange-200 transition-colors">
                        <i class="fas fa-exchange-alt mr-1"></i>Replacements
                    </button>
 
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <!-- Total Scheduled -->
            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:border-blue-300 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-calendar text-blue-500 text-lg"></i>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_scheduled']; ?></p>
                </div>
                <p class="text-xs text-gray-600">Total Scheduled</p>
            </div>

            <!-- Overdue -->
            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:border-red-300 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['overdue_deliveries']; ?></p>
                </div>
                <p class="text-xs text-gray-600">Overdue</p>
            </div>

            <!-- Pending -->
            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:border-yellow-300 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-clock text-yellow-500 text-lg"></i>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_deliveries']; ?></p>
                </div>
                <p class="text-xs text-gray-600">Pending</p>
            </div>

            <!-- Completed -->
            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:border-green-300 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['completed_deliveries']; ?></p>
                </div>
                <p class="text-xs text-gray-600">Completed</p>
            </div>

            <!-- Today's Deliveries -->
            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:border-purple-300 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-truck-fast text-purple-500 text-lg"></i>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['today_deliveries']; ?></p>
                </div>
                <p class="text-xs text-gray-600">Today's Deliveries</p>
            </div>

            <!-- Replacements -->
            <div class="bg-white rounded-lg border border-gray-200 p-4 hover:border-orange-300 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-exchange-alt text-orange-500 text-lg"></i>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['replacement_deliveries']; ?></p>
                </div>
                <p class="text-xs text-gray-600">Replacements</p>
            </div>
        </div>


        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

            <!-- Calendar Section -->
            <div class="xl:col-span-3">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-calendar text-blue-600 mr-3"></i>
                        Delivery Calendar
                    </h3>

                    <!-- Calendar Navigation -->
                    <div class="flex items-center justify-between mb-4">
                        <button type="button" id="prevMonth" class="p-2 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-chevron-left text-gray-600"></i>
                        </button>
                        <h4 id="currentMonth" class="text-lg font-semibold text-gray-900"></h4>
                        <button type="button" id="nextMonth" class="p-2 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-chevron-right text-gray-600"></i>
                        </button>
                    </div>

                    <!-- Calendar Grid -->
                    <div class="grid grid-cols-7 gap-1 mb-2">
                        <div class="text-center text-sm font-medium text-gray-500 p-2">Sun</div>
                        <div class="text-center text-sm font-medium text-gray-500 p-2">Mon</div>
                        <div class="text-center text-sm font-medium text-gray-500 p-2">Tue</div>
                        <div class="text-center text-sm font-medium text-gray-500 p-2">Wed</div>
                        <div class="text-center text-sm font-medium text-gray-500 p-2">Thu</div>
                        <div class="text-center text-sm font-medium text-gray-500 p-2">Fri</div>
                        <div class="text-center text-sm font-medium text-gray-500 p-2">Sat</div>
                    </div>

                    <div id="calendar-grid" class="grid grid-cols-7 gap-1"></div>

                    <!-- Legend -->
                    <div class="mt-6 grid grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-orange-100 border-2 border-orange-500 rounded mr-2"></div>
                            <span>Has deliveries</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-red-100 border-2 border-red-500 rounded mr-2"></div>
                            <span>Has overdue</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-purple-100 border-2 border-purple-500 rounded mr-2"></div>
                            <span>Busy (10+ deliveries)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-blue-600 rounded mr-2"></div>
                            <span>Selected date</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Details Section -->
            <div class="xl:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <!-- MODIFICATION 1: Updated header with navigation button -->
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-list text-purple-600 mr-3"></i>
                                <span id="details-title">All Deliveries</span>
                            </h3>
                            <!-- Navigation button - only shows when a date is selected -->
                            <button type="button" id="navigateBtn" onclick="navigateToDetailedView()"
                                class="hidden bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                View Details
                            </button>
                        </div>
                        <div class="flex items-center mt-2 space-x-4">
                            <span id="items-count" class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                                <?php echo count($schedules); ?> items
                            </span>
                            <span id="filter-status" class="text-sm text-gray-500"></span>
                        </div>
                    </div>

                    <div id="delivery-details-container" class="max-h-[800px] overflow-y-auto">
                        <!-- This will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Deliveries Table Section -->
<div class="mt-4">
    <div class="bg-white shadow-sm border border-gray-200 p-6">
        <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
            <i class="fas fa-check-circle text-green-600 mr-3"></i>
            Completed Deliveries
        </h3>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-green-50 border-b-2 border-green-200">
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Order ID</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Customer Name</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Delivery Date</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Completed Date</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Courier</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Tracking No.</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Total Amount</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Status</th>
                    </tr>
                </thead>
                <tbody id="completed-table-body">
                    <!-- This will be populated by JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- No completed deliveries message -->
        <div id="no-completed-message" class="hidden">
            <div class="p-8 text-center">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-inbox text-6xl"></i>
                </div>
                <h4 class="text-lg font-medium text-gray-600">No Completed Deliveries Yet</h4>
                <p class="text-gray-500 mt-2">All completed deliveries will appear here</p>
            </div>
        </div>
    </div>
</div>

    <script>
        // Calendar and scheduling data
        const deliveryCounts = <?php echo json_encode($deliveryCountsByDate); ?>;
        const schedules = <?php echo json_encode($schedules); ?>;

        let currentDate = new Date();
        let selectedDate = null;
        let currentFilter = 'all';

        // **NEW CODE 1: Add helper functions for current month filtering**
        function getCurrentMonthRange() {
            const now = new Date();
            const year = now.getFullYear();
            const month = now.getMonth();

            const startOfMonth = new Date(year, month, 1);
            const endOfMonth = new Date(year, month + 1, 0);

            const startDateString = year + '-' +
                String(month + 1).padStart(2, '0') + '-01';
            const endDateString = year + '-' +
                String(month + 1).padStart(2, '0') + '-' +
                String(endOfMonth.getDate()).padStart(2, '0');

            return {
                startDateString,
                endDateString
            };
        }

        function filterSchedulesByCurrentMonth(schedules) {
            const {
                startDateString,
                endDateString
            } = getCurrentMonthRange();
            return schedules.filter(schedule =>
                schedule.delivery_date >= startDateString &&
                schedule.delivery_date <= endDateString
            );
        }
        // **END OF NEW CODE 1**

        function generateCalendar(year, month) {
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();

            const calendarGrid = document.getElementById('calendar-grid');
            calendarGrid.innerHTML = '';

            // Add empty cells for days before the first day of the month
            for (let i = 0; i < startingDayOfWeek; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'p-3';
                calendarGrid.appendChild(emptyDay);
            }

            // Add days of the month
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dateString = year + '-' +
                    String(month + 1).padStart(2, '0') + '-' +
                    String(day).padStart(2, '0');

                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day relative p-3 text-center rounded-lg border border-gray-200 bg-white flex flex-col justify-center items-center';

                const dayNumber = document.createElement('div');
                dayNumber.textContent = day;
                dayNumber.className = 'font-semibold';
                dayElement.appendChild(dayNumber);

                dayElement.dataset.date = dateString;

                const currentDateOnly = new Date(year, month, day);
                currentDateOnly.setHours(0, 0, 0, 0);

                // Check if this date has deliveries
                const deliveryData = deliveryCounts[dateString];

                if (currentDateOnly < today) {
                    dayElement.className += ' past-date';
                }

                if (deliveryData) {
                    const totalCount = deliveryData.count;
                    const overdueCount = deliveryData.overdue_count;
                    const completedCount = deliveryData.completed_count;
                    const pendingCount = deliveryData.pending_count;

                    // Create badges container
                    const badgesContainer = document.createElement('div');
                    badgesContainer.className = 'delivery-badges';

                    // Only show overdue badge if there are actually overdue items (not delivered and past due date)
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

                    // Calendar day styling priority: overdue > busy > has deliveries
                    if (totalCount >= 10) {
                        dayElement.className += ' busy';
                    } else if (overdueCount === 0 && (pendingCount > 0 || completedCount > 0)) {
                        dayElement.className += ' has-deliveries';
                    }

                    dayElement.appendChild(badgesContainer);

                    // Add click event
                    dayElement.addEventListener('click', function() {
                        selectDate(dateString, dayElement);
                    });
                } else {
                    // Add click event for dates without deliveries
                    dayElement.addEventListener('click', function() {
                        selectDate(dateString, dayElement);
                    });
                }

                calendarGrid.appendChild(dayElement);
            }
        }

        function selectDate(dateString, element) {
            // Remove previous selection
            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) {
                previousSelected.classList.remove('selected');
            }

            // Add selection to clicked element
            element.classList.add('selected');
            selectedDate = dateString;

            // Show navigation button when a date is selected
            const navigateBtn = document.getElementById('navigateBtn');
            const dateSchedules = schedules.filter(s => s.delivery_date === dateString);

            if (dateSchedules.length > 0) {
                navigateBtn.classList.remove('hidden');
            } else {
                navigateBtn.classList.add('hidden');
            }

            // Update delivery details for selected date
            updateDeliveryDetails(dateString);
        }

        function navigateToDetailedView() {
            if (selectedDate) {
                const targetPage = 'logistic-delivery-date-orders-page-2.php';
                const url = `${targetPage}?date=${selectedDate}`;
                window.location.href = url;
            }
        }

        function filterDeliveries(filterType) {
            currentFilter = filterType;

            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-blue-500', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700');
            });

            const activeBtn = event.target.closest('button');
            activeBtn.classList.remove('bg-gray-100', 'text-gray-700');
            activeBtn.classList.add('active', 'bg-blue-500', 'text-white');

            // Clear calendar selection and update details
            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) {
                previousSelected.classList.remove('selected');
            }
            selectedDate = null;

            // Hide navigation button when filtering
            document.getElementById('navigateBtn').classList.add('hidden');

            updateDeliveryDetails();
        }

        function updateDeliveryDetails(selectedDate = null) {
            const detailsContainer = document.getElementById('delivery-details-container');
            const itemsCountSpan = document.getElementById('items-count');
            const detailsTitle = document.getElementById('details-title');
            const filterStatus = document.getElementById('filter-status');

            let filteredSchedules = schedules;

            // Apply date filter first
            if (selectedDate) {
                filteredSchedules = schedules.filter(s => s.delivery_date === selectedDate);
                detailsTitle.textContent = `Deliveries for ${formatDisplayDate(selectedDate)}`;
                filterStatus.textContent = '';
            } else {
                // Apply status filter
                if (currentFilter === 'overdue') {
                    filteredSchedules = schedules.filter(s =>
                        s.delivery_status === 'overdue'
                    );
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
                    // Filter by current month when showing "all" deliveries
                    filteredSchedules = filterSchedulesByCurrentMonth(schedules);
                    const currentMonthName = new Date().toLocaleString('en-US', {
                        month: 'long',
                        year: 'numeric'
                    });
                    detailsTitle.textContent = `All Deliveries - ${currentMonthName}`;
                    filterStatus.textContent = 'Showing current month deliveries only';
                }
            }

            itemsCountSpan.textContent = `${filteredSchedules.length} items`;

            if (filteredSchedules.length === 0) {
                let emptyMessage = 'No deliveries found';
                if (selectedDate) {
                    emptyMessage = `No deliveries scheduled for ${formatDisplayDate(selectedDate)}`;
                } else if (currentFilter === 'overdue') {
                    emptyMessage = 'No overdue deliveries';
                } else if (currentFilter === 'today') {
                    emptyMessage = 'No deliveries scheduled for today';
                } else if (currentFilter === 'upcoming') {
                    emptyMessage = 'No upcoming deliveries';
                } else if (currentFilter === 'all') {} else if (currentFilter === 'replacement') {
                    emptyMessage = 'No replacement deliveries';
                } else if (currentFilter === 'all') {
                    // **NEW CODE 2: Add current month empty message**
                    const currentMonthName = new Date().toLocaleString('en-US', {
                        month: 'long',
                        year: 'numeric'
                    });
                    emptyMessage = `No deliveries scheduled for ${currentMonthName}`;
                    // **END OF NEW CODE 2**
                }

                detailsContainer.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-day text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">${emptyMessage}</h4>
                        ${selectedDate || currentFilter !== 'all' ? `
                            <button type="button" onclick="showAllDeliveries()" 
                                    class="mt-4 text-blue-600 hover:text-blue-800 font-medium">
                                <i class="fas fa-list mr-1"></i>
                                View All Deliveries
                            </button>
                        ` : ''}
                    </div>
                `;
                return;
            }

            // Group schedules by date, then by time
            const schedulesByDate = {};
            filteredSchedules.forEach(schedule => {
                const date = schedule.delivery_date;
                if (!schedulesByDate[date]) {
                    schedulesByDate[date] = {};
                }
                const time = schedule.delivery_time;
                if (!schedulesByDate[date][time]) {
                    schedulesByDate[date][time] = [];
                }
                schedulesByDate[date][time].push(schedule);
            });

            let html = '';

            // Sort dates (most recent first for overdue, chronological for others)
            const sortedDates = Object.keys(schedulesByDate).sort((a, b) => {
                if (currentFilter === 'overdue') {
                    return new Date(b) - new Date(a); // Most recent overdue first
                }
                return new Date(a) - new Date(b); // Chronological order
            });

            sortedDates.forEach(date => {
                const dateSchedules = schedulesByDate[date];
                const dateObj = new Date(date + 'T00:00:00');
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                let dateClass = '';
                let dateIcon = 'fa-calendar';

                // Check if there are any actual overdue items for this date
                const hasOverdueItems = Object.values(dateSchedules).some(timeSchedules =>
                    timeSchedules.some(schedule =>
                        schedule.delivery_status === 'overdue'
                    )
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
                                ${hasOverdueItems ? `
                                    <span class="ml-2 bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold">
                                        OVERDUE
                                    </span>
                                ` : ''}
                            </h4>
                        </div>
                `;

                // Sort time slots
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
            <div class="flex items-start justify-between mb-3 ">
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
                    <span class="font-semibold">₱${parseFloat(schedule.final_total).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
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
                </div>
                ` : ''}
                ${schedule.courier_name ? `
<div class="flex items-center">
    <i class="fas fa-shipping-fast mr-3 text-gray-500 w-4"></i>
    <span class="text-gray-700 text-sm">Courier: ${escapeHtml(schedule.courier_name)}</span>
</div>
` : ''}
${schedule.tracking_number ? `
<div class="flex items-center">
    <i class="fas fa-barcode mr-3 text-gray-500 w-4"></i>
    <span class="text-gray-700 text-sm">Tracking: ${escapeHtml(schedule.tracking_number)}</span>
</div>
` : ''}
${schedule.booking_reference ? `
<div class="flex items-center">
    <i class="fas fa-receipt mr-3 text-gray-500 w-4"></i>
    <span class="text-gray-700 text-sm ">Booking Ref: ${escapeHtml(schedule.booking_reference)}</span>
</div>
` : ''}
${schedule.booking_type && schedule.booking_type !== 'delivery' ? `
<div class="flex items-center">
    <i class="fas fa-hand-holding-box mr-3 text-gray-500 w-4"></i>
    <span class="text-gray-700 text-sm capitalize">Type: ${schedule.booking_type}</span>
</div>
` : ''}
                ${schedule.delivery_notes ? `
                <div class="mt-2 bg-yellow-100 p-2 rounded border border-yellow-200">
                    <div class="flex items-start">
                        <i class="fas fa-sticky-note mr-2 text-yellow-600 mt-1"></i>
                        <span class="text-gray-700 text-sm flex-1">${escapeHtml(schedule.delivery_notes)}</span>
                    </div>
                </div>
                ` : ''}
                ${isCompleted && schedule.actual_delivery_time ? `
<div class="mt-2 bg-green-100 p-2 rounded border border-green-200">
    <div class="flex items-center">
        <i class="fas fa-check-double mr-2 text-green-600"></i>
        <span class="text-green-700 text-sm">
            Delivered: ${formatDateTime(schedule.actual_delivery_time)}
        </span>
    </div>
</div>
` : ''}
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
                </div>
                ` : ''}
            </div>
        </div>
    `;
                    });

                    html += '</div>';
                });

                html += '</div>';
            });

            detailsContainer.innerHTML = html;
        }

        function showAllDeliveries() {
            currentFilter = 'all';

            // Reset filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-blue-500', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700');
            });
            document.querySelector('button[onclick="filterDeliveries(\'all\')"]').classList.remove('bg-gray-100', 'text-gray-700');
            document.querySelector('button[onclick="filterDeliveries(\'all\')"]').classList.add('active', 'bg-blue-500', 'text-white');

            // Remove calendar selection
            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) {
                previousSelected.classList.remove('selected');
            }
            selectedDate = null;

            // Hide navigation button
            document.getElementById('navigateBtn').classList.add('hidden');

            updateDeliveryDetails();
        }

        // Helper functions
        function formatDisplayDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const dateOnly = new Date(date);
            dateOnly.setHours(0, 0, 0, 0);

            if (dateOnly.getTime() === today.getTime()) {
                return 'Today - ' + date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric'
                });
            } else if (dateOnly.getTime() === today.getTime() - 86400000) {
                return 'Yesterday - ' + date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric'
                });
            } else if (dateOnly.getTime() === today.getTime() + 86400000) {
                return 'Tomorrow - ' + date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric'
                });
            } else {
                return date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
        }

        function formatTime(timeString) {
            const time = new Date('2000-01-01 ' + timeString);
            return time.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function formatDateTime(datetimeString) {
            const date = new Date(datetimeString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function getTodayString() {
            const today = new Date();
            return today.getFullYear() + '-' +
                String(today.getMonth() + 1).padStart(2, '0') + '-' +
                String(today.getDate()).padStart(2, '0');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function updateCalendarHeader() {
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            document.getElementById('currentMonth').textContent =
                monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
        }

        function initializeCalendar() {
            updateCalendarHeader();
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());

            // Navigation event listeners
            document.getElementById('prevMonth').addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() - 1);
                updateCalendarHeader();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            });

            document.getElementById('nextMonth').addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                updateCalendarHeader();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            });
        }

        // ADD THIS FUNCTION after the initializeCalendar() function

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

    // Sort by actual delivery time (most recent first)
    completedSchedules.sort((a, b) => {
        if (!a.actual_delivery_time || !b.actual_delivery_time) return 0;
        return new Date(b.actual_delivery_time) - new Date(a.actual_delivery_time);
    });

    let html = '';

    completedSchedules.forEach(schedule => {
        const deliveryDate = new Date(schedule.delivery_date + 'T00:00:00');
        const completedDate = schedule.actual_delivery_time ? new Date(schedule.actual_delivery_time) : null;

        html += `
            <tr class="border-b border-gray-200 hover:bg-green-50 transition-colors">
                <td class="px-6 py-4 text-sm font-medium text-blue-600">
                    <a href="#" class="hover:underline">#${schedule.order_id}</a>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">${escapeHtml(schedule.customer_name)}</td>
                <td class="px-6 py-4 text-sm text-gray-700">
                    ${deliveryDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                </td>
                <td class="px-6 py-4 text-sm text-gray-700">
                    ${completedDate ? completedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}
                </td>
                <td class="px-6 py-4 text-sm text-gray-700">
                    ${schedule.courier_name ? escapeHtml(schedule.courier_name) : '<span class="text-gray-400">-</span>'}
                </td>
                <td class="px-6 py-4 text-sm text-gray-700">
                    ${schedule.tracking_number ? escapeHtml(schedule.tracking_number) : '<span class="text-gray-400">-</span>'}
                </td>
                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                    ₱${parseFloat(schedule.final_total).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </td>
                <td class="px-6 py-4 text-sm">
                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold flex items-center w-fit">
                        <i class="fas fa-check-circle mr-2"></i>
                        ${schedule.booking_status === 'delivered' ? 'Delivered' : 'Picked Up'}
                    </span>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}


        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            updateDeliveryDetails(); // Show all deliveries by default
             populateCompletedDeliveriesTable(); // ADD THIS LINE
        });

        
    </script>
</body>

</html>