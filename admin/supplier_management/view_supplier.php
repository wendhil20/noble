<?php
//view_supplier.php
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
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$supplier_id) {
    header("Location: supplier_directory.php");
    exit();
}

// Get supplier information
$supplier_sql = "SELECT * FROM supplier_list WHERE id = ?";
$supplier_stmt = $conn->prepare($supplier_sql);
$supplier_stmt->bind_param("i", $supplier_id);
$supplier_stmt->execute();
$supplier_result = $supplier_stmt->get_result();
$supplier = $supplier_result->fetch_assoc();
$supplier_stmt->close();

if (!$supplier) {
    header("Location: supplier_directory.php?error=supplier_not_found");
    exit();
}

// Handle AJAX request for variants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_variants') {
    header('Content-Type: application/json');
    $product_id = intval($_POST['product_id']);
    
    $variants_sql = "SELECT pv.*, 
                     CASE WHEN slp.status = 'active' THEN 1 ELSE 0 END as is_linked,
                     slp.supplier_type,
                     slp.supplier_price
                     FROM product_variants pv
                     LEFT JOIN supp_link_products slp ON pv.id = slp.variant_id 
                         AND slp.supplier_id = ? AND slp.status = 'active'
                     WHERE pv.product_id = ?
                     ORDER BY pv.namevariant ASC, pv.color ASC, pv.size ASC";
    
    $variants_stmt = $conn->prepare($variants_sql);
    $variants_stmt->bind_param("ii", $supplier_id, $product_id);
    $variants_stmt->execute();
    $variants_result = $variants_stmt->get_result();
    $variants = $variants_result->fetch_all(MYSQLI_ASSOC);
    $variants_stmt->close();
    
    echo json_encode(['success' => true, 'variants' => $variants]);
    exit();
}

// Get linked products for this supplier (now getting unique products with variant counts)
$products_sql = "SELECT p.*, 
                 COUNT(DISTINCT CASE WHEN slp.status = 'active' AND slp.variant_id IS NOT NULL THEN slp.variant_id END) as linked_variants_count,
                 COUNT(DISTINCT pv.id) as total_variants_count,
                 MIN(slp.created_at) as linked_date
                 FROM products p
                 INNER JOIN supp_link_products slp ON p.id = slp.product_id
                 LEFT JOIN product_variants pv ON p.id = pv.product_id
                 WHERE slp.supplier_id = ? AND slp.status = 'active'
                 GROUP BY p.id
                 ORDER BY p.product_name ASC";

