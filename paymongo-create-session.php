<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// PayMongo Secret Key (Test muna)
$secretKey = "sk_test_AJdRkkXWfGW9W5DHV6UNNECZ";

$input = json_decode(file_get_contents("php://input"), true);
$amount = intval($input['amount']) * 100; // centavos

$ch = curl_init("https://api.paymongo.com/v1/checkout_sessions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Basic " . base64_encode($secretKey . ":")
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "data" => [
        "attributes" => [
            "amount" => $amount,
            "currency" => "PHP",
            "line_items" => [[
                "name" => "Sample Order",
                "quantity" => 1,
                "amount" => $amount,
                "currency" => "PHP"
            ]],
            "payment_method_types" => ["gcash", "paymaya", "card"],
            "success_url" => "http://localhost/noble/success.php",
            "cancel_url" => "http://localhost/noble/cancel.php"
        ]
    ]
]));

$response = curl_exec($ch);
if ($response === false) {
    echo json_encode(["error" => curl_error($ch)]);
    exit;
}
curl_close($ch);

echo $response;
