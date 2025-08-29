<?php
// delivery_schedule_api.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role([ 'superadmin', 'sales', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Handle errors gracefully
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display errors for JSON responses

$action = $_GET['action'] ?? '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    if ($input) {
        $postData = json_decode($input, true);
        if ($postData && isset($postData['action'])) {
            $action = $postData['action'];
        }
    }
}

try {
    switch ($action) {
        case 'get_calendar_data':
            getCalendarData($conn);
            break;
        case 'get_available_vehicles':
            getAvailableVehicles($conn);
            break;
        case 'get_available_drivers':
            getAvailableDrivers($conn);
            break;
        case 'save_schedule':
            saveSchedule($conn);
            break;
        case 'get_schedule_details':
            getScheduleDetails($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    error_log("Delivery Schedule API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function createDeliverySchedulesTable($conn) {
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS `delivery_schedules` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `order_item_id` int(11) NOT NULL,
            `delivery_date` date NOT NULL,
            `delivery_type` enum('company_vehicle', 'third_party', 'lalamove') NOT NULL DEFAULT 'company_vehicle',
            `vehicle_id` int(11) DEFAULT NULL,
            `driver_id` int(11) DEFAULT NULL,
            `third_party_details` text DEFAULT NULL,
            `delivery_time_slot` enum('morning', 'afternoon', 'evening', 'full_day') DEFAULT 'full_day',
            `status` enum('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
            `notes` text DEFAULT NULL,
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_delivery_date` (`delivery_date`),
            KEY `idx_vehicle_date` (`vehicle_id`, `delivery_date`),
            KEY `idx_driver_date` (`driver_id`, `delivery_date`),
            KEY `idx_order_item` (`order_item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    if (!$conn->query($createTableSql)) {
        throw new Exception('Failed to create delivery_schedules table: ' . $conn->error);
    }
}

function getCalendarData($conn) {
    $year = (int)($_GET['year'] ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('m'));
    
    // Check and create table if needed
    $tableCheck = $conn->query("SHOW TABLES LIKE 'delivery_schedules'");
    if ($tableCheck->num_rows == 0) {
        createDeliverySchedulesTable($conn);
    }
    
    // Get all scheduled deliveries for the month
    $sql = "
        SELECT 
            ds.*,
            CONCAT(COALESCE(vl.make, ''), ' ', COALESCE(vl.model, ''), 
                   CASE WHEN vl.plate_number IS NOT NULL 
                        THEN CONCAT(' (', vl.plate_number, ')') 
                        ELSE '' END) as vehicle_info,
            CONCAT(COALESCE(dl.first_name, ''), ' ', COALESCE(dl.last_name, '')) as driver_name,
            oi.product_name,
            o.customer_name
        FROM delivery_schedules ds
        LEFT JOIN vehicle_list vl ON ds.vehicle_id = vl.id
        LEFT JOIN driver_list dl ON ds.driver_id = dl.id
        LEFT JOIN order_items oi ON ds.order_item_id = oi.id
        LEFT JOIN orders o ON ds.order_id = o.id
        WHERE YEAR(ds.delivery_date) = ? AND MONTH(ds.delivery_date) = ?
        AND ds.status != 'cancelled'
        ORDER BY ds.delivery_date, ds.delivery_time_slot
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Group by date
    $calendarData = [];
    foreach ($schedules as $schedule) {
        $date = $schedule['delivery_date'];
        if (!isset($calendarData[$date])) {
            $calendarData[$date] = [];
        }
        $calendarData[$date][] = $schedule;
    }
    
    echo json_encode($calendarData);
}

function getAvailableVehicles($conn) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $dayOfWeek = strtolower(date('l', strtotime($date)));
    
    // Check if vehicle_list table exists
    $vehicleTableCheck = $conn->query("SHOW TABLES LIKE 'vehicle_list'");
    if ($vehicleTableCheck->num_rows == 0) {
        echo json_encode([]);
        return;
    }
    
    // Check if delivery_schedules table exists
    $scheduleTableCheck = $conn->query("SHOW TABLES LIKE 'delivery_schedules'");
    if ($scheduleTableCheck->num_rows == 0) {
        createDeliverySchedulesTable($conn);
    }
    
    $sql = "
        SELECT 
            vl.*,
            (SELECT COUNT(*) FROM delivery_schedules ds 
             WHERE ds.vehicle_id = vl.id AND ds.delivery_date = ? AND ds.status != 'cancelled') as scheduled_count
        FROM vehicle_list vl
        WHERE vl.status = 'active'
        AND (vl.unavailable_days IS NULL OR vl.unavailable_days NOT LIKE CONCAT('%', ?, '%'))
        ORDER BY vl.make, vl.model
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $date, $dayOfWeek);
    $stmt->execute();
    $vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Mark vehicles as busy if they have multiple schedules
    foreach ($vehicles as &$vehicle) {
        $vehicle['is_busy'] = $vehicle['scheduled_count'] >= 2;
        $vehicle['availability_status'] = $vehicle['is_busy'] ? 'busy' : 'available';
    }
    
    echo json_encode($vehicles);
}

function getAvailableDrivers($conn) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $vehicle_id = $_GET['vehicle_id'] ?? null;
    
    // Check if driver_list table exists
    $driverTableCheck = $conn->query("SHOW TABLES LIKE 'driver_list'");
    if ($driverTableCheck->num_rows == 0) {
        echo json_encode([]);
        return;
    }
    
    // Check if delivery_schedules table exists
    $scheduleTableCheck = $conn->query("SHOW TABLES LIKE 'delivery_schedules'");
    if ($scheduleTableCheck->num_rows == 0) {
        createDeliverySchedulesTable($conn);
    }
    
    $sql = "
        SELECT 
            dl.*,
            (SELECT COUNT(*) FROM delivery_schedules ds 
             WHERE ds.driver_id = dl.id AND ds.delivery_date = ? AND ds.status != 'cancelled') as scheduled_count,
            (SELECT GROUP_CONCAT(CONCAT(COALESCE(o.customer_name, 'Unknown'), ' (', TIME(ds.created_at), ')') SEPARATOR ', ') 
             FROM delivery_schedules ds 
             LEFT JOIN orders o ON ds.order_id = o.id 
             WHERE ds.driver_id = dl.id AND ds.delivery_date = ? AND ds.status != 'cancelled') as existing_schedules
        FROM driver_list dl
        WHERE dl.license_expiration_date > ?
        ORDER BY dl.first_name, dl.last_name
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sss", $date, $date, $date);
    $stmt->execute();
    $drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($drivers as &$driver) {
        $driver['is_busy'] = $driver['scheduled_count'] >= 3;
        $driver['availability_status'] = $driver['is_busy'] ? 'busy' : 'available';
    }
    
    echo json_encode($drivers);
}

function saveSchedule($conn) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    $order_id = (int)($data['order_id'] ?? 0);
    $order_item_id = (int)($data['order_item_id'] ?? 0);
    $delivery_date = $data['delivery_date'] ?? '';
    $delivery_type = $data['delivery_type'] ?? '';
    $vehicle_id = isset($data['vehicle_id']) && $data['vehicle_id'] ? (int)$data['vehicle_id'] : null;
    $driver_id = isset($data['driver_id']) && $data['driver_id'] ? (int)$data['driver_id'] : null;
    $delivery_time_slot = $data['delivery_time_slot'] ?? 'full_day';
    $third_party_details = $data['third_party_details'] ?? null;
    $notes = $data['notes'] ?? null;
    $user_id = $_SESSION['noble_user']['id'];
    
    // Validate required fields
    if (!$order_id || !$order_item_id || !$delivery_date || !$delivery_type) {
        throw new Exception('Missing required fields');
    }
    
    // If company vehicle, require vehicle selection
    if ($delivery_type === 'company_vehicle' && !$vehicle_id) {
        throw new Exception('Vehicle is required for company vehicle delivery');
    }
    
    // Check and create table if needed
    $tableCheck = $conn->query("SHOW TABLES LIKE 'delivery_schedules'");
    if ($tableCheck->num_rows == 0) {
        createDeliverySchedulesTable($conn);
    }
    
    // Check if already scheduled
    $checkSql = "SELECT id FROM delivery_schedules WHERE order_item_id = ? AND status != 'cancelled'";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $order_item_id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        // Update existing schedule
        $sql = "
            UPDATE delivery_schedules SET 
                delivery_date = ?, 
                delivery_type = ?, 
                vehicle_id = ?, 
                driver_id = ?, 
                delivery_time_slot = ?, 
                third_party_details = ?, 
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssiisssi", $delivery_date, $delivery_type, $vehicle_id, $driver_id, $delivery_time_slot, $third_party_details, $notes, $existing['id']);
    } else {
        // Create new schedule
        $sql = "
            INSERT INTO delivery_schedules 
            (order_id, order_item_id, delivery_date, delivery_type, vehicle_id, driver_id, delivery_time_slot, third_party_details, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("iissiisssi", $order_id, $order_item_id, $delivery_date, $delivery_type, $vehicle_id, $driver_id, $delivery_time_slot, $third_party_details, $notes, $user_id);
    }
    
    if ($stmt->execute()) {
        $schedule_id = $existing['id'] ?? $conn->insert_id;
        
        // Update order item status if needed
        if ($delivery_type === 'company_vehicle') {
            $columnCheck = $conn->query("SHOW COLUMNS FROM order_items LIKE 'tracking_status'");
            if ($columnCheck->num_rows > 0) {
                $updateStatusSql = "UPDATE order_items SET tracking_status = 'ready_for_pickup' WHERE id = ?";
                $updateStmt = $conn->prepare($updateStatusSql);
                if ($updateStmt) {
                    $updateStmt->bind_param("i", $order_item_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'schedule_id' => $schedule_id,
            'message' => $existing ? 'Schedule updated successfully' : 'Schedule created successfully'
        ]);
    } else {
        throw new Exception('Failed to save schedule: ' . $stmt->error);
    }
    
    $stmt->close();
}

function getScheduleDetails($conn) {
    $item_id = (int)($_GET['item_id'] ?? 0);
    
    // Check if delivery_schedules table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'delivery_schedules'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(null);
        return;
    }
    
    $sql = "
        SELECT 
            ds.*,
            vl.make, vl.model, vl.plate_number,
            dl.first_name, dl.last_name, dl.contact_number
        FROM delivery_schedules ds
        LEFT JOIN vehicle_list vl ON ds.vehicle_id = vl.id
        LEFT JOIN driver_list dl ON ds.driver_id = dl.id
        WHERE ds.order_item_id = ? AND ds.status != 'cancelled'
        ORDER BY ds.created_at DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $schedule = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    echo json_encode($schedule ?: null);
}
?>