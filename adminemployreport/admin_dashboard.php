<?php
session_name("nobleemployeereport");
session_start();

include '../connection/connect.php';

// Get current week
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('saturday this week'));

// Get next week
$next_week_start = date('Y-m-d', strtotime('monday next week'));
$next_week_end = date('Y-m-d', strtotime('saturday next week'));

// Function to calculate remaining delay days
function getRemainingDelayDays($delay_start_date, $initial_delay_days) {
    if (!$delay_start_date || $initial_delay_days <= 0) {
        return 0;
    }
    
    $start = new DateTime($delay_start_date);
    $today = new DateTime();
    $days_passed = $start->diff($today)->days;
    
    $remaining = $initial_delay_days - $days_passed;
    return max(0, $remaining);
}

// Get all employees with their tasks for current week
$current_employees_query = "SELECT 
                    e.id,
                    e.username,
                    e.position,
                    e.email,
                    COUNT(DISTINCT t.id) as total_tasks,
                    SUM(CASE WHEN t.progress_percentage = 100 THEN 1 ELSE 0 END) as completed_tasks,
                    AVG(t.progress_percentage) as avg_progress
                    FROM employeaccountreport e
                    LEFT JOIN employee_tasks t ON e.id = t.user_id 
                        AND t.start_date >= '$current_week_start' 
                        AND t.start_date <= '$current_week_end'
                    GROUP BY e.id
                    ORDER BY e.username ASC";
$current_employees = mysqli_query($conn, $current_employees_query);

// Get all employees with their tasks for next week
$next_employees_query = "SELECT 
                    e.id,
                    e.username,
                    e.position,
                    e.email,
                    COUNT(DISTINCT t.id) as total_tasks,
                    SUM(CASE WHEN t.progress_percentage = 100 THEN 1 ELSE 0 END) as completed_tasks,
                    AVG(t.progress_percentage) as avg_progress
                    FROM employeaccountreport e
                    LEFT JOIN employee_tasks t ON e.id = t.user_id 
                        AND t.start_date >= '$next_week_start' 
                        AND t.start_date <= '$next_week_end'
                    GROUP BY e.id
                    ORDER BY e.username ASC";
$next_employees = mysqli_query($conn, $next_employees_query);

// Get overall statistics for current week
$current_stats_query = "SELECT 
                COUNT(DISTINCT user_id) as active_employees,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed_tasks,
                AVG(progress_percentage) as avg_progress
                FROM employee_tasks 
                WHERE start_date >= '$current_week_start' 
                AND start_date <= '$current_week_end'";
$current_stats = mysqli_fetch_assoc(mysqli_query($conn, $current_stats_query));

// Get overall statistics for next week
$next_stats_query = "SELECT 
                COUNT(DISTINCT user_id) as active_employees,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed_tasks,
                AVG(progress_percentage) as avg_progress
                FROM employee_tasks 
                WHERE start_date >= '$next_week_start' 
                AND start_date <= '$next_week_end'";
