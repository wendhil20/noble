<?php
// view_defects.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin', 'sales']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    header("Location: order_list.php");
    exit();
}

// Get order details
$orderStmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: order_list.php");
    exit();
}

// Get all defect reports for this order
$defectsStmt = $conn->prepare("
    SELECT 
        dr.*,
        oi.product_name,
        oi.codename,
        oi.size,
        oi.variant_color,
        na.fullname as reporter_name
    FROM defect_reports dr
    LEFT JOIN order_items oi ON dr.order_item_id = oi.id
    LEFT JOIN nobleaccount na ON dr.reported_by = na.id
    WHERE dr.order_id = ?
    ORDER BY dr.reported_at DESC
");
$defectsStmt->bind_param("i", $order_id);
$defectsStmt->execute();
$defects = $defectsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$defectsStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defect Reports - Order #<?php echo $order_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="order_list.php" class="bg-gray-100 hover:bg-gray-200 p-3 rounded-xl transition-all duration-200">
                        <i class="fas fa-arrow-left text-gray-600"></i>
                    </a>
                    <div class="bg-gradient-to-r from-orange-500 to-red-600 p-4 rounded-xl">
                        <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Defect Reports</h1>
                        <p class="text-gray-600">Order #<?php echo $order_id; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-orange-600"><?php echo count($defects); ?></div>
                    <div class="text-sm text-gray-600">Total Reports</div>
                </div>
            </div>
        </div>

        <?php if (empty($defects)): ?>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-12 text-center">
                <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">No Defects Reported</h3>
                <p class="text-gray-600">This order has no defect reports.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($defects as $defect): 
                    $severityColors = [
                        'minor' => ['bg' => 'yellow', 'text' => 'yellow'],
                        'moderate' => ['bg' => 'orange', 'text' => 'orange'],
                        'severe' => ['bg' => 'red', 'text' => 'red']
                    ];
                    $colors = $severityColors[$defect['severity']] ?? ['bg' => 'gray', 'text' => 'gray'];
                    
                    $statusColors = [
                        'pending' => 'yellow',
                        'acknowledged' => 'blue',
                        'replacement_requested' => 'purple',
                        'resolved' => 'green'
                    ];
                    $statusColor = $statusColors[$defect['status']] ?? 'gray';
                ?>
                    <div class="bg-white rounded-xl shadow-lg border-l-4 border-<?php echo $colors['bg']; ?>-500 p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-3">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-<?php echo $colors['bg']; ?>-100 text-<?php echo $colors['text']; ?>-800">
                                        <i class="fas fa-exclamation-circle mr-2"></i>
                                        <?php echo strtoupper($defect['severity']); ?> SEVERITY
                                    </span>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                        <?php echo strtoupper(str_replace('_', ' ', $defect['status'])); ?>
                                    </span>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($defect['product_name']); ?>
                                </h3>
                                <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-3">
                                    <span><i class="fas fa-barcode mr-1"></i><?php echo htmlspecialchars($defect['codename']); ?></span>
                                    <span><i class="fas fa-ruler mr-1"></i><?php echo htmlspecialchars($defect['size']); ?></span>
                                    <span><i class="fas fa-palette mr-1"></i><?php echo htmlspecialchars($defect['variant_color']); ?></span>
                                    <span><i class="fas fa-boxes mr-1"></i>Qty Defective: <strong><?php echo $defect['quantity_defective']; ?></strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-<?php echo $colors['bg']; ?>-50 rounded-lg p-4 mb-4">
                            <div class="font-bold text-<?php echo $colors['text']; ?>-900 mb-2">
                                <i class="fas fa-tag mr-2"></i><?php echo htmlspecialchars($defect['defect_type']); ?>
                            </div>
                            <div class="text-gray-700">
                                <?php echo nl2br(htmlspecialchars($defect['defect_description'])); ?>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm text-gray-600 pt-4 border-t border-gray-200">
    <div>
        <i class="fas fa-user mr-2"></i>
        Reported by <strong><?php echo htmlspecialchars($defect['reporter_name']); ?></strong>
    </div>
    <div class="flex items-center space-x-4">
        <div>
            <i class="fas fa-calendar mr-2"></i>
            <?php echo date('M j, Y g:i A', strtotime($defect['reported_at'])); ?>
        </div>
        <?php if ($defect['status'] !== 'resolved'): ?>
            <button onclick="resolveThisDefect(<?php echo $defect['id']; ?>)" 
                class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm font-medium transition-colors duration-200">
                <i class="fas fa-check mr-1"></i>Mark Resolved
            </button>
        <?php endif; ?>
    </div>
</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
function resolveThisDefect(defectId) {
    if (!confirm('Mark this defect as resolved?')) {
        return;
    }
    
    fetch('resolve_single_defect.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            defect_id: defectId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Defect marked as resolved!');
            window.location.reload();
        } else {
            alert('✗ Failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('✗ Failed to resolve defect.');
    });
}
</script>
</body>
</html>