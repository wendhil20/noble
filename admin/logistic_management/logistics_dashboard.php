<?php
// logistics_dashboard.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_scheduled_items':
            $date = $_POST['date'];
            $sql = "SELECT ds.*, oi.product_name, oi.quantity, oi.price, oi.tracking_status, 
                           o.customer_name, o.address, o.mobile, o.id as order_id
                    FROM delivery_schedules ds
                    JOIN order_items oi ON ds.item_id = oi.id
                    JOIN orders o ON ds.order_id = o.id
                    WHERE ds.delivery_date = ?
                    ORDER BY ds.delivery_time";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($result);
            exit();
            
        case 'get_available_trucks':
            $date = $_POST['date'];
            $dayOfWeek = strtolower(date('l', strtotime($date)));
            
            $sql = "SELECT * FROM vehicle_list 
                    WHERE status = 'active' 
                    AND (unavailable_days IS NULL 
                         OR unavailable_days = '' 
                         OR FIND_IN_SET(?, unavailable_days) = 0)
                    ORDER BY plate_number";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $dayOfWeek);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($result);
            exit();
            
        case 'assign_truck':
            $delivery_id = $_POST['delivery_id'];
            $plate_number = $_POST['plate_number'];
            $delivery_type = $_POST['delivery_type']; // 'company' or 'third_party'
            
            try {
                $conn->begin_transaction();
                
                // Update delivery schedule with truck assignment
                $sql = "UPDATE delivery_schedules SET 
                        assigned_truck = ?, 
                        delivery_type = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $plate_number, $delivery_type, $delivery_id);
                $stmt->execute();
                
                // Update order item status to out_for_delivery
                $sql = "UPDATE order_items oi
                        JOIN delivery_schedules ds ON oi.id = ds.item_id
                        SET oi.tracking_status = 'out_for_delivery'
                        WHERE ds.id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $delivery_id);
                $stmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit();
            
        case 'upload_proof':
            $delivery_id = $_POST['delivery_id'];
            
            if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/delivery_proof/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
                $file_name = 'delivery_' . $delivery_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $file_path)) {
                    try {
                        $conn->begin_transaction();
                        
                        // Update delivery schedule with proof
                        $sql = "UPDATE delivery_schedules SET 
                                delivery_proof = ?, 
                                delivered_at = NOW(),
                                updated_at = NOW()
                                WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $file_name, $delivery_id);
                        $stmt->execute();
                        
                        // Update order item status to delivered
                        $sql = "UPDATE order_items oi
                                JOIN delivery_schedules ds ON oi.id = ds.item_id
                                SET oi.tracking_status = 'delivered'
                                WHERE ds.id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $delivery_id);
                        $stmt->execute();
                        
                        $conn->commit();
                        echo json_encode(['success' => true]);
                    } catch (Exception $e) {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'No file uploaded']);
            }
            exit();
            
        case 'move_to_next_day':
            $delivery_id = $_POST['delivery_id'];
            $new_date = $_POST['new_date'];
            
            try {
                $conn->begin_transaction();
                
                // Update delivery schedule - clear truck assignment and set new date
                $sql = "UPDATE delivery_schedules SET 
                        delivery_date = ?,
                        assigned_truck = NULL,
                        delivery_type = NULL,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $new_date, $delivery_id);
                $stmt->execute();
                
                // Update order item status back to ready_for_pickup
                $sql = "UPDATE order_items oi
                        JOIN delivery_schedules ds ON oi.id = ds.item_id
                        SET oi.tracking_status = 'ready_for_pickup'
                        WHERE ds.id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $delivery_id);
                $stmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit();
            
        case 'get_delivery_counts':
            // Get delivery counts for the next 60 days for calendar
            $sql = "SELECT DATE(ds.delivery_date) as date, COUNT(*) as count
                    FROM delivery_schedules ds
                    WHERE ds.delivery_date >= CURDATE() AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                    GROUP BY DATE(ds.delivery_date)";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Convert to associative array for easier lookup
            $deliveryCountsByDate = [];
            foreach ($result as $data) {
                $deliveryCountsByDate[$data['date']] = $data['count'];
            }
            
            echo json_encode($deliveryCountsByDate);
            exit();
    }
}

