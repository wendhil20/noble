<?php
//set_supplier.php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get order_id and item_index from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$item_index = isset($_GET['item_index']) ? (int)$_GET['item_index'] : null;

if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// Get current user's emp_id
$email = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

// Fetch order details (only if assigned to current employee)
$stmt = $conn->prepare("
    SELECT 
        id,
        customer_name,
        email,
        mobile,
        address,
        zipcode,
        total,
        created_at,
        COALESCE(status, 'pending') AS status
    FROM orders 
    WHERE id = ? AND emp_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $order_id, $emp_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Fetch suppliers (users with lvl = 'supplier')
$stmt = $conn->prepare("
    SELECT 
        id,
        fullname,
        email
    FROM nobleaccount 
    WHERE lvl = 'supplier'
    ORDER BY fullname ASC
");
$stmt->execute();
$suppliers_result = $stmt->get_result();
$suppliers = [];
while ($supplier = $suppliers_result->fetch_assoc()) {
    $suppliers[] = $supplier;
}
$stmt->close();

// Fetch order items with current supplier info
$stmt = $conn->prepare("
    SELECT 
        oi.id,
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
        na.fullname as supplier_name
    FROM order_items oi
    LEFT JOIN nobleaccount na ON oi.supplier_id = na.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Supplier - Order #<?php echo $order['id']; ?></title>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <a href="ordering.php" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-truck-loading text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Set Supplier</h1>
                        <p class="text-gray-600 mt-1">
                            Order #<?php echo $order['id']; ?> - <?php echo $order['customer_name']; ?>
                            <?php if ($item_index !== null): ?>
                                <span class="text-purple-600"> • Item #<?php echo ($item_index + 1); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Order Summary Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-info-circle text-primary-600 mr-2"></i>
                Order Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Customer</p>
                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Status</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Order Date</p>
                    <p class="font-semibold text-gray-900"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <?php if (empty($suppliers)): ?>
            <!-- No Suppliers Alert -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-amber-400 text-2xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-amber-800">No Suppliers Available</h3>
                        <p class="text-amber-700 mt-1">
                            There are currently no suppliers registered in the system. Please contact your administrator to add suppliers before assigning them to orders.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Items List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-boxes text-primary-600 mr-2"></i>
                    <?php if ($item_index !== null): ?>
                        Selected Item (Item #<?php echo ($item_index + 1); ?>)
                    <?php else: ?>
                        Order Items (<?php echo count($items); ?> items)
                    <?php endif; ?>
                </h2>

                <?php if (empty($items)): ?>
                    <div class="text-center py-12">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-box-open text-6xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Items Found</h3>
                        <p class="text-gray-600">This order doesn't have any items.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php 
                        // If item_index is specified, show only that item
                        $itemsToShow = ($item_index !== null && isset($items[$item_index])) 
                            ? [$items[$item_index]] 
                            : $items;
                        
                        foreach ($itemsToShow as $index => $item): 
                            $displayIndex = ($item_index !== null) ? $item_index : $index;
                        ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                                <div class="flex items-start space-x-4">
                                    <div class="bg-primary-50 p-3 rounded-lg">
                                        <i class="fas fa-box text-primary-600 text-xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                                    <?php if ($item_index !== null): ?>
                                                        <span class="text-sm text-purple-600 font-medium ml-2">(Item #<?php echo ($item_index + 1); ?>)</span>
                                                    <?php endif; ?>
                                                </h3>
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-4">
                                                    <div>
                                                        <span class="text-gray-600 font-medium">Size:</span>
                                                        <span class="text-gray-900"><?php echo htmlspecialchars($item['size']); ?></span>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-600 font-medium">Color:</span>
                                                        <span class="text-gray-900"><?php echo htmlspecialchars($item['variant_color']); ?></span>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-600 font-medium">Code:</span>
                                                        <span class="text-gray-900"><?php echo htmlspecialchars($item['codename']); ?></span>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-600 font-medium">Quantity:</span>
                                                        <span class="text-gray-900"><?php echo $item['quantity']; ?></span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Supplier Selection -->
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <label for="supplier-select-<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                                        <i class="fas fa-truck mr-1"></i>
                                                        Assign Supplier
                                                    </label>
                                                    
                                                    <?php if (empty($suppliers)): ?>
                                                        <div class="flex items-center space-x-2 text-gray-500">
                                                            <i class="fas fa-info-circle"></i>
                                                            <span class="text-sm">No suppliers available</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="flex items-center space-x-3">
                                                            <select 
                                                                id="supplier-select-<?php echo $item['id']; ?>" 
                                                                class="flex-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                                                                onchange="assignSupplier(<?php echo $item['id']; ?>, this.value)"
                                                            >
                                                                <option value="">Select a supplier...</option>
                                                                <?php foreach ($suppliers as $supplier): ?>
                                                                    <option 
                                                                        value="<?php echo $supplier['id']; ?>"
                                                                        <?php echo ($item['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>
                                                                    >
                                                                        <?php echo htmlspecialchars($supplier['fullname']); ?>
                                                                        (<?php echo htmlspecialchars($supplier['email']); ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            
                                                            <?php if ($item['supplier_id']): ?>
                                                                <div class="flex items-center text-green-600">
                                                                    <i class="fas fa-check-circle mr-1"></i>
                                                                    <span class="text-sm font-medium">Assigned</span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <?php if ($item['supplier_name']): ?>
                                                            <div class="mt-2 text-sm text-gray-600">
                                                                <strong>Current Supplier:</strong> 
                                                                <span class="text-primary-600 font-medium"><?php echo htmlspecialchars($item['supplier_name']); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($item['descrip6'] || $item['descrip7']): ?>
                                                    <div class="mt-3 text-sm text-gray-600">
                                                        <span class="font-medium">Details:</span>
                                                        <?php echo htmlspecialchars(trim($item['descrip6'] . ' ' . $item['descrip7'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($item['origin']): ?>
                                                    <div class="mt-2 text-sm">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                                            Origin: <?php echo htmlspecialchars($item['origin']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-right ml-4">
                                                <div class="text-sm text-gray-600 mb-1">
                                                    ₱<?php echo number_format($item['price'], 2); ?> each
                                                </div>
                                                <div class="text-lg font-semibold text-gray-900">
                                                    ₱<?php echo number_format($item['subtotal'], 2); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex justify-between items-center">
            <a href="ordering.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Orders</span>
            </a>
            
            <div class="text-gray-500 text-sm">
                <i class="fas fa-info-circle mr-1"></i>
                Select suppliers from the dropdown menus to assign them to specific items
            </div>
        </div>
    </div>

    <!-- Alert Container for Messages -->
    <div id="alertContainer" class="fixed top-4 right-4 z-50"></div>

    <script>
        // Show alert message
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle'
            };

            const colors = {
                success: 'bg-green-50 border-green-200 text-green-800',
                error: 'bg-red-50 border-red-200 text-red-800',
                info: 'bg-blue-50 border-blue-200 text-blue-800'
            };

            alertContainer.innerHTML = `
                <div class="border-l-4 ${colors[type]} p-4 rounded-lg shadow-lg max-w-md">
                    <div class="flex items-center">
                        <i class="${icons[type]} text-xl mr-3"></i>
                        <div>
                            <p class="font-medium">${message}</p>
                        </div>
                    </div>
                </div>`;

            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Assign supplier to item
        function assignSupplier(itemId, supplierId) {
            const selectElement = document.getElementById(`supplier-select-${itemId}`);
            
            if (selectElement) {
                selectElement.disabled = true;
            }

            const requestData = {
                item_id: parseInt(itemId),
                supplier_id: supplierId ? parseInt(supplierId) : null
            };

            fetch('update_item_supplier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showAlert('Supplier assigned successfully!', 'success');
                    // Reload page to show updated supplier info
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || data.error || 'Unknown error occurred');
                }
            })
            .catch(error => {
                console.error('Error assigning supplier:', error);
                showAlert(`Failed to assign supplier: ${error.message}`, 'error');
                
                if (selectElement) {
                    selectElement.disabled = false;
                }
            });
        }
    </script>
</body>
</html>