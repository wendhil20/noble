<?php 
session_name("nobleadmin"); 
include '../../connection/connect.php'; 
include '../role/roleaccount.php'; 
require_role(['sales', 'superadmin']); 

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// ✅ Get date parameters from URL or set defaults
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$comparison_period = isset($_GET['comparison_period']) ? $_GET['comparison_period'] : 'previous_period';
$quick_filter = isset($_GET['quick_filter']) ? $_GET['quick_filter'] : '';

// Handle quick filters
switch ($quick_filter) {
    case 'today':
        $start_date = $end_date = date('Y-m-d');
        break;
    case 'yesterday':
        $start_date = $end_date = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'last_week':
        $start_date = date('Y-m-d', strtotime('monday last week'));
        $end_date = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('last month'));
        $end_date = date('Y-m-t', strtotime('last month'));
        break;
    case 'last_30_days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        break;
    case 'last_90_days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $end_date = date('Y-m-d');
        break;
}

// ✅ Calculate comparison period dates
$date_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
switch ($comparison_period) {
    case 'previous_period':
        $comp_start = date('Y-m-d', strtotime($start_date . " -" . ($date_diff + 1) . " days"));
        $comp_end = date('Y-m-d', strtotime($start_date . " -1 day"));
        break;
    case 'same_period_last_month':
        $comp_start = date('Y-m-d', strtotime($start_date . " -1 month"));
        $comp_end = date('Y-m-d', strtotime($end_date . " -1 month"));
        break;
    case 'same_period_last_year':
        $comp_start = date('Y-m-d', strtotime($start_date . " -1 year"));
        $comp_end = date('Y-m-d', strtotime($end_date . " -1 year"));
        break;
    default:
        $comp_start = $comp_end = null;
}

// ✅ Main Period Data
$mainPeriodData = $conn->query("
    SELECT 
        COUNT(*) as count,
        SUM(final_total) as total_amount,
        AVG(final_total) as avg_amount,
        MIN(confirmed_at) as first_verification,
        MAX(confirmed_at) as last_verification,
        AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) as avg_verification_minutes
    FROM orders 
    WHERE verified_by IS NOT NULL
    AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc();

// ✅ Comparison Period Data
$comparisonData = null;
if ($comp_start && $comp_end) {
    $comparisonData = $conn->query("
        SELECT 
            COUNT(*) as count,
            SUM(final_total) as total_amount,
            AVG(final_total) as avg_amount,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) as avg_verification_minutes
        FROM orders 
        WHERE verified_by IS NOT NULL
        AND DATE(confirmed_at) BETWEEN '$comp_start' AND '$comp_end'
    ")->fetch_assoc();
}

// ✅ Daily Breakdown for the selected period
$dailyBreakdown = $conn->query("
    SELECT 
        DATE(confirmed_at) as date,
        DAYNAME(confirmed_at) as day_name,
        COUNT(*) as count,
        SUM(final_total) as total_amount,
        AVG(final_total) as avg_amount,
        MIN(confirmed_at) as first_verification,
        MAX(confirmed_at) as last_verification,
        AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) as avg_verification_minutes
    FROM orders 
    WHERE verified_by IS NOT NULL 
    AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(confirmed_at), DAYNAME(confirmed_at)
    ORDER BY DATE(confirmed_at)
");

// ✅ Create chart data for daily trend
$chartLabels = [];
$chartData = [];
$chartAmounts = [];
$current = strtotime($start_date);
$end = strtotime($end_date);

// Initialize all dates in range with 0
while ($current <= $end) {
    $date = date('Y-m-d', $current);
    $chartLabels[] = date('M j', $current);
    $chartData[] = 0;
    $chartAmounts[] = 0;
    $current = strtotime('+1 day', $current);
}

// Fill with actual data
$dailyBreakdown->data_seek(0); // Reset result pointer
while ($row = $dailyBreakdown->fetch_assoc()) {
    $index = array_search(date('M j', strtotime($row['date'])), $chartLabels);
    if ($index !== false) {
        $chartData[$index] = (int)$row['count'];
        $chartAmounts[$index] = (float)$row['total_amount'];
    }
}

