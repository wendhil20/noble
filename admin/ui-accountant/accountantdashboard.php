<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['accountant', 'superadmin']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Dashboard — Noble Admin</title>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-600 rounded-lg flex items-center justify-center shrink-0">
                    <i class="fas fa-chart-line text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 leading-tight">Revenue Analytics</h1>
                    <p class="text-sm text-gray-500">Track your business performance over time</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-6 space-y-5">

        <!-- Metric Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div
                class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Total Revenue</p>
                    <p class="text-2xl font-bold text-emerald-600" id="totalRevenue">₱0</p>
                </div>
                <div class="w-11 h-11 bg-emerald-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-peso-sign text-emerald-500 text-lg"></i>
                </div>
            </div>
            <div
                class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Daily Average</p>
                    <p class="text-2xl font-bold text-blue-600" id="avgRevenue">₱0</p>
                </div>
                <div class="w-11 h-11 bg-blue-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-chart-bar text-blue-500 text-lg"></i>
                </div>
            </div>
            <div
                class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Highest Day</p>
                    <p class="text-2xl font-bold text-purple-600" id="highestDay">₱0</p>
                </div>
                <div class="w-11 h-11 bg-purple-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-arrow-trend-up text-purple-500 text-lg"></i>
                </div>
            </div>
            <div
                class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Days Tracked</p>
                    <p class="text-2xl font-bold text-orange-600" id="totalDays">0</p>
                </div>
                <div class="w-11 h-11 bg-orange-50 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas fa-calendar-days text-orange-500 text-lg"></i>
                </div>
            </div>
        </div>

        <!-- Chart Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

            <!-- Card Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-base font-semibold text-gray-800">Revenue Chart</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Filter by date range to update the chart</p>
                </div>
                <button onclick="exportData()" class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-md
                       bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>

            <!-- Controls -->
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-100">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <!-- Date pickers -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-gray-500 whitespace-nowrap">From</label>
                        <input type="date" id="startDate"
                            class="text-sm px-3 py-2 border border-gray-200 rounded-lg
                               focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition bg-white">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-gray-500 whitespace-nowrap">To</label>
                        <input type="date" id="endDate"
                            class="text-sm px-3 py-2 border border-gray-200 rounded-lg
                               focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition bg-white">
                    </div>
                    <button onclick="loadRevenue()" class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-lg
                           bg-emerald-600 hover:bg-emerald-700 text-white transition-colors shrink-0">
                        <i class="fas fa-rotate-right text-xs"></i> Update
                    </button>
                </div>

                <!-- Quick filters -->
                <div class="flex gap-2 mt-3 flex-wrap">
                    <span class="text-xs text-gray-400 self-center">Quick:</span>
                    <button onclick="setDateRange(7)"
                        class="quick-btn text-xs font-medium px-3 py-1 rounded-full border border-gray-200
                           text-gray-500 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 transition-colors">
                        Last 7 days
                    </button>
                    <button onclick="setDateRange(30)"
                        class="quick-btn text-xs font-medium px-3 py-1 rounded-full border border-gray-200
                           text-gray-500 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 transition-colors">
                        Last 30 days
                    </button>
                    <button onclick="setDateRange(90)"
                        class="quick-btn text-xs font-medium px-3 py-1 rounded-full border border-gray-200
                           text-gray-500 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 transition-colors">
                        Last 90 days
                    </button>
                    <button onclick="setCurrentMonth()"
                        class="quick-btn text-xs font-medium px-3 py-1 rounded-full border border-gray-200
                           text-gray-500 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 transition-colors">
                        This month
                    </button>
                </div>
            </div>

            <!-- Chart -->
            <div class="p-5 relative">
                <div id="revenueChart" style="width:100%; height:380px;"></div>
                <div id="loadingIndicator"
                    class="absolute inset-0 hidden items-center justify-center bg-white bg-opacity-80 rounded-xl">
                    <div class="flex items-center gap-3 text-sm text-gray-500">
                        <i class="fas fa-spinner fa-spin text-emerald-500"></i>
                        Loading data...
                    </div>
                </div>
            </div>
        </div>

        <!-- Records Table Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

            <!-- Card Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-base font-semibold text-gray-800">Revenue Records</h2>
                    <p class="text-xs text-gray-400 mt-0.5">All-time daily revenue entries</p>
                </div>
                <!-- Search -->
                <div class="relative">
                    <i
                        class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs pointer-events-none"></i>
                    <input type="text" id="revenueSearchBox" placeholder="Search by date..." class="text-sm pl-8 pr-4 py-2 border border-gray-200 rounded-lg w-full sm:w-56
                           focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition">
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <div class="max-h-[480px] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10 border-b border-gray-200">
                            <tr>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    Date</th>
                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    Revenue</th>
                            </tr>
                        </thead>
                        <tbody id="revenueRecordsBody" class="divide-y divide-gray-100">
                            <!-- injected by JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty state -->
            <div id="revenueNoRecords" class="hidden py-14 text-center">
                <i class="fas fa-inbox text-4xl text-gray-200 mb-3 block"></i>
                <p class="text-sm font-medium text-gray-500">No records found</p>
                <p class="text-xs text-gray-400 mt-1">Try adjusting your search or date range.</p>
            </div>

        </div>
    </div><!-- end page wrap -->

    <script>
        let currentData = [];
        let allRecordsData = [];
        const BASE_URL = "<?= BASE_URL; ?>";

        google.charts.load('current', { packages: ['corechart'] });

        async function loadAllRecords() {
            try {
                const res = await fetch('fetch_revenue.php?all_records=true');
                const result = await res.json();
                allRecordsData = (result.success && Array.isArray(result.data)) ? result.data : [];
                displayRevenueTable(allRecordsData);
            } catch (e) {
                console.error(e);
                allRecordsData = [];
                displayRevenueTable([]);
            }
        }

        async function loadRevenue() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            if (!startDate || !endDate) { alert('Please select both start and end dates.'); return; }

            showLoading(true);
            try {
                const res = await fetch(`${BASE_URL}/fetchdashboardaccountant?start_date=${startDate}&end_date=${endDate}`);
                const result = await res.json();
                currentData = (result.success && Array.isArray(result.data)) ? result.data : [];
                updateChart();
                updateStats();
                await loadAllRecords();
            } catch (e) {
                console.error(e);
                currentData = [];
                alert('Error loading revenue data.');
            } finally {
                showLoading(false);
            }
        }

        function updateChart() {
            const container = document.getElementById('revenueChart');
            if (!currentData.length) {
                container.innerHTML = '<p class="text-center text-gray-400 py-16 text-sm">No data to display for this range.</p>';
                return;
            }

            const data = new google.visualization.DataTable();
            data.addColumn('string', 'Date');
            data.addColumn('number', 'Revenue');
            data.addRows(currentData.map(item => [
                new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
                item.total
            ]));

            const options = {
                backgroundColor: 'transparent',
                colors: ['#059669'],
                hAxis: { textStyle: { color: '#9ca3af', fontSize: 11 }, gridlines: { color: 'transparent' } },
                vAxis: { textStyle: { color: '#9ca3af', fontSize: 11 }, gridlines: { color: '#f3f4f6' }, format: '₱#,###', baselineColor: '#e5e7eb' },
                legend: { position: 'none' },
                curveType: 'function',
                lineWidth: 2,
                pointSize: 5,
                pointShape: 'circle',
                animation: { startup: true, duration: 800, easing: 'out' },
                chartArea: { left: 70, top: 20, right: 20, width: '100%', height: '80%' },
                tooltip: { textStyle: { fontSize: 12 } },
            };

            new google.visualization.LineChart(container).draw(data, options);
        }

        function updateStats() {
            if (!currentData.length) {
                ['totalRevenue', 'avgRevenue', 'highestDay'].forEach(id => document.getElementById(id).textContent = '₱0');
                document.getElementById('totalDays').textContent = '0';
                return;
            }
            const total = currentData.reduce((s, i) => s + i.total, 0);
            const avg = total / currentData.length;
            const highest = Math.max(...currentData.map(i => i.total));
            document.getElementById('totalRevenue').textContent = '₱' + total.toLocaleString();
            document.getElementById('avgRevenue').textContent = '₱' + Math.round(avg).toLocaleString();
            document.getElementById('highestDay').textContent = '₱' + highest.toLocaleString();
            document.getElementById('totalDays').textContent = currentData.length;
        }

        function displayRevenueTable(data) {
            const tbody = document.getElementById('revenueRecordsBody');
            const noRec = document.getElementById('revenueNoRecords');

            if (!data || !data.length) {
                tbody.innerHTML = '';
                noRec.classList.remove('hidden');
                return;
            }
            noRec.classList.add('hidden');

            const sorted = [...data].sort((a, b) => new Date(b.date) - new Date(a.date));
            tbody.innerHTML = sorted.map(item => {
                if (!item?.date || item.total === undefined) return '';
                const fmt = new Date(item.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3.5 text-gray-700">${fmt}</td>
                    <td class="px-5 py-3.5 text-right font-semibold text-emerald-600">₱${item.total.toLocaleString()}</td>
                </tr>`;
            }).join('');
        }

        function searchRevenueTable() {
            const term = document.getElementById('revenueSearchBox').value.toLowerCase().trim();
            if (!term) { displayRevenueTable(allRecordsData); return; }
            const filtered = allRecordsData.filter(item => {
                if (!item?.date) return false;
                const fmt = new Date(item.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }).toLowerCase();
                return item.date.toLowerCase().includes(term) || fmt.includes(term);
            });
            displayRevenueTable(filtered);
        }

        function showLoading(show) {
            const el = document.getElementById('loadingIndicator');
            el.classList.toggle('hidden', !show);
            el.classList.toggle('flex', show);
        }

        function setDateRange(days) {
            const today = new Date();
            const start = new Date();
            start.setDate(start.getDate() - days);
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
            document.getElementById('startDate').value = start.toISOString().split('T')[0];
            loadRevenue();
        }

        function setCurrentMonth() {
            const today = new Date();
            const first = new Date(today.getFullYear(), today.getMonth(), 1);
            document.getElementById('startDate').value = first.toISOString().split('T')[0];
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
            loadRevenue();
        }

        function exportData() {
            const data = allRecordsData.length ? allRecordsData : currentData;
            if (!data.length) { alert('No data to export.'); return; }
            const csv = "data:text/csv;charset=utf-8,Date,Revenue\n" + data.map(r => `${r.date},${r.total}`).join('\n');
            const link = document.createElement('a');
            link.setAttribute('href', encodeURI(csv));
            link.setAttribute('download', 'revenue_data.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        window.onload = () => {
            currentData = []; allRecordsData = [];
            loadAllRecords();
            setDateRange(7);

            let t;
            document.getElementById('revenueSearchBox').addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(searchRevenueTable, 300);
            });
        };
    </script>

</body>

</html>