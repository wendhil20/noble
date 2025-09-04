<?php
// ===============================
// CONFIGURATION
// ===============================
$api_username = "sb-e9jug45931077_api1.business.example.com";
$api_password = "JA7M5EZ3V8ZDY3L8";
$api_signature = "AATf2rd9rVjFGl2pL2Qc416hwLDOAIUwgGoZOuE1LuBHdGu5B4TOQgqu";
$endpoint = "https://api-3t.sandbox.paypal.com/nvp";
$version = "204.0"; // NVP API version

// ===============================
// SETUP REQUEST PARAMETERS
// ===============================
// Example: TransactionSearch for the last 30 days
$startDate = date("Y-m-d", strtotime("-30 days")) . "T00:00:00Z";

$params = [
    "METHOD" => "TransactionSearch",
    "USER" => $api_username,
    "PWD" => $api_password,
    "SIGNATURE" => $api_signature,
    "VERSION" => $version,
    "STARTDATE" => $startDate,
    "ENDDATE" => date("Y-m-d") . "T23:59:59Z",
];



// Convert parameters to NVP query string
$request = http_build_query($params);

// ===============================
// CURL REQUEST
// ===============================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // sandbox only

$response = curl_exec($ch);
if (!$response) {
    die("Error: " . curl_error($ch));
}
curl_close($ch);

// Parse NVP response into associative array
parse_str($response, $parsed_response);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sandbox PayPal Transactions</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h1 { color: #0070ba; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #0070ba; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Sandbox Transaction History (Last 30 Days)</h1>

    <table>
        <tr>
            <th>Transaction ID</th>
            <th>Type</th>
            <th>Status</th>
            <th>Amount</th>
            <th>Fee</th>
            <th>Timestamp</th>
        </tr>
        <?php
        // Check if there are transactions
        if (!empty($parsed_response)) {
            // PayPal NVP returns transactions as TnxID0, TnxID1, etc.
            $i = 0;
            while(isset($parsed_response["L_TRANSACTIONID$i"])) {
                $id     = $parsed_response["L_TRANSACTIONID$i"];
                $type   = $parsed_response["L_TYPE$i"] ?? "N/A";
                $status = $parsed_response["L_STATUS$i"] ?? "N/A";
                $amt    = $parsed_response["L_AMT$i"] ?? "0.00";
                $fee    = $parsed_response["L_FEEAMT$i"] ?? "0.00";
             $time = $parsed_response["L_TIMESTAMP$i"] ?? "N/A";

             // Convert to AM/PM format if not empty
if ($time !== "N/A") {
    $timestamp = strtotime($time); // convert string to Unix timestamp
    $time = date("Y-m-d h:i A", $timestamp); // format: 2025-09-04 02:30 PM
}

                echo "<tr>
                        <td>$id</td>
                        <td>$type</td>
                        <td>$status</td>
                        <td>$amt</td>
                        <td>$fee</td>
                        <td>$time</td>
                      </tr>";
                $i++;
            }

            if($i === 0) {
                echo "<tr><td colspan='6'>No transactions found in this date range.</td></tr>";
            }
        } else {
            echo "<tr><td colspan='6'>No transactions found.</td></tr>";
        }
        ?>
    </table>
</body>
</html>
