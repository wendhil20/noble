<?php
//link_products.php
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
    $variant_id = isset($_POST['variant_id']) ? intval($_POST['variant_id']) : null;
    $supplier_type = isset($_POST['supplier_type']) ? $_POST['supplier_type'] : 'secondary';
    $supplier_price = isset($_POST['supplier_price']) && !empty($_POST['supplier_price']) ? floatval($_POST['supplier_price']) : null;
    
    try {
        // Check if link already exists for this supplier and variant
        if ($variant_id) {
            $check_sql = "SELECT id, status, supplier_type FROM supp_link_products WHERE supplier_id = ? AND variant_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $supplier_id, $variant_id);
        } else {
            $check_sql = "SELECT id, status, supplier_type FROM supp_link_products WHERE supplier_id = ? AND product_id = ? AND variant_id IS NULL";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $supplier_id, $product_id);
        }
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            if ($existing['status'] === 'active') {
                // Already linked and active - update price if provided
                if ($supplier_price !== null) {
                    if ($variant_id) {
                        $update_price_sql = "UPDATE supp_link_products SET supplier_price = ?, updated_at = NOW() WHERE supplier_id = ? AND variant_id = ?";
                        $update_price_stmt = $conn->prepare($update_price_sql);
                        $update_price_stmt->bind_param("dii", $supplier_price, $supplier_id, $variant_id);
                    } else {
                        $update_price_sql = "UPDATE supp_link_products SET supplier_price = ?, updated_at = NOW() WHERE supplier_id = ? AND product_id = ? AND variant_id IS NULL";
                        $update_price_stmt = $conn->prepare($update_price_sql);
                        $update_price_stmt->bind_param("dii", $supplier_price, $supplier_id, $product_id);
                    }
                    $update_price_stmt->execute();
                    $update_price_stmt->close();
                }
                echo json_encode(['success' => true, 'message' => 'Product is already linked. Price updated.']);
                exit();
            } else {
                // Update status to active if link exists but inactive
                if ($variant_id) {
                    $update_sql = "UPDATE supp_link_products SET status = 'active', supplier_type = ?, supplier_price = ?, updated_at = NOW() WHERE supplier_id = ? AND variant_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("sdii", $supplier_type, $supplier_price, $supplier_id, $variant_id);
                } else {
                    $update_sql = "UPDATE supp_link_products SET status = 'active', supplier_type = ?, supplier_price = ?, updated_at = NOW() WHERE supplier_id = ? AND product_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("sdii", $supplier_type, $supplier_price, $supplier_id, $product_id);
                }
                $success = $update_stmt->execute();
                $update_stmt->close();
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Product linked successfully with price']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update product link: ' . $conn->error]);
                }
                exit();
            }
        } else {
            // Check if trying to set as primary and there's already a primary supplier for this product
            if ($supplier_type === 'primary') {
                if ($variant_id) {
                    $primary_check_sql = "SELECT sp.id, sl.business_name FROM supp_link_products sp 
                                        LEFT JOIN supplier_list sl ON sp.supplier_id = sl.id 
                                        WHERE sp.variant_id = ? AND sp.supplier_type = 'primary' AND sp.status = 'active'";
                    $primary_check_stmt = $conn->prepare($primary_check_sql);
                    $primary_check_stmt->bind_param("i", $variant_id);
                } else {
                    $primary_check_sql = "SELECT sp.id, sl.business_name FROM supp_link_products sp 
                                        LEFT JOIN supplier_list sl ON sp.supplier_id = sl.id 
                                        WHERE sp.product_id = ? AND sp.variant_id IS NULL AND sp.supplier_type = 'primary' AND sp.status = 'active'";
                    $primary_check_stmt = $conn->prepare($primary_check_sql);
                    $primary_check_stmt->bind_param("i", $product_id);
                }
                $primary_check_stmt->execute();
                $existing_primary = $primary_check_stmt->get_result()->fetch_assoc();
                $primary_check_stmt->close();
                
                if ($existing_primary) {
                    $supplier_name = $existing_primary['business_name'] ? $existing_primary['business_name'] : 'Another supplier';
                    echo json_encode(['success' => false, 'message' => "This product already has a primary supplier ({$supplier_name}). Only one primary supplier is allowed per product."]);
                    exit();
                }
            }
            
            // Create new link with price (only store in supp_link_products)
            if ($variant_id) {
                $insert_sql = "INSERT INTO supp_link_products (supplier_id, product_id, variant_id, supplier_type, supplier_price, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iiisd", $supplier_id, $product_id, $variant_id, $supplier_type, $supplier_price);
            } else {
                $insert_sql = "INSERT INTO supp_link_products (supplier_id, product_id, supplier_type, supplier_price, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iisd", $supplier_id, $product_id, $supplier_type, $supplier_price);
            }
            $success = $insert_stmt->execute();
            
            if ($success) {
                $insert_stmt->close();
                echo json_encode(['success' => true, 'message' => 'Product linked successfully as ' . $supplier_type . ' supplier' . ($supplier_price ? ' with price ₱' . number_format($supplier_price, 2) : '')]);
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
            $variant_id = isset($_POST['variant_id']) ? intval($_POST['variant_id']) : null;
            
            try {
                if ($variant_id) {
                    $update_sql = "UPDATE supp_link_products SET status = 'inactive', updated_at = NOW() WHERE supplier_id = ? AND variant_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $supplier_id, $variant_id);
                } else {
                    $update_sql = "UPDATE supp_link_products SET status = 'inactive', updated_at = NOW() WHERE supplier_id = ? AND product_id = ? AND variant_id IS NULL";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $supplier_id, $product_id);
                }
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

    if ($_POST['action'] === 'get_single_variant' && isset($_POST['variant_id']) && isset($_POST['product_id'])) {
            $variant_id = intval($_POST['variant_id']);
            $product_id = intval($_POST['product_id']);
            
            try {
                $variant_sql = "
                    SELECT pv.*, 
                           CASE WHEN sp.status = 'active' THEN 1 ELSE 0 END as is_linked,
                           sp.supplier_type,
                           sp.supplier_price,
                           sp.created_at as linked_at,
                           (SELECT COUNT(*) FROM supp_link_products sp2 WHERE sp2.variant_id = pv.id AND sp2.supplier_type = 'primary' AND sp2.status = 'active') as has_primary
                    FROM product_variants pv
                    LEFT JOIN supp_link_products sp ON pv.id = sp.variant_id AND sp.supplier_id = ? AND sp.status = 'active'
                    WHERE pv.id = ? AND pv.product_id = ?
                ";
                
                $variant_stmt = $conn->prepare($variant_sql);
                $variant_stmt->bind_param("iii", $supplier_id, $variant_id, $product_id);
                $variant_stmt->execute();
                $variant_result = $variant_stmt->get_result();
                $variant = $variant_result->fetch_assoc();
                $variant_stmt->close();
                
                if ($variant) {
                    echo json_encode(['success' => true, 'variant' => $variant]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Variant not found']);
                }
                exit();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching variant: ' . $e->getMessage()]);
                exit();
            }
        }


    if ($_POST['action'] === 'get_product_counts' && isset($_POST['product_id'])) {
            $product_id = intval($_POST['product_id']);
            
            try {
                $counts_sql = "
                    SELECT 
                        (SELECT COUNT(*) FROM supp_link_products sp 
                         WHERE sp.product_id = ? AND sp.supplier_id = ? 
                         AND sp.status = 'active' AND sp.variant_id IS NOT NULL) as linked_count,
                        (SELECT COUNT(*) FROM product_variants pv 
                         WHERE pv.product_id = ?) as total_count
                ";
                
                $counts_stmt = $conn->prepare($counts_sql);
                $counts_stmt->bind_param("iii", $product_id, $supplier_id, $product_id);
                $counts_stmt->execute();
                $result = $counts_stmt->get_result();
                $counts = $result->fetch_assoc();
                $counts_stmt->close();
                
                echo json_encode([
                    'success' => true, 
                    'linked_count' => $counts['linked_count'],
                    'total_count' => $counts['total_count']
                ]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching counts: ' . $e->getMessage()]);
                exit();
            }
        }

        if ($_POST['action'] === 'get_variants' && isset($_POST['product_id'])) {
            $product_id = intval($_POST['product_id']);
            
            try {
                $variants_sql = "
                    SELECT pv.*, 
                           CASE WHEN sp.status = 'active' THEN 1 ELSE 0 END as is_linked,
                           sp.supplier_type,
                           sp.supplier_price,
                           sp.created_at as linked_at,
                           (SELECT COUNT(*) FROM supp_link_products sp2 WHERE sp2.variant_id = pv.id AND sp2.supplier_type = 'primary' AND sp2.status = 'active') as has_primary
                    FROM product_variants pv
                    LEFT JOIN supp_link_products sp ON pv.id = sp.variant_id AND sp.supplier_id = ? AND sp.status = 'active'
                    WHERE pv.product_id = ?
                    ORDER BY pv.namevariant ASC, pv.color ASC, pv.size ASC
                ";
                
                $variants_stmt = $conn->prepare($variants_sql);
                $variants_stmt->bind_param("ii", $supplier_id, $product_id);
                $variants_stmt->execute();
                $variants_result = $variants_stmt->get_result();
                $variants = $variants_result->fetch_all(MYSQLI_ASSOC);
                $variants_stmt->close();
                
                echo json_encode(['success' => true, 'variants' => $variants]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching variants: ' . $e->getMessage()]);
                exit();
            }
        }
    
    // If we get here, invalid action
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Handle search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Build WHERE clause for products search
$where_conditions = [];
$params = [];
$types = "";

// Add product filter if specified
if ($filter_product_id > 0) {
    $where_conditions[] = "p.id = ?";
    $params[] = $filter_product_id;
    $types .= "i";
}

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
           (SELECT COUNT(*) FROM supp_link_products sp2 
            WHERE sp2.product_id = p.id AND sp2.status = 'active' 
            AND sp2.supplier_id = ? AND sp2.variant_id IS NOT NULL) as linked_variants_count,
           (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) as total_variants_count
    FROM products p
    $where_clause
    ORDER BY p.product_name ASC
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
        <a href="view_supplier.php?id=<?= $supplier_id ?>" class="text-noble-primary hover:text-blue-700 transition-colors">
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
        <?php if ($filter_product_id > 0): ?>
            <input type="hidden" name="product_id" value="<?= $filter_product_id ?>">
        <?php endif; ?>
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
        <?php if ($filter_product_id > 0): ?>
            <a href="?supplier_id=<?= $supplier_id ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-3 rounded-lg transition-colors duration-200">
                <i class="fas fa-times mr-1"></i>View All Products
            </a>
        <?php else: ?>
            <a href="?supplier_id=<?= $supplier_id ?>" class="text-gray-600 hover:text-gray-800 px-4 py-3">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Filter Indicator -->
<?php if ($filter_product_id > 0): 
    // Get the filtered product name
    $filtered_product = array_filter($products, function($p) use ($filter_product_id) {
        return $p['id'] == $filter_product_id;
    });
    $filtered_product = reset($filtered_product);
?>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-filter text-blue-500 mr-3"></i>
                <div>
                    <p class="text-blue-900 font-medium">Filtering by product:</p>
                    <p class="text-blue-700"><?= htmlspecialchars($filtered_product['product_name'] ?? 'Unknown Product') ?></p>
                </div>
            </div>
            <a href="?supplier_id=<?= $supplier_id ?>" 
               class="text-blue-600 hover:text-blue-800 underline">
                Clear filter
            </a>
        </div>
    </div>
<?php endif; ?>

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
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200" 
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
                
                <!-- Variants Status -->
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <i class="fas fa-layer-group mr-1"></i>
                        <?= $product['total_variants_count'] ?> Variants
                    </span>
                    <?php if ($product['linked_variants_count'] > 0): ?>
                        <span class="linked-variants-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-link mr-1"></i>
                            <?= $product['linked_variants_count'] ?> Linked
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details -->
    <div class="p-6 space-y-3">
        <!-- Description -->
        <div class="text-sm text-gray-600">
            <?php if (!empty($product['description']) && trim($product['description']) !== ''): ?>
                <p class="line-clamp-2"><?= htmlspecialchars($product['description']) ?></p>
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
    </div>

    <!-- Action Button -->
    <div class="px-6 pb-6">
        <button onclick="showVariantsModal(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['product_name'])) ?>')" 
                class="w-full bg-noble-primary hover:bg-blue-700 text-white py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center">
            <i class="fas fa-list mr-2"></i>View & Link Variants
        </button>
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

    <!-- Price Modal -->
<div id="priceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-tag text-blue-500 mr-2"></i>
                    <span id="modalAction">Set</span> Supplier Price
                </h3>
                <button onclick="closePriceModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">Product:</p>
                <p class="font-medium text-gray-900" id="modalProductName"></p>
            </div>
            
            <!-- Show Original Price Reference -->
            <div id="originalPriceReference" class="mb-4 bg-gray-50 border border-gray-200 rounded-lg p-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>Original Price (Reference):
                    </span>
                    <span class="font-semibold text-gray-900" id="modalOriginalPrice">₱0.00</span>
                </div>
            </div>
            
            <div class="mb-6">
                <label for="supplierPriceInput" class="block text-sm font-medium text-gray-700 mb-2">
                    Your Supplier Price (₱) <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₱</span>
                    <input type="number" 
                           id="supplierPriceInput" 
                           step="0.01" 
                           min="0"
                           placeholder="0.00"
                           class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-lightbulb mr-1"></i>
                    Enter the price this supplier offers for this product
                </p>
            </div>
            
            <input type="hidden" id="modalProductId">
            <input type="hidden" id="modalVariantId">
            <input type="hidden" id="modalSupplierType">
            <input type="hidden" id="modalOriginalPriceValue">
            
            <div class="flex space-x-3">
                <button onclick="closePriceModal()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 px-4 rounded-lg transition-colors duration-200">
                    Cancel
                </button>
                <button onclick="submitPriceAndLink()" 
                        id="submitPriceBtn"
                        class="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center">
                    <i class="fas fa-check mr-2"></i>
                    <span id="submitBtnText">Link Product</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Variants Modal -->
<div id="variantsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-layer-group text-blue-500 mr-2"></i>
                    Product Variants - <span id="variantsModalProductName"></span>
                </h3>
                <button onclick="closeVariantsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6">
            <div id="variantsLoadingSpinner" class="text-center py-12">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-noble-primary mx-auto mb-4"></div>
                <p class="text-gray-600">Loading variants...</p>
            </div>
            
            <div id="variantsContent" class="hidden space-y-4">
                <!-- Variants will be loaded here dynamically -->
            </div>
            
            <div id="variantsEmptyState" class="hidden text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-layer-group text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No variants found</h3>
                <p class="text-gray-600">This product doesn't have any variants yet.</p>
            </div>
        </div>
    </div>
</div>

    <script>
        // Toast and Loading Functions
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
            
            setTimeout(() => {
                hideToast();
            }, 3000);
        }

        function hideToast() {
            document.getElementById('toast').classList.add('hidden');
        }

        function closePriceModal() {
            document.getElementById('priceModal').classList.add('hidden');
        }

        function submitPriceAndLink() {
            const productId = document.getElementById('modalProductId').value;
            const variantId = document.getElementById('modalVariantId').value;
            const supplierType = document.getElementById('modalSupplierType').value;
            const supplierPrice = document.getElementById('supplierPriceInput').value;
            const submitBtn = document.getElementById('submitPriceBtn');
            
            if (!supplierPrice || parseFloat(supplierPrice) <= 0) {
                showToast('Please enter a valid price', 'error');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=link_product&product_id=${productId}${variantId ? '&variant_id=' + variantId : ''}&supplier_type=${supplierType}&supplier_price=${supplierPrice}`
            })
            .then(response => response.json())
            .then(data => {
    if (data.success) {
        showToast(data.message || 'Operation successful!', 'success');
        closePriceModal();
        
        // Just reload the page after showing success message
        setTimeout(() => {
            location.reload();
        }, 1500);
    } else {
                    showToast(data.message || 'Error processing request. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i><span id="submitBtnText">Link Product</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i><span id="submitBtnText">Link Product</span>';
            });
        }

        // Close modal when clicking outside
        document.getElementById('priceModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePriceModal();
            }
        });

        // Allow Enter key to submit
        document.getElementById('supplierPriceInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitPriceAndLink();
            }
        });
        // Variants Modal Functions
function showVariantsModal(productId, productName) {
    document.getElementById('variantsModalProductName').textContent = productName;
    document.getElementById('variantsModal').classList.remove('hidden');
    document.getElementById('variantsLoadingSpinner').classList.remove('hidden');
    document.getElementById('variantsContent').classList.add('hidden');
    document.getElementById('variantsEmptyState').classList.add('hidden');
    
    // Clear previous content
    document.getElementById('variantsContent').innerHTML = '';
    
    // Fetch variants
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_variants&product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('variantsLoadingSpinner').classList.add('hidden');
        
        if (data.success && data.variants && data.variants.length > 0) {
            displayVariants(data.variants, productId);
        } else {
            document.getElementById('variantsEmptyState').classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('variantsLoadingSpinner').classList.add('hidden');
        document.getElementById('variantsEmptyState').classList.remove('hidden');
    });
}

function closeVariantsModal() {
    document.getElementById('variantsModal').classList.add('hidden');
}

function displayVariants(variants, productId) {
    const content = document.getElementById('variantsContent');
    content.innerHTML = '';
    
    variants.forEach(variant => {
        const variantCard = createVariantCard(variant, productId);
        content.appendChild(variantCard);
    });
    
    content.classList.remove('hidden');
}

function createVariantCard(variant, productId) {
    const card = document.createElement('div');
    card.className = 'bg-gray-50 rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow duration-200';
    card.id = `variant-card-${variant.id}`;
    
    const isLinked = variant.is_linked == 1;
    const hasPrimary = variant.has_primary > 0;
    
    card.innerHTML = `
        <div class="flex items-start space-x-4">
            <!-- Variant Image -->
            <div class="flex-shrink-0">
                ${variant.image ? `
                    <img src="../../${variant.image}" 
                         alt="Variant image"
                         class="w-20 h-20 rounded-lg object-cover border-2 border-gray-200">
                ` : `
                    <div class="w-20 h-20 rounded-lg bg-gray-200 flex items-center justify-center border-2 border-gray-300">
                        <i class="fas fa-image text-gray-400 text-xl"></i>
                    </div>
                `}
            </div>
            
            <!-- Variant Info -->
            <div class="flex-1">
                <div class="flex items-start justify-between">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-1">
                            ${variant.namevariant || 'Unnamed Variant'}
                        </h4>
                        <div class="flex flex-wrap gap-2 mb-2">
                            ${variant.color ? `
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-purple-100 text-purple-800">
                                    <i class="fas fa-palette mr-1"></i>${variant.color}
                                </span>
                            ` : ''}
                            ${variant.size ? `
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-indigo-100 text-indigo-800">
                                    <i class="fas fa-ruler mr-1"></i>${variant.size}
                                </span>
                            ` : ''}
                            ${isLinked ? `
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium ${variant.supplier_type === 'primary' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                    <i class="fas fa-link mr-1"></i>Linked (${variant.supplier_type})
                                </span>
                            ` : ''}
                        </div>
                    </div>
                </div>
                
                <!-- Variant Details Grid -->
                <div class="grid grid-cols-2 gap-3 mt-3 text-sm">
                    <div>
                        <span class="text-gray-600">Original Price:</span>
                        <span class="font-semibold text-gray-900">₱${parseFloat(variant.original_price || 0).toFixed(2)}</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Selling Price:</span>
                        <span class="font-semibold text-green-600">₱${parseFloat(variant.price || 0).toFixed(2)}</span>
                    </div>
                    ${variant.width || variant.height || variant.length ? `
                        <div>
                            <span class="text-gray-600">Dimensions:</span>
                            <span class="font-semibold text-gray-900">
                                ${variant.width || 0} × ${variant.height || 0} × ${variant.length || 0} ${variant.dimension_unit || 'cm'}
                            </span>
                        </div>
                    ` : ''}
                    ${variant.weight ? `
                        <div>
                            <span class="text-gray-600">Weight:</span>
                            <span class="font-semibold text-gray-900">${variant.weight} ${variant.weight_unit || 'kg'}</span>
                        </div>
                    ` : ''}
                </div>
                
                <!-- Supplier Price Display (if linked) -->
                ${isLinked && variant.supplier_price ? `
                    <div class="mt-3 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-blue-700 font-medium">Your Supplier Price:</span>
                            <span class="text-lg font-bold text-blue-900">₱${parseFloat(variant.supplier_price).toFixed(2)}</span>
                        </div>
                    </div>
                ` : ''}
                
                <!-- Action Buttons -->
                <div class="mt-4 flex gap-2">
                    ${isLinked ? `
                        <button 
                                data-action="update-price"
                                data-product-id="${productId}"
                                data-variant-id="${variant.id}"
                                data-variant-name="${(variant.namevariant || 'Variant').replace(/"/g, '&quot;')}"
                                data-current-price="${variant.supplier_price || 0}"
                                data-original-price="${variant.original_price || 0}"
                                class="variant-action-btn flex-1 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg transition-colors duration-200 text-sm">
                            <i class="fas fa-edit mr-1"></i>Update Price
                        </button>
                        <button 
                                data-action="unlink"
                                data-product-id="${productId}"
                                data-variant-id="${variant.id}"
                                id="btn-variant-${variant.id}"
                                class="variant-action-btn flex-1 bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-lg transition-colors duration-200 text-sm">
                            <i class="fas fa-unlink mr-1"></i>Unlink
                        </button>
                    ` : `
                        ${!hasPrimary ? `
                            <button 
                                    data-action="link"
                                    data-product-id="${productId}"
                                    data-variant-id="${variant.id}"
                                    data-variant-name="${(variant.namevariant || 'Variant').replace(/"/g, '&quot;')}"
                                    data-supplier-type="primary"
                                    data-original-price="${variant.original_price || 0}"
                                    class="variant-action-btn flex-1 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg transition-colors duration-200 text-sm">
                                <i class="fas fa-star mr-1"></i>Link as Primary
                            </button>
                        ` : `
                            <button disabled 
                                    class="flex-1 bg-gray-300 text-gray-500 py-2 px-4 rounded-lg text-sm cursor-not-allowed">
                                <i class="fas fa-star mr-1"></i>Primary Taken
                            </button>
                        `}
                        <button 
                                data-action="link"
                                data-product-id="${productId}"
                                data-variant-id="${variant.id}"
                                data-variant-name="${(variant.namevariant || 'Variant').replace(/"/g, '&quot;')}"
                                data-supplier-type="secondary"
                                data-original-price="${variant.original_price || 0}"
                                class="variant-action-btn flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg transition-colors duration-200 text-sm">
                            <i class="fas fa-link mr-1"></i>Link as Secondary
                        </button>
                    `}
                </div>
            </div>
        </div>
    `;
    
    return card;
}

function showLinkModalForVariant(productId, variantId, variantName, supplierType, originalPrice) {
    // Add null checks for all elements
    const modalProductId = document.getElementById('modalProductId');
    const modalVariantId = document.getElementById('modalVariantId');
    const modalProductName = document.getElementById('modalProductName');
    const modalSupplierType = document.getElementById('modalSupplierType');
    const supplierPriceInput = document.getElementById('supplierPriceInput');
    const modalAction = document.getElementById('modalAction');
    const submitBtnText = document.getElementById('submitBtnText');
    const modalOriginalPriceValue = document.getElementById('modalOriginalPriceValue');
    const modalOriginalPrice = document.getElementById('modalOriginalPrice');
    const priceModal = document.getElementById('priceModal');
    
    // Check if all elements exist
    if (!modalProductId || !modalVariantId || !modalProductName || !modalSupplierType || 
        !supplierPriceInput || !modalAction || !submitBtnText || !modalOriginalPriceValue || 
        !modalOriginalPrice || !priceModal) {
        console.error('Modal elements not found!');
        showToast('Error: Modal elements not found. Please refresh the page.', 'error');
        return;
    }
    
    modalProductId.value = productId;
    modalVariantId.value = variantId;
    modalProductName.textContent = variantName;
    modalSupplierType.value = supplierType;
    supplierPriceInput.value = '';
    modalAction.textContent = 'Set';
    submitBtnText.textContent = 'Link Variant';
    modalOriginalPriceValue.value = originalPrice;
    modalOriginalPrice.textContent = '₱' + parseFloat(originalPrice).toFixed(2);
    
    const refDiv = document.getElementById('originalPriceReference');
    if (refDiv) {
        if (originalPrice > 0) {
            refDiv.classList.remove('hidden');
        } else {
            refDiv.classList.add('hidden');
        }
    }
    
    priceModal.classList.remove('hidden');
    setTimeout(() => {
        if (supplierPriceInput) {
            supplierPriceInput.focus();
        }
    }, 100);
}

function showPriceModalForVariant(productId, variantId, variantName, currentPrice, originalPrice) {
    // Add null checks for all elements
    const modalProductId = document.getElementById('modalProductId');
    const modalVariantId = document.getElementById('modalVariantId');
    const modalProductName = document.getElementById('modalProductName');
    const modalSupplierType = document.getElementById('modalSupplierType');
    const supplierPriceInput = document.getElementById('supplierPriceInput');
    const modalAction = document.getElementById('modalAction');
    const submitBtnText = document.getElementById('submitBtnText');
    const modalOriginalPriceValue = document.getElementById('modalOriginalPriceValue');
    const modalOriginalPrice = document.getElementById('modalOriginalPrice');
    const priceModal = document.getElementById('priceModal');
    
    // Check if all elements exist
    if (!modalProductId || !modalVariantId || !modalProductName || !modalSupplierType || 
        !supplierPriceInput || !modalAction || !submitBtnText || !modalOriginalPriceValue || 
        !modalOriginalPrice || !priceModal) {
        console.error('Modal elements not found!');
        showToast('Error: Modal elements not found. Please refresh the page.', 'error');
        return;
    }
    
    modalProductId.value = productId;
    modalVariantId.value = variantId;
    modalProductName.textContent = variantName;
    modalSupplierType.value = 'update';
    supplierPriceInput.value = currentPrice || '';
    modalAction.textContent = 'Update';
    submitBtnText.textContent = 'Update Price';
    modalOriginalPriceValue.value = originalPrice;
    modalOriginalPrice.textContent = '₱' + parseFloat(originalPrice).toFixed(2);
    
    const refDiv = document.getElementById('originalPriceReference');
    if (refDiv) {
        if (originalPrice > 0) {
            refDiv.classList.remove('hidden');
        } else {
            refDiv.classList.add('hidden');
        }
    }
    
    priceModal.classList.remove('hidden');
    setTimeout(() => {
        if (supplierPriceInput) {
            supplierPriceInput.focus();
        }
    }, 100);
}

function unlinkVariant(productId, variantId) {
    if (confirm('Are you sure you want to unlink this variant from this supplier? The price data will be preserved.')) {
        const button = document.getElementById(`btn-variant-${variantId}`);
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Unlinking...';
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=unlink_product&product_id=${productId}&variant_id=${variantId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Variant unlinked successfully!', 'success');
                
                // Just reload the page after showing success message
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showToast(data.message || 'Error unlinking variant. Please try again.', 'error');
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-unlink mr-1"></i>Unlink';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error. Please try again.', 'error');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-unlink mr-1"></i>Unlink';
        });
    }
}

// Add this new function here:
function updateProductCardCounts(productId) {
    // Fetch updated counts for the product
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_product_counts&product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the badge on the product card
            const productCard = document.getElementById(`product-card-${productId}`);
            if (productCard) {
                const linkedBadge = productCard.querySelector('.linked-variants-badge');
                if (data.linked_count > 0) {
                    if (linkedBadge) {
                        linkedBadge.innerHTML = `<i class="fas fa-link mr-1"></i>${data.linked_count} Linked`;
                    } else {
                        // Create the badge if it doesn't exist
                        const variantsStatusDiv = productCard.querySelector('.flex.items-center.space-x-2');
                        if (variantsStatusDiv) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'linked-variants-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
                            newBadge.innerHTML = `<i class="fas fa-link mr-1"></i>${data.linked_count} Linked`;
                            variantsStatusDiv.appendChild(newBadge);
                        }
                    }
                } else {
                    // Remove the badge if no variants are linked
                    if (linkedBadge) {
                        linkedBadge.remove();
                    }
                }
            }
        }
    })
    .catch(error => {
        console.error('Error updating counts:', error);
    });
}


// Add this new function:
function refreshSingleVariant(productId, variantId) {
    // Fetch the updated variant data
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_single_variant&product_id=${productId}&variant_id=${variantId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.variant) {
            // Find and replace the variant card
            const oldCard = document.getElementById(`variant-card-${variantId}`);
            if (oldCard) {
                const newCard = createVariantCard(data.variant, productId);
                oldCard.replaceWith(newCard);
            }
        }
    })
    .catch(error => {
        console.error('Error refreshing variant:', error);
    });
}

// Add this NEW function here:
function refreshSingleVariant(productId, variantId) {
    console.log('Refreshing variant:', variantId); // Debug log
    
    // Fetch the updated variant data
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_single_variant&product_id=${productId}&variant_id=${variantId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Variant data received:', data); // Debug log
        
        if (data.success && data.variant) {
            // Find the old variant card
            const oldCard = document.getElementById(`variant-card-${variantId}`);
            if (oldCard) {
                // Create new card with updated data
                const newCard = createVariantCard(data.variant, productId);
                
                // Replace the old card with the new one
                oldCard.parentNode.replaceChild(newCard, oldCard);
                
                console.log('Variant card updated successfully'); // Debug log
            } else {
                console.error('Could not find variant card:', `variant-card-${variantId}`);
            }
        } else {
            console.error('Failed to fetch variant:', data.message);
        }
    })
    .catch(error => {
        console.error('Error refreshing variant:', error);
    });
}

// Event delegation for variant action buttons
document.addEventListener('click', function(e) {
    const button = e.target.closest('.variant-action-btn');
    if (!button) return;
    
    const action = button.getAttribute('data-action');
    const productId = parseInt(button.getAttribute('data-product-id'));
    const variantId = parseInt(button.getAttribute('data-variant-id'));
    
    if (action === 'link') {
        const variantName = button.getAttribute('data-variant-name');
        const supplierType = button.getAttribute('data-supplier-type');
        const originalPrice = parseFloat(button.getAttribute('data-original-price'));
        
        showLinkModalForVariant(productId, variantId, variantName, supplierType, originalPrice);
    } else if (action === 'update-price') {
        const variantName = button.getAttribute('data-variant-name');
        const currentPrice = parseFloat(button.getAttribute('data-current-price'));
        const originalPrice = parseFloat(button.getAttribute('data-original-price'));
        
        showPriceModalForVariant(productId, variantId, variantName, currentPrice, originalPrice);
    } else if (action === 'unlink') {
        unlinkVariant(productId, variantId);
    }
});

// Close variants modal when clicking outside
document.getElementById('variantsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeVariantsModal();
    }
});
    </script>
</body>
</html>