// Get delivery statistics
$statsSql = "SELECT 
                COUNT(*) as total_scheduled,
                COUNT(CASE WHEN ds.delivery_date = CURDATE() THEN 1 END) as today_deliveries,
                COUNT(CASE WHEN oi.tracking_status = 'out_for_delivery' THEN 1 END) as out_for_delivery,
                COUNT(CASE WHEN oi.tracking_status = 'delivered' THEN 1 END) as completed_today
             FROM delivery_schedules ds
             JOIN order_items oi ON ds.item_id = oi.id
             WHERE ds.delivery_date >= CURDATE() AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$stats = $conn->query($statsSql)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Dashboard</title>
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
            position: relative;
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
        .calendar-day.today {
            background-color: #e8f5e8;
            border: 2px solid #4caf50;
        }
        .delivery-indicator {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #ff9800;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4 mb-4">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 rounded-xl shadow-lg">
                    <i class="fas fa-truck text-white text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Logistics Dashboard</h1>
                    <p class="text-gray-600">Manage deliveries and track logistics operations</p>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Total Scheduled</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_scheduled']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-lg">
                            <i class="fas fa-truck text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Today's Deliveries</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['today_deliveries']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-lg">
                            <i class="fas fa-route text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Out for Delivery</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['out_for_delivery']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-lg">
                            <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Completed Today</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['completed_today']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Calendar Section -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
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
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-100 border-2 border-green-500 rounded mr-2"></div>
                            <span>Today</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-orange-100 border-2 border-orange-500 rounded mr-2"></div>
                            <span>Has deliveries</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-blue-600 rounded mr-2"></div>
                            <span>Selected date</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Items Section -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-list text-purple-600 mr-3"></i>
                                Scheduled Deliveries
                                <span id="selected-date-display" class="ml-3 text-sm text-gray-500"></span>
                            </h3>
                            <div class="flex space-x-2">
                                <button id="refresh-items" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    <i class="fas fa-refresh mr-2"></i>Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="delivery-items-container" class="max-h-[800px] overflow-y-auto">
                        <div class="p-8 text-center">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-calendar-day text-6xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-600 mb-2">Select a Date</h4>
                            <p class="text-gray-500">Click on a calendar date to view scheduled deliveries</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Truck Modal -->
    <div id="assignTruckModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full relative">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Assign Truck</h3>
                        <button id="closeTruckModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form id="assignTruckForm">
                        <input type="hidden" id="assignDeliveryId" name="delivery_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Type</label>
                            <select id="deliveryType" name="delivery_type" class="w-full p-3 border border-gray-300 rounded-lg" required>
                                <option value="">Select delivery type</option>
                                <option value="company">Company Delivery</option>
                                <option value="third_party">Third Party (Lalamove, etc.)</option>
                            </select>
                        </div>
                        
                        <div class="mb-6" id="truckSelectDiv">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Truck</label>
                            <select id="truckSelect" name="plate_number" class="w-full p-3 border border-gray-300 rounded-lg">
                                <option value="">Select a truck</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" id="cancelAssign" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Assign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Proof Modal -->
    <div id="uploadProofModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full relative">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Upload Delivery Proof</h3>
                        <button id="closeProofModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form id="uploadProofForm" enctype="multipart/form-data">
                        <input type="hidden" id="proofDeliveryId" name="delivery_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Upload Photo</label>
                            <input type="file" id="proofImage" name="proof_image" accept="image/*" class="w-full p-3 border border-gray-300 rounded-lg" required>
                            <p class="text-sm text-gray-500 mt-1">Upload a photo as proof of delivery</p>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" id="cancelProof" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentDate = new Date();
        let selectedDate = null;
        let deliveryCounts = {};
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            loadDeliveryCounts();
            
            // Event listeners for modals
            document.getElementById('closeTruckModal').addEventListener('click', closeTruckModal);
            document.getElementById('cancelAssign').addEventListener('click', closeTruckModal);
            document.getElementById('closeProofModal').addEventListener('click', closeProofModal);
            document.getElementById('cancelProof').addEventListener('click', closeProofModal);
            
            // Form submissions
            document.getElementById('assignTruckForm').addEventListener('submit', handleTruckAssignment);
            document.getElementById('uploadProofForm').addEventListener('submit', handleProofUpload);
            
            // Delivery type change handler
            document.getElementById('deliveryType').addEventListener('change', function() {
                const truckDiv = document.getElementById('truckSelectDiv');
                const truckSelect = document.getElementById('truckSelect');
                
                if (this.value === 'third_party') {
                    truckDiv.style.display = 'none';
                    truckSelect.required = false;
                } else if (this.value === 'company') {
                    truckDiv.style.display = 'block';
                    truckSelect.required = true;
                    // Load available trucks for selected date
                    if (selectedDate) {
                        loadAvailableTrucks(selectedDate);
                    }
                } else {
                    truckDiv.style.display = 'block';
                    truckSelect.required = false;
                }
            });
            
            // Refresh button
            document.getElementById('refresh-items').addEventListener('click', function() {
                if (selectedDate) {
                    loadDeliveryItems(selectedDate);
                }
            });
        });
        
        function loadDeliveryCounts() {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_delivery_counts'
            })
            .then(response => response.json())
            .then(data => {
                deliveryCounts = data;
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            })
            .catch(error => console.error('Error:', error));
        }
        
        function loadAvailableTrucks(date) {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_available_trucks&date=${encodeURIComponent(date)}`
            })
            .then(response => response.json())
            .then(data => {
                const truckSelect = document.getElementById('truckSelect');
                truckSelect.innerHTML = '<option value="">Select a truck</option>';
                
                data.forEach(truck => {
                    const option = document.createElement('option');
                    option.value = truck.plate_number;
                    option.textContent = `${truck.plate_number} - ${truck.truck_type}`;
                    truckSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading trucks:', error);
                showAlert('Error loading available trucks', 'error');
            });
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
        
        function generateCalendar(year, month) {
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();
            
            const calendarGrid = document.getElementById('calendar-grid');
            calendarGrid.innerHTML = '';
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Add empty cells for days before the first day of the month
            for (let i = 0; i < startingDayOfWeek; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'p-3';
                calendarGrid.appendChild(emptyDay);
            }
            
            // Add days of the month
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dateString = year + '-' + 
                    String(month + 1).padStart(2, '0') + '-' + 
                    String(day).padStart(2, '0');
                    
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day p-3 text-center rounded-lg border border-gray-200 bg-white';
                dayElement.textContent = day;
                dayElement.dataset.date = dateString;
                
                // Check if today
                const currentDateOnly = new Date(year, month, day);
                currentDateOnly.setHours(0, 0, 0, 0);
                
                if (currentDateOnly.getTime() === today.getTime()) {
                    dayElement.classList.add('today');
                }
                
                // Add delivery indicator if has deliveries
                if (deliveryCounts[dateString]) {
                    dayElement.classList.add('has-deliveries');
                    const indicator = document.createElement('div');
                    indicator.className = 'delivery-indicator';
                    dayElement.appendChild(indicator);
                }
                
                // Add click event
                dayElement.addEventListener('click', function() {
                    selectDate(dateString, dayElement);
                });
                
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
            
            // Update display
            document.getElementById('selected-date-display').textContent = `for ${formatDisplayDate(dateString)}`;
            
            // Load delivery items for selected date
            loadDeliveryItems(dateString);
        }
        
        function loadDeliveryItems(date) {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_scheduled_items&date=${encodeURIComponent(date)}`
            })
            .then(response => response.json())
            .then(data => {
                displayDeliveryItems(data);
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error loading delivery items', 'error');
            });
        }
        
        function displayDeliveryItems(items) {
            const container = document.getElementById('delivery-items-container');
            
            if (items.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-day text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No Deliveries Scheduled</h4>
                        <p class="text-gray-500">No deliveries scheduled for ${formatDisplayDate(selectedDate)}</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="p-4 space-y-4">';
            
            items.forEach(item => {
                const statusColor = getStatusColor(item.tracking_status);
                const canAssign = !item.assigned_truck && item.tracking_status !== 'delivered';
                const canUploadProof = item.assigned_truck && item.tracking_status === 'out_for_delivery';
                
                html += `
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h4 class="font-semibold text-lg text-gray-900">${escapeHtml(item.product_name)}</h4>
                                <p class="text-sm text-gray-600">Order #${item.order_id}</p>
                                <p class="text-sm text-blue-600 font-medium">
                                    <i class="fas fa-clock mr-1"></i>
                                    ${formatTime(item.delivery_time)}
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-medium ${statusColor}">
                                ${item.tracking_status.replace('_', ' ').toUpperCase()}
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                            <div>
                                <span class="font-medium">Quantity:</span> ${item.quantity}
                            </div>
                            <div>
                                <span class="font-medium">Price:</span> ₱${parseFloat(item.price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-3 mb-3">
                            <p class="text-gray-700 font-medium mb-1">
                                <i class="fas fa-user mr-2 text-gray-500"></i>
                                ${escapeHtml(item.customer_name)}
                            </p>
                            <p class="text-gray-600 text-sm">
                                <i class="fas fa-map-marker-alt mr-2 text-gray-500"></i>
                                ${escapeHtml(item.address)}
                            </p>
                            <p class="text-gray-600 text-sm">
                                <i class="fas fa-phone mr-2 text-gray-500"></i>
                                ${item.mobile || 'No phone number'}
                            </p>
                            ${item.delivery_notes ? `
                                <p class="text-gray-600 text-sm mt-2 bg-yellow-50 p-2 rounded">
                                    <i class="fas fa-sticky-note mr-2 text-yellow-600"></i>
                                    ${escapeHtml(item.delivery_notes)}
                                </p>
                            ` : ''}
                            ${item.assigned_truck ? `
                                <p class="text-gray-600 text-sm mt-2 bg-blue-50 p-2 rounded">
                                    <i class="fas fa-truck mr-2 text-blue-600"></i>
                                    Assigned to: ${escapeHtml(item.assigned_truck)}
                                    ${item.delivery_type ? `(${item.delivery_type})` : ''}
                                </p>
                            ` : ''}
                        </div>
                        
                        <div class="flex justify-end space-x-2">
                            ${canAssign ? `
                                <button onclick="openAssignTruckModal(${item.id})" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                    <i class="fas fa-truck mr-1"></i>
                                    Assign Truck
                                </button>
                            ` : ''}
                            
                            ${canUploadProof ? `
                                <button onclick="openUploadProofModal(${item.id})" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                    <i class="fas fa-camera mr-1"></i>
                                    Upload Proof
                                </button>
                            ` : ''}
                            
                            ${item.tracking_status === 'out_for_delivery' ? `
                                <button onclick="moveToNextDay(${item.id})" 
                                        class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 text-sm">
                                    <i class="fas fa-forward mr-1"></i>
                                    Move to Tomorrow
                                </button>
                            ` : ''}
                            
                            ${item.delivery_proof ? `
                                <button onclick="viewProof('${item.delivery_proof}')" 
                                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                                    <i class="fas fa-eye mr-1"></i>
                                    View Proof
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function getStatusColor(status) {
            const colors = {
                'ready_for_pickup': 'bg-blue-100 text-blue-800',
                'in_local_warehouse': 'bg-yellow-100 text-yellow-800',
                'out_for_delivery': 'bg-orange-100 text-orange-800',
                'delivered': 'bg-green-100 text-green-800',
                'pending': 'bg-gray-100 text-gray-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        }
        
        function openAssignTruckModal(deliveryId) {
            document.getElementById('assignDeliveryId').value = deliveryId;
            document.getElementById('assignTruckModal').classList.remove('hidden');
        }
        
        function closeTruckModal() {
            document.getElementById('assignTruckModal').classList.add('hidden');
            document.getElementById('assignTruckForm').reset();
        }
        
        function openUploadProofModal(deliveryId) {
            document.getElementById('proofDeliveryId').value = deliveryId;
            document.getElementById('uploadProofModal').classList.remove('hidden');
        }
        
        function closeProofModal() {
            document.getElementById('uploadProofModal').classList.add('hidden');
            document.getElementById('uploadProofForm').reset();
        }
        
        function handleTruckAssignment(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'assign_truck');
            
            fetch('logistics_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Truck assigned successfully!', 'success');
                    closeTruckModal();
                    if (selectedDate) {
                        loadDeliveryItems(selectedDate);
                    }
                } else {
                    showAlert('Error assigning truck: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error assigning truck', 'error');
            });
        }
        
        function handleProofUpload(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'upload_proof');
            
            fetch('logistics_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Delivery proof uploaded successfully!', 'success');
                    closeProofModal();
                    if (selectedDate) {
                        loadDeliveryItems(selectedDate);
                    }
                } else {
                    showAlert('Error uploading proof: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error uploading proof', 'error');
            });
        }
        
        function moveToNextDay(deliveryId) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tomorrowString = tomorrow.getFullYear() + '-' + 
                String(tomorrow.getMonth() + 1).padStart(2, '0') + '-' + 
                String(tomorrow.getDate()).padStart(2, '0');
            
            if (confirm('Move this delivery to tomorrow? The truck assignment will be cleared and need to be reassigned.')) {
                fetch('logistics_dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=move_to_next_day&delivery_id=${deliveryId}&new_date=${tomorrowString}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Delivery moved to tomorrow! Truck assignment cleared.', 'success');
                        if (selectedDate) {
                            loadDeliveryItems(selectedDate);
                        }
                        // Refresh delivery counts to update calendar
                        loadDeliveryCounts();
                    } else {
                        showAlert('Error moving delivery: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error moving delivery', 'error');
                });
            }
        }
        
        function viewProof(proofFileName) {
            const proofUrl = '../../uploads/delivery_proof/' + proofFileName;
            window.open(proofUrl, '_blank');
        }
        
        function updateCalendarHeader() {
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            document.getElementById('currentMonth').textContent = 
                monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
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
            if (!timeString) return 'Not specified';
            const time = new Date('2000-01-01 ' + timeString);
            return time.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800';
            const icon = type === 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-triangle text-red-600';
            
            alertDiv.className = `fixed top-4 right-4 z-50 border-l-4 rounded-lg p-4 shadow-lg ${bgColor}`;
            alertDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} mr-3"></i>
                    <span class="font-medium">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>