// ✅ Employee Performance for selected period
$employeePerformance = $conn->query("
    SELECT 
        verified_by,
        COUNT(*) as total_verified,
        SUM(final_total) as total_amount,
        AVG(final_total) as avg_amount,
        MIN(confirmed_at) as first_verification,
        MAX(confirmed_at) as last_verification,
        AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) as avg_verification_minutes
    FROM orders 
    WHERE verified_by IS NOT NULL 
    AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY verified_by
    ORDER BY total_verified DESC
");

// ✅ Hourly Analysis for selected period
$hourlyAnalysis = $conn->query("
    SELECT 
        HOUR(confirmed_at) as hour,
        COUNT(*) as count,
        SUM(final_total) as total_amount,
        AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) as avg_minutes
    FROM orders 
    WHERE verified_by IS NOT NULL
    AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY HOUR(confirmed_at)
    ORDER BY HOUR(confirmed_at)
");

$hourlyData = [];
$hourlyAmounts = [];
$hourlyLabels = [];
$hourlyVerificationTimes = [];
for ($i = 0; $i < 24; $i++) {
    $hourlyLabels[] = sprintf("%02d:00", $i);
    $hourlyData[] = 0;
    $hourlyAmounts[] = 0;
    $hourlyVerificationTimes[] = 0;
}

while ($row = $hourlyAnalysis->fetch_assoc()) {
    $hourlyData[$row['hour']] = (int)$row['count'];
    $hourlyAmounts[$row['hour']] = (float)$row['total_amount'];
    $hourlyVerificationTimes[$row['hour']] = round($row['avg_minutes'] ?? 0, 1);
}

