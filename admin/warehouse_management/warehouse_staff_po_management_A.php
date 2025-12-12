<?php
//warehouse_staff_po_management_A.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin', 'sales']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Check if order_id is provided instead of customer_email
if (!isset($_GET['order_id'])) {
    header("Location: warehouse_staff_management_main.php");
    exit();
}

$order_id = intval($_GET['order_id']);

// Get the specific order
$orderStmt = $conn->prepare("
    SELECT id, customer_name, email, created_at, status, total 
    FROM orders 
    WHERE id = ? 
    LIMIT 1
");
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();

if ($orderResult->num_rows === 0) {
    header("Location: warehouse_staff_management_main.php");
    exit();
}

$order = $orderResult->fetch_assoc();
$orderStmt->close();

$customer_name = $order['customer_name'];
$customer_email = $order['email'];

// Get all order items for this specific order with original_price from product_variants
$itemStmt = $conn->prepare("
    SELECT 
        oi.id as item_id,
        oi.order_id,
        oi.product_id,
        oi.variant_id,
        oi.product_name,
        oi.size,
        oi.variant_color,
        oi.codename,
        oi.descrip6,
        oi.descrip7,
        oi.price as order_price,
        COALESCE(slp.supplier_price, oi.price) as current_price,
        oi.quantity,
        (COALESCE(slp.supplier_price, oi.price) * oi.quantity) as calculated_subtotal,
        oi.subtotal as original_subtotal,
        oi.origin,
        oi.supplier_id,
        p.product_name as product_full_name,
        p.codename as product_code,
        pv.namevariant,
        pv.color as variant_color_db,
        pv.size as variant_size_db,
        o.created_at as order_date,
        o.status as order_status,
        slp.supplier_price
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    LEFT JOIN products p ON pv.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id
    LEFT JOIN supp_link_products slp ON oi.variant_id = slp.variant_id 
        AND oi.supplier_id = slp.supplier_id 
        AND slp.status = 'active'
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// Calculate new order total based on original prices
$newOrderTotal = 0;
foreach ($allItems as $item) {
    $newOrderTotal += $item['calculated_subtotal'];
}

// Count unassigned items and items with primary suppliers available
$unassignedCount = 0;
$primaryAvailableCount = 0;

// For each item, get available suppliers from supp_link_products
$autoAssignedCount = 0;
$autoAssignedItems = [];

for ($i = 0; $i < count($allItems); $i++) {
    // Initialize arrays to prevent undefined key errors
    $allItems[$i]['linked_suppliers'] = [];
    $allItems[$i]['primary_supplier'] = null;

    // Check if item is unassigned
    $isUnassigned = (is_null($allItems[$i]['supplier_id']) || $allItems[$i]['supplier_id'] == 0);
    
    if ($isUnassigned) {
        $unassignedCount++;
    }

    // Get linked suppliers with proper JOIN - using variant_id
if ($allItems[$i]['variant_id']) {
    $linkedSuppStmt = $conn->prepare("
        SELECT 
            slp.supplier_id,
            slp.supplier_type,
            slp.supplier_price,
            sl.business_name,
            sl.primary_contact_name,
            sl.email_address,
            sl.phone_number,
            slp.status as link_status,
            sl.status as supplier_status
        FROM supp_link_products slp
        INNER JOIN supplier_list sl ON slp.supplier_id = sl.id
        WHERE slp.variant_id = ? 
            AND slp.status = 'active' 
            AND sl.status = 'active'
        ORDER BY 
            CASE slp.supplier_type 
                WHEN 'primary' THEN 1 
                WHEN 'secondary' THEN 2 
                ELSE 3 
            END ASC, 
            sl.business_name ASC
    ");
    $linkedSuppStmt->bind_param("i", $allItems[$i]['variant_id']);
        $linkedSuppStmt->execute();
        $linkedResult = $linkedSuppStmt->get_result();
        $allItems[$i]['linked_suppliers'] = $linkedResult->fetch_all(MYSQLI_ASSOC);
        $linkedSuppStmt->close();

        // Find primary supplier
        foreach ($allItems[$i]['linked_suppliers'] as $supplier) {
            if ($supplier['supplier_type'] === 'primary') {
                $allItems[$i]['primary_supplier'] = $supplier;
                if ($isUnassigned) {
                    $primaryAvailableCount++;
                    
                    // AUTO-ASSIGN: If item is unassigned and has a primary supplier, assign it automatically
                    $autoAssignStmt = $conn->prepare("
                        UPDATE order_items 
                        SET supplier_id = ?
                        WHERE id = ?
                    ");
                    $autoAssignStmt->bind_param("ii", $supplier['supplier_id'], $allItems[$i]['item_id']);
                    
                    if ($autoAssignStmt->execute()) {
                        // Update the current item data to reflect the assignment
                        $allItems[$i]['supplier_id'] = $supplier['supplier_id'];
                        $autoAssignedCount++;
                        $autoAssignedItems[] = [
                            'item_id' => $allItems[$i]['item_id'],
                            'product_name' => $allItems[$i]['product_name'],
                            'supplier_name' => $supplier['business_name']
                        ];
                        $unassignedCount--; // Decrease unassigned count
                    }
                    $autoAssignStmt->close();
                }
                break;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>P.O Management - Order #<?php echo $order['id']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .dropdown-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .dropdown-content.open {
            max-height: 500px;
            transition: max-height 0.3s ease-in;
        }

        .rotate-180 {
            transform: rotate(180deg);
        }

        .price-updated {
            background-color: #fef3c7;
            padding: 2px 4px;
            border-radius: 4px;
            border: 1px solid #f59e0b;
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    <!-- Header -->
<div class="bg-transparent">
    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-4">
                <a href="warehouse_staff_management_main.php" class="text-primary-600 hover:text-primary-700">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div class="bg-primary-500 p-3 rounded-lg">
                    <i class="fas fa-clipboard-list text-white text-2xl"></i>
                </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">P.O Management</h1>
                        <p class="text-gray-600 mt-1">
                            Order #<?php echo $order['id']; ?> - <?php echo htmlspecialchars($customer_name); ?>
                            <span class="text-sm text-gray-500">(<?php echo htmlspecialchars($customer_email); ?>)</span>
                        </p>
                    </div>
                </div>
                <div class="bg-primary-50 px-4 py-2 rounded-lg">
                    <span class="text-primary-700 font-medium"><?php echo count($allItems); ?> Items</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
<div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="alertContainer" class="mb-6"></div>

        <!-- Auto-Assignment Notification -->
        <?php if ($autoAssignedCount > 0): ?>
        <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-lg shadow-sm">
            <div class="flex items-center mb-2">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-semibold text-green-900">
                        Auto-Assignment Complete!
                    </h3>
                    <p class="text-green-700 text-sm mt-1">
                        Successfully assigned <?php echo $autoAssignedCount; ?> item<?php echo $autoAssignedCount > 1 ? 's' : ''; ?> to their primary suppliers.
                    </p>
                </div>
            </div>
            
            <!-- Details of auto-assigned items -->
            <div class="ml-9 mt-3 space-y-2">
                <?php foreach ($autoAssignedItems as $assignedItem): ?>
                    <div class="text-sm text-green-800 bg-green-100 px-3 py-2 rounded">
                        <i class="fas fa-arrow-right mr-2"></i>
                        <strong><?php echo htmlspecialchars($assignedItem['product_name']); ?></strong>
                        → <?php echo htmlspecialchars($assignedItem['supplier_name']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bulk Assignment Section -->
        <?php if ($unassignedCount > 0 && $primaryAvailableCount > 0): ?>
        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="bg-blue-100 p-2 rounded-lg">
                        <i class="fas fa-magic text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-900">Bulk Assignment Available</h3>
                        <p class="text-blue-700 text-sm">
                            <?php echo $primaryAvailableCount; ?> unassigned items can be automatically assigned to their primary suppliers
                        </p>
                    </div>
                </div>
                <button onclick="assignAllToPrimarySuppliers()" 
                        id="bulkAssignBtn"
                        class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-3 rounded-lg shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center space-x-2">
                    <i class="fas fa-wand-magic-sparkles"></i>
                    <span class="font-medium">Assign All to Primary Suppliers</span>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Assignment Status Summary -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="bg-green-100 p-2 rounded-lg mr-3">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Assigned Items</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo count($allItems) - $unassignedCount; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-2 rounded-lg mr-3">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Unassigned Items</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $unassignedCount; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-2 rounded-lg mr-3">
                        <i class="fas fa-star text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Primary Suppliers Available</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $primaryAvailableCount; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Single Order Display -->
        <div class="mb-8">
            <!-- Order Header -->
            <div class="bg-white rounded-t-xl shadow-sm border border-gray-200 p-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-2 rounded-lg">
                            <i class="fas fa-receipt text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Order #<?php echo $order['id']; ?></h2>
                            <p class="text-sm text-gray-600">
                                <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?> •
                                Status: <span class="font-medium text-<?php echo $order['status'] === 'pending' ? 'yellow' : 'green'; ?>-600">
                                    <?php echo ucfirst($order['status'] ?? 'pending'); ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <?php if ($newOrderTotal != $order['total']): ?>
                                <div class="text-sm text-gray-500">
                                    Original: <span class="line-through">₱<?php echo number_format($order['total'], 2); ?></span>
                                </div>
                                <div class="text-lg font-bold text-amber-600 price-updated">
                                    Updated: ₱<?php echo number_format($newOrderTotal, 2); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-lg font-bold text-primary-700">₱<?php echo number_format($order['total'], 2); ?></div>
                            <?php endif; ?>
                        </div>
                        <!-- GENERATE P.O. BUTTON -->
                        <a href="warehouse_staff_generate_po_A-B.php?order_id=<?php echo $order['id']; ?>"
                            class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-2 rounded-lg shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center space-x-2">
                            <i class="fas fa-file-invoice text-white"></i>
                            <span class="font-medium">Generate P.O.</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Price Update Notice -->
            <?php if ($newOrderTotal != $order['total']): ?>
    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-amber-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-amber-700">
                    <strong>Price Update Notice:</strong> Some items are now using supplier prices from assigned suppliers.
                    The total has been recalculated from ₱<?php echo number_format($order['total'], 2); ?> to ₱<?php echo number_format($newOrderTotal, 2); ?>.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

            <!-- Order Items -->
            <?php if (!empty($allItems)): ?>
                <div class="space-y-4 bg-gray-50 rounded-b-xl border-x border-b border-gray-200 p-4">
                    <?php foreach ($allItems as $itemIndex => $item): ?>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" id="item-<?php echo $item['item_id']; ?>">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">
    <?php echo htmlspecialchars($item['product_name']); ?>
    <?php if ($item['namevariant']): ?>
        <span class="text-sm text-gray-600 font-normal">- <?php echo htmlspecialchars($item['namevariant']); ?></span>
    <?php endif; ?>
</h3>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
    <div><strong>Variant ID:</strong> <?php echo htmlspecialchars($item['variant_id']); ?></div>
    <div><strong>Code:</strong> <?php echo htmlspecialchars($item['codename']); ?></div>
    <div><strong>Size:</strong> <?php echo htmlspecialchars($item['variant_size_db'] ?? $item['size']); ?></div>
    <div><strong>Color:</strong> <?php echo htmlspecialchars($item['variant_color_db'] ?? $item['variant_color']); ?></div>
    <div><strong>Qty:</strong> <?php echo htmlspecialchars($item['quantity']); ?></div>
</div>

                                    <!-- Price Information with Update Indicator -->
<div class="mt-2 text-sm text-gray-600">
    <div class="flex flex-wrap items-center gap-4">
        <div>
            <strong>Price:</strong>
            <?php if ($item['current_price'] != $item['order_price']): ?>
                <span class="line-through text-gray-400">₱<?php echo number_format($item['order_price'], 2); ?></span>
                <span class="price-updated ml-1">₱<?php echo number_format($item['current_price'], 2); ?></span>
                <i class="fas fa-arrow-up text-amber-500 ml-1" title="Price updated from supplier"></i>
            <?php else: ?>
                ₱<?php echo number_format($item['current_price'], 2); ?>
            <?php endif; ?>
        </div>

        <div>
            <strong>Subtotal:</strong>
            <?php if ($item['calculated_subtotal'] != $item['original_subtotal']): ?>
                <span class="line-through text-gray-400">₱<?php echo number_format($item['original_subtotal'], 2); ?></span>
                <span class="price-updated ml-1">₱<?php echo number_format($item['calculated_subtotal'], 2); ?></span>
                <i class="fas fa-calculator text-amber-500 ml-1" title="Subtotal recalculated"></i>
            <?php else: ?>
                ₱<?php echo number_format($item['calculated_subtotal'], 2); ?>
            <?php endif; ?>
        </div>

        <?php if ($item['origin']): ?>
            <div>
                <strong>Origin:</strong> <?php echo htmlspecialchars($item['origin']); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($item['supplier_price'] && $item['current_price'] != $item['order_price']): ?>
        <div class="mt-1 text-xs text-amber-600">
            <i class="fas fa-info-circle mr-1"></i>
            Using supplier price: ₱<?php echo number_format($item['supplier_price'], 2); ?>
        </div>
    <?php endif; ?>
</div>
                                </div>
                            </div>

                            <!-- Current Supplier Display -->
                            <?php if ($item['supplier_id'] && $item['supplier_id'] != 0): ?>
                                <?php
                                $currentSupplierStmt = $conn->prepare("SELECT business_name, primary_contact_name FROM supplier_list WHERE id = ?");
                                $currentSupplierStmt->bind_param("i", $item['supplier_id']);
                                $currentSupplierStmt->execute();
                                $currentSupplier = $currentSupplierStmt->get_result()->fetch_assoc();
                                $currentSupplierStmt->close();
                                ?>
                                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                            <span class="font-medium text-green-800">
                                                Currently assigned to: <?php echo htmlspecialchars($currentSupplier['business_name'] ?? 'Unknown Supplier'); ?>
                                                <span class="text-sm text-green-600 ml-1">(Linked Supplier)</span>
                                            </span>
                                        </div>
                                        <button onclick="unassignSupplier(<?php echo $item['item_id']; ?>)"
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition-colors duration-200">
                                            <i class="fas fa-times mr-1"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Supplier Assignment Section -->
                            <div class="border-t pt-4">
                                <?php
                                $hasLinkedSuppliers = !empty($item['linked_suppliers']);
                                ?>

                                <!-- Linked Suppliers Collapsible Section -->
                                <div class="mb-4">
                                    <!-- Header/Toggle Button -->
                                    <button onclick="toggleSupplierDropdown(<?php echo $item['item_id']; ?>)"
                                        class="w-full flex items-center justify-between p-3 bg-primary-50 hover:bg-primary-100 border border-primary-200 rounded-lg transition-colors duration-200">
                                        <div class="flex items-center">
                                            <i class="fas fa-link text-primary-600 mr-2"></i>
                                            <span class="font-medium text-primary-800">
                                                Linked Suppliers
                                                <?php if ($hasLinkedSuppliers): ?>
                                                    <span class="text-sm text-primary-600 ml-1">(<?php echo count($item['linked_suppliers']); ?> available)</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <i id="dropdownIcon-<?php echo $item['item_id']; ?>" class="fas fa-chevron-down text-primary-600 transition-transform duration-200"></i>
                                    </button>

                                    <!-- Collapsible Content -->
                                    <div id="supplierDropdown-<?php echo $item['item_id']; ?>" class="dropdown-content">
                                        <?php if ($hasLinkedSuppliers): ?>
                                            <div class="border-l border-r border-b border-primary-200 rounded-b-lg p-4 bg-white">
                                                <!-- Suppliers Grid -->
                                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                                                    <?php foreach ($item['linked_suppliers'] as $supplier): ?>
                                                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200 <?php echo $item['supplier_id'] == $supplier['supplier_id'] ? 'ring-2 ring-green-500 bg-green-50' : ''; ?>">
                                                            <!-- Supplier Type Badge -->
                                                            <div class="flex items-center justify-between mb-2">
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $supplier['supplier_type'] === 'primary' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                                    <?php echo ucfirst($supplier['supplier_type']); ?>
                                                                </span>
                                                                <?php if ($item['supplier_id'] == $supplier['supplier_id']): ?>
                                                                    <i class="fas fa-check-circle text-green-600"></i>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Business Name -->
                                                            <h4 class="font-semibold text-gray-900 mb-1 text-sm"><?php echo htmlspecialchars($supplier['business_name']); ?></h4>

                                                            <!-- Contact Info -->
<div class="text-xs text-gray-600 space-y-1">
    <div class="flex items-center">
        <i class="fas fa-user mr-1"></i>
        <span><?php echo htmlspecialchars($supplier['primary_contact_name']); ?></span>
    </div>
    <div class="flex items-center">
        <i class="fas fa-envelope mr-1"></i>
        <span><?php echo htmlspecialchars($supplier['email_address']); ?></span>
    </div>
    <div class="flex items-center">
        <i class="fas fa-phone mr-1"></i>
        <span><?php echo htmlspecialchars($supplier['phone_number']); ?></span>
    </div>
    <?php if ($supplier['supplier_price']): ?>
        <div class="flex items-center mt-2 pt-2 border-t border-gray-200">
            <i class="fas fa-tag mr-1 text-green-600"></i>
            <span class="font-semibold text-green-700">₱<?php echo number_format($supplier['supplier_price'], 2); ?></span>
        </div>
    <?php else: ?>
        <div class="flex items-center mt-2 pt-2 border-t border-gray-200">
            <i class="fas fa-exclamation-circle mr-1 text-yellow-600"></i>
            <span class="text-yellow-700 italic">Price not set</span>
        </div>
    <?php endif; ?>
</div>

                                                            <!-- Action Buttons -->
                                                            <div class="flex space-x-2 mt-3">
                                                                <button onclick="assignLinkedSupplierById(<?php echo $item['item_id']; ?>, <?php echo $supplier['supplier_id']; ?>)"
                                                                    class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-2 py-1 rounded text-xs transition-colors duration-200 <?php echo $item['supplier_id'] == $supplier['supplier_id'] ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                                                    <?php echo $item['supplier_id'] == $supplier['supplier_id'] ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-check mr-1"></i>Assign
                                                                </button>
                                                                <button onclick="contactSupplierById('<?php echo htmlspecialchars($supplier['email_address']); ?>')"
                                                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs transition-colors duration-200">
                                                                    <i class="fas fa-envelope mr-1"></i>Contact
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="border-l border-r border-b border-primary-200 rounded-b-lg p-4 bg-white">
                                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                                        <span class="text-yellow-700 text-sm">No suppliers are linked to this product</span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-b-xl border-x border-b border-gray-200 p-8 text-center">
                    <div class="text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-4"></i>
                        <p class="text-lg font-medium">No items found for this order</p>
                        <p class="text-sm">This order appears to be empty or the items couldn't be loaded.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const colors = {
                success: 'bg-green-50 border-green-200 text-green-800',
                error: 'bg-red-50 border-red-200 text-red-800',
                info: 'bg-blue-50 border-blue-200 text-blue-800'
            };

            alertContainer.innerHTML = `
                <div class="border-l-4 ${colors[type]} p-4 rounded-lg shadow-sm">
                    <p class="font-medium">${message}</p>
                </div>`;

            setTimeout(() => alertContainer.innerHTML = '', 5000);
        }

        function toggleSupplierDropdown(itemId) {
            const dropdown = document.getElementById(`supplierDropdown-${itemId}`);
            const icon = document.getElementById(`dropdownIcon-${itemId}`);

            dropdown.classList.toggle('open');
            icon.classList.toggle('rotate-180');
        }

        function assignLinkedSupplierById(itemId, supplierId) {
            if (!confirm('Are you sure you want to assign this linked supplier to this item?')) {
                return;
            }

            fetch('warehouse_staff_assign_supplier_A1&B1.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        supplier_id: parseInt(supplierId),
                        type: 'linked'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Linked supplier assigned successfully!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.error || 'Failed to assign supplier', 'error');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred: ' + error.message, 'error');
                });
        }

        function contactSupplierById(email) {
            const orderId = <?php echo $order['id']; ?>;
            window.location.href = `mailto:${email}?subject=Purchase Order Inquiry - Order #${orderId}&body=Hello,%0D%0A%0D%0AI would like to inquire about products for Order #${orderId}.%0D%0A%0D%0AThank you.`;
        }

        

        function unassignSupplier(itemId) {
            if (!confirm('Are you sure you want to remove the assigned supplier from this item?')) {
                return;
            }

            fetch('warehouse_staff_assign_supplier_A1&B1.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        supplier_id: null,
                        type: 'unassign'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Supplier removed successfully!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.error || 'Failed to remove supplier', 'error');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred: ' + error.message, 'error');
                });
        }

        // New function for bulk assignment to primary suppliers
        function assignAllToPrimarySuppliers() {
            if (!confirm('Are you sure you want to assign all unassigned items to their primary suppliers? This action cannot be undone.')) {
                return;
            }

            const btn = document.getElementById('bulkAssignBtn');
            const originalContent = btn.innerHTML;
            
            // Show loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            btn.disabled = true;

            fetch('warehouse_staff_bulk_assign_suppliers_A1.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({  
                        order_id: <?php echo $order['id']; ?>,
                        type: 'primary_only'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(`Successfully assigned ${data.assigned_count} items to their primary suppliers!`, 'success');
                        
                        // Add visual feedback for assigned items
                        if (data.assigned_items) {
                            data.assigned_items.forEach(itemId => {
                                const itemElement = document.getElementById(`item-${itemId}`);
                                if (itemElement) {
                                    itemElement.classList.add('pulse-animation');
                                }
                            });
                        }
                        
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showAlert(data.error || 'Failed to assign suppliers', 'error');
                        btn.innerHTML = originalContent;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    showAlert('An error occurred: ' + error.message, 'error');
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                });
        }
    </script>
</body>

</html>