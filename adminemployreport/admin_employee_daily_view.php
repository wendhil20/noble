<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Daily Tasks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <style>
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .dot-not_started { background-color: #F59E0B; }
        .dot-in_progress { background-color: #3B82F6; }
        .dot-completed { background-color: #10B981; }
        .dot-delayed { background-color: #EF4444; animation: pulse-dot 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3B82F6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .8; }
        }

        .day-column {
            min-height: 300px;
        }

        .task-card {
            transition: all 0.2s;
        }

        .task-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="max-w-[1600px] mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <button onclick="goBack()" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <div class="flex items-center gap-3">
                        <div id="employeeAvatar" class="w-12 h-12 bg-gray-900 rounded-full flex items-center justify-center text-white font-bold"></div>
                        <div>
                            <h1 id="employeeName" class="text-2xl font-bold text-gray-900"></h1>
                            <p id="employeePosition" class="text-sm text-gray-500"></p>
                        </div>
                    </div>
                </div>
                <button onclick="refreshData()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-[1600px] mx-auto px-6 py-6">
        <!-- Loading State -->
        <div id="loadingState" class="flex justify-center items-center py-20">
            <div class="loading-spinner"></div>
        </div>

        <!-- Content Container -->
        <div id="contentContainer" class="hidden">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                    <p class="text-sm text-gray-600 mb-1">Total Tasks</p>
                    <p id="statTotal" class="text-3xl font-bold text-gray-900">0</p>
                </div>
                <div class="bg-green-50 rounded-lg shadow-sm p-4 border border-green-200">
                    <p class="text-sm text-green-700 mb-1">Completed</p>
                    <p id="statCompleted" class="text-3xl font-bold text-green-700">0</p>
                </div>
                <div class="bg-blue-50 rounded-lg shadow-sm p-4 border border-blue-200">
                    <p class="text-sm text-blue-700 mb-1">In Progress</p>
                    <p id="statInProgress" class="text-3xl font-bold text-blue-700">0</p>
                </div>
                <div class="bg-yellow-50 rounded-lg shadow-sm p-4 border border-yellow-200">
                    <p class="text-sm text-yellow-700 mb-1">Not Started</p>
                    <p id="statNotStarted" class="text-3xl font-bold text-yellow-700">0</p>
                </div>
                <div class="bg-red-50 rounded-lg shadow-sm p-4 border border-red-200">
                    <p class="text-sm text-red-700 mb-1">Delayed</p>
                    <p id="statDelayed" class="text-3xl font-bold text-red-700">0</p>
                </div>
                <div class="bg-orange-50 rounded-lg shadow-sm p-4 border border-orange-200">
                    <p class="text-sm text-orange-700 mb-1">Rolled Over</p>
                    <p id="statRolledOver" class="text-3xl font-bold text-orange-700">0</p>
                </div>
            </div>

            <!-- Incomplete Tasks Calendar View -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Weekly Task Calendar (Incomplete Tasks)
                </h2>
                
                <!-- Week Navigation -->
                <div class="flex justify-between items-center mb-4">
                    <button onclick="previousWeek()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center gap-2 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Previous
                    </button>
                    <div class="flex items-center gap-3">
                        <h3 id="weekRange" class="text-lg font-semibold text-gray-700"></h3>
                        <button onclick="goToToday()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
                            Today
                        </button>
                    </div>
                    <button onclick="nextWeek()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center gap-2 transition-colors">
                        Next
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>

                <!-- Calendar Grid -->
                <div id="calendarGrid" class="grid grid-cols-6 gap-4">
                    <!-- Will be populated by JS -->
                </div>
            </div>

            <!-- Completed Tasks Calendar View -->
            <div class="bg-green-50 rounded-xl shadow-sm p-6 border-2 border-green-200">
                <h2 class="text-xl font-bold text-green-900 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Completed Tasks Calendar
                </h2>
                
                <!-- Week Navigation for Completed -->
                <div class="flex justify-between items-center mb-4">
                    <button onclick="previousWeekCompleted()" class="px-4 py-2 bg-white hover:bg-gray-100 rounded-lg flex items-center gap-2 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Previous
                    </button>
                    <div class="flex items-center gap-3">
                        <h3 id="weekRangeCompleted" class="text-lg font-semibold text-green-800"></h3>
                        <button onclick="goToTodayCompleted()" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-colors">
                            Today
                        </button>
                    </div>
                    <button onclick="nextWeekCompleted()" class="px-4 py-2 bg-white hover:bg-gray-100 rounded-lg flex items-center gap-2 transition-colors">
                        Next
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>

                <!-- Completed Calendar Grid -->
                <div id="calendarGridCompleted" class="grid grid-cols-6 gap-4">
                    <!-- Will be populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <script>
        const statusColors = {
            'not_started': 'bg-yellow-100 text-yellow-800 border-yellow-300',
            'in_progress': 'bg-blue-100 text-blue-800 border-blue-300',
            'completed': 'bg-green-100 text-green-800 border-green-300',
            'delayed': 'bg-red-100 text-red-800 border-red-300 animate-pulse-slow'
        };

        const statusLabels = {
            'not_started': 'Not Started',
            'in_progress': 'In Progress',
            'completed': 'Completed',
            'delayed': 'Delayed'
        };

        let allTasksData = {};
        let currentWeekStart = new Date();
        let currentWeekStartCompleted = new Date();

        const urlParams = new URLSearchParams(window.location.search);
        const employeeId = urlParams.get('employee_id');

        if (!employeeId) {
            alert('No employee selected');
            goBack();
        }

        // Helper function to get local date string without timezone conversion
        function getLocalDateString(date = new Date()) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        currentWeekStart.setDate(currentWeekStart.getDate() - (currentWeekStart.getDay() || 7) + 1);
        currentWeekStart.setHours(0, 0, 0, 0);
        
        currentWeekStartCompleted.setDate(currentWeekStartCompleted.getDate() - (currentWeekStartCompleted.getDay() || 7) + 1);
        currentWeekStartCompleted.setHours(0, 0, 0, 0);

        document.addEventListener('DOMContentLoaded', () => {
            loadData();
        });

        function goBack() {
            window.history.back();
        }

        async function loadData() {
            try {
                document.getElementById('loadingState').classList.remove('hidden');
                document.getElementById('contentContainer').classList.add('hidden');

                const response = await fetch(`admin_employee_daily_tasks.php?employee_id=${employeeId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }

                renderEmployeeInfo(data.employee);
                renderStatistics(data.statistics);
                
                allTasksData = data.tasks_by_date;
                renderCalendar();
                renderCompletedCalendar();

                document.getElementById('loadingState').classList.add('hidden');
                document.getElementById('contentContainer').classList.remove('hidden');
            } catch (error) {
                console.error('Error loading data:', error);
                alert('Failed to load employee data: ' + error.message);
            }
        }

        function refreshData() {
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = `
                <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refreshing...
            `;
            btn.disabled = true;
            
            loadData().finally(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
        }

        function renderEmployeeInfo(employee) {
            document.getElementById('employeeName').textContent = employee.username.toUpperCase();
            document.getElementById('employeePosition').textContent = employee.position;
            document.getElementById('employeeAvatar').textContent = employee.username.substring(0, 2).toUpperCase();
        }

        function renderStatistics(stats) {
            document.getElementById('statTotal').textContent = stats.total_tasks || 0;
            document.getElementById('statCompleted').textContent = stats.completed_tasks || 0;
            document.getElementById('statInProgress').textContent = stats.in_progress_tasks || 0;
            document.getElementById('statNotStarted').textContent = stats.not_started_tasks || 0;
            document.getElementById('statDelayed').textContent = stats.delayed_tasks || 0;
            document.getElementById('statRolledOver').textContent = stats.rolled_over_tasks || 0;
        }

        function previousWeek() {
            currentWeekStart.setDate(currentWeekStart.getDate() - 7);
            renderCalendar();
        }

        function nextWeek() {
            currentWeekStart.setDate(currentWeekStart.getDate() + 7);
            renderCalendar();
        }

        function goToToday() {
            const today = new Date();
            currentWeekStart = new Date(today);
            currentWeekStart.setDate(currentWeekStart.getDate() - (currentWeekStart.getDay() || 7) + 1);
            currentWeekStart.setHours(0, 0, 0, 0);
            renderCalendar();
        }

        function previousWeekCompleted() {
            currentWeekStartCompleted.setDate(currentWeekStartCompleted.getDate() - 7);
            renderCompletedCalendar();
        }

        function nextWeekCompleted() {
            currentWeekStartCompleted.setDate(currentWeekStartCompleted.getDate() + 7);
            renderCompletedCalendar();
        }

        function goToTodayCompleted() {
            const today = new Date();
            currentWeekStartCompleted = new Date(today);
            currentWeekStartCompleted.setDate(currentWeekStartCompleted.getDate() - (currentWeekStartCompleted.getDay() || 7) + 1);
            currentWeekStartCompleted.setHours(0, 0, 0, 0);
            renderCompletedCalendar();
        }

        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const weekRange = document.getElementById('weekRange');
            
            const weekEnd = new Date(currentWeekStart);
            weekEnd.setDate(weekEnd.getDate() + 5);
            
            weekRange.textContent = `${currentWeekStart.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${weekEnd.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            
            let html = '';
            const today = getLocalDateString();
            
            for (let i = 0; i < 6; i++) {
                const date = new Date(currentWeekStart);
                date.setDate(date.getDate() + i);
                const dateStr = getLocalDateString(date);
                const isToday = dateStr === today;
                
                const dayName = date.toLocaleDateString('en-US', { weekday: 'short' });
                const dayNum = date.getDate();
                
                const allTasks = allTasksData[dateStr] || [];
                const incompleteTasks = allTasks.filter(task => 
                    task.progress_percentage < 100 && task.status !== 'completed'
                );
                const uniqueTasks = Array.from(new Map(
                    incompleteTasks.map(task => [task.id, task])
                ).values());
                
                html += `
                    <div class="day-column border-2 ${isToday ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white'} rounded-lg overflow-hidden">
                        <div class="${isToday ? 'bg-blue-600' : 'bg-gray-800'} text-white p-3 text-center">
                            <div class="text-xs font-semibold uppercase">${dayName}</div>
                            <div class="text-2xl font-bold">${dayNum}</div>
                            ${isToday ? '<div class="text-xs mt-1 bg-blue-500 rounded px-2 py-0.5">TODAY</div>' : ''}
                        </div>
                        <div class="p-3 space-y-2 max-h-[700px] overflow-y-auto">
                            ${uniqueTasks.length > 0 ? uniqueTasks.map(task => renderTaskCard(task)).join('') : '<p class="text-xs text-gray-400 text-center py-4">No tasks</p>'}
                        </div>
                    </div>
                `;
            }
            
            grid.innerHTML = html;
        }

        function renderCompletedCalendar() {
            const grid = document.getElementById('calendarGridCompleted');
            const weekRange = document.getElementById('weekRangeCompleted');
            
            const weekEnd = new Date(currentWeekStartCompleted);
            weekEnd.setDate(weekEnd.getDate() + 5);
            
            weekRange.textContent = `${currentWeekStartCompleted.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${weekEnd.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            
            let html = '';
            const today = getLocalDateString();
            
            for (let i = 0; i < 6; i++) {
                const date = new Date(currentWeekStartCompleted);
                date.setDate(date.getDate() + i);
                const dateStr = getLocalDateString(date);
                const isToday = dateStr === today;
                
                const dayName = date.toLocaleDateString('en-US', { weekday: 'short' });
                const dayNum = date.getDate();
                
                const allTasks = allTasksData[dateStr] || [];
                const completedTasks = allTasks.filter(task => 
                    task.progress_percentage == 100 || task.status === 'completed'
                );
                const uniqueTasks = Array.from(new Map(
                    completedTasks.map(task => [task.id, task])
                ).values());
                
                html += `
                    <div class="day-column border-2 ${isToday ? 'border-green-600 bg-green-100' : 'border-green-300 bg-white'} rounded-lg overflow-hidden">
                        <div class="${isToday ? 'bg-green-600' : 'bg-green-700'} text-white p-3 text-center">
                            <div class="text-xs font-semibold uppercase">${dayName}</div>
                            <div class="text-2xl font-bold">${dayNum}</div>
                            ${isToday ? '<div class="text-xs mt-1 bg-green-500 rounded px-2 py-0.5">TODAY</div>' : ''}
                        </div>
                        <div class="p-3 space-y-2 max-h-[700px] overflow-y-auto">
                            ${uniqueTasks.length > 0 ? uniqueTasks.map(task => renderCompletedTaskCard(task)).join('') : '<p class="text-xs text-gray-400 text-center py-4">No completed tasks</p>'}
                        </div>
                    </div>
                `;
            }
            
            grid.innerHTML = html;
        }

      function renderTaskCard(task) {
    const endDate = new Date(task.end_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
    const isRolledOver = task.is_rolled_over == 1;
    
    // Calculate remaining days for in_progress tasks
    let durationDisplay = '';
    if (task.status === 'in_progress' && task.estimated_days) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const end = new Date(task.end_date);
        end.setHours(0, 0, 0, 0);
        
        const timeDiff = end - today;
        const remainingDays = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
        
        if (remainingDays > 0) {
            durationDisplay = `${remainingDays}d left`;
        } else if (remainingDays === 0) {
            durationDisplay = 'Due today';
        } else {
            durationDisplay = `${Math.abs(remainingDays)}d overdue`;
        }
    } else if (task.estimated_days) {
        durationDisplay = `${task.estimated_days}d`;
    }
    
    return `
        <div class="task-card bg-white rounded-lg border ${isRolledOver ? 'border-orange-300 bg-orange-50' : statusColors[task.status]} shadow-sm p-2.5 hover:shadow-md">
            <div class="flex items-start justify-between gap-1 mb-1.5">
                <div class="flex items-center gap-1 flex-1">
                    ${isRolledOver ? `
                        <svg class="w-3.5 h-3.5 text-orange-700 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                        </svg>
                    ` : ''}
                    <h4 class="font-bold text-gray-900 text-[11px] uppercase leading-tight flex-1">${task.task_title}</h4>
                </div>
                <span class="status-badge ${isRolledOver ? 'bg-orange-100 text-orange-800' : statusColors[task.status]} flex-shrink-0 text-[10px] px-1.5 py-0.5">
                    <div class="status-dot ${isRolledOver ? 'bg-orange-600' : 'dot-' + task.status}"></div>
                    ${isRolledOver ? 'OVERDUE' : statusLabels[task.status].split(' ')[0]}
                </span>
            </div>
            
            <p class="text-[10px] text-gray-600 mb-2 line-clamp-1">${task.task_description || 'No description'}</p>
            
            <div class="flex items-center justify-between text-[10px]">
                ${durationDisplay ? `
                    <span class="text-gray-600 flex items-center gap-0.5 ${task.status === 'in_progress' ? 'font-semibold text-blue-700' : ''}">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        ${durationDisplay}
                    </span>
                ` : '<span></span>'}
                
                <span class="font-bold text-gray-900">${task.progress_percentage}%</span>
                
                ${isRolledOver ? `
                    <span class="font-semibold text-orange-700 flex items-center gap-0.5">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                        </svg>
                        Overdue
                    </span>
                ` : (task.status === 'delayed' && task.display_delay_days > 0 ? `
                    <span class="font-semibold text-red-600 flex items-center gap-0.5">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        +${task.display_delay_days}d
                    </span>
                ` : `<span class="text-gray-500 text-[9px]">End: ${endDate}</span>`)}
            </div>
        </div>
    `;
}

        function renderCompletedTaskCard(task) {
            const endDate = new Date(task.end_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            const completedDate = task.completed_date ? new Date(task.completed_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : 'Unknown';
            return `
                <div class="task-card bg-white rounded-lg border-2 border-green-300 shadow-sm p-2.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-1 mb-1.5">
                        <div class="flex items-center gap-1 flex-1">
                            <svg class="w-3.5 h-3.5 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <h4 class="font-bold text-gray-900 text-[11px] uppercase leading-tight flex-1">${task.task_title}</h4>
                        </div>
                        <span class="status-badge bg-green-100 text-green-800 border-green-300 flex-shrink-0 text-[10px] px-1.5 py-0.5">
                            <div class="status-dot bg-green-600"></div>
                            DONE
                        </span>
                    </div>
                    
                    <p class="text-[10px] text-gray-600 mb-2 line-clamp-1">${task.task_description || 'No description'}</p>
                    
                    <div class="flex items-center justify-between text-[10px]">
                        ${task.estimated_days ? `
                            <span class="text-gray-600 flex items-center gap-0.5">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                ${task.estimated_days}d
                            </span>
                        ` : '<span></span>'}
                        
                        <span class="font-bold text-green-700 flex items-center gap-0.5">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            100%
                        </span>
                        
                        <span class="text-green-700 font-semibold text-[9px]">✓ ${completedDate}</span>
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>