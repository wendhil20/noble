<?php
//po_management.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Check if order_id is provided instead of customer_email
if (!isset($_GET['order_id'])) {
    header("Location: ordering.php");
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
    header("Location: ordering.php");
    exit();
}

$order = $orderResult->fetch_assoc();
$orderStmt->close();

$customer_name = $order['customer_name'];
$customer_email = $order['email'];

// Get all order items for this specific order
$itemStmt = $conn->prepare("
    SELECT 
        oi.id as item_id,
        oi.order_id,
        oi.product_id,
        oi.product_name,
        oi.size,
        oi.variant_color,
        oi.codename,
        oi.descrip6,
        oi.descrip7,
        oi.price,
        oi.quantity,
        oi.subtotal,
        oi.origin,
        oi.supplier_id,
        oi.manual_supplier_name,
        p.product_name as product_full_name,
        p.codename as product_code,
        o.created_at as order_date,
        o.status as order_status
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// For each item, get available suppliers from supp_link_products
for ($i = 0; $i < count($allItems); $i++) {
    // Initialize arrays to prevent undefined key errors
    $allItems[$i]['linked_suppliers'] = [];
    
    // Get linked suppliers
    if ($allItems[$i]['product_id']) {
        // Get linked suppliers with proper JOIN
        $linkedSuppStmt = $conn->prepare("
            SELECT 
                slp.supplier_id,
                slp.supplier_type,
                sl.business_name,
                sl.primary_contact_name,
                sl.email_address,
                sl.phone_number,
                slp.status as link_status,
                sl.status as supplier_status
            FROM supp_link_products slp
            INNER JOIN supplier_list sl ON slp.supplier_id = sl.id
            WHERE slp.product_id = ? 
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
        $linkedSuppStmt->bind_param("i", $allItems[$i]['product_id']);
        $linkedSuppStmt->execute();
        $linkedResult = $linkedSuppStmt->get_result();
        $allItems[$i]['linked_suppliers'] = $linkedResult->fetch_all(MYSQLI_ASSOC);
        $linkedSuppStmt->close();
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
                            50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74',
                            400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c',
                            800: '#9a3412', 900: '#7c2d12',
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
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <a href="ordering.php" class="text-primary-600 hover:text-primary-700">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-3 rounded-xl shadow-lg">
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="alertContainer" class="mb-6"></div>

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

 <!-- ADD THIS SECTION - Replace the existing right side div -->
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <div class="text-lg font-bold text-primary-700">₱<?php echo number_format($order['total'], 2); ?></div>
                        </div>
                        <!-- NEW GENERATE P.O. BUTTON -->
                        <a href="generate_po.php?order_id=<?php echo $order['id']; ?>" 
                           class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-2 rounded-lg shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center space-x-2">
                            <i class="fas fa-file-invoice text-white"></i>
                            <span class="font-medium">Generate P.O.</span>
                        </a>
                    </div>
                </div>
            </div>
                    
                    

            <!-- Order Items -->
            <?php if (!empty($allItems)): ?>
            <div class="space-y-4 bg-gray-50 rounded-b-xl border-x border-b border-gray-200 p-4">
                <?php foreach ($allItems as $itemIndex => $item): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
                                <div><strong>Code:</strong> <?php echo htmlspecialchars($item['codename']); ?></div>
                                <div><strong>Size:</strong> <?php echo htmlspecialchars($item['size']); ?></div>
                                <div><strong>Color:</strong> <?php echo htmlspecialchars($item['variant_color']); ?></div>
                                <div><strong>Qty:</strong> <?php echo htmlspecialchars($item['quantity']); ?></div>
                            </div>
                            <div class="mt-2 text-sm text-gray-600">
                                <strong>Price:</strong> ₱<?php echo number_format($item['price'], 2); ?> | 
                                <strong>Subtotal:</strong> ₱<?php echo number_format($item['subtotal'], 2); ?>
                                <?php if ($item['origin']): ?>
                                    | <strong>Origin:</strong> <?php echo htmlspecialchars($item['origin']); ?>
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
                    <?php elseif ($item['supplier_id'] == 0 && !empty($item['manual_supplier_name'])): ?>
                    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-user-edit text-blue-600 mr-2"></i>
                                <span class="font-medium text-blue-800">
                                    Currently assigned to: <?php echo htmlspecialchars($item['manual_supplier_name']); ?>
                                    <span class="text-sm text-blue-600 ml-1">(Manual Supplier)</span>
                                </span>
                            </div>
                            <button onclick="unassignManualSupplier(<?php echo $item['item_id']; ?>)" 
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
                        $hasManualSupplier = ($item['supplier_id'] == 0 && !empty($item['manual_supplier_name']));
                        $isDisabled = $hasManualSupplier;
                        ?>

                        <!-- Linked Suppliers Collapsible Section -->
                        <div class="mb-4">
                            <!-- Header/Toggle Button -->
                            <button onclick="toggleSupplierDropdown(<?php echo $item['item_id']; ?>)" 
                                    class="w-full flex items-center justify-between p-3 bg-primary-50 hover:bg-primary-100 border border-primary-200 rounded-lg transition-colors duration-200 <?php echo $isDisabled ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $isDisabled ? 'disabled' : ''; ?>>
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

                        <!-- Manual Supplier Input -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-edit text-gray-600 mr-1"></i>
                                Manual Supplier Entry
                            </label>
                            <div class="flex space-x-2">
                                <input type="text" 
                                       id="manualSupplierInput-<?php echo $item['item_id']; ?>"
                                       placeholder="Enter supplier name manually..."
                                       value="<?php echo $item['supplier_id'] == 0 ? htmlspecialchars($item['manual_supplier_name'] ?? '') : ''; ?>"
                                       class="flex-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500">
                                <button onclick="assignManualSupplier(<?php echo $item['item_id']; ?>)" 
                                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm transition-colors duration-200">
                                    <i class="fas fa-plus mr-1"></i>Assign
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Use this option when the supplier is not in the system or not linked to this product
                            </p>
                        </div>

                        <?php if ($hasManualSupplier): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded">
                            <p class="text-sm text-blue-700">
                                <i class="fas fa-info-circle mr-1"></i>
                                Manual supplier assigned. Linked suppliers are disabled. Remove the manual supplier to use linked suppliers again.
                            </p>
                        </div>
                        <?php endif; ?>
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

            fetch('assign_supplier.php', {
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

        function assignManualSupplier(itemId) {
            const input = document.getElementById(`manualSupplierInput-${itemId}`);
            const supplierName = input.value.trim();
            
            if (!supplierName) {
                showAlert('Please enter a supplier name', 'error');
                return;
            }

            if (!confirm('Are you sure you want to assign this manual supplier to this item?')) {
                return;
            }

            fetch('assign_supplier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId,
                    supplier_id: 0,
                    manual_supplier_name: supplierName,
                    type: 'manual'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Manual supplier assigned successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to assign manual supplier', 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred: ' + error.message, 'error');
            });
        }

        function unassignSupplier(itemId) {
            if (!confirm('Are you sure you want to remove the assigned supplier from this item?')) {
                return;
            }

            fetch('assign_supplier.php', {
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

        function unassignManualSupplier(itemId) {
            if (!confirm('Are you sure you want to remove the manual supplier from this item?')) {
                return;
            }

            fetch('assign_supplier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId,
                    supplier_id: null,
                    manual_supplier_name: null,
                    type: 'unassign_manual'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Manual supplier removed successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to remove manual supplier', 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred: ' + error.message, 'error');
            });
        }
    </script>
</body>
</html>