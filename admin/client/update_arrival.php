<?php
require_once '../../connection/connect.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? null;

if ($email) {
    $stmt = $conn->prepare("
        UPDATE orders 
        SET status = 'Arrival',
            final_total = COALESCE(total, 0) + COALESCE(shipping_fee, 0) - COALESCE(discount, 0)
        WHERE status = 'Ongoing' 
          AND estimated_arrival_date <= NOW()
          AND email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
}
?>