$next_stats = mysqli_fetch_assoc(mysqli_query($conn, $next_stats_query));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - All Employee Tasks</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <svg class="w-8 h-8 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
                        <p class="text-sm text-gray-500 mt-0.5">Overview of all employee tasks</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-6">
        
        <!-- Two Column Layout: Current Week & Next Week -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- CURRENT WEEK CONTAINER -->
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 sticky top-24 z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <svg class="w-6 h-6 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-900">Current Week</h2>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">
                        <?php echo date('M d', strtotime($current_week_start)); ?> - 
                        <?php echo date('M d, Y', strtotime($current_week_end)); ?>
                    </p>
                    <div class="grid grid-cols-3 gap-4 mt-4">
                        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $current_stats['total_tasks'] ?? 0; ?></p>
                            <p class="text-xs text-gray-600">Total Tasks</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $current_stats['completed_tasks'] ?? 0; ?></p>
                            <p class="text-xs text-gray-600">Completed</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                            <p class="text-2xl font-bold text-gray-900"><?php echo round($current_stats['avg_progress'] ?? 0); ?>%</p>
                            <p class="text-xs text-gray-600">Avg Progress</p>
                        </div>
                    </div>
                </div>

                <!-- Current Week Employee List -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                      current week
                    </h3>

                    <?php 
                    if (mysqli_num_rows($current_employees) == 0): 
                    ?>
                        <div class="text-center py-8">
                            <p class="text-gray-400">No employees found</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php while ($employee = mysqli_fetch_assoc($current_employees)): 
                                $tasks_query = "SELECT * FROM employee_tasks 
                                               WHERE user_id = {$employee['id']} 
                                               AND start_date >= '$current_week_start' 
                                               AND start_date <= '$current_week_end'
                                               ORDER BY start_date ASC";
                                $tasks = mysqli_query($conn, $tasks_query);
                                $task_count = mysqli_num_rows($tasks);
                                
                                $status_colors = [
                                    'not_started' => 'bg-gray-100 text-gray-700 border border-gray-300',
                                    'in_progress' => 'bg-gray-700 text-white',
                                    'completed' => 'bg-gray-900 text-white',
                                    'delayed' => 'bg-red-50 text-red-700 border border-red-200'
                                ];
                                $status_labels = [
                                    'not_started' => 'Not Started',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'delayed' => 'Delayed'
                                ];
                            ?>
                                <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                    <div class="bg-gray-50 p-3 cursor-pointer" 
                                         onclick="toggleEmployee('current', <?php echo $employee['id']; ?>)">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-gray-900 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                                    <?php echo strtoupper(substr($employee['username'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-gray-900 text-sm"><?php echo $employee['username']; ?></h4>
                                                    <p class="text-xs text-gray-600"><?php echo $employee['position']; ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <div class="text-center">
                                                    <p class="text-lg font-bold text-gray-900"><?php echo $task_count; ?>/5</p>
                                                    <p class="text-xs text-gray-500">Tasks</p>
                                                </div>
                                                <div class="text-gray-400">
                                                    <svg class="w-5 h-5 transform transition-transform" id="arrow-current-<?php echo $employee['id']; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="employee-current-<?php echo $employee['id']; ?>" class="hidden">
                                        <?php if ($task_count == 0): ?>
                                            <div class="p-4 text-center bg-white">
                                                <p class="text-gray-400 text-sm">No tasks</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="p-3 space-y-2 bg-white">
                                                <?php while ($task = mysqli_fetch_assoc($tasks)): 
                                                    $display_delay_days = 0;
                                                    if ($task['status'] === 'delayed' && isset($task['delay_start_date'])) {
                                                        $display_delay_days = getRemainingDelayDays($task['delay_start_date'], $task['delay_days']);
                                                    }
                                                ?>
                                                    <div class="bg-gray-50 rounded-lg p-3 text-sm border border-gray-200">
                                                        <div class="flex justify-between items-start mb-2">
                                                            <span class="font-semibold text-gray-900"><?php echo $task['task_title']; ?></span>
                                                            <span class="text-xs font-medium px-2 py-1 rounded-full <?php echo $status_colors[$task['status']]; ?>">
                                                                <?php echo $status_labels[$task['status']]; ?>
                                                            </span>
                                                        </div>
                                                        <div class="flex justify-between items-center text-xs text-gray-600">
                                                            <span>
                                                                <?php if ($task['status'] === 'delayed'): ?>
                                                                    <span class="font-bold text-red-600 flex items-center gap-1">
                                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                                        </svg>
                                                                        <?php echo $display_delay_days; ?> days delay
                                                                    </span>
                                                                <?php else: ?>
                                                                    <?php echo date('M d', strtotime($task['start_date'])); ?> - <?php echo date('M d', strtotime($task['end_date'])); ?>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="font-semibold text-gray-900"><?php echo $task['progress_percentage']; ?>%</span>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NEXT WEEK CONTAINER -->
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 sticky top-24 z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <svg class="w-6 h-6 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-900">Next Week</h2>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">
                        <?php echo date('M d', strtotime($next_week_start)); ?> - 
                        <?php echo date('M d, Y', strtotime($next_week_end)); ?>
                    </p>
                    <div class="grid grid-cols-3 gap-4 mt-4">
                        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $next_stats['total_tasks'] ?? 0; ?></p>
                            <p class="text-xs text-gray-600">Total Tasks</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $next_stats['completed_tasks'] ?? 0; ?></p>
                            <p class="text-xs text-gray-600">Completed</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                            <p class="text-2xl font-bold text-gray-900"><?php echo round($next_stats['avg_progress'] ?? 0); ?>%</p>
                            <p class="text-xs text-gray-600">Avg Progress</p>
                        </div>
                    </div>
                </div>

                <!-- Next Week Employee List -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                     Next Week
                    </h3>

                    <?php 
                    if (mysqli_num_rows($next_employees) == 0): 
                    ?>
                        <div class="text-center py-8">
                            <p class="text-gray-400">No tasks scheduled yet</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php while ($employee = mysqli_fetch_assoc($next_employees)): 
                                $tasks_query = "SELECT * FROM employee_tasks 
                                               WHERE user_id = {$employee['id']} 
                                               AND start_date >= '$next_week_start' 
                                               AND start_date <= '$next_week_end'
                                               ORDER BY start_date ASC";
                                $tasks = mysqli_query($conn, $tasks_query);
                                $task_count = mysqli_num_rows($tasks);
                                
                                $status_colors = [
                                    'not_started' => 'bg-gray-100 text-gray-700 border border-gray-300',
                                    'in_progress' => 'bg-gray-700 text-white',
                                    'completed' => 'bg-gray-900 text-white',
                                    'delayed' => 'bg-red-50 text-red-700 border border-red-200'
                                ];
                                $status_labels = [
                                    'not_started' => 'Not Started',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'delayed' => 'Delayed'
                                ];
                            ?>
                                <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                    <div class="bg-gray-50 p-3 cursor-pointer" 
                                         onclick="toggleEmployee('next', <?php echo $employee['id']; ?>)">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-gray-900 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                                    <?php echo strtoupper(substr($employee['username'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-gray-900 text-sm"><?php echo $employee['username']; ?></h4>
                                                    <p class="text-xs text-gray-600"><?php echo $employee['position']; ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <div class="text-center">
                                                    <p class="text-lg font-bold text-gray-900"><?php echo $task_count; ?>/5</p>
                                                    <p class="text-xs text-gray-500">Tasks</p>
                                                </div>
                                                <div class="text-gray-400">
                                                    <svg class="w-5 h-5 transform transition-transform" id="arrow-next-<?php echo $employee['id']; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="employee-next-<?php echo $employee['id']; ?>" class="hidden">
                                        <?php if ($task_count == 0): ?>
                                            <div class="p-4 text-center bg-white">
                                                <p class="text-gray-400 text-sm">No tasks</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="p-3 space-y-2 bg-white">
                                                <?php while ($task = mysqli_fetch_assoc($tasks)): 
                                                    $display_delay_days = 0;
                                                    if ($task['status'] === 'delayed' && isset($task['delay_start_date'])) {
                                                        $display_delay_days = getRemainingDelayDays($task['delay_start_date'], $task['delay_days']);
                                                    }
                                                ?>
                                                    <div class="bg-gray-50 rounded-lg p-3 text-sm border border-gray-200">
                                                        <div class="flex justify-between items-start mb-2">
                                                            <span class="font-semibold text-gray-900"><?php echo $task['task_title']; ?></span>
                                                            <span class="text-xs font-medium px-2 py-1 rounded-full <?php echo $status_colors[$task['status']]; ?>">
                                                                <?php echo $status_labels[$task['status']]; ?>
                                                            </span>
                                                        </div>
                                                        <div class="flex justify-between items-center text-xs text-gray-600">
                                                            <span>
                                                                <?php if ($task['status'] === 'delayed'): ?>
                                                                    <span class="font-bold text-red-600 flex items-center gap-1">
                                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                                        </svg>
                                                                        <?php echo $display_delay_days; ?> days delay
                                                                    </span>
                                                                <?php else: ?>
                                                                    <?php echo date('M d', strtotime($task['start_date'])); ?> - <?php echo date('M d', strtotime($task['end_date'])); ?>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="font-semibold text-gray-900"><?php echo $task['progress_percentage']; ?>%</span>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        function toggleEmployee(week, employeeId) {
            const content = document.getElementById(`employee-${week}-${employeeId}`);
            const arrow = document.getElementById(`arrow-${week}-${employeeId}`);
            
            content.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
        }
    </script>
</body>
</html>