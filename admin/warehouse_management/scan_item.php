<?php
// scan_item.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get user info from session or database
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

// Set user variables
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
$itemInfo = null;
$orderInfo = null;
$supplierInfo = null;

if ($item_id > 0) {
    // Get item information
    $stmt = $conn->prepare("
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
        oi.quantity,
        oi.po_number,
        oi.qr_code,
        oi.warehouse_location,
        oi.tracking_status,
        oi.supplier_id,
        oi.manual_supplier_name,
        sl.business_name,
        sl.primary_contact_name,
        sl.email_address,
        sl.phone_number,
        o.customer_name,
        o.email as customer_email,
        o.created_at as order_date,
        o.status as order_status
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE oi.id = ?
");
    
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $itemInfo = $result->fetch_assoc();
    $stmt->close();
    
    if ($itemInfo) {
        $orderInfo = [
            'order_id' => $itemInfo['order_id'],
            'customer_name' => $itemInfo['customer_name'],
            'customer_email' => $itemInfo['customer_email'],
            'order_date' => $itemInfo['order_date'],
            'order_status' => $itemInfo['order_status']
        ];
        
        $supplierInfo = [
            'name' => $itemInfo['supplier_id'] ? $itemInfo['business_name'] : $itemInfo['manual_supplier_name'],
            'contact' => $itemInfo['primary_contact_name'] ?? 'N/A',
            'email' => $itemInfo['email_address'] ?? 'N/A',
            'phone' => $itemInfo['phone_number'] ?? 'N/A'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanned Item Info - <?php echo $itemInfo ? htmlspecialchars($itemInfo['product_name']) : 'Not Found'; ?></title>
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
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-transparent">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="bg-green-500 p-3 rounded-lg">
                        <i class="fas fa-qrcode text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Scanned Item Information</h1>
                        <p class="text-gray-600 mt-1">Item details from QR code scan</p>
                    </div>
                </div>
                
                <!-- User Info -->
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">
                            <i class="fas fa-user text-primary-600 mr-1"></i>
                            <?php echo htmlspecialchars($fullname); ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-shield-alt mr-1"></i>
                            <?php echo htmlspecialchars(ucfirst($user_level)); ?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?php echo date('M j, Y g:i A'); ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                        <span class="text-white font-bold text-lg">
                            <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if (!$itemInfo): ?>
            <!-- Item Not Found -->
            <div class="bg-white rounded-xl shadow-lg border border-red-200 p-12 text-center">
                <div class="text-red-500 mb-4">
                    <i class="fas fa-exclamation-triangle text-6xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Item Not Found</h2>
                <p class="text-gray-600 mb-6">
                    <?php if ($item_id > 0): ?>
                        No item found with ID: <span class="font-mono font-bold"><?php echo $item_id; ?></span>
                    <?php else: ?>
                        Invalid or missing item ID in QR code.
                    <?php endif; ?>
                </p>
                <a href="view_po_items.php" 
                   class="inline-flex items-center space-x-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg transition-colors duration-200">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to P.O. Items</span>
                </a>
            </div>
        <?php else: ?>
            
            <!-- Success Badge -->
            <div class="mb-6 flex justify-center">
                <div class="inline-flex items-center space-x-2 bg-green-100 text-green-800 px-4 py-2 rounded-full">
                    <i class="fas fa-check-circle"></i>
                    <span class="font-medium">QR Code Scanned Successfully</span>
                </div>
            </div>

            <!-- Item Information Card -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-6">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                    <h2 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-box mr-3"></i>
                        Item Details
                    </h2>
                </div>
                
                <!-- Content -->
                <div class="p-6">
                    <!-- Product Name -->
                    <div class="mb-6 pb-6 border-b border-gray-200">
                        <div class="text-sm text-gray-600 mb-1">Product Name</div>
                        <div class="text-2xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($itemInfo['product_name']); ?>
                        </div>
                    </div>
                    
                    <!-- Grid Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-barcode w-5 mr-1"></i>Code Name
                                </div>
                                <div class="text-lg font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($itemInfo['codename']); ?>
                                </div>
                            </div>
                            
                            <div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-ruler w-5 mr-1"></i>Size & Color
                                </div>
                                <div class="text-lg font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($itemInfo['size']); ?> | 
                                    <?php echo htmlspecialchars($itemInfo['variant_color']); ?>
                                </div>
                            </div>
                            
                            <div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-box w-5 mr-1"></i>Quantity
                                </div>
                                <div class="text-lg font-semibold text-gray-900">
                                    <?php echo $itemInfo['quantity']; ?> 
                                    <?php echo htmlspecialchars($itemInfo['descrip6'] ?: 'pcs'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="space-y-4">
                            <div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-hashtag w-5 mr-1"></i>Item ID
                                </div>
                                <div class="text-lg font-semibold text-gray-900">
                                    <?php echo $itemInfo['item_id']; ?>
                                </div>
                            </div>
                            
                            <div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-file-invoice w-5 mr-1"></i>P.O. Number
                                </div>
                                <div class="text-lg font-semibold text-gray-900">
                                    <?php echo $itemInfo['po_number'] ? htmlspecialchars($itemInfo['po_number']) : '<span class="text-gray-400">Not assigned</span>'; ?>
                                </div>
                            </div>
                            
                            <div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-shopping-cart w-5 mr-1"></i>Order ID
                                </div>
                                <div class="text-lg font-semibold text-gray-900">
                                    #<?php echo $orderInfo['order_id']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
            <!-- Tracking Status Section -->
                    <?php 
                    $currentStatus = $itemInfo['tracking_status'] ?? 'Pending Receipt';
                    $isInWarehouse = ($currentStatus === 'In Warehouse');
                    ?>
                    
                    <div class="bg-gradient-to-r from-<?php echo $isInWarehouse ? 'green' : 'indigo'; ?>-50 to-<?php echo $isInWarehouse ? 'emerald' : 'purple'; ?>-50 border-2 border-<?php echo $isInWarehouse ? 'green' : 'indigo'; ?>-300 rounded-lg p-6 mb-6">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start flex-1">
                                <div class="bg-<?php echo $isInWarehouse ? 'green' : 'indigo'; ?>-500 p-3 rounded-lg mr-4">
                                    <i class="fas fa-<?php echo $isInWarehouse ? 'warehouse' : 'shipping-fast'; ?> text-white text-2xl"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm text-<?php echo $isInWarehouse ? 'green' : 'indigo'; ?>-700 font-medium mb-2">
                                        TRACKING STATUS
                                    </div>
                                    <div class="text-2xl font-bold text-<?php echo $isInWarehouse ? 'green' : 'indigo'; ?>-900">
                                        <?php echo htmlspecialchars($currentStatus); ?>
                                    </div>
                                    <div class="text-sm text-<?php echo $isInWarehouse ? 'green' : 'indigo'; ?>-600 mt-2">
                                        <?php if ($isInWarehouse): ?>
                                            <i class="fas fa-check-circle mr-1"></i>Package has been received and stored in warehouse
                                        <?php elseif ($currentStatus === 'Pending Receipt'): ?>
                                            <i class="fas fa-clock mr-1"></i>Awaiting receipt confirmation
                                        <?php elseif ($currentStatus === 'In Transit'): ?>
                                            <i class="fas fa-truck mr-1"></i>Package is on the way
                                        <?php else: ?>
                                            <i class="fas fa-info-circle mr-1"></i><?php echo htmlspecialchars($currentStatus); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($isInWarehouse): ?>
                                    <!-- Show received timestamp if available -->
                                    <div class="text-xs text-green-500 mt-2 flex items-center">
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        <span class="font-medium">Received and confirmed</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($isInWarehouse): ?>
                                <!-- Already in warehouse - show confirmation badge -->
                                <div class="bg-green-500 text-white px-4 py-2 rounded-lg flex items-center space-x-2 shadow-lg ml-4">
                                    <i class="fas fa-check-circle text-xl"></i>
                                    <span class="font-medium">Confirmed</span>
                                </div>
                            <?php elseif (strtolower($user_level) === 'warehouse' || strtolower($user_level) === 'superadmin'): ?>
                                <!-- Not in warehouse AND user has warehouse access - show button -->
                                <button onclick="updateTrackingStatus('In Warehouse')" 
                                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-lg ml-4 hover:shadow-xl transform hover:scale-105">
                                    <i class="fas fa-warehouse"></i>
                                    <span>Mark as In Warehouse</span>
                                </button>
                            <?php endif; ?>
                            <!-- If not warehouse user and not in warehouse, nothing shows -->
                        </div>
                    </div>

                    <!-- Warehouse Location - Highlighted -->
<?php if (!empty($itemInfo['warehouse_location'])): ?>
<div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300 rounded-lg p-6 mb-6">
    <div class="flex items-start justify-between">
        <div class="flex items-start flex-1">
            <div class="bg-blue-500 p-3 rounded-lg mr-4">
                <i class="fas fa-map-marker-alt text-white text-2xl"></i>
            </div>
            <div class="flex-1">
                <div class="text-sm text-blue-700 font-medium mb-2">
                    WAREHOUSE LOCATION
                </div>
                <div class="text-2xl font-bold text-blue-900">
                    <?php echo htmlspecialchars($itemInfo['warehouse_location']); ?>
                </div>
            </div>
        </div>
        <button onclick="openEditLocationModal()" 
                class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-lg ml-4">
            <i class="fas fa-edit"></i>
            <span>Edit</span>
        </button>
    </div>
</div>
<?php else: ?>
<div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-6 mb-6">
    <div class="flex items-start justify-between">
        <div class="flex items-center flex-1">
            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3"></i>
            <div>
                <div class="font-semibold text-yellow-900">No Location Set</div>
                <div class="text-sm text-yellow-700">Warehouse location has not been assigned yet.</div>
            </div>
        </div>
        <button onclick="openSetLocationModal()" 
                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-lg ml-4">
            <i class="fas fa-map-marker-alt"></i>
            <span>Set Location</span>
        </button>
    </div>
</div>
<?php endif; ?>
                </div>
            </div>

            <!-- Owner Information -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-user mr-3"></i>
                        Owner Information
                    </h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Customer Name</div>
                            <div class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($orderInfo['customer_name']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Email Address</div>
                            <div class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($orderInfo['customer_email']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Order Date</div>
                            <div class="text-lg font-semibold text-gray-900">
                                <?php echo date('M j, Y g:i A', strtotime($orderInfo['order_date'])); ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Order Status</div>
                            <div>
                                <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?php 
                                    echo ($orderInfo['order_status'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : 
                                         (($orderInfo['order_status'] === 'processing') ? 'bg-blue-100 text-blue-800' : 
                                         'bg-green-100 text-green-800'); 
                                ?>">
                                    <?php echo htmlspecialchars(ucfirst($orderInfo['order_status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supplier Information -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-building mr-3"></i>
                        Supplier Information
                    </h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Supplier Name</div>
                            <div class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($supplierInfo['name']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Contact Person</div>
                            <div class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($supplierInfo['contact']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Email</div>
                            <div class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($supplierInfo['email']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Phone</div>
                            <div class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($supplierInfo['phone']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="view_po_items.php?po_number=<?php echo urlencode($itemInfo['po_number']); ?>" 
                   class="inline-flex items-center justify-center space-x-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 shadow-lg">
                    <i class="fas fa-list"></i>
                    <span>View All P.O. Items</span>
                </a>
                
                <button onclick="window.print()" 
                        class="inline-flex items-center justify-center space-x-2 bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 shadow-lg">
                    <i class="fas fa-print"></i>
                    <span>Print Details</span>
                </button>
            </div>

        <?php endif; ?>
    </div>

    <!-- Set/Edit Location Modal -->
    <div id="locationModal" class="modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 rounded-t-xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-map-marker-alt mr-2"></i><span id="modalTitle">Set Location</span>
                    </h3>
                    <button onclick="closeLocationModal()" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i>Warehouse Location
                    </label>
                    <input type="text" 
                           id="warehouseLocationInput" 
                           placeholder="e.g., Aisle A, Shelf 3, Bin 5" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Enter the physical location where this item is stored</p>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex space-x-3">
                    <button onclick="saveLocation()" 
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                        <i class="fas fa-save"></i>
                        <span>Save Location</span>
                    </button>
                    <button onclick="closeLocationModal()" 
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg transition-colors duration-200">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @media print {
            .no-print, nav, button, .modal {
                display: none !important;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
    
    <script>
    const itemId = <?php echo $item_id; ?>;
    
    function updateTrackingStatus(newStatus) {
        if (!confirm('Are you sure you want to update the tracking status to "' + newStatus + '"?\n\nThis will indicate that the package has been received and is now stored in the warehouse.')) {
            return;
        }
        
        // Show loading state
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
        
        fetch('update_tracking_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId,
                tracking_status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✓ Tracking status updated successfully!\n\nNew status: ' + newStatus);
                window.location.reload();
            } else {
                alert('✗ Failed to update status: ' + (data.error || 'Unknown error'));
                button.disabled = false;
                button.innerHTML = originalContent;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('✗ Failed to update tracking status. Please try again.');
            button.disabled = false;
            button.innerHTML = originalContent;
        });
    }
    
    function openSetLocationModal() {
        document.getElementById('modalTitle').textContent = 'Set Location';
        document.getElementById('warehouseLocationInput').value = '';
        document.getElementById('locationModal').classList.add('active');
        document.getElementById('warehouseLocationInput').focus();
    }
    
    function openEditLocationModal() {
        document.getElementById('modalTitle').textContent = 'Edit Location';
        document.getElementById('warehouseLocationInput').value = '<?php echo htmlspecialchars($itemInfo['warehouse_location'] ?? '', ENT_QUOTES); ?>';
        document.getElementById('locationModal').classList.add('active');
        document.getElementById('warehouseLocationInput').focus();
    }
    
    function closeLocationModal() {
        document.getElementById('locationModal').classList.remove('active');
    }
    
    function saveLocation() {
        const location = document.getElementById('warehouseLocationInput').value.trim();
        
        if (!location) {
            alert('Please enter warehouse location');
            return;
        }
        
        // Send to server
        fetch('update_location.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId,
                warehouse_location: location
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Location saved successfully!');
                closeLocationModal();
                window.location.reload();
            } else {
                alert('Failed to save: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to save location');
        });
    }
    
    // Close modal when clicking outside
    document.getElementById('locationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLocationModal();
        }
    });
    
    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLocationModal();
        }
    });
</script>
</body>
</html>