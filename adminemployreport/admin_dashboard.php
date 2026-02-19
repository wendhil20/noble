<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - All Employee Tasks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <style>
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        .status-dot {
            width: 8px;
            height: 8px;
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

        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .8; }
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

        /* Custom Scrollbar */
        .overflow-y-auto::-webkit-scrollbar {
            width: 8px;
        }

        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
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
                <button onclick="refreshData()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-6">
        <!-- Loading State -->
        <div id="loadingState" class="flex justify-center items-center py-20">
            <div class="loading-spinner"></div>
        </div>

        <!-- Content Container -->
        <div id="contentContainer" class="hidden">
            <!-- ALL EMPLOYEES OVERVIEW SECTION -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    All Employees Task Overview
                </h2>

                <!-- Color Legend -->
                <div class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-sm font-semibold text-gray-700 mb-3">Status Color Guide:</p>
                    <div class="flex flex-wrap gap-4 text-xs">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-green-100 border-2 border-green-500 rounded-lg flex items-center justify-center shadow-sm">
                                <div class="status-dot dot-completed"></div>
                            </div>
                            <span class="text-gray-700 font-medium">Completed</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-blue-100 border-2 border-blue-500 rounded-lg flex items-center justify-center shadow-sm">
                                <div class="status-dot dot-in_progress"></div>
                            </div>
                            <span class="text-gray-700 font-medium">In Progress</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-yellow-100 border-2 border-yellow-500 rounded-lg flex items-center justify-center shadow-sm">
                                <div class="status-dot dot-not_started"></div>
                            </div>
                            <span class="text-gray-700 font-medium">Not Started</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-red-100 border-2 border-red-500 rounded-lg flex items-center justify-center shadow-sm">
                                <div class="status-dot dot-delayed"></div>
                            </div>
                            <span class="text-gray-700 font-medium">Delayed</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-orange-100 border-2 border-orange-400 rounded-lg flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-orange-700" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700 font-medium">Rolled Over</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-900 text-white">
                                <th class="px-4 py-3 text-left text-sm font-semibold">Employee</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Position</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Completed</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">In Progress</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Not Started</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Delayed</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Rolled Over</th>
                            </tr>
                        </thead>
                        <tbody id="allEmployeesTable"></tbody>
                    </table>
                </div>
            </div>

            <!-- Two Column Layout: Current Week & Next Week -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Current Week Section -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Current Week
                    </h3>
                    <div id="currentWeekEmployees" class="space-y-3"></div>
                </div>

                <!-- Next Week Section -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                        Next Week
                    </h3>
                    <div id="nextWeekEmployees" class="space-y-3"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const statusColors = {
            'not_started': 'bg-yellow-100 text-yellow-800 border-2 border-yellow-500',
            'in_progress': 'bg-blue-100 text-blue-800 border-2 border-blue-500',
            'completed': 'bg-green-100 text-green-800 border-2 border-green-500',
            'delayed': 'bg-red-100 text-red-800 border-2 border-red-500 animate-pulse-slow'
        };

        const statusLabels = {
            'not_started': 'Not Started',
            'in_progress': 'In Progress',
            'completed': 'Completed',
            'delayed': 'Delayed'
        };

        const progressColors = {
            'not_started': 'bg-yellow-500',
            'in_progress': 'bg-blue-500',
            'completed': 'bg-green-500',
            'delayed': 'bg-red-500'
        };

        const borderColors = {
            'not_started': 'border-yellow-200',
            'in_progress': 'border-blue-200',
            'completed': 'border-green-200',
            'delayed': 'border-red-200'
        };

        document.addEventListener('DOMContentLoaded', () => {
            loadData();
            setInterval(() => loadData(true), 5000);
        });

        let expandedEmployees = new Set();
        let expandedTasks = new Set();

        async function loadData(isRefresh = false) {
            try {
                if (isRefresh) {
                    saveExpandedState();
                }

                if (!isRefresh) {
                    document.getElementById('loadingState').classList.remove('hidden');
                    document.getElementById('contentContainer').classList.add('hidden');
                }
                
                console.log('Fetching data...', new Date().toLocaleTimeString());
                
                const response = await fetch('admin_ajax_employe.php', {
                    method: 'GET',
                    cache: 'no-cache',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Data loaded:', data);
                
                renderAllEmployees(data.all_employees);
                renderCurrentWeek(data.current_week);
                renderNextWeek(data.next_week);
                
                if (isRefresh) {
                    restoreExpandedState();
                }
                
                document.getElementById('loadingState').classList.add('hidden');
                document.getElementById('contentContainer').classList.remove('hidden');
            } catch (error) {
                console.error('Error loading data:', error);
                document.getElementById('loadingState').classList.add('hidden');
                document.getElementById('contentContainer').classList.remove('hidden');
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                errorDiv.innerHTML = `
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">Failed to load data. Will retry automatically.</span>
                `;
                document.querySelector('.max-w-7xl').prepend(errorDiv);
                
                setTimeout(() => errorDiv.remove(), 5000);
            }
        }

        function saveExpandedState() {
            expandedEmployees.clear();
            expandedTasks.clear();

            document.querySelectorAll('[id^="employee-"]').forEach(el => {
                if (!el.classList.contains('hidden')) {
                    expandedEmployees.add(el.id);
                }
            });

            document.querySelectorAll('[id^="task-desc-"]').forEach(el => {
                if (!el.classList.contains('hidden')) {
                    expandedTasks.add(el.id);
                }
            });

            console.log('Saved state:', {
                employees: Array.from(expandedEmployees),
                tasks: Array.from(expandedTasks)
            });
        }

        function restoreExpandedState() {
            expandedEmployees.forEach(id => {
                const content = document.getElementById(id);
                const arrowId = id.replace('employee-', 'arrow-');
                const arrow = document.getElementById(arrowId);
                
                if (content && arrow) {
                    content.classList.remove('hidden');
                    arrow.classList.add('rotate-180');
                }
            });

            expandedTasks.forEach(id => {
                const content = document.getElementById(id);
                const arrowId = id.replace('task-desc-', 'task-arrow-');
                const arrow = document.getElementById(arrowId);
                
                if (content && arrow) {
                    content.classList.remove('hidden');
                    arrow.classList.add('rotate-90');
                }
            });

            console.log('Restored state');
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
            
            loadData(true).finally(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
        }

        function renderAllEmployees(employees) {
            const tbody = document.getElementById('allEmployeesTable');
            tbody.innerHTML = employees.map(emp => `
                <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gray-900 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                ${emp.username.substring(0, 2).toUpperCase()}
                            </div>
                            <span class="font-semibold text-gray-900 uppercase cursor-pointer hover:text-blue-600 hover:underline transition-colors" 
                                  onclick="window.location.href='admin_employee_daily_view.php?employee_id=${emp.id}'">
                                ${emp.username}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-center text-sm text-gray-600">${emp.position}</td>

                    <td class="px-4 py-4 text-center">
                        <span class="inline-flex items-center justify-center w-12 h-12 bg-green-100 rounded-lg font-bold text-green-700 border-2 border-green-500 shadow-sm">
                            ${emp.completed_tasks}
                        </span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="inline-flex items-center justify-center w-12 h-12 bg-blue-100 rounded-lg font-bold text-blue-700 border-2 border-blue-500 shadow-sm">
                            ${emp.in_progress_tasks}
                        </span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="inline-flex items-center justify-center w-12 h-12 bg-yellow-100 rounded-lg font-bold text-yellow-700 border-2 border-yellow-500 shadow-sm">
                            ${emp.not_started_tasks}
                        </span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        ${emp.delayed_tasks > 0 ? `
                            <span class="inline-flex items-center justify-center w-12 h-12 bg-red-100 rounded-lg font-bold text-red-700 border-2 border-red-500 shadow-sm animate-pulse-slow">
                                ${emp.delayed_tasks}
                            </span>
                        ` : `
                            <span class="inline-flex items-center justify-center w-12 h-12 bg-gray-50 rounded-lg font-bold text-gray-400 border-2 border-gray-200">
                                0
                            </span>
                        `}
                    </td>
                    <td class="px-4 py-4 text-center">
                        ${emp.rolled_over_tasks > 0 ? `
                            <span class="inline-flex items-center justify-center w-12 h-12 bg-orange-100 rounded-lg font-bold text-orange-700 border-2 border-orange-400 shadow-sm">
                                ${emp.rolled_over_tasks}
                            </span>
                        ` : `
                            <span class="inline-flex items-center justify-center w-12 h-12 bg-gray-50 rounded-lg font-bold text-gray-400 border-2 border-gray-200">
                                0
                            </span>
                        `}
                    </td>
                </tr>
            `).join('');
        }

        function renderCurrentWeek(weekData) {
            const container = document.getElementById('currentWeekEmployees');
            
            if (weekData.length === 0) {
                container.innerHTML = '<div class="text-center py-8"><p class="text-gray-400">No employees found</p></div>';
                return;
            }

            container.innerHTML = weekData.map(item => renderEmployeeCard(item, 'current')).join('');
        }

        function renderNextWeek(weekData) {
            const container = document.getElementById('nextWeekEmployees');
            
            if (weekData.length === 0) {
                container.innerHTML = '<div class="text-center py-8"><p class="text-gray-400">No tasks scheduled</p></div>';
                return;
            }

            container.innerHTML = weekData.map(item => renderEmployeeCard(item, 'next')).join('');
        }

        function renderEmployeeCard(item, prefix) {
            const empId = `${prefix}-${item.employee.id}`;
            return `
                <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                    <div class="bg-gray-50 p-3 cursor-pointer" onclick="toggleEmployee('${empId}')">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-900 rounded-full flex items-center justify-center text-white font-bold text-sm ">
                                    ${item.employee.username.substring(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm uppercase">${item.employee.username}</h4>
                                    <p class="text-xs text-gray-600">${item.employee.position}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-center">
                                    <p class="text-lg font-bold text-gray-900">${item.task_count}</p>
                                    <p class="text-xs text-gray-500">Tasks</p>
                                </div>
                                <div class="text-gray-400">
                                    <svg class="w-5 h-5 transform transition-transform" id="arrow-${empId}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="employee-${empId}" class="hidden">
                        ${item.tasks.length === 0 ? `
                            <div class="p-4 text-center bg-white">
                                <p class="text-gray-400 text-sm">No tasks</p>
                            </div>
                        ` : `
                            <div class="p-3 space-y-2 bg-white max-h-[600px] overflow-y-auto">
                                ${item.tasks.map(task => renderTask(task, prefix)).join('')}
                            </div>
                        `}
                    </div>
                </div>
            `;
        }

        function renderTask(task, prefix) {
            const taskId = `${prefix}-${task.id}`;
            const duration = task.estimated_days || 0;
            const isRolledOver = task.is_rolled_over == 1;
            const rowBorderClass = isRolledOver ? 'border-orange-400' : borderColors[task.status];
            const rowBgClass = isRolledOver ? 'bg-orange-50' : 'bg-gray-50';
            
            return `
                <div class="${rowBgClass} rounded-lg border-2 ${rowBorderClass} overflow-hidden">
                    <div class="p-3 cursor-pointer hover:opacity-90 transition-opacity" onclick="toggleTask('${taskId}')">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center gap-2 flex-1">
                                <svg class="w-4 h-4 text-gray-400 transform transition-transform" id="task-arrow-${taskId}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                <div class="flex items-center gap-2">
                                    ${isRolledOver ? `
                                        <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs px-2 py-1 rounded-full font-bold">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                            </svg>
                                            OVERDUE
                                        </span>
                                    ` : ''}
                                    <span class="font-bold text-black uppercase">${task.task_title}</span>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-3 py-1.5 rounded-full ${statusColors[task.status]} flex items-center gap-1.5">
                                <div class="status-dot dot-${task.status}"></div>
                                ${statusLabels[task.status]}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-xs ml-6">
                            <div class="flex items-center gap-3">
                                <span class="text-gray-600">
                                    ${new Date(task.start_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})} - 
                                    ${new Date(task.end_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})}
                                </span>
                                ${task.status !== 'completed' ? `
                                    <span class="text-gray-500">•</span>
                                    <span class="font-semibold text-gray-700 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        ${duration} ${duration === 1 ? 'day' : 'days'}
                                    </span>
                                ` : ''}
                            </div>
                            <span class="font-semibold text-gray-900">${task.progress_percentage}%</span>
                        </div>
                    </div>

                    <div id="task-desc-${taskId}" class="hidden border-t-2 ${rowBorderClass} bg-white">
                        <div class="p-4 space-y-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-700 block mb-1">Description</label>
                                <p class="text-sm text-gray-700 bg-gray-50 rounded p-3 border border-gray-200 leading-relaxed">
                                    ${task.task_description || 'No description provided.'}
                                </p>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 block mb-1">Start Date</label>
                                    <p class="text-sm text-gray-900">${new Date(task.start_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 block mb-1">End Date</label>
                                    <p class="text-sm text-gray-900">${new Date(task.end_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}</p>
                                </div>
                                ${task.status !== 'completed' ? `
                                    <div>
                                        <label class="text-xs font-semibold text-gray-700 block mb-1">Duration</label>
                                        <p class="text-sm font-bold text-gray-900">${duration} ${duration === 1 ? 'day' : 'days'}</p>
                                    </div>
                                ` : `
                                    <div>
                                        <label class="text-xs font-semibold text-gray-700 block mb-1">Status</label>
                                        <p class="text-sm font-bold text-green-700">✓ Completed</p>
                                    </div>
                                `}
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-gray-700 block mb-2">Progress</label>
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2.5 border border-gray-300">
                                        <div class="${progressColors[task.status]} h-2.5 rounded-full transition-all" style="width: ${task.progress_percentage}%"></div>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900">${task.progress_percentage}%</span>
                                </div>
                            </div>

                            ${isRolledOver && task.original_week_start ? `
                                <div class="bg-orange-50 border-2 border-orange-300 rounded-lg p-3">
                                    <div class="flex items-start gap-2 text-orange-700">
                                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                        </svg>
                                        <div class="text-sm">
                                            <p class="font-semibold">Task Rolled Over</p>
                                            <p class="text-xs mt-1">Originally from: ${new Date(task.original_week_start).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}</p>
                                            ${task.rollover_count > 1 ? `
                                                <p class="text-xs mt-1 font-bold">Rolled over ${task.rollover_count} times</p>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            ` : ''}

                            ${task.status === 'delayed' && task.display_delay_days > 0 ? `
                                <div class="bg-red-50 border-2 border-red-300 rounded-lg p-3">
                                    <div class="flex items-center gap-2 text-red-700">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-sm font-semibold">This task is delayed by ${task.display_delay_days} day(s)</span>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        function toggleEmployee(employeeId) {
            const content = document.getElementById(`employee-${employeeId}`);
            const arrow = document.getElementById(`arrow-${employeeId}`);

            if (content && arrow) {
                content.classList.toggle('hidden');
                arrow.classList.toggle('rotate-180');
            }
        }

        function toggleTask(taskId) {
            const content = document.getElementById(`task-desc-${taskId}`);
            const arrow = document.getElementById(`task-arrow-${taskId}`);

            if (content && arrow) {
                content.classList.toggle('hidden');
                arrow.classList.toggle('rotate-90');
            }
        }
    </script>
</body>

</html>