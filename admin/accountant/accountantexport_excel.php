<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant']);

// Build query with same filters as main page
$sql = "SELECT * FROM accountantrecord WHERE 1=1";
$params = [];
$types = "";

// Get filter values from URL
$filter_project = isset($_GET['project']) ? $_GET['project'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_forms = isset($_GET['forms']) ? $_GET['forms'] : '';

// Apply filters
if (!empty($filter_project)) {
    $sql .= " AND project_name LIKE ?";
    $params[] = "%" . $filter_project . "%";
    $types .= "s";
}

if (!empty($filter_date_from)) {
    $sql .= " AND date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $sql .= " AND date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

if (!empty($filter_forms)) {
    $sql .= " AND forms LIKE ?";
    $params[] = "%" . $filter_forms . "%";
    $types .= "s";
}

$sql .= " ORDER BY date DESC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Generate filename with filters info
$filename = "accountant_records_" . date('Y-m-d');
if (!empty($filter_project)) {
    $filename .= "_" . preg_replace('/[^a-zA-Z0-9]/', '_', $filter_project);
}
if (!empty($filter_date_from) || !empty($filter_date_to)) {
    $filename .= "_" . ($filter_date_from ?: 'start') . "_to_" . ($filter_date_to ?: 'end');
}
$filename .= ".xls";

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            font-family: Arial, sans-serif;
        }
        th, td { 
            border: 0.5pt solid #000; 
            padding: 8px; 
            text-align: center; 
            font-family: Arial, sans-serif;
            vertical-align: middle;
        }
        th { 
            background-color: #4CAF50; 
            color: white; 
            font-weight: bold; 
            text-align: center;
        }
        .amount { 
            text-align: center; 
        }
        .header-info {
            margin-bottom: 20px;
            font-family: Arial, sans-serif;
        }
        .header-info p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="header-info">
        <h2>Accountant Records Export</h2>
        <p><strong>Export Date:</strong> <?php echo date('F d, Y h:i A'); ?></p>
        
        <?php if (!empty($filter_project)): ?>
            <p><strong>Project Filter:</strong> <?php echo htmlspecialchars($filter_project); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($filter_date_from) || !empty($filter_date_to)): ?>
            <p><strong>Date Range:</strong> 
                <?php echo !empty($filter_date_from) ? date('M d, Y', strtotime($filter_date_from)) : 'Start'; ?>
                to 
                <?php echo !empty($filter_date_to) ? date('M d, Y', strtotime($filter_date_to)) : 'End'; ?>
            </p>
        <?php endif; ?>
        
        <?php if (!empty($filter_forms)): ?>
            <p><strong>Forms Filter:</strong> <?php echo htmlspecialchars($filter_forms); ?></p>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Project Name</th>
                <th>Date</th>
                <th>Particular</th>
                <th>Sale</th>
                <th style="width: 20px;"></th>
                <th>Expense</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_sale = 0;
            $total_expense = 0;
            while ($row = $result->fetch_assoc()): 
                if ($row['forms'] === 'Sale') {
                    $total_sale += $row['amount'];
                } else {
                    $total_expense += $row['amount'];
                }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['project_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                    <td><?php echo htmlspecialchars($row['particular']); ?></td>
                    <td class="amount"><?php echo $row['forms'] === 'Sale' ? '₱' . number_format($row['amount'], 2) : ''; ?></td>
                    <td style="border: none; background-color: white;"></td>
                    <td class="amount"><?php echo $row['forms'] === 'Expense' ? '₱' . number_format($row['amount'], 2) : ''; ?></td>
                </tr>
            <?php endwhile; ?>
            
            <!-- Empty row for spacing -->
            <tr>
                <td colspan="6" style="border: none; height: 20px; background-color: white;"></td>
            </tr>
            
            <!-- Totals Row -->
            <tr style="font-weight: bold;">
                <td colspan="3" style="text-align: right; background-color: #e8f5e9;">total:</td>
                <td class="amount" style="background-color: #d4edda;">₱<?php echo number_format($total_sale, 2); ?></td>
                <td style="border: none; background-color: white;"></td>
                <td class="amount" style="background-color: #f8d7da;">₱<?php echo number_format($total_expense, 2); ?></td>
            </tr>
        </tbody>
    </table>
</body>
</html>