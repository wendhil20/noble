<?php
// owner_order_view.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
// ONLY SUPERADMIN CAN ACCESS
require_role(['superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: owner_dashboard.php");
    exit();
}

// Get order details
$orderSql = "
    SELECT 
        o.*,
        na.fullname as warehouse_staff_name,
        na.email as warehouse_staff_email,
        COUNT(DISTINCT oi.id) as total_items,
        COUNT(DISTINCT CASE 
            WHEN (oi.supplier_id IS NOT NULL AND oi.supplier_id > 0) 
              OR (oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '') 
            THEN oi.id 
        END) as assigned_items,
        COUNT(DISTINCT poa.id) as po_files_count,
        COUNT(DISTINCT ds.id) as scheduled_deliveries
    FROM orders o
    LEFT JOIN nobleaccount na ON o.warehouse_employee_id = na.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN po_attachments poa ON o.id = poa.order_id
    LEFT JOIN delivery_schedules ds ON o.id = ds.order_id
    WHERE o.id = ?
    GROUP BY o.id
    LIMIT 1
";

$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: owner_dashboard.php");
    exit();
}

// Get all items including replacements
$itemsSql = "
    SELECT 
        oi.id,
        oi.product_name,
        oi.codename,
        oi.type_name,
        oi.variant_color,
        oi.size,
        oi.quantity,
        oi.price,
        oi.subtotal,
        oi.origin,
        oi.tracking_status,
        COALESCE(sl.business_name, oi.manual_supplier_name, 'Not Assigned') as supplier_name,
        'original' as item_type,
        NULL as replacement_id,
        NULL as replacement_reason
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
    WHERE oi.order_id = ?
    
    UNION ALL
    
    SELECT 
        oi.id,
        oi.product_name,
        oi.codename,
        oi.type_name,
        oi.variant_color,
        oi.size,
        rr.replacement_quantity as quantity,
        oi.price,
        (rr.replacement_quantity * oi.price) as subtotal,
        oi.origin,
        rr.status as tracking_status,
        COALESCE(sl.business_name, oi.manual_supplier_name, 'Not Assigned') as supplier_name,
        'replacement' as item_type,
        rr.id as replacement_id,
        rr.reason as replacement_reason
    FROM replacement_requests rr
    LEFT JOIN order_items oi ON rr.order_item_id = oi.id
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
    WHERE rr.order_id = ? 
    AND rr.status IN ('approved', 'processing', 'In Warehouse', 'scheduled', 'ready_for_pickup', 'out_for_delivery', 'delivered')
    
    ORDER BY origin, supplier_name, product_name
