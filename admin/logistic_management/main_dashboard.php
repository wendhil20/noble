<?php
// logistics_dashboard.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get delivery schedules with related order and item information for the next 60 days
$scheduleSql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.item_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.assigned_truck,
    ds.delivery_type,
    ds.delivery_proof,
    ds.delivered_at,
    ds.created_by,
    ds.created_at,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.status as order_status,
    o.final_total,
    oi.product_name,
    oi.codename,
    oi.type_name,
    oi.variant_color,
    oi.price,
    oi.quantity,
    oi.subtotal,
    oi.size,
    oi.tracking_status
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
INNER JOIN order_items oi ON ds.item_id = oi.id
WHERE ds.delivery_date >= CURDATE() 
    AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
ORDER BY ds.delivery_date ASC, ds.delivery_time ASC";

$scheduleStmt = $conn->prepare($scheduleSql);
$scheduleStmt->execute();
$schedules = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduleStmt->close();

// Get delivery counts by date for calendar display
$countSql = "SELECT 
    DATE(ds.delivery_date) as date, 
    COUNT(*) as count,
    COUNT(CASE WHEN ds.delivered_at IS NULL THEN 1 END) as pending_count,
    COUNT(CASE WHEN ds.delivered_at IS NOT NULL THEN 1 END) as completed_count
FROM delivery_schedules ds
WHERE ds.delivery_date >= CURDATE() 
    AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
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

// Get summary statistics
$statsSql = "SELECT 
    COUNT(*) as total_scheduled,
    COUNT(CASE WHEN ds.delivered_at IS NULL THEN 1 END) as pending_deliveries,
    COUNT(CASE WHEN ds.delivered_at IS NOT NULL THEN 1 END) as completed_deliveries,
    COUNT(CASE WHEN ds.delivery_date = CURDATE() THEN 1 END) as today_deliveries
