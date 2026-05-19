<?php
session_name("nobleadmin"); 
include '../../connection/connect.php'; 
include '../role/roleaccount.php'; 
require_role(['sales', 'superadmin']); 

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

// Validate inputs
$type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if (empty($type) || empty($start_date) || empty($end_date)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Validate date range
if ($start_date > $end_date) {
    echo json_encode(['success' => false, 'message' => 'Start date cannot be later than end date']);
    exit();
}

try {
    if ($type === 'daily') {
        // Daily chart data
        $dailyData = $conn->query("
            SELECT 
                DATE(confirmed_at) as date,
                COUNT(*) as count,
                SUM(final_total) as total_amount
            FROM orders 
            WHERE verified_by IS NOT NULL 
            AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY DATE(confirmed_at)
            ORDER BY DATE(confirmed_at)
        ");

        // Create chart data arrays
        $labels = [];
        $orders = [];
        $amounts = [];
        
        // Initialize all dates in range with 0
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $labels[] = date('M j', $current);
            $orders[] = 0;
            $amounts[] = 0;
            $current = strtotime('+1 day', $current);
        }

        // Fill with actual data
        while ($row = $dailyData->fetch_assoc()) {
            $index = array_search(date('M j', strtotime($row['date'])), $labels);
            if ($index !== false) {
                $orders[$index] = (int)$row['count'];
                $amounts[$index] = (float)$row['total_amount'];
            }
        }

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'orders' => $orders,
            'amounts' => $amounts
        ]);

    } elseif ($type === 'hourly') {
        // Hourly chart data
        $hourlyData = $conn->query("
            SELECT 
                HOUR(confirmed_at) as hour,
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) as avg_minutes
            FROM orders 
            WHERE verified_by IS NOT NULL
            AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY HOUR(confirmed_at)
            ORDER BY HOUR(confirmed_at)
        ");

        // Initialize arrays for 24 hours
        $orders = array_fill(0, 24, 0);
        $times = array_fill(0, 24, 0);

        // Fill with actual data
        while ($row = $hourlyData->fetch_assoc()) {
            $orders[$row['hour']] = (int)$row['count'];
            $times[$row['hour']] = round($row['avg_minutes'] ?? 0, 1);
        }

        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'times' => $times
        ]);

    } elseif ($type === 'weekly') {
        // Weekly chart data (for future use)
        $weeklyData = $conn->query("
            SELECT 
                YEARWEEK(confirmed_at) as year_week,
                DATE(MIN(confirmed_at)) as week_start,
                COUNT(*) as count,
                SUM(final_total) as total_amount
            FROM orders 
            WHERE verified_by IS NOT NULL 
            AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY YEARWEEK(confirmed_at)
            ORDER BY YEARWEEK(confirmed_at)
        ");

        $labels = [];
        $orders = [];
        $amounts = [];

        while ($row = $weeklyData->fetch_assoc()) {
            $labels[] = 'Week of ' . date('M j', strtotime($row['week_start']));
            $orders[] = (int)$row['count'];
            $amounts[] = (float)$row['total_amount'];
        }

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'orders' => $orders,
            'amounts' => $amounts
        ]);

    } elseif ($type === 'monthly') {
        // Monthly chart data (for future use)
        $monthlyData = $conn->query("
            SELECT 
                YEAR(confirmed_at) as year,
                MONTH(confirmed_at) as month,
                MONTHNAME(confirmed_at) as month_name,
                COUNT(*) as count,
                SUM(final_total) as total_amount
            FROM orders 
            WHERE verified_by IS NOT NULL 
            AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY YEAR(confirmed_at), MONTH(confirmed_at)
            ORDER BY YEAR(confirmed_at), MONTH(confirmed_at)
        ");

        $labels = [];
        $orders = [];
        $amounts = [];

        while ($row = $monthlyData->fetch_assoc()) {
            $labels[] = $row['month_name'] . ' ' . $row['year'];
            $orders[] = (int)$row['count'];
            $amounts[] = (float)$row['total_amount'];
        }

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'orders' => $orders,
            'amounts' => $amounts
        ]);

    } elseif ($type === 'employee_daily') {
        // Employee performance by day (for future use)
        $employeeDailyData = $conn->query("
            SELECT 
                DATE(confirmed_at) as date,
                verified_by,
                COUNT(*) as count,
                SUM(final_total) as total_amount
            FROM orders 
            WHERE verified_by IS NOT NULL 
            AND DATE(confirmed_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY DATE(confirmed_at), verified_by
            ORDER BY DATE(confirmed_at), verified_by
        ");

        $result = [];
        $employees = [];
        $dates = [];

        while ($row = $employeeDailyData->fetch_assoc()) {
            $date = $row['date'];
            $employee = 'Employee ' . $row['verified_by'];
            
            if (!in_array($date, $dates)) {
                $dates[] = $date;
            }
            if (!in_array($employee, $employees)) {
                $employees[] = $employee;
            }
            
            $result[$employee][$date] = [
                'count' => (int)$row['count'],
                'amount' => (float)$row['total_amount']
            ];
        }

        // Format for chart
        $datasets = [];
        $colors = ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444', '#06B6D4'];
        $colorIndex = 0;

        foreach ($employees as $employee) {
            $employeeData = [];
            foreach ($dates as $date) {
                $employeeData[] = isset($result[$employee][$date]) ? $result[$employee][$date]['count'] : 0;
            }
            
            $datasets[] = [
                'label' => $employee,
                'data' => $employeeData,
                'backgroundColor' => $colors[$colorIndex % count($colors)] . '80',
                'borderColor' => $colors[$colorIndex % count($colors)],
                'borderWidth' => 2
            ];
            $colorIndex++;
        }

        // Format dates for display
        $formattedDates = array_map(function($date) {
            return date('M j', strtotime($date));
        }, $dates);

        echo json_encode([
            'success' => true,
            'labels' => $formattedDates,
            'datasets' => $datasets
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid chart type']);
    }

} catch (Exception $e) {
    error_log("Chart data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>