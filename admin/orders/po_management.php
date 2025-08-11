<?php
//po_management.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if (!isset($_GET['customer_email'])) {
    header("Location: orders.php");
    exit();
}

$customer_email = $_GET['customer_email'];

// Get all orders for this customer
$ordersStmt = $conn->prepare("
    SELECT id, customer_name, email, created_at, status, total 
    FROM orders 
    WHERE email = ? 
    ORDER BY created_at DESC
");
$ordersStmt->bind_param("s", $customer_email);
$ordersStmt->execute();
$orders = $ordersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ordersStmt->close();

if (empty($orders)) {
    header("Location: orders.php");
    exit();
}

$customer_name = $orders[0]['customer_name'];

// Get all order items for all orders from this customer
$orderIds = array_column($orders, 'id');
$orderIdsStr = implode(',', array_map('intval', $orderIds));

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
        p.product_name as product_full_name,
        p.codename as product_code,
        o.created_at as order_date,
        o.status as order_status
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE oi.order_id IN ($orderIdsStr)
    ORDER BY o.created_at DESC, oi.id
");
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// Group items by order
$itemsByOrder = [];
foreach ($allItems as $item) {
    $itemsByOrder[$item['order_id']][] = $item;
}

// For each item, get available suppliers from supp_link_products
foreach ($allItems as &$item) {
    if ($item['product_id']) {
        $suppStmt = $conn->prepare("
            SELECT 
                slp.supplier_id,
                slp.supplier_type,
                sl.business_name,
                sl.primary_contact_name,
                sl.email_address,
                sl.phone_number
            FROM supp_link_products slp
            JOIN supplier_list sl ON slp.supplier_id = sl.id
            WHERE slp.product_id = ? AND slp.status = 'active' AND sl.status = 'active'
            ORDER BY slp.supplier_type ASC, sl.business_name ASC
        ");
        $suppStmt->bind_param("i", $item['product_id']);
        $suppStmt->execute();
        $item['available_suppliers'] = $suppStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $suppStmt->close();
    } else {
        $item['available_suppliers'] = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>P.O Management - <?php echo htmlspecialchars($customer_name); ?></title>
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
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <a href="orders.php" class="text-primary-600 hover:text-primary-700">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-clipboard-list text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">P.O Management</h1>
                        <p class="text-gray-600 mt-1">Customer: <?php echo htmlspecialchars($customer_name); ?> (<?php echo count($orders); ?> orders)</p>
                    </div>
                </div>
                <div class="bg-primary-50 px-4 py-2 rounded-lg">
                    <span class="text-primary-700 font-medium"><?php echo count($allItems); ?> Total Items</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="alertContainer" class="mb-6"></div>

        <!-- Orders and Items -->
        <?php foreach ($orders as $order): ?>
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
                    <div class="text-right">
                        <div class="text-lg font-bold text-primary-700">₱<?php echo number_format($order['total'], 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <?php if (isset($itemsByOrder[$order['id']])): ?>
            <div class="space-y-4 bg-gray-50 rounded-b-xl border-x border-b border-gray-200 p-4">
                <?php foreach ($itemsByOrder[$order['id']] as $itemIndex => $item): ?>
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

                    <!-- Current Supplier (if assigned) -->
                    <?php if ($item['supplier_id']): ?>
                    <?php
                    $currentSupplierStmt = $conn->prepare("SELECT business_name, primary_contact_name FROM supplier_list WHERE id = ?");
                    $currentSupplierStmt->bind_param("i", $item['supplier_id']);
                    $currentSupplierStmt->execute();
                    $currentSupplier = $currentSupplierStmt->get_result()->fetch_assoc();
                    $currentSupplierStmt->close();
                    ?>
                    <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                            <span class="font-medium text-green-800">
                                Currently assigned to: <?php echo htmlspecialchars($currentSupplier['business_name'] ?? 'Unknown Supplier'); ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Available Suppliers -->
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-users text-primary-600 mr-2"></i>
                            Available Suppliers
                        </h4>
                        
                        <?php if (empty($item['available_suppliers'])): ?>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                    <span class="text-yellow-700">No suppliers linked to this product</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($item['available_suppliers'] as $supplier): ?>
                                <div class="border rounded-lg p-4 hover:shadow-md transition-shadow duration-200 
                                    <?php echo $supplier['supplier_type'] === 'primary' ? 'border-green-300 bg-green-50' : 'border-gray-300 bg-white'; ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <h5 class="font-semibold text-gray-900"><?php echo htmlspecialchars($supplier['business_name']); ?></h5>
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            <?php echo $supplier['supplier_type'] === 'primary' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                            <?php echo ucfirst($supplier['supplier_type']); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <div><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($supplier['primary_contact_name']); ?></div>
                                        <div><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($supplier['email_address']); ?></div>
                                        <div><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($supplier['phone_number']); ?></div>
                                    </div>
                                    <div class="mt-3 flex space-x-2">
                                        <button onclick="assignSupplier(<?php echo $item['item_id']; ?>, <?php echo $supplier['supplier_id']; ?>)" 
                                                class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 rounded text-sm transition-colors duration-200 
                                                    <?php echo $item['supplier_id'] == $supplier['supplier_id'] ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                                <?php echo $item['supplier_id'] == $supplier['supplier_id'] ? 'disabled' : ''; ?>>
                                            <i class="fas fa-<?php echo $item['supplier_id'] == $supplier['supplier_id'] ? 'check' : 'plus'; ?> mr-1"></i>
                                            <?php echo $item['supplier_id'] == $supplier['supplier_id'] ? 'Assigned' : 'Assign'; ?>
                                        </button>
                                        <button onclick="contactSupplier('<?php echo $supplier['email_address']; ?>', <?php echo $order['id']; ?>)" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm transition-colors duration-200">
                                            <i class="fas fa-envelope mr-1"></i>Contact
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
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

        function assignSupplier(itemId, supplierId) {
            if (!confirm('Are you sure you want to assign this supplier to this item?')) {
                return;
            }

            fetch('assign_supplier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId,
                    supplier_id: supplierId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Supplier assigned successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to assign supplier', 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred: ' + error.message, 'error');
            });
        }

        function contactSupplier(email, orderId) {
            window.location.href = `mailto:${email}?subject=Purchase Order Inquiry - Order #${orderId}&body=Hello,%0D%0A%0D%0AI would like to inquire about products for Order #${orderId}.%0D%0A%0D%0AThank you.`;
        }
    </script>
</body>
</html>