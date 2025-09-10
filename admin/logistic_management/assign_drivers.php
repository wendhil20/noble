<?php
// assign_drivers.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get the selected date from URL parameter
$selected_date = $_GET['date'] ?? null;

// Validate date format
if (!$selected_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    header("Location: logistics_dashboard_view.php");
    exit();
}

// Handle truck scheduling
if ($_POST && isset($_POST['schedule_truck'])) {
    $truck_plate = $_POST['truck_plate'];
    $max_capacity = $_POST['max_capacity'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    // Check if truck is already scheduled for this date
    $checkSql = "SELECT id FROM truck_schedules WHERE truck_id = ? AND scheduled_date = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ss", $truck_plate, $selected_date);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        $error_message = "Truck is already scheduled for this date.";
    } else {
        $insertSql = "INSERT INTO truck_schedules (truck_id, scheduled_date, max_capacity, notes, status) VALUES (?, ?, ?, ?, 'scheduled')";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("ssss", $truck_plate, $selected_date, $max_capacity, $notes);
        
        if ($insertStmt->execute()) {
            $success_message = "Truck scheduled successfully!";
        } else {
            $error_message = "Error scheduling truck: " . $conn->error;
        }
        $insertStmt->close();
    }
}

// Handle removing truck schedule
if ($_POST && isset($_POST['remove_truck_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    
    $removeSql = "DELETE FROM truck_schedules WHERE id = ?";
    $removeStmt = $conn->prepare($removeSql);
    $removeStmt->bind_param("i", $schedule_id);
    
    if ($removeStmt->execute()) {
        $success_message = "Truck schedule removed successfully!";
    } else {
        $error_message = "Error removing truck schedule: " . $conn->error;
    }
    $removeStmt->close();
}

// Convert date for display
$display_date = new DateTime($selected_date);
$formatted_date = $display_date->format('l, F j, Y');

// Get delivery schedules for the selected date
$scheduleSql = "SELECT 
    ds.id as delivery_id,
    ds.order_id,
    ds.item_id,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
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
    CASE 
        WHEN ds.delivered_at IS NULL AND ds.delivery_date < CURDATE() THEN 'overdue'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date = CURDATE() THEN 'today_pending'
        WHEN ds.delivered_at IS NULL AND ds.delivery_date > CURDATE() THEN 'upcoming'
        WHEN ds.delivered_at IS NOT NULL THEN 'completed'
    END as delivery_status
FROM delivery_schedules ds
INNER JOIN orders o ON ds.order_id = o.id
INNER JOIN order_items oi ON ds.item_id = oi.id
WHERE ds.delivery_date = ?
ORDER BY ds.delivery_time ASC, ds.order_id ASC";

$scheduleStmt = $conn->prepare($scheduleSql);
$scheduleStmt->bind_param("s", $selected_date);
$scheduleStmt->execute();
$schedules = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduleStmt->close();

// Get available trucks for scheduling
$availableTrucksSql = "SELECT 
    vl.id as truck_id,
    vl.plate_number,
    vl.make,
    vl.model,
    vl.truck_type,
    vl.photo_path,
    vl.status,
    vl.weight_capacity,
    vl.volume_capacity
FROM vehicle_list vl
LEFT JOIN truck_schedules ts ON vl.plate_number = ts.truck_id AND ts.scheduled_date = ?
WHERE vl.status = 'active'
    AND (vl.unavailable_days IS NULL OR vl.unavailable_days NOT LIKE CONCAT('%', DAYNAME(STR_TO_DATE(?, '%Y-%m-%d')), '%'))
    AND ts.id IS NULL
ORDER BY vl.plate_number";

$availableTrucksStmt = $conn->prepare($availableTrucksSql);
$availableTrucksStmt->bind_param("ss", $selected_date, $selected_date);
$availableTrucksStmt->execute();
$available_trucks = $availableTrucksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$availableTrucksStmt->close();

// Get scheduled trucks for the selected date
$scheduledTrucksSql = "SELECT 
    ts.id as schedule_id,
    ts.truck_id,
    ts.scheduled_date,
    ts.max_capacity,
    ts.notes,
    ts.status,
    ts.created_at,
    vl.make,
    vl.model,
    vl.truck_type,
    vl.photo_path,
    vl.weight_capacity,
    vl.volume_capacity
FROM truck_schedules ts
INNER JOIN vehicle_list vl ON ts.truck_id = vl.plate_number
WHERE ts.scheduled_date = ?
ORDER BY ts.created_at DESC";

$scheduledTrucksStmt = $conn->prepare($scheduledTrucksSql);
$scheduledTrucksStmt->bind_param("s", $selected_date);
$scheduledTrucksStmt->execute();
$scheduled_trucks = $scheduledTrucksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduledTrucksStmt->close();

// Get summary statistics
$statsSql = "SELECT 
    COUNT(*) as total_scheduled,
    COUNT(CASE WHEN ds.delivered_at IS NULL THEN 1 END) as pending_deliveries,
    COUNT(CASE WHEN ds.delivered_at IS NOT NULL THEN 1 END) as completed_deliveries
FROM delivery_schedules ds
WHERE ds.delivery_date = ?";

$statsStmt = $conn->prepare($statsSql);
$statsStmt->bind_param("s", $selected_date);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Trucks for <?php echo $formatted_date; ?> - Noble Home</title>
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
        .truck-card {
            transition: all 0.3s ease;
        }
        .truck-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .schedule-modal {
            backdrop-filter: blur(5px);
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
                    <div class="bg-gradient-to-r from-green-500 to-green-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-truck text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Schedule Trucks</h1>
                        <p class="text-gray-600 mt-1"><?php echo $formatted_date; ?></p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center space-x-3">
                    <a href="delivery_detailed_view.php?date=<?php echo $selected_date; ?>" 
                       class="bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition-colors flex items-center">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        Assign Deliveries
                    </a>
                    <a href="logistics_dashboard_view.php" 
                       class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
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

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-blue-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-list text-blue-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Total Deliveries</p>
                    <p class="text-xl font-bold text-gray-900"><?php echo $stats['total_scheduled']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-green-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-truck text-green-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Scheduled Trucks</p>
                    <p class="text-xl font-bold text-green-600"><?php echo count($scheduled_trucks); ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-yellow-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-clock text-yellow-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Pending</p>
                    <p class="text-xl font-bold text-yellow-600"><?php echo $stats['pending_deliveries']; ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="text-center">
                    <div class="bg-purple-100 p-3 rounded-lg mx-auto w-fit mb-2">
                        <i class="fas fa-plus text-purple-600 text-lg"></i>
                    </div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Available to Schedule</p>
                    <p class="text-xl font-bold text-purple-600"><?php echo count($available_trucks); ?></p>
                </div>
            </div>
        </div>

        <!-- Schedule New Truck Button -->
        <?php if (!empty($available_trucks)): ?>
        <div class="mb-8">
            <button onclick="openScheduleModal()" 
                    class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition-colors font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Schedule New Truck
            </button>
        </div>
        <?php endif; ?>

        <!-- Scheduled Trucks Section -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 mb-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-calendar-check mr-3 text-green-500"></i>
                    Scheduled Trucks for <?php echo $formatted_date; ?>
                </h2>
            </div>
            
            <div class="p-6">
                <?php if (empty($scheduled_trucks)): ?>
                    <div class="text-center py-12">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-truck text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Trucks Scheduled</h3>
                        <p class="text-gray-500 mb-6">Schedule trucks to start assigning deliveries</p>
                        <?php if (!empty($available_trucks)): ?>
                        <button onclick="openScheduleModal()" 
                                class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition-colors font-medium">
                            <i class="fas fa-plus mr-2"></i>
                            Schedule First Truck
                        </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($scheduled_trucks as $truck): ?>
                            <div class="truck-card bg-gray-50 border border-gray-200 rounded-xl p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold">
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        Scheduled
                                    </span>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="schedule_id" value="<?php echo $truck['schedule_id']; ?>">
                                        <button type="submit" name="remove_truck_schedule" 
                                                onclick="return confirm('Remove truck from schedule?')"
                                                class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>

                                <div class="flex items-center space-x-4 mb-4">
                                    <div class="w-12 h-10 bg-gray-200 rounded overflow-hidden flex-shrink-0">
                                        <?php if ($truck['photo_path'] && file_exists("../uploads/truck_photo_collection/" . $truck['photo_path'])): ?>
                                            <img src="../uploads/truck_photo_collection/<?php echo htmlspecialchars($truck['photo_path']); ?>" 
                                                 alt="Truck Photo" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fas fa-truck text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900 text-lg">
                                            <?php echo htmlspecialchars($truck['truck_id']); ?>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($truck['make'] . ' ' . $truck['model']); ?>
                                        </div>
                                        <div class="text-xs text-blue-600">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $truck['truck_type']))); ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($truck['weight_capacity']): ?>
                                <div class="bg-white rounded-lg p-3 mb-4">
                                    <div class="text-xs text-gray-600 mb-1">Capacity</div>
                                    <div class="font-medium text-gray-900">
                                        <i class="fas fa-weight-hanging mr-1"></i>
                                        <?php echo $truck['weight_capacity']; ?> tons
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($truck['max_capacity']): ?>
                                <div class="bg-blue-50 rounded-lg p-3 mb-4">
                                    <div class="text-xs text-blue-600 mb-1">Max Items for This Date</div>
                                    <div class="font-medium text-blue-900">
                                        <i class="fas fa-boxes mr-1"></i>
                                        <?php echo $truck['max_capacity']; ?> items
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($truck['notes']): ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                    <div class="text-xs text-yellow-600 mb-1">Notes</div>
                                    <div class="text-sm text-yellow-700">
                                        <?php echo nl2br(htmlspecialchars($truck['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Schedule Truck Modal -->
    <div id="scheduleModal" class="fixed inset-0 bg-black bg-opacity-50 schedule-modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900">Schedule Truck</h3>
                        <button type="button" onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="schedule_truck" value="1">
                        
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Select Truck:</label>
                                <?php if (empty($available_trucks)): ?>
                                    <div class="text-center py-8">
                                        <div class="text-gray-400 mb-4">
                                            <i class="fas fa-truck-moving text-4xl"></i>
                                        </div>
                                        <p class="text-gray-600">No available trucks found</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-3 max-h-80 overflow-y-auto">
                                        <?php foreach ($available_trucks as $truck): ?>
                                            <label class="truck-option cursor-pointer">
                                                <input type="radio" name="truck_plate" value="<?php echo htmlspecialchars($truck['plate_number']); ?>" 
                                                       class="sr-only truck-radio" required>
                                                <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-green-300 transition-colors truck-selection-card">
                                                    <div class="flex items-center space-x-4">
                                                        <div class="w-12 h-10 bg-gray-200 rounded overflow-hidden flex-shrink-0">
                                                            <?php if ($truck['photo_path'] && file_exists("../uploads/truck_photo_collection/" . $truck['photo_path'])): ?>
                                                                <img src="../uploads/truck_photo_collection/<?php echo htmlspecialchars($truck['photo_path']); ?>" 
                                                                     alt="Truck Photo" class="w-full h-full object-cover">
                                                            <?php else: ?>
                                                                <div class="w-full h-full flex items-center justify-center">
                                                                    <i class="fas fa-truck text-gray-400"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="flex-1">
                                                            <div class="font-semibold text-gray-900">
                                                                <?php echo htmlspecialchars($truck['plate_number']); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-600">
                                                                <?php echo htmlspecialchars($truck['make'] . ' ' . $truck['model']); ?>
                                                            </div>
                                                            <div class="text-xs text-blue-600">
                                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $truck['truck_type']))); ?>
                                                            </div>
                                                            <?php if ($truck['weight_capacity']): ?>
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                <i class="fas fa-weight-hanging mr-1"></i>
                                                                <?php echo $truck['weight_capacity']; ?> tons capacity
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="text-green-500 opacity-0 truck-check">
                                                            <i class="fas fa-check-circle text-xl"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($available_trucks)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="max_capacity" class="block text-sm font-medium text-gray-700 mb-2">
                                        Maximum Items (Optional)
                                    </label>
                                    <input type="number" name="max_capacity" id="max_capacity" min="1" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500"
                                           placeholder="Leave empty for unlimited">
                                </div>
                            </div>
                            
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    Notes (Optional)
                                </label>
                                <textarea name="notes" id="notes" rows="3" 
                                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500"
                                          placeholder="Any special instructions or notes for this truck schedule..."></textarea>
                            </div>
                            
                            <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                                <button type="button" onclick="closeScheduleModal()" 
                                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-medium">
                                    <i class="fas fa-calendar-plus mr-2"></i>
                                    Schedule Truck
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openScheduleModal() {
            document.getElementById('scheduleModal').classList.remove('hidden');
            resetTruckSelection();
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').classList.add('hidden');
            resetTruckSelection();
        }

        function resetTruckSelection() {
            // Reset truck selections
            document.querySelectorAll('.truck-radio').forEach(radio => {
                radio.checked = false;
            });
            document.querySelectorAll('.truck-selection-card').forEach(card => {
                card.classList.remove('border-green-500', 'bg-green-50');
                card.classList.add('border-gray-200');
            });
            document.querySelectorAll('.truck-check').forEach(check => {
                check.classList.add('opacity-0');
            });
        }

        // Handle truck selection
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.truck-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        // Reset all truck cards
                        document.querySelectorAll('.truck-selection-card').forEach(card => {
                            card.classList.remove('border-green-500', 'bg-green-50');
                            card.classList.add('border-gray-200');
                        });
                        document.querySelectorAll('.truck-check').forEach(check => {
                            check.classList.add('opacity-0');
                        });
                        
                        // Highlight selected truck card
                        const card = this.closest('.truck-option').querySelector('.truck-selection-card');
                        const check = this.closest('.truck-option').querySelector('.truck-check');
                        card.classList.remove('border-gray-200');
                        card.classList.add('border-green-500', 'bg-green-50');
                        check.classList.remove('opacity-0');
                    }
                });
            });
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('scheduleModal').classList.contains('hidden')) {
                closeScheduleModal();
            }
        });

        // Close modal on backdrop click
        document.getElementById('scheduleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeScheduleModal();
            }
        });
    </script>
</body>
</html>