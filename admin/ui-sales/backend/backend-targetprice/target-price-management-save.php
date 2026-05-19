<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['sales', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_POST['product_id']) || !isset($_POST['tiers'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
$tiers = json_decode($_POST['tiers'], true);

if (!is_array($tiers)) {
    echo json_encode(['success' => false, 'message' => 'Invalid tiers data']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Process each tier
    foreach ($tiers as $tier) {
        $min_quantity = intval($tier['min_quantity']);
        $discount = floatval($tier['discount']);
        $free_shipping = intval($tier['free_shipping']);
        
        if (empty($tier['id'])) {
            // Insert new tier
            $query = "INSERT INTO product_tiers 
                      (product_id, min_quantity, discount_percent, free_shipping, created_at) 
                      VALUES ('$product_id', '$min_quantity', '$discount', '$free_shipping', NOW())";
        } else {
            // Update existing tier
            $tier_id = mysqli_real_escape_string($conn, $tier['id']);
            $query = "UPDATE product_tiers 
                      SET min_quantity = '$min_quantity', 
                          discount_percent = '$discount', 
                          free_shipping = '$free_shipping',
                          updated_at = NOW()
                      WHERE id = '$tier_id' AND product_id = '$product_id'";
        }
        
        if (!mysqli_query($conn, $query)) {
            throw new Exception('Failed to save tier: ' . mysqli_error($conn));
        }
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode(['success' => true, 'message' => 'Tiers saved successfully']);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