FROM delivery_schedules ds
WHERE ds.delivery_date >= CURDATE()";

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
    <title>Logistics Dashboard - Noble Home</title>
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
        .calendar-day {
            transition: all 0.2s ease;
            cursor: pointer;
            min-height: 50px;
        }
        .calendar-day:hover {
            background-color: #e0f2fe;
            transform: scale(1.05);
        }
        .calendar-day.selected {
            background-color: #1976d2 !important;
            color: white;
        }
        .calendar-day.has-deliveries {
            background-color: #fff3e0;
            border: 2px solid #ff9800;
        }
        .calendar-day.busy {
            background-color: #ffebee;
            border: 2px solid #f44336;
        }
        .delivery-count {
            font-size: 0.7rem;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 2px;
            right: 2px;
            font-weight: bold;
        }
        .item-card {
            transition: all 0.3s ease;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-6 space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-truck text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Logistics Dashboard</h1>
                        <p class="text-gray-600 mt-1">Manage and track delivery schedules</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-calendar text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Scheduled</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_scheduled']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-lg">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_deliveries']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Completed</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['completed_deliveries']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-truck-fast text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Today's Deliveries</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['today_deliveries']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            
            <!-- Calendar Section -->
            <div class="xl:col-span-2">
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
                    <div class="mt-6 flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-orange-100 border-2 border-orange-500 rounded mr-2"></div>
                            <span>Has deliveries</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-red-100 border-2 border-red-500 rounded mr-2"></div>
                            <span>Busy (5+ deliveries)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-blue-600 rounded mr-2"></div>
                            <span>Selected date</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Details Section -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-list text-purple-600 mr-3"></i>
                            <span id="details-title">Scheduled Deliveries</span>
                            <span id="items-count" class="ml-3 bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                                <?php echo count($schedules); ?> items
                            </span>
                        </h3>
                    </div>
                    
                    <div id="delivery-details-container" class="max-h-[800px] overflow-y-auto">
                        <!-- This will be populated by JavaScript -->
                    </div>
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
                dayElement.textContent = day;
                dayElement.dataset.date = dateString;
                
                const currentDateOnly = new Date(year, month, day);
                currentDateOnly.setHours(0, 0, 0, 0);
                
                if (currentDateOnly < today) {
                    dayElement.className += ' text-gray-400 cursor-not-allowed bg-gray-100';
                } else {
                    // Check if this date has deliveries
                    const deliveryData = deliveryCounts[dateString];
                    
                    if (deliveryData) {
                        const count = deliveryData.count;
                        
                        if (count >= 5) {
                            dayElement.className += ' busy';
                        } else {
                            dayElement.className += ' has-deliveries';
                        }
                        
                        // Add delivery count badge
                        const countBadge = document.createElement('div');
                        countBadge.className = 'delivery-count';
                        countBadge.textContent = count;
                        
                        if (count >= 5) {
                            countBadge.style.backgroundColor = '#ffcdd2';
                            countBadge.style.color = '#c62828';
                        } else {
                            countBadge.style.backgroundColor = '#fff3e0';
                            countBadge.style.color = '#ef6c00';
                        }
                        
                        dayElement.appendChild(countBadge);
                    }
                    
                    // Add click event
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
            
            // Update delivery details for selected date
            updateDeliveryDetails(dateString);
        }
        
        function updateDeliveryDetails(selectedDate = null) {
            const detailsContainer = document.getElementById('delivery-details-container');
            const itemsCountSpan = document.getElementById('items-count');
            const detailsTitle = document.getElementById('details-title');
            
            let filteredSchedules = schedules;
            
            if (selectedDate) {
                filteredSchedules = schedules.filter(s => s.delivery_date === selectedDate);
                detailsTitle.textContent = `Deliveries for ${formatDisplayDate(selectedDate)}`;
                itemsCountSpan.textContent = `${filteredSchedules.length} items`;
            } else {
                detailsTitle.textContent = 'All Scheduled Deliveries';
                itemsCountSpan.textContent = `${schedules.length} items`;
            }
            
            if (filteredSchedules.length === 0) {
                detailsContainer.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-day text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No Deliveries</h4>
                        <p class="text-gray-500">${selectedDate ? `No deliveries scheduled for ${formatDisplayDate(selectedDate)}` : 'No deliveries scheduled'}</p>
                        ${selectedDate ? `
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
            
            // Group schedules by time
            const schedulesByTime = {};
            filteredSchedules.forEach(schedule => {
                const time = schedule.delivery_time;
                if (!schedulesByTime[time]) {
                    schedulesByTime[time] = [];
                }
                schedulesByTime[time].push(schedule);
            });
            
            let html = '';
            
            // Sort time slots
            const sortedTimes = Object.keys(schedulesByTime).sort();
            
            sortedTimes.forEach(time => {
                const timeSchedules = schedulesByTime[time];
                
                html += `
                    <div class="border-b border-gray-100">
                        <div class="bg-gray-50 px-6 py-3">
                            <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide">
                                <i class="fas fa-clock mr-2 text-gray-500"></i>
                                ${formatTime(time)} (${timeSchedules.length} items)
                            </h4>
                        </div>
                        <div class="p-4 space-y-4">
                `;
                
                timeSchedules.forEach(schedule => {
                    const isCompleted = schedule.delivered_at !== null;
                    const statusClass = isCompleted ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                    const statusIcon = isCompleted ? 'fa-check-circle' : 'fa-clock';
                    const statusText = isCompleted ? 'Delivered' : 'Pending';
                    
                    html += `
                        <div class="item-card bg-white border border-gray-200 rounded-lg p-4 hover:border-blue-300">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h5 class="font-semibold text-gray-900 text-lg mb-1">
                                        ${escapeHtml(schedule.product_name)}
                                    </h5>
                                    <div class="flex items-center space-x-3 mb-2">
                                        <span class="${statusClass} px-2 py-1 rounded-full text-xs font-medium">
                                            <i class="fas ${statusIcon} mr-1"></i>
                                            ${statusText}
                                        </span>
                                        <span class="text-gray-600 text-sm">
                                            Order #${schedule.order_id}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3 text-sm text-gray-600 mb-3">
                                <div>
                                    <span class="font-medium">Quantity:</span> ${schedule.quantity}
                                </div>
                                <div>
                                    <span class="font-medium">Price:</span> ₱${parseFloat(schedule.price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                </div>
                                ${schedule.variant_color ? `
                                <div>
                                    <span class="font-medium">Color:</span> ${escapeHtml(schedule.variant_color)}
                                </div>
                                ` : ''}
                                ${schedule.size ? `
                                <div>
                                    <span class="font-medium">Size:</span> ${escapeHtml(schedule.size)}
                                </div>
                                ` : ''}
                            </div>
                            
                            <div class="border-t border-gray-200 pt-3">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-user mr-2 text-gray-500"></i>
                                    <span class="font-medium text-gray-700">${escapeHtml(schedule.customer_name)}</span>
                                </div>
                                <div class="flex items-start mb-2">
                                    <i class="fas fa-map-marker-alt mr-2 text-gray-500 mt-1"></i>
                                    <span class="text-gray-600 text-sm">${escapeHtml(schedule.address)}</span>
                                </div>
                                ${schedule.mobile ? `
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-phone mr-2 text-gray-500"></i>
                                    <span class="text-gray-600 text-sm">${escapeHtml(schedule.mobile)}</span>
                                </div>
                                ` : ''}
                                ${schedule.assigned_truck ? `
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-truck mr-2 text-gray-500"></i>
                                    <span class="text-gray-600 text-sm">Truck: ${escapeHtml(schedule.assigned_truck)}</span>
                                </div>
                                ` : ''}
                                ${schedule.delivery_notes ? `
                                <div class="mt-2 bg-yellow-50 p-2 rounded">
                                    <i class="fas fa-sticky-note mr-2 text-yellow-600"></i>
                                    <span class="text-gray-700 text-sm">${escapeHtml(schedule.delivery_notes)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                
                html += '</div></div>';
            });
            
            detailsContainer.innerHTML = html;
        }
        
        function showAllDeliveries() {
            // Remove calendar selection
            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) {
                previousSelected.classList.remove('selected');
            }
            selectedDate = null;
            
            updateDeliveryDetails();
        }
        
        // Helper functions
        function formatDisplayDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        function formatTime(timeString) {
            const time = new Date('2000-01-01 ' + timeString);
            return time.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
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
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            updateDeliveryDetails(); // Show all deliveries by default
        });
    </script>
</body>
</html>