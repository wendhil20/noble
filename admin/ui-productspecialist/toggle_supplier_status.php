<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: toggle_supplier_status.php");
    exit();
}

// Get form data
$supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
$new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';

// Validate input
if (!$supplier_id || !in_array($new_status, ['active', 'inactive'])) {
    header("Location: toggle_supplier_status.php?error=invalid_data");
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // First, get the supplier name for the success message
    $name_sql = "SELECT business_name FROM supplier_list WHERE id = ?";
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->bind_param("i", $supplier_id);
    $name_stmt->execute();
    $name_result = $name_stmt->get_result();
    $supplier_data = $name_result->fetch_assoc();
    $name_stmt->close();

    if (!$supplier_data) {
        throw new Exception("Supplier not found");
    }

    // Update supplier status
    $update_sql = "UPDATE supplier_list SET status = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $supplier_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update supplier status");
    }
    
    // Check if any rows were affected
    if ($update_stmt->affected_rows === 0) {
        throw new Exception("Supplier not found or status not changed");
    }
    
    $update_stmt->close();

    // Optional: Log the activity (uncomment if you have an activity log table)
    /*
    $log_sql = "INSERT INTO activity_log (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $action = "supplier_status_" . $new_status;
    $description = "Changed supplier '{$supplier_data['business_name']}' (ID: $supplier_id) status to $new_status";
    $user_id = $_SESSION['noble_user']['id'] ?? 0;
    $log_stmt->bind_param("iss", $user_id, $action, $description);
    $log_stmt->execute();
    $log_stmt->close();
    */

    // Commit transaction
    $conn->commit();
    
    // Set success message
    $supplier_name = htmlspecialchars($supplier_data['business_name']);
    $status_text = ucfirst($new_status);
    $message = "Supplier '{$supplier_name}' status successfully updated to {$status_text}";
    header("Location: view_supplier.php?id=" . $supplier_id . "&success=" . urlencode($message));
    exit();

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Set error message
    $error_message = "Error updating supplier status: " . $e->getMessage();
    header("Location: view_supplier.php?id=" . $supplier_id . "&error=" . urlencode($error_message));
    exit();
}
?>