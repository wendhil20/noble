<?php
session_name("nobleemployeereport");
session_start();

header('Content-Type: application/json');

include '../connection/connect.php';

// Get employee ID from request
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

if ($employee_id <= 0) {
    echo json_encode(['error' => 'Invalid employee ID']);
    exit;
}

// Function to calculate remaining delay days
function getRemainingDelayDays($delay_start_date, $initial_delay_days)
{
    if (!$delay_start_date || $initial_delay_days <= 0) {
        return 0;
    }

    $start = new DateTime($delay_start_date);
    $today = new DateTime();
    $days_passed = $start->diff($today)->days;

    $remaining = $initial_delay_days - $days_passed;
    return max(0, $remaining);
}

$response = [];

// Get employee details
$employee_query = "SELECT id, username, position, email FROM employeaccountreport WHERE id = $employee_id";
$employee_result = mysqli_query($conn, $employee_query);

if (mysqli_num_rows($employee_result) === 0) {
    echo json_encode(['error' => 'Employee not found']);
    exit;
}

$response['employee'] = mysqli_fetch_assoc($employee_result);

// Get all tasks for this employee
$tasks_query = "SELECT * FROM employee_tasks 
                WHERE user_id = $employee_id 
                ORDER BY start_date ASC, id ASC";
$tasks_result = mysqli_query($conn, $tasks_query);

$all_tasks = [];
while ($task = mysqli_fetch_assoc($tasks_result)) {
    $display_delay_days = 0;
    if ($task['status'] === 'delayed' && isset($task['delay_start_date'])) {
        $display_delay_days = getRemainingDelayDays($task['delay_start_date'], $task['delay_days']);
    }
    $task['display_delay_days'] = $display_delay_days;
    $all_tasks[] = $task;
}

// Group tasks by date
$tasks_by_date = [];
foreach ($all_tasks as $task) {
    // Check if task is completed
    $is_completed = ($task['status'] === 'completed' || $task['progress_percentage'] == 100);
    
    if ($is_completed && !empty($task['completed_date'])) {
        // For completed tasks, only show on completed_date
        $completed_date_only = explode(' ', $task['completed_date'])[0];
        
        if (!isset($tasks_by_date[$completed_date_only])) {
            $tasks_by_date[$completed_date_only] = [];
        }
        
        $tasks_by_date[$completed_date_only][] = $task;
    } else {
        // For non-completed tasks, show on start_date only
        $start_date = $task['start_date'];
        
        if (!isset($tasks_by_date[$start_date])) {
            $tasks_by_date[$start_date] = [];
        }
        
        $tasks_by_date[$start_date][] = $task;
    }
}

// Sort by date
ksort($tasks_by_date);

$response['tasks_by_date'] = $tasks_by_date;

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN progress_percentage > 0 AND progress_percentage < 100 THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN progress_percentage = 0 THEN 1 ELSE 0 END) as not_started_tasks,
                SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed_tasks,
                SUM(CASE WHEN is_rolled_over = 1 THEN 1 ELSE 0 END) as rolled_over_tasks,
                AVG(progress_percentage) as avg_progress
                FROM employee_tasks 
                WHERE user_id = $employee_id";
$response['statistics'] = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

echo json_encode($response);