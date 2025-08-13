<?php
// chat_fetch_sales
session_name("nobleuser");
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    include '../../connection/connect.php';

    // Fetch sales representatives with both id and sales_id
    $sql = "SELECT id, sales_id, fullname, email FROM nobleaccount WHERE lvl = 'sales' AND status = 'active' AND sales_id IS NOT NULL";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception($conn->error);
    }

    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = [
            'id' => $row['id'],              // Primary key for internal reference
            'sales_id' => $row['sales_id'],  // Unique sales identifier
            'fullname' => $row['fullname'],
            'email' => $row['email']
        ];
    }

    echo json_encode($sales);

} catch (Exception $e) {
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage()
    ]);
}
?>