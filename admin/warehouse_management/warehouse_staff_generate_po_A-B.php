<?php
//warehouse_staff_generate_po_A-B.php (Enhanced with supplier change feature)
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
function generateCustomPONumber($supplier_id)
{
    $date = date('mdY'); // format: 10202025
    $time = date('Gis'); // format: 92233 (9:22:33)
    return 'NH' . $date . $time . $supplier_id;
}

// Check if we're editing an existing P.O.
$editing_po_id = isset($_GET['edit_po']) ? (int)$_GET['edit_po'] : 0;
$existing_po_data = null;

if ($editing_po_id > 0) {
    $poDataStmt = $conn->prepare("
        SELECT * FROM po_attachments 
        WHERE id = ? AND order_id = ? 
        LIMIT 1
    ");
    $poDataStmt->bind_param("ii", $editing_po_id, $order_id);
    $poDataStmt->execute();
    $existing_po_data = $poDataStmt->get_result()->fetch_assoc();
    $poDataStmt->close();

    // If P.O. doesn't exist or doesn't belong to this order, redirect
    if (!$existing_po_data) {
        header("Location: warehouse_staff_po_management_A.php?order_id=" . $order_id);
        exit();
    }
}

// Get P.O. rejection status for each supplier
$poRejectionsSql = "SELECT id,
                           supplier_name, 
                           superadmin_approval_status, 
                           approval_status,
                           superadmin_rejection_reason,
                           rejection_reason
                    FROM po_attachments 
                    WHERE order_id = ? 
                    AND (superadmin_approval_status = 'rejected' OR approval_status = 'rejected')";
$poRejectionsStmt = $conn->prepare($poRejectionsSql);
$poRejectionsStmt->bind_param("i", $order_id);
$poRejectionsStmt->execute();
$rejectedPOs = $poRejectionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$poRejectionsStmt->close();

// Create a lookup array for rejected suppliers
$rejectedSuppliers = [];
foreach ($rejectedPOs as $rejection) {
    $rejectedSuppliers[$rejection['supplier_name']] = $rejection;
}

// Get order items with assigned suppliers and original price from product_variants
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
        oi.quantity,
        oi.subtotal as original_subtotal,
        oi.origin,
        oi.supplier_id,
        oi.po_number,
        slp.supplier_price,
        COALESCE(slp.supplier_price, oi.price) as current_price,
        (COALESCE(slp.supplier_price, oi.price) * oi.quantity) as calculated_subtotal,
        sl.business_name,
        sl.primary_contact_name,
        sl.email_address,
        sl.phone_number,
        sl.business_address,
        pv.namevariant,
        pv.color as variant_color_db,
        pv.size as variant_size_db
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    LEFT JOIN supp_link_products slp ON oi.variant_id = slp.variant_id 
        AND oi.supplier_id = slp.supplier_id 
        AND slp.status = 'active'
    WHERE oi.order_id = ? AND oi.supplier_id IS NOT NULL
    ORDER BY oi.id
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
    $supplierKey = strval($item['supplier_id']);

    if (!isset($supplierGroups[$supplierKey])) {
        // Check if any item has a P.O. number for this supplier
        $existing_po = null;
        $po_attachment_id = null;
        $po_approval_status = null;
        $po_superadmin_approval_status = null;

        // First, get P.O. attachment by supplier_id directly (more reliable)
        $poAttachmentSql = "SELECT id, po_number, approval_status, superadmin_approval_status 
                        FROM po_attachments 
                        WHERE order_id = ? AND supplier_name = (SELECT business_name FROM supplier_list WHERE id = ? LIMIT 1)
                        ORDER BY id DESC LIMIT 1";
        $poAttachmentStmt = $conn->prepare($poAttachmentSql);
        $poAttachmentStmt->bind_param("ii", $order_id, $item['supplier_id']);
        $poAttachmentStmt->execute();
        $poAttachmentResult = $poAttachmentStmt->get_result();

        if ($poAttachmentRow = $poAttachmentResult->fetch_assoc()) {
            $po_attachment_id = $poAttachmentRow['id'];
            $existing_po = $poAttachmentRow['po_number'];
            $po_approval_status = $poAttachmentRow['approval_status'];
            $po_superadmin_approval_status = $poAttachmentRow['superadmin_approval_status'];
        }
        $poAttachmentStmt->close();

        // Fallback: check order_items if P.O. attachment not found
        if (!$existing_po) {
            foreach ($allItems as $checkItem) {
                $checkKey = strval($checkItem['supplier_id']);
                if ($checkKey === $supplierKey && !empty($checkItem['po_number'])) {
                    $existing_po = $checkItem['po_number'];

                    // Try to get attachment info again using this P.O. number
                    $poAttachmentSql2 = "SELECT id, approval_status, superadmin_approval_status FROM po_attachments WHERE order_id = ? AND po_number = ? LIMIT 1";
                    $poAttachmentStmt2 = $conn->prepare($poAttachmentSql2);
                    $poAttachmentStmt2->bind_param("is", $order_id, $existing_po);
                    $poAttachmentStmt2->execute();
                    $poAttachmentResult2 = $poAttachmentStmt2->get_result();
                    if ($poAttachmentRow2 = $poAttachmentResult2->fetch_assoc()) {
                        $po_attachment_id = $poAttachmentRow2['id'];
                        $po_approval_status = $poAttachmentRow2['approval_status'];
                        $po_superadmin_approval_status = $poAttachmentRow2['superadmin_approval_status'];
                    }
                    $poAttachmentStmt2->close();
                    break;
                }
            }
        }

        $supplierGroups[$supplierKey] = [
            'supplier_info' => [
                'name' => $item['business_name'],
                'contact' => $item['primary_contact_name'] ?? '',
                'email' => $item['email_address'] ?? '',
                'phone' => $item['phone_number'] ?? '',
                'address' => $item['business_address'] ?? '',
                'supplier_id' => $item['supplier_id'],
                'existing_po' => $existing_po,
                'po_attachment_id' => $po_attachment_id,
                'po_approval_status' => $po_approval_status,
                'po_superadmin_approval_status' => $po_superadmin_approval_status
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
    <style>
        .dropdown-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .dropdown-content.open {
            max-height: 2000px;
            transition: max-height 0.5s ease-in;
        }

        .rotate-180 {
            transform: rotate(180deg);
            transition: transform 0.3s ease;
        }

        .supplier-group {
            transition: all 0.3s ease;
        }

        .supplier-group:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Smooth transitions for icons */
        .transition-transform {
            transition: transform 0.3s ease;
        }

        /* Item card hover effect */
        .item-card {
            transition: all 0.2s ease;
        }

        .item-card:hover {
            background-color: #f9fafb;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <?php include '../navbar/top.php'; ?>
<!-- Header Navbar -->
<nav class="bg-white border-b border-gray-200">
    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Left: Back + Icons + Divider + Title -->
            <div class="flex items-center gap-2.5">

                <!-- Back Button -->
                <a href="warehouse_staff_po_management_A.php?order_id=<?php echo $order['id']; ?>"
                   class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors duration-150"
                   title="Back">
                    <i class="fas fa-chevron-left text-xs"></i>
                </a>

                <!-- View P.O. Files Button -->
                <a href="warehouse_head_staff_view_po_files_B.php?order_id=<?php echo $order['id']; ?>"
                   class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-500 hover:bg-blue-600 transition-colors duration-150"
                   title="View All P.O. Files">
                    <i class="fas fa-file-excel text-white text-sm"></i>
                </a>

                <!-- Generate P.O. Icon -->
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-green-500">
                    <i class="fas fa-file-invoice text-white text-sm"></i>
                </div>

                <!-- Divider -->
                <div class="h-7 w-px bg-gray-200 mx-1"></div>

                <!-- Title & Order Info -->
                <div>
                    <h1 class="text-sm font-medium text-gray-900 leading-tight">Generate Purchase Order</h1>
                    <p class="text-xs text-gray-500 leading-tight">
                        Order #<?php echo $order['id']; ?> &mdash;
                        <?php echo htmlspecialchars($order['customer_name']); ?>
                    </p>
                </div>

            </div>

            <!-- Right: Order ID badge -->
            <div class="text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-1.5 whitespace-nowrap">
                P.O. Generator
            </div>

        </div>
    </div>
</nav>

    <!-- Main Content -->
    <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="alertContainer" class="mb-6"></div>

        <?php if (empty($supplierGroups)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <div class="text-gray-500">
                    <i class="fas fa-exclamation-triangle text-4xl mb-4 text-yellow-500"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Suppliers Assigned</h3>
                    <p class="text-sm text-gray-600 mb-4">You need to assign suppliers to order items before generating purchase orders.</p>
                    <a href="warehouse_staff_po_management_A.php?order_id=<?php echo $order['id']; ?>"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to P.O Management
                    </a>
                </div>
            </div>
        <?php else: ?>

 

            <!-- Supplier Selection with Enhanced Features -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-building text-primary-600 mr-2"></i>
                        Suppliers & Items Management
                    </h2>
                    <p class="text-sm text-gray-600">
                        Expand each supplier to view items, change suppliers, or generate purchase orders.
                    </p>
                </div>

                <div class="space-y-4" id="suppliersGrid">
                    <?php foreach ($supplierGroups as $supplierKey => $supplierData): ?>
                        <div class="supplier-group rounded-lg border-2 border-gray-200 overflow-hidden"
                            data-supplier="<?php echo htmlspecialchars($supplierKey); ?>">

                            <!-- Supplier Header Card -->
                            <div class="bg-gradient-to-r from-gray-50 to-white p-5">
                                <div class="flex justify-between items-center">
                                    <!-- Left: Supplier Info -->
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="bg-primary-100 p-2 rounded-lg">
                                                <i class="fas fa-building text-primary-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-bold text-gray-900">
                                                    <?php echo htmlspecialchars($supplierData['supplier_info']['name']); ?>
                                                </h4>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-link mr-1"></i>
                                                        Linked
                                                    </span>
                                                    <?php if (!empty($supplierData['supplier_info']['existing_po'])): ?>
                                                        <?php
                                                        // Check if this supplier's P.O. was rejected
                                                        $isRejected = isset($rejectedSuppliers[$supplierData['supplier_info']['name']]);
                                                        ?>
                                                        <?php if ($isRejected): ?>
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-500 text-white animate-pulse">
                                                                <i class="fas fa-exclamation-triangle mr-1"></i>Needs Edit - Rejected
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500 text-white">
                                                                <i class="fas fa-check-circle mr-1"></i>P.O. Generated
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <span class="text-xs text-gray-500">
                                                        <i class="fas fa-box mr-1"></i><?php echo count($supplierData['items']); ?> item(s)
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ml-12 text-xs text-gray-600 space-y-1">
                                            <?php if ($supplierData['supplier_info']['contact']): ?>
                                                <div><i class="fas fa-user w-4 text-gray-400"></i><?php echo htmlspecialchars($supplierData['supplier_info']['contact']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($supplierData['supplier_info']['email']): ?>
                                                <div><i class="fas fa-envelope w-4 text-gray-400"></i><?php echo htmlspecialchars($supplierData['supplier_info']['email']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Right: Action Buttons -->
                                    <div class="flex items-center gap-3">
                                        <!-- View/Hide Items Button -->
                                        <button onclick="toggleItemsList('<?php echo $supplierKey; ?>')"
                                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition-colors duration-200 flex items-center gap-2">
                                            <i class="fas fa-eye"></i>
                                            <span class="text-sm font-medium">View Items</span>
                                            <i id="itemsIcon-<?php echo $supplierKey; ?>" class="fas fa-chevron-down transition-transform duration-200"></i>
                                        </button>

                                        <!-- Generate/Edit P.O. Button -->
                                        <?php if (!empty($supplierData['supplier_info']['po_attachment_id'])): ?>
                                            <?php
                                            // Check if P.O. is fully approved
                                            $isFullyApproved = ($supplierData['supplier_info']['po_superadmin_approval_status'] == 'approved' &&
                                                $supplierData['supplier_info']['po_approval_status'] == 'approved');
                                            $isRejected = ($supplierData['supplier_info']['po_superadmin_approval_status'] == 'rejected' ||
                                                $supplierData['supplier_info']['po_approval_status'] == 'rejected');
                                            $isPending = (($supplierData['supplier_info']['po_superadmin_approval_status'] == 'pending' ||
                                                $supplierData['supplier_info']['po_approval_status'] == 'pending') &&
                                                !$isRejected);
                                            ?>

                                            <?php if ($isFullyApproved): ?>
                                                <!-- Fully Approved - Cannot Edit -->
                                                <button disabled
                                                    title="P.O. is fully approved and cannot be edited. Download it from the P.O. Files page."
                                                    class="bg-gray-400 cursor-not-allowed text-white px-5 py-2 rounded-lg shadow-md flex items-center gap-2 opacity-60">
                                                    <i class="fas fa-lock"></i>
                                                    <span class="font-medium">P.O. Approved</span>
                                                </button>
                                                <a href="warehouse_head_staff_view_po_files_B.php?order_id=<?php echo $order['id']; ?>"
                                                    class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-5 py-2 rounded-lg shadow-md transition-all duration-200 flex items-center gap-2 transform hover:scale-105"
                                                    title="Go to P.O. Files to download">
                                                    <i class="fas fa-download"></i>
                                                    <span class="font-medium">Download P.O.</span>
                                                </a>
                                            <?php elseif ($isPending): ?>
                                                <!-- Pending Approval - Cannot Edit -->
                                                <button disabled
                                                    title="P.O. is pending approval and cannot be edited at this time."
                                                    class="bg-yellow-400 cursor-not-allowed text-white px-5 py-2 rounded-lg shadow-md flex items-center gap-2 opacity-60">
                                                    <i class="fas fa-clock"></i>
                                                    <span class="font-medium">Pending Approval</span>
                                                </button>
                                            <?php else: ?>
                                                <!-- Not Approved or Rejected - Allow Edit -->
                                                <a href="warehouse_staff_generate_po_A-B.php?order_id=<?php echo $order['id']; ?>&edit_po=<?php echo $supplierData['supplier_info']['po_attachment_id']; ?>"
                                                    class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-5 py-2 rounded-lg shadow-md transition-all duration-200 flex items-center gap-2 transform hover:scale-105">
                                                    <i class="fas fa-edit"></i>
                                                    <span class="font-medium"><?php echo $isRejected ? 'Edit Rejected P.O.' : 'Edit P.O.'; ?></span>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Generate new P.O. -->
                                            <button onclick="selectSupplier('<?php echo htmlspecialchars($supplierKey); ?>')"
                                                class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-5 py-2 rounded-lg shadow-md transition-all duration-200 flex items-center gap-2 transform hover:scale-105">
                                                <i class="fas fa-file-invoice"></i>
                                                <span class="font-medium">Generate P.O.</span>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Reset P.O. Button (if exists) -->
                                        <?php if (!empty($supplierData['supplier_info']['existing_po'])): ?>
                                            <button onclick="resetPONumber('<?php echo htmlspecialchars($supplierKey); ?>')"
                                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center gap-2"
                                                title="Reset P.O. Number">
                                                <i class="fas fa-redo-alt"></i>
                                                <span class="text-sm font-medium">Reset</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- P.O. Number Display (if exists) -->
                                <?php if (!empty($supplierData['supplier_info']['existing_po'])): ?>
                                    <?php
                                    $isRejected = isset($rejectedSuppliers[$supplierData['supplier_info']['name']]);
                                    $bgColor = $isRejected ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200';
                                    $textColor = $isRejected ? 'text-red-600' : 'text-green-600';
                                    $labelColor = $isRejected ? 'text-red-700' : 'text-green-700';
                                    $valueColor = $isRejected ? 'text-red-800' : 'text-green-800';
                                    ?>
                                    <div class="ml-12 mt-3 inline-flex items-center gap-2 <?php echo $bgColor; ?> px-3 py-1 rounded-lg">
                                        <i class="fas fa-file-alt <?php echo $textColor; ?> text-sm"></i>
                                        <span class="text-xs font-medium <?php echo $labelColor; ?>">P.O. Number:</span>
                                        <span class="text-xs font-mono <?php echo $valueColor; ?>"><?php echo htmlspecialchars($supplierData['supplier_info']['existing_po']); ?></span>
                                    </div>

                                    <?php if ($isRejected): ?>
                                        <div class="ml-12 mt-2 p-3 bg-red-50 border-l-4 border-red-500 rounded">
                                            <p class="text-xs font-semibold text-red-900 mb-1">
                                                <i class="fas fa-times-circle mr-1"></i>
                                                Rejection Reason:
                                            </p>
                                            <p class="text-xs text-red-800">
                                                <?php
                                                $rejection = $rejectedSuppliers[$supplierData['supplier_info']['name']];
                                                if ($rejection['superadmin_approval_status'] == 'rejected') {
                                                    echo '<strong>Superadmin:</strong> ' . htmlspecialchars($rejection['superadmin_rejection_reason']);
                                                } elseif ($rejection['approval_status'] == 'rejected') {
                                                    echo '<strong>Document Controller:</strong> ' . htmlspecialchars($rejection['rejection_reason']);
                                                }
                                                ?>
                                            </p>
                                            <div class="mt-2">
                                                <a href="warehouse_staff_generate_po_A-B.php?order_id=<?php echo $order['id']; ?>&edit_po=<?php echo $supplierData['supplier_info']['po_attachment_id']; ?>"
                                                    class="inline-flex items-center px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded transition-colors duration-200">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    Edit P.O. Now
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Collapsible Items List -->
                            <div id="itemsList-<?php echo $supplierKey; ?>" class="dropdown-content">
                                <div class="bg-white border-t-2 border-gray-100 p-5">
                                    <div class="space-y-3">
                                        <?php foreach ($supplierData['items'] as $itemIndex => $item): ?>
                                            <div class="item-card border border-gray-200 rounded-lg p-4 hover:border-primary-300"
                                                data-item-id="<?php echo $item['item_id']; ?>">

                                                <!-- Item Header -->
                                                <div class="flex justify-between items-start mb-3">
                                                    <div class="flex-1">
                                                        <div class="flex items-start gap-3">
                                                            <div class="bg-primary-50 p-2 rounded">
                                                                <i class="fas fa-cube text-primary-600 text-sm"></i>
                                                            </div>
                                                            <div class="flex-1">
                                                                <h5 class="font-semibold text-gray-900 mb-1">
                                                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                                                    <?php if ($item['namevariant']): ?>
                                                                        <span class="text-sm text-gray-500 font-normal">- <?php echo htmlspecialchars($item['namevariant']); ?></span>
                                                                    <?php endif; ?>
                                                                </h5>

                                                                <!-- Item Details Grid -->
                                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-2 text-xs text-gray-600 mt-2">
                                                                    <div>
                                                                        <span class="font-medium text-gray-500">Variant ID:</span>
                                                                        <span class="ml-1"><?php echo $item['variant_id']; ?></span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-500">Code:</span>
                                                                        <span class="ml-1"><?php echo htmlspecialchars($item['codename']); ?></span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-500">Size:</span>
                                                                        <span class="ml-1"><?php echo htmlspecialchars($item['variant_size_db'] ?? $item['size']); ?></span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-500">Color:</span>
                                                                        <span class="ml-1"><?php echo htmlspecialchars($item['variant_color_db'] ?? $item['variant_color']); ?></span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-500">Quantity:</span>
                                                                        <span class="ml-1 font-semibold text-primary-600"><?php echo $item['quantity']; ?></span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-500">Unit Price:</span>
                                                                        <span class="ml-1 font-semibold text-gray-900">₱<?php echo number_format($item['current_price'], 2); ?></span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-500">Subtotal:</span>
                                                                        <span class="ml-1 font-semibold text-gray-900">₱<?php echo number_format($item['calculated_subtotal'], 2); ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Change Supplier Section -->
                                                <div class="border-t border-gray-100 pt-3 mt-3">
                                                    <button onclick="toggleSupplierOptions(<?php echo $item['item_id']; ?>)"
                                                        class="w-full flex items-center justify-between p-3 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg transition-all duration-200">
                                                        <span class="text-sm font-medium text-amber-800 flex items-center gap-2">
                                                            <i class="fas fa-exchange-alt"></i>
                                                            <span>Change Supplier for this Item</span>
                                                        </span>
                                                        <i id="supplierIcon-<?php echo $item['item_id']; ?>" class="fas fa-chevron-down text-amber-600 transition-transform duration-200"></i>
                                                    </button>

                                                    <!-- Supplier Options Dropdown -->
                                                    <div id="supplierOptions-<?php echo $item['item_id']; ?>" class="dropdown-content">
                                                        <div class="border-l-2 border-r-2 border-b-2 border-amber-200 rounded-b-lg p-4 bg-gradient-to-b from-amber-50 to-white mt-0">
                                                            <?php
                                                            // Get available suppliers for this item's variant
                                                            $linkedSuppStmt = $conn->prepare("
                                            SELECT 
                                                slp.supplier_id,
                                                slp.supplier_type,
                                                slp.supplier_price,
                                                sl.business_name,
                                                sl.primary_contact_name,
                                                sl.email_address
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
                                                END ASC
                                        ");
                                                            $linkedSuppStmt->bind_param("i", $item['variant_id']);
                                                            $linkedSuppStmt->execute();
                                                            $availSuppliers = $linkedSuppStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                                            $linkedSuppStmt->close();
                                                            ?>

                                                            <?php if (!empty($availSuppliers)): ?>
                                                                <!-- Linked Suppliers Section -->
                                                                <div class="mb-4">
                                                                    <div class="flex items-center gap-2 mb-3">
                                                                        <i class="fas fa-link text-primary-600"></i>
                                                                        <h6 class="text-sm font-semibold text-gray-900">Linked Suppliers</h6>
                                                                        <span class="text-xs text-gray-500">(<?php echo count($availSuppliers); ?> available)</span>
                                                                    </div>
                                                                    <div class="space-y-2">
                                                                        <?php foreach ($availSuppliers as $supplier): ?>
                                                                            <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg hover:border-primary-300 hover:shadow-sm transition-all duration-200">
                                                                                <div class="flex-1">
                                                                                    <div class="flex items-center gap-2 mb-1">
                                                                                        <span class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($supplier['business_name']); ?></span>
                                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $supplier['supplier_type'] === 'primary' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                                                            <?php echo $supplier['supplier_type'] === 'primary' ? '⭐ Primary' : 'Secondary'; ?>
                                                                                        </span>
                                                                                    </div>
                                                                                    <div class="flex items-center gap-3 text-xs text-gray-600">
                                                                                        <?php if ($supplier['supplier_price']): ?>
                                                                                            <div class="flex items-center gap-1">
                                                                                                <i class="fas fa-tag text-green-600"></i>
                                                                                                <span class="font-semibold text-green-700">₱<?php echo number_format($supplier['supplier_price'], 2); ?></span>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                        <?php if ($supplier['primary_contact_name']): ?>
                                                                                            <div class="flex items-center gap-1">
                                                                                                <i class="fas fa-user text-gray-400"></i>
                                                                                                <span><?php echo htmlspecialchars($supplier['primary_contact_name']); ?></span>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                </div>
                                                                                <button onclick="reassignItemSupplier(<?php echo $item['item_id']; ?>, <?php echo $supplier['supplier_id']; ?>, 'linked')"
                                                                                    class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-xs font-medium transition-colors duration-200 flex items-center gap-1 ml-3">
                                                                                    <i class="fas fa-check"></i>
                                                                                    <span>Assign</span>
                                                                                </button>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                                                    <div class="flex items-center gap-2">
                                                                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                                                        <p class="text-xs text-yellow-700 font-medium">No linked suppliers available for this item</p>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- P.O Details Form -->
            <div id="poDetailsForm" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" style="display: none;">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-edit text-primary-600 mr-2"></i>
                    <?php echo $existing_po_data ? 'Edit Purchase Order Details' : 'Purchase Order Details'; ?>
                </h2>

                <?php if ($existing_po_data): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                            <div>
                                <p class="text-yellow-800 font-medium">Editing Existing P.O.</p>
                                <p class="text-yellow-700 text-sm">Original file: <?php echo htmlspecialchars($existing_po_data['original_filename']); ?></p>
                                <p class="text-yellow-700 text-sm">Uploaded: <?php echo date('M j, Y g:i A', strtotime($existing_po_data['uploaded_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="editingPoId" value="<?php echo $editing_po_id; ?>">
                <?php endif; ?>

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
                        <input type="text" id="paymentTerms"
                            placeholder="e.g., After 7-14 Days"
                            value="<?php echo $existing_po_data ? htmlspecialchars($existing_po_data['payment_terms']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Details</label>
                        <input type="text" id="deliveryDetails"
                            placeholder="e.g., Pickup at warehouse"
                            value="<?php echo $existing_po_data ? htmlspecialchars($existing_po_data['delivery_details']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Condition and Other Special Instructions</label>
                    <textarea id="conditions" rows="3"
                        placeholder="Enter any special conditions or instructions..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"><?php echo $existing_po_data ? htmlspecialchars($existing_po_data['conditions']) : ''; ?></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                    <textarea id="additionalNotes" rows="3"
                        placeholder="Enter any additional notes..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"><?php echo $existing_po_data ? htmlspecialchars($existing_po_data['additional_notes']) : ''; ?></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <?php if ($existing_po_data): ?>
                        <a href="warehouse_staff_po_management_A.php?order_id=<?php echo $order_id; ?>"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                            <i class="fas fa-times"></i>
                            <span class="font-medium">Cancel Edit</span>
                        </a>
                    <?php endif; ?>
                    <button onclick="generatePO()"
                        class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-8 py-3 rounded-lg shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center space-x-2">
                        <i class="fas fa-file-download"></i>
                        <span class="font-medium"><?php echo $existing_po_data ? 'Update P.O.' : 'Generate P.O.'; ?></span>
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

        function toggleItemsList(supplierKey) {
            const dropdown = document.getElementById(`itemsList-${supplierKey}`);
            const icon = document.getElementById(`itemsIcon-${supplierKey}`);

            dropdown.classList.toggle('open');
            icon.classList.toggle('rotate-180');
        }

        function toggleSupplierOptions(itemId) {
            const dropdown = document.getElementById(`supplierOptions-${itemId}`);
            const icon = document.getElementById(`supplierIcon-${itemId}`);

            dropdown.classList.toggle('open');
            icon.classList.toggle('rotate-180');
        }

        function selectSupplier(supplierKey) {
            console.log('Selecting supplier:', supplierKey);
            selectedSupplier = supplierKey;

            // Show P.O details form
            document.getElementById('poDetailsForm').style.display = 'block';
            document.getElementById('itemsPreview').style.display = 'block';

            // Update preview
            updateItemsPreview(supplierKey);

            // Scroll to form
            document.getElementById('poDetailsForm').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function reassignItemSupplier(itemId, supplierId, type) {
            if (!confirm('Are you sure you want to reassign this item to the selected supplier?')) {
                return;
            }

            fetch('warehouse_staff_assign_supplier_A1&B1.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        supplier_id: supplierId,
                        type: type
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Supplier reassigned successfully! Page will reload...', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(data.error || 'Failed to reassign supplier', 'error');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred: ' + error.message, 'error');
                });
        }

        function resetPONumber(supplierKey) {
            if (!confirm('Are you sure you want to reset the P.O. number for this supplier? This will allow you to generate a new P.O.')) {
                return;
            }

            const supplier = supplierData[supplierKey];
            if (!supplier) return;

            const itemIds = supplier.items.map(item => item.item_id);

            fetch('warehouse_staff_reset_po_number_A-B2.php', {
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
        `;

            supplier.items.forEach(item => {
                const unitPrice = parseFloat(item.current_price || item.price || 0);
                const totalPrice = parseFloat(item.calculated_subtotal || item.subtotal || 0);

                html += `
                <tr>
                    <td class="px-4 py-4">
                        <div class="font-medium text-gray-900">${item.product_name}</div>
                        ${item.namevariant ? `<div class="text-sm text-gray-500 italic">${item.namevariant}</div>` : ''}
                        <div class="text-sm text-gray-500">${item.codename}</div>
                    </td>
                    <td class="px-4 py-4 text-sm text-gray-900">
                        <div>Variant ID: ${item.variant_id}</div>
                        <div>${item.variant_size_db || item.size} | ${item.variant_color_db || item.variant_color}</div>
                    </td>
                    <td class="px-4 py-4 text-sm text-gray-900">${item.descrip6 || 'pcs'}</td>
                    <td class="px-4 py-4 text-sm text-gray-900">${item.quantity}</td>
                    <td class="px-4 py-4 text-sm text-gray-900">₱${unitPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td class="px-4 py-4 text-sm text-gray-900">₱${totalPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>
            `;
            });

            html += `</tbody></table></div>`;
            previewContent.innerHTML = html;
        }

        function generatePO() {
            const editingPoId = document.getElementById('editingPoId') ? document.getElementById('editingPoId').value : 0;

            if (!editingPoId && !selectedSupplier) {
                showAlert('Please select a supplier first', 'error');
                return;
            }

            const paymentTerms = document.getElementById('paymentTerms').value;
            const deliveryDetails = document.getElementById('deliveryDetails').value;
            const conditions = document.getElementById('conditions').value;
            const additionalNotes = document.getElementById('additionalNotes').value;

            // Show loading indicator
            showAlert(editingPoId ? 'Updating Purchase Order... Please wait.' : 'Saving Purchase Order... Please wait. File will be available for download after approval.', 'info');

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'warehouse_staff_generate_po_excel_A-B1.php';
            // Remove target='_blank' so it redirects in same window

            const fields = [
                ['order_id', <?php echo $order['id']; ?>],
                ['supplier_key', editingPoId ? '<?php echo $existing_po_data ? ($existing_po_data['supplier_name'] ?? '') : ''; ?>' : selectedSupplier],
                ['payment_terms', paymentTerms],
                ['delivery_details', deliveryDetails],
                ['conditions', conditions],
                ['additional_notes', additionalNotes],
                ['prepared_by', '<?php echo htmlspecialchars($prepared_by); ?>'],
                ['editing_po_id', editingPoId]
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

            // Don't show success here - the redirect will handle it
            // The form submission will redirect to view page with success message
        }

        // Auto-select supplier and show form if editing
        <?php if ($existing_po_data): ?>
            window.addEventListener('DOMContentLoaded', function() {
                // Find the supplier key from the existing P.O.
                const supplierName = '<?php echo addslashes($existing_po_data['supplier_name']); ?>';

                // Try to find matching supplier key
                let foundSupplierKey = null;
                Object.keys(supplierData).forEach(key => {
                    if (supplierData[key].supplier_info.name === supplierName) {
                        foundSupplierKey = key;
                    }
                });

                if (foundSupplierKey) {
                    selectedSupplier = foundSupplierKey;
                    document.getElementById('poDetailsForm').style.display = 'block';
                    document.getElementById('itemsPreview').style.display = 'block';
                    updateItemsPreview(foundSupplierKey);

                    // Scroll to form
                    document.getElementById('poDetailsForm').scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>