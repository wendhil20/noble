<?php
// test_notification.php - Para sa debugging
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';

error_log("=== TEST NOTIFICATION DEBUG ===");
error_log("Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL'));
error_log("Session noble_user: " . (isset($_SESSION['noble_user']) ? $_SESSION['noble_user'] : 'NULL'));

// Test data
$test_order_id = 130; // Change this to actual order ID
$test_booking_id = 1;
$test_booking_type = 'delivery';

error_log("Test Order ID: $test_order_id");

// Step 1: Check if order exists
$stmt = $conn->prepare("SELECT user_id, customer_name FROM orders WHERE id = ?");
$stmt->bind_param("i", $test_order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    error_log("✅ Order found - user_id: " . $row['user_id'] . ", customer_name: " . $row['customer_name']);
} else {
    error_log("❌ Order not found");
}
$stmt->close();

// Step 2: Test direct insert
$test_user_id = $row['user_id'];
$test_actor_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 4; // Default to 4 if null
$test_type = "DELIVERY_COMPLETED_TEST";
$test_message = "Test notification for Order #$test_order_id";

error_log("Attempting insert with: user_id=$test_user_id, actor_id=$test_actor_id, type=$test_type");

$stmt_insert = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) VALUES (?, ?, ?, ?, NOW())");
if (!$stmt_insert) {
    error_log("❌ Prepare failed: " . $conn->error);
} else {
    $stmt_insert->bind_param("iiss", $test_user_id, $test_actor_id, $test_type, $test_message);
    
    if ($stmt_insert->execute()) {
        error_log("✅ Test notification inserted successfully - ID: " . $stmt_insert->insert_id);
    } else {
        error_log("❌ Execute failed: " . $stmt_insert->error);
    }
    $stmt_insert->close();
}

// Step 3: Check if it was inserted
$stmt_check = $conn->prepare("SELECT * FROM notifications WHERE type = ? ORDER BY created_at DESC LIMIT 1");
$stmt_check->bind_param("s", $test_type);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    $notif = $result_check->fetch_assoc();
    error_log("✅ Notification verified in DB - ID: " . $notif['id']);
    echo "<pre>";
    print_r($notif);
    echo "</pre>";
} else {
    error_log("❌ Notification NOT found in DB");
}
$stmt_check->close();

error_log("=== END TEST ===");
echo "Check error logs for results!";
?>