$products_stmt = $conn->prepare($products_sql);
$products_stmt->bind_param("i", $supplier_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$linked_products = $products_result->fetch_all(MYSQLI_ASSOC);
$products_stmt->close();

// Count linked products
$active_products = count($linked_products);
$total_linked_variants = array_sum(array_column($linked_products, 'linked_variants_count'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($supplier['business_name']) ?> - Supplier Details</title>
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
        <!-- Back Button -->
        <div class="mb-6">
            <a href="suppliers_list.php" class="inline-flex items-center text-noble-primary hover:text-blue-700 transition-colors duration-200">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Supplier Directory
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-400 mr-3"></i>
                    <p class="text-green-800"><?= htmlspecialchars($_GET['success']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                    <p class="text-red-800"><?= htmlspecialchars($_GET['error']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Supplier Information Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
            <div class="p-8">
                <div class="flex items-start space-x-6">
                    <!-- Logo Section -->
                    <div class="flex-shrink-0">
                        <?php 
                        $logo_path = !empty($supplier['logo_path']) ? '../../uploads/supplier_logos/' . basename($supplier['logo_path']) : '';
                        $logo_exists = !empty($logo_path) && file_exists($logo_path);
                        
                        if ($logo_exists): ?>
                            <img src="<?= htmlspecialchars($logo_path) ?>" 
                                 alt="<?= htmlspecialchars($supplier['business_name']) ?> logo"
                                 class="w-20 h-20 rounded-xl object-cover border-2 border-gray-200">
                        <?php else: 
                            // Create acronym from business name
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
                            
                            // Generate a consistent color
                            $colors = [
                                'bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-pink-500', 
                                'bg-yellow-500', 'bg-indigo-500', 'bg-red-500', 'bg-teal-500'
                            ];
                            $color_index = abs(crc32($supplier['business_name'])) % count($colors);
                            $bg_color = $colors[$color_index];
                        ?>
                            <div class="w-20 h-20 rounded-xl <?= $bg_color ?> flex items-center justify-center text-white font-bold text-xl border-2 border-gray-200">
                                <?= htmlspecialchars($acronym) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Business Information -->
                    <div class="flex-1">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                                    <?= htmlspecialchars($supplier['business_name']) ?>
                                </h1>
                                <div class="flex items-center space-x-4 mb-3">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                        <?= $supplier['business_type'] == 'Manufacturer' ? 'bg-blue-100 text-blue-800' :
                                           ($supplier['business_type'] == 'Wholesaler' ? 'bg-green-100 text-green-800' :
                                           ($supplier['business_type'] == 'Distributor' ? 'bg-purple-100 text-purple-800' :
                                           ($supplier['business_type'] == 'Retailer' ? 'bg-yellow-100 text-yellow-800' :
                                           'bg-gray-100 text-gray-800'))) ?>">
                                        <?= htmlspecialchars($supplier['business_type']) ?>
                                    </span>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                        <?= $supplier['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <span class="w-2 h-2 rounded-full mr-2 
                                            <?= $supplier['status'] == 'active' ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                                        <?= ucfirst($supplier['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
    <a href="edit_supplier.php?edit_id=<?= $supplier['id'] ?>" 
       class="bg-noble-primary hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 inline-flex items-center">
        <i class="fas fa-edit mr-2"></i>Edit Supplier
    </a>
    <a href="link_products.php?supplier_id=<?= $supplier['id'] ?>" 
       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 inline-flex items-center">
        <i class="fas fa-link mr-2"></i>Add Products
    </a>
    <!-- Status Toggle Button -->
    <button onclick="toggleSupplierStatus(<?= $supplier['id'] ?>, '<?= $supplier['status'] ?>', '<?= htmlspecialchars($supplier['business_name'], ENT_QUOTES) ?>')"
            class="<?= $supplier['status'] == 'active' ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600' ?> text-white px-4 py-2 rounded-lg transition-colors duration-200 inline-flex items-center">
        <i class="fas fa-<?= $supplier['status'] == 'active' ? 'pause' : 'play' ?> mr-2"></i>
        <?= $supplier['status'] == 'active' ? 'Deactivate' : 'Activate' ?>
    </button>
</div>
                        </div>

                        <!-- Contact Information Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <i class="fas fa-user text-gray-400 w-5 text-center mr-4"></i>
                                    <div>
                                        <p class="font-medium text-gray-900"><?= !empty($supplier['primary_contact_name']) ? htmlspecialchars($supplier['primary_contact_name']) : 'No contact name' ?></p>
                                        <p class="text-sm text-gray-600"><?= !empty($supplier['job_title']) ? htmlspecialchars($supplier['job_title']) : 'No job title specified' ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center">
                                    <i class="fas fa-envelope text-gray-400 w-5 text-center mr-4"></i>
                                    <?php if (!empty($supplier['email_address'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($supplier['email_address']) ?>" 
                                           class="text-noble-primary hover:text-blue-700 hover:underline">
                                            <?= htmlspecialchars($supplier['email_address']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500 italic">No email address</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <i class="fas fa-phone text-gray-400 w-5 text-center mr-4"></i>
                                    <?php if (!empty($supplier['phone_number'])): ?>
                                        <a href="tel:<?= htmlspecialchars($supplier['phone_number']) ?>" 
                                           class="text-gray-700 hover:text-noble-primary">
                                            <?= htmlspecialchars($supplier['phone_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500 italic">No phone number</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt text-gray-400 w-5 text-center mr-4"></i>
                                    <span class="text-gray-700"><?= !empty($supplier['country_region']) ? htmlspecialchars($supplier['country_region']) : 'No location specified' ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Info -->
                        <div class="mt-6 pt-6 border-t border-gray-200 text-sm text-gray-600">
                            <i class="fas fa-calendar mr-2"></i>
                            Supplier added on <?= date('F j, Y', strtotime($supplier['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Linked Products Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">Linked Products</h2>
                        <p class="text-gray-600">Products associated with this supplier</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <i class="fas fa-box mr-1"></i>
                                <?= $active_products ?> Products
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-layer-group mr-1"></i>
                                <?= $total_linked_variants ?> Variants Linked
                            </span>
                        </div>
                        <a href="link_products.php?supplier_id=<?= $supplier['id'] ?>" 
                           class="bg-noble-primary hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 inline-flex items-center text-sm">
                            <i class="fas fa-plus mr-2"></i>Add Products
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <?php if (empty($linked_products)): ?>
    <div class="text-center py-12">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
            <i class="fas fa-box text-gray-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No products linked</h3>
        <p class="text-gray-600 mb-4">This supplier doesn't have any products linked yet.</p>
        <a href="link_products.php?supplier_id=<?= $supplier['id'] ?>" 
           class="bg-noble-primary hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors duration-200 inline-flex items-center">
            <i class="fas fa-link mr-2"></i>Link Products
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($linked_products as $product): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 overflow-hidden">
                <!-- Product Image -->
                <div class="aspect-square bg-gray-50 relative">
                    <?php 
                    $image_path = !empty($product['main_image']) ? '../../' . $product['main_image'] : '';
                    $image_exists = !empty($image_path) && file_exists($image_path);
                    
                    if ($image_exists): ?>
                        <img src="<?= htmlspecialchars($image_path) ?>" 
                             alt="<?= htmlspecialchars($product['product_name']) ?>"
                             class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-box text-gray-300 text-4xl"></i>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Variant Count Badge -->
                    <?php if ($product['linked_variants_count'] > 0): ?>
                    <div class="absolute top-3 right-3">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-link mr-1"></i>
                            <?= $product['linked_variants_count'] ?> linked
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="p-4">
                    <div class="mb-3">
                        <h3 class="font-semibold text-gray-900 text-sm line-clamp-2 mb-1" title="<?= htmlspecialchars($product['product_name']) ?>">
                            <?= !empty($product['product_name']) ? htmlspecialchars($product['product_name']) : 'Unnamed Product' ?>
                        </h3>
                        <p class="text-xs text-gray-500">ID: <?= $product['id'] ?></p>
                    </div>

                    <div class="space-y-2 text-sm">
                        <!-- Variants Count -->
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Variants:</span>
                            <span class="font-medium text-blue-600">
                                <?= $product['linked_variants_count'] ?> / <?= $product['total_variants_count'] ?> linked
                            </span>
                        </div>

                        <!-- Code -->
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Code:</span>
                            <span class="font-medium text-gray-900 truncate ml-2" title="<?= htmlspecialchars($product['codename']) ?>">
                                <?= !empty($product['codename']) ? htmlspecialchars($product['codename']) : 'No code' ?>
                            </span>
                        </div>

                        <!-- Linked Date -->
                        <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                            <span class="text-gray-600">Linked:</span>
                            <span class="text-gray-500 text-xs">
                                <?= date('M j, Y', strtotime($product['linked_date'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-4 flex space-x-2">
                        <button onclick="showVariantsModal(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['product_name'])) ?>')"
                           class="flex-1 bg-noble-primary hover:bg-blue-700 text-white text-xs py-2 px-3 rounded-lg transition-colors duration-200 text-center"
                           title="View Linked Variants">
                            <i class="fas fa-list mr-1"></i>View Variants
                        </button>
                        <a href="link_products.php?supplier_id=<?= $supplier['id'] ?>&product_id=<?= $product['id'] ?>" 
   class="bg-green-500 hover:bg-green-600 text-white text-xs py-2 px-3 rounded-lg transition-colors duration-200" 
   title="Link More Variants">
    <i class="fas fa-link"></i>
</a>
                        <button onclick="unlinkProduct(<?= $supplier['id'] ?>, <?= $product['id'] ?>, '<?= htmlspecialchars($product['product_name'], ENT_QUOTES) ?>')"
                                class="bg-red-500 hover:bg-red-600 text-white text-xs py-2 px-3 rounded-lg transition-colors duration-200" 
                                title="Unlink Product">
                            <i class="fas fa-unlink"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="mt-6 text-center">
        <p class="text-sm text-gray-600">
            Showing <?= count($linked_products) ?> linked product<?= count($linked_products) != 1 ? 's' : '' ?>
        </p>
    </div>
<?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Variants Modal -->
    <div id="variantsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <i class="fas fa-layer-group text-blue-500 mr-2"></i>
                        Linked Variants - <span id="variantsModalProductName"></span>
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
                    <p class="text-gray-600">This product doesn't have any variants.</p>
                </div>
            </div>
        </div>
    </div>

    <script></script>
    <script>
        function toggleSupplierStatus(supplierId, currentStatus, supplierName) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${action} "${supplierName}"?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'toggle_supplier_status.php';
                
                const supplierInput = document.createElement('input');
                supplierInput.type = 'hidden';
                supplierInput.name = 'supplier_id';
                supplierInput.value = supplierId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = newStatus;
                
                form.appendChild(supplierInput);
                form.appendChild(statusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function toggleLinkStatus(supplierId, productId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${action} this product link?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'toggle_product_link.php';
                
                const supplierInput = document.createElement('input');
                supplierInput.type = 'hidden';
                supplierInput.name = 'supplier_id';
                supplierInput.value = supplierId;
                
                const productInput = document.createElement('input');
                productInput.type = 'hidden';
                productInput.name = 'product_id';
                productInput.value = productId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = newStatus;
                
                form.appendChild(supplierInput);
                form.appendChild(productInput);
                form.appendChild(statusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function unlinkProduct(supplierId, productId, productName) {
            if (confirm(`Are you sure you want to unlink "${productName}" from this supplier? This action cannot be undone.`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'unlink_product.php';
                
                const supplierInput = document.createElement('input');
                supplierInput.type = 'hidden';
                supplierInput.name = 'supplier_id';
                supplierInput.value = supplierId;
                
                const productInput = document.createElement('input');
                productInput.type = 'hidden';
                productInput.name = 'product_id';
                productInput.value = productId;
                
                form.appendChild(supplierInput);
                form.appendChild(productInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-hide success/error messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.bg-green-50, .bg-red-50');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.transition = 'opacity 0.5s ease-out';
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 500);
                }, 5000);
            });
        });

        // Variants Modal Functions
        function showVariantsModal(productId, productName) {
            document.getElementById('variantsModalProductName').textContent = productName;
            document.getElementById('variantsModal').classList.remove('hidden');
            document.getElementById('variantsLoadingSpinner').classList.remove('hidden');
            document.getElementById('variantsContent').classList.add('hidden');
            document.getElementById('variantsEmptyState').classList.add('hidden');
            
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
                    displayVariants(data.variants);
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

        function displayVariants(variants) {
            const content = document.getElementById('variantsContent');
            content.innerHTML = '';
            
            // Separate linked and unlinked variants
            const linkedVariants = variants.filter(v => v.is_linked == 1);
            const unlinkedVariants = variants.filter(v => v.is_linked == 0);
            
            // Show linked variants first
            if (linkedVariants.length > 0) {
                const linkedHeader = document.createElement('h4');
                linkedHeader.className = 'text-md font-semibold text-gray-900 mb-3 flex items-center';
                linkedHeader.innerHTML = '<i class="fas fa-link text-green-500 mr-2"></i>Linked Variants (' + linkedVariants.length + ')';
                content.appendChild(linkedHeader);
                
                linkedVariants.forEach(variant => {
                    content.appendChild(createVariantCard(variant, true));
                });
            }
            
            // Show unlinked variants
            if (unlinkedVariants.length > 0) {
                const unlinkedHeader = document.createElement('h4');
                unlinkedHeader.className = 'text-md font-semibold text-gray-500 mb-3 mt-6 flex items-center';
                unlinkedHeader.innerHTML = '<i class="fas fa-unlink text-gray-400 mr-2"></i>Not Linked (' + unlinkedVariants.length + ')';
                content.appendChild(unlinkedHeader);
                
                unlinkedVariants.forEach(variant => {
                    content.appendChild(createVariantCard(variant, false));
                });
            }
            
            content.classList.remove('hidden');
        }

        function createVariantCard(variant, isLinked) {
            const card = document.createElement('div');
            card.className = 'bg-gray-50 rounded-lg border ' + (isLinked ? 'border-green-200 bg-green-50' : 'border-gray-200') + ' p-4';
            
            card.innerHTML = `
                <div class="flex items-start space-x-4">
                    <!-- Variant Image -->
                    <div class="flex-shrink-0">
                        ${variant.image ? `
                            <img src="../../${variant.image}" 
                                 alt="Variant image"
                                 class="w-16 h-16 rounded-lg object-cover border-2 border-gray-200">
                        ` : `
                            <div class="w-16 h-16 rounded-lg bg-gray-200 flex items-center justify-center border-2 border-gray-300">
                                <i class="fas fa-image text-gray-400"></i>
                            </div>
                        `}
                    </div>
                    
                    <!-- Variant Info -->
                    <div class="flex-1">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h5 class="font-semibold text-gray-900 mb-1">
                                    ${variant.namevariant || 'Unnamed Variant'}
                                </h5>
                                <div class="flex flex-wrap gap-2 mb-2">
                                    ${variant.color ? `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                            <i class="fas fa-palette mr-1"></i>${variant.color}
                                        </span>
                                    ` : ''}
                                    ${variant.size ? `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                            <i class="fas fa-ruler mr-1"></i>${variant.size}
                                        </span>
                                    ` : ''}
                                    ${isLinked ? `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${variant.supplier_type === 'primary' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                            <i class="fas fa-link mr-1"></i>${variant.supplier_type}
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Variant Details Grid -->
                        <div class="grid grid-cols-2 gap-3 mt-2 text-sm">
                            <div>
                                <span class="text-gray-600">Original Price:</span>
                                <span class="font-semibold text-gray-900">₱${parseFloat(variant.original_price || 0).toFixed(2)}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Selling Price:</span>
                                <span class="font-semibold text-green-600">₱${parseFloat(variant.price || 0).toFixed(2)}</span>
                            </div>
                            ${isLinked && variant.supplier_price ? `
                                <div class="col-span-2">
                                    <span class="text-blue-700 font-medium">Your Supplier Price:</span>
                                    <span class="font-bold text-blue-900">₱${parseFloat(variant.supplier_price).toFixed(2)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            return card;
        }

        // Close modal when clicking outside
        document.getElementById('variantsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeVariantsModal();
            }
        });
    </script>
</body>
</html>