<?php 
session_start(); 
include '../../connection/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $place = isset($_POST['place']) ? trim($_POST['place']) : '';
    $complete_delivery = isset($_POST['complete_delivery']) ? (int)$_POST['complete_delivery'] : 0;

    if (!$order_id || empty($place)) {
        die("Invalid input.");
    }

    // Debug: Check what values we're receiving
    error_log("Order ID: $order_id, Place: $place, Complete Delivery: $complete_delivery");

    // Step 1: Mark current place status based on whether it's the final delivery
    if ($complete_delivery == 1) {
        // If it's the final delivery, mark as 'complete' directly
        $stmt = $conn->prepare("UPDATE variant_tracking SET status = 'complete', completed_at = NOW(), timestamp = NOW() WHERE order_id = ? AND place = ?");
        $stmt->bind_param("is", $order_id, $place);
        $stmt->execute();
        
        // Debug: Check if the update was successful
        $affected_rows = $stmt->affected_rows;
        error_log("Affected rows for 'complete' update: $affected_rows");
        $stmt->close();
        
        // Also update the main orders table
        $stmt2 = $conn->prepare("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $stmt2->bind_param("i", $order_id);
        $stmt2->execute();
        $stmt2->close();
    } else {
        // If it's not the final delivery, mark as 'reached'
        $stmt = $conn->prepare("UPDATE variant_tracking SET status = 'reached', timestamp = NOW() WHERE order_id = ? AND place = ?");
        $stmt->bind_param("is", $order_id, $place);
        $stmt->execute();
        
        // Debug: Check if the update was successful
        $affected_rows = $stmt->affected_rows;
        error_log("Affected rows for 'reached' update: $affected_rows");
        $stmt->close();
    }

    header("Location: order_details.php?id=" . $order_id);
    exit();
} else {
    echo "Invalid request method.";
}
?>