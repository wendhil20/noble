<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Restore session if remember_token exists
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
    }
    $stmt->close();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Guest';

// Get product IDs from query string
$product_ids = [];
if (isset($_GET['products'])) {
    $product_ids = array_filter(array_map('intval', explode(',', $_GET['products'])));
}

$products = [];
if (!empty($product_ids)) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $order_field = implode(',', $product_ids);
    
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.id SEPARATOR ', ') as available_colors,
            GROUP_CONCAT(DISTINCT pt.type_name ORDER BY pt.id SEPARATOR ', ') as available_types,
            MIN(pv.price) as min_price,
            MAX(pv.price) as max_price
        FROM products p
        LEFT JOIN product_colors pc ON p.id = pc.product_id
        LEFT JOIN product_types pt ON p.id = pt.product_id
        LEFT JOIN product_variants pv ON pt.id = pv.type_id
        WHERE p.id IN ($placeholders)
        GROUP BY p.id
        ORDER BY FIELD(p.id, $order_field)
    ");
    
    $types = str_repeat('i', count($product_ids));
    $stmt->bind_param($types, ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Product Comparison</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Roboto', sans-serif;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
        }
        
        .comparison-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .comparison-table::-webkit-scrollbar {
            height: 6px;
        }
        
        .comparison-table::-webkit-scrollbar-track {
            background: #f5f5f5;
        }
        
        .comparison-table::-webkit-scrollbar-thumb {
            background: #333;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: white;
        }
        
        .product-column {
            min-width: 280px;
            max-width: 320px;
        }
        
        .feature-row {
            transition: background 0.2s ease;
        }
        
        .feature-row:hover {
            background: #fafafa;
        }
        
        @media (max-width: 768px) {
            .product-column {
                min-width: 240px;
                max-width: 260px;
            }
        }
    </style>
