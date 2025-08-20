<?php
//generate_po.php
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

// Get user info for "Prepared By"
$user_id = $_SESSION['noble_user'];
$userStmt = $conn->prepare("SELECT fullname FROM nobleaccount WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$prepared_by = $user['fullname'] ?? 'Unknown User';
$userStmt->close();

// Get order items with assigned suppliers
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
        sl.business_name,
        sl.primary_contact_name,
        sl.email_address,
        sl.phone_number,
        sl.business_address
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    WHERE oi.order_id = ? AND (oi.supplier_id IS NOT NULL OR oi.manual_supplier_name IS NOT NULL)
    ORDER BY COALESCE(sl.business_name, oi.manual_supplier_name), oi.id
");
$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// Group items by supplier
$supplierGroups = [];
foreach ($allItems as $item) {
    $supplierKey = $item['supplier_id'] ? 
        $item['supplier_id'] : 
        'manual_' . $item['manual_supplier_name'];
    
    if (!isset($supplierGroups[$supplierKey])) {
        $supplierGroups[$supplierKey] = [
            'supplier_info' => [
                'name' => $item['supplier_id'] ? $item['business_name'] : $item['manual_supplier_name'],
                'contact' => $item['primary_contact_name'] ?? '',
                'email' => $item['email_address'] ?? '',
                'phone' => $item['phone_number'] ?? '',
                'address' => $item['address'] ?? '',
                'is_manual' => !$item['supplier_id']
            ],
            'items' => []
        ];
    }
    
    $supplierGroups[$supplierKey]['items'][] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Purchase Order - Order #<?php echo $order['id']; ?></title>
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
                    <a href="po_management.php?order_id=<?php echo $order['id']; ?>" class="text-primary-600 hover:text-primary-700">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div class="bg-gradient-to-r from-green-500 to-green-600 p-3 rounded-xl shadow-lg">
                        <i class="fas fa-file-invoice text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Generate Purchase Order</h1>
                        <p class="text-gray-600 mt-1">Order #<?php echo $order['id']; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="alertContainer" class="mb-6"></div>

        <?php if (empty($supplierGroups)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <div class="text-gray-500">
                <i class="fas fa-exclamation-triangle text-4xl mb-4 text-yellow-500"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Suppliers Assigned</h3>
                <p class="text-sm text-gray-600 mb-4">You need to assign suppliers to order items before generating purchase orders.</p>
                <a href="po_management.php?order_id=<?php echo $order['id']; ?>" 
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to P.O Management
                </a>
            </div>
        </div>
        <?php else: ?>

        <!-- Supplier Selection -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-building text-primary-600 mr-2"></i>
                Select Supplier for P.O Generation
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($supplierGroups as $supplierKey => $supplierData): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200 cursor-pointer supplier-card" 
                     data-supplier="<?php echo htmlspecialchars($supplierKey); ?>"
                     onclick="selectSupplier('<?php echo htmlspecialchars($supplierKey); ?>')">
                    <div class="flex items-center justify-between mb-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $supplierData['supplier_info']['is_manual'] ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo $supplierData['supplier_info']['is_manual'] ? 'Manual' : 'Linked'; ?>
                        </span>
                        <div class="supplier-radio">
                            <input type="radio" name="selected_supplier" value="<?php echo htmlspecialchars($supplierKey); ?>" class="text-primary-600">
                        </div>
                    </div>
                    
                    <h4 class="font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($supplierData['supplier_info']['name']); ?></h4>
                    
                    <?php if (!$supplierData['supplier_info']['is_manual']): ?>
                    <div class="text-xs text-gray-600 space-y-1 mb-3">
                        <?php if ($supplierData['supplier_info']['contact']): ?>
                        <div><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($supplierData['supplier_info']['contact']); ?></div>
                        <?php endif; ?>
                        <?php if ($supplierData['supplier_info']['email']): ?>
                        <div><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($supplierData['supplier_info']['email']); ?></div>
                        <?php endif; ?>
                        <?php if ($supplierData['supplier_info']['phone']): ?>
                        <div><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($supplierData['supplier_info']['phone']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-sm text-primary-600 font-medium">
                        <?php echo count($supplierData['items']); ?> item(s)
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- P.O Details Form -->
        <div id="poDetailsForm" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" style="display: none;">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-edit text-primary-600 mr-2"></i>
                Purchase Order Details
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Terms</label>
                    <input type="text" id="paymentTerms" placeholder="e.g., After 7-14 Days" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Details</label>
                    <input type="text" id="deliveryDetails" placeholder="e.g., Pickup at warehouse" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Condition and Other Special Instructions</label>
                <textarea id="conditions" rows="3" placeholder="Enter any special conditions or instructions..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"></textarea>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                <textarea id="additionalNotes" rows="3" placeholder="Enter any additional notes..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"></textarea>
            </div>
            
            <div class="flex justify-end">
                <button onclick="generatePO()" 
                        class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-8 py-3 rounded-lg shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center space-x-2">
                    <i class="fas fa-file-download"></i>
                    <span class="font-medium">Generate P.O.</span>
                </button>
            </div>
        </div>

        <!-- Selected Supplier Items Preview -->
        <div id="itemsPreview" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" style="display: none;">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-list text-primary-600 mr-2"></i>
                Items for Selected Supplier
            </h2>
            <div id="previewContent"></div>
        </div>

        <?php endif; ?>
    </div>

    <script>
        let selectedSupplier = null;
        const supplierData = <?php echo json_encode($supplierGroups); ?>;

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

        function selectSupplier(supplierKey) {
            // Update radio button
            document.querySelector(`input[value="${supplierKey}"]`).checked = true;
            
            // Remove previous selection styling
            document.querySelectorAll('.supplier-card').forEach(card => {
                card.classList.remove('ring-2', 'ring-primary-500', 'bg-primary-50');
            });
            
            // Add selection styling
            document.querySelector(`[data-supplier="${supplierKey}"]`).classList.add('ring-2', 'ring-primary-500', 'bg-primary-50');
            
            selectedSupplier = supplierKey;
            
            // Show P.O details form
            document.getElementById('poDetailsForm').style.display = 'block';
            document.getElementById('itemsPreview').style.display = 'block';
            
            // Update preview
            updateItemsPreview(supplierKey);
        }

        function updateItemsPreview(supplierKey) {
            const supplier = supplierData[supplierKey];
            const previewContent = document.getElementById('previewContent');
            
            let html = `
                <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <h3 class="font-semibold text-gray-900">${supplier.supplier_info.name}</h3>
                    <p class="text-sm text-gray-600">${supplier.items.length} item(s) will be included in this P.O.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Specification</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            supplier.items.forEach(item => {
                html += `
                    <tr>
                        <td class="px-4 py-4">
                            <div class="font-medium text-gray-900">${item.product_name}</div>
                            <div class="text-sm text-gray-500">${item.codename}</div>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-900">
                            ${item.size} | ${item.variant_color}
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-900">${item.quantity}</td>
                        <td class="px-4 py-4 text-sm text-gray-900">₱${parseFloat(item.price).toLocaleString()}</td>
                        <td class="px-4 py-4 text-sm text-gray-900">₱${parseFloat(item.subtotal).toLocaleString()}</td>
                    </tr>
                `;
            });
            
            html += `</tbody></table></div>`;
            previewContent.innerHTML = html;
        }

        function generatePO() {
            if (!selectedSupplier) {
                showAlert('Please select a supplier first', 'error');
                return;
            }

            const paymentTerms = document.getElementById('paymentTerms').value;
            const deliveryDetails = document.getElementById('deliveryDetails').value;
            const conditions = document.getElementById('conditions').value;
            const additionalNotes = document.getElementById('additionalNotes').value;

            // Create form and submit to P.O. generator
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generate_po_pdf.php';
            form.target = '_blank';

            const fields = [
                ['order_id', <?php echo $order['id']; ?>],
                ['supplier_key', selectedSupplier],
                ['payment_terms', paymentTerms],
                ['delivery_details', deliveryDetails],
                ['conditions', conditions],
                ['additional_notes', additionalNotes],
                ['prepared_by', '<?php echo htmlspecialchars($prepared_by); ?>']
            ];

            fields.forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            showAlert('Generating Purchase Order...', 'success');
        }
    </script>
</body>
</html>