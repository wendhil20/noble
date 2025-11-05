<?php
// delivery_schedule.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin', 'sales', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$schedule_all = isset($_GET['schedule_all']) && $_GET['schedule_all'] === 'true';
$schedule_replacements = isset($_GET['schedule_replacements']) && $_GET['schedule_replacements'] === 'true';

// Validate input parameters
if ($order_id <= 0 || (!$schedule_all && !$schedule_replacements)) {
    header("Location: order_list.php");
    exit();
}

// Get order details
$orderSql = "SELECT * FROM orders WHERE id = ? LIMIT 1";
$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: order_list.php");
    exit();
}

// Get items in warehouse based on scheduling mode
if ($schedule_replacements) {
    // Only get replacement items that are in warehouse
    $itemsSql = "
        SELECT 
            oi.id,
            CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
            rr.replacement_quantity as quantity,
            oi.price,
            CAST(oi.origin AS CHAR) COLLATE utf8mb4_unicode_ci as origin,
            CAST(rr.status AS CHAR) COLLATE utf8mb4_unicode_ci as tracking_status,
            CAST('replacement' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
            rr.id as replacement_id,
            CAST(rr.reason AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason,
            oi.lt_from,
            oi.lt_to
        FROM replacement_requests rr
        JOIN order_items oi ON rr.order_item_id = oi.id
        WHERE rr.order_id = ? AND rr.status = 'In Warehouse'
        ORDER BY product_name
    ";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $order_id);
} else {
    // Get all items (original + replacements) in warehouse
    $itemsSql = "
        SELECT 
            oi.id,
            CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
            oi.quantity,
            oi.price,
            CAST(oi.origin AS CHAR) COLLATE utf8mb4_unicode_ci as origin,
            CAST(oi.tracking_status AS CHAR) COLLATE utf8mb4_unicode_ci as tracking_status,
            CAST('original' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
            NULL as replacement_id,
            CAST(NULL AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason,
            oi.lt_from,
            oi.lt_to
        FROM order_items oi
        WHERE oi.order_id = ? AND oi.tracking_status = 'In Warehouse'
        
        UNION ALL
        
        SELECT 
            oi.id,
            CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
            rr.replacement_quantity as quantity,
            oi.price,
            CAST(oi.origin AS CHAR) COLLATE utf8mb4_unicode_ci as origin,
            CAST(rr.status AS CHAR) COLLATE utf8mb4_unicode_ci as tracking_status,
            CAST('replacement' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
            rr.id as replacement_id,
            CAST(rr.reason AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason,
            oi.lt_from,
            oi.lt_to
        FROM replacement_requests rr
        JOIN order_items oi ON rr.order_item_id = oi.id
        WHERE rr.order_id = ? AND rr.status = 'In Warehouse'
        
        ORDER BY item_type, product_name
    ";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("ii", $order_id, $order_id);
}
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Calculate the latest lead time range from all items
$latestLtFrom = null;
$latestLtTo = null;

foreach ($items as $item) {
    if (!empty($item['lt_from']) && !empty($item['lt_to'])) {
        $currentLtFrom = new DateTime($item['lt_from']);
        $currentLtTo = new DateTime($item['lt_to']);

        // Find the latest lt_from date
        if ($latestLtFrom === null || $currentLtFrom > $latestLtFrom) {
            $latestLtFrom = $currentLtFrom;
        }

        // Find the latest lt_to date
        if ($latestLtTo === null || $currentLtTo > $latestLtTo) {
            $latestLtTo = $currentLtTo;
        }
    }
}

// Format the expected delivery message
$expectedDeliveryMessage = '';
if ($latestLtFrom && $latestLtTo) {
    $formattedLtFrom = $latestLtFrom->format('F j, Y');
    $formattedLtTo = $latestLtTo->format('F j, Y');
    $expectedDeliveryMessage = "Expected delivery: {$formattedLtFrom} - {$formattedLtTo}";
}

// Check if there are any items ready for scheduling
if (empty($items)) {
    header("Location: order_tracking.php?order_id=$order_id");
    exit();
}

// Calculate totals
$totalItems = count($items);
$totalQuantity = array_sum(array_column($items, 'quantity'));
$hasReplacements = !empty(array_filter($items, function ($item) {
    return $item['item_type'] === 'replacement';
}));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_delivery'])) {
    $delivery_date = $_POST['delivery_date'];
    $delivery_time = $_POST['delivery_time'];
    $delivery_notes = $_POST['delivery_notes'] ?? '';

    try {
        $conn->begin_transaction();

        // Determine item_type based on scheduling mode
        $item_type_for_schedule = $schedule_replacements ? 'replacement' : 'original';

        // Insert ONE delivery schedule record for the entire order
        $scheduleSql = "INSERT INTO delivery_schedules 
                    (order_id, delivery_date, delivery_time, delivery_notes, item_type, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $scheduleStmt = $conn->prepare($scheduleSql);
        $created_by = $_SESSION['noble_user'];
        $scheduleStmt->bind_param("isssss", $order_id, $delivery_date, $delivery_time, $delivery_notes, $item_type_for_schedule, $created_by);
        $scheduleStmt->execute();
        $scheduleStmt->close();

        // Update items based on scheduling mode
        $affectedOriginal = 0;
        $affectedReplacement = 0;

        if ($schedule_replacements) {
            // Only update replacement items
            $updateReplacementSql = "UPDATE replacement_requests 
                            SET status = 'scheduled' 
                            WHERE order_id = ? AND status = 'In Warehouse'";
            $updateReplacementStmt = $conn->prepare($updateReplacementSql);
            $updateReplacementStmt->bind_param("i", $order_id);
            $updateReplacementStmt->execute();
            $affectedReplacement = $updateReplacementStmt->affected_rows;
            $updateReplacementStmt->close();
        } else {
            // Update both original and replacement items
            $updateOriginalSql = "UPDATE order_items 
                         SET tracking_status = 'scheduled' 
                         WHERE order_id = ? AND tracking_status = 'In Warehouse'";
            $updateOriginalStmt = $conn->prepare($updateOriginalSql);
            $updateOriginalStmt->bind_param("i", $order_id);
            $updateOriginalStmt->execute();
            $affectedOriginal = $updateOriginalStmt->affected_rows;
            $updateOriginalStmt->close();

            $updateReplacementSql = "UPDATE replacement_requests 
                            SET status = 'scheduled' 
                            WHERE order_id = ? AND status = 'In Warehouse'";
            $updateReplacementStmt = $conn->prepare($updateReplacementSql);
            $updateReplacementStmt->bind_param("i", $order_id);
            $updateReplacementStmt->execute();
            $affectedReplacement = $updateReplacementStmt->affected_rows;
            $updateReplacementStmt->close();
        }

        $conn->commit();

        $totalUpdated = $affectedOriginal + $affectedReplacement;
        $success_message = "Order delivery scheduled successfully! {$totalUpdated} item(s) updated to 'Scheduled'.";

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

// Get scheduled deliveries for the next 30 days (order-based)
// Get scheduled deliveries for the next 30 days (including both original and replacement)
$scheduleSql = "SELECT ds.*, 
                       o.customer_name, 
                       o.address,
                       o.id as order_id,
                       COUNT(DISTINCT oi.id) as original_count,
                       SUM(oi.quantity) as original_quantity,
                       COUNT(DISTINCT rr.id) as replacement_count,
                       SUM(rr.replacement_quantity) as replacement_quantity
                FROM delivery_schedules ds
                JOIN orders o ON ds.order_id = o.id
                LEFT JOIN order_items oi ON oi.order_id = ds.order_id AND oi.tracking_status IN ('ready_for_pickup', 'out_for_delivery')
                LEFT JOIN replacement_requests rr ON rr.order_id = ds.order_id AND rr.status IN ('ready_for_pickup', 'out_for_delivery')
                WHERE ds.delivery_date >= CURDATE() 
                  AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                  AND ds.item_type IN ('original', 'replacement')
                GROUP BY ds.id, ds.order_id, ds.delivery_date, ds.delivery_time, ds.delivery_notes, 
                         o.customer_name, o.address, ds.item_type
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
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
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
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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
                    <div class="bg-gradient-to-r from-green-500 to-green-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-calendar-check text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            <?php echo $schedule_replacements ? 'Schedule Replacement Delivery' : 'Schedule Order Delivery'; ?>
                        </h1>
                        <p class="text-gray-600 mt-1">
                            Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($order['customer_name']); ?>
                            <?php if ($hasReplacements): ?>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium ml-2">
                                    <i class="fas fa-sync-alt mr-1"></i>Includes Replacements
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex flex-col space-y-2">
                    <div class="bg-blue-100 px-4 py-2 rounded-lg">
                        <span class="text-blue-900 font-semibold">
                            <i class="fas fa-boxes mr-2"></i><?php echo $totalItems; ?> Items
                        </span>
                    </div>
                    <div class="bg-green-100 px-4 py-2 rounded-lg">
                        <span class="text-green-900 font-semibold">
                            <i class="fas fa-cube mr-2"></i><?php echo $totalQuantity; ?> Total Qty
                        </span>
                    </div>
                    <?php if ($expectedDeliveryMessage): ?>
                        <div class="bg-orange-100 px-4 py-2 rounded-lg">
                            <span class="text-orange-900 font-semibold text-sm">
                                <i class="fas fa-calendar-alt mr-2"></i><?php echo $expectedDeliveryMessage; ?>
                            </span>
                        </div>
                    <?php endif; ?>
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
                <!-- Order Items Summary -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-boxes text-green-600 mr-3"></i>
                        Order Items (<?php echo $totalItems; ?>)
                    </h3>

                    <div class="max-h-96 overflow-y-auto space-y-3">
                        <?php foreach ($items as $item): ?>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 <?php echo $item['item_type'] === 'replacement' ? 'border-l-4 border-l-red-500' : ''; ?>">
                                <?php if ($item['item_type'] === 'replacement'): ?>
                                    <div class="flex items-center mb-2">
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium">
                                            <i class="fas fa-sync-alt mr-1"></i>REPLACEMENT
                                        </span>
                                    </div>
                                    <?php if ($item['replacement_reason']): ?>
                                        <p class="text-red-600 text-xs mb-2">
                                            Reason: <?php echo htmlspecialchars(ucfirst($item['replacement_reason'])); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="font-medium text-gray-900 mb-1">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </div>
                                <div class="flex justify-between text-sm text-gray-600">
                                    <span>Qty: <?php echo $item['quantity']; ?></span>
                                    <span>₱<?php echo number_format((float)$item['price'], 2); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Items:</span>
                                <span class="font-bold"><?php echo $totalItems; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Quantity:</span>
                                <span class="font-bold"><?php echo $totalQuantity; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Customer:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                            </div>
                            <?php if ($expectedDeliveryMessage): ?>
                                <div class="pt-2 border-t">
                                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                                        <div class="flex items-start">
                                            <i class="fas fa-clock text-orange-600 mr-2 mt-1"></i>
                                            <div>
                                                <span class="text-orange-900 font-semibold text-sm block">Estimated Delivery</span>
                                                <span class="text-orange-800 text-sm"><?php echo str_replace('Expected delivery: ', '', $expectedDeliveryMessage); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="pt-2 border-t">
                                <span class="text-gray-600">Delivery Address:</span>
                                <p class="font-medium mt-1"><?php echo htmlspecialchars($order['address']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Schedule Form -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-calendar-check text-green-600 mr-3"></i>
                        Schedule Order Delivery
                    </h3>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-2 text-gray-500"></i>
                                Delivery Date
                            </label>
                            <input type="date" name="delivery_date" id="delivery_date" required
                                min="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-<?php echo $hasReplacements ? 'red' : 'blue'; ?>-500 focus:border-<?php echo $hasReplacements ? 'red' : 'blue'; ?>-500 text-lg">
                            <p class="text-sm text-gray-500 mt-1">Click on calendar days to select dates</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-2 text-gray-500"></i>
                                Delivery Time
                            </label>
                            <select name="delivery_time" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-<?php echo $hasReplacements ? 'red' : 'blue'; ?>-500 focus:border-<?php echo $hasReplacements ? 'red' : 'blue'; ?>-500 text-lg">
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
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-<?php echo $hasReplacements ? 'red' : 'blue'; ?>-500 focus:border-<?php echo $hasReplacements ? 'red' : 'blue'; ?>-500"
                                placeholder="Special instructions, contact details, or delivery preferences...<?php echo $hasReplacements ? ' (Includes replacement items)' : ''; ?>"></textarea>
                        </div>

                        <div class="pt-4">
                            <button type="submit" name="schedule_delivery"
                                class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-medium py-4 px-6 rounded-lg transition-all duration-200 transform hover:scale-[1.02] text-lg shadow-lg">
                                <i class="fas fa-calendar-check mr-3"></i>
                                Schedule All <?php echo $totalItems; ?> Items for Delivery
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
        const totalItems = <?php echo $totalItems; ?>;
        const hasReplacements = <?php echo $hasReplacements ? 'true' : 'false'; ?>;

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
                `${selectedDateSchedules.length} orders on ${formatDisplayDate(selectedDate)}` :
                `${schedules.length} orders`;

            if (selectedDateSchedules.length === 0) {
                scheduledItemsContainer.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-day text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No Orders Scheduled</h4>
                        <p class="text-gray-500">No deliveries scheduled for ${formatDisplayDate(selectedDate)}</p>
                        <button type="button" onclick="showAllScheduledItems()" 
                                class="mt-4 text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-list mr-1"></i>
                            View All Scheduled Orders
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
                                ${daySchedules.length} orders
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
                    html += renderScheduleItem(schedule);
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

            itemsCountSpan.textContent = `${schedules.length} orders`;

            if (schedules.length === 0) {
                scheduledItemsContainer.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-times text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No Orders Scheduled</h4>
                        <p class="text-gray-500">Schedule your first order for delivery using the form.</p>
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
                                ${daySchedules.length} orders
                            </span>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                `;

                daySchedules.forEach(schedule => {
                    html += renderScheduleItem(schedule);
                });

                html += '</div>';
            }

            scheduledItemsContainer.innerHTML = html;
        }

        function renderScheduleItem(schedule) {
            const originalCount = parseInt(schedule.original_count) || 0;
            const replacementCount = parseInt(schedule.replacement_count) || 0;
            const totalItems = originalCount + replacementCount;
            const originalQty = parseInt(schedule.original_quantity) || 0;
            const replacementQty = parseInt(schedule.replacement_quantity) || 0;
            const totalQty = originalQty + replacementQty;

            return `
        <div class="item-card bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <div class="flex items-center mb-1">
                        <h5 class="font-semibold text-gray-900 text-lg">
                            Order #${schedule.order_id}
                        </h5>
                        <span class="ml-2 bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium">
                            <i class="fas fa-boxes mr-1"></i>${totalItems} items
                        </span>
                        ${replacementCount > 0 ? `
                            <span class="ml-2 bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium">
                                <i class="fas fa-sync-alt mr-1"></i>${replacementCount} replacement
                            </span>
                        ` : ''}
                    </div>
                    <p class="text-green-600 font-medium mb-2">
                        <i class="fas fa-clock mr-1"></i>
                        ${formatTime(schedule.delivery_time)}
                    </p>
                </div>
                <div class="text-right">
                    <span class="bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-sm font-medium block mb-1">
                        Total: ${totalQty}
                    </span>
                    ${replacementCount > 0 ? `
                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs font-medium block">
                            +${replacementQty} replaced
                        </span>
                    ` : ''}
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