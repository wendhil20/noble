<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit();
}

$product_id = intval($_GET['product_id']);

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

// Get tiers for this product - UPDATED TO USE min_quantity
$query = "SELECT id, min_quantity, discount_percent, free_shipping FROM product_tiers 
          WHERE product_id = '$product_id' 
          ORDER BY min_quantity ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . mysqli_error($conn)
    ]);
    exit();
}

$tiers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tiers[] = [
        'id' => $row['id'],
        'min_quantity' => $row['min_quantity'],
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
