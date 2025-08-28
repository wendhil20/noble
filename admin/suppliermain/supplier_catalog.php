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
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <?php include '../navbar/top.php'; ?>
    <div class="container p-4 mx-auto max-w-7xl">
        <!-- Navigation -->
        <div class="mb-6">
            <a href="../warehouse/warehouses.php"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Supplier List
            </a>

        </div>

        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-4">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($supplier_name); ?></h1>
                    <div class="flex flex-col gap-1 text-gray-600 text-sm">
                        <p>Supplier ID: <span class="font-medium"><?php echo $supplier_id; ?></span></p>
                        <p>Contact: <span class="font-medium"><?php echo htmlspecialchars($supplier_info['email']); ?></span></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold">
                        <?php echo count($organized_products); ?> Products
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Updated: <?php echo date('M j, Y'); ?></p>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div class="bg-white rounded-lg shadow-md p-4">
            <h2 class="text-lg font-bold mb-4 text-gray-800">Product Catalog</h2>


            <div id="detailsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-50">

                <div class="bg-white rounded-lg w-full max-w-2xl p-6 relative">
                    <button class="absolute top-2 right-2 text-gray-500 hover:text-black" onclick="closeModal()">&times;</button>
                    <h2 class="text-xl font-semibold mb-4">Product Details</h2>
                    <div id="modalContent">
                        <!-- AJAX will inject content here -->
                        <div class="text-center text-gray-500">Loading...</div>
                    </div>
                </div>
            </div>

            <?php if (count($organized_products) > 0): ?>
                <?php
                $current_category = '';
                foreach ($organized_products as $product):
                    if ($current_category !== $product['category']):
                        $current_category = $product['category'];
                ?>
                        <h3 class="text-lg font-semibold text-blue-700 border-l-4 border-blue-500 pl-3 my-6">
                            <?php echo htmlspecialchars($current_category); ?>
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php endif; ?>

                        <!-- Product Card -->
                        <div class="border border-gray-200 bg-white rounded-lg shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col">
                            <div class="w-full h-48 bg-gray-100 flex items-center justify-center">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="w-full h-full object-contain">
                                <?php else: ?>
                                    <div class="text-gray-400 text-sm">No Image</div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-1 text-base">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </h4>
                                    <p class="text-xs text-gray-500 mb-2"><?php echo htmlspecialchars($product['description']); ?></p>

                                    <div class="mb-2">
                                        <span class="text-xs bg-gray-100 text-gray-700 rounded px-2 py-0.5 mr-1"><?php echo htmlspecialchars($product['item_code']); ?></span>
                                        <span class="text-xs bg-green-100 text-green-700 rounded px-2 py-0.5"><?php echo ucfirst($product['status']); ?></span>
                                    </div>

                                    <?php if (!empty($product['variants'])): ?>
                                        <div class="swiper variantSwiper">
                                            <div class="swiper-wrapper">
                                                <?php foreach ($product['variants'] as $variant): ?>
                                                    <div class="swiper-slide !w-auto max-w-[250px]">
                                                        <div class="bg-gray-50 border rounded px-2 py-1 text-sm h-full">
                                                            <div class="flex justify-between items-center gap-x-2">
                                                                <span class="text-gray-700">Color: <?php echo htmlspecialchars($variant['color']); ?></span>
                                                                <span class="font-semibold text-red-600">₱ <?php echo number_format($variant['price'], 2); ?></span>
                                                            </div>

                                                            <?php if (!empty($variant['sizes'])): ?>
                                                                <div class="mt-1 flex flex-wrap gap-1">
                                                                    <?php foreach ($variant['sizes'] as $size): ?>
                                                                        <span class="text-xs bg-white border px-1.5 py-0.5 rounded whitespace-nowrap">
                                                                            <?php echo htmlspecialchars($size['size']); ?> (<?php echo $size['stock']; ?>)
                                                                        </span>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-4 flex justify-between gap-2">
                                    <button
                                        class="text-xs bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded open-details"
                                        data-product-id="<?php echo $product['id']; ?>">
                                        Details
                                    </button>

                                    <button class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">Add Order</button>
                                    <button class="text-xs bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded">Contact</button>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                        </div> <!-- Close last grid -->
                    <?php else: ?>
                        <div class="text-center py-10 text-gray-500">No products found for this supplier.</div>
             <?php endif; ?>
        </div>


        <!-- Summary Footer -->
        <?php if (count($organized_products) > 0): ?>
            <div class="bg-white rounded-lg shadow-md p-4 mt-4">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-gray-600 text-sm">
                            Showing <span class="font-semibold"><?php echo count($organized_products); ?></span> products from
                            <span class="font-semibold"><?php echo htmlspecialchars($supplier_name); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const variantSwiper = new Swiper(".variantSwiper", {
            slidesPerView: 'auto',
            spaceBetween: 12,
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
        });


        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.open-details').forEach(button => {
                button.addEventListener('click', () => {
                    const productId = button.getAttribute('data-product-id');

                    // Show modal
                    document.getElementById('detailsModal').classList.remove('hidden');

                    // Load modal content
                    fetch(`get_product_details.php?product_id=${productId}`)
                        .then(res => res.text())
                        .then(html => {
                            document.getElementById('modalContent').innerHTML = html;
                        })
                        .catch(err => {
                            document.getElementById('modalContent').innerHTML = '<p class="text-red-500">Failed to load details.</p>';
                        });
                });
            });
        });

        function closeModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
    </script>


</body>

</html>