<?php
// get_available_drivers.php
// CREATE THIS NEW FILE in the same directory as your delivery_detailed_view.php

session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$selected_date = $_GET['date'] ?? null;
$current_truck_id = $_GET['truck_id'] ?? null;

if (!$selected_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid date format']));
}

// Get available drivers (exclude drivers already assigned to OTHER trucks on this date)
// But include the driver currently assigned to THIS truck (so they can be reassigned)
$driversSql = "SELECT 
    dl.id as driver_id,
    dl.first_name,
    dl.last_name,
    dl.contact_number,
    dl.email,
    dl.photo_path,
    dl.status
FROM driver_list dl
WHERE dl.status = 'active'
    AND dl.id NOT IN (
        SELECT DISTINCT ts.assigned_driver_id 
        FROM truck_schedules ts 
        WHERE ts.scheduled_date = ? 
        AND ts.assigned_driver_id IS NOT NULL
        AND ts.id != ?
    )
ORDER BY dl.first_name, dl.last_name";

$driversStmt = $conn->prepare($driversSql);
$driversStmt->bind_param("si", $selected_date, $current_truck_id);
$driversStmt->execute();
$available_drivers = $driversStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$driversStmt->close();

header('Content-Type: application/json');
echo json_encode(['drivers' => $available_drivers]);
?>