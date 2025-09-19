<?php
// delivery_schedule.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role([ 'superadmin', 'sales', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$origin = isset($_GET['origin']) ? $_GET['origin'] : '';
$replacement_id = isset($_GET['replacement_id']) ? (int)$_GET['replacement_id'] : 0;

// Validate input parameters
if ($order_id <= 0 || $item_id <= 0 || !in_array($origin, ['local', 'international', 'replacement'])) {
    header("Location: order_list.php");
    exit();
}

// If it's a replacement request, validate replacement_id
if ($origin === 'replacement' && $replacement_id <= 0) {
    header("Location: order_tracking.php?order_id=$order_id");
    exit();
}

// Get order and item details
if ($origin === 'replacement') {
    // Get replacement request details with original item info
    $itemSql = "SELECT rr.*, 
                       oi.product_name, oi.price, oi.origin,
                       o.customer_name, o.address, o.mobile 
                FROM replacement_requests rr
                JOIN order_items oi ON rr.order_item_id = oi.id
                JOIN orders o ON rr.order_id = o.id 
                WHERE rr.id = ? AND rr.order_id = ? AND rr.order_item_id = ? LIMIT 1";
    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->bind_param("iii", $replacement_id, $order_id, $item_id);
} else {
    // Get original order item details
    $itemSql = "SELECT oi.*, o.customer_name, o.address, o.mobile 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                WHERE oi.id = ? AND oi.order_id = ? LIMIT 1";
    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->bind_param("ii", $item_id, $order_id);
}

$itemStmt->execute();
$item = $itemStmt->get_result()->fetch_assoc();
$itemStmt->close();

