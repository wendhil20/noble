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

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'get_scheduled_items':
            $date = $_POST['date'];
            $sql = "SELECT ds.*, oi.product_name, oi.quantity, oi.price, oi.tracking_status, 
                           o.customer_name, o.address, o.mobile, o.id as order_id,
                           ts.id as truck_schedule_id, ts.truck_id as scheduled_truck_id
                    FROM delivery_schedules ds
                    JOIN order_items oi ON ds.item_id = oi.id
                    JOIN orders o ON ds.order_id = o.id
                    LEFT JOIN truck_schedules ts ON ds.truck_schedule_id = ts.id
                    WHERE ds.delivery_date = ?
                    ORDER BY ds.delivery_time";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($result);
            exit();

        case 'get_truck_schedules':
            $date = $_POST['date'];
            try {
                // First check what columns exist in vehicle_list
                $checkSql = "SHOW COLUMNS FROM vehicle_list";
                $checkResult = $conn->query($checkSql);
                $columns = [];
                while ($row = $checkResult->fetch_assoc()) {
                    $columns[] = $row['Field'];
                }
                
                // Build query based on available columns
                $selectColumns = "ts.*, vl.truck_type";
                if (in_array('capacity', $columns)) {
                    $selectColumns .= ", vl.capacity";
                } else {
                    $selectColumns .= ", NULL as capacity";
                }
                
                $sql = "SELECT {$selectColumns},
                               COUNT(ds.id) as assigned_deliveries
                        FROM truck_schedules ts
                        JOIN vehicle_list vl ON ts.truck_id = vl.plate_number
                        LEFT JOIN delivery_schedules ds ON ts.id = ds.truck_schedule_id
                        WHERE ts.scheduled_date = ?
                        GROUP BY ts.id
                        ORDER BY ts.truck_id";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("s", $date);
                $stmt->execute();
                $result = $stmt->get_result();
                if (!$result) {
                    throw new Exception("Query failed: " . $stmt->error);
                }
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit();

        case 'schedule_truck':
            $truck_id = $_POST['truck_id'];
            $date = $_POST['date'];
            $max_capacity = $_POST['max_capacity'] ?? null;
            $notes = $_POST['notes'] ?? '';
            
            try {
                $sql = "INSERT INTO truck_schedules (truck_id, scheduled_date, max_capacity, notes) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        max_capacity = VALUES(max_capacity),
                        notes = VALUES(notes),
                        updated_at = NOW()";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssis", $truck_id, $date, $max_capacity, $notes);
                $stmt->execute();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit();

        case 'get_available_trucks':
            $date = $_POST['date'];
            $dayOfWeek = strtolower(date('l', strtotime($date)));
            
            $sql = "SELECT vl.* FROM vehicle_list vl
                    WHERE vl.status = 'active' 
                    AND (vl.unavailable_days IS NULL 
                         OR vl.unavailable_days = '' 
                         OR FIND_IN_SET(?, vl.unavailable_days) = 0)
                    AND vl.plate_number NOT IN (
                        SELECT truck_id FROM truck_schedules 
                        WHERE scheduled_date = ?
                    )
                    ORDER BY vl.plate_number";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $dayOfWeek, $date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($result);
            exit();

        case 'assign_to_truck':
            $delivery_id = $_POST['delivery_id'];
            $truck_schedule_id = $_POST['truck_schedule_id'];
            
            try {
                $conn->begin_transaction();
                
                // Get truck info
                $sql = "SELECT truck_id FROM truck_schedules WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $truck_schedule_id);
                $stmt->execute();
                $truck_info = $stmt->get_result()->fetch_assoc();
                
                if (!$truck_info) {
                    throw new Exception("Truck schedule not found");
                }
                
                // Update delivery schedule
                $sql = "UPDATE delivery_schedules SET 
                        truck_schedule_id = ?,
                        assigned_truck = ?,
                        delivery_type = 'company',
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isi", $truck_schedule_id, $truck_info['truck_id'], $delivery_id);
                $stmt->execute();
                
                // Update order item status
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

        case 'get_truck_deliveries':
            $truck_schedule_id = $_POST['truck_schedule_id'];
            $sql = "SELECT ds.*, oi.product_name, oi.quantity, oi.price, oi.tracking_status, 
                           o.customer_name, o.address, o.mobile, o.id as order_id
                    FROM delivery_schedules ds
                    JOIN order_items oi ON ds.item_id = oi.id
                    JOIN orders o ON ds.order_id = o.id
                    WHERE ds.truck_schedule_id = ?
                    ORDER BY ds.delivery_time";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $truck_schedule_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($result);
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
                        
                        $sql = "UPDATE delivery_schedules SET 
                                delivery_proof = ?, 
                                delivered_at = NOW(),
                                updated_at = NOW()
                                WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $file_name, $delivery_id);
                        $stmt->execute();
                        
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

        case 'remove_truck_schedule':
            $truck_schedule_id = $_POST['truck_schedule_id'];
            
            try {
                $conn->begin_transaction();
                
                // Reset delivery schedules back to unassigned
                $sql = "UPDATE delivery_schedules SET 
                        truck_schedule_id = NULL,
                        assigned_truck = NULL,
                        delivery_type = NULL
                        WHERE truck_schedule_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $truck_schedule_id);
                $stmt->execute();
                
                // Update order items back to ready_for_pickup
                $sql = "UPDATE order_items oi
                        JOIN delivery_schedules ds ON oi.id = ds.item_id
                        SET oi.tracking_status = 'ready_for_pickup'
                        WHERE ds.truck_schedule_id IS NULL AND ds.assigned_truck IS NULL";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                
                // Delete truck schedule
                $sql = "DELETE FROM truck_schedules WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $truck_schedule_id);
                $stmt->execute();

                $conn->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit();

        case 'get_delivery_counts':
            // Extended date range to include past dates for better visibility
            $sql = "SELECT DATE(ds.delivery_date) as date, COUNT(*) as count
                    FROM delivery_schedules ds
                    WHERE ds.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                    AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                    GROUP BY DATE(ds.delivery_date)";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $deliveryCountsByDate = [];
            foreach ($result as $data) {
                $deliveryCountsByDate[$data['date']] = $data['count'];
            }

            echo json_encode($deliveryCountsByDate);
            exit();

        case 'unassign_delivery':
            $delivery_id = $_POST['delivery_id'];
            
            try {
                $conn->begin_transaction();
                
                // Update delivery schedule to remove truck assignment
                $sql = "UPDATE delivery_schedules SET 
                        truck_schedule_id = NULL,
                        assigned_truck = NULL,
                        delivery_type = NULL,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $delivery_id);
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

        case 'reassign_delivery':
            $delivery_id = $_POST['delivery_id'];
            $new_truck_schedule_id = $_POST['new_truck_schedule_id'];
            
            try {
                $conn->begin_transaction();
                
                // Get new truck info
                $sql = "SELECT truck_id FROM truck_schedules WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $new_truck_schedule_id);
                $stmt->execute();
                $truck_info = $stmt->get_result()->fetch_assoc();
                
                if (!$truck_info) {
                    throw new Exception("New truck schedule not found");
                }
                
                // Update delivery schedule with new truck
                $sql = "UPDATE delivery_schedules SET 
                        truck_schedule_id = ?,
                        assigned_truck = ?,
                        delivery_type = 'company',
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isi", $new_truck_schedule_id, $truck_info['truck_id'], $delivery_id);
                $stmt->execute();
                
                // Update order item status
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

        case 'reschedule_delivery':
            $delivery_id = $_POST['delivery_id'];
            $new_date = $_POST['new_date'];
            $new_time = $_POST['new_time'] ?? null;
            
            try {
                $conn->begin_transaction();
                
                // First unassign from truck if assigned
                $sql = "UPDATE delivery_schedules SET 
                        truck_schedule_id = NULL,
                        assigned_truck = NULL,
                        delivery_type = NULL,
                        delivery_date = ?,
                        delivery_time = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $new_date, $new_time, $delivery_id);
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

        case 'get_available_trucks_for_reassign':
            $date = $_POST['date'];
            $current_truck_schedule_id = $_POST['current_truck_schedule_id'] ?? null;
            
            $sql = "SELECT ts.*, vl.truck_type, COUNT(ds.id) as assigned_deliveries
                    FROM truck_schedules ts
                    JOIN vehicle_list vl ON ts.truck_id = vl.plate_number
                    LEFT JOIN delivery_schedules ds ON ts.id = ds.truck_schedule_id
                    WHERE ts.scheduled_date = ?";
            
            if ($current_truck_schedule_id) {
                $sql .= " AND ts.id != ?";
            }
            
            $sql .= " GROUP BY ts.id ORDER BY ts.truck_id";
            
            $stmt = $conn->prepare($sql);
            if ($current_truck_schedule_id) {
                $stmt->bind_param("si", $date, $current_truck_schedule_id);
            } else {
                $stmt->bind_param("s", $date);
            }
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($result);
            exit();

        case 'get_overdue_deliveries':
            // Get undelivered items from past dates
            $sql = "SELECT DATE(ds.delivery_date) as date, COUNT(*) as count
                    FROM delivery_schedules ds
                    JOIN order_items oi ON ds.item_id = oi.id
                    WHERE ds.delivery_date < CURDATE() 
                    AND oi.tracking_status NOT IN ('delivered', 'cancelled')
                    GROUP BY DATE(ds.delivery_date)
                    ORDER BY ds.delivery_date DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($result);
            exit();
    }
}

