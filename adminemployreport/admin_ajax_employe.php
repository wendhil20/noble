<?php
session_name("nobleemployeereport");
session_start();

header('Content-Type: application/json');

include '../connection/connect.php';

// Get current week
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('saturday this week'));

// Get next week
$next_week_start = date('Y-m-d', strtotime('monday next week'));
$next_week_end = date('Y-m-d', strtotime('saturday next week'));

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

// Get all employees with their OVERALL task statistics
$all_employees_query = "SELECT 
                    e.id,
                    e.username,
                    e.position,
                    e.email,
                    COUNT(DISTINCT t.id) as total_tasks,
                    SUM(CASE WHEN t.progress_percentage = 100 THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN t.progress_percentage > 0 AND t.progress_percentage < 100 THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN t.progress_percentage = 0 THEN 1 ELSE 0 END) as not_started_tasks,
                    SUM(CASE WHEN t.status = 'delayed' THEN 1 ELSE 0 END) as delayed_tasks
                    FROM employeaccountreport e
                    LEFT JOIN employee_tasks t ON e.id = t.user_id
                    GROUP BY e.id
                    ORDER BY e.username ASC";
$all_employees = mysqli_query($conn, $all_employees_query);
$response['all_employees'] = [];
while ($emp = mysqli_fetch_assoc($all_employees)) {
    $response['all_employees'][] = $emp;
}

// Get current week employees with tasks
$current_employees_query = "SELECT 
                    e.id,
                    e.username,
                    e.position,
                    e.email
                    FROM employeaccountreport e
                    ORDER BY e.username ASC";
$current_employees = mysqli_query($conn, $current_employees_query);
$response['current_week'] = [];

while ($employee = mysqli_fetch_assoc($current_employees)) {
    $tasks_query = "SELECT * FROM employee_tasks 
                   WHERE user_id = {$employee['id']} 
                   AND start_date >= '$current_week_start' 
                   AND start_date <= '$current_week_end'
                   ORDER BY start_date ASC";
    $tasks_result = mysqli_query($conn, $tasks_query);
    
    $tasks = [];
    while ($task = mysqli_fetch_assoc($tasks_result)) {
        $display_delay_days = 0;
        if ($task['status'] === 'delayed' && isset($task['delay_start_date'])) {
            $display_delay_days = getRemainingDelayDays($task['delay_start_date'], $task['delay_days']);
        }
        $task['display_delay_days'] = $display_delay_days;
        $tasks[] = $task;
    }
    
    $response['current_week'][] = [
        'employee' => $employee,
        'tasks' => $tasks,
        'task_count' => count($tasks)
    ];
}

// Get next week employees with tasks
$next_employees_query = "SELECT 
                    e.id,
                    e.username,
                    e.position,
                    e.email
                    FROM employeaccountreport e
                    ORDER BY e.username ASC";
$next_employees = mysqli_query($conn, $next_employees_query);
$response['next_week'] = [];

while ($employee = mysqli_fetch_assoc($next_employees)) {
    $tasks_query = "SELECT * FROM employee_tasks 
                   WHERE user_id = {$employee['id']} 
                   AND start_date >= '$next_week_start' 
                   AND start_date <= '$next_week_end'
                   ORDER BY start_date ASC";
    $tasks_result = mysqli_query($conn, $tasks_query);
    
    $tasks = [];
    while ($task = mysqli_fetch_assoc($tasks_result)) {
        $display_delay_days = 0;
        if ($task['status'] === 'delayed' && isset($task['delay_start_date'])) {
            $display_delay_days = getRemainingDelayDays($task['delay_start_date'], $task['delay_days']);
        }
        $task['display_delay_days'] = $display_delay_days;
        $tasks[] = $task;
    }
    
    $response['next_week'][] = [
        'employee' => $employee,
        'tasks' => $tasks,
        'task_count' => count($tasks)
    ];
}

// Get current week stats
$current_stats_query = "SELECT 
                COUNT(DISTINCT user_id) as active_employees,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed_tasks,
                AVG(progress_percentage) as avg_progress
                FROM employee_tasks 
                WHERE start_date >= '$current_week_start' 
                AND start_date <= '$current_week_end'";
$response['current_stats'] = mysqli_fetch_assoc(mysqli_query($conn, $current_stats_query));

// Get next week stats
$next_stats_query = "SELECT 
                COUNT(DISTINCT user_id) as active_employees,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed_tasks,
                AVG(progress_percentage) as avg_progress
                FROM employee_tasks 
                WHERE start_date >= '$next_week_start' 
                AND start_date <= '$next_week_end'";
$response['next_stats'] = mysqli_fetch_assoc(mysqli_query($conn, $next_stats_query));

echo json_encode($response);