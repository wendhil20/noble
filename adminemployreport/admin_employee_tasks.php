<?php
include '../connection/connect.php';

$user_id = $_GET['user_id'];

$query = "SELECT * FROM employee_tasks WHERE user_id = $user_id ORDER BY start_date DESC";
$result = mysqli_query($conn, $query);

$tasks = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tasks[] = $row;
}

header('Content-Type: application/json');
echo json_encode($tasks);
?>