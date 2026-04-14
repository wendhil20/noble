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

// Handle AJAX requests for supplier management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_product_info':
            $product_id = intval($_POST['product_id']);
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            echo json_encode($product);
            exit;
            
        case 'get_product_suppliers':
            $product_id = intval($_POST['product_id']);
            $stmt = $conn->prepare("
                SELECT 
                    slp.id, 
                    slp.supplier_type, 
                    slp.status,
                    sl.id as supplier_id,
                    sl.business_name,
                    sl.business_address,
                    sl.business_type,
                    sl.primary_contact_name,
                    sl.phone_number,
                    sl.email_address
                FROM supp_link_products slp
                JOIN supplier_list sl ON slp.supplier_id = sl.id
                WHERE slp.product_id = ? AND slp.status = 'active'
                ORDER BY slp.supplier_type = 'primary' DESC, sl.business_name ASC
            ");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $suppliers = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($suppliers);
            exit;
            
        case 'get_available_suppliers':
            $product_id = intval($_POST['product_id']);
            $stmt = $conn->prepare("
                SELECT id, business_name, business_type, primary_contact_name
                FROM supplier_list 
                WHERE status = 'active' 
                AND id NOT IN (
                    SELECT supplier_id 
                    FROM supp_link_products 
                    WHERE product_id = ? AND status = 'active'
                )
                ORDER BY business_name ASC
            ");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $suppliers = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($suppliers);
            exit;
            
        case 'add_supplier_to_product':
            $product_id = intval($_POST['product_id']);
            $supplier_id = intval($_POST['supplier_id']);
            $supplier_type = $_POST['supplier_type'];
            
            // Check if trying to add primary supplier when one already exists
            if ($supplier_type === 'primary') {
                $check_stmt = $conn->prepare("
                    SELECT id FROM supp_link_products 
                    WHERE product_id = ? AND supplier_type = 'primary' AND status = 'active'
                ");
                $check_stmt->bind_param("i", $product_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    echo json_encode(array('success' => false, 'message' => 'A primary supplier already exists for this product'));
                    exit;
                }
            }
            
            $stmt = $conn->prepare("
                INSERT INTO supp_link_products (supplier_id, product_id, supplier_type, status) 
                VALUES (?, ?, ?, 'active')
            ");
            $stmt->bind_param("iis", $supplier_id, $product_id, $supplier_type);
            
            if ($stmt->execute()) {
                echo json_encode(array('success' => true, 'message' => 'Supplier added successfully'));
            } else {
                echo json_encode(array('success' => false, 'message' => 'Failed to add supplier'));
            }
            exit;
            
        case 'remove_supplier_from_product':
            $link_id = intval($_POST['link_id']);
            $stmt = $conn->prepare("UPDATE supp_link_products SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param("i", $link_id);
            
            if ($stmt->execute()) {
                echo json_encode(array('success' => true, 'message' => 'Supplier removed successfully'));
            } else {
                echo json_encode(array('success' => false, 'message' => 'Failed to remove supplier'));
            }
            exit;
            
        case 'change_supplier_type':
            $link_id = intval($_POST['link_id']);
            $new_type = $_POST['new_type'];
            $product_id = intval($_POST['product_id']);
            
            // If changing to primary, check if primary already exists
            if ($new_type === 'primary') {
                $check_stmt = $conn->prepare("
                    SELECT id FROM supp_link_products 
                    WHERE product_id = ? AND supplier_type = 'primary' AND status = 'active' AND id != ?
                ");
                $check_stmt->bind_param("ii", $product_id, $link_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    echo json_encode(array('success' => false, 'message' => 'A primary supplier already exists. Remove it first.'));
                    exit;
                }
            }
            
            $stmt = $conn->prepare("UPDATE supp_link_products SET supplier_type = ? WHERE id = ?");
            $stmt->bind_param("si", $new_type, $link_id);
            
            if ($stmt->execute()) {
                echo json_encode(array('success' => true, 'message' => 'Supplier type updated successfully'));
            } else {
                echo json_encode(array('success' => false, 'message' => 'Failed to update supplier type'));
            }
            exit;
    }
}

// Fetch all products with their supplier counts
$products_query = "
    SELECT 
        p.*,
        COALESCE(supplier_counts.supplier_count, 0) as supplier_count,
        COALESCE(supplier_counts.has_primary, 0) as has_primary
    FROM products p
    LEFT JOIN (
        SELECT 
            product_id,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as supplier_count,
            COUNT(CASE WHEN supplier_type = 'primary' AND status = 'active' THEN 1 END) as has_primary
        FROM supp_link_products
        GROUP BY product_id
    ) supplier_counts ON p.id = supplier_counts.product_id
    ORDER BY p.product_name ASC
";

$products_result = $conn->query($products_query);

if (!$products_result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products & Suppliers Management</title>
   
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Products & Suppliers Management</h1>
            <p class="text-gray-600 mt-2">Manage supplier relationships for your products</p>
            
            <!-- Filter Options -->
            <div class="mt-4 flex flex-wrap gap-4 items-center">
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">Show:</label>
                    <select id="productFilter" onchange="filterProducts()" class="rounded-md border-gray-300 text-sm">
                        <option value="all">All Products</option>
                        <option value="incomplete">Products with Missing Info</option>
                        <option value="no-primary">Products without Primary Supplier</option>
                        <option value="no-suppliers">Products without Suppliers</option>
                    </select>
                </div>
                <div class="text-sm text-gray-500" id="productCount">
                    <!-- Product count will be shown here -->
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php while ($product = $products_result->fetch_assoc()): 
                $hasIncompleteInfo = empty($product['product_name']) || 
                                   $product['price'] === null || 
                                   $product['quantity'] === null || 
                                   empty($product['codename']);
                
                $product_name = !empty($product['product_name']) ? htmlspecialchars($product['product_name']) : 'Unnamed Product';
                $codename = !empty($product['codename']) ? htmlspecialchars($product['codename']) : 'No code assigned';
                $price_display = ($product['price'] !== null) ? '$' . number_format($product['price'], 2) : 'Price not set';
                
                $quantity_display = 'Quantity not set';
                if ($product['quantity'] !== null) {
                    $unit = !empty($product['unit']) ? htmlspecialchars($product['unit']) : 'units';
                    $quantity_display = $product['quantity'] . ' ' . $unit;
                }
                
                $supplier_count = intval($product['supplier_count']);
                $has_primary = intval($product['has_primary']);
            ?>
            <div class="product-card bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                <div class="p-6">
                    <!-- Incomplete Info Indicator -->
                    <?php if ($hasIncompleteInfo): ?>
                    <div class="incomplete-info mb-3">
                        <span class="inline-flex items-center px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Incomplete Info
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Product Image -->
                    <?php if (!empty($product['main_image'])): ?>
                    <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>" 
                         alt="<?php echo $product_name; ?>"
                         class="w-full h-32 object-cover rounded-md mb-4"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="w-full h-32 bg-gray-200 rounded-md mb-4 items-center justify-center hidden">
                        <div class="text-center">
                            <i class="fas fa-image text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-400 text-xs">Image not available</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="w-full h-32 bg-gray-200 rounded-md mb-4 flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-image text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-400 text-xs">No image</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Product Name -->
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                        <?php if (!empty($product['product_name'])): ?>
                            <?php echo $product_name; ?>
                        <?php else: ?>
                            <span class="text-gray-500 italic">Unnamed Product</span>
                        <?php endif; ?>
                    </h3>
                    
                    <!-- Description -->
                    <?php if (!empty($product['description'])): ?>
                    <p class="text-xs text-gray-500 mb-3 line-clamp-2">
                        <?php 
                        $description = htmlspecialchars($product['description']);
                        echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                        ?>
                    </p>
                    <?php endif; ?>
                    
                    <!-- Product Details -->
                    <div class="space-y-2 text-sm text-gray-600 mb-4">
                        <p><span class="font-medium">Code:</span> 
                            <?php if (!empty($product['codename'])): ?>
                                <?php echo $codename; ?>
                            <?php else: ?>
                                <span class="text-gray-400 italic">No code assigned</span>
                            <?php endif; ?>
                        </p>
                        <p><span class="font-medium">Price:</span> 
                            <?php if ($product['price'] !== null): ?>
                                <?php echo $price_display; ?>
                            <?php else: ?>
                                <span class="text-gray-400 italic">Price not set</span>
                            <?php endif; ?>
                        </p>
                        <p><span class="font-medium">Quantity:</span> 
                            <?php if ($product['quantity'] !== null): ?>
                                <?php echo $quantity_display; ?>
                            <?php else: ?>
                                <span class="text-gray-400 italic">Quantity not set</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <!-- Supplier Status -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-truck text-blue-500"></i>
                            <span class="supplier-count text-sm text-gray-600">
                                <?php echo $supplier_count; ?>
                            </span>
                            <span class="text-sm text-gray-600">
                                <?php echo ($supplier_count != 1) ? 'suppliers' : 'supplier'; ?>
                            </span>
                        </div>
                        
                        <?php if ($has_primary > 0): ?>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                <i class="fas fa-star"></i> Primary Set
                            </span>
                        <?php else: ?>
                            <span class="no-primary-badge px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">
                                <i class="fas fa-exclamation-triangle"></i> No Primary
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <button onclick="openSupplierModal(<?php echo intval($product['id']); ?>)" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors duration-200">
                        <i class="fas fa-cog mr-2"></i>Manage Suppliers
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Supplier Management Modal -->
    <div id="supplierModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-screen overflow-y-auto">
                <div class="sticky top-0 bg-white border-b px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-bold text-gray-900">Manage Suppliers</h2>
                        <button onclick="closeSupplierModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div id="modalProductInfo" class="mt-2">
                        <!-- Product info will be loaded here -->
                    </div>
                </div>

                <div class="p-6">
                    <!-- Add New Supplier Section -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Supplier</h3>
                        <div class="flex flex-wrap gap-4">
                            <select id="availableSuppliers" class="flex-1 min-w-0 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select a supplier...</option>
                            </select>
                            <select id="supplierType" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="secondary">Secondary</option>
                                <option value="primary">Primary</option>
                            </select>
                            <button onclick="addSupplierToProduct()" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-medium">
                                <i class="fas fa-plus mr-2"></i>Add Supplier
                            </button>
                        </div>
                    </div>

                    <!-- Current Suppliers List -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Current Suppliers</h3>
                        <div id="currentSuppliers" class="space-y-4">
                            <!-- Suppliers will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentProductId = null;

        // Filter products based on criteria
        function filterProducts() {
            const filter = document.getElementById('productFilter').value;
            const cards = document.querySelectorAll('.product-card');
            let visibleCount = 0;

            cards.forEach(card => {
                let show = true;
                
                switch(filter) {
                    case 'incomplete':
                        // Check if product has missing essential info
                        const hasIncompleteInfo = card.querySelector('.incomplete-info') !== null;
                        show = hasIncompleteInfo;
                        break;
                    case 'no-primary':
                        const noPrimary = card.querySelector('.no-primary-badge') !== null;
                        show = noPrimary;
                        break;
                    case 'no-suppliers':
                        const supplierCount = parseInt(card.querySelector('.supplier-count').textContent);
                        show = supplierCount === 0;
                        break;
                    case 'all':
                    default:
                        show = true;
                        break;
                }

                if (show) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            document.getElementById('productCount').textContent = 'Showing ' + visibleCount + ' products';
        }

        // Initialize filter on page load
        document.addEventListener('DOMContentLoaded', function() {
            filterProducts();
        });

        function openSupplierModal(productId) {
            currentProductId = productId;
            document.getElementById('supplierModal').classList.remove('hidden');
            loadProductInfo(productId);
            loadProductSuppliers(productId);
            loadAvailableSuppliers(productId);
        }

        function loadProductInfo(productId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_product_info&product_id=' + productId
            })
            .then(response => response.json())
            .then(product => {
                const container = document.getElementById('modalProductInfo');
                const formatValue = (value, fallback) => {
                    fallback = fallback || 'Not specified';
                    return (value && value !== null && value !== '') ? value : '<span class="text-gray-400 italic">' + fallback + '</span>';
                };
                
                let priceDisplay = 'Not set';
                if (product.price !== null && product.price !== '') {
                    priceDisplay = '$' + parseFloat(product.price).toFixed(2);
                }
                
                let quantityDisplay = 'Not set';
                if (product.quantity !== null && product.quantity !== '') {
                    quantityDisplay = product.quantity + ' ' + (product.unit || 'units');
                }
                
                container.innerHTML = 
                    '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">' +
                        '<div>' +
                            '<h3 class="font-semibold text-gray-900 text-lg">' + formatValue(product.product_name, 'Unnamed Product') + '</h3>' +
                            '<p class="text-gray-600 mt-1"><strong>Code:</strong> ' + formatValue(product.codename, 'No code') + '</p>' +
                        '</div>' +
                        '<div class="text-right md:text-left">' +
                            '<p class="text-gray-600"><strong>Price:</strong> ' + (product.price !== null ? '$' + parseFloat(product.price).toFixed(2) : '<span class="text-gray-400 italic">Not set</span>') + '</p>' +
                            '<p class="text-gray-600"><strong>Quantity:</strong> ' + (product.quantity !== null ? quantityDisplay : '<span class="text-gray-400 italic">Not set</span>') + '</p>' +
                        '</div>' +
                    '</div>' +
                    (product.description ? '<p class="text-gray-600 text-xs mt-2">' + product.description + '</p>' : '');
            });
        }

        function closeSupplierModal() {
            document.getElementById('supplierModal').classList.add('hidden');
            currentProductId = null;
        }

        function loadProductSuppliers(productId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_product_suppliers&product_id=' + productId
            })
            .then(response => response.json())
            .then(suppliers => {
                const container = document.getElementById('currentSuppliers');
                if (suppliers.length === 0) {
                    container.innerHTML = '<p class="text-gray-500 text-center py-8">No suppliers assigned to this product yet.</p>';
                    return;
                }

                container.innerHTML = suppliers.map(supplier => {
                    const isPrimary = supplier.supplier_type === 'primary';
                    return '<div class="border rounded-lg p-4 ' + (isPrimary ? 'border-green-500 bg-green-50' : 'border-gray-200') + '">' +
                        '<div class="flex items-start justify-between">' +
                            '<div class="flex-1">' +
                                '<div class="flex items-center space-x-3 mb-2">' +
                                    '<h4 class="font-medium text-gray-900">' + supplier.business_name + '</h4>' +
                                    '<span class="px-2 py-1 text-xs rounded-full ' + (isPrimary ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') + '">' +
                                        (isPrimary ? '<i class="fas fa-star mr-1"></i>Primary' : 'Secondary') +
                                    '</span>' +
                                '</div>' +
                                '<div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600">' +
                                    '<p><strong>Contact:</strong> ' + supplier.primary_contact_name + '</p>' +
                                    '<p><strong>Type:</strong> ' + supplier.business_type + '</p>' +
                                    '<p><strong>Phone:</strong> ' + supplier.phone_number + '</p>' +
                                    '<p><strong>Email:</strong> ' + supplier.email_address + '</p>' +
                                '</div>' +
                                '<p class="text-sm text-gray-600 mt-2"><strong>Address:</strong> ' + supplier.business_address + '</p>' +
                            '</div>' +
                            '<div class="flex flex-col space-y-2 ml-4">' +
                                '<button onclick="changeSupplierType(' + supplier.id + ', \'' + (isPrimary ? 'secondary' : 'primary') + '\')" ' +
                                        'class="px-3 py-1 text-xs ' + (isPrimary ? 'bg-blue-100 text-blue-800 hover:bg-blue-200' : 'bg-green-100 text-green-800 hover:bg-green-200') + ' rounded-md">' +
                                    (isPrimary ? 'Make Secondary' : 'Make Primary') +
                                '</button>' +
                                '<button onclick="removeSupplier(' + supplier.id + ')" ' +
                                        'class="px-3 py-1 text-xs bg-red-100 text-red-800 hover:bg-red-200 rounded-md">' +
                                    '<i class="fas fa-trash mr-1"></i>Remove' +
                                '</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                }).join('');
            });
        }

        function loadAvailableSuppliers(productId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_available_suppliers&product_id=' + productId
            })
            .then(response => response.json())
            .then(suppliers => {
                const select = document.getElementById('availableSuppliers');
                select.innerHTML = '<option value="">Select a supplier...</option>' + 
                    suppliers.map(supplier => 
                        '<option value="' + supplier.id + '">' + supplier.business_name + ' (' + supplier.business_type + ')</option>'
                    ).join('');
            });
        }

        function addSupplierToProduct() {
            const supplierId = document.getElementById('availableSuppliers').value;
            const supplierType = document.getElementById('supplierType').value;
            
            if (!supplierId) {
                alert('Please select a supplier');
                return;
            }

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=add_supplier_to_product&product_id=' + currentProductId + '&supplier_id=' + supplierId + '&supplier_type=' + supplierType
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    loadProductSuppliers(currentProductId);
                    loadAvailableSuppliers(currentProductId);
                    document.getElementById('availableSuppliers').value = '';
                    location.reload(); // Refresh to update supplier counts
                } else {
                    alert(result.message);
                }
            });
        }

        function removeSupplier(linkId) {
            if (!confirm('Are you sure you want to remove this supplier?')) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=remove_supplier_from_product&link_id=' + linkId
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    loadProductSuppliers(currentProductId);
                    loadAvailableSuppliers(currentProductId);
                    location.reload(); // Refresh to update supplier counts
                } else {
                    alert(result.message);
                }
            });
        }

        function changeSupplierType(linkId, newType) {
            if (!confirm('Are you sure you want to change this supplier type?')) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=change_supplier_type&link_id=' + linkId + '&new_type=' + newType + '&product_id=' + currentProductId
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    loadProductSuppliers(currentProductId);
                    location.reload(); // Refresh to update supplier counts
                } else {
                    alert(result.message);
                }
            });
        }

        // Close modal when clicking outside of it
        document.getElementById('supplierModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSupplierModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('supplierModal').classList.contains('hidden')) {
                closeSupplierModal();
            }
        });
    </script>
</body>
</html>