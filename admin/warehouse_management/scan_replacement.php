<?php
// scan_replacement.php
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

$replacement_id = isset($_GET['replacement_id']) ? intval($_GET['replacement_id']) : 0;
$itemInfo = null;
$orderInfo = null;
$supplierInfo = null;

if ($replacement_id > 0) {
    // Get replacement item information using order_id and order_item_id
    $stmt = $conn->prepare("
        SELECT 
            rr.id as replacement_id,
            rr.order_id,
            rr.order_item_id,
            rr.replacement_quantity,
            rr.reason as replacement_reason,
            rr.po_number,
            rr.qr_code,
            rr.warehouse_location,
            rr.created_at as replacement_date,
            rr.status,
            oi.product_id,
            oi.product_name,
            oi.size,
            oi.variant_color,
            oi.codename,
            oi.descrip6,
            oi.descrip7,
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
        FROM replacement_requests rr
        INNER JOIN order_items oi ON rr.order_item_id = oi.id
        INNER JOIN orders o ON rr.order_id = o.id
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
        WHERE rr.id = ?
    ");

    $stmt->bind_param("i", $replacement_id);
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
    <title>Replacement Item - <?php echo $itemInfo ? htmlspecialchars($itemInfo['product_name']) : 'Not Found'; ?></title>
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
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header - Mobile Optimized -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
            <div class="py-3 sm:py-4">
                <!-- Mobile Layout -->
                <div class="flex flex-col space-y-3 sm:hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="bg-red-500 p-2 rounded-lg">
                                <i class="fas fa-sync-alt text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-gray-900">Replacement Item</h1>
                                <p class="text-xs text-gray-600">QR code scan</p>
                            </div>
                        </div>
                        <div class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-sm">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-1 text-gray-700">
                                <i class="fas fa-user text-primary-600"></i>
                                <span class="font-medium truncate max-w-[120px]"><?php echo htmlspecialchars($fullname); ?></span>
                            </div>
                            <div class="flex items-center space-x-1 text-gray-600">
                                <i class="fas fa-shield-alt"></i>
                                <span><?php echo htmlspecialchars(ucfirst($user_level)); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Layout -->
                <div class="hidden sm:flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <div class="bg-red-500 p-3 rounded-lg">
                            <i class="fas fa-sync-alt text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Replacement Item Information</h1>
                            <p class="text-gray-600 mt-1">Replacement item details from QR code scan</p>
                        </div>
                    </div>

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
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8 py-4 sm:py-8">

        <?php if (!$itemInfo): ?>
            <!-- Item Not Found -->
            <div class="bg-white rounded-xl shadow-lg border border-red-200 p-6 sm:p-12 text-center">
                <div class="text-red-500 mb-4">
                    <i class="fas fa-exclamation-triangle text-4xl sm:text-6xl"></i>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Replacement Item Not Found</h2>
                <p class="text-sm sm:text-base text-gray-600 mb-6">
                    <?php if ($replacement_id > 0): ?>
                        No replacement item found with ID: <span class="font-mono font-bold"><?php echo $replacement_id; ?></span>
                    <?php else: ?>
                        Invalid or missing replacement ID in QR code.
                    <?php endif; ?>
                </p>
                <a href="view_po_items.php"
                    class="inline-flex items-center space-x-2 bg-primary-600 hover:bg-primary-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg transition-colors duration-200 text-sm sm:text-base">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to P.O. Items</span>
                </a>
            </div>
        <?php else: ?>

            <!-- Success Badge with Replacement Indicator -->
            <div class="mb-4 sm:mb-6 flex justify-center">
                <div class="inline-flex items-center space-x-2 bg-red-100 text-red-800 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full text-xs sm:text-sm">
                    <i class="fas fa-sync-alt"></i>
                    <span class="font-medium">Replacement Item Scanned Successfully</span>
                </div>
            </div>

            <!-- Replacement Info Banner -->
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                <div class="flex items-start">
                    <i class="fas fa-sync-alt text-red-600 text-2xl mr-3 mt-1"></i>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-red-800 mb-1">REPLACEMENT ITEM</h3>
                        <div class="text-sm text-red-700 space-y-1">
                            <div><strong>Reason:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $itemInfo['replacement_reason']))); ?></div>
                            <div><strong>Original Order Item ID:</strong> #<?php echo $itemInfo['order_item_id']; ?></div>
                            <div><strong>Replacement Date:</strong> <?php echo date('M j, Y g:i A', strtotime($itemInfo['replacement_date'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item Information Card -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-4 sm:mb-6">
                <!-- Header -->
                <div class="bg-gradient-to-r from-red-500 to-red-600 px-4 sm:px-6 py-3 sm:py-4">
                    <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-box mr-2 sm:mr-3"></i>
                        Replacement Item Details
                    </h2>
                </div>

                <!-- Content -->
                <div class="p-4 sm:p-6">
                    <!-- Product Name -->
                    <div class="mb-4 sm:mb-6 pb-4 sm:pb-6 border-b border-gray-200">
                        <div class="text-xs sm:text-sm text-gray-600 mb-1">Product Name</div>
                        <div class="text-lg sm:text-2xl font-bold text-gray-900 break-words">
                            <?php echo htmlspecialchars($itemInfo['product_name']); ?>
                        </div>
                    </div>

                    <!-- Grid Information -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6">
                        <!-- Left Column -->
                        <div class="space-y-3 sm:space-y-4">
                            <div>
                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                    <i class="fas fa-barcode w-4 sm:w-5 mr-1"></i>Code Name
                                </div>
                                <div class="text-base sm:text-lg font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($itemInfo['codename']); ?>
                                </div>
                            </div>

                            <div>
                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                    <i class="fas fa-ruler w-4 sm:w-5 mr-1"></i>Size & Color
                                </div>
                                <div class="text-base sm:text-lg font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($itemInfo['size']); ?> |
                                    <?php echo htmlspecialchars($itemInfo['variant_color']); ?>
                                </div>
                            </div>

                            <div>
                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                    <i class="fas fa-box w-4 sm:w-5 mr-1"></i>Replacement Quantity
                                </div>
                                <div class="text-base sm:text-lg font-semibold text-red-600">
                                    <?php echo $itemInfo['replacement_quantity']; ?>
                                    <?php echo htmlspecialchars($itemInfo['descrip6'] ?: 'pcs'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-3 sm:space-y-4">
                            <div>
                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                    <i class="fas fa-hashtag w-4 sm:w-5 mr-1"></i>Replacement ID
                                </div>
                                <div class="text-base sm:text-lg font-semibold text-gray-900">
                                    <?php echo $itemInfo['replacement_id']; ?>
                                </div>
                            </div>

                            <div>
                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                    <i class="fas fa-file-invoice w-4 sm:w-5 mr-1"></i>P.O. Number
                                </div>
                                <div class="text-base sm:text-lg font-semibold text-gray-900">
                                    <?php echo $itemInfo['po_number'] ? htmlspecialchars($itemInfo['po_number']) : '<span class="text-gray-400">Not assigned</span>'; ?>
                                </div>
                            </div>

                            <div>
                                <div class="text-xs sm:text-sm text-gray-600 mb-1">
                                    <i class="fas fa-shopping-cart w-4 sm:w-5 mr-1"></i>Order ID
                                </div>
                                <div class="text-base sm:text-lg font-semibold text-gray-900">
                                    #<?php echo $orderInfo['order_id']; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Replacement Status Section -->
                    <?php
                    $currentStatus = $itemInfo['status'] ?? 'pending';
                    $isReceived = ($currentStatus === 'In Warehouse');
                    ?>

                    <div class="bg-gradient-to-r from-<?php echo $isReceived ? 'green' : 'yellow'; ?>-50 to-<?php echo $isReceived ? 'emerald' : 'orange'; ?>-50 border-2 border-<?php echo $isReceived ? 'green' : 'yellow'; ?>-300 rounded-lg p-4 sm:p-6 mb-4 sm:mb-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div class="flex items-start flex-1">
                                <div class="bg-<?php echo $isReceived ? 'green' : 'yellow'; ?>-500 p-2 sm:p-3 rounded-lg mr-3 sm:mr-4 flex-shrink-0">
                                    <i class="fas fa-<?php echo $isReceived ? 'check-circle' : 'clock'; ?> text-white text-lg sm:text-2xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs sm:text-sm text-<?php echo $isReceived ? 'green' : 'yellow'; ?>-700 font-medium mb-1 sm:mb-2">
                                        REPLACEMENT STATUS
                                    </div>
                                    <div class="text-lg sm:text-2xl font-bold text-<?php echo $isReceived ? 'green' : 'yellow'; ?>-900 break-words">
                                        <?php echo htmlspecialchars(ucfirst($currentStatus)); ?>
                                    </div>
                                    <div class="text-xs sm:text-sm text-<?php echo $isReceived ? 'green' : 'yellow'; ?>-600 mt-1 sm:mt-2">
                                        <?php if ($isReceived): ?>
                                            <i class="fas fa-check-circle mr-1"></i>Replacement item has been received and stored
                                        <?php elseif ($currentStatus === 'pending'): ?>
                                            <i class="fas fa-clock mr-1"></i>Awaiting receipt confirmation
                                        <?php elseif ($currentStatus === 'approved'): ?>
                                            <i class="fas fa-check mr-1"></i>Replacement approved, awaiting receipt
                                        <?php else: ?>
                                            <i class="fas fa-info-circle mr-1"></i><?php echo htmlspecialchars($currentStatus); ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($isReceived): ?>
                                        <div class="text-xs text-green-500 mt-1 sm:mt-2 flex items-center">
                                            <i class="fas fa-calendar-check mr-1"></i>
                                            <span class="font-medium">Received and confirmed</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($isReceived): ?>
                                <div class="bg-green-500 text-white px-3 sm:px-4 py-2 rounded-lg flex items-center justify-center space-x-2 shadow-lg w-full sm:w-auto">
                                    <i class="fas fa-check-circle text-base sm:text-xl"></i>
                                    <span class="font-medium text-sm sm:text-base">Confirmed</span>
                                </div>
                            <?php elseif (strtolower($currentStatus) === 'processing' && (strtolower($user_level) === 'warehouse' || strtolower($user_level) === 'superadmin')): ?>
                                <button onclick="updateReplacementStatus('In Warehouse')"
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 shadow-lg w-full sm:w-auto hover:shadow-xl transform hover:scale-105 text-sm sm:text-base">
                                    <i class="fas fa-warehouse"></i>
                                    <span>Mark as In Warehouse</span>
                                </button>
                            <?php elseif (!$isReceived && strtolower($currentStatus) !== 'processing'): ?>
                                <!-- Show status info when not processing and not received -->
                                <div class="bg-blue-100 text-blue-800 px-3 sm:px-4 py-2 rounded-lg flex items-center justify-center space-x-2 w-full sm:w-auto">
                                    <i class="fas fa-info-circle text-base sm:text-xl"></i>
                                    <span class="font-medium text-sm sm:text-base">Status: <?php echo htmlspecialchars(ucfirst($currentStatus)); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Warehouse Location -->
                    <?php if (!empty($itemInfo['warehouse_location'])): ?>
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300 rounded-lg p-4 sm:p-6 mb-4 sm:mb-6">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
                                <div class="flex items-start flex-1">
                                    <div class="bg-blue-500 p-2 sm:p-3 rounded-lg mr-3 sm:mr-4 flex-shrink-0">
                                        <i class="fas fa-map-marker-alt text-white text-lg sm:text-2xl"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs sm:text-sm text-blue-700 font-medium mb-1 sm:mb-2">
                                            WAREHOUSE LOCATION
                                        </div>
                                        <div class="text-lg sm:text-2xl font-bold text-blue-900 break-words">
                                            <?php echo htmlspecialchars($itemInfo['warehouse_location']); ?>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="openEditLocationModal()"
                                    class="bg-amber-500 hover:bg-amber-600 text-white px-3 sm:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 shadow-lg w-full sm:w-auto text-sm sm:text-base">
                                    <i class="fas fa-edit"></i>
                                    <span>Edit</span>
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4 sm:p-6 mb-4 sm:mb-6">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
                                <div class="flex items-center flex-1">
                                    <i class="fas fa-exclamation-triangle text-yellow-600 text-lg sm:text-xl mr-2 sm:mr-3 flex-shrink-0"></i>
                                    <div>
                                        <div class="font-semibold text-yellow-900 text-sm sm:text-base">No Location Set</div>
                                        <div class="text-xs sm:text-sm text-yellow-700">Warehouse location has not been assigned yet.</div>
                                    </div>
                                </div>
                                <button onclick="openSetLocationModal()"
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 shadow-lg w-full sm:w-auto text-sm sm:text-base">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Set Location</span>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Owner Information -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-4 sm:mb-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-4 sm:px-6 py-3 sm:py-4">
                    <h2 class="text-lg sm:text-xl font-bold text-white flex items-center">
                        <i class="fas fa-user mr-2 sm:mr-3"></i>
                        Owner Information
                    </h2>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <div class="text-xs sm:text-sm text-gray-600 mb-1">Customer Name</div>
                            <div class="text-base sm:text-lg font-semibold text-gray-900 break-words">
                                <?php echo htmlspecialchars($orderInfo['customer_name']); ?>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs sm:text-sm text-gray-600 mb-1">Email Address</div>
                            <div class="text-base sm:text-lg font-semibold text-gray-900 break-all">
                                <?php echo htmlspecialchars($orderInfo['customer_email']); ?>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs sm:text-sm text-gray-600 mb-1">Order Date</div>
                            <div class="text-base sm:text-lg font-semibold text-gray-900">
                                <?php echo date('M j, Y g:i A', strtotime($orderInfo['order_date'])); ?>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs sm:text-sm text-gray-600 mb-1">Order Status</div>
                            <div>
                                <span class="inline-block px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium <?php
                                                                                                                        echo ($orderInfo['order_status'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : (($orderInfo['order_status'] === 'processing') ? 'bg-blue-100 text-blue-800' :
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
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-4 sm:mb-6">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-4 sm:px-6 py-3 sm:py-4">
                    <h2 class="text-lg sm:text-xl font-bold text-white flex items-center">
                        <i class="fas fa-building mr-2 sm:mr-3"></i>
                        Supplier Information
                    </h2>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <div class="text-xs sm:text-sm text-gray-600 mb-1">Supplier Name</div>
                            <div class="text-base sm:text-lg font-semibold text-gray-900 break-words">
                                <?php echo htmlspecialchars($supplierInfo['name']); ?>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs sm:text-sm text-gray-600 mb-1">Contact Person</div>
                            <div class="text-base sm:text-lg font-semibold text-gray-900 break-words">
                                <?php echo htmlspecialchars($supplierInfo['contact']); ?>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs sm:text-sm text-gray-600 mb-1">Email</div>
                            <div class="text-base sm:text-lg font-semibold text-gray-900 break-all">
                                <?php echo htmlspecialchars($supplierInfo['email']); ?>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs sm:text-sm text-gray-600 mb-1">Phone</div>
                            <div class="text-base sm:text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($supplierInfo['phone']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center">
                <a href="view_po_items.php?po_number=<?php echo urlencode($itemInfo['po_number']); ?>"
                    class="inline-flex items-center justify-center space-x-2 bg-primary-600 hover:bg-primary-700 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg transition-colors duration-200 shadow-lg text-sm sm:text-base">
                    <i class="fas fa-list"></i>
                    <span>View All P.O. Items</span>
                </a>
            </div>

        <?php endif; ?>
    </div>

    <!-- Set/Edit Location Modal -->
    <div id="locationModal" class="modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-3 sm:mx-4">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 sm:px-6 py-3 sm:py-4 rounded-t-xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg sm:text-xl font-bold text-white">
                        <i class="fas fa-map-marker-alt mr-2"></i><span id="modalTitle">Set Location</span>
                    </h3>
                    <button onclick="closeLocationModal()" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-lg sm:text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-4 sm:p-6">
                <div class="mb-4 sm:mb-6">
                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i>Warehouse Location
                    </label>
                    <input type="text"
                        id="warehouseLocationInput"
                        placeholder="e.g., Aisle A, Shelf 3, Bin 5"
                        class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm sm:text-base">
                    <p class="text-xs text-gray-500 mt-1">Enter the physical location where this replacement item is stored</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <button onclick="saveLocation()"
                        class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 sm:py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 text-sm sm:text-base">
                        <i class="fas fa-save"></i>
                        <span>Save Location</span>
                    </button>
                    <button onclick="closeLocationModal()"
                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2.5 sm:py-2 rounded-lg transition-colors duration-200 text-sm sm:text-base">
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

        @media (max-width: 640px) {
            .break-words {
                word-break: break-word;
                overflow-wrap: break-word;
            }

            .break-all {
                word-break: break-all;
            }
        }
    </style>

    <script>
        const replacementId = <?php echo $replacement_id; ?>;

        function updateReplacementStatus(newStatus) {
            const statusMessages = {
                'In Warehouse': 'This will indicate that the replacement item has been received and is now stored in the warehouse.',
                'approved': 'This will approve the replacement request.',
                'processing': 'This will mark the replacement as being processed.',
                'ready_for_pickup': 'This will mark the replacement as ready for pickup.',
                'out_for_delivery': 'This will mark the replacement as out for delivery.',
                'delivered': 'This will mark the replacement as delivered to the customer.',
                'cancelled': 'This will cancel the replacement request.'
            };

            const message = statusMessages[newStatus] || 'This will update the replacement status.';

            if (!confirm('Are you sure you want to mark this replacement item as "' + newStatus + '"?\n\n' + message)) {
                return;
            }

            // Show loading state
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';

            fetch('update_replacement_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        replacement_id: replacementId,
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ Replacement status updated successfully!\n\nNew status: ' + newStatus);
                        window.location.reload();
                    } else {
                        alert('✗ Failed to update status: ' + (data.error || 'Unknown error'));
                        button.disabled = false;
                        button.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('✗ Failed to update replacement status. Please try again.');
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

            // Send to server with item_type specified
            fetch('update_location.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_id: replacementId,
                        warehouse_location: location,
                        item_type: 'replacement'
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