<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['sales', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_POST['tier_id'])) {
    echo json_encode(['success' => false, 'message' => 'Tier ID is required']);
    exit();
}

$tier_id = intval($_POST['tier_id']);

$query = "DELETE FROM product_tiers WHERE id = '$tier_id'";

if (mysqli_query($conn, $query)) {
    if (mysqli_affected_rows($conn) > 0) {
        echo json_encode(['success' => true, 'message' => 'Tier deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tier not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}
?>