<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';

if (!isset($_SESSION['noble_id'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$employee_id = $_SESSION['noble_id'];

// Check if the order is still unassigned
$stmt = $conn->prepare("SELECT emp_id FROM orders WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

if ($emp_id === null || $emp_id == '') {
    // Proceed with accepting the order
    $update = $conn->prepare("UPDATE orders SET emp_id = ? WHERE id = ?");
    $update->bind_param("ii", $employee_id, $order_id);
    if ($update->execute()) {
        header("Location: unassigned_orders.php?accepted=true");
    } else {
        header("Location: unassigned_orders.php?error=update_failed");
    }
    $update->close();
} else {
    // Order already assigned
    header("Location: unassigned_orders.php?error=already_accepted");
}

$conn->close();