// ✅ Recent Verifications for selected period
$recentVerifications = $conn->query("
    SELECT 
        id,
        customer_name,
        final_total,
        verified_by,
        created_at,
        confirmed_at,
        payment_status,
        mode_payment,
        TIMESTAMPDIFF(MINUTE, created_at, confirmed_at) as verification_time_minutes
    FROM orders 
    WHERE verified_by IS NOT NULL 
    AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
    ORDER BY confirmed_at DESC 
    LIMIT 15
");

// ✅ Helper functions
function formatPeriodName($start, $end) {
    $start_ts = strtotime($start);
    $end_ts = strtotime($end);
    
    if ($start === $end) {
        return date('F j, Y', $start_ts);
    } elseif (date('Y-m', $start_ts) === date('Y-m', $end_ts)) {
        return date('F j', $start_ts) . ' - ' . date('j, Y', $end_ts);
    } else {
        return date('M j, Y', $start_ts) . ' - ' . date('M j, Y', $end_ts);
    }
}

function calculatePercentageChange($current, $previous) {
    if (!$previous || $previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Date Range Verification Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include '../navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto mt-6 px-4">
        <!-- Header -->
        <div class="mb-6 text-center">
            <h1 class="text-4xl font-bold text-orange-400 mb-2">Custom Date Range Analytics</h1>
            <p class="text-gray-600">Analyze verification data for any date range</p>
        </div>

        <!-- Date Filter Section -->
        <div class="bg-white shadow-xl rounded-2xl p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Main Date Range Filters</h2>
            
            <form method="GET" class="space-y-4">
                <!-- Quick Filters -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quick Filters:</label>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="quick_filter" value="today" 
                                class="px-3 py-1 text-sm rounded-full <?= $quick_filter === 'today' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Today
                        </button>
                        <button type="submit" name="quick_filter" value="yesterday" 
                                class="px-3 py-1 text-sm rounded-full <?= $quick_filter === 'yesterday' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Yesterday
                        </button>
                        <button type="submit" name="quick_filter" value="this_week" 
                                class="px-3 py-1 text-sm rounded-full <?= $quick_filter === 'this_week' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            This Week
                        </button>
                        <button type="submit" name="quick_filter" value="last_week" 
                                class="px-3 py-1 text-sm rounded-full <?= $quick_filter === 'last_week' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Last Week
                        </button>
                        <button type="submit" name="quick_filter" value="this_month" 
                                class="px-3 py-1 text-sm rounded-full <?= $quick_filter === 'this_month' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            This Month
                        </button>
                        <button type="submit" name="quick_filter" value="last_month" 
                                class="px-3 py-1 text-sm rounded-full <?= $quick_filter === 'last_month' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Last Month
                        </button>
                        <button type="submit" name="quick_filter" value="last_30_days" 
                                class="px-3 py-1 text-sm rounded-full <?= $quick_filter === 'last_30_days' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Last 30 Days
                        </button>
                        <button type="submit" name="quick_filter" value="last_90_days" 
                                class="px-3 py-1 text-sm rounded-full <?= $quick_filter === 'last_90_days' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Last 90 Days
                        </button>
                    </div>
                </div>

                <!-- Custom Date Range -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                        <input type="date" name="start_date" value="<?= $start_date ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                        <input type="date" name="end_date" value="<?= $end_date ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Compare With:</label>
                        <select name="comparison_period" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="previous_period" <?= $comparison_period === 'previous_period' ? 'selected' : '' ?>>Previous Period</option>
                            <option value="same_period_last_month" <?= $comparison_period === 'same_period_last_month' ? 'selected' : '' ?>>Same Period Last Month</option>
                            <option value="same_period_last_year" <?= $comparison_period === 'same_period_last_year' ? 'selected' : '' ?>>Same Period Last Year</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                            Apply Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Current Period Summary -->
        <div class="bg-white shadow-xl rounded-2xl p-6 mb-6 border-l-4 border-blue-500">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">
                    Verification Summary: <?= formatPeriodName($start_date, $end_date) ?>
                </h2>
                <?php if ($comparisonData): ?>
                    <span class="text-sm text-gray-500">
                        vs <?= formatPeriodName($comp_start, $comp_end) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Orders Verified -->
                <div class="text-center bg-blue-50 rounded-lg p-4">
                    <p class="text-3xl font-bold text-blue-600"><?= number_format($mainPeriodData['count'] ?? 0) ?></p>
                    <p class="text-sm text-gray-600">Orders Verified</p>
                    <?php if ($comparisonData): 
                        $change = calculatePercentageChange($mainPeriodData['count'], $comparisonData['count']);
                    ?>
                        <p class="text-xs mt-1 <?= $change >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $change >= 0 ? '↗' : '↘' ?> <?= abs($change) ?>% vs comparison
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Total Amount -->
                <div class="text-center bg-green-50 rounded-lg p-4">
                    <p class="text-3xl font-bold text-green-600">₱<?= number_format($mainPeriodData['total_amount'] ?? 0, 2) ?></p>
                    <p class="text-sm text-gray-600">Total Value</p>
                    <?php if ($comparisonData): 
                        $change = calculatePercentageChange($mainPeriodData['total_amount'], $comparisonData['total_amount']);
                    ?>
                        <p class="text-xs mt-1 <?= $change >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $change >= 0 ? '↗' : '↘' ?> <?= abs($change) ?>% vs comparison
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Average Amount -->
                <div class="text-center bg-purple-50 rounded-lg p-4">
                    <p class="text-2xl font-bold text-purple-600">₱<?= number_format($mainPeriodData['avg_amount'] ?? 0, 2) ?></p>
                    <p class="text-sm text-gray-600">Average per Order</p>
                    <?php if ($comparisonData): 
                        $change = calculatePercentageChange($mainPeriodData['avg_amount'], $comparisonData['avg_amount']);
                    ?>
                        <p class="text-xs mt-1 <?= $change >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $change >= 0 ? '↗' : '↘' ?> <?= abs($change) ?>% vs comparison
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Average Verification Time -->
                <div class="text-center bg-orange-50 rounded-lg p-4">
                    <p class="text-2xl font-bold text-orange-600"><?= number_format($mainPeriodData['avg_verification_minutes'] ?? 0, 1) ?></p>
                    <p class="text-sm text-gray-600">Avg Minutes to Verify</p>
                    <?php if ($comparisonData): 
                        $change = calculatePercentageChange($mainPeriodData['avg_verification_minutes'], $comparisonData['avg_verification_minutes']);
                    ?>
                        <p class="text-xs mt-1 <?= $change <= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $change <= 0 ? '↗' : '↘' ?> <?= abs($change) ?>% vs comparison
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Daily Trend Chart with Individual Filter -->
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Daily Verification paid</h3>
                    <span class="text-xs text-gray-500" id="dailyChartPeriod"><?= formatPeriodName($start_date, $end_date) ?></span>
                </div>
                
                <!-- Individual Date Filter for Daily Chart -->
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Start Date:</label>
                            <input type="date" id="dailyChartStart" value="<?= $start_date ?>" 
                                   class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">End Date:</label>
                            <input type="date" id="dailyChartEnd" value="<?= $end_date ?>" 
                                   class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div class="flex gap-1">
                            <button onclick="updateDailyChart()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded transition duration-200">
                                Update
                            </button>
                            <button onclick="resetDailyChart()" 
                                    class="bg-gray-600 hover:bg-gray-700 text-white text-xs px-2 py-1 rounded transition duration-200">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
                
                <div style="height: 300px;">
                    <canvas id="dailyTrendChart"></canvas>
                </div>
            </div>

            <!-- Hourly Pattern Chart with Individual Filter -->
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Hourly Verification Pattern</h3>
                    <span class="text-xs text-gray-500" id="hourlyChartPeriod"><?= formatPeriodName($start_date, $end_date) ?></span>
                </div>
                
                <!-- Individual Date Filter for Hourly Chart -->
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Start Date:</label>
                            <input type="date" id="hourlyChartStart" value="<?= $start_date ?>" 
                                   class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">End Date:</label>
                            <input type="date" id="hourlyChartEnd" value="<?= $end_date ?>" 
                                   class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div class="flex gap-1">
                            <button onclick="updateHourlyChart()" 
                                    class="bg-purple-600 hover:bg-purple-700 text-white text-xs px-3 py-1 rounded transition duration-200">
                                Update
                            </button>
                            <button onclick="resetHourlyChart()" 
                                    class="bg-gray-600 hover:bg-gray-700 text-white text-xs px-2 py-1 rounded transition duration-200">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
                
                <div style="height: 300px;">
                    <canvas id="hourlyPatternChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Employee Performance Chart with Individual Filter -->
        <div class="bg-white shadow-xl rounded-2xl p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Employee Performance</h3>
                <span class="text-xs text-gray-500" id="employeeChartPeriod"><?= formatPeriodName($start_date, $end_date) ?></span>
            </div>
            
            <!-- Individual Date Filter for Employee Chart -->
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Start Date:</label>
                        <input type="date" id="employeeChartStart" value="<?= $start_date ?>" 
                               class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">End Date:</label>
                        <input type="date" id="employeeChartEnd" value="<?= $end_date ?>" 
                               class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">View:</label>
                        <select id="employeeChartView" 
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="count">Order Count</option>
                            <option value="amount">Total Amount</option>
                            <option value="avg">Average Amount</option>
                        </select>
                    </div>
                    <div class="flex gap-1">
                        <button onclick="updateEmployeeChart()" 
                                class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded transition duration-200">
                            Update
                        </button>
                        <button onclick="resetEmployeeChart()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white text-xs px-2 py-1 rounded transition duration-200">
                            Reset
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="height: 300px;">
                <canvas id="employeePerformanceChart"></canvas>
            </div>
        </div>

        <!-- Daily Breakdown Table -->
        <div class="bg-white shadow-xl rounded-2xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Recent Verifications</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left">Order ID</th>
                            <th class="px-4 py-2 text-left">Customer</th>
                            <th class="px-4 py-2 text-right">Amount</th>
                            <th class="px-4 py-2 text-center">Verified By</th>
                            <th class="px-4 py-2 text-center">Date</th>
                            <th class="px-4 py-2 text-center">Verification Time</th>
                            <th class="px-4 py-2 text-center">Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recentVerifications->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2 font-bold text-blue-600">#<?= $row['id'] ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td class="px-4 py-2 text-right font-semibold">₱<?= number_format($row['final_total'], 2) ?></td>
                            <td class="px-4 py-2 text-center">
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm font-semibold">
                                    Employee <?= $row['verified_by'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center text-sm">
                                <?= date('M j, Y', strtotime($row['confirmed_at'])) ?><br>
                                <span class="text-xs text-gray-500"><?= date('g:i A', strtotime($row['confirmed_at'])) ?></span>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm">
                                    <?php
                                    $minutes = $row['verification_time_minutes'];
                                    if ($minutes > 60) {
                                        $hours = floor($minutes / 60);
                                        $mins = $minutes % 60;
                                        echo $hours . 'h ' . $mins . 'm';
                                    } else {
                                        echo $minutes . ' min';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs">
                                    <?= ucfirst($row['mode_payment']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Additional Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Peak Hours -->
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-3">Peak Activity Hours</h4>
                <?php 
                $maxHour = array_keys($hourlyData, max($hourlyData))[0];
                $minHour = array_keys($hourlyData, min(array_filter($hourlyData)))[0];
                ?>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Busiest Hour:</span>
                        <span class="font-semibold text-green-600"><?= sprintf('%02d:00', $maxHour) ?> (<?= $hourlyData[$maxHour] ?> orders)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Slowest Hour:</span>
                        <span class="font-semibold text-red-600"><?= sprintf('%02d:00', $minHour) ?> (<?= $hourlyData[$minHour] ?> orders)</span>
                    </div>
                </div>
            </div>

            <!-- Verification Speed -->
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-3">Verification Speed</h4>
                <?php 
                $fastestHour = array_keys($hourlyVerificationTimes, min(array_filter($hourlyVerificationTimes)))[0];
                $slowestHour = array_keys($hourlyVerificationTimes, max($hourlyVerificationTimes))[0];
                ?>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Fastest Hour:</span>
                        <span class="font-semibold text-green-600"><?= sprintf('%02d:00', $fastestHour) ?> (<?= $hourlyVerificationTimes[$fastestHour] ?>m)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Slowest Hour:</span>
                        <span class="font-semibold text-red-600"><?= sprintf('%02d:00', $slowestHour) ?> (<?= $hourlyVerificationTimes[$slowestHour] ?>m)</span>
                    </div>
                </div>
            </div>

            <!-- Period Summary -->
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-3">Period Summary</h4>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Total Days:</span>
                        <span class="font-semibold"><?= ceil((strtotime($end_date) - strtotime($start_date)) / (60*60*24)) + 1 ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Avg per Day:</span>
                        <span class="font-semibold text-blue-600"><?= number_format(($mainPeriodData['count'] ?? 0) / (ceil((strtotime($end_date) - strtotime($start_date)) / (60*60*24)) + 1), 1) ?> orders</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Active Days:</span>
                        <span class="font-semibold text-green-600"><?= $dailyBreakdown->num_rows ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="bg-white shadow-xl rounded-2xl p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <a href="?" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition duration-200">
                    <span class="mr-2">🔄</span> Reset Filters
                </a>
                <button onclick="location.reload()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition duration-200">
                    <span class="mr-2">↻</span> Refresh Data
                </button>
            </div>
        </div>
    </div>

    <script>
        // Chart.js configuration
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Store chart instances globally
        let dailyTrendChart;
        let hourlyPatternChart;
        let employeePerformanceChart;

        // Original data (fallback)
        const originalDailyData = {
            labels: <?= json_encode($chartLabels) ?>,
            orders: <?= json_encode($chartData) ?>,
            amounts: <?= json_encode($chartAmounts) ?>
        };

        const originalHourlyData = {
            labels: <?= json_encode($hourlyLabels) ?>,
            orders: <?= json_encode($hourlyData) ?>,
            times: <?= json_encode($hourlyVerificationTimes) ?>
        };

        // Employee performance data
        const originalEmployeeData = {
            labels: [],
            counts: [],
            amounts: [],
            avgAmounts: []
        };
        
        // Populate employee data
        <?php 
        $employeeLabels = [];
        $employeeCounts = [];
        $employeeAmounts = [];
        $employeeAvgAmounts = [];
        while ($row = $employeePerformance->fetch_assoc()) {
            $employeeLabels[] = 'Employee ' . $row['verified_by'];
            $employeeCounts[] = (int)$row['total_verified'];
            $employeeAmounts[] = (float)$row['total_amount'];
            $employeeAvgAmounts[] = (float)$row['avg_amount'];
        }
        ?>
        
        originalEmployeeData.labels = <?= json_encode($employeeLabels) ?>;
        originalEmployeeData.counts = <?= json_encode($employeeCounts) ?>;
        originalEmployeeData.amounts = <?= json_encode($employeeAmounts) ?>;
        originalEmployeeData.avgAmounts = <?= json_encode($employeeAvgAmounts) ?>;

        // Initialize Daily Trend Chart
        function initDailyTrendChart() {
            const dailyTrendCtx = document.getElementById('dailyTrendChart').getContext('2d');
            dailyTrendChart = new Chart(dailyTrendCtx, {
                type: 'line',
                data: {
                    labels: originalDailyData.labels,
                    datasets: [{
                        label: 'Orders Verified',
                        data: originalDailyData.orders,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    }, {
                        label: 'Total Amount (₱)',
                        data: originalDailyData.amounts,
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1',
                        hidden: true
                    }]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: 'Daily Verification Trend'
                        },
                        legend: {
                            onClick: function(e, legendItem, legend) {
                                const index = legendItem.datasetIndex;
                                const chart = legend.chart;
                                if (chart.isDatasetVisible(index)) {
                                    chart.hide(index);
                                    legendItem.hidden = true;
                                } else {
                                    chart.show(index);
                                    legendItem.hidden = false;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Orders Count'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        }

        // Initialize Hourly Pattern Chart
        function initHourlyPatternChart() {
            const hourlyPatternCtx = document.getElementById('hourlyPatternChart').getContext('2d');
            hourlyPatternChart = new Chart(hourlyPatternCtx, {
                type: 'bar',
                data: {
                    labels: originalHourlyData.labels,
                    datasets: [{
                        label: 'Orders Verified',
                        data: originalHourlyData.orders,
                        backgroundColor: 'rgba(168, 85, 247, 0.8)',
                        borderRadius: 8,
                        yAxisID: 'y'
                    }, {
                        label: 'Avg Verification Time (min)',
                        data: originalHourlyData.times,
                        type: 'line',
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1',
                        hidden: true
                    }]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: 'Hourly Verification Pattern'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Orders Count'
                            },
                            ticks: {
                                stepSize: 1
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Minutes'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }

        // Initialize Employee Performance Chart
        function initEmployeePerformanceChart() {
            const employeeCtx = document.getElementById('employeePerformanceChart').getContext('2d');
            employeePerformanceChart = new Chart(employeeCtx, {
                type: 'bar',
                data: {
                    labels: originalEmployeeData.labels,
                    datasets: [{
                        label: 'Orders Verified',
                        data: originalEmployeeData.counts,
                        backgroundColor: 'rgba(34, 197, 94, 0.8)',
                        borderRadius: 8
                    }]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: 'Employee Performance - Order Count'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Orders Count'
                            }
                        }
                    }
                }
            });
        }

        // Update Daily Chart with new date range
        async function updateDailyChart() {
            const startDate = document.getElementById('dailyChartStart').value;
            const endDate = document.getElementById('dailyChartEnd').value;
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }
            
            if (startDate > endDate) {
                alert('Start date cannot be later than end date');
                return;
            }

            try {
                // Show loading
                dailyTrendChart.data.labels = ['Loading...'];
                dailyTrendChart.data.datasets[0].data = [0];
                dailyTrendChart.data.datasets[1].data = [0];
                dailyTrendChart.update();

                // Fetch new data
                const response = await fetch(`dashboardchart.php?type=daily&start_date=${startDate}&end_date=${endDate}`);
                const data = await response.json();
                
                if (data.success) {
                    dailyTrendChart.data.labels = data.labels;
                    dailyTrendChart.data.datasets[0].data = data.orders;
                    dailyTrendChart.data.datasets[1].data = data.amounts;
                    dailyTrendChart.options.plugins.title.text = `Daily Trend (${formatDateRange(startDate, endDate)})`;
                    dailyTrendChart.update();
                    
                    document.getElementById('dailyChartPeriod').textContent = formatDateRange(startDate, endDate);
                } else {
                    alert('Failed to load data: ' + (data.message || 'Unknown error'));
                    resetDailyChart();
                }
            } catch (error) {
                console.error('Error updating daily chart:', error);
                alert('Error loading data. Please try again.');
                resetDailyChart();
            }
        }

        // Update Hourly Chart with new date range
        async function updateHourlyChart() {
            const startDate = document.getElementById('hourlyChartStart').value;
            const endDate = document.getElementById('hourlyChartEnd').value;
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }
            
            if (startDate > endDate) {
                alert('Start date cannot be later than end date');
                return;
            }

            try {
                // Show loading
                hourlyPatternChart.data.datasets[0].data = new Array(24).fill(0);
                hourlyPatternChart.data.datasets[1].data = new Array(24).fill(0);
                hourlyPatternChart.update();

                // Fetch new data
                const response = await fetch(`get_chart_data.php?type=hourly&start_date=${startDate}&end_date=${endDate}`);
                const data = await response.json();
                
                if (data.success) {
                    hourlyPatternChart.data.datasets[0].data = data.orders;
                    hourlyPatternChart.data.datasets[1].data = data.times;
                    hourlyPatternChart.options.plugins.title.text = `Hourly Pattern (${formatDateRange(startDate, endDate)})`;
                    hourlyPatternChart.update();
                    
                    document.getElementById('hourlyChartPeriod').textContent = formatDateRange(startDate, endDate);
                } else {
                    alert('Failed to load data: ' + (data.message || 'Unknown error'));
                    resetHourlyChart();
                }
            } catch (error) {
                console.error('Error updating hourly chart:', error);
                alert('Error loading data. Please try again.');
                resetHourlyChart();
            }
        }

        // Update Employee Chart with new date range
        async function updateEmployeeChart() {
            const startDate = document.getElementById('employeeChartStart').value;
            const endDate = document.getElementById('employeeChartEnd').value;
            const viewType = document.getElementById('employeeChartView').value;
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }
            
            if (startDate > endDate) {
                alert('Start date cannot be later than end date');
                return;
            }

            try {
                // Show loading
                employeePerformanceChart.data.labels = ['Loading...'];
                employeePerformanceChart.data.datasets[0].data = [0];
                employeePerformanceChart.update();

                // Fetch new data
                const response = await fetch(`get_chart_data.php?type=employee&start_date=${startDate}&end_date=${endDate}&view=${viewType}`);
                const data = await response.json();
                
                if (data.success) {
                    employeePerformanceChart.data.labels = data.labels;
                    employeePerformanceChart.data.datasets[0].data = data.values;
                    
                    // Update chart title and axis label based on view type
                    let titleText = 'Employee Performance - ';
                    let yAxisLabel = '';
                    let backgroundColor = 'rgba(34, 197, 94, 0.8)';
                    
                    switch(viewType) {
                        case 'count':
                            titleText += 'Order Count';
                            yAxisLabel = 'Orders Count';
                            backgroundColor = 'rgba(34, 197, 94, 0.8)';
                            break;
                        case 'amount':
                            titleText += 'Total Amount';
                            yAxisLabel = 'Amount (₱)';
                            backgroundColor = 'rgba(59, 130, 246, 0.8)';
                            break;
                        case 'avg':
                            titleText += 'Average Amount';
                            yAxisLabel = 'Average Amount (₱)';
                            backgroundColor = 'rgba(168, 85, 247, 0.8)';
                            break;
                    }
                    
                    employeePerformanceChart.data.datasets[0].backgroundColor = backgroundColor;
                    employeePerformanceChart.options.plugins.title.text = `${titleText} (${formatDateRange(startDate, endDate)})`;
                    employeePerformanceChart.options.scales.y.title.text = yAxisLabel;
                    employeePerformanceChart.update();
                    
                    document.getElementById('employeeChartPeriod').textContent = formatDateRange(startDate, endDate);
                } else {
                    alert('Failed to load data: ' + (data.message || 'Unknown error'));
                    resetEmployeeChart();
                }
            } catch (error) {
                console.error('Error updating employee chart:', error);
                alert('Error loading data. Please try again.');
                resetEmployeeChart();
            }
        }

        // Reset Daily Chart to original data
        function resetDailyChart() {
            dailyTrendChart.data.labels = originalDailyData.labels;
            dailyTrendChart.data.datasets[0].data = originalDailyData.orders;
            dailyTrendChart.data.datasets[1].data = originalDailyData.amounts;
            dailyTrendChart.options.plugins.title.text = 'Daily Verification Trend';
            dailyTrendChart.update();
            
            document.getElementById('dailyChartStart').value = '<?= $start_date ?>';
            document.getElementById('dailyChartEnd').value = '<?= $end_date ?>';
            document.getElementById('dailyChartPeriod').textContent = '<?= formatPeriodName($start_date, $end_date) ?>';
        }

        // Reset Hourly Chart to original data
        function resetHourlyChart() {
            hourlyPatternChart.data.datasets[0].data = originalHourlyData.orders;
            hourlyPatternChart.data.datasets[1].data = originalHourlyData.times;
            hourlyPatternChart.options.plugins.title.text = 'Hourly Verification Pattern';
            hourlyPatternChart.update();
            
            document.getElementById('hourlyChartStart').value = '<?= $start_date ?>';
            document.getElementById('hourlyChartEnd').value = '<?= $end_date ?>';
            document.getElementById('hourlyChartPeriod').textContent = '<?= formatPeriodName($start_date, $end_date) ?>';
        }

        // Reset Employee Chart to original data
        function resetEmployeeChart() {
            employeePerformanceChart.data.labels = originalEmployeeData.labels;
            employeePerformanceChart.data.datasets[0].data = originalEmployeeData.counts;
            employeePerformanceChart.data.datasets[0].backgroundColor = 'rgba(34, 197, 94, 0.8)';
            employeePerformanceChart.options.plugins.title.text = 'Employee Performance - Order Count';
            employeePerformanceChart.options.scales.y.title.text = 'Orders Count';
            employeePerformanceChart.update();
            
            document.getElementById('employeeChartStart').value = '<?= $start_date ?>';
            document.getElementById('employeeChartEnd').value = '<?= $end_date ?>';
            document.getElementById('employeeChartView').value = 'count';
            document.getElementById('employeeChartPeriod').textContent = '<?= formatPeriodName($start_date, $end_date) ?>';
        }

        // Format date range for display
        function formatDateRange(start, end) {
            const startDate = new Date(start);
            const endDate = new Date(end);
            
            if (start === end) {
                return startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            } else {
                return startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + 
                       ' - ' + endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }
        }

        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initDailyTrendChart();
            initHourlyPatternChart();
            initEmployeePerformanceChart();
        });

        // Export to CSV function
        function exportToCSV() {
            const data = [
                ['Date Range', '<?= formatPeriodName($start_date, $end_date) ?>'],
                ['Generated', '<?= date('Y-m-d H:i:s') ?>'],
                [''],
                ['Summary'],
                ['Total Orders', '<?= $mainPeriodData['count'] ?? 0 ?>'],
                ['Total Amount', '<?= number_format($mainPeriodData['total_amount'] ?? 0, 2) ?>'],
                ['Average Amount', '<?= number_format($mainPeriodData['avg_amount'] ?? 0, 2) ?>'],
                ['Avg Verification Time (min)', '<?= number_format($mainPeriodData['avg_verification_minutes'] ?? 0, 1) ?>'],
                [''],
                ['Recent Verifications'],
                ['Order ID', 'Customer', 'Amount', 'Verified By', 'Date', 'Verification Time', 'Payment Method']
            ];
            
            // Add recent verifications data
            <?php 
            $recentVerifications->data_seek(0);
            while ($row = $recentVerifications->fetch_assoc()): 
                $minutes = $row['verification_time_minutes'];
                $timeStr = $minutes > 60 ? floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm' : $minutes . ' min';
            ?>
            data.push([
                '#<?= $row['id'] ?>',
                '<?= htmlspecialchars($row['customer_name']) ?>',
                '<?= number_format($row['final_total'], 2) ?>',
                'Employee <?= $row['verified_by'] ?>',
                '<?= date('M j, Y g:i A', strtotime($row['confirmed_at'])) ?>',
                '<?= $timeStr ?>',
                '<?= ucfirst($row['mode_payment']) ?>'
            ]);
            <?php endwhile; ?>

            let csvContent = "data:text/csv;charset=utf-8," + data.map(e => e.join(",")).join("\n");
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "verification_analytics_<?= $start_date ?>_to_<?= $end_date ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        window.print();
                        break;
                    case 'e':
                        e.preventDefault();
                        exportToCSV();
                        break;
                    case 'r':
                        e.preventDefault();
                        location.reload();
                        break;
                }
            }
        });

        // Form validation for all chart filters
        function validateDateInputs(startId, endId) {
            const startDate = document.getElementById(startId).value;
            const endDate = document.getElementById(endId).value;
            
            if (startDate && endDate && startDate > endDate) {
                alert('Start date cannot be later than end date');
                return false;
            }
            
            const today = new Date().toISOString().split('T')[0];
            if (endDate > today) {
                return confirm('End date is in the future. Continue anyway?');
            }
            
            return true;
        }

        // Add print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                .no-print { display: none !important; }
                body { font-size: 12px !important; }
                .bg-white { background: white !important; }
                .shadow-xl, .shadow-lg { box-shadow: none !important; }
                .rounded-2xl, .rounded-lg { border-radius: 0 !important; }
                .text-blue-600, .text-green-600, .text-purple-600, .text-orange-600 { 
                    color: black !important; 
                }
                canvas { max-height: 400px !important; }
                .chart-filter { display: none !important; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>