<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}
$_SESSION['last_activity'] = time();

// Get parameters from URL
$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$supplier_name = isset($_GET['supplier_name']) ? $_GET['supplier_name'] : '';

if ($supplier_id <= 0) {
    header("Location: supplier_management.php");
    exit();
}

// Get supplier details
$supplier_query = "SELECT fullname, email FROM nobleaccount WHERE supplier_id = ?";
$stmt = $conn->prepare($supplier_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$supplier_result = $stmt->get_result();
$supplier_info = $supplier_result->fetch_assoc();

if (!$supplier_info) {
    header("Location: supplier_management.php");
    exit();
}

// Get supplier products with variants and sizes
$products_query = "SELECT 
    sp.id,
    sp.item_code,
    sp.product_name,
    sp.description,
    sp.category,
    sp.image,
    sp.status,
    sp.created_at,
    spv.id as variant_id,
    spv.color,
    spv.price,
    spv.image as variant_image,
    svs.id as size_id,
    svs.size,
    svs.stock
FROM supplier_products sp
LEFT JOIN supplier_product_variants spv ON sp.id = spv.product_id
LEFT JOIN supplier_variant_sizes svs ON spv.id = svs.variant_id
WHERE sp.supplier_id = ? AND sp.status = 'active'
ORDER BY sp.category, sp.product_name, spv.color, svs.size";

$stmt = $conn->prepare($products_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$products_result = $stmt->get_result();

// Organize products by grouping variants and sizes
$organized_products = [];
while ($row = $products_result->fetch_assoc()) {
    $product_id = $row['id'];
    if (!isset($organized_products[$product_id])) {
        $organized_products[$product_id] = [
            'id' => $row['id'],
            'item_code' => $row['item_code'],
            'product_name' => $row['product_name'],
            'description' => $row['description'],
            'category' => $row['category'],
            'image' => $row['image'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'variants' => []
        ];
    }
    
    if (!empty($row['variant_id'])) {
        $variant_id = $row['variant_id'];
        if (!isset($organized_products[$product_id]['variants'][$variant_id])) {
            $organized_products[$product_id]['variants'][$variant_id] = [
                'id' => $row['variant_id'],
                'color' => $row['color'],
                'price' => $row['price'],
                'image' => $row['variant_image'],
                'sizes' => []
            ];
        }
        
        if (!empty($row['size_id'])) {
            $organized_products[$product_id]['variants'][$variant_id]['sizes'][] = [
                'id' => $row['size_id'],
                'size' => $row['size'],
                'stock' => $row['stock']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($supplier_name); ?> - Product Catalog</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

    <div class="container mx-auto p-4">
        <!-- Navigation -->
        <div class="mb-6">
            <a href="supplier_management.php"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Supplier List
            </a>
            <a href="../supplier/supplier.php"
                class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600 ml-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4"></path>
                </svg>
                Manage Suppliers
            </a>
        </div>

        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($supplier_name); ?></h1>
                    <div class="flex flex-col gap-1 text-gray-600">
                        <p class="text-lg">Supplier ID: <span class="font-medium"><?php echo $supplier_id; ?></span></p>
                        <p class="text-lg">Contact: <span class="font-medium"><?php echo htmlspecialchars($supplier_info['email']); ?></span></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-full text-lg font-semibold">
                        <?php echo count($organized_products); ?> Products Available
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Last updated: <?php echo date('M j, Y'); ?></p>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Product Catalog</h2>

            <?php if (count($organized_products) > 0): ?>
                <div class="grid gap-8">
                    <?php 
                    $current_category = '';
                    foreach ($organized_products as $product): 
                        // Show category header if it's a new category
                        if ($current_category !== $product['category']):
                            $current_category = $product['category'];
                            if (!empty($current_category)):
                    ?>
                        <div class="border-t-4 border-blue-500 pt-6 mt-8 first:border-t-0 first:pt-0 first:mt-0">
                            <h3 class="text-xl font-bold text-gray-800 mb-6 bg-gray-50 px-4 py-3 rounded-lg border-l-4 border-blue-500">
                                <?php echo htmlspecialchars($current_category); ?>
                            </h3>
                        </div>
                    <?php 
                            endif;
                        endif; 
                    ?>
                    
                    <div class="border border-gray-200 rounded-xl p-8 hover:shadow-lg transition-shadow bg-white">
                        <div class="flex gap-6">
                            <!-- Product Image -->
                            <div class="w-32 h-32 flex-shrink-0">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                         class="w-full h-full object-cover rounded-xl border-2 border-gray-200 shadow-sm">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl border-2 border-gray-200 flex items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Product Details -->
                            <div class="flex-1">
                                <div class="flex items-center gap-4 mb-3">
                                    <h4 class="font-bold text-gray-900 text-2xl"><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                    <?php if (!empty($product['item_code'])): ?>
                                        <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-mono border"><?php echo htmlspecialchars($product['item_code']); ?></span>
                                    <?php endif; ?>
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-medium border border-green-200">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($product['description'])): ?>
                                    <p class="text-gray-600 text-base mb-6 leading-relaxed"><?php echo htmlspecialchars($product['description']); ?></p>
                                <?php endif; ?>
                                
                                <!-- Variants -->
                                <?php if (!empty($product['variants'])): ?>
                                    <div class="space-y-4">
                                        <h5 class="font-semibold text-gray-800 text-lg">Available Options:</h5>
                                        <?php foreach ($product['variants'] as $variant): ?>
                                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                                <div class="flex items-center gap-4 mb-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-700">Color:</span>
                                                        <span class="bg-white px-3 py-1 rounded-full border font-medium"><?php echo htmlspecialchars($variant['color']); ?></span>
                                                    </div>
                                                    <div class="font-bold text-green-600 text-xl">₱<?php echo number_format($variant['price'], 2); ?></div>
                                                </div>
                                                
                                                <!-- Sizes and Stock -->
                                                <?php if (!empty($variant['sizes'])): ?>
                                                    <div>
                                                        <span class="font-medium text-gray-700 text-sm mb-2 block">Available Sizes:</span>
                                                        <div class="flex flex-wrap gap-2">
                                                            <?php foreach ($variant['sizes'] as $size): ?>
                                                                <div class="bg-white border border-gray-300 rounded-lg px-4 py-2 text-sm shadow-sm">
                                                                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($size['size']); ?></span>
                                                                    <span class="text-gray-500 ml-2">(<?php echo $size['stock']; ?> available)</span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-6 text-sm mt-6 pt-4 border-t border-gray-200">
                                    <span class="text-gray-500">Added: <?php echo date('M j, Y', strtotime($product['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <!-- Action buttons -->
                            <div class="flex flex-col gap-3 ml-6">
                                <button class="bg-blue-500 text-white px-6 py-3 rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors shadow-sm">
                                    View Details
                                </button>
                                <button class="bg-green-500 text-white px-6 py-3 rounded-lg text-sm font-medium hover:bg-green-600 transition-colors shadow-sm">
                                    Add to Order
                                </button>
                                <button class="bg-gray-500 text-white px-6 py-3 rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors shadow-sm">
                                    Contact Supplier
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- No Products State -->
                <div class="text-center py-16">
                    <svg class="w-24 h-24 mx-auto mb-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707-.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <h3 class="text-2xl font-bold text-gray-700 mb-4">No Products Available</h3>
                    <p class="text-gray-600 mb-8 text-lg">This supplier hasn't added any products to their catalog yet.</p>
                    <div class="flex gap-4 justify-center">
                        <button class="bg-orange-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-orange-600 transition-colors">
                            Contact Supplier
                        </button>
                        <a href="supplier_management.php" class="bg-gray-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-gray-600 transition-colors">
                            Back to Suppliers
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Summary Footer -->
        <?php if (count($organized_products) > 0): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-600">
                        Showing <span class="font-semibold"><?php echo count($organized_products); ?></span> products from 
                        <span class="font-semibold"><?php echo htmlspecialchars($supplier_name); ?></span>
                    </p>
                </div>
                <div class="flex gap-3">
                    <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors">
                        Download Catalog
                    </button>
                    <button class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors">
                        Request Quote
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</body>

</html>