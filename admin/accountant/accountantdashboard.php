<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';

// Only allow accountants/superadmins
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Dashboard</title>
    <!-- Google Charts API -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'pulse-subtle': 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="text-center mb-8 animate-fade-in">
            <h1 class="text-4xl font-bold text-slate-800 mb-2">Revenue Analytics</h1>
            <p class="text-slate-600 text-lg">Track your business performance over time</p>
        </div>

        <!-- Main Dashboard Card -->
        <div class="bg-white overflow-hidden animate-slide-up">
            <!-- Card Header -->
            <div class="bg-black px-6 py-4">
                <h2 class="text-xl font-semibold text-white flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Revenue Dashboard
                </h2>
            </div>

            <!-- Controls Section -->
            <div class="p-6 bg-slate-50 border-b border-slate-200">
                <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                    <!-- Date Filters -->
                    <div class="flex flex-col sm:flex-row gap-4 items-center">
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-slate-700 whitespace-nowrap">From:</label>
                            <input type="date" id="startDate" 
                                class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-white text-sm">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-slate-700 whitespace-nowrap">To:</label>
                            <input type="date" id="endDate" 
                                class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-white text-sm">
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <button onclick="loadRevenue()" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200 transform hover:scale-105 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-lg hover:shadow-xl">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Update
                        </button>
                        <button onclick="exportData()" 
                            class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white font-medium rounded-lg transition-all duration-200 focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export
                        </button>
                    </div>
                </div>

                <!-- Quick Filters -->
                <div class="flex gap-2 mt-4 flex-wrap">
                    <button onclick="setDateRange(7)" class="px-3 py-1 text-xs font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-full border border-slate-300 hover:border-blue-300 transition-colors">Last 7 days</button>
                    <button onclick="setDateRange(30)" class="px-3 py-1 text-xs font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-full border border-slate-300 hover:border-blue-300 transition-colors">Last 30 days</button>
                    <button onclick="setDateRange(90)" class="px-3 py-1 text-xs font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-full border border-slate-300 hover:border-blue-300 transition-colors">Last 90 days</button>
                    <button onclick="setCurrentMonth()" class="px-3 py-1 text-xs font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-full border border-slate-300 hover:border-blue-300 transition-colors">This month</button>
                </div>
            </div>

            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-6 bg-white">
                <div class="text-center p-4 rounded-xl bg-emerald-50 border border-green-200">
                    <div class="text-2xl font-bold text-green-700" id="totalRevenue">₱0</div>
                    <div class="text-sm text-green-600">Total Revenue</div>
                </div>
                <div class="text-center p-4 rounded-xl bg-cyan-50 border border-blue-200">
                    <div class="text-2xl font-bold text-blue-700" id="avgRevenue">₱0</div>
                    <div class="text-sm text-blue-600">Daily Average</div>
                </div>
                <div class="text-center p-4 rounded-xl bg-violet-50 border border-purple-200">
                    <div class="text-2xl font-bold text-purple-700" id="highestDay">₱0</div>
                    <div class="text-sm text-purple-600">Highest Day</div>
                </div>
                <div class="text-center p-4 rounded-xl bg-amber-50 border border-orange-200">
                    <div class="text-2xl font-bold text-orange-700" id="totalDays">0</div>
                    <div class="text-sm text-orange-600">Days</div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="p-6">
                <div class="relative bg-white rounded-lg">
                    <!-- Google Chart Container -->
                    <div id="revenueChart" style="width: 100%; height: 400px;"></div>
                    <div id="loadingIndicator" class="absolute inset-0 items-center justify-center bg-white bg-opacity-90 rounded-lg hidden">
                        <div class="flex items-center gap-3">
                            <div class="w-6 h-6 border-3 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                            <span class="text-slate-600 font-medium">Loading data...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Records Table -->
        <section class="p-6 bg-white rounded-lg shadow-lg mt-6 animate-slide-up">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-800 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Revenue Records
                </h2>
            </div>

            <!-- Enhanced Search box -->
            <div class="relative mb-6">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text" id="revenueSearchBox" 
                       placeholder="Search by date (e.g., 2024-01-15, January, etc.)" 
                       class="pl-10 pr-4 py-3 border border-gray-300 rounded-lg w-full md:w-1/2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white text-sm placeholder-gray-500">
            </div>

            <!-- Enhanced Table -->
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full text-sm text-left bg-white">
                    <thead class="bg-gray-50 text-gray-700">
                        <tr>
                            <th class="px-6 py-4 font-semibold border-b border-gray-200">Date</th>
                            <th class="px-6 py-4 font-semibold border-b border-gray-200">Revenue</th>
                        </tr>
                    </thead>
                    <tbody id="revenueRecordsBody" class="divide-y divide-gray-100">
                        <!-- Data will be injected here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Enhanced No Records Message -->
            <div id="revenueNoRecords" class="text-center py-12 hidden">
                <svg class="mx-auto h-12 w-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-500 font-medium">No records found</p>
                <p class="text-gray-400 text-sm mt-1">Try adjusting your search or date range</p>
            </div>
        </section>
    </div>

    <script>
       let currentData = [];
