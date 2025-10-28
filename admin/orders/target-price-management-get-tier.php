<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']);

header('Content-Type: application/json');

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isset($_GET['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit();
}

$product_id = mysqli_real_escape_string($conn, $_GET['product_id']);

// Check if table exists
$check_table = "SHOW TABLES LIKE 'product_tiers'";
$table_result = mysqli_query($conn, $check_table);

if (mysqli_num_rows($table_result) == 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Table product_tiers does not exist. Please run the SQL schema first.'
    ]);
    exit();
}

// Get tiers for this product
$query = "SELECT * FROM product_tiers 
          WHERE product_id = '$product_id' 
          ORDER BY min_amount ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . mysqli_error($conn),
        'query' => $query
    ]);
    exit();
}

$tiers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tiers[] = [
        'id' => $row['id'],
        'min_amount' => $row['min_amount'],
        'discount_percent' => $row['discount_percent'],
        'free_shipping' => $row['free_shipping']
    ];
}

echo json_encode([
    'success' => true,
    'tiers' => $tiers,
    'count' => count($tiers)
]);
?>