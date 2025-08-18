<?php
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

// Get supplier ID from URL
if (!isset($_GET['supplier_id']) || !is_numeric($_GET['supplier_id'])) {
    header("Location: supplier_directory.php");
    exit();
}

$supplier_id = intval($_GET['supplier_id']);

// Get supplier information
$supplier_sql = "SELECT * FROM supplier_list WHERE id = ?";
$supplier_stmt = $conn->prepare($supplier_sql);
$supplier_stmt->bind_param("i", $supplier_id);
$supplier_stmt->execute();
$supplier_result = $supplier_stmt->get_result();
$supplier = $supplier_result->fetch_assoc();
$supplier_stmt->close();

if (!$supplier) {
    header("Location: supplier_directory.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'link_product' && isset($_POST['product_id'])) {
            $product_id = intval($_POST['product_id']);
            $supplier_type = isset($_POST['supplier_type']) ? $_POST['supplier_type'] : 'secondary'; // Default to secondary
            
            try {
                // Check if link already exists for this supplier and product
                $check_sql = "SELECT id, status, supplier_type FROM supp_link_products WHERE supplier_id = ? AND product_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $supplier_id, $product_id);
                $check_stmt->execute();
                $existing = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();
                
                if ($existing) {
                    if ($existing['status'] === 'active') {
                        // Already linked and active
                        echo json_encode(['success' => true, 'message' => 'Product is already linked to this supplier']);
                        exit();
                    } else {
                        // Update status to active if link exists but inactive
                        $update_sql = "UPDATE supp_link_products SET status = 'active', supplier_type = ?, updated_at = NOW() WHERE supplier_id = ? AND product_id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("sii", $supplier_type, $supplier_id, $product_id);
                        $success = $update_stmt->execute();
                        $update_stmt->close();
                        
                        if ($success) {
                            echo json_encode(['success' => true, 'message' => 'Product linked successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to update product link: ' . $conn->error]);
                        }
                        exit();
                    }
                } else {
                    // Check if trying to set as primary and there's already a primary supplier for this product
                    if ($supplier_type === 'primary') {
                        $primary_check_sql = "SELECT sp.id, sl.business_name FROM supp_link_products sp 
                                            LEFT JOIN supplier_list sl ON sp.supplier_id = sl.id 
                                            WHERE sp.product_id = ? AND sp.supplier_type = 'primary' AND sp.status = 'active'";
                        $primary_check_stmt = $conn->prepare($primary_check_sql);
                        $primary_check_stmt->bind_param("i", $product_id);
                        $primary_check_stmt->execute();
                        $existing_primary = $primary_check_stmt->get_result()->fetch_assoc();
                        $primary_check_stmt->close();
                        
                        if ($existing_primary) {
                            $supplier_name = $existing_primary['business_name'] ? $existing_primary['business_name'] : 'Another supplier';
                            echo json_encode(['success' => false, 'message' => "This product already has a primary supplier ({$supplier_name}). Only one primary supplier is allowed per product."]);
                            exit();
                        }
                    }
                    
                    // For secondary suppliers, we allow multiple, so no additional checks needed
                    
                    // Create new link
                    $insert_sql = "INSERT INTO supp_link_products (supplier_id, product_id, supplier_type, status, created_at, updated_at) VALUES (?, ?, ?, 'active', NOW(), NOW())";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iis", $supplier_id, $product_id, $supplier_type);
                    $success = $insert_stmt->execute();
                    
                    if ($success) {
                        $insert_stmt->close();
                        echo json_encode(['success' => true, 'message' => 'Product linked successfully as ' . $supplier_type . ' supplier']);
                    } else {
                        $error_msg = $conn->error;
                        $insert_stmt->close();
                        echo json_encode(['success' => false, 'message' => 'Failed to create product link: ' . $error_msg]);
                    }
                    exit();
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit();
            }
        }
        
        if ($_POST['action'] === 'unlink_product' && isset($_POST['product_id'])) {
            $product_id = intval($_POST['product_id']);
            
            try {
                // Set status to inactive instead of deleting
                $update_sql = "UPDATE supp_link_products SET status = 'inactive', updated_at = NOW() WHERE supplier_id = ? AND product_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $supplier_id, $product_id);
                $success = $update_stmt->execute();
                $update_stmt->close();
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Product unlinked successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to unlink product: ' . $conn->error]);
                }
                exit();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit();
            }
        }
    }
    
    // If we get here, invalid action
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Handle search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause for products search
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(p.product_name LIKE ? OR p.codename LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all products with their link status for this supplier
$products_sql = "
    SELECT p.*, 
           CASE WHEN sp.status = 'active' THEN 1 ELSE 0 END as is_linked,
           sp.supplier_type,
           sp.created_at as linked_at,
           (SELECT COUNT(*) FROM supp_link_products sp2 WHERE sp2.product_id = p.id AND sp2.supplier_type = 'primary' AND sp2.status = 'active') as has_primary
    FROM products p
    LEFT JOIN supp_link_products sp ON p.id = sp.product_id AND sp.supplier_id = ? AND sp.status = 'active'
    $where_clause
    ORDER BY is_linked DESC, p.product_name ASC
";

try {
    if (!empty($params)) {
        $products_stmt = $conn->prepare($products_sql);
        if (!$products_stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $products_stmt->bind_param("i" . $types, $supplier_id, ...$params);
    } else {
        $products_stmt = $conn->prepare($products_sql);
        if (!$products_stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $products_stmt->bind_param("i", $supplier_id);
    }

    $products_stmt->execute();
    $products_result = $products_stmt->get_result();
    $products = $products_result->fetch_all(MYSQLI_ASSOC);
    $products_stmt->close();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Check if products table has any data
$products_count_sql = "SELECT COUNT(*) as total FROM products";
$products_count_result = $conn->query($products_count_sql);
$total_products = $products_count_result->fetch_assoc()['total'];

// Get linked products count for this supplier
$linked_count_sql = "SELECT COUNT(*) as count FROM supp_link_products WHERE supplier_id = ? AND status = 'active'";
$linked_count_stmt = $conn->prepare($linked_count_sql);
$linked_count_stmt->bind_param("i", $supplier_id);
$linked_count_stmt->execute();
$linked_count = $linked_count_stmt->get_result()->fetch_assoc()['count'];
$linked_count_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Products - <?= htmlspecialchars($supplier['business_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble': {
                            'primary': '#1e40af',
                            'secondary': '#3b82f6',
                            'accent': '#60a5fa'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4 mb-4">
                <a href="suppliers_list.php" class="text-noble-primary hover:text-blue-700 transition-colors">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Link Products</h1>
                    <p class="text-gray-600">Manage product links for <?= htmlspecialchars($supplier['business_name']) ?></p>
                </div>
            </div>
            
            <!-- Supplier Info Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center space-x-4">
                    <?php 
                    $logo_path = !empty($supplier['logo_path']) ? '../../uploads/supplier_logos/' . basename($supplier['logo_path']) : '';
                    $logo_exists = !empty($logo_path) && file_exists($logo_path);
                    
                    if ($logo_exists): ?>
                        <img src="<?= htmlspecialchars($logo_path) ?>" 
                             alt="<?= htmlspecialchars($supplier['business_name']) ?> logo"
                             class="w-16 h-16 rounded-lg object-cover border-2 border-gray-200">
                    <?php else: 
                        $words = explode(' ', trim($supplier['business_name']));
                        $acronym = '';
                        foreach ($words as $word) {
                            if (!empty($word)) {
                                $acronym .= strtoupper(substr($word, 0, 1));
                                if (strlen($acronym) >= 2) break;
                            }
                        }
                        if (empty($acronym)) {
                            $acronym = strtoupper(substr($supplier['business_name'], 0, 2));
                        }
                        
                        $colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-pink-500'];
                        $color_index = abs(crc32($supplier['business_name'])) % count($colors);
                        $bg_color = $colors[$color_index];
                    ?>
                        <div class="w-16 h-16 rounded-lg <?= $bg_color ?> flex items-center justify-center text-white font-bold text-lg">
                            <?= htmlspecialchars($acronym) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex-1">
                        <h2 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($supplier['business_name']) ?></h2>
                        <p class="text-gray-600"><?= htmlspecialchars($supplier['business_type']) ?> • <?= htmlspecialchars($supplier['country_region']) ?></p>
                        <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                            <span><i class="fas fa-link mr-1"></i><?= $linked_count ?> products linked</span>
                            <span><i class="fas fa-box mr-1"></i><?= count($products) ?> total products</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="flex items-center space-x-4">
                <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search products by name, code, or description..."
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-noble-primary focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                <button type="submit" class="bg-noble-primary hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors duration-200">
                    Search
                </button>
                <a href="?supplier_id=<?= $supplier_id ?>" class="text-gray-600 hover:text-gray-800 px-4 py-3">Clear</a>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-box text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
                <p class="text-gray-600">Try adjusting your search criteria.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($products as $product): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200 <?= $product['is_linked'] ? 'ring-2 ring-green-200' : '' ?>" 
                         id="product-card-<?= $product['id'] ?>">
                        <!-- Product Header -->
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-start space-x-4">
                                <!-- Product Image -->
                                <div class="flex-shrink-0">
                                    <?php 
                                    $image_path = !empty($product['main_image']) ? '../../' . $product['main_image'] : '';
                                    $image_exists = !empty($image_path) && file_exists($image_path);
                                    
                                    if ($image_exists): ?>
                                        <img src="<?= htmlspecialchars($image_path) ?>" 
                                             alt="<?= !empty($product['product_name']) ? htmlspecialchars($product['product_name']) : 'Product image' ?>"
                                             class="w-16 h-16 rounded-lg object-cover border-2 border-gray-200">
                                    <?php else: ?>
                                        <div class="w-16 h-16 rounded-lg bg-gray-100 flex items-center justify-center border-2 border-gray-200">
                                            <i class="fas fa-box text-gray-400 text-xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Product Info -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-1 line-clamp-2">
                                        <?= !empty($product['product_name']) ? htmlspecialchars($product['product_name']) : 'Unnamed Product' ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 mb-2">
                                        <?= !empty($product['codename']) ? htmlspecialchars($product['codename']) : 'No code assigned' ?>
                                    </p>
                                    
                                    <!-- Link Status -->
                                    <?php if ($product['is_linked']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $product['supplier_type'] === 'primary' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                            <i class="fas fa-link mr-1"></i>
                                            Linked (<?= ucfirst($product['supplier_type']) ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            <i class="fas fa-unlink mr-1"></i>Not Linked
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Product Details -->
                        <div class="p-6 space-y-3">
                            <!-- Price and Quantity -->
                            <div class="flex justify-between items-center text-sm">
                                <div>
                                    <span class="text-gray-600">Price:</span>
                                    <?php if (!empty($product['price']) && $product['price'] > 0): ?>
                                        <span class="font-semibold text-noble-primary">₱<?= number_format($product['price'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="font-semibold text-gray-400 italic">Not set</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="text-gray-600">Quantity:</span>
                                    <?php if (isset($product['quantity']) && $product['quantity'] !== null): ?>
                                        <span class="font-semibold"><?= number_format($product['quantity']) ?> <?= htmlspecialchars($product['unit'] ?? '') ?></span>
                                    <?php else: ?>
                                        <span class="font-semibold text-gray-400 italic">Not set</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="text-sm text-gray-600">
                                <?php if (!empty($product['description']) && trim($product['description']) !== ''): ?>
                                    <p class="line-clamp-3"><?= htmlspecialchars($product['description']) ?></p>
                                <?php else: ?>
                                    <p class="italic text-gray-400">No description available</p>
                                <?php endif; ?>
                            </div>

                            <!-- Specification -->
                            <div class="text-sm">
                                <span class="text-gray-600">Spec:</span>
                                <?php if (!empty($product['specification']) && trim($product['specification']) !== ''): ?>
                                    <span class="text-gray-700"><?= htmlspecialchars($product['specification']) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">Not specified</span>
                                <?php endif; ?>
                            </div>

                            <!-- Link Date -->
                            <?php if ($product['is_linked']): ?>
                                <div class="text-xs text-gray-500 pt-2 border-t border-gray-100">
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php if (!empty($product['linked_at'])): ?>
                                        Linked <?= date('M j, Y g:i A', strtotime($product['linked_at'])) ?>
                                    <?php else: ?>
                                        <span class="italic">Link date not available</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Button -->
                        <div class="px-6 pb-6">
                            <?php if ($product['is_linked']): ?>
                                <button onclick="unlinkProduct(<?= $product['id'] ?>)" 
                                        class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center"
                                        id="btn-<?= $product['id'] ?>">
                                    <i class="fas fa-unlink mr-2"></i>Unlink Product
                                </button>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <!-- Primary Supplier Button -->
                                    <?php if ($product['has_primary'] == 0): ?>
                                        <button onclick="linkProduct(<?= $product['id'] ?>, 'primary')" 
                                                class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center"
                                                id="btn-primary-<?= $product['id'] ?>">
                                            <i class="fas fa-star mr-2"></i>Link as Primary Supplier
                                        </button>
                                    <?php else: ?>
                                        <button disabled 
                                                class="w-full bg-gray-300 text-gray-500 py-2 px-4 rounded-lg flex items-center justify-center cursor-not-allowed"
                                                title="This product already has a primary supplier">
                                            <i class="fas fa-star mr-2"></i>Primary Slot Taken
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Secondary Supplier Button -->
                                    <button onclick="linkProduct(<?= $product['id'] ?>, 'secondary')" 
                                            class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center"
                                            id="btn-secondary-<?= $product['id'] ?>">
                                        <i class="fas fa-link mr-2"></i>Link as Secondary Supplier
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-noble-primary"></div>
            <span class="text-gray-700">Processing...</span>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="hidden fixed top-4 right-4 z-50 bg-white border border-gray-200 rounded-lg shadow-lg p-4 max-w-sm">
        <div class="flex items-center">
            <div id="toast-icon" class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-3">
                <i id="toast-icon-i" class="text-white"></i>
            </div>
            <div class="flex-1">
                <p id="toast-message" class="text-sm font-medium text-gray-900"></p>
            </div>
            <button onclick="hideToast()" class="ml-3 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loading-overlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loading-overlay').classList.add('hidden');
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            const iconI = document.getElementById('toast-icon-i');
            const messageEl = document.getElementById('toast-message');
            
            messageEl.textContent = message;
            
            if (type === 'success') {
                icon.className = 'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-3 bg-green-500';
                iconI.className = 'fas fa-check text-white';
            } else {
                icon.className = 'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-3 bg-red-500';
                iconI.className = 'fas fa-times text-white';
            }
            
            toast.classList.remove('hidden');
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                hideToast();
            }, 3000);
        }

        function hideToast() {
            document.getElementById('toast').classList.add('hidden');
        }

        function linkProduct(productId, supplierType = 'secondary') {
            const buttonId = supplierType === 'primary' ? `btn-primary-${productId}` : `btn-secondary-${productId}`;
            const button = document.getElementById(buttonId);
            
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Linking...';
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=link_product&product_id=${productId}&supplier_type=${supplierType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Product linked successfully!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Error linking product. Please try again.', 'error');
                    if (button) {
                        button.disabled = false;
                        const iconClass = supplierType === 'primary' ? 'fas fa-star' : 'fas fa-link';
                        const text = supplierType === 'primary' ? 'Link as Primary Supplier' : 'Link as Secondary Supplier';
                        button.innerHTML = `<i class="${iconClass} mr-2"></i>${text}`;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
                if (button) {
                    button.disabled = false;
                    const iconClass = supplierType === 'primary' ? 'fas fa-star' : 'fas fa-link';
                    const text = supplierType === 'primary' ? 'Link as Primary Supplier' : 'Link as Secondary Supplier';
                    button.innerHTML = `<i class="${iconClass} mr-2"></i>${text}`;
                }
            });
        }

        function unlinkProduct(productId) {
            if (confirm('Are you sure you want to unlink this product from this supplier?')) {
                const button = document.getElementById(`btn-${productId}`);
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Unlinking...';
                
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=unlink_product&product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Product unlinked successfully!', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showToast(data.message || 'Error unlinking product. Please try again.', 'error');
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-unlink mr-2"></i>Unlink Product';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error. Please try again.', 'error');
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-unlink mr-2"></i>Unlink Product';
                });
            }
        }

        // Add hover effects
        document.querySelectorAll('[id^="product-card-"]').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>