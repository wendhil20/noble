<?php
session_name("nobleemployeereport");
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include '../connection/connect.php';

$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('saturday this week'));

function getWeekStart($date) {
    return date('Y-m-d', strtotime('monday this week', strtotime($date)));
}

function getWeekEnd($date) {
    return date('Y-m-d', strtotime('saturday this week', strtotime($date)));
}

function getWeekTaskCount($conn, $user_id, $week_start, $week_end) {
    $query = "SELECT COUNT(*) as count FROM employee_tasks 
              WHERE user_id = $user_id 
              AND start_date >= '$week_start' 
              AND start_date <= '$week_end'";
    $result = mysqli_fetch_assoc(mysqli_query($conn, $query));
    return $result['count'];
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_task'])) {
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $title = mysqli_real_escape_string($conn, $_POST['task_title']);
        $desc = mysqli_real_escape_string($conn, $_POST['task_description']);
        $end = mysqli_real_escape_string($conn, $_POST['end_date']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $progress = (int)$_POST['progress_percentage'];
        
        $date1 = new DateTime($start_date);
        $date2 = new DateTime($end);
        $estimated_days = $date1->diff($date2)->days + 1;
        
        $delay_days = 0;
        $delay_start_date = null;
        if ($status === 'delayed' && isset($_POST['delay_days'])) {
            $delay_days = (int)$_POST['delay_days'];
            $delay_start_date = date('Y-m-d');
        }
        
        $query = "INSERT INTO employee_tasks 
                  (user_id, task_title, task_description, task_type, start_date, end_date, 
                   estimated_days, status, progress_percentage, delay_days, delay_start_date) 
                  VALUES ({$_SESSION['user_id']}, '$title', '$desc', 'ongoing', '$start_date', '$end', 
                  $estimated_days, '$status', $progress, $delay_days, " . 
                  ($delay_start_date ? "'$delay_start_date'" : "NULL") . ")";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['flash_message'] = "Task added successfully!";
            $_SESSION['flash_type'] = "success";
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['update_task'])) {
        $task_id = (int)$_POST['task_id'];
        $title = mysqli_real_escape_string($conn, $_POST['task_title']);
        $desc = mysqli_real_escape_string($conn, $_POST['task_description']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $progress = (int)$_POST['progress_percentage'];
        $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
        
        $current_task = mysqli_fetch_assoc(mysqli_query($conn, "SELECT delay_start_date FROM employee_tasks WHERE id=$task_id"));
        
        $delay_days = 0;
        $delay_start_date = $current_task['delay_start_date'];
        
        if ($status === 'delayed' && isset($_POST['delay_days'])) {
            $delay_days = (int)$_POST['delay_days'];
            if (!$delay_start_date) {
                $delay_start_date = date('Y-m-d');
            }
        } else if ($status !== 'delayed') {
            $delay_start_date = null;
        }
        
        $query = "UPDATE employee_tasks SET task_title='$title', task_description='$desc', 
                  status='$status', progress_percentage=$progress, 
                  end_date='$end_date', delay_days=$delay_days, 
                  delay_start_date=" . ($delay_start_date ? "'$delay_start_date'" : "NULL") . ", 
                  updated_at=NOW() 
                  WHERE id=$task_id AND user_id={$_SESSION['user_id']}";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['flash_message'] = "Task updated!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_task'])) {
        $task_id = (int)$_POST['task_id'];
        mysqli_query($conn, "DELETE FROM employee_tasks WHERE id=$task_id AND user_id={$_SESSION['user_id']}");
        $_SESSION['flash_message'] = "Task deleted!";
        $_SESSION['flash_type'] = "success";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$current_tasks = mysqli_query($conn, "SELECT * FROM employee_tasks 
                                       WHERE user_id={$_SESSION['user_id']} 
                                       AND start_date >= '$current_week_start' 
                                       AND start_date <= '$current_week_end'
                                       ORDER BY start_date ASC");

$current_task_count = getWeekTaskCount($conn, $_SESSION['user_id'], $current_week_start, $current_week_end);

$stats_query = mysqli_query($conn, "SELECT 
                                     SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed_count,
                                     AVG(progress_percentage) as avg_progress
                                     FROM employee_tasks 
                                     WHERE user_id={$_SESSION['user_id']} 
                                     AND start_date >= '$current_week_start' 
                                     AND start_date <= '$current_week_end'");
$stats = mysqli_fetch_assoc($stats_query);

$upcoming_weeks_query = "SELECT 
                         t.id,
                         t.start_date,
                         t.task_title,
                         t.progress_percentage
                         FROM employee_tasks t
                         WHERE t.user_id={$_SESSION['user_id']} 
                         AND t.start_date > '$current_week_end'
                         ORDER BY t.start_date ASC
                         LIMIT 10";
$upcoming_weeks = mysqli_query($conn, $upcoming_weeks_query);

$previous_weeks_query = "SELECT 
                         MIN(start_date) as week_start,
                         MAX(start_date) as week_end,
                         COUNT(*) as task_count,
                         SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed_count
                         FROM employee_tasks 
                         WHERE user_id={$_SESSION['user_id']} 
                         AND start_date < '$current_week_start'
                         GROUP BY YEARWEEK(start_date, 1)
                         ORDER BY week_start DESC
                         LIMIT 3";
$previous_weeks = mysqli_query($conn, $previous_weeks_query);

$message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$messageType = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Tasks</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <svg class="w-8 h-8 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Weekly Task Report</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Track your weekly progress</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <p class="text-sm font-medium text-gray-900"><?php echo $_SESSION['username']; ?></p>
                    <p class="text-xs text-gray-500">Employee</p>
                </div>
                <a href="logout.php" class="text-sm px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors font-medium flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-full mx-auto px-6 py-6">
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg text-sm shadow-sm <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                <div class="flex items-center gap-2">
                    <?php if ($messageType === 'success'): ?>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    <?php else: ?>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    <?php endif; ?>
                    <span class="font-medium"><?php echo $message; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Week Summary Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <div class="flex items-center gap-3">
                    <svg class="w-8 h-8 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Current Week</h2>
                        <p class="text-gray-600 text-sm">
                            <?php echo date('M d', strtotime($current_week_start)); ?> - 
                            <?php echo date('M d, Y', strtotime($current_week_end)); ?>
                        </p>
                    </div>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-center">
                    <div class="text-3xl font-bold text-gray-900"><?php echo $current_task_count; ?></div>
                    <div class="text-xs text-gray-600">tasks</div>
                </div>
            </div>
            <div class="mt-4 flex gap-6 text-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-gray-600">Completed:</span>
                    <span class="font-semibold text-gray-900"><?php echo $stats['completed_count'] ?? 0; ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span class="text-gray-600">Avg Progress:</span>
                    <span class="font-semibold text-gray-900"><?php echo round($stats['avg_progress'] ?? 0); ?>%</span>
                </div>
            </div>
        </div>

        <!-- Add Button -->
        <div class="mb-6">
            <button onclick="toggleModal('addTaskModal')" 
                    class="bg-gray-900 text-white px-6 py-3 rounded-lg hover:bg-gray-800 shadow-sm hover:shadow-md transition-all duration-200 font-medium flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add New Task
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Current Week Tasks (Left - 2 columns) -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="font-bold text-gray-900 mb-4 text-lg flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 5h6m-6 4h6"></path>
                        </svg>
                        This Week's Tasks
                    </h3>
                    
                    <?php if ($current_task_count == 0): ?>
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500">No tasks scheduled yet</p>
                            <p class="text-xs text-gray-400 mt-1">Click "Add New Task" to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b-2 border-gray-200">
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Task</th>
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Description</th>
                                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-700">Start</th>
                                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-700">End</th>
                                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-700">Duration</th>
                                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-700">Status</th>
                                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-700">Progress</th>
                                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($task = mysqli_fetch_assoc($current_tasks)): 
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
                                        
                                        $display_delay_days = 0;
                                        if ($task['status'] === 'delayed' && $task['delay_start_date']) {
                                            $display_delay_days = getRemainingDelayDays($task['delay_start_date'], $task['delay_days']);
                                        }
                                    ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                            <td class="py-4 px-4">
                                                <span class="font-semibold text-gray-900"><?php echo $task['task_title']; ?></span>
                                            </td>
                                            <td class="py-4 px-4 max-w-[200px] relative">
                                                <span class="block text-sm text-gray-600 whitespace-nowrap overflow-hidden text-ellipsis after:content-[''] after:absolute after:right-0 after:top-0 after:h-full after:w-10 after:bg-gradient-to-r after:from-transparent after:to-white">
                                                    <?php echo htmlspecialchars($task['task_description'] ?: '-'); ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <?php if ($task['status'] === 'delayed'): ?>
                                                    <span class="text-sm text-gray-400">-</span>
                                                <?php else: ?>
                                                    <span class="text-sm text-gray-600"><?php echo date('M d', strtotime($task['start_date'])); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <?php if ($task['status'] === 'delayed'): ?>
                                                    <span class="text-sm text-gray-400">-</span>
                                                <?php else: ?>
                                                    <span class="text-sm text-gray-600"><?php echo date('M d', strtotime($task['end_date'])); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <?php if ($task['status'] === 'delayed'): ?>
                                                    <span class="text-sm font-bold text-red-600 flex items-center justify-center gap-1">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        <?php echo $display_delay_days; ?> day<?php echo $display_delay_days != 1 ? 's' : ''; ?>
                                                    </span>
                                                <?php elseif ($task['status'] === 'completed'): ?>
                                                    <span class="text-sm text-gray-400">-</span>
                                                <?php else: ?>
                                                    <span class="text-sm font-medium text-gray-700"><?php echo $task['estimated_days']; ?> day<?php echo $task['estimated_days'] > 1 ? 's' : ''; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <span class="text-xs font-medium px-2 py-1 rounded-full <?php echo $status_colors[$task['status']]; ?>">
                                                    <?php echo $status_labels[$task['status']]; ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="flex flex-col items-center gap-2">
                                                    <span class="text-sm font-bold px-3 py-1 rounded-full <?php echo $task['progress_percentage'] == 100 ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700'; ?>">
                                                        <?php echo $task['progress_percentage']; ?>%
                                                    </span>
                                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                                        <div class="<?php echo $task['progress_percentage'] == 100 ? 'bg-gray-900' : 'bg-gray-700'; ?> h-2 rounded-full transition-all duration-300" 
                                                             style="width: <?php echo $task['progress_percentage']; ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="flex gap-2 justify-center">
                                                    <button onclick="editTask(<?php echo htmlspecialchars(json_encode($task)); ?>)" 
                                                            class="text-xs px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors font-medium border border-gray-300">
                                                        Update
                                                    </button>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <button type="submit" name="delete_task" 
                                                                onclick="return confirm('Delete this task?')"
                                                                class="text-xs px-3 py-1.5 bg-red-50 text-red-600 rounded-md hover:bg-red-100 transition-colors font-medium border border-red-200">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-4">
                <!-- Upcoming Weeks -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h3 class="font-semibold text-gray-900 mb-3 text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        Upcoming Tasks
                    </h3>
                    <?php 
                    mysqli_data_seek($upcoming_weeks, 0);
                    if (mysqli_num_rows($upcoming_weeks) > 0): 
                    ?>
                        <div class="space-y-2">
                            <?php while ($task = mysqli_fetch_assoc($upcoming_weeks)): 
                                $full_task_query = "SELECT * FROM employee_tasks WHERE id = {$task['id']} LIMIT 1";
                                $full_task_result = mysqli_query($conn, $full_task_query);
                                $full_task = mysqli_fetch_assoc($full_task_result);
                            ?>
                                <div class="bg-gray-50 border border-gray-200 rounded p-2.5 hover:border-gray-300 transition-colors">
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-medium text-gray-900 truncate"><?php echo $task['task_title']; ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                <?php echo date('M d, Y', strtotime($task['start_date'])); ?>
                                            </p>
                                        </div>
                                        <span class="text-xs text-gray-600 whitespace-nowrap">
                                            <?php echo $task['progress_percentage']; ?>%
                                        </span>
                                    </div>
                                    <div class="flex gap-1 mt-2">
                                        <button onclick="editTask(<?php echo htmlspecialchars(json_encode($full_task)); ?>, true)" 
                                                class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors font-medium border border-gray-300 flex-1">
                                            Update
                                        </button>
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <button type="submit" name="delete_task" 
                                                    onclick="return confirm('Delete this upcoming task?')"
                                                    class="w-full text-xs px-2 py-1 bg-red-50 text-red-600 rounded hover:bg-red-100 transition-colors font-medium border border-red-200">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-400 text-xs py-4">No upcoming tasks</p>
                    <?php endif; ?>
                </div>

                <!-- Previous Weeks -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h3 class="font-semibold text-gray-900 mb-3 text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Previous Weeks
                    </h3>
                    <?php if (mysqli_num_rows($previous_weeks) > 0): ?>
                        <div class="space-y-2">
                            <?php while ($week = mysqli_fetch_assoc($previous_weeks)): ?>
                                <div class="bg-gray-50 border border-gray-200 rounded p-2.5 hover:border-gray-300 transition-colors">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs font-medium text-gray-700">
                                            <?php echo date('M d', strtotime($week['week_start'])); ?> - 
                                            <?php echo date('M d', strtotime($week['week_end'])); ?>
                                        </span>
                                        <span class="text-xs text-gray-600">
                                            <?php echo $week['completed_count']; ?>/<?php echo $week['task_count']; ?> completed
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-400 text-xs py-4">No previous weeks</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div id="addTaskModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full">
            <h2 class="text-xl font-bold text-gray-900 mb-5">Add New Task</h2>
            <form method="POST" onsubmit="return validateTaskForm()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Task Title *</label>
                        <input type="text" name="task_title" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <textarea name="task_description" rows="3" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Start Date *</label>
                            <input type="date" name="start_date" id="start_date" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">End Date *</label>
                            <input type="date" name="end_date" id="end_date" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Status *</label>
                        <select name="status" id="add_status" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress" selected>In Progress</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>
                    <div id="add_delay_field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Delay Days *</label>
                        <input type="number" name="delay_days" id="add_delay_days" min="1" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                        <p class="text-xs text-gray-500 mt-1">How many days delayed? (Will countdown daily)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Progress (%)</label>
                        <input type="number" name="progress_percentage" min="0" max="100" value="0" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" name="add_task" class="flex-1 bg-gray-900 text-white py-2.5 rounded-lg font-medium hover:bg-gray-800 transition-colors">
                        Add Task
                    </button>
                    <button type="button" onclick="toggleModal('addTaskModal')" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editTaskModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full">
            <h2 class="text-xl font-bold text-gray-900 mb-5">Update Task</h2>
            <form method="POST">
                <input type="hidden" name="task_id" id="edit_task_id">
                <input type="hidden" id="edit_task_start_date">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Task Title *</label>
                        <input type="text" name="task_title" id="edit_task_title" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <textarea name="task_description" id="edit_task_description" rows="3" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Status *</label>
                        <select name="status" id="edit_status" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>
                    <div id="edit_delay_field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Delay Days *</label>
                        <input type="number" name="delay_days" id="edit_delay_days" min="1" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                        <p class="text-xs text-gray-500 mt-1">Update delay days (Will countdown from today)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Progress (%) *</label>
                        <input type="number" name="progress_percentage" id="edit_progress" min="0" max="100" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">End Date *</label>
                        <input type="date" name="end_date" id="edit_end_date" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none transition-colors">
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                        <svg class="w-4 h-4 text-amber-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <p class="text-xs text-amber-700">Can only update tasks from current week (<?php echo date('M d', strtotime($current_week_start)); ?> - <?php echo date('M d', strtotime($current_week_end)); ?>)</p>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" name="update_task" id="updateTaskBtn" class="flex-1 bg-gray-900 text-white py-2.5 rounded-lg font-medium hover:bg-gray-800 transition-colors">
                        Update
                    </button>
                    <button type="button" onclick="toggleModal('editTaskModal')" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(id) {
            document.getElementById(id).classList.toggle('hidden');
        }

        function editTask(task, isUpcoming = false) {
            const currentWeekStart = '<?php echo $current_week_start; ?>';
            const taskStartDate = new Date(task.start_date);
            const currentWeekStartDate = new Date(currentWeekStart);
            taskStartDate.setHours(0, 0, 0, 0);
            currentWeekStartDate.setHours(0, 0, 0, 0);
            
            if (!isUpcoming && taskStartDate < currentWeekStartDate) {
                alert('Cannot update tasks from previous weeks!\nTask started: ' + formatDate(task.start_date));
                return;
            }
            
            document.getElementById('edit_task_id').value = task.id;
            document.getElementById('edit_task_start_date').value = task.start_date;
            document.getElementById('edit_task_title').value = task.task_title;
            document.getElementById('edit_task_description').value = task.task_description || '';
            document.getElementById('edit_status').value = task.status;
            document.getElementById('edit_progress').value = task.progress_percentage;
            document.getElementById('edit_end_date').value = task.end_date;
            document.getElementById('edit_delay_days').value = task.delay_days || '';
            
            toggleDelayField('edit', task.status);
            
            const endDateInput = document.getElementById('edit_end_date');
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            endDateInput.setAttribute('min', todayStr);
            
            const warningDiv = document.querySelector('#editTaskModal .bg-amber-50');
            if (isUpcoming) {
                warningDiv.innerHTML = `
                    <svg class="w-4 h-4 text-amber-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-xs text-amber-700">Editing upcoming task from future week</p>
                `;
            } else {
                warningDiv.innerHTML = `
                    <svg class="w-4 h-4 text-amber-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-xs text-amber-700">Can only update tasks from current week (<?php echo date('M d', strtotime($current_week_start)); ?> - <?php echo date('M d', strtotime($current_week_end)); ?>)</p>
                `;
            }
            
            toggleModal('editTaskModal');
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const options = { month: 'short', day: 'numeric', year: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        function toggleDelayField(prefix, status) {
            const delayField = document.getElementById(prefix + '_delay_field');
            const delayInput = document.getElementById(prefix + '_delay_days');
            
            if (status === 'delayed') {
                delayField.classList.remove('hidden');
                delayInput.setAttribute('required', 'required');
            } else {
                delayField.classList.add('hidden');
                delayInput.removeAttribute('required');
                delayInput.value = '';
            }
        }

        function isSunday(dateString) {
            const date = new Date(dateString);
            return date.getDay() === 0;
        }

        function validateTaskForm() {
            const startDate = document.getElementById('start_date').value;
            if (isSunday(startDate)) {
                alert('Cannot add tasks on Sunday! Choose Monday-Saturday only.');
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            const today = new Date().toISOString().split('T')[0];
            if (startDateInput) {
                startDateInput.setAttribute('min', today);
            }
            if (endDateInput) {
                endDateInput.setAttribute('min', today);
            }
            
            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    const startDate = new Date(this.value);
                    if (startDate.getDay() === 0) {
                        alert('Sundays are not allowed!');
                        this.value = '';
                        return;
                    }
                    const endDate = new Date(startDate);
                    endDate.setDate(endDate.getDate() + 7);
                    endDateInput.value = endDate.toISOString().split('T')[0];
                    endDateInput.setAttribute('min', this.value);
                });
            }
            
            const addStatusSelect = document.getElementById('add_status');
            const editStatusSelect = document.getElementById('edit_status');
            
            if (addStatusSelect) {
                addStatusSelect.addEventListener('change', function() {
                    toggleDelayField('add', this.value);
                });
            }
            
            if (editStatusSelect) {
                editStatusSelect.addEventListener('change', function() {
                    toggleDelayField('edit', this.value);
                });
            }
        });
    </script>
</body>
</html>