let allRecordsData = []; // New variable to store all records

// Load Google Charts
google.charts.load('current', {'packages':['line', 'corechart']});

// Load all records for the table (independent of graph date range)
async function loadAllRecords() {
    try {
        const response = await fetch('fetch_revenue.php?all_records=true');
        const result = await response.json();
        
        if (result.success && result.data) {
            allRecordsData = Array.isArray(result.data) ? result.data : [];
            displayRevenueTable(allRecordsData);
        } else {
            console.error('Failed to load all records:', result);
            allRecordsData = [];
            displayRevenueTable([]);
        }
    } catch (error) {
        console.error('Error loading all records:', error);
        allRecordsData = [];
        displayRevenueTable([]);
    }
}

async function loadRevenue() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }

    showLoading(true);

    try {
        // Load data for chart (filtered by date range)
        const response = await fetch(`fetch_revenue.php?start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();

        if (result.success && result.data) {
            currentData = Array.isArray(result.data) ? result.data : [];
            updateChart();
            updateStats();
        } else {
            console.error('API returned error or no data:', result);
            currentData = [];
            alert('No data found for the selected date range');
        }
        
        // Always load all records for table (independent of chart filter)
        await loadAllRecords();
        
    } catch (error) {
        console.error('Error loading revenue data:', error);
        currentData = [];
        alert('Error loading revenue data. Please check your connection.');
    } finally {
        showLoading(false);
    }
}

function updateChart() {
    if (!currentData || currentData.length === 0) {
        document.getElementById('revenueChart').innerHTML = '<p class="text-center text-gray-500 py-8">No data to display</p>';
        return;
    }

    // Prepare data for Google Charts
    const data = new google.visualization.DataTable();
    data.addColumn('string', 'Date');
    data.addColumn('number', 'Revenue');

    // Add data rows
    const chartData = currentData.map(item => {
        const date = new Date(item.date);
        const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        return [formattedDate, item.total];
    });

    data.addRows(chartData);

    // Chart options
    const options = {
        chart: {
            title: 'Revenue Over Time',
            subtitle: 'Daily revenue tracking'
        },
        backgroundColor: 'transparent',
        colors: ['#2563eb'],
        hAxis: {
            title: 'Date',
            titleTextStyle: {color: '#64748b', fontSize: 12},
            textStyle: {color: '#64748b', fontSize: 11}
        },
        vAxis: {
            title: 'Revenue (₱)',
            titleTextStyle: {color: '#64748b', fontSize: 12},
            textStyle: {color: '#64748b', fontSize: 11},
            format: '₱#,###'
        },
        legend: {
            position: 'none'
        },
        curveType: 'function',
        lineWidth: 3,
        pointSize: 6,
        pointShape: 'circle',
        animation: {
            startup: true,
            duration: 1000,
            easing: 'out'
        },
        chartArea: {
            left: 80,
            top: 60,
            width: '80%',
            height: '70%'
        }
    };

    // Create and draw chart
    const chart = new google.visualization.LineChart(document.getElementById('revenueChart'));
    chart.draw(data, options);
}

function updateStats() {
    if (!currentData || currentData.length === 0) {
        document.getElementById('totalRevenue').textContent = '₱0';
        document.getElementById('avgRevenue').textContent = '₱0';
        document.getElementById('highestDay').textContent = '₱0';
        document.getElementById('totalDays').textContent = '0';
        return;
    }

    const total = currentData.reduce((sum, item) => sum + item.total, 0);
    const average = total / currentData.length;
    const highest = Math.max(...currentData.map(item => item.total));
    const days = currentData.length;

    document.getElementById('totalRevenue').textContent = '₱' + total.toLocaleString();
    document.getElementById('avgRevenue').textContent = '₱' + Math.round(average).toLocaleString();
    document.getElementById('highestDay').textContent = '₱' + highest.toLocaleString();
    document.getElementById('totalDays').textContent = days;
}

function displayRevenueTable(data) {
    const tbody = document.getElementById('revenueRecordsBody');
    const noRecords = document.getElementById('revenueNoRecords');
    
    if (!data || !Array.isArray(data) || data.length === 0) {
        tbody.innerHTML = '';
        noRecords.classList.remove('hidden');
        return;
    }
    
    noRecords.classList.add('hidden');
    
    try {
        // Sort data by date descending (newest first)
        const sortedData = [...data].sort((a, b) => new Date(b.date) - new Date(a.date));
        
        tbody.innerHTML = sortedData.map(item => {
            if (!item || !item.date || item.total === undefined) {
                return '';
            }
            
            const formattedDate = new Date(item.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 border-b border-gray-100 text-gray-700">${formattedDate}</td>
                    <td class="px-6 py-4 border-b border-gray-100 text-green-600 font-semibold">₱${item.total.toLocaleString()}</td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Error displaying table:', error);
        tbody.innerHTML = '<tr><td colspan="2" class="px-6 py-4 text-center text-red-500">Error displaying data</td></tr>';
    }
}

function searchRevenueTable() {
    const searchBox = document.getElementById('revenueSearchBox');
    
    if (!searchBox) {
        console.error('Search box not found');
        return;
    }
    
    const searchTerm = searchBox.value.toLowerCase().trim();
    
    // Search through ALL records, not just current filtered data
    if (!allRecordsData || !Array.isArray(allRecordsData)) {
        console.warn('No data available to search');
        displayRevenueTable([]);
        return;
    }
    
    if (searchTerm === '') {
        displayRevenueTable(allRecordsData);
        return;
    }
    
    try {
        const filteredData = allRecordsData.filter(item => {
            if (!item || !item.date) return false;
            
            const date = item.date.toLowerCase();
            const formattedDate = new Date(item.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }).toLowerCase();
            
            return date.includes(searchTerm) || formattedDate.includes(searchTerm);
        });
        
        displayRevenueTable(filteredData);
    } catch (error) {
        console.error('Error filtering data:', error);
        displayRevenueTable([]);
    }
}

function showLoading(show) {
    document.getElementById('loadingIndicator').classList.toggle('hidden', !show);
}

function setDateRange(days) {
    const today = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - days);
    
    document.getElementById('endDate').value = today.toISOString().split('T')[0];
    document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
    
    loadRevenue();
}

function setCurrentMonth() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
    document.getElementById('endDate').value = today.toISOString().split('T')[0];
    
    loadRevenue();
}

function exportData() {
    // Export all records, not just filtered data
    const dataToExport = allRecordsData && allRecordsData.length > 0 ? allRecordsData : currentData;
    
    if (!dataToExport || dataToExport.length === 0) {
        alert('No data to export');
        return;
    }

    const csvContent = "data:text/csv;charset=utf-8,Date,Revenue\n" + 
        dataToExport.map(row => `${row.date},${row.total}`).join('\n');

    const link = document.createElement('a');
    link.setAttribute('href', encodeURI(csvContent));
    link.setAttribute('download', 'revenue_data.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize on page load
window.onload = () => {
    currentData = [];
    allRecordsData = [];
    
    // Load all records first
    loadAllRecords();
    
    // Set default date range for chart
    setDateRange(7);
    
    // Add search functionality
    const searchBox = document.getElementById('revenueSearchBox');
    
    if (searchBox) {
        let searchTimeout;
        searchBox.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(searchRevenueTable, 300);
        });
        
        searchBox.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                clearTimeout(searchTimeout);
                searchRevenueTable();
            }
        });
    } else {
        console.error('Search box not found in DOM');
    }
};
    </script>
</body>
</html>