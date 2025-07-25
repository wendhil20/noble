<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']);

// Redirect if not logged in
if (!isset($_SESSION['noble_user']) || !isset($_SESSION['noble_id'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = $_POST['order_id'] ?? null;
$user_id = $_SESSION['noble_id'];

if ($order_id && is_numeric($order_id)) {
    $stmt = $conn->prepare("UPDATE orders SET emp_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $user_id, $order_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect with success message
header("Location: unassigned_orders.php?accepted=true");
exit();
