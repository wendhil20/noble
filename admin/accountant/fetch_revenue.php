<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';

// Only allow accountants/superadmins
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

header('Content-Type: application/json');

try {
    // Check if requesting all records
    if (isset($_GET['all_records']) && $_GET['all_records'] === 'true') {
        // Fetch all revenue records
        $stmt = $conn->prepare("
            SELECT DATE(confirmed_at) as date, COALESCE(SUM(final_total),0) as total
            FROM orders 
            WHERE payment_status = 'verified'
            GROUP BY DATE(confirmed_at)
            ORDER BY DATE(confirmed_at) ASC
        ");
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
            'count' => count($data)
        ]);
        
    } else {
        // Get date range from request (default last 7 days)
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $end_date   = $_GET['end_date'] ?? date('Y-m-d');

        // Query orders revenue for specific date range
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
    }

} catch (Exception $e) {
    error_log("Error in fetch_revenue.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching data'
    ]);
}
?>