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
        -- Warehouse Staff
        ws.fullname as warehouse_staff_name,
        ws.email as warehouse_staff_email,
        ws.subrole as warehouse_staff_role,
        
        -- Sales Person (from referral code)
        sales.fullname as sales_person_name,
        sales.email as sales_person_email,
        sales.commission_rate as sales_commission_rate,
        rc.referral_code as sales_referral_code_used,
        
        -- Verified By (Accountant)
        accountant.fullname as accountant_name,
        accountant.email as accountant_email,
        
        -- Document Controller (from po_attachments)
        doc_controller.fullname as doc_controller_name,
        doc_controller.email as doc_controller_email,
        
        -- Dispatcher (from delivery_bookings)
        dispatcher.fullname as dispatcher_name,
        dispatcher.email as dispatcher_email,
        
        -- Warehouse Receiver (from po_receiver_assignments)
        receiver.fullname as receiver_name,
        receiver.email as receiver_email,
        
        COUNT(DISTINCT oi.id) as total_items,
        COUNT(DISTINCT CASE 
            WHEN (oi.supplier_id IS NOT NULL AND oi.supplier_id > 0) 
              OR (oi.supplier_id = 0 AND oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '') 
            THEN oi.id 
        END) as assigned_items,
        COUNT(DISTINCT poa.id) as po_files_count,
        COUNT(DISTINCT ds.id) as scheduled_deliveries
    FROM orders o
    
    -- Warehouse Staff
    LEFT JOIN nobleaccount ws ON o.warehouse_employee_id = ws.id
    
    -- Sales Person (from sales_user_id which links to nobleaccount)
    LEFT JOIN nobleaccount sales ON o.sales_user_id = sales.id
    LEFT JOIN referral_codes rc ON o.sales_referral_code = rc.referral_code AND rc.user_id = sales.id
    
    -- Accountant (verified_by)
    LEFT JOIN nobleaccount accountant ON o.verified_by = accountant.id
    
    -- Document Controller (get most recent from po_attachments)
    LEFT JOIN (
        SELECT poa1.order_id, poa1.approved_by
        FROM po_attachments poa1
        WHERE poa1.id = (
            SELECT poa2.id 
            FROM po_attachments poa2 
            WHERE poa2.order_id = poa1.order_id 
            AND poa2.approved_by IS NOT NULL
            ORDER BY poa2.approved_at DESC 
            LIMIT 1
        )
    ) latest_po ON o.id = latest_po.order_id
    LEFT JOIN nobleaccount doc_controller ON latest_po.approved_by = doc_controller.id
    
    -- ✅ FIXED: Dispatcher (get most recent from delivery_bookings)
    LEFT JOIN (
        SELECT db1.order_id, db1.dispatcher_id
        FROM delivery_bookings db1
        WHERE db1.id = (
            SELECT db2.id 
            FROM delivery_bookings db2 
            WHERE db2.order_id = db1.order_id 
            ORDER BY db2.created_at DESC 
            LIMIT 1
        )
    ) latest_booking ON o.id = latest_booking.order_id
    LEFT JOIN nobleaccount dispatcher ON latest_booking.dispatcher_id = dispatcher.id
    
    -- Warehouse Receiver (get most recent from po_receiver_assignments)
    LEFT JOIN (
        SELECT pra1.order_id, pra1.receiver_id
        FROM po_receiver_assignments pra1
        WHERE pra1.id = (
            SELECT pra2.id 
            FROM po_receiver_assignments pra2 
            WHERE pra2.order_id = pra1.order_id 
            ORDER BY pra2.assigned_at DESC 
            LIMIT 1
        )
    ) latest_pra ON o.id = latest_pra.order_id
    LEFT JOIN nobleaccount receiver ON latest_pra.receiver_id = receiver.id
    
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
    <style>
    @media print {
        body {
            background: white !important;
        }
        .no-print, button {
            display: none !important;
        }
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    table {
        border-collapse: collapse;
    }

    .hover\:shadow-md:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
</style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Professional Header -->
    <div class="bg-white border-b-2 border-gray-200 shadow-sm">
        <div class="max-w-[1400px] mx-auto px-6 py-5">
            <div class="flex items-center justify-between">
                <!-- Left: Back Button + Order Info -->
                <div class="flex items-center space-x-4">
                    <a href="owner_dashboard.php" class="flex items-center justify-center w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left text-gray-700"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Order #<?php echo $order_id; ?></h1>
                        <p class="text-sm text-gray-600 mt-0.5">
                            <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($order['customer_name']); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-calendar mr-1"></i><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Right: Status + Total -->
                <div class="flex items-center space-x-6">
                    <div class="text-right">
                        <div class="text-xs text-gray-500 mb-1">Order Status</div>
                        <?php
                        $statusColors = [
                            'Pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                            'Ongoing' => 'bg-orange-100 text-orange-800 border-orange-200',
                            'processing' => 'bg-blue-100 text-blue-800 border-blue-200',
                            'Ready for Pickup' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                            'Out for Delivery' => 'bg-purple-100 text-purple-800 border-purple-200',
                            'Out for Pickup' => 'bg-pink-100 text-pink-800 border-pink-200',
                            'Delivered' => 'bg-green-100 text-green-800 border-green-200',
                            'Picked Up' => 'bg-teal-100 text-teal-800 border-teal-200'
                        ];
                        $statusColor = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                        ?>
                        <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold border-2 <?php echo $statusColor; ?>">
                            <i class="fas fa-circle text-xs mr-2"></i>
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </div>
                    <div class="text-right pl-6 border-l-2 border-gray-200">
                        <div class="text-xs text-gray-500 mb-1">Total Amount</div>
                        <div class="text-3xl font-bold text-gray-900">₱<?php echo number_format((float)$order['total'], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="max-w-[1400px] mx-auto px-6 py-6">
        
        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <!-- Total Items -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Items</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $totalOriginalItems; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-boxes text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Assignment -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Assigned</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $assignmentPercentage; ?>%</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Replacements -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Replacements</p>
                        <p class="text-3xl font-bold text-<?php echo $totalReplacements > 0 ? 'red' : 'gray'; ?>-600"><?php echo $totalReplacements; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sync-alt text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Defects -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Defects</p>
                        <p class="text-3xl font-bold text-<?php echo $unresolvedDefects > 0 ? 'orange' : 'gray'; ?>-600"><?php echo $unresolvedDefects; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-3 gap-6 mb-6">
            
            <!-- Left Column: Customer Info (1/3) -->
            <div class="bg-white rounded-lg border border-gray-200 p-5">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4 flex items-center">
                    <i class="fas fa-user w-5 text-blue-600 mr-2"></i>
                    Customer Information
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Name:</span>
                        <span class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Email:</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['email']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Phone:</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['mobile'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-sm text-gray-600">Order Date:</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Handlers (2/3) -->
            <div class="col-span-2 bg-white rounded-lg border border-gray-200 p-5">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4 flex items-center">
                    <i class="fas fa-users-cog w-5 text-indigo-600 mr-2"></i>
                    Order Handlers
                </h3>
                
                <div class="grid grid-cols-2 gap-4">
                    
                    <!-- Sales Person -->
                    <?php if (!empty($order['sales_person_name'])): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-user-tie text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-green-700 uppercase mb-1">Sales Rep</p>
                                <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($order['sales_person_name']); ?></p>
                                <p class="text-xs text-gray-600 truncate mt-1"><?php echo htmlspecialchars($order['sales_person_email']); ?></p>
                                <?php if (!empty($order['sales_referral_code_used'])): ?>
                                <div class="mt-2 inline-flex items-center px-2 py-1 bg-white border border-green-300 rounded text-xs">
                                    <span class="font-mono font-bold text-green-700"><?php echo htmlspecialchars($order['sales_referral_code_used']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Accountant -->
                    <?php if (!empty($order['accountant_name'])): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-calculator text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-blue-700 uppercase mb-1">Accountant</p>
                                <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($order['accountant_name']); ?></p>
                                <p class="text-xs text-gray-600 truncate mt-1"><?php echo htmlspecialchars($order['accountant_email']); ?></p>
                                <div class="mt-2 inline-flex items-center text-xs text-blue-700">
                                    <i class="fas fa-check-circle mr-1"></i> Payment Verified
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Warehouse Receiver -->
                    <?php if (!empty($order['receiver_name'])): ?>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-boxes text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-purple-700 uppercase mb-1">Receiver</p>
                                <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($order['receiver_name']); ?></p>
                                <p class="text-xs text-gray-600 truncate mt-1"><?php echo htmlspecialchars($order['receiver_email']); ?></p>
                                <div class="mt-2 inline-flex items-center text-xs text-purple-700">
                                    <i class="fas fa-clipboard-check mr-1"></i> Receiving Items
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Warehouse Staff -->
                    <?php if (!empty($order['warehouse_staff_name'])): ?>
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-warehouse text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-indigo-700 uppercase mb-1"><?php echo $order['warehouse_staff_role'] ?? 'Staff'; ?></p>
                                <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($order['warehouse_staff_name']); ?></p>
                                <p class="text-xs text-gray-600 truncate mt-1"><?php echo htmlspecialchars($order['warehouse_staff_email']); ?></p>
                                <div class="mt-2 inline-flex items-center text-xs text-indigo-700">
                                    <i class="fas fa-tasks mr-1"></i> Managing Order
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Document Controller -->
                    <?php if (!empty($order['doc_controller_name'])): ?>
                    <div class="bg-cyan-50 border border-cyan-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-10 h-10 bg-cyan-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-file-signature text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-cyan-700 uppercase mb-1">Document Controller</p>
                                <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($order['doc_controller_name']); ?></p>
                                <p class="text-xs text-gray-600 truncate mt-1"><?php echo htmlspecialchars($order['doc_controller_email']); ?></p>
                                <div class="mt-2 inline-flex items-center text-xs text-cyan-700">
                                    <i class="fas fa-stamp mr-1"></i> P.O. Approved
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Dispatcher -->
                    <?php if (!empty($order['dispatcher_name'])): ?>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-truck text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-orange-700 uppercase mb-1">Dispatcher</p>
                                <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($order['dispatcher_name']); ?></p>
                                <p class="text-xs text-gray-600 truncate mt-1"><?php echo htmlspecialchars($order['dispatcher_email']); ?></p>
                                <div class="mt-2 inline-flex items-center text-xs text-orange-700">
                                    <i class="fas fa-route mr-1"></i> Delivery Scheduled
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Empty State -->
                    <?php if (empty($order['sales_person_name']) && empty($order['accountant_name']) && 
                              empty($order['receiver_name']) && empty($order['warehouse_staff_name']) && 
                              empty($order['doc_controller_name']) && empty($order['dispatcher_name'])): ?>
                    <div class="col-span-2 bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                        <i class="fas fa-users-slash text-gray-400 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-600">No handlers assigned yet</p>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>

        <!-- Order Items Section -->
        <div class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4 flex items-center">
                <i class="fas fa-box-open w-5 text-blue-600 mr-2"></i>
                Order Items (<?php echo count($items); ?>)
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-200">
                            <th class="text-left py-3 px-4 text-xs font-bold text-gray-700 uppercase tracking-wide">Product</th>
                            <th class="text-center py-3 px-4 text-xs font-bold text-gray-700 uppercase tracking-wide">Qty</th>
                            <th class="text-right py-3 px-4 text-xs font-bold text-gray-700 uppercase tracking-wide">Price</th>
                            <th class="text-right py-3 px-4 text-xs font-bold text-gray-700 uppercase tracking-wide">Subtotal</th>
                            <th class="text-left py-3 px-4 text-xs font-bold text-gray-700 uppercase tracking-wide">Supplier</th>
                            <th class="text-center py-3 px-4 text-xs font-bold text-gray-700 uppercase tracking-wide">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($items as $item): 
                            $isReplacement = $item['item_type'] === 'replacement';
                            $statusColors = [
                                'processing' => 'bg-blue-100 text-blue-700',
                                'In Warehouse' => 'bg-indigo-100 text-indigo-700',
                                'scheduled' => 'bg-purple-100 text-purple-700',
                                'ready_for_pickup' => 'bg-yellow-100 text-yellow-700',
                                'out_for_delivery' => 'bg-orange-100 text-orange-700',
                                'delivered' => 'bg-green-100 text-green-700',
                                'approved' => 'bg-green-100 text-green-700',
                                'cancelled' => 'bg-red-100 text-red-700'
                            ];
                            $statusColor = $statusColors[$item['tracking_status']] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <tr class="hover:bg-gray-50 <?php echo $isReplacement ? 'bg-red-50' : ''; ?>">
                            <td class="py-3 px-4">
                                <?php if ($isReplacement): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-600 text-white mb-1">
                                    <i class="fas fa-sync-alt mr-1"></i> REPLACEMENT
                                </span>
                                <?php endif; ?>
                                <div class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="text-xs text-gray-600 mt-1">
                                    <?php if (!empty($item['codename'])): ?>
                                        <span class="mr-2"><?php echo htmlspecialchars($item['codename']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['variant_color'])): ?>
                                        <span class="mr-2"><?php echo htmlspecialchars($item['variant_color']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['size'])): ?>
                                        <span><?php echo htmlspecialchars($item['size']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center justify-center w-10 h-10 bg-gray-100 rounded-lg font-bold text-gray-900">
                                    <?php echo $item['quantity']; ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-gray-900">₱<?php echo number_format((float)$item['price'], 2); ?></td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900">₱<?php echo number_format((float)$item['subtotal'], 2); ?></td>
                            <td class="py-3 px-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['supplier_name']); ?></div>
                                <div class="text-xs text-gray-600 mt-1">
                                    <i class="fas fa-<?php echo $item['origin'] === 'local' ? 'home' : 'globe'; ?> mr-1"></i>
                                    <?php echo ucfirst($item['origin']); ?>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColor; ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['tracking_status']))); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Defects Section (if any) -->
        <?php if (!empty($defects)): ?>
        <div class="bg-white rounded-lg border border-orange-200 p-5 mb-6">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4 flex items-center">
                <i class="fas fa-exclamation-triangle w-5 text-orange-600 mr-2"></i>
                Defect Reports (<?php echo count($defects); ?>)
            </h3>

            <div class="space-y-3">
                <?php foreach ($defects as $defect): 
                    $severityColors = [
                        'minor' => 'border-yellow-200 bg-yellow-50',
                        'moderate' => 'border-orange-200 bg-orange-50',
                        'severe' => 'border-red-200 bg-red-50'
                    ];
                    $severityColor = $severityColors[$defect['severity']] ?? 'border-gray-200 bg-gray-50';
                ?>
                <div class="border-2 <?php echo $severityColor; ?> rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-red-600 text-white">
                                    <?php echo strtoupper($defect['severity']); ?>
                                </span>
                                <span class="font-bold text-gray-900"><?php echo htmlspecialchars($defect['defect_type']); ?></span>
                            </div>
                            <p class="text-sm text-gray-700 mb-2">
                                <strong>Item:</strong> <?php echo htmlspecialchars($defect['product_name']); ?>
                                <?php if (!empty($defect['codename'])): ?>
                                    (<?php echo htmlspecialchars($defect['codename']); ?>)
                                <?php endif; ?>
                            </p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($defect['defect_description']); ?></p>
                        </div>
                        <div class="text-right ml-4">
                            <div class="text-xs text-gray-600">Qty Defective</div>
                            <div class="text-2xl font-bold text-red-600"><?php echo $defect['quantity_defective']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>

</html>