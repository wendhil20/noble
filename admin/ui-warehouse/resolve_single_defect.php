<?php
// resolve_single_defect.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $defect_id = isset($data['defect_id']) ? intval($data['defect_id']) : 0;
    
    if ($defect_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing defect ID']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE defect_reports SET status = 'resolved' WHERE id = ?");
    $stmt->bind_param("i", $defect_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Defect resolved']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to resolve defect']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request method']);
?>