if (!$item) {
    header("Location: order_tracking.php?order_id=$order_id");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_delivery'])) {
    $delivery_date = $_POST['delivery_date'];
    $delivery_time = $_POST['delivery_time'];
    $delivery_notes = $_POST['delivery_notes'] ?? '';
    
    try {
        $conn->begin_transaction();
        
        if ($origin === 'replacement') {
            // Insert replacement delivery schedule
            $scheduleSql = "INSERT INTO delivery_schedules (order_id, item_id, replacement_id, delivery_date, delivery_time, delivery_notes, item_type, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, 'replacement', ?, NOW())";
            $scheduleStmt = $conn->prepare($scheduleSql);
            $scheduleStmt->bind_param("iiissss", $order_id, $item_id, $replacement_id, $delivery_date, $delivery_time, $delivery_notes, $_SESSION['noble_user']);
            $scheduleStmt->execute();
            $scheduleStmt->close();
            
            // Update replacement request status
            $new_status = 'ready_for_pickup';
            $updateSql = "UPDATE replacement_requests SET status = ? WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("sii", $new_status, $replacement_id, $order_id);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert original item delivery schedule
            $scheduleSql = "INSERT INTO delivery_schedules (order_id, item_id, delivery_date, delivery_time, delivery_notes, item_type, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'original', ?, NOW())";
            $scheduleStmt = $conn->prepare($scheduleSql);
            $scheduleStmt->bind_param("iissss", $order_id, $item_id, $delivery_date, $delivery_time, $delivery_notes, $_SESSION['noble_user']);
            $scheduleStmt->execute();
            $scheduleStmt->close();
            
            // Update original item status based on origin
            $new_status = ($origin === 'local') ? 'ready_for_pickup' : 'in_local_warehouse';
            $updateSql = "UPDATE order_items SET tracking_status = ? WHERE id = ? AND order_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("sii", $new_status, $item_id, $order_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        $conn->commit();
        
        $success_message = ($origin === 'replacement') ? 
            "Replacement item scheduled for delivery successfully!" : 
            "Item scheduled for delivery successfully!";
        
        // Redirect after 2 seconds
        echo "<script>
            setTimeout(function() {
                window.location.href = 'order_tracking.php?order_id=$order_id';
            }, 2000);
        </script>";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Failed to schedule delivery: " . $e->getMessage();
    }
}

// Get scheduled deliveries for the next 60 days for calendar (including replacements)
$calendarSql = "SELECT DATE(ds.delivery_date) as date, COUNT(*) as count
                FROM delivery_schedules ds
                WHERE ds.delivery_date >= CURDATE() AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                GROUP BY DATE(ds.delivery_date)";
$calendarStmt = $conn->prepare($calendarSql);
$calendarStmt->execute();
$calendarData = $calendarStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$calendarStmt->close();

// Convert to associative array for easier lookup
$deliveryCountsByDate = [];
foreach ($calendarData as $data) {
    $deliveryCountsByDate[$data['date']] = $data['count'];
}

// Get scheduled deliveries for the next 30 days (including replacements)
$scheduleSql = "SELECT ds.*, 
                       CASE 
                           WHEN ds.item_type = 'replacement' THEN CONCAT(oi.product_name, ' (REPLACEMENT)')
                           ELSE oi.product_name
                       END as product_name,
                       CASE 
                           WHEN ds.item_type = 'replacement' THEN rr.replacement_quantity
                           ELSE oi.quantity
                       END as quantity,
                       oi.price, o.customer_name, o.address,
                       CASE 
                           WHEN ds.item_type = 'replacement' THEN rr.reason
                           ELSE NULL
                       END as replacement_reason
                FROM delivery_schedules ds
                JOIN order_items oi ON ds.item_id = oi.id
                JOIN orders o ON ds.order_id = o.id
                LEFT JOIN replacement_requests rr ON ds.replacement_id = rr.id AND ds.item_type = 'replacement'
                WHERE ds.delivery_date >= CURDATE() AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY ds.delivery_date, ds.delivery_time";
$scheduleStmt = $conn->prepare($scheduleSql);
$scheduleStmt->execute();
$schedules = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduleStmt->close();

// Group schedules by date
$schedulesByDate = [];
foreach ($schedules as $schedule) {
    $date = $schedule['delivery_date'];
    if (!isset($schedulesByDate[$date])) {
        $schedulesByDate[$date] = [];
    }
    $schedulesByDate[$date][] = $schedule;
}

// Determine display values based on item type
$isReplacement = ($origin === 'replacement');
$displayProductName = $isReplacement ? 
    htmlspecialchars($item['product_name']) . ' (REPLACEMENT)' : 
    htmlspecialchars($item['product_name']);
$displayQuantity = $isReplacement ? $item['replacement_quantity'] : $item['quantity'];
$displayReason = $isReplacement ? 'Reason: ' . htmlspecialchars(ucfirst($item['reason'])) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Delivery - Order #<?php echo $order_id; ?></title>
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
        .item-card {
            transition: all 0.3s ease;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .date-section {
            border-left: 4px solid #3b82f6;
        }
        .calendar-day {
            transition: all 0.2s ease;
            cursor: pointer;
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
        .replacement-item {
            border-left: 4px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
        }
        .replacement-badge {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
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
                    <a href="order_tracking.php?order_id=<?php echo $order_id; ?>" class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-arrow-left text-gray-600"></i>
                    </a>
                    <div class="bg-gradient-to-r <?php echo $isReplacement ? 'from-red-500 to-red-600' : 'from-blue-500 to-blue-600'; ?> p-3 rounded-xl shadow-lg">
                        <i class="fas <?php echo $isReplacement ? 'fa-exchange-alt' : 'fa-calendar-plus'; ?> text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            Schedule <?php echo $isReplacement ? 'Replacement' : 'Item'; ?> for Delivery
                        </h1>
                        <p class="text-gray-600 mt-1">
                            <?php echo $displayProductName; ?>
                            <?php if ($isReplacement): ?>
                                <span class="replacement-badge px-2 py-1 rounded-full text-xs font-medium ml-2">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>REPLACEMENT
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-3"></i>
                    <span class="text-green-800 font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-400 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                    <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            
            <!-- Left Column: Item Details & Schedule Form -->
            <div class="xl:col-span-1">
                <!-- Item Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 <?php echo $isReplacement ? 'replacement-item' : ''; ?>">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas <?php echo $isReplacement ? 'fa-exchange-alt text-red-600' : 'fa-box text-blue-600'; ?> mr-3"></i>
                        <?php echo $isReplacement ? 'Replacement' : 'Item'; ?> Details
                    </h3>
                    
                    <?php if ($isReplacement): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="font-medium">Replacement Request</span>
                            </div>
                            <p class="text-red-700 text-sm mt-1"><?php echo $displayReason; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Product:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($item['product_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Quantity:</span>
                            <span class="font-medium"><?php echo $displayQuantity; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Price:</span>
                            <span class="font-medium">₱<?php echo number_format((float)$item['price'], 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Customer:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($item['customer_name']); ?></span>
                        </div>
                        <?php if ($isReplacement): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Request Date:</span>
                                <span class="font-medium"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="pt-2 border-t">
                            <span class="text-gray-600">Address:</span>
                            <p class="font-medium mt-1"><?php echo htmlspecialchars($item['address']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Schedule Form -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-calendar-plus <?php echo $isReplacement ? 'text-red-600' : 'text-green-600'; ?> mr-3"></i>
                        Schedule <?php echo $isReplacement ? 'Replacement' : ''; ?> Delivery
                    </h3>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-2 text-gray-500"></i>
                                Delivery Date
                            </label>
                            <input type="date" name="delivery_date" id="delivery_date" required 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-<?php echo $isReplacement ? 'red' : 'blue'; ?>-500 focus:border-<?php echo $isReplacement ? 'red' : 'blue'; ?>-500 text-lg">
                            <p class="text-sm text-gray-500 mt-1">Click on calendar days to select dates</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-2 text-gray-500"></i>
                                Delivery Time
                            </label>
                            <select name="delivery_time" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-<?php echo $isReplacement ? 'red' : 'blue'; ?>-500 focus:border-<?php echo $isReplacement ? 'red' : 'blue'; ?>-500 text-lg">
                                <option value="">Select time slot</option>
                                <option value="08:00">8:00 AM - 9:00 AM</option>
                                <option value="09:00">9:00 AM - 10:00 AM</option>
                                <option value="10:00">10:00 AM - 11:00 AM</option>
                                <option value="11:00">11:00 AM - 12:00 PM</option>
                                <option value="13:00">1:00 PM - 2:00 PM</option>
                                <option value="14:00">2:00 PM - 3:00 PM</option>
                                <option value="15:00">3:00 PM - 4:00 PM</option>
                                <option value="16:00">4:00 PM - 5:00 PM</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-sticky-note mr-2 text-gray-500"></i>
                                Delivery Notes (Optional)
                            </label>
                            <textarea name="delivery_notes" rows="3" 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-<?php echo $isReplacement ? 'red' : 'blue'; ?>-500 focus:border-<?php echo $isReplacement ? 'red' : 'blue'; ?>-500" 
                                      placeholder="Special instructions, contact details, or delivery preferences...<?php echo $isReplacement ? ' (Replacement delivery)' : ''; ?>"></textarea>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" name="schedule_delivery" 
                                    class="w-full bg-gradient-to-r <?php echo $isReplacement ? 'from-red-600 to-red-700 hover:from-red-700 hover:to-red-800' : 'from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800'; ?> text-white font-medium py-4 px-6 rounded-lg transition-all duration-200 transform hover:scale-[1.02] text-lg">
                                <i class="fas fa-calendar-plus mr-3"></i>
                                Schedule This <?php echo $isReplacement ? 'Replacement' : 'Item'; ?> for Delivery
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Middle Column: Calendar -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-calendar text-purple-600 mr-3"></i>
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
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-orange-100 border-2 border-orange-500 rounded mr-2"></div>
                            <span>Has scheduled deliveries</span>
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

            <!-- Right Column: Scheduled Items -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-list text-purple-600 mr-3"></i>
                            Scheduled Items
                            <span id="items-count" class="ml-3 bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                                <?php echo count($schedules); ?> items
                            </span>
                        </h3>
                    </div>
                    
                    <div id="scheduled-items-container" class="max-h-[700px] overflow-y-auto">
                        <!-- Items will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Calendar and scheduling data
        const deliveryCounts = <?php echo json_encode($deliveryCountsByDate); ?>;
        const schedules = <?php echo json_encode($schedules); ?>;
        const isReplacement = <?php echo $isReplacement ? 'true' : 'false'; ?>;
        
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
                
                dayElement.className = 'calendar-day relative p-3 text-center rounded-lg border border-gray-200 bg-white';
                dayElement.textContent = day;
                dayElement.dataset.date = dateString;
                
                const currentDateOnly = new Date(year, month, day);
                currentDateOnly.setHours(0, 0, 0, 0);
                
                if (currentDateOnly < today) {
                    dayElement.className += ' text-gray-400 cursor-not-allowed bg-gray-100';
                } else {
                    const deliveryCount = deliveryCounts[dateString] || 0;
                    
                    if (deliveryCount > 0) {
                        if (deliveryCount >= 5) {
                            dayElement.className += ' busy';
                        } else {
                            dayElement.className += ' has-deliveries';
                        }
                        
                        const countBadge = document.createElement('div');
                        countBadge.className = 'delivery-count';
                        countBadge.textContent = deliveryCount;
                        
                        if (deliveryCount >= 5) {
                            countBadge.style.backgroundColor = '#ffcdd2';
                            countBadge.style.color = '#c62828';
                        } else {
                            countBadge.style.backgroundColor = '#fff3e0';
                            countBadge.style.color = '#ef6c00';
                        }
                        
                        dayElement.appendChild(countBadge);
                    }
                    
                    dayElement.addEventListener('click', function() {
                        if (currentDateOnly >= today) {
                            selectDate(dateString, dayElement);
                        }
                    });
                }
                
                calendarGrid.appendChild(dayElement);
            }
        }
        
        function selectDate(dateString, element) {
            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) {
                previousSelected.classList.remove('selected');
            }
            
            element.classList.add('selected');
            selectedDate = dateString;
            
            document.getElementById('delivery_date').value = dateString;
            updateScheduledItemsDisplay(dateString);
            checkTimeSlotAvailability();
        }
        
        function updateScheduledItemsDisplay(selectedDate = null) {
            const scheduledItemsContainer = document.getElementById('scheduled-items-container');
            const itemsCountSpan = document.getElementById('items-count');
            
            if (!selectedDate) {
                showAllScheduledItems();
                return;
            }
            
            const selectedDateSchedules = schedules.filter(s => s.delivery_date === selectedDate);
            
            itemsCountSpan.textContent = selectedDate ? 
                `${selectedDateSchedules.length} items on ${formatDisplayDate(selectedDate)}` : 
                `${schedules.length} items`;
            
            if (selectedDateSchedules.length === 0) {
                scheduledItemsContainer.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-day text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No Items Scheduled</h4>
                        <p class="text-gray-500">No deliveries scheduled for ${formatDisplayDate(selectedDate)}</p>
                        <button type="button" onclick="showAllScheduledItems()" 
                                class="mt-4 text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-list mr-1"></i>
                            View All Scheduled Items
                        </button>
                    </div>
                `;
                return;
            }
            
            const schedulesByDate = {};
            selectedDateSchedules.forEach(schedule => {
                const date = schedule.delivery_date;
                if (!schedulesByDate[date]) {
                    schedulesByDate[date] = [];
                }
                schedulesByDate[date].push(schedule);
            });
            
            let html = '';
            for (const [date, daySchedules] of Object.entries(schedulesByDate)) {
                html += `
                    <div class="date-section bg-blue-50 px-6 py-4 border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <h4 class="text-lg font-bold text-blue-900">
                                <i class="fas fa-calendar text-blue-600 mr-2"></i>
                                ${formatDisplayDate(date)}
                            </h4>
                            <div class="flex items-center space-x-2">
                                <span class="bg-blue-200 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                    ${daySchedules.length} items
                                </span>
                                <button type="button" onclick="showAllScheduledItems()" 
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <i class="fas fa-list mr-1"></i>
                                    View All
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                `;
                
                daySchedules.forEach(schedule => {
                    const isReplacementItem = schedule.item_type === 'replacement';
                    html += renderScheduleItem(schedule, isReplacementItem);
                });
                
                html += '</div>';
            }
            
            scheduledItemsContainer.innerHTML = html;
        }
        
        function showAllScheduledItems() {
            const scheduledItemsContainer = document.getElementById('scheduled-items-container');
            const itemsCountSpan = document.getElementById('items-count');
            
            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) {
                previousSelected.classList.remove('selected');
            }
            selectedDate = null;
            
            itemsCountSpan.textContent = `${schedules.length} items`;
            
            if (schedules.length === 0) {
                scheduledItemsContainer.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-times text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No Items Scheduled</h4>
                        <p class="text-gray-500">Schedule your first item for delivery using the form.</p>
                    </div>
                `;
                return;
            }
            
            const schedulesByDate = {};
            schedules.forEach(schedule => {
                const date = schedule.delivery_date;
                if (!schedulesByDate[date]) {
                    schedulesByDate[date] = [];
                }
                schedulesByDate[date].push(schedule);
            });
            
            let html = '';
            for (const [date, daySchedules] of Object.entries(schedulesByDate)) {
                html += `
                    <div class="date-section bg-blue-50 px-6 py-4 border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <h4 class="text-lg font-bold text-blue-900">
                                <i class="fas fa-calendar text-blue-600 mr-2"></i>
                                ${formatDisplayDate(date)}
                            </h4>
                            <span class="bg-blue-200 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                ${daySchedules.length} items
                            </span>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                `;
                
                daySchedules.forEach(schedule => {
                    const isReplacementItem = schedule.item_type === 'replacement';
                    html += renderScheduleItem(schedule, isReplacementItem);
                });
                
                html += '</div>';
            }
            
            scheduledItemsContainer.innerHTML = html;
        }
        
        function renderScheduleItem(schedule, isReplacementItem) {
            const itemClass = isReplacementItem ? 'replacement-item' : 'bg-gray-50';
            const badgeClass = isReplacementItem ? 'bg-red-100 text-red-800' : 'bg-gray-200 text-gray-700';
            const reasonText = isReplacementItem && schedule.replacement_reason ? 
                `<p class="text-red-600 text-sm mt-1 bg-red-50 p-2 rounded">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Replacement reason: ${escapeHtml(schedule.replacement_reason)}
                </p>` : '';
            
            return `
                <div class="item-card ${itemClass} border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center mb-1">
                                <h5 class="font-semibold text-gray-900 text-lg">
                                    ${escapeHtml(schedule.product_name)}
                                </h5>
                                ${isReplacementItem ? '<span class="ml-2 bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium"><i class="fas fa-exchange-alt mr-1"></i>REPLACEMENT</span>' : ''}
                            </div>
                            <p class="text-blue-600 font-medium mb-2">
                                <i class="fas fa-clock mr-1"></i>
                                ${formatTime(schedule.delivery_time)}
                            </p>
                            ${reasonText}
                        </div>
                        <span class="${badgeClass} px-3 py-1 rounded-full text-sm font-medium">
                            Order #${schedule.order_id}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                        <div>
                            <span class="font-medium">Quantity:</span> ${schedule.quantity}
                        </div>
                        <div>
                            <span class="font-medium">Price:</span> ₱${parseFloat(schedule.price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-3">
                        <p class="text-gray-700 font-medium mb-1">
                            <i class="fas fa-user mr-2 text-gray-500"></i>
                            ${escapeHtml(schedule.customer_name)}
                        </p>
                        <p class="text-gray-600 text-sm">
                            <i class="fas fa-map-marker-alt mr-2 text-gray-500"></i>
                            ${escapeHtml(schedule.address)}
                        </p>
                        ${schedule.delivery_notes ? `
                            <p class="text-gray-600 text-sm mt-2 bg-yellow-50 p-2 rounded">
                                <i class="fas fa-sticky-note mr-2 text-yellow-600"></i>
                                ${escapeHtml(schedule.delivery_notes)}
                            </p>
                        ` : ''}
                    </div>
                </div>
            `;
        }
        
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
        
        function checkTimeSlotAvailability() {
            const selectedDate = document.getElementById('delivery_date').value;
            const selectedTime = document.querySelector('select[name="delivery_time"]').value;
            
            if (selectedDate && selectedTime) {
                const conflicts = schedules.filter(s => 
                    s.delivery_date === selectedDate && s.delivery_time === selectedTime
                );
                
                const timeSelect = document.querySelector('select[name="delivery_time"]');
                const selectedOption = timeSelect.querySelector(`option[value="${selectedTime}"]`);
                
                if (conflicts.length > 0) {
                    selectedOption.text = selectedOption.text.replace(' (Busy)', '') + ` (${conflicts.length} scheduled)`;
                    selectedOption.style.color = '#dc2626';
                } else {
                    selectedOption.text = selectedOption.text.replace(/ \(\d+ scheduled\)/, '');
                    selectedOption.style.color = '#059669';
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            showAllScheduledItems();
        });

        document.getElementById('delivery_date').addEventListener('change', function() {
            const selectedDateValue = this.value;
            
            const previousSelected = document.querySelector('.calendar-day.selected');
            if (previousSelected) {
                previousSelected.classList.remove('selected');
            }
            
            const calendarDay = document.querySelector(`[data-date="${selectedDateValue}"]`);
            if (calendarDay) {
                calendarDay.classList.add('selected');
                selectedDate = selectedDateValue;
            }
            
            checkTimeSlotAvailability();
        });
    </script>
</body>
</html>