// Get delivery statistics
$statsSql = "SELECT 
                COUNT(*) as total_scheduled,
                COUNT(CASE WHEN ds.delivery_date = CURDATE() THEN 1 END) as today_deliveries,
                COUNT(CASE WHEN oi.tracking_status = 'out_for_delivery' THEN 1 END) as out_for_delivery,
                COUNT(CASE WHEN oi.tracking_status = 'delivered' THEN 1 END) as completed_today,
                COUNT(CASE WHEN ds.delivery_date < CURDATE() AND oi.tracking_status NOT IN ('delivered', 'cancelled') THEN 1 END) as overdue_deliveries
             FROM delivery_schedules ds
             JOIN order_items oi ON ds.item_id = oi.id
             WHERE ds.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
             AND ds.delivery_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
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
        .calendar-day {
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            min-height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .calendar-day:hover {
            background-color: #dbeafe;
            border-color: #93c5fd;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .calendar-day.selected {
            background-color: #3b82f6 !important;
            color: white !important;
            border-color: #2563eb;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .calendar-day.has-deliveries {
            background-color: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
            font-weight: 600;
        }
        .calendar-day.has-deliveries:hover {
            background-color: #fde68a;
            border-color: #d97706;
        }
        .calendar-day.today {
            background-color: #dcfce7;
            border-color: #16a34a;
            color: #15803d;
            font-weight: 700;
            position: relative;
        }
        .calendar-day.today:before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border: 2px solid #16a34a;
            border-radius: 10px;
            z-index: -1;
        }
        .calendar-day.today:hover {
            background-color: #bbf7d0;
        }
        .calendar-day.past-date {
            opacity: 0.7;
            background-color: #f9fafb;
        }
        .calendar-day.past-date.has-deliveries {
            background-color: #fef3c7;
            border-color: #f59e0b;
        }
        .calendar-day.overdue {
            background-color: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
        .calendar-day.overdue:hover {
            background-color: #fecaca;
        }
        .delivery-indicator {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #f59e0b;
            border: 1px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .overdue-indicator {
            background-color: #dc2626 !important;
        }
        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .calendar-nav-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .calendar-nav-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        .calendar-weekday {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            padding: 0.5rem;
            text-align: center;
            background-color: #f9fafb;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }
        .truck-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .truck-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .truck-card.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .delivery-item {
            transition: all 0.2s ease;
        }
        .delivery-item.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }
        .delivery-item.overdue-item {
            border-color: #dc2626;
            background-color: #fee2e2;
        }
        .truck-dropzone {
            border: 2px dashed #e5e7eb;
            transition: all 0.2s ease;
        }
        .truck-dropzone.drag-over {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .legend-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border-radius: 6px;
            background-color: #f9fafb;
            margin-bottom: 0.25rem;
        }
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
            flex-shrink: 0;
        }
        .overdue-banner {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4 mb-4">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 rounded-xl shadow-lg">
                    <i class="fas fa-truck text-white text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Logistics Dashboard</h1>
                    <p class="text-gray-600">Manage truck schedules and track delivery operations</p>
                </div>
            </div>
            
            <!-- Overdue Banner -->
            <?php if ($stats['overdue_deliveries'] > 0): ?>
            <div class="overdue-banner">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-xl mr-3"></i>
                        <span class="font-bold">Alert: <?php echo $stats['overdue_deliveries']; ?> overdue deliveries need attention!</span>
                    </div>
                    <button id="view-overdue" class="bg-white bg-opacity-20 hover:bg-opacity-30 px-4 py-2 rounded-lg text-sm font-medium transition-all">
                        <i class="fas fa-eye mr-2"></i>View Overdue
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
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
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 <?php echo $stats['overdue_deliveries'] > 0 ? 'border-red-300 bg-red-50' : ''; ?>">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo $stats['overdue_deliveries']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Calendar Section -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="calendar-header">
                        <h3 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-calendar text-white mr-3"></i>
                            Delivery Calendar
                        </h3>
                        
                        <!-- Calendar Navigation -->
                        <div class="flex items-center justify-between mb-4">
                            <button type="button" id="prevMonth" class="calendar-nav-btn">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h4 id="currentMonth" class="text-xl font-bold"></h4>
                            <button type="button" id="nextMonth" class="calendar-nav-btn">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <!-- Calendar Grid -->
                        <div class="grid grid-cols-7 gap-2 mb-4">
                            <div class="calendar-weekday">Sun</div>
                            <div class="calendar-weekday">Mon</div>
                            <div class="calendar-weekday">Tue</div>
                            <div class="calendar-weekday">Wed</div>
                            <div class="calendar-weekday">Thu</div>
                            <div class="calendar-weekday">Fri</div>
                            <div class="calendar-weekday">Sat</div>
                        </div>
                        
                        <div id="calendar-grid" class="grid grid-cols-7 gap-2 mb-6"></div>
                        
                        <!-- Legend -->
                        <div class="space-y-2 text-sm">
                            <div class="legend-item">
                                <div class="legend-dot" style="background-color: #dcfce7; border: 2px solid #16a34a;"></div>
                                <span class="font-medium">Today</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot" style="background-color: #fef3c7; border: 2px solid #f59e0b;"></div>
                                <span class="font-medium">Has deliveries</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot" style="background-color: #fee2e2; border: 2px solid #dc2626;"></div>
                                <span class="font-medium">Overdue deliveries</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot" style="background-color: #3b82f6;"></div>
                                <span class="font-medium">Selected date</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot" style="background-color: #f9fafb; opacity: 0.7;"></div>
                                <span class="font-medium">Past dates (clickable)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Truck Scheduling Section -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-truck text-green-600 mr-3"></i>
                                Scheduled Trucks
                            </h3>
                            <button id="add-truck-btn" class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                <i class="fas fa-plus mr-1"></i>Schedule
                            </button>
                        </div>
                        <p id="truck-date-display" class="text-sm text-gray-500 mt-1"></p>
                    </div>
                    
                    <div id="truck-schedules-container" class="max-h-[600px] overflow-y-auto">
                        <div class="p-8 text-center">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-truck text-6xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-600 mb-2">Select a Date</h4>
                            <p class="text-gray-500">Click on a calendar date to view/schedule trucks</p>
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
                                <span id="delivery-section-title">Delivery Items</span>
                            </h3>
                            <div class="flex space-x-2">
                                <button id="view-all-items" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                                    <i class="fas fa-list mr-2"></i>All Items
                                </button>
                                <button id="refresh-items" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                    <i class="fas fa-refresh mr-1"></i>Refresh
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

    <!-- Schedule Truck Modal -->
    <div id="scheduleTruckModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full relative">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Schedule Truck</h3>
                        <button id="closeTruckScheduleModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form id="scheduleTruckForm">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Truck</label>
                            <select id="truckScheduleSelect" name="truck_id" class="w-full p-3 border border-gray-300 rounded-lg" required>
                                <option value="">Select a truck</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Capacity (optional)</label>
                            <input type="number" id="maxCapacity" name="max_capacity" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="Enter max deliveries">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                            <textarea id="truckNotes" name="notes" class="w-full p-3 border border-gray-300 rounded-lg" rows="3" placeholder="Add any notes for this truck schedule"></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" id="cancelSchedule" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Schedule</button>
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

    <!-- Reassign Delivery Modal -->
    <div id="reassignDeliveryModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full relative">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Reassign Delivery</h3>
                        <button id="closeReassignModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form id="reassignDeliveryForm">
                        <input type="hidden" id="reassignDeliveryId" name="delivery_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select New Truck</label>
                            <select id="reassignTruckSelect" name="new_truck_schedule_id" class="w-full p-3 border border-gray-300 rounded-lg" required>
                                <option value="">Select a truck</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" id="cancelReassign" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Reassign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Delivery Modal -->
    <div id="rescheduleDeliveryModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full relative">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Reschedule Delivery</h3>
                        <button id="closeRescheduleModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form id="rescheduleDeliveryForm">
                        <input type="hidden" id="rescheduleDeliveryId" name="delivery_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Date</label>
                            <input type="date" id="newDeliveryDate" name="new_date" class="w-full p-3 border border-gray-300 rounded-lg" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Time (optional)</label>
                            <input type="time" id="newDeliveryTime" name="new_time" class="w-full p-3 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" id="cancelReschedule" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                            <button type="submit" class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">Reschedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentDate = new Date();
        let selectedDate = null;
        let selectedTruckScheduleId = null;
        let deliveryCounts = {};
        let overdueCounts = {};
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            loadDeliveryCounts();
            loadOverdueDeliveries();
            initializeEventListeners();
        });
        
        function initializeEventListeners() {
            // Existing modal event listeners
            document.getElementById('closeTruckScheduleModal').addEventListener('click', closeScheduleTruckModal);
            document.getElementById('cancelSchedule').addEventListener('click', closeScheduleTruckModal);
            document.getElementById('closeProofModal').addEventListener('click', closeProofModal);
            document.getElementById('cancelProof').addEventListener('click', closeProofModal);
            
            // New modal event listeners
            document.getElementById('closeReassignModal').addEventListener('click', closeReassignModal);
            document.getElementById('cancelReassign').addEventListener('click', closeReassignModal);
            document.getElementById('closeRescheduleModal').addEventListener('click', closeRescheduleModal);
            document.getElementById('cancelReschedule').addEventListener('click', closeRescheduleModal);
            
            // Form submissions
            document.getElementById('scheduleTruckForm').addEventListener('submit', handleTruckScheduling);
            document.getElementById('uploadProofForm').addEventListener('submit', handleProofUpload);
            document.getElementById('reassignDeliveryForm').addEventListener('submit', handleReassignDelivery);
            document.getElementById('rescheduleDeliveryForm').addEventListener('submit', handleRescheduleDelivery);
            
            // Button listeners
            document.getElementById('add-truck-btn').addEventListener('click', openScheduleTruckModal);
            document.getElementById('view-all-items').addEventListener('click', viewAllItems);
            document.getElementById('refresh-items').addEventListener('click', function() {
                if (selectedTruckScheduleId) {
                    loadTruckDeliveries(selectedTruckScheduleId);
                } else if (selectedDate) {
                    loadDeliveryItems(selectedDate);
                }
            });

            // View overdue button
            const viewOverdueBtn = document.getElementById('view-overdue');
            if (viewOverdueBtn) {
                viewOverdueBtn.addEventListener('click', viewOverdueDeliveries);
            }
        }
        
        function loadDeliveryCounts() {
            fetch('logistics_dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=get_delivery_counts'
                })
                .then(response => response.json())
                .then(data => {
                    deliveryCounts = data;
                    generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
                })
                .catch(error => console.error('Error:', error));
        }
        
        function loadOverdueDeliveries() {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_overdue_deliveries'
            })
            .then(response => response.json())
            .then(data => {
                overdueCounts = {};
                data.forEach(item => {
                    overdueCounts[item.date] = item.count;
                });
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            })
            .catch(error => console.error('Error loading overdue deliveries:', error));
        }
        
        function viewOverdueDeliveries() {
            // Find the most recent date with overdue deliveries
            const overdueDates = Object.keys(overdueCounts);
            if (overdueDates.length === 0) return;
            
            // Sort dates and select the most recent one
            overdueDates.sort((a, b) => new Date(b) - new Date(a));
            const mostRecentOverdue = overdueDates[0];
            
            // Navigate to that date's month if needed
            const overdueDate = new Date(mostRecentOverdue);
            if (overdueDate.getMonth() !== currentDate.getMonth() || 
                overdueDate.getFullYear() !== currentDate.getFullYear()) {
                currentDate = new Date(overdueDate.getFullYear(), overdueDate.getMonth(), 1);
                updateCalendarHeader();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            }
            
            // Select that date
            setTimeout(() => {
                const dayElement = document.querySelector(`[data-date="${mostRecentOverdue}"]`);
                if (dayElement) {
                    selectDate(mostRecentOverdue, dayElement);
                }
            }, 100);
        }
        
        function loadAvailableTrucksForScheduling(date) {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_available_trucks&date=${encodeURIComponent(date)}`
            })
            .then(response => response.json())
            .then(data => {
                const truckSelect = document.getElementById('truckScheduleSelect');
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
                emptyDay.className = 'h-12';
                calendarGrid.appendChild(emptyDay);
            }

            // Add days of the month
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dateString = year + '-' +
                    String(month + 1).padStart(2, '0') + '-' +
                    String(day).padStart(2, '0');

                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day bg-white';
                dayElement.textContent = day;
                dayElement.dataset.date = dateString;

                // Check if today
                const currentDateOnly = new Date(year, month, day);
                currentDateOnly.setHours(0, 0, 0, 0);

                if (currentDateOnly.getTime() === today.getTime()) {
                    dayElement.classList.add('today');
                }
                
                // Check if past date
                if (currentDateOnly < today) {
                    dayElement.classList.add('past-date');
                }
                
                // Add delivery indicator if has deliveries
                if (deliveryCounts[dateString]) {
                    dayElement.classList.add('has-deliveries');
                    const indicator = document.createElement('div');
                    indicator.className = 'delivery-indicator';
                    dayElement.appendChild(indicator);
                }
                
                // Add overdue indicator if has overdue deliveries
                if (overdueCounts[dateString]) {
                    dayElement.classList.add('overdue');
                    const indicator = document.createElement('div');
                    indicator.className = 'delivery-indicator overdue-indicator';
                    dayElement.appendChild(indicator);
                }
                
                // Add click event for all dates (including past dates)
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
            selectedTruckScheduleId = null; // Clear truck selection
            
            // Update display
            const displayDate = formatDisplayDate(dateString);
            const selectedDateObj = new Date(dateString);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            let dateLabel = displayDate;
            if (selectedDateObj < today) {
                dateLabel += ' (Past Date)';
            }
            
            document.getElementById('truck-date-display').textContent = `for ${dateLabel}`;
            document.getElementById('delivery-section-title').textContent = `Delivery Items for ${dateLabel}`;
            
            // Load truck schedules and delivery items for selected date
            loadTruckSchedules(dateString);
            loadDeliveryItems(dateString);
        }
        
        function loadTruckSchedules(date) {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_truck_schedules&date=${encodeURIComponent(date)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Server error:', data.error);
                    showAlert('Error loading truck schedules: ' + data.error, 'error');
                } else {
                    displayTruckSchedules(data);
                }
            })
            .catch(error => {
                console.error('Network error:', error);
                showAlert('Error loading truck schedules: ' + error.message, 'error');
            });
        }
        
        function displayTruckSchedules(trucks) {
            const container = document.getElementById('truck-schedules-container');
            
            if (trucks.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-truck text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No Trucks Scheduled</h4>
                        <p class="text-gray-500">Click "Schedule" to add trucks for this date</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="p-4 space-y-4">';
            
            trucks.forEach(truck => {
                const statusColor = truck.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                
                html += `
                    <div class="truck-card border-2 border-gray-200 rounded-lg p-4 bg-white truck-dropzone" 
                         data-truck-schedule-id="${truck.id}"
                         onclick="selectTruck(${truck.id}, '${truck.truck_id}')"
                         ondrop="dropDeliveryItem(event, ${truck.id})"
                         ondragover="allowDrop(event)"
                         ondragenter="handleDragEnter(event)"
                         ondragleave="handleDragLeave(event)">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h4 class="font-bold text-lg text-gray-900">
                                    <i class="fas fa-truck mr-2 text-blue-600"></i>
                                    ${escapeHtml(truck.truck_id)}
                                </h4>
                                <p class="text-sm text-gray-600">${escapeHtml(truck.truck_type)}</p>
                                ${truck.capacity ? `<p class="text-sm text-blue-600">Capacity: ${truck.capacity}</p>` : ''}
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-medium ${statusColor}">
                                    ${truck.status.toUpperCase()}
                                </span>
                                <button onclick="removeTruckSchedule(event, ${truck.id})" 
                                        class="ml-2 text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center text-sm text-gray-600 mb-3">
                            <span>
                                <i class="fas fa-box mr-1"></i>
                                ${truck.assigned_deliveries} deliveries
                            </span>
                            ${truck.max_capacity ? `<span>Max: ${truck.max_capacity}</span>` : ''}
                        </div>
                        
                        ${truck.notes ? `
                            <div class="bg-yellow-50 p-2 rounded text-sm text-gray-700 mb-3">
                                <i class="fas fa-sticky-note mr-1 text-yellow-600"></i>
                                ${escapeHtml(truck.notes)}
                            </div>
                        ` : ''}
                        
                        <div class="text-center">
                            <button onclick="selectTruck(${truck.id}, '${truck.truck_id}')" 
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                <i class="fas fa-eye mr-1"></i>
                                View Deliveries
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function selectTruck(truckScheduleId, truckId) {
            // Remove previous truck selection
            document.querySelectorAll('.truck-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked truck
            const truckCard = document.querySelector(`[data-truck-schedule-id="${truckScheduleId}"]`);
            if (truckCard) {
                truckCard.classList.add('selected');
            }
            
            selectedTruckScheduleId = truckScheduleId;
            
            // Update delivery section title
            document.getElementById('delivery-section-title').textContent = `Deliveries for ${truckId}`;
            
            // Load deliveries for this truck
            loadTruckDeliveries(truckScheduleId);
        }
        
        function loadDeliveryItems(date) {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_scheduled_items&date=${encodeURIComponent(date)}`
            })
            .then(response => response.json())
            .then(data => {
                displayDeliveryItems(data, 'all');
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error loading delivery items', 'error');
            });
        }
        
        function loadTruckDeliveries(truckScheduleId) {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_truck_deliveries&truck_schedule_id=${encodeURIComponent(truckScheduleId)}`
            })
            .then(response => response.json())
            .then(data => {
                displayDeliveryItems(data, 'truck');
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error loading truck deliveries', 'error');
            });
        }
        
        function displayDeliveryItems(items, viewType) {
            const container = document.getElementById('delivery-items-container');

            if (items.length === 0) {
                const emptyMessage = viewType === 'truck' 
                    ? 'No deliveries assigned to this truck' 
                    : `No deliveries scheduled for ${formatDisplayDate(selectedDate)}`;
                    
                container.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-calendar-day text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-600 mb-2">No Deliveries</h4>
                        <p class="text-gray-500">${emptyMessage}</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="p-4 space-y-4">';
            
            // Check if selected date is in the past for overdue styling
            const selectedDateObj = new Date(selectedDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const isOverdueDate = selectedDateObj < today;
            
            items.forEach(item => {
                const statusColor = getStatusColor(item.tracking_status);
                const isAssigned = item.truck_schedule_id != null;
                const canAssignToTruck = !isAssigned && viewType === 'all';
                const canUploadProof = isAssigned && item.tracking_status === 'out_for_delivery';
                const canUnassign = isAssigned && item.tracking_status !== 'delivered';
                const canReschedule = item.tracking_status !== 'delivered';
                const isOverdue = isOverdueDate && item.tracking_status !== 'delivered';
                
                html += `
                    <div class="delivery-item border border-gray-200 rounded-lg p-4 bg-gray-50 ${canAssignToTruck ? 'cursor-move' : ''} ${isOverdue ? 'overdue-item' : ''}"
                         ${canAssignToTruck ? `draggable="true" ondragstart="dragStart(event, ${item.id})" ondragend="dragEnd(event)"` : ''}
                         data-delivery-id="${item.id}">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h4 class="font-semibold text-lg text-gray-900">${escapeHtml(item.product_name)}</h4>
                                <p class="text-sm text-gray-600">Order #${item.order_id}</p>
                                <p class="text-sm text-blue-600 font-medium">
                                    <i class="fas fa-clock mr-1"></i>
                                    ${formatTime(item.delivery_time)}
                                </p>
                                ${isOverdue ? `
                                    <p class="text-sm text-red-600 font-bold bg-red-100 px-2 py-1 rounded mt-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        OVERDUE
                                    </p>
                                ` : ''}
                            </div>
                            <div class="flex flex-col items-end space-y-2">
                                <span class="px-3 py-1 rounded-full text-xs font-medium ${statusColor}">
                                    ${item.tracking_status.replace('_', ' ').toUpperCase()}
                                </span>
                                ${isAssigned ? `
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                                        <i class="fas fa-truck mr-1"></i>
                                        ${item.assigned_truck || 'Assigned'}
                                    </span>
                                ` : canAssignToTruck ? `
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">
                                        <i class="fas fa-hand-paper mr-1"></i>
                                        Drag to truck
                                    </span>
                                ` : ''}
                            </div>
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
                        </div>
                        
                        <div class="flex justify-between items-center flex-wrap gap-2">
                            <!-- Left side buttons -->
                            <div class="flex space-x-2 flex-wrap">
                                ${canUnassign ? `
                                    <button onclick="unassignDelivery(${item.id})" 
                                            class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                        <i class="fas fa-times mr-1"></i>
                                        Unassign
                                    </button>
                                ` : ''}
                                
                                ${isAssigned && canUnassign ? `
                                    <button onclick="openReassignModal(${item.id}, '${selectedDate}')" 
                                            class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                        <i class="fas fa-exchange-alt mr-1"></i>
                                        Reassign
                                    </button>
                                ` : ''}
                                
                                ${canReschedule ? `
                                    <button onclick="openRescheduleModal(${item.id}, '${item.delivery_date}', '${item.delivery_time || ''}')" 
                                            class="px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        Reschedule
                                    </button>
                                ` : ''}
                            </div>
                            
                            <!-- Right side buttons -->
                            <div class="flex space-x-2">
                                ${canUploadProof ? `
                                    <button onclick="openUploadProofModal(${item.id})" 
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                        <i class="fas fa-camera mr-1"></i>
                                        Upload Proof
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
        
        // Drag and Drop Functions
        function dragStart(event, deliveryId) {
            event.dataTransfer.setData('text/plain', deliveryId);
            event.currentTarget.classList.add('dragging');
        }
        
        function dragEnd(event) {
            event.currentTarget.classList.remove('dragging');
        }
        
        function allowDrop(event) {
            event.preventDefault();
        }
        
        function handleDragEnter(event) {
            event.preventDefault();
            event.currentTarget.classList.add('drag-over');
        }
        
        function handleDragLeave(event) {
            event.currentTarget.classList.remove('drag-over');
        }
        
        function dropDeliveryItem(event, truckScheduleId) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
            
            const deliveryId = event.dataTransfer.getData('text/plain');
            if (deliveryId) {
                assignDeliveryToTruck(deliveryId, truckScheduleId);
            }
        }
        
        function assignDeliveryToTruck(deliveryId, truckScheduleId) {
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=assign_to_truck&delivery_id=${deliveryId}&truck_schedule_id=${truckScheduleId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Delivery assigned to truck successfully!', 'success');
                    // Refresh both truck schedules and delivery items
                    if (selectedDate) {
                        loadTruckSchedules(selectedDate);
                        loadDeliveryItems(selectedDate);
                    }
                } else {
                    showAlert('Error assigning delivery: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error assigning delivery', 'error');
            });
        }
        
        // Unassign delivery function
        function unassignDelivery(deliveryId) {
            if (confirm('Are you sure you want to unassign this delivery from the truck?')) {
                fetch('logistics_dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=unassign_delivery&delivery_id=${deliveryId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Delivery unassigned successfully!', 'success');
                        refreshCurrentView();
                    } else {
                        showAlert('Error unassigning delivery: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error unassigning delivery', 'error');
                });
            }
        }
        
        // Modal Functions
        function openScheduleTruckModal() {
            if (!selectedDate) {
                showAlert('Please select a date first', 'error');
                return;
            }
            
            loadAvailableTrucksForScheduling(selectedDate);
            document.getElementById('scheduleTruckModal').classList.remove('hidden');
        }
        
        function closeScheduleTruckModal() {
            document.getElementById('scheduleTruckModal').classList.add('hidden');
            document.getElementById('scheduleTruckForm').reset();
        }

        function openUploadProofModal(deliveryId) {
            document.getElementById('proofDeliveryId').value = deliveryId;
            document.getElementById('uploadProofModal').classList.remove('hidden');
        }

        function closeProofModal() {
            document.getElementById('uploadProofModal').classList.add('hidden');
            document.getElementById('uploadProofForm').reset();
        }
        
        // Reassign modal functions
        function openReassignModal(deliveryId, currentDate) {
            document.getElementById('reassignDeliveryId').value = deliveryId;
            loadAvailableTrucksForReassign(currentDate, null);
            document.getElementById('reassignDeliveryModal').classList.remove('hidden');
        }
        
        function closeReassignModal() {
            document.getElementById('reassignDeliveryModal').classList.add('hidden');
            document.getElementById('reassignDeliveryForm').reset();
        }
        
        function loadAvailableTrucksForReassign(date, currentTruckScheduleId) {
            let params = `action=get_available_trucks_for_reassign&date=${encodeURIComponent(date)}`;
            if (currentTruckScheduleId) {
                params += `&current_truck_schedule_id=${currentTruckScheduleId}`;
            }
            
            fetch('logistics_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(response => response.json())
            .then(data => {
                const truckSelect = document.getElementById('reassignTruckSelect');
                truckSelect.innerHTML = '<option value="">Select a truck</option>';
                
                data.forEach(truck => {
                    const option = document.createElement('option');
                    option.value = truck.id;
                    option.textContent = `${truck.truck_id} - ${truck.truck_type} (${truck.assigned_deliveries} assigned)`;
                    truckSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading trucks:', error);
                showAlert('Error loading available trucks', 'error');
            });
        }
        
        // Reschedule modal functions
        function openRescheduleModal(deliveryId, currentDate, currentTime) {
            document.getElementById('rescheduleDeliveryId').value = deliveryId;
            document.getElementById('newDeliveryDate').value = currentDate;
            document.getElementById('newDeliveryTime').value = currentTime;
            document.getElementById('rescheduleDeliveryModal').classList.remove('hidden');
        }
        
        function closeRescheduleModal() {
            document.getElementById('rescheduleDeliveryModal').classList.add('hidden');
            document.getElementById('rescheduleDeliveryForm').reset();
        }
        
        function handleTruckScheduling(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'schedule_truck');
            formData.append('date', selectedDate);
            
            fetch('logistics_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Truck scheduled successfully!', 'success');
                    closeScheduleTruckModal();
                    if (selectedDate) {
                        loadTruckSchedules(selectedDate);
                    }
                } else {
                    showAlert('Error scheduling truck: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error scheduling truck', 'error');
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
                    if (selectedTruckScheduleId) {
                        loadTruckDeliveries(selectedTruckScheduleId);
                    } else if (selectedDate) {
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
        
        function handleReassignDelivery(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'reassign_delivery');
            
            fetch('logistics_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Delivery reassigned successfully!', 'success');
                    closeReassignModal();
                    refreshCurrentView();
                } else {
                    showAlert('Error reassigning delivery: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error reassigning delivery', 'error');
            });
        }
        
        function handleRescheduleDelivery(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'reschedule_delivery');
            
            fetch('logistics_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Delivery rescheduled successfully!', 'success');
                    closeRescheduleModal();
                    refreshCurrentView();
                    // Reload delivery counts to update calendar indicators
                    loadDeliveryCounts();
                    loadOverdueDeliveries();
                } else {
                    showAlert('Error rescheduling delivery: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error rescheduling delivery', 'error');
            });
        }
        
        function removeTruckSchedule(event, truckScheduleId) {
            event.stopPropagation();
            
            if (confirm('Remove this truck from the schedule? All assigned deliveries will be unassigned.')) {
                fetch('logistics_dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=remove_truck_schedule&truck_schedule_id=${truckScheduleId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Truck schedule removed successfully!', 'success');
                        if (selectedDate) {
                            loadTruckSchedules(selectedDate);
                            loadDeliveryItems(selectedDate);
                        }
                        if (selectedTruckScheduleId === truckScheduleId) {
                            selectedTruckScheduleId = null;
                            document.getElementById('delivery-section-title').textContent = `Delivery Items for ${formatDisplayDate(selectedDate)}`;
                        }
                    } else {
                        showAlert('Error removing truck schedule: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error removing truck schedule', 'error');
                });
            }
        }
        
        function viewAllItems() {
            if (!selectedDate) {
                showAlert('Please select a date first', 'error');
                return;
            }
            
            selectedTruckScheduleId = null;
            
            // Remove truck selection
            document.querySelectorAll('.truck-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Update title and load all items
            const displayDate = formatDisplayDate(selectedDate);
            const selectedDateObj = new Date(selectedDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            let dateLabel = displayDate;
            if (selectedDateObj < today) {
                dateLabel += ' (Past Date)';
            }
            
            document.getElementById('delivery-section-title').textContent = `Delivery Items for ${dateLabel}`;
            loadDeliveryItems(selectedDate);
        }
        
        function viewProof(proofFileName) {
            const proofUrl = '../../uploads/delivery_proof/' + proofFileName;
            window.open(proofUrl, '_blank');
        }
        
        // Helper function to refresh current view
        function refreshCurrentView() {
            if (selectedTruckScheduleId) {
                loadTruckDeliveries(selectedTruckScheduleId);
            } else if (selectedDate) {
                loadDeliveryItems(selectedDate);
            }
            
            // Also refresh truck schedules if viewing a date
            if (selectedDate) {
                loadTruckSchedules(selectedDate);
            }
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
       