</head>
<body class="bg-white">
    <?php include '../navbar/top.php'; ?>

    <!-- Hero Section -->
    <div class="bg-black text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl sm:text-5xl font-light mb-4 tracking-tight">
                    Product Comparison
                </h1>
                <p class="text-lg font-light opacity-90">
                    Compare specifications and features side-by-side
                </p>
            </div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <nav class="bg-white px-4 py-3">
        <div class="container mx-auto">
            <div class="flex items-center space-x-2 text-sm">
                <a href="index" class="text-gray-700 hover:text-black transition font-light">
                    <i class="fas fa-home mr-1"></i>Home
                </a>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <a href="shop" class="text-gray-700 hover:text-black transition font-light">Shop</a>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <span class="text-black font-normal">Compare Products</span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (empty($products)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded p-16 text-center">
                <div class="mb-6">
                    <i class="fas fa-balance-scale text-7xl text-gray-300"></i>
                </div>
                <h2 class="text-3xl font-light text-black mb-4">No Products to Compare</h2>
                <p class="text-gray-600 mb-8 font-light">
                    Select products from the shop to start comparing
                </p>
                <a href="shop" class="inline-block bg-black hover:bg-gray-800 text-white px-8 py-3 transition font-normal">
                    Browse Products
                </a>
            </div>
        <?php else: ?>
            <!-- Comparison Controls -->
            <div class="bg-white p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div>
                        <h3 class="text-xl font-normal text-black">Comparing <?= count($products) ?> Product<?= count($products) > 1 ? 's' : '' ?></h3>
                        <p class="text-sm text-gray-600 font-light mt-1">Review and compare product features</p>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="printComparison()" 
                            class="px-5 py-2.5 bg-white hover:bg-gray-100 text-black transition font-normal text-sm">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                        <a href="shop" 
                            class="px-5 py-2.5 bg-white hover:bg-gray-100 text-black transition font-normal text-sm">
                            <i class="fas fa-plus mr-2"></i>Add More
                        </a>
                        <button onclick="clearAllComparisons()" 
                            class="px-5 py-2.5 bg-black hover:bg-gray-800 text-white transition font-normal text-sm">
                            <i class="fas fa-times mr-2"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Comparison Table -->
            <div class="bg-white">
                <div class="comparison-table">
                    <table class="w-full">
                        <!-- Product Images and Names -->
                        <thead class="sticky-header">
                            <tr>
                                <th class="p-5 text-left font-medium text-black w-48">
                                    Features
                                </th>
                                <?php foreach ($products as $index => $product): ?>
                                    <th class="p-5 bg-white product-column">
                                        <div class="text-center">
                                            <?php if (!empty($product['main_image'])): ?>
                                                <div class="relative mb-4">
                                                    <img src="../../<?= htmlspecialchars($product['main_image']) ?>" 
                                                         alt="<?= htmlspecialchars($product['product_name']) ?>"
                                                         class="w-full h-56 object-contain">
                                                    <div class="absolute top-3 right-3 bg-black text-white w-8 h-8 flex items-center justify-center font-medium text-sm">
                                                        <?= $index + 1 ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-full h-56 bg-gray-100 mb-4 flex items-center justify-center">
                                                    <i class="fas fa-image text-4xl text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                            <h3 class="font-medium text-lg text-black mb-2">
                                                <?= htmlspecialchars($product['product_name']) ?>
                                            </h3>
                                            <p class="text-sm text-gray-600 font-light">
                                                <?= htmlspecialchars($product['codename'] ?? 'N/A') ?>
                                            </p>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>

                        <tbody>
                            <!-- Price Range -->
                            <tr class="feature-row">
                                <td class="p-5 font-medium text-black">
                                    Price Range
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5 text-center">
                                        <?php if ($product['min_price'] && $product['max_price']): ?>
                                            <div class="font-medium text-black text-lg">
                                                ₱<?= number_format($product['min_price'], 2) ?>
                                                <?php if ($product['min_price'] != $product['max_price']): ?>
                                                    <div class="text-sm font-light text-gray-600 mt-1">to ₱<?= number_format($product['max_price'], 2) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-500 font-light">Contact for price</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Category -->
                            <tr class="feature-row">
                                <td class="p-5 font-medium text-black">
                                    Category
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5 text-center">
                                        <span class="inline-block bg-black text-white px-4 py-1.5 text-sm font-normal">
                                            <?= htmlspecialchars($product['codename'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Available Colors -->
                            <tr class="feature-row">
                                <td class="p-5 font-medium text-black">
                                    Available Colors
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5 text-center">
                                        <?php if (!empty($product['available_colors'])): ?>
                                            <div class="text-sm text-gray-700 font-light">
                                                <?= htmlspecialchars($product['available_colors']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Available Types -->
                            <tr class="feature-row">
                                <td class="p-5 font-medium text-black">
                                    Available Types
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5 text-center">
                                        <?php if (!empty($product['available_types'])): ?>
                                            <div class="text-sm text-gray-700 font-light">
                                                <?= htmlspecialchars($product['available_types']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Available Sizes -->
                            <tr class="feature-row">
                                <td class="p-5 font-medium text-black">
                                    Available Sizes
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5 text-center">
                                        <?php 
                                        $size_stmt = $conn->prepare("
                                            SELECT DISTINCT pv.size 
                                            FROM product_variants pv
                                            JOIN product_types pt ON pv.type_id = pt.id
                                            WHERE pt.product_id = ? AND pv.size IS NOT NULL AND pv.size != ''
                                            ORDER BY pv.size
                                        ");
                                        $size_stmt->bind_param("i", $product['id']);
                                        $size_stmt->execute();
                                        $size_result = $size_stmt->get_result();
                                        $sizes = [];
                                        while ($size_row = $size_result->fetch_assoc()) {
                                            $sizes[] = $size_row['size'];
                                        }
                                        $size_stmt->close();
                                        
                                        if (!empty($sizes)): ?>
                                            <div class="text-sm text-gray-700 font-light">
                                                <?= htmlspecialchars(implode(', ', $sizes)) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Unit -->
                            <tr class="feature-row">
                                <td class="p-5 font-medium text-black">
                                    Unit
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5 text-center">
                                        <span class="text-gray-700 font-light"><?= htmlspecialchars($product['descrip6'] ?? '—') ?></span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Specification -->
                            <tr class="feature-row">
                                <td class="p-5 font-medium text-black">
                                    Specification
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5 text-center">
                                        <span class="text-gray-700 font-light"><?= htmlspecialchars($product['descrip7'] ?? '—') ?></span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Description -->
                            <tr class="feature-row">
                                <td class="p-5 font-medium text-black">
                                    Description
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5">
                                        <div class="text-sm text-gray-700 text-left max-h-32 overflow-y-auto font-light leading-relaxed">
                                            <?= nl2br(htmlspecialchars($product['description'] ?? 'No description available')) ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Actions -->
                            <tr class="feature-row bg-gray-50">
                                <td class="p-5 font-medium text-black">
                                    Actions
                                </td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-5 text-center">
                                        <div class="flex flex-col gap-2">
                                            <a href="product_view?id=<?= $product['id'] ?>" 
                                               class="bg-black hover:bg-gray-800 text-white px-4 py-2.5 transition font-normal text-sm">
                                                <i class="fas fa-eye mr-2"></i>View Details
                                            </a>
                                            <button onclick="removeFromComparison(<?= $product['id'] ?>)"
                                                    class="bg-white hover:bg-gray-100 text-black px-4 py-2.5 transition font-normal text-sm">
                                                <i class="fas fa-times mr-2"></i>Remove
                                            </button>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tips Section -->
            <div class="mt-6 p-5">
                <h3 class="font-medium text-black mb-3 flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>
                    Comparison Guidelines
                </h3>
                <ul class="space-y-2 text-sm text-gray-700 font-light">
                    <li>• Scroll horizontally to view all products on smaller screens</li>
                    <li>• Click "View Details" to see complete product information</li>
                    <li>• Use the print button to save this comparison</li>
                    <li>• Compare unlimited products to find the best match</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <script>
        // Print comparison
        function printComparison() {
            window.print();
        }

        // Remove product from comparison
        function removeFromComparison(productId) {
            if (confirm('Remove this product from comparison?')) {
                const urlParams = new URLSearchParams(window.location.search);
                const products = urlParams.get('products');
                
                if (products) {
                    const productIds = products.split(',').filter(id => id != productId);
                    localStorage.setItem('compareProducts', JSON.stringify(productIds.map(id => parseInt(id))));
                    
                    if (productIds.length > 0) {
                        window.location.href = '?products=' + productIds.join(',');
                    } else {
                        window.location.href = 'index-shop-page-2';
                    }
                }
            }
        }

        // Clear all comparisons
        function clearAllComparisons() {
            if (confirm('Clear all product comparisons?')) {
                localStorage.removeItem('compareProducts');
                window.location.href = 'index-shop-page-2';
            }
        }

        // Print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body { background: white !important; }
                .sticky-header { position: static !important; }
                button, nav, footer { display: none !important; }
                .comparison-table { overflow: visible !important; }
                table { page-break-inside: auto; }
                tr { page-break-inside: avoid; page-break-after: auto; }
                @page { margin: 1cm; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>