";

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("ii", $order_id, $order_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Get defects
$defectsSql = "
    SELECT 
        dr.*,
        oi.product_name,
        oi.codename,
        na.fullname as reporter_name
    FROM defect_reports dr
    LEFT JOIN order_items oi ON dr.order_item_id = oi.id
    LEFT JOIN nobleaccount na ON dr.reported_by = na.id
    WHERE dr.order_id = ?
    ORDER BY dr.reported_at DESC
";

$defectsStmt = $conn->prepare($defectsSql);
$defectsStmt->bind_param("i", $order_id);
$defectsStmt->execute();
$defects = $defectsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$defectsStmt->close();

// Calculate summary
$totalOriginalItems = 0;
$totalReplacements = 0;
$totalDefects = count($defects);
$unresolvedDefects = 0;

foreach ($items as $item) {
    if ($item['item_type'] === 'replacement') {
        $totalReplacements++;
    } else {
        $totalOriginalItems++;
    }
}

foreach ($defects as $defect) {
    if ($defect['status'] !== 'resolved') {
        $unresolvedDefects++;
    }
}

$assignmentPercentage = $order['total_items'] > 0 ? round(($order['assigned_items'] / $order['total_items']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Order #<?php echo $order_id; ?> - Owner View</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fef3c7',
                            100: '#fde68a',
                            200: '#fcd34d',
                            300: '#fbbf24',
                            400: '#f59e0b',
                            500: '#d97706',
                            600: '#b45309',
                            700: '#92400e',
                            800: '#78350f',
                            900: '#451a03',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .item-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .replacement-card {
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
            border-left: 6px solid #ef4444;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-gradient-to-r from-amber-600 via-orange-600 to-yellow-600 text-white shadow-2xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="owner_dashboard.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 p-3 rounded-xl transition-all duration-200">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div class="bg-white bg-opacity-20 p-4 rounded-2xl backdrop-blur-lg">
                        <i class="fas fa-receipt text-3xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">Order #<?php echo $order_id; ?></h1>
                        <p class="text-white text-opacity-90 text-lg"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-4xl font-bold">₱<?php echo number_format((float)$order['total'], 2); ?></div>
                    <div class="text-sm text-white text-opacity-90 mt-1">
                        <?php
                        $statusColors = [
                            'Pending' => 'bg-yellow-400',
                            'Ongoing' => 'bg-orange-400',
                            'processing' => 'bg-blue-400',
                            'Ready for Pickup' => 'bg-indigo-400',
                            'Out for Delivery' => 'bg-purple-400',
                            'Out for Pickup' => 'bg-pink-400',
                            'Delivered' => 'bg-green-400',
                            'Picked Up' => 'bg-teal-400'
                        ];
                        $statusColor = $statusColors[$order['status']] ?? 'bg-gray-400';
                        ?>
                        <span class="inline-block px-4 py-2 rounded-full text-sm font-bold <?php echo $statusColor; ?> text-white shadow-lg">
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Order Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Total Items -->
            <div class="bg-white rounded-2xl p-6 shadow-lg border-2 border-blue-200">
                <div class="flex items-center justify-between">
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-4 rounded-xl">
                        <i class="fas fa-boxes text-white text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-blue-600"><?php echo $totalOriginalItems; ?></div>
                        <div class="text-sm text-gray-600">Total Items</div>
                    </div>
                </div>
            </div>

            <!-- Supplier Assignment -->
            <div class="bg-white rounded-2xl p-6 shadow-lg border-2 border-green-200">
                <div class="flex items-center justify-between">
                    <div class="bg-gradient-to-br from-green-500 to-green-600 p-4 rounded-xl">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-green-600"><?php echo $assignmentPercentage; ?>%</div>
                        <div class="text-sm text-gray-600">Assigned</div>
                    </div>
                </div>
            </div>

            <!-- Replacements -->
            <?php if ($totalReplacements > 0): ?>
                <div class="bg-white rounded-2xl p-6 shadow-lg border-2 border-red-200">
                    <div class="flex items-center justify-between">
                        <div class="bg-gradient-to-br from-red-500 to-red-600 p-4 rounded-xl">
                            <i class="fas fa-sync-alt text-white text-2xl"></i>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold text-red-600"><?php echo $totalReplacements; ?></div>
                            <div class="text-sm text-gray-600">Replacements</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Defects -->
            <?php if ($unresolvedDefects > 0): ?>
                <div class="bg-white rounded-2xl p-6 shadow-lg border-2 border-orange-200">
                    <div class="flex items-center justify-between">
                        <div class="bg-gradient-to-br from-orange-500 to-orange-600 p-4 rounded-xl">
                            <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold text-orange-600"><?php echo $unresolvedDefects; ?></div>
                            <div class="text-sm text-gray-600">Defects</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Customer & Staff Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Customer Info -->
            <div class="bg-white rounded-2xl p-6 shadow-lg border-2 border-gray-200">
                <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-user text-blue-600 mr-2"></i>
                    Customer Information
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-600">Name:</span>
                        <span class="font-bold text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-600">Email:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($order['email']); ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-600">Phone:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-gray-600">Order Date:</span>
                        <span class="font-medium text-gray-900"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Warehouse Staff Info -->
            <div class="bg-white rounded-2xl p-6 shadow-lg border-2 border-gray-200">
                <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-user-cog text-purple-600 mr-2"></i>
                    Warehouse Staff
                </h3>
                <?php if (!empty($order['warehouse_staff_name'])): ?>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Assigned To:</span>
                            <span class="font-bold text-purple-600"><?php echo htmlspecialchars($order['warehouse_staff_name']); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($order['warehouse_staff_email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">P.O. Files:</span>
                            <span class="font-bold text-green-600">
                                <i class="fas fa-file-excel mr-1"></i><?php echo $order['po_files_count']; ?> file(s)
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-gray-600">Scheduled Deliveries:</span>
                            <span class="font-bold text-blue-600">
                                <i class="fas fa-calendar-check mr-1"></i><?php echo $order['scheduled_deliveries']; ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4 text-center">
                        <i class="fas fa-exclamation-circle text-yellow-600 text-2xl mb-2"></i>
                        <p class="text-yellow-800 font-medium">No warehouse staff assigned yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items List -->
        <div class="bg-white rounded-2xl p-6 shadow-lg border-2 border-gray-200 mb-8">
            <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-box-open text-blue-600 mr-3"></i>
                Order Items (<?php echo count($items); ?>)
            </h3>

            <div class="space-y-4">
                <?php foreach ($items as $item): 
                    $isReplacement = $item['item_type'] === 'replacement';
                    $cardClass = $isReplacement ? 'replacement-card' : '';
                    
                    // Status colors
                    $statusColors = [
                        'processing' => 'bg-blue-100 text-blue-800',
                        'In Warehouse' => 'bg-indigo-100 text-indigo-800',
                        'scheduled' => 'bg-purple-100 text-purple-800',
                        'ready_for_pickup' => 'bg-yellow-100 text-yellow-800',
                        'out_for_delivery' => 'bg-orange-100 text-orange-800',
                        'delivered' => 'bg-green-100 text-green-800',
                        'approved' => 'bg-green-100 text-green-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ];
                    $statusColor = $statusColors[$item['tracking_status']] ?? 'bg-gray-100 text-gray-800';
                ?>
                    <div class="item-card border-2 border-gray-200 rounded-xl p-5 <?php echo $cardClass; ?>">
                        <?php if ($isReplacement): ?>
                            <div class="mb-3 flex items-center justify-between">
                                <span class="bg-red-600 text-white px-4 py-1.5 rounded-full text-xs font-bold flex items-center">
                                    <i class="fas fa-sync-alt mr-2"></i>
                                    REPLACEMENT ITEM
                                </span>
                                <span class="text-sm text-red-700 font-medium">
                                    Reason: <?php echo htmlspecialchars(ucfirst($item['replacement_reason'])); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="text-lg font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-3">
                                    <?php if (!empty($item['codename'])): ?>
                                        <div>
                                            <span class="text-gray-600">Code:</span>
                                            <span class="font-medium text-gray-900 ml-1"><?php echo htmlspecialchars($item['codename']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['type_name'])): ?>
                                        <div>
                                            <span class="text-gray-600">Type:</span>
                                            <span class="font-medium text-gray-900 ml-1"><?php echo htmlspecialchars($item['type_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['variant_color'])): ?>
                                        <div>
                                            <span class="text-gray-600">Color:</span>
                                            <span class="font-medium text-gray-900 ml-1"><?php echo htmlspecialchars($item['variant_color']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['size'])): ?>
                                        <div>
                                            <span class="text-gray-600">Size:</span>
                                            <span class="font-medium text-gray-900 ml-1"><?php echo htmlspecialchars($item['size']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-wrap items-center gap-4">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-boxes text-gray-500"></i>
                                        <span class="text-sm text-gray-600">Qty:</span>
                                        <span class="font-bold text-gray-900"><?php echo $item['quantity']; ?></span>
                                    </div>
                                    
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-peso-sign text-gray-500"></i>
                                        <span class="text-sm text-gray-600">Price:</span>
                                        <span class="font-bold text-gray-900">₱<?php echo number_format((float)$item['price'], 2); ?></span>
                                    </div>
                                    
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-calculator text-gray-500"></i>
                                        <span class="text-sm text-gray-600">Subtotal:</span>
                                        <span class="font-bold text-primary-600">₱<?php echo number_format((float)$item['subtotal'], 2); ?></span>
                                    </div>
                                    
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-building text-gray-500"></i>
                                        <span class="text-sm text-gray-600">Supplier:</span>
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($item['supplier_name']); ?></span>
                                    </div>
                                    
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-<?php echo $item['origin'] === 'local' ? 'home' : 'globe'; ?> text-gray-500"></i>
                                        <span class="text-sm text-gray-600">Origin:</span>
                                        <span class="font-medium <?php echo $item['origin'] === 'local' ? 'text-green-600' : 'text-blue-600'; ?>">
                                            <?php echo ucfirst($item['origin']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="ml-4">
                                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold <?php echo $statusColor; ?> whitespace-nowrap">
                                    <i class="fas fa-circle mr-2 text-xs"></i>
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['tracking_status']))); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Defects Section -->
        <?php if (!empty($defects)): ?>
            <div class="bg-white rounded-2xl p-6 shadow-lg border-2 border-orange-200 mb-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-exclamation-triangle text-orange-600 mr-3"></i>
                    Defect Reports (<?php echo count($defects); ?>)
                </h3>

                <div class="space-y-4">
                    <?php foreach ($defects as $defect): 
                        $severityColors = [
                            'minor' => 'border-yellow-300 bg-yellow-50',
                            'moderate' => 'border-orange-300 bg-orange-50',
                            'severe' => 'border-red-300 bg-red-50'
                        ];
                        $severityColor = $severityColors[$defect['severity']] ?? 'border-gray-300 bg-gray-50';
                        
                        $statusDefect = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'acknowledged' => 'bg-blue-100 text-blue-800',
                            'replacement_requested' => 'bg-purple-100 text-purple-800',
                            'resolved' => 'bg-green-100 text-green-800'
                        ];
                        $statusDefectColor = $statusDefect[$defect['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                        <div class="border-2 <?php echo $severityColor; ?> rounded-xl p-5">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-<?php echo explode('-', explode(' ', $severityColor)[0])[1]; ?>-600 text-white">
                                            <?php echo strtoupper($defect['severity']); ?>
                                        </span>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?php echo $statusDefectColor; ?>">
                                            <?php echo strtoupper(str_replace('_', ' ', $defect['status'])); ?>
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($defect['defect_type']); ?></h4>
                                    <p class="text-sm text-gray-700 mt-2">
                                        <strong>Item:</strong> <?php echo htmlspecialchars($defect['product_name']); ?>
                                        <?php if (!empty($defect['codename'])): ?>
                                            (<?php echo htmlspecialchars($defect['codename']); ?>)
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-600">Qty Defective</div>
                                    <div class="text-2xl font-bold text-red-600"><?php echo $defect['quantity_defective']; ?></div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 mb-3">
                                <div class="text-sm font-medium text-gray-700 mb-2">Description:</div>
                                <p class="text-gray-800"><?php echo htmlspecialchars($defect['defect_description']); ?></p>
                            </div>

                            <div class="flex items-center justify-between text-xs text-gray-600">
                                <div>
                                    <i class="fas fa-user mr-1"></i>
                                    Reported by <strong><?php echo htmlspecialchars($defect['reporter_name']); ?></strong>
                                </div>
                                <div>
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($defect['reported_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div class="flex justify-center">
            <a href="owner_dashboard.php" 
               class="bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white px-8 py-4 rounded-xl transition-all duration-200 flex items-center space-x-3 shadow-lg hover:shadow-xl hover:scale-105 font-bold text-lg">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Print Button (Floating) -->
    <div class="fixed bottom-8 right-8 z-50">
        <button onclick="window.print()" 
                class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white p-4 rounded-full shadow-2xl hover:shadow-3xl transition-all duration-200 hover:scale-110 flex items-center space-x-3">
            <i class="fas fa-print text-xl"></i>
            <span class="font-bold pr-2">Print</span>
        </button>
    </div>

    <script>
        // Print styles
        const printStyles = `
            @media print {
                body {
                    background: white !important;
                }
                .no-print {
                    display: none !important;
                }
                .bg-gradient-to-br {
                    background: white !important;
                }
            }
        `;
        
        const styleSheet = document.createElement("style");
        styleSheet.innerText = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>

</html>