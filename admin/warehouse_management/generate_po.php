<?php
//generate_po.php (Enhanced with supplier change feature)
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

// Get user info for "Prepared By" and display using session data
$prepared_by = $_SESSION['noble_name'] ?? 'Unknown User';
$user_role = $_SESSION['noble_lvl'] ?? 'Unknown Role';
$user_id = $_SESSION['noble_id'] ?? null;

// Function to generate custom P.O. number
function generateCustomPONumber($supplier_id) {
    $date = date('mdY'); // format: 10202025
    $time = date('Gis'); // format: 92233 (9:22:33)
    return 'NH' . $date . $time . $supplier_id;
}

// Get order items with assigned suppliers and original price from product_variants
$itemStmt = $conn->prepare("
    SELECT 
        oi.id as item_id,
        oi.order_id,
        oi.product_id,
        oi.product_name,
        oi.size,
        oi.variant_color,
        oi.codename,
        oi.supplier_id,
        oi.descrip6,
        oi.descrip7,
        oi.price,
        oi.quantity,
        oi.subtotal,
        oi.origin,
        oi.supplier_id,
        oi.manual_supplier_name,
        oi.po_number,
        pv.original_price,
        COALESCE(pv.original_price, oi.price) as computed_price,
        (oi.quantity * COALESCE(pv.original_price, oi.price)) as computed_subtotal,
        sl.business_name,
        sl.primary_contact_name,
        sl.email_address,
        sl.phone_number,
        sl.business_address
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.product_id = pv.id
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    WHERE oi.order_id = ? AND (oi.supplier_id IS NOT NULL OR oi.manual_supplier_name IS NOT NULL)
    ORDER BY COALESCE(sl.business_name, oi.manual_supplier_name), oi.id
");
$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// Get all available suppliers for the dropdown
$suppliersStmt = $conn->prepare("
    SELECT id, business_name, primary_contact_name, email_address, phone_number 
    FROM supplier_list 
    WHERE status = 'active' 
    ORDER BY business_name ASC
");
$suppliersStmt->execute();
$availableSuppliers = $suppliersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$suppliersStmt->close();

// Group items by supplier
$supplierGroups = [];
foreach ($allItems as $item) {
    $supplierKey = $item['supplier_id'] ? 
        strval($item['supplier_id']) : 
        'manual_' . $item['manual_supplier_name'];
    
    if (!isset($supplierGroups[$supplierKey])) {
        // Check if any item has a P.O. number for this supplier
        $existing_po = null;
        foreach ($allItems as $checkItem) {
            $checkKey = $checkItem['supplier_id'] ? 
                strval($checkItem['supplier_id']) : 
                'manual_' . $checkItem['manual_supplier_name'];
            if ($checkKey === $supplierKey && !empty($checkItem['po_number'])) {
                $existing_po = $checkItem['po_number'];
                break;
            }
        }
        
        $supplierGroups[$supplierKey] = [
            'supplier_info' => [
                'name' => $item['supplier_id'] ? $item['business_name'] : $item['manual_supplier_name'],
                'contact' => $item['primary_contact_name'] ?? '',
                'email' => $item['email_address'] ?? '',
                'phone' => $item['phone_number'] ?? '',
                'address' => $item['business_address'] ?? '',
                'is_manual' => !$item['supplier_id'],
                'supplier_id' => $item['supplier_id'] ?? 0,
                'existing_po' => $existing_po
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
    <style>
        .supplier-reassign-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .supplier-reassign-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .item-reassign-highlight {
            background: linear-gradient(45deg, #fef3c7, #fde68a);
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
<div class="bg-transparent">
    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-4">
                <a href="po_management.php?order_id=<?php echo $order['id']; ?>" class="text-primary-600 hover:text-primary-700">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div class="bg-green-500 p-3 rounded-lg">
                    <i class="fas fa-file-invoice text-white text-2xl"></i>
                </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Generate Purchase Order</h1>
                        <p class="text-gray-600 mt-1">Order #<?php echo $order['id']; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    </div>
                </div>
                
                <!-- User Info Display -->
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">
                            <i class="fas fa-user text-primary-600 mr-1"></i>
                            <?php echo htmlspecialchars($prepared_by); ?>
                        </div>
                        <div class="text-xs text-gray-500 capitalize">
                            <?php echo htmlspecialchars($user_role); ?>
                        </div>
                    </div>
                    <div class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-sm">
                            <?php echo strtoupper(substr($prepared_by, 0, 1)); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
<div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
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

        <!-- User Welcome Message -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex items-center">
                <div class="bg-blue-100 p-2 rounded-lg mr-3">
                    <i class="fas fa-user-check text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-medium text-blue-900">Welcome, <?php echo htmlspecialchars($prepared_by); ?>!</h3>
                    <p class="text-blue-700 text-sm">You are logged in as <span class="font-medium capitalize"><?php echo htmlspecialchars($user_role); ?></span></p>
                </div>
            </div>
        </div>

        <!-- Supplier Selection with Enhanced Features -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-building text-primary-600 mr-2"></i>
                    Select Supplier for P.O Generation
                </h2>
                <div class="flex space-x-2">
                    <button onclick="toggleSupplierChangeMode()" 
                            id="toggleModeBtn"
                            class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Enable Supplier Changes</span>
                    </button>
                </div>
            </div>
            
            <!-- Mode indicator -->
            <div id="modeIndicator" class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-gray-600 mr-2"></i>
                    <span class="text-sm text-gray-700">Normal Mode: Select a supplier to generate P.O.</span>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="suppliersGrid">
                <?php foreach ($supplierGroups as $supplierKey => $supplierData): ?>
<div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200 cursor-pointer supplier-card <?php echo !empty($supplierData['supplier_info']['existing_po']) ? 'border-green-400 bg-green-50' : ''; ?>" 
     data-supplier="<?php echo htmlspecialchars($supplierKey); ?>"
     data-has-po="<?php echo !empty($supplierData['supplier_info']['existing_po']) ? 'true' : 'false'; ?>"
     data-po-number="<?php echo htmlspecialchars($supplierData['supplier_info']['existing_po'] ?? ''); ?>"
     onclick="selectSupplier('<?php echo htmlspecialchars($supplierKey); ?>')">
    
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center space-x-2">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $supplierData['supplier_info']['is_manual'] ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                <?php echo $supplierData['supplier_info']['is_manual'] ? 'Manual' : 'Linked'; ?>
            </span>
            <?php if (!empty($supplierData['supplier_info']['existing_po'])): ?>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500 text-white">
                    <i class="fas fa-check-circle mr-1"></i>P.O. Generated
                </span>
            <?php endif; ?>
        </div>
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
    
    <?php if (!empty($supplierData['supplier_info']['existing_po'])): ?>
        <div class="mt-3 p-2 bg-white border border-green-300 rounded text-xs">
            <div class="font-medium text-gray-700 mb-1">Existing P.O.:</div>
            <div class="text-green-600 font-mono"><?php echo htmlspecialchars($supplierData['supplier_info']['existing_po']); ?></div>
            <button onclick="event.stopPropagation(); resetPONumber('<?php echo htmlspecialchars($supplierKey); ?>')"
                    class="mt-2 w-full bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs transition-colors duration-200">
                <i class="fas fa-times-circle mr-1"></i>Reset P.O.
            </button>
        </div>
    <?php endif; ?>
    
    <!-- Change supplier button (hidden by default) -->
    <button onclick="event.stopPropagation(); openSupplierChangeModal('<?php echo htmlspecialchars($supplierKey); ?>')"
            class="supplier-change-btn mt-2 w-full bg-amber-500 hover:bg-amber-600 text-white px-3 py-1 rounded text-sm transition-colors duration-200"
            style="display: none;">
        <i class="fas fa-exchange-alt mr-1"></i>Change Items Supplier
    </button>
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
            
            <!-- Prepared By Info -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-user-edit text-gray-600 mr-2"></i>
                    <span class="text-sm font-medium text-gray-700">Prepared By: </span>
                    <span class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($prepared_by); ?></span>
                    <span class="mx-2 text-gray-400">|</span>
                    <span class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($user_role); ?></span>
                </div>
            </div>
            
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

    <!-- Supplier Change Modal -->
    <div id="supplierChangeModal" class="supplier-reassign-modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 rounded-t-xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-exchange-alt text-amber-500 mr-2"></i>
                        Change Supplier Assignment
                    </h3>
                    <button onclick="closeSupplierChangeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedSupplier = null;
        let supplierChangeMode = false;
        const supplierData = <?php echo json_encode($supplierGroups); ?>;
        const availableSuppliers = <?php echo json_encode($availableSuppliers); ?>;

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

        function toggleSupplierChangeMode() {
            supplierChangeMode = !supplierChangeMode;
            const toggleBtn = document.getElementById('toggleModeBtn');
            const modeIndicator = document.getElementById('modeIndicator');
            const changeButtons = document.querySelectorAll('.supplier-change-btn');
            
            if (supplierChangeMode) {
                toggleBtn.innerHTML = '<i class="fas fa-eye"></i><span>Normal Mode</span>';
                toggleBtn.className = 'bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2';
                
                modeIndicator.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-exchange-alt text-amber-600 mr-2"></i>
                        <span class="text-sm text-amber-700 font-medium">Change Mode: Click "Change Items Supplier" on any supplier to reassign items</span>
                    </div>`;
                modeIndicator.className = 'mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg';
                
                changeButtons.forEach(btn => btn.style.display = 'block');
            } else {
                toggleBtn.innerHTML = '<i class="fas fa-exchange-alt"></i><span>Enable Supplier Changes</span>';
                toggleBtn.className = 'bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2';
                
                modeIndicator.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-gray-600 mr-2"></i>
                        <span class="text-sm text-gray-700">Normal Mode: Select a supplier to generate P.O.</span>
                    </div>`;
                modeIndicator.className = 'mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg';
                
                changeButtons.forEach(btn => btn.style.display = 'none');
            }
        }

        function selectSupplier(supplierKey) {
            if (supplierChangeMode) return; // Disable normal selection in change mode
            
            console.log('Selecting supplier:', supplierKey);
            
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

        function resetPONumber(supplierKey) {
    if (!confirm('Are you sure you want to reset the P.O. number for this supplier? This will allow you to generate a new P.O.')) {
        return;
    }
    
    const supplier = supplierData[supplierKey];
    if (!supplier) return;
    
    // Get all item IDs for this supplier
    const itemIds = supplier.items.map(item => item.item_id);
    
    // Send request to reset P.O. number
    fetch('reset_po_number.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            item_ids: itemIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('P.O. number reset successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('Failed to reset P.O. number: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to reset P.O. number', 'error');
    });
}

        function openSupplierChangeModal(supplierKey) {
            const supplier = supplierData[supplierKey];
            if (!supplier) return;
            
            const modal = document.getElementById('supplierChangeModal');
            const modalContent = document.getElementById('modalContent');
            
            // Get current supplier ID to exclude from dropdown
            let currentSupplierId = null;
            if (!supplier.supplier_info.is_manual && supplier.items.length > 0) {
                currentSupplierId = supplier.items[0].supplier_id;
            }
            
            let html = `
                <div class="mb-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">
                        Current Supplier: ${supplier.supplier_info.name}
                    </h4>
                    <p class="text-sm text-gray-600">Select items to reassign to a different supplier:</p>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Items List -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h5 class="font-medium text-gray-900 mb-4">Items to Reassign:</h5>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
            `;
            
            supplier.items.forEach((item, index) => {
                html += `
                    <div class="bg-white p-3 rounded border">
                        <label class="flex items-start space-x-3 cursor-pointer">
                            <input type="checkbox" value="${item.item_id}" name="itemsToReassign" class="mt-1 text-amber-600">
                            <div class="flex-1">
                                <div class="font-medium text-sm text-gray-900">${item.product_name}</div>
                                <div class="text-xs text-gray-600">
                                    ${item.codename} | ${item.size} | ${item.variant_color} | Qty: ${item.quantity}
                                </div>
                                <div class="text-xs text-gray-600">₱${parseFloat(item.computed_price || item.price).toLocaleString()}</div>
                            </div>
                        </label>
                    </div>
                `;
            });
            
            html += `
                        </div>
                        <div class="mt-4 pt-3 border-t">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" id="selectAllItems" onchange="toggleSelectAll()" class="text-amber-600">
                                <span class="text-sm font-medium text-gray-700">Select All Items</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- New Supplier Selection -->
                    <div>
                        <h5 class="font-medium text-gray-900 mb-4">Assign to New Supplier:</h5>
                        
                        <!-- Linked Supplier Option -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Choose Linked Supplier:</label>
                            <select id="newLinkedSupplier" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="">Select a supplier...</option>
            `;
            
            // Filter out the current supplier from available options
            availableSuppliers.forEach(supplierOption => {
                if (currentSupplierId === null || supplierOption.id != currentSupplierId) {
                    html += `<option value="${supplierOption.id}">${supplierOption.business_name}</option>`;
                }
            });
            
            html += `
                            </select>
                        </div>
                        
                        <div class="text-center text-gray-400 text-sm mb-4">OR</div>
                        
                        <!-- Manual Supplier Option -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Enter Manual Supplier:</label>
                            <input type="text" id="newManualSupplier" placeholder="Enter supplier name manually..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex space-x-3">
                            <button onclick="processSupplierReassignment()" 
                                    class="flex-1 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                                <i class="fas fa-exchange-alt mr-2"></i>Reassign Items
                            </button>
                            <button onclick="closeSupplierChangeModal()" 
                                    class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg transition-colors duration-200">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            modalContent.innerHTML = html;
            modal.classList.add('active');
        }

        function closeSupplierChangeModal() {
            document.getElementById('supplierChangeModal').classList.remove('active');
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllItems');
            const             itemCheckboxes = document.querySelectorAll('input[name="itemsToReassign"]');
            
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }

        function processSupplierReassignment() {
            const selectedItems = Array.from(document.querySelectorAll('input[name="itemsToReassign"]:checked'))
                .map(checkbox => parseInt(checkbox.value));
            
            if (selectedItems.length === 0) {
                showAlert('Please select at least one item to reassign', 'error');
                return;
            }
            
            const linkedSupplierId = document.getElementById('newLinkedSupplier').value;
            const manualSupplierName = document.getElementById('newManualSupplier').value.trim();
            
            if (!linkedSupplierId && !manualSupplierName) {
                showAlert('Please select a linked supplier or enter a manual supplier name', 'error');
                return;
            }
            
            if (linkedSupplierId && manualSupplierName) {
                showAlert('Please choose either a linked supplier OR a manual supplier, not both', 'error');
                return;
            }
            
            if (!confirm(`Are you sure you want to reassign ${selectedItems.length} item(s) to ${linkedSupplierId ? 'the selected supplier' : manualSupplierName}?`)) {
                return;
            }
            
            // Process each item
            let completedRequests = 0;
            let successCount = 0;
            let errorCount = 0;
            
            selectedItems.forEach(itemId => {
                const requestData = {
                    item_id: itemId,
                    type: linkedSupplierId ? 'linked' : 'manual'
                };
                
                if (linkedSupplierId) {
                    requestData.supplier_id = parseInt(linkedSupplierId);
                } else {
                    requestData.manual_supplier_name = manualSupplierName;
                }
                
                fetch('assign_supplier.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                })
                .then(response => response.json())
                .then(data => {
                    completedRequests++;
                    if (data.success) {
                        successCount++;
                    } else {
                        errorCount++;
                        console.error('Failed to reassign item:', itemId, data.error);
                    }
                    
                    // Check if all requests completed
                    if (completedRequests === selectedItems.length) {
                        if (successCount > 0) {
                            showAlert(`Successfully reassigned ${successCount} item(s)${errorCount > 0 ? ` (${errorCount} failed)` : ''}`, successCount > errorCount ? 'success' : 'error');
                            closeSupplierChangeModal();
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('Failed to reassign any items', 'error');
                        }
                    }
                })
                .catch(error => {
                    completedRequests++;
                    errorCount++;
                    console.error('Request failed for item:', itemId, error);
                    
                    if (completedRequests === selectedItems.length) {
                        showAlert(`Failed to reassign items: ${error.message}`, 'error');
                    }
                });
            });
        }

        function updateItemsPreview(supplierKey) {
            const supplier = supplierData[supplierKey];
            console.log('Updating preview for supplier:', supplierKey, supplier);
            
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
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            supplier.items.forEach(item => {
                // Use computed_price (which uses original_price if available, otherwise falls back to item.price)
                const unitPrice = item.computed_price ? parseFloat(item.computed_price) : parseFloat(item.price);
                const totalPrice = item.computed_subtotal ? parseFloat(item.computed_subtotal) : parseFloat(item.subtotal);
                
                html += `
                    <tr>
                        <td class="px-4 py-4">
                            <div class="font-medium text-gray-900">${item.product_name}</div>
                            <div class="text-sm text-gray-500">${item.codename}</div>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-900">
                            ${item.size} | ${item.variant_color}
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-900">${item.descrip6 || 'pcs'}</td>
                        <td class="px-4 py-4 text-sm text-gray-900">${item.quantity}</td>
                        <td class="px-4 py-4 text-sm text-gray-900">₱${unitPrice.toLocaleString()}</td>
                        <td class="px-4 py-4 text-sm text-gray-900">₱${totalPrice.toLocaleString()}</td>
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
    
    // Check if supplier already has P.O.
    const supplierCard = document.querySelector(`[data-supplier="${selectedSupplier}"]`);
    const hasPO = supplierCard.dataset.hasPo === 'true';
    const existingPO = supplierCard.dataset.poNumber;
    
    if (hasPO && existingPO) {
        if (!confirm(`This supplier already has a P.O. number: ${existingPO}\n\nGenerating a new P.O. will overwrite the existing one. Continue?`)) {
            return;
        }
    }

    const paymentTerms = document.getElementById('paymentTerms').value;
    const deliveryDetails = document.getElementById('deliveryDetails').value;
    const conditions = document.getElementById('conditions').value;
    const additionalNotes = document.getElementById('additionalNotes').value;

    // Debug: Log the selected supplier
    console.log('Selected supplier key:', selectedSupplier);
    console.log('Supplier data:', supplierData[selectedSupplier]);

    // Create form and submit to Excel P.O. generator
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

    showAlert('Generating Purchase Order Excel file... Page will reload shortly.', 'success');
    
    // Reload the page after 2 seconds to show the updated P.O. status
    setTimeout(() => {
        location.reload();
    }, 2000);
}

        // Close modal when clicking outside
        document.getElementById('supplierChangeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSupplierChangeModal();
            }
        });

        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSupplierChangeModal();
            }
        });
    </script>
</body>
</html>