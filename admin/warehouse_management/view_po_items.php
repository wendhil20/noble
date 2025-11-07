<?php
// view_po_items.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);
// Either warehouse receiver or warehouse keeper can access
require_subrole(['warehouse_receiver']);

// Ensure session exists
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get user info
$sessionUser = $_SESSION['noble_user'];
$user_id = null;
$fullname = '';

if (is_array($sessionUser)) {
    $user_id = $sessionUser['id'] ?? $sessionUser['user_id'] ?? null;
    $fullname = $sessionUser['fullname'] ?? $sessionUser['name'] ?? '';
}

$po_number = isset($_GET['po_number']) ? trim($_GET['po_number']) : '';
$orderItems = [];
$orderInfo = null;
$supplierInfo = null;

if (!empty($po_number)) {
    // Get order items with the P.O. number (both original and replacement items)
    $itemStmt = $conn->prepare("
        SELECT 
            oi.id as item_id,
            oi.order_id,
            oi.product_id,
            CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
            CAST(oi.size AS CHAR) COLLATE utf8mb4_unicode_ci as size,
            CAST(oi.variant_color AS CHAR) COLLATE utf8mb4_unicode_ci as variant_color,
            CAST(oi.codename AS CHAR) COLLATE utf8mb4_unicode_ci as codename,
            CAST(oi.descrip6 AS CHAR) COLLATE utf8mb4_unicode_ci as descrip6,
            CAST(oi.descrip7 AS CHAR) COLLATE utf8mb4_unicode_ci as descrip7,
            oi.quantity,
            CAST(oi.po_number AS CHAR) COLLATE utf8mb4_unicode_ci as po_number,
            CAST(oi.qr_code AS CHAR) COLLATE utf8mb4_unicode_ci as qr_code,
            CAST(oi.warehouse_location AS CHAR) COLLATE utf8mb4_unicode_ci as warehouse_location,
            oi.supplier_id,
            CAST(oi.manual_supplier_name AS CHAR) COLLATE utf8mb4_unicode_ci as manual_supplier_name,
            CAST('original' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
            NULL as replacement_id,
            CAST(NULL AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason,
            CAST(sl.business_name AS CHAR) COLLATE utf8mb4_unicode_ci as business_name,
            CAST(sl.primary_contact_name AS CHAR) COLLATE utf8mb4_unicode_ci as primary_contact_name,
            CAST(sl.email_address AS CHAR) COLLATE utf8mb4_unicode_ci as email_address,
            CAST(sl.phone_number AS CHAR) COLLATE utf8mb4_unicode_ci as phone_number,
            CAST(sl.business_address AS CHAR) COLLATE utf8mb4_unicode_ci as business_address,
            CAST(o.customer_name AS CHAR) COLLATE utf8mb4_unicode_ci as customer_name,
            CAST(o.email AS CHAR) COLLATE utf8mb4_unicode_ci as customer_email,
            o.created_at as order_date,
            CAST(o.status AS CHAR) COLLATE utf8mb4_unicode_ci as order_status
        FROM order_items oi
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE oi.po_number = ?
        
        UNION ALL
        
        SELECT 
            rr.id as item_id,
            rr.order_id,
            oi.product_id,
            CAST(oi.product_name AS CHAR) COLLATE utf8mb4_unicode_ci as product_name,
            CAST(oi.size AS CHAR) COLLATE utf8mb4_unicode_ci as size,
            CAST(oi.variant_color AS CHAR) COLLATE utf8mb4_unicode_ci as variant_color,
            CAST(oi.codename AS CHAR) COLLATE utf8mb4_unicode_ci as codename,
            CAST(oi.descrip6 AS CHAR) COLLATE utf8mb4_unicode_ci as descrip6,
            CAST(oi.descrip7 AS CHAR) COLLATE utf8mb4_unicode_ci as descrip7,
            rr.replacement_quantity as quantity,
            CAST(rr.po_number AS CHAR) COLLATE utf8mb4_unicode_ci as po_number,
            CAST(rr.qr_code AS CHAR) COLLATE utf8mb4_unicode_ci as qr_code,
            CAST(rr.warehouse_location AS CHAR) COLLATE utf8mb4_unicode_ci as warehouse_location,
            oi.supplier_id,
            CAST(oi.manual_supplier_name AS CHAR) COLLATE utf8mb4_unicode_ci as manual_supplier_name,
            CAST('replacement' AS CHAR) COLLATE utf8mb4_unicode_ci as item_type,
            rr.id as replacement_id,
            CAST(rr.reason AS CHAR) COLLATE utf8mb4_unicode_ci as replacement_reason,
            CAST(sl.business_name AS CHAR) COLLATE utf8mb4_unicode_ci as business_name,
            CAST(sl.primary_contact_name AS CHAR) COLLATE utf8mb4_unicode_ci as primary_contact_name,
            CAST(sl.email_address AS CHAR) COLLATE utf8mb4_unicode_ci as email_address,
            CAST(sl.phone_number AS CHAR) COLLATE utf8mb4_unicode_ci as phone_number,
            CAST(sl.business_address AS CHAR) COLLATE utf8mb4_unicode_ci as business_address,
            CAST(o.customer_name AS CHAR) COLLATE utf8mb4_unicode_ci as customer_name,
            CAST(o.email AS CHAR) COLLATE utf8mb4_unicode_ci as customer_email,
            o.created_at as order_date,
            CAST(o.status AS CHAR) COLLATE utf8mb4_unicode_ci as order_status
        FROM replacement_requests rr
        LEFT JOIN order_items oi ON rr.order_item_id = oi.id
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
        LEFT JOIN orders o ON rr.order_id = o.id
        WHERE rr.po_number = ?
        
        ORDER BY item_id
    ");
    
    $itemStmt->bind_param("ss", $po_number, $po_number);
    $itemStmt->execute();
    $result = $itemStmt->get_result();
    $orderItems = $result->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();
    
    // Get order and supplier info from first item
    if (!empty($orderItems)) {
        $firstItem = $orderItems[0];
        
        $orderInfo = [
            'order_id' => $firstItem['order_id'],
            'customer_name' => $firstItem['customer_name'],
            'customer_email' => $firstItem['customer_email'],
            'order_date' => $firstItem['order_date'],
            'order_status' => $firstItem['order_status']
        ];
        
        $supplierInfo = [
            'name' => $firstItem['supplier_id'] ? $firstItem['business_name'] : $firstItem['manual_supplier_name'],
            'contact' => $firstItem['primary_contact_name'] ?? 'N/A',
            'email' => $firstItem['email_address'] ?? 'N/A',
            'phone' => $firstItem['phone_number'] ?? 'N/A',
            'address' => $firstItem['business_address'] ?? 'N/A',
            'is_manual' => !$firstItem['supplier_id']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View P.O. Items - P.O System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
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
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-transparent no-print">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-500 p-3 rounded-lg">
                        <i class="fas fa-qrcode text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">P.O. Items & QR Management</h1>
                        <p class="text-gray-600 mt-1">Generate QR codes and manage warehouse locations</p>
                    </div>
                </div>
                
                <!-- User Info Display -->
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">
                            <i class="fas fa-user text-primary-600 mr-1"></i>
                            <?php echo htmlspecialchars($fullname); ?>
                        </div>
                    </div>
                    <div class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-sm">
                            <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Alert Container -->
        <div id="alertContainer" class="mb-6"></div>
        
        <!-- Search Box -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 no-print">
            <form method="GET" class="space-y-4">
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search mr-1"></i>Enter P.O. Number
                        </label>
                        <input type="text" 
                               name="po_number" 
                               value="<?php echo htmlspecialchars($po_number); ?>" 
                               placeholder="e.g., NH10202025922331" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-lg"
                               required>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" 
                                class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg transition-colors duration-200 flex items-center space-x-2 shadow-lg">
                            <i class="fas fa-search"></i>
                            <span class="font-medium">Search</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($po_number)): ?>
            <?php if (empty($orderItems)): ?>
                <!-- No Results -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <div class="text-gray-500">
                        <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                        <h3 class="text-lg font-medium mb-2">No Items Found</h3>
                        <p class="text-sm">No items found with P.O. number: <span class="font-mono font-medium"><?php echo htmlspecialchars($po_number); ?></span></p>
                        <p class="text-sm text-gray-400 mt-2">Please check the P.O. number and try again.</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- P.O. Information -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl shadow-sm border border-blue-200 p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-900">
                            <i class="fas fa-file-invoice text-blue-600 mr-2"></i>
                            P.O. Number: <span class="font-mono"><?php echo htmlspecialchars($po_number); ?></span>
                        </h2>
                        <button onclick="window.print()" 
                                class="no-print bg-white hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg border border-gray-300 transition-colors duration-200 flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-print"></i>
                            <span>Print</span>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Order Information -->
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <h3 class="font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-shopping-cart text-primary-600 mr-2"></i>
                                Order Information
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Order ID:</span>
                                    <span class="font-medium text-gray-900">#<?php echo $orderInfo['order_id']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Customer:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($orderInfo['customer_name']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($orderInfo['customer_email']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-medium text-gray-900"><?php echo date('M j, Y g:i A', strtotime($orderInfo['order_date'])); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo ($orderInfo['order_status'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : (($orderInfo['order_status'] === 'processing') ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($orderInfo['order_status'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Supplier Information -->
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <h3 class="font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-building text-green-600 mr-2"></i>
                                Supplier Information
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between items-start">
                                    <span class="text-gray-600">Supplier:</span>
                                    <div class="text-right">
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($supplierInfo['name']); ?></span>
                                        <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium <?php echo $supplierInfo['is_manual'] ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo $supplierInfo['is_manual'] ? 'Manual' : 'Linked'; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!$supplierInfo['is_manual']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Contact:</span>
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($supplierInfo['contact']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Email:</span>
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($supplierInfo['email']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Phone:</span>
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($supplierInfo['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Items:</span>
                                    <span class="font-medium text-primary-600"><?php echo count($orderItems); ?> item(s)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Viewed By Info -->
                    <div class="mt-4 pt-4 border-t border-blue-200">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-eye mr-2"></i>
                            <span>Viewed by: <span class="font-medium text-gray-900"><?php echo htmlspecialchars($fullname); ?></span></span>
                            <span class="mx-2">•</span>
                            <span><?php echo date('M j, Y g:i A'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Items Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($orderItems as $index => $item): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow duration-200">
                        <!-- Item Header -->
                        <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-4 text-white">
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-lg">Item #<?php echo $index + 1; ?></h3>
                                <?php if (!empty($item['qr_code'])): ?>
                                    <span class="bg-green-500 px-2 py-1 rounded-full text-xs font-medium">
                                        <i class="fas fa-check-circle mr-1"></i>QR Generated
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ADD REPLACEMENT BADGE HERE -->
                        <?php if (isset($item['item_type']) && $item['item_type'] === 'replacement'): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 p-3 mx-4 mt-4 rounded">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-sync-alt text-red-600 text-lg"></i>
                                    <span class="text-red-800 font-bold text-sm">REPLACEMENT ITEM</span>
                                </div>
                                <?php if (!empty($item['replacement_reason'])): ?>
                                <span class="text-xs text-red-700 bg-red-50 px-2 py-1 rounded">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $item['replacement_reason']))); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Item Details -->
                        <div class="p-4">
                            <h4 class="font-bold text-gray-900 text-lg mb-2"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                            
                            <div class="space-y-2 text-sm mb-4">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-barcode w-5 mr-2"></i>
                                    <span><?php echo htmlspecialchars($item['codename']); ?></span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-ruler w-5 mr-2"></i>
                                    <span><?php echo htmlspecialchars($item['size']); ?> | <?php echo htmlspecialchars($item['variant_color']); ?></span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-box w-5 mr-2"></i>
                                    <span>Qty: <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['descrip6'] ?: 'pcs'); ?></span>
                                </div>
                                
                                <?php if (!empty($item['warehouse_location'])): ?>
                                    <div class="flex items-start text-gray-600 bg-blue-50 p-2 rounded">
                                        <i class="fas fa-map-marker-alt w-5 mr-2 mt-1"></i>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Location:</div>
                                            <div class="font-medium text-blue-700"><?php echo htmlspecialchars($item['warehouse_location']); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- QR Code Display -->
                            <?php if (!empty($item['qr_code'])): ?>
                                <div class="border-t border-gray-200 pt-4 mb-4">
                                    <div class="text-center">
                                        <div class="inline-block p-2 bg-white border-2 border-gray-300 rounded">
                                            <div id="qr-display-<?php echo $item['item_id']; ?>" class="qr-code-display"></div>
                                        </div>
                                        <div class="mt-2 text-xs font-mono text-gray-600">
                                            <?php echo htmlspecialchars($item['qr_code']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="space-y-2">
                                <?php if (empty($item['qr_code'])): ?>
    <button onclick="openQRModal(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES); ?>', '<?php echo $item['item_type']; ?>')" 
            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-2 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2">
        <i class="fas fa-qrcode"></i>
        <span>Generate QR Code</span>
    </button>
<?php else: ?>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button onclick="downloadQR(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['qr_code'], ENT_QUOTES); ?>')" 
                                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-1 text-sm">
                                            <i class="fas fa-download"></i>
                                            <span>Download</span>
                                        </button>
                                        <button onclick="openEditLocationModal(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['warehouse_location'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES); ?>')" 
                                                class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-1 text-sm">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>Location</span>
                                        </button>
                                    </div>
                                    <button onclick="resetQR(<?php echo $item['item_id']; ?>, '<?php echo $item['item_type']; ?>')" 
        class="w-full bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 text-sm">
    <i class="fas fa-times-circle"></i>
    <span>Reset QR & Location</span>
</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- QR Generation Modal -->
<div id="qrModal" class="modal">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 rounded-t-xl">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-qrcode mr-2"></i>Generate QR Code
                </h3>
                <button onclick="closeQRModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div id="modalItemName" class="text-lg font-semibold text-gray-900 mb-4"></div>
            
            <!-- QR Code Preview -->
            <div class="mb-6 text-center">
                <div class="inline-block p-4 bg-white border-2 border-gray-300 rounded-lg">
                    <div id="qrcode" class="qr-code-container"></div>
                </div>
                <div id="qrCodeValue" class="mt-3 text-sm font-mono text-gray-600"></div>
            </div>
            
            <!-- Info Message -->
            <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                    <div class="text-sm text-blue-800">
                        <strong>Note:</strong> You can set the warehouse location after scanning this QR code.
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex space-x-3">
                <button id="saveQRBtn" onclick="saveQRCode()" 
        class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
    <i class="fas fa-save mr-1"></i>
    <span>Save QR Code</span>
</button>
                <button onclick="closeQRModal()" 
                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg transition-colors duration-200">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Edit Location Modal -->
    <div id="editLocationModal" class="modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="bg-gradient-to-r from-amber-500 to-amber-600 px-6 py-4 rounded-t-xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-map-marker-alt mr-2"></i>Edit Location
                    </h3>
                    <button onclick="closeEditLocationModal()" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div id="editModalItemName" class="text-lg font-semibold text-gray-900 mb-4"></div>
                
                <!-- Warehouse Location Input -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i>Warehouse Location
                    </label>
                    <input type="text" 
                           id="editWarehouseLocation" 
                           placeholder="e.g., Aisle A, Shelf 3, Bin 5" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Update the physical location where this item is stored</p>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex space-x-3">
                    <button onclick="updateLocation()" 
                            class="flex-1 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                        <i class="fas fa-save"></i>
                        <span>Update Location</span>
                    </button>
                    <button onclick="closeEditLocationModal()" 
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg transition-colors duration-200">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentItemId = null;
        let currentQRCode = null;
        
        // Generate existing QR codes on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($orderItems as $item): ?>
                <?php if (!empty($item['qr_code'])): ?>
                    generateQRCodeDisplay(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['qr_code'], ENT_QUOTES); ?>');
                <?php endif; ?>
            <?php endforeach; ?>
        });
        
        function generateQRCodeDisplay(itemId, qrValue) {
            const element = document.getElementById('qr-display-' + itemId);
            if (element) {
                element.innerHTML = ''; // Clear previous QR if any
                new QRCode(element, {
                    text: qrValue,
                    width: 100,
                    height: 100
                });
            }
        }
        
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
            
            // Scroll to alert
            alertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        function openQRModal(itemId, productName, itemType = 'original') {
    currentItemId = itemId;
    
    // Store item type for later use
    window.currentItemType = itemType;
    
    // Get the correct base path from current URL
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    
    // Generate unique QR code value with URL - different page for replacements
    if (itemType === 'replacement') {
        currentQRCode = `${window.location.origin}${basePath}scan_replacement.php?replacement_id=${itemId}`;
    } else {
        currentQRCode = `${window.location.origin}${basePath}scan_item.php?item_id=${itemId}`;
    }
    
    // Set product name with item type indicator
    const typeLabel = itemType === 'replacement' ? ' [REPLACEMENT]' : '';
    document.getElementById('modalItemName').textContent = productName + typeLabel;
    
    // Clear previous QR code
    document.getElementById('qrcode').innerHTML = '';
    
    // Generate QR code
    new QRCode(document.getElementById('qrcode'), {
        text: currentQRCode,
        width: 200,
        height: 200
    });
    
    // Display QR code value
    document.getElementById('qrCodeValue').textContent = currentQRCode;
    
    // Show modal
    document.getElementById('qrModal').classList.add('active');
}
        
        function closeQRModal() {
            document.getElementById('qrModal').classList.remove('active');
            currentItemId = null;
            currentQRCode = null;
        }
        
        function saveQRCode() {
    if (!currentItemId || !currentQRCode) {
        alert('Error: Missing item or QR code data');
        return;
    }
    
    // Disable button to prevent double clicks
    const saveBtn = document.getElementById('saveQRBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i><span>Saving...</span>';
    
    // Get item type (stored when modal was opened)
    const itemType = window.currentItemType || 'original';
    
    console.log('Saving QR Code:', { 
        item_id: currentItemId, 
        qr_code: currentQRCode,
        item_type: itemType
    });
    
    // Send to server with item type
    fetch('save_qr_code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            item_id: currentItemId,
            qr_code: currentQRCode,
            item_type: itemType
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
            showAlert('QR code saved successfully! Reloading...', 'success');
            closeQRModal();
            
            // Force reload after short delay
            setTimeout(() => {
                window.location.reload(true);
            }, 500);
        } else {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save mr-1"></i><span>Save QR Code</span>';
            showAlert('Failed to save: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-1"></i><span>Save QR Code</span>';
        showAlert('Failed to save QR code: ' + error.message, 'error');
    });
}
        
        function openEditLocationModal(itemId, currentLocation, productName) {
            currentItemId = itemId;
            
            // Set product name
            document.getElementById('editModalItemName').textContent = productName;
            
            // Set current location
            document.getElementById('editWarehouseLocation').value = currentLocation;
            
            // Show modal
            document.getElementById('editLocationModal').classList.add('active');
        }
        
        function closeEditLocationModal() {
            document.getElementById('editLocationModal').classList.remove('active');
            currentItemId = null;
        }
        
        function updateLocation() {
    const location = document.getElementById('editWarehouseLocation').value.trim();
    
    if (!location) {
        alert('Please enter warehouse location');
        return;
    }
    
    if (!currentItemId) {
        alert('Error: Missing item data');
        return;
    }
    
    // Send to server
    fetch('update_location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            item_id: currentItemId,
            warehouse_location: location
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Location updated successfully! Reloading...', 'success');
            closeEditLocationModal();
            setTimeout(() => window.location.reload(), 800);
        } else {
            showAlert('Failed to update: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to update location', 'error');
    });
}
        
        function downloadQR(itemId, qrValue) {
    // Store item data in data attributes for easier access
    const itemData = {
        id: itemId,
        qr: qrValue
    };
    
    // Get item data from PHP - we'll pass it via data attributes
    const itemCard = document.querySelector(`#qr-display-${itemId}`);
    if (!itemCard) {
        alert('QR code not found');
        return;
    }
    
    const parentCard = itemCard.closest('.bg-white.rounded-xl');
    if (!parentCard) {
        alert('Item card not found');
        return;
    }
    
    // Extract data from the card
    const productNameEl = parentCard.querySelector('h4');
    const productName = productNameEl ? productNameEl.textContent.trim() : 'Unknown Product';
    
    // Get all text content and extract info
    const allText = parentCard.textContent;
    
    // Extract codename (text after barcode icon)
    let codename = 'N/A';
    const barcodeIcon = parentCard.querySelector('.fa-barcode');
    if (barcodeIcon) {
        const barcodeText = barcodeIcon.parentElement.textContent;
        codename = barcodeText.trim();
    }
    
    // Extract specification (text after ruler icon)
    let specification = 'N/A';
    const rulerIcon = parentCard.querySelector('.fa-ruler');
    if (rulerIcon) {
        const specText = rulerIcon.parentElement.textContent;
        specification = specText.trim();
    }
    
    // Extract quantity (text after box icon)
    let quantity = 'N/A';
    const boxIcon = parentCard.querySelector('.fa-box');
    if (boxIcon) {
        const qtyText = boxIcon.parentElement.textContent;
        quantity = qtyText.trim();
    }
    
    // Extract location
    let location = 'Not Set';
    const locationDiv = parentCard.querySelector('.text-blue-700');
    if (locationDiv) {
        location = locationDiv.textContent.trim();
    }
    
    // Get the QR code canvas
    const qrCanvas = itemCard.querySelector('canvas');
    if (!qrCanvas) {
        alert('QR canvas not found');
        return;
    }
    
    // Create a new canvas with larger size for info
    const finalCanvas = document.createElement('canvas');
    const ctx = finalCanvas.getContext('2d');
    
    // Set canvas size
    const qrSize = 300;
    const padding = 20;
    const infoHeight = 280;
    finalCanvas.width = qrSize + (padding * 2);
    finalCanvas.height = qrSize + infoHeight + (padding * 3);
    
    // Fill white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);
    
    // Draw border
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 2;
    ctx.strokeRect(5, 5, finalCanvas.width - 10, finalCanvas.height - 10);
    
    // Draw QR code (centered and scaled)
    const scale = qrSize / qrCanvas.width;
    ctx.drawImage(qrCanvas, padding, padding, qrSize, qrSize);
    
    // Draw info section
    let yPos = qrSize + padding * 2 + 10;
    
    // Title
    ctx.fillStyle = '#111827';
    ctx.font = 'bold 18px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('ITEM INFORMATION', finalCanvas.width / 2, yPos);
    
    yPos += 25;
    
    // Draw line separator
    ctx.strokeStyle = '#d1d5db';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padding, yPos);
    ctx.lineTo(finalCanvas.width - padding, yPos);
    ctx.stroke();
    
    yPos += 25;
    
    // Helper function to wrap text
    function wrapText(text, maxWidth) {
        const words = text.split(' ');
        const lines = [];
        let currentLine = words[0];
        
        for (let i = 1; i < words.length; i++) {
            const word = words[i];
            const width = ctx.measureText(currentLine + " " + word).width;
            if (width < maxWidth) {
                currentLine += " " + word;
            } else {
                lines.push(currentLine);
                currentLine = word;
            }
        }
        lines.push(currentLine);
        return lines;
    }
    
    ctx.textAlign = 'left';
    const labelX = padding + 5;
    const valueX = padding + 80;
    const maxValueWidth = finalCanvas.width - valueX - padding - 5;
    
    // Product name
    ctx.font = 'bold 13px Arial';
    ctx.fillStyle = '#374151';
    ctx.fillText('Product:', labelX, yPos);
    
    ctx.font = '13px Arial';
    ctx.fillStyle = '#111827';
    const productLines = wrapText(productName, maxValueWidth);
    productLines.forEach((line, index) => {
        ctx.fillText(line, valueX, yPos + (index * 18));
    });
    yPos += (productLines.length * 18) + 8;
    
    // Codename
    ctx.font = 'bold 13px Arial';
    ctx.fillStyle = '#374151';
    ctx.fillText('Code:', labelX, yPos);
    ctx.font = '13px Arial';
    ctx.fillStyle = '#111827';
    ctx.fillText(codename, valueX, yPos);
    yPos += 22;
    
    // Specification
    ctx.font = 'bold 13px Arial';
    ctx.fillStyle = '#374151';
    ctx.fillText('Spec:', labelX, yPos);
    ctx.font = '13px Arial';
    ctx.fillStyle = '#111827';
    const specLines = wrapText(specification, maxValueWidth);
    specLines.forEach((line, index) => {
        ctx.fillText(line, valueX, yPos + (index * 18));
    });
    yPos += (specLines.length * 18) + 8;
    
    // Quantity
    ctx.font = 'bold 13px Arial';
    ctx.fillStyle = '#374151';
    ctx.fillText('Quantity:', labelX, yPos);
    ctx.font = '13px Arial';
    ctx.fillStyle = '#111827';
    ctx.fillText(quantity, valueX, yPos);
    yPos += 25;
    
    // Location (highlighted box)
    const locationBoxHeight = 28;
    ctx.fillStyle = '#dbeafe';
    ctx.fillRect(padding, yPos - 18, finalCanvas.width - padding * 2, locationBoxHeight);
    
    ctx.font = 'bold 13px Arial';
    ctx.fillStyle = '#1e40af';
    ctx.fillText('Location:', labelX, yPos);
    ctx.font = 'bold 13px Arial';
    ctx.fillStyle = '#1e3a8a';
    
    const locationLines = wrapText(location, maxValueWidth);
    locationLines.forEach((line, index) => {
        ctx.fillText(line, valueX, yPos + (index * 16));
    });
    
    yPos += locationBoxHeight + 15;
    
    // QR Code value at bottom
    ctx.font = '10px monospace';
    ctx.fillStyle = '#6b7280';
    ctx.textAlign = 'center';
    ctx.fillText(qrValue, finalCanvas.width / 2, yPos);
    
    // Convert to blob and download
    try {
        finalCanvas.toBlob(function(blob) {
            if (!blob) {
                alert('Failed to create image');
                return;
            }
            
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            
            // Create safe filename
            const safeProductName = productName
                .replace(/[^a-z0-9]/gi, '_')
                .substring(0, 30)
                .replace(/_+/g, '_')
                .replace(/^_|_$/g, '');
            
            a.download = `QR_${safeProductName}_Item${itemId}.png`;
            document.body.appendChild(a);
            a.click();
            
            // Cleanup
            setTimeout(() => {
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }, 100);
            
            showAlert('QR code with item details downloaded!', 'success');
        }, 'image/png');
    } catch (error) {
        console.error('Download error:', error);
        alert('Failed to download QR code: ' + error.message);
    }
}
        
        function resetQR(itemId, itemType = 'original') {
    if (!confirm('Are you sure you want to reset the QR code and location for this item? This action cannot be undone.')) {
        return;
    }
    
    console.log('Resetting QR - Item ID:', itemId, 'Type:', itemType);
    
    // Send to server
    fetch('reset_qr_code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            item_id: itemId,
            item_type: itemType
        })
    })
    .then(response => {
        console.log('Reset response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Reset response data:', data);
        if (data.success) {
            showAlert('QR code and location reset successfully! Reloading...', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showAlert('Failed to reset: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to reset QR code: ' + error.message, 'error');
    });
}
        
        // Close modals when clicking outside
        document.getElementById('qrModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQRModal();
            }
        });
        
        document.getElementById('editLocationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditLocationModal();
            }
        });
        
        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeQRModal();
                closeEditLocationModal();
            }
        });
        
        // Auto-focus on search input
        const searchInput = document.querySelector('input[name="po_number"]');
        if (searchInput && !searchInput.value) {
            searchInput.focus();
        }
    </script>
</body>
</html>