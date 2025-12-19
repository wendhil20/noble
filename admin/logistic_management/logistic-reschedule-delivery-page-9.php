<?php
// reschedule_delivery.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get delivery ID and current date from URL parameters
$delivery_id = $_GET['delivery_id'] ?? null;
$return_date = $_GET['date'] ?? null;

// Validate delivery ID
if (!$delivery_id || !is_numeric($delivery_id)) {
    header("Location: logistic-main-dashboard-page-1.php");
    exit();
}

// Handle reschedule form submission
if ($_POST && isset($_POST['reschedule_delivery'])) {
    $new_delivery_date = $_POST['new_delivery_date'];
    $new_delivery_time = $_POST['new_delivery_time'];
    $reschedule_reason = $_POST['reschedule_reason'] ?? '';
    
    // Validate new date and time
    if (!$new_delivery_date || !$new_delivery_time) {
        $error_message = "Please select both new delivery date and time.";
    } else {
        $conn->begin_transaction();
        
        try {
            // Get item_id from delivery_schedules
            $getItemSql = "SELECT item_id, order_id FROM delivery_schedules WHERE id = ?";
            $getItemStmt = $conn->prepare($getItemSql);
            $getItemStmt->bind_param("i", $delivery_id);
            $getItemStmt->execute();
            $item_result = $getItemStmt->get_result()->fetch_assoc();
            $item_id = $item_result['item_id'];
            $order_id = $item_result['order_id'];
            $getItemStmt->close();
            
            // Update delivery schedule
            $updateDeliverySql = "UPDATE delivery_schedules SET 
                delivery_date = ?, 
                delivery_time = ?, 
                delivery_notes = CONCAT(COALESCE(delivery_notes, ''), IF(delivery_notes IS NOT NULL AND delivery_notes != '', '\n', ''), 'Rescheduled: ', ?),
                truck_schedule_id = NULL,
                assigned_truck = NULL,
                delivery_status = 'scheduled',
                delivery_type = NULL,
                updated_at = NOW()
                WHERE id = ?";
            $updateDeliveryStmt = $conn->prepare($updateDeliverySql);
            $updateDeliveryStmt->bind_param("sssi", $new_delivery_date, $new_delivery_time, $reschedule_reason, $delivery_id);
            
            // Update order item tracking status to ready_for_pickup
            $updateOrderItemSql = "UPDATE order_items SET tracking_status = 'ready_for_pickup' WHERE id = ?";
            $updateOrderItemStmt = $conn->prepare($updateOrderItemSql);
            $updateOrderItemStmt->bind_param("i", $item_id);
            
            if ($updateDeliveryStmt->execute() && $updateOrderItemStmt->execute()) {
                // Log the reschedule action
                $logSql = "INSERT INTO delivery_logs (delivery_id, order_id, action_type, action_details, created_by, created_at) VALUES (?, ?, 'reschedule', ?, ?, NOW())";
                $logStmt = $conn->prepare($logSql);
                $log_details = "Rescheduled to $new_delivery_date $new_delivery_time. Reason: $reschedule_reason";
                
                // Handle different session data formats
                if (is_array($_SESSION['noble_user'])) {
                    $created_by = $_SESSION['noble_user']['id'] ?? 1; // Default to 1 if id not found
                } else {
                    $created_by = 1; // Default admin user ID
                }
                
                $logStmt->bind_param("iisi", $delivery_id, $order_id, $log_details, $created_by);
                $logStmt->execute();
                $logStmt->close();
                
                $conn->commit();
                $success_message = "Delivery successfully rescheduled to " . date('F j, Y', strtotime($new_delivery_date)) . " at " . date('g:i A', strtotime($new_delivery_time));
            } else {
                throw new Exception("Error updating delivery schedule: " . $conn->error);
            }
            
            $updateDeliveryStmt->close();
            $updateOrderItemStmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Get current delivery details
$currentDeliverySql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.item_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivery_status,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    oi.product_name,
    oi.quantity,
    oi.price,
    oi.variant_color,
    oi.size,
    oi.subtotal,
    oi.tracking_status
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
INNER JOIN order_items oi ON ds.item_id = oi.id
WHERE ds.id = ?";

$currentDeliveryStmt = $conn->prepare($currentDeliverySql);
$currentDeliveryStmt->bind_param("i", $delivery_id);
$currentDeliveryStmt->execute();
$current_delivery = $currentDeliveryStmt->get_result()->fetch_assoc();
$currentDeliveryStmt->close();

if (!$current_delivery) {
    header("Location: logistic-main-dashboard-page-1.php");
    exit();
}

// Get delivery schedules for calendar (next 60 days)
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
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    oi.product_name,
    oi.quantity,
    oi.price,
    oi.variant_color,
    oi.size,
    oi.subtotal,
    oi.tracking_status,
    CASE 
        WHEN ds.delivered_at IS NULL AND ds.delivery_date < CURDATE() THEN 'overdue'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date = CURDATE() THEN 'today_pending'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date > CURDATE() THEN 'upcoming'
        WHEN ds.delivered_at IS NOT NULL THEN 'completed'
    END as delivery_status_calc
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
INNER JOIN order_items oi ON ds.item_id = oi.id
WHERE ds.delivery_date >= CURDATE()
    AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    AND ds.id != ?
ORDER BY ds.delivery_date ASC, ds.delivery_time ASC";

$scheduleStmt = $conn->prepare($scheduleSql);
$scheduleStmt->bind_param("i", $delivery_id);
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
    AND ds.id != ?
GROUP BY DATE(ds.delivery_date)";

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param("i", $delivery_id);
$countStmt->execute();
$deliveryCounts = $countStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$countStmt->close();

// Convert to associative array for easier lookup
$deliveryCountsByDate = [];
foreach ($deliveryCounts as $data) {
    $deliveryCountsByDate[$data['date']] = $data;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Delivery - Noble Home</title>
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
        .calendar-day.busy {
            background-color: #f3e5f5;
            border: 2px solid #9c27b0;
        }
        .calendar-day.past-date {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
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
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-6 space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-r from-yellow-500 to-orange-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Reschedule Delivery</h1>
                        <p class="text-gray-600 mt-1">Reschedule delivery for Order #<?php echo $current_delivery['order_id']; ?></p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center space-x-3">
                    <?php if ($return_date): ?>
                    <a href="logistic-delivery-detailed-view-page-14.php?date=<?php echo $return_date; ?>" 
                       class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Detailed View
                    </a>
                    <?php endif; ?>
                    <a href="logistic-main-dashboard-page-1.php" 
                       class="bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition-colors flex items-center">
                        <i class="fas fa-dashboard mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <div class="mt-3 flex space-x-3">
                <?php if ($return_date): ?>
                <a href="logistic-delivery-detailed-view-page-14.php?date=<?php echo $return_date; ?>" 
                   class="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 transition-colors">
                    Return to Detailed View
                </a>
                <?php endif; ?>
                <a href="logistic-main-dashboard-page-1.php" 
                   class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700 transition-colors">
                    Back to Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            
            <!-- Current Delivery Details -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-box text-orange-600 mr-3"></i>
                        Current Delivery Details
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-medium">
                                    Order #<?php echo $current_delivery['order_id']; ?>
                                </span>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">
                                    Needs Reschedule
                                </span>
                            </div>
                            
                            <h4 class="font-semibold text-gray-900 mb-3">
                                <?php echo htmlspecialchars($current_delivery['product_name']); ?>
                            </h4>
                            
                            <div class="grid grid-cols-2 gap-3 text-sm mb-3">
                                <div class="bg-white p-2 rounded">
                                    <span class="text-gray-600">Quantity:</span>
                                    <span class="font-medium ml-1"><?php echo $current_delivery['quantity']; ?></span>
                                </div>
                                <div class="bg-white p-2 rounded">
                                    <span class="text-gray-600">Value:</span>
                                    <span class="font-medium ml-1">₱<?php echo number_format($current_delivery['subtotal'], 2); ?></span>
                                </div>
                                <?php if ($current_delivery['variant_color']): ?>
                                <div class="bg-white p-2 rounded">
                                    <span class="text-gray-600">Color:</span>
                                    <span class="font-medium ml-1"><?php echo htmlspecialchars($current_delivery['variant_color']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($current_delivery['size']): ?>
                                <div class="bg-white p-2 rounded">
                                    <span class="text-gray-600">Size:</span>
                                    <span class="font-medium ml-1"><?php echo htmlspecialchars($current_delivery['size']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="bg-white rounded p-3 space-y-2">
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-2 text-gray-500 w-4"></i>
                                    <span class="font-medium"><?php echo htmlspecialchars($current_delivery['customer_name']); ?></span>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-map-marker-alt mr-2 text-gray-500 w-4 mt-1"></i>
                                    <span class="text-sm flex-1"><?php echo htmlspecialchars($current_delivery['address']); ?></span>
                                </div>
                                <?php if ($current_delivery['mobile']): ?>
                                <div class="flex items-center">
                                    <i class="fas fa-phone mr-2 text-gray-500 w-4"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($current_delivery['mobile']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h5 class="font-medium text-red-800 mb-2 flex items-center">
                                <i class="fas fa-calendar-times mr-2"></i>
                                Current Schedule
                            </h5>
                            <div class="text-sm text-red-700">
                                <div class="flex justify-between">
                                    <span>Date:</span>
                                    <span class="font-medium"><?php echo date('F j, Y', strtotime($current_delivery['delivery_date'])); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Time:</span>
                                    <span class="font-medium"><?php echo date('g:i A', strtotime($current_delivery['delivery_time'])); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Status:</span>
                                    <span class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $current_delivery['tracking_status'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reschedule Form -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-calendar-plus text-green-600 mr-3"></i>
                        Reschedule Delivery
                    </h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="reschedule_delivery" value="1">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                New Delivery Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="new_delivery_date" id="new_delivery_date" required
                                   min="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                New Delivery Time <span class="text-red-500">*</span>
                            </label>
                            <select name="new_delivery_time" id="new_delivery_time" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select time...</option>
                                <option value="08:00:00">8:00 AM</option>
                                <option value="09:00:00">9:00 AM</option>
                                <option value="10:00:00">10:00 AM</option>
                                <option value="11:00:00">11:00 AM</option>
                                <option value="13:00:00">1:00 PM</option>
                                <option value="14:00:00">2:00 PM</option>
                                <option value="15:00:00">3:00 PM</option>
                                <option value="16:00:00">4:00 PM</option>
                                <option value="17:00:00">5:00 PM</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reschedule Reason
                            </label>
                            <textarea name="reschedule_reason" rows="3" 
                                      placeholder="Enter reason for rescheduling..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-600 mr-2 mt-1"></i>
                                <div class="text-sm text-blue-800">
                                    <strong>Note:</strong> When you reschedule this delivery:
                                    <ul class="mt-2 list-disc list-inside space-y-1">
                                        <li>The item will be removed from any assigned truck</li>
                                        <li>Tracking status will be changed to "Ready for Pickup"</li>
                                        <li>The customer will need to be notified of the new schedule</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <button type="submit" 
                                    class="flex-1 bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition-colors font-medium">
                                <i class="fas fa-calendar-check mr-2"></i>
                                Reschedule Delivery
                            </button>
                            <a href="<?php echo $return_date ? "logistic-delivery-detailed-view-page-14.php?date=$return_date" : 'logistic-main-dashboard-page-1.php'; ?>" 
                               class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Calendar Section -->
            <div class="xl:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-calendar text-blue-600 mr-3"></i>
                        Available Delivery Dates
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
                            <div class="w-4 h-4 bg-purple-100 border-2 border-purple-500 rounded mr-2"></div>
                            <span>Busy (10+ deliveries)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-blue-600 rounded mr-2"></div>
                            <span>Selected date</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-gray-300 rounded mr-2"></div>
                            <span>Past dates (unavailable)</span>
                        </div>
                    </div>
                </div>
                
                <!-- Selected Date Deliveries -->
                <div id="dateDeliveriesSection" class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6" style="display: none;">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-list text-purple-600 mr-3"></i>
                            <span id="selectedDateTitle">Deliveries</span>
                        </h3>
                        <div class="flex items-center mt-2">
                            <span id="selectedDateCount" class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                                0 items
                            </span>
                        </div>
                    </div>
                    
                    <div id="selectedDateDeliveries" class="max-h-[400px] overflow-y-auto">
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
                
                const dayNumber = document.createElement('div');
                dayNumber.textContent = day;
                dayNumber.className = 'font-semibold';
                dayElement.appendChild(dayNumber);
                
                dayElement.dataset.date = dateString;
                
                const currentDateOnly = new Date(year, month, day);
                currentDateOnly.setHours(0, 0, 0, 0);
                
                // Check if this date is in the past (disable past dates)
                if (currentDateOnly < today) {
                    dayElement.className += ' past-date';
                    dayElement.style.cursor = 'not-allowed';
                } else {
                    // Check if this date has deliveries
                    const deliveryData = deliveryCounts[dateString];
                    
                    if (deliveryData) {
                        const totalCount = deliveryData.count;
                        const completedCount = deliveryData.completed_count;
                        const pendingCount = deliveryData.pending_count;
                        
                        // Create badges container
                        const badgesContainer = document.createElement('div');
                        badgesContainer.className = 'delivery-badges';
                        
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
                        } else {
                            dayElement.className += ' has-deliveries';
                        }
                        
                        dayElement.appendChild(badgesContainer);
                    }
                    
                    // Add click event for future dates
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
            
            // Update form date input
            document.getElementById('new_delivery_date').value = dateString;
            
            // Show deliveries for selected date
            showDateDeliveries(dateString);
        }
        
        function showDateDeliveries(dateString) {
            const dateSchedules = schedules.filter(s => s.delivery_date === dateString);
            const section = document.getElementById('dateDeliveriesSection');
            const title = document.getElementById('selectedDateTitle');
            const count = document.getElementById('selectedDateCount');
            const container = document.getElementById('selectedDateDeliveries');
            
            title.textContent = `Deliveries for ${formatDisplayDate(dateString)}`;
            count.textContent = `${dateSchedules.length} items`;
            
            if (dateSchedules.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-day text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No deliveries scheduled</h4>
                        <p class="text-gray-500">This date is available for scheduling</p>
                    </div>
                `;
            } else {
                // Group schedules by time
                const schedulesByTime = {};
                dateSchedules.forEach(schedule => {
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
                        <div class="border-b border-gray-200">
                            <div class="bg-gray-50 px-6 py-3">
                                <h5 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                                    <i class="fas fa-clock mr-2 text-gray-500"></i>
                                    ${formatTime(time)} (${timeSchedules.length} items)
                                </h5>
                            </div>
                            <div class="p-4 space-y-3">
                    `;
                    
                    timeSchedules.forEach(schedule => {
                        const isCompleted = schedule.delivered_at !== null;
                        
                        let statusClass, statusIcon, statusText, statusBg;
                        
                        if (isCompleted) {
                            statusClass = 'bg-green-100 text-green-800 border-green-200';
                            statusIcon = 'fa-check-circle';
                            statusText = 'Delivered';
                            statusBg = 'bg-green-50 border-green-200';
                        } else {
                            statusClass = 'bg-blue-100 text-blue-800 border-blue-200';
                            statusIcon = 'fa-clock';
                            statusText = 'Scheduled';
                            statusBg = 'bg-blue-50 border-blue-200';
                        }
                        
                        html += `
                            <div class="item-card ${statusBg} border-2 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h6 class="font-semibold text-gray-900 mb-2">
                                            ${escapeHtml(schedule.product_name)}
                                        </h6>
                                        <div class="flex items-center space-x-3 mb-2">
                                            <span class="${statusClass} border px-3 py-1 rounded-full text-xs font-medium">
                                                <i class="fas ${statusIcon} mr-1"></i>
                                                ${statusText}
                                            </span>
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-medium">
                                                Order #${schedule.order_id}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3 text-sm text-gray-700 mb-3">
                                    <div class="bg-white bg-opacity-50 p-2 rounded">
                                        <span class="font-medium text-gray-600">Quantity:</span> 
                                        <span class="font-semibold">${schedule.quantity}</span>
                                    </div>
                                    <div class="bg-white bg-opacity-50 p-2 rounded">
                                        <span class="font-medium text-gray-600">Price:</span> 
                                        <span class="font-semibold">₱${parseFloat(schedule.price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                    </div>
                                </div>
                                
                                <div class="bg-white bg-opacity-50 rounded-lg p-3 space-y-2">
                                    <div class="flex items-center">
                                        <i class="fas fa-user mr-3 text-gray-500 w-4"></i>
                                        <span class="font-medium text-gray-800">${escapeHtml(schedule.customer_name)}</span>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-map-marker-alt mr-3 text-gray-500 w-4 mt-1"></i>
                                        <span class="text-gray-700 text-sm flex-1">${escapeHtml(schedule.address)}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div></div>';
                });
                
                container.innerHTML = html;
            }
            
            section.style.display = 'block';
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
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            
            // Form submission validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const date = document.getElementById('new_delivery_date').value;
                const time = document.getElementById('new_delivery_time').value;
                
                if (!date || !time) {
                    e.preventDefault();
                    alert('Please select both delivery date and time.');
                    return;
                }
                
                // Check if date is not in the past
                const selectedDate = new Date(date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    e.preventDefault();
                    alert('Please select a future date for delivery.');
                    return;
                }
                
                // Confirm reschedule
                if (!confirm('Are you sure you want to reschedule this delivery? This will remove it from any assigned truck and change the tracking status to "Ready for Pickup".')) {
                    e.preventDefault();
                    return;
                }
            });
            
            // Date input change event
            document.getElementById('new_delivery_date').addEventListener('change', function() {
                const dateString = this.value;
                if (dateString) {
                    // Find and select the corresponding calendar day
                    const calendarDay = document.querySelector(`[data-date="${dateString}"]`);
                    if (calendarDay && !calendarDay.classList.contains('past-date')) {
                        selectDate(dateString, calendarDay);
                    } else {
                        // Show deliveries for the selected date even if not visible in calendar
                        showDateDeliveries(dateString);
                    }
                }
            });
        });
    </script>
</body>
</html>