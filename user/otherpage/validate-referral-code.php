<?php

header('Content-Type: application/json');

include ROOT_PATH . '/connection/connect.php';

$response = ['valid' => false, 'message' => 'Invalid code'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['referral_code'])) {
    $code = trim(strtoupper($_POST['referral_code']));
    
    // Validate format
    if (!preg_match('/^NH-[A-Z0-9]{6}$/', $code)) {
        $response['message'] = 'Invalid code format';
        echo json_encode($response);
        exit;
    }
    
    // Check database
    $stmt = $conn->prepare("
        SELECT discount_enabled, discount_type, discount_value 
        FROM referral_codes 
        WHERE referral_code = ? AND is_active = 1 
        LIMIT 1
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->bind_result($enabled, $type, $value);
    
    if ($stmt->fetch() && $enabled == 1) {
        $response['valid'] = true;
        $response['discount_type'] = $type;
        $response['discount_value'] = $value;
        
        if ($type === 'percentage') {
            $response['discount_display'] = number_format($value, 0) . '%';
        } else {
            $response['discount_display'] = '₱' . number_format($value, 2);
        }
        
        $response['message'] = 'Code verified successfully!';
    } else {
        $response['message'] = 'This referral code is not active or does not exist';
    }
    
    $stmt->close();
}

echo json_encode($response);