<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';

// Only allow accountants/superadmins
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

header('Content-Type: application/json');

// Get date range from request (default last 7 days)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// Query orders revenue
$stmt = $conn->prepare("
    SELECT DATE(confirmed_at) as date, COALESCE(SUM(final_total),0) as total
    FROM orders 
    WHERE payment_status = 'verified'
      AND DATE(confirmed_at) BETWEEN ? AND ?
    GROUP BY DATE(confirmed_at)
    ORDER BY DATE(confirmed_at)
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'date' => $row['date'],
        'total' => (float)$row['total']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $data,
    'start_date' => $start_date,
    'end_date' => $end_date
]);
