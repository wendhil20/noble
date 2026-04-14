<?php
// File: admin/notification/notification_history.php
session_name("nobleadmin");
session_start();
date_default_timezone_set('Asia/Manila');

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_once '../notification/main-handler-notif-page-2.php';

// Check user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get filter type
$filter_type = $_GET['type'] ?? null;

// Get history logs
if ($filter_type) {
    $history = getNotificationHistory($conn, 100, $filter_type);
} else {
    $history = getNotificationHistory($conn, 100);
}

// Get notification counts by type
$typeStatsQuery = "SELECT notification_type, COUNT(*) as count 
                   FROM admin_notification_history 
                   GROUP BY notification_type";
$typeStatsResult = $conn->query($typeStatsQuery);
$typeStats = [];
while ($row = $typeStatsResult->fetch_assoc()) {
    $typeStats[$row['notification_type']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification History Log</title>
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #ea580c;
            border-radius: 10px;
        }
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Notification History</h1>
            <p class="text-gray-600">Complete log of all notification activities</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Notifications</p>
                        <p class="text-3xl font-bold text-gray-900"><?= array_sum($typeStats) ?></p>
                    </div>
                    <i class="ri-notification-2-line text-4xl text-orange-500 opacity-20"></i>
                </div>
            </div>

            <?php foreach ($typeStats as $type => $count): ?>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium capitalize"><?= ucfirst(str_replace('_', ' ', $type)) ?></p>
                            <p class="text-3xl font-bold text-gray-900"><?= $count ?></p>
                        </div>
                        <i class="ri-notification-2-line text-4xl text-blue-500 opacity-20"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>
            <div class="flex flex-wrap gap-2">
                <a href="" class="px-4 py-2 rounded-lg font-medium transition-all <?= !$filter_type ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    All
                </a>
                <?php foreach (array_keys($typeStats) as $type): ?>
                    <a href="?type=<?= urlencode($type) ?>" class="px-4 py-2 rounded-lg font-medium transition-all <?= $filter_type === $type ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                        <?= ucfirst(str_replace('_', ' ', $type)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- History Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b-2 border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">ID</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Title</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Type</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Action</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Admin</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Date & Time</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <i class="ri-inbox-archive-line text-4xl opacity-30 mb-2"></i>
                                    <p>No notification history found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $log): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-900">#<?= $log['notification_id'] ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <p class="text-gray-900 font-medium"><?= htmlspecialchars($log['title']) ?></p>
                                        <p class="text-gray-500 text-xs mt-1 truncate"><?= htmlspecialchars($log['message']) ?></p>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                            <?= ucfirst(str_replace('_', ' ', $log['notification_type'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold <?= 
                                            $log['action'] === 'created' ? 'bg-green-100 text-green-800' : 
                                            ($log['action'] === 'read' ? 'bg-blue-100 text-blue-800' : 
                                            ($log['action'] === 'deleted' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) 
                                        ?>">
                                            <?= ucfirst($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($log['admin_name'] ?? 'System') ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900 whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500 font-mono text-xs"><?= htmlspecialchars($log['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Export Button -->
        <div class="mt-8 flex justify-end">
            <button onclick="exportToCSV()" class="px-6 py-3 bg-orange-600 text-white rounded-lg font-semibold hover:bg-orange-700 transition-all flex items-center space-x-2">
                <i class="ri-download-2-line"></i>
                <span>Export History</span>
            </button>
        </div>
    </div>

    <script>
        function exportToCSV() {
            const table = document.querySelector('table');
            const rows = Array.from(table.querySelectorAll('tr'));
            const csv = rows.map(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(cell => {
                    const text = cell.textContent.trim().replace(/"/g, '""');
                    return `"${text}"`;
                }).join(',');
            }).join('\n');

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `notification-history-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>

</body>
</html>
?>