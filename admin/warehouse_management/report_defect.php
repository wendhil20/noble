<?php
// report_defect.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get user ID
$user_id = null;
if (isset($_SESSION['noble_id'])) {
    $user_id = $_SESSION['noble_id'];
} else {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($user_id);
    $stmt->fetch();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $item_id = isset($data['item_id']) ? intval($data['item_id']) : 0;
    $defect_type = isset($data['defect_type']) ? trim($data['defect_type']) : '';
    $defect_description = isset($data['defect_description']) ? trim($data['defect_description']) : '';
    $quantity_defective = isset($data['quantity_defective']) ? intval($data['quantity_defective']) : 1;
    $severity = isset($data['severity']) ? $data['severity'] : 'moderate';
    
    if ($item_id <= 0 || empty($defect_type) || empty($defect_description)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Get order_id from item
    $stmt = $conn->prepare("SELECT order_id, quantity FROM order_items WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit();
    }
    
    if ($quantity_defective > $item['quantity']) {
        echo json_encode(['success' => false, 'error' => 'Defective quantity cannot exceed item quantity']);
        exit();
    }
    
    // Insert defect report
    $stmt = $conn->prepare("
        INSERT INTO defect_reports 
        (order_item_id, order_id, reported_by, defect_type, defect_description, quantity_defective, severity, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iiissis", 
        $item_id, 
        $item['order_id'], 
        $user_id, 
        $defect_type, 
        $defect_description, 
        $quantity_defective, 
        $severity
    );
    
    if ($stmt->execute()) {
        $defect_id = $stmt->insert_id;
        $stmt->close();
        echo json_encode([
            'success' => true, 
            'message' => 'Defect report submitted successfully',
            'defect_id' => $defect_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to submit report']);
    }
    exit();
}

// GET request - fetch defects for an item or order
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    
    if ($item_id > 0) {
        $stmt = $conn->prepare("
            SELECT dr.*, na.fullname as reporter_name 
            FROM defect_reports dr
            LEFT JOIN nobleaccount na ON dr.reported_by = na.id
            WHERE dr.order_item_id = ?
            ORDER BY dr.reported_at DESC
        ");
        $stmt->bind_param("i", $item_id);
    } elseif ($order_id > 0) {
        $stmt = $conn->prepare("
            SELECT dr.*, na.fullname as reporter_name,
                   oi.product_name, oi.codename
            FROM defect_reports dr
            LEFT JOIN nobleaccount na ON dr.reported_by = na.id
            LEFT JOIN order_items oi ON dr.order_item_id = oi.id
            WHERE dr.order_id = ?
            ORDER BY dr.reported_at DESC
        ");
        $stmt->bind_param("i", $order_id);
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit();
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $defects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['success' => true, 'defects' => $defects]);
    exit();
}
?>