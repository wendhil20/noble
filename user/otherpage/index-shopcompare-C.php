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
        
        .comparison-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .comparison-table::-webkit-scrollbar {
            height: 5px;
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
            min-width: 200px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Hero Section -->
    <div class="bg-black text-white py-6">
        <div class="container mx-auto px-4">
            <h1 class="text-2xl sm:text-3xl font-semibold">Product Comparison</h1>
            <p class="text-sm text-gray-300 mt-1">Compare features side-by-side</p>
        </div>
    </div>

  <!-- Breadcrumb -->
  <nav class="bg-white border-b border-gray-200 px-4 py-3">
    <div class="container mx-auto">
      <div class="flex items-center space-x-2 text-sm">
        <a href="index-page-1-A-B-C-D-E" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
          <i class="fas fa-home mr-1"></i>Home
        </a>
        <i class="fas fa-chevron-right text-gray-400"></i>
        <span class="text-gray-600 font-medium">Compare</span>
      </div>
    </div>
  </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-6">
        <?php if (empty($products)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded-lg p-12 text-center shadow-sm">
                <i class="fas fa-balance-scale text-5xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900 mb-2">No Products Selected</h2>
                <p class="text-gray-600 text-sm mb-6">Select products from the shop to start comparing</p>
                <a href="shop" class="inline-block bg-black hover:bg-gray-800 text-white px-6 py-2 text-sm rounded transition">
                    <i class="fas fa-shopping-bag mr-2"></i>Browse Products
                </a>
            </div>
        <?php else: ?>
            <!-- Control Bar -->
            <div class="bg-white rounded-lg p-4 mb-4 shadow-sm">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">Comparing <?= count($products) ?> Product<?= count($products) > 1 ? 's' : '' ?></h3>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                      
                        <a href="shop" 
                            class="flex-1 sm:flex-none px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-900 text-xs font-medium rounded transition text-center">
                            <i class="fas fa-plus mr-1"></i>Add
                        </a>
                        <button onclick="clearAllComparisons()" 
                            class="flex-1 sm:flex-none px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded transition">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Comparison Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="comparison-table">
                    <table class="w-full text-sm">
                        <!-- Product Header -->
                        <thead class="sticky-header border-b border-gray-200">
                            <tr class="bg-gray-50">
                                <th class="p-3 text-left font-semibold text-gray-900 w-40">Features</th>
                                <?php foreach ($products as $index => $product): ?>
                            <th class="p-3 product-column bg-white border-l border-gray-200">
                                        <div class="text-center">
                                            <?php if (!empty($product['main_image'])): ?>
                                                <div class="relative mb-2">
                                                    <img src="../../<?= htmlspecialchars($product['main_image']) ?>" 
                                                         alt="<?= htmlspecialchars($product['product_name']) ?>"
                                                         class="w-full h-24 object-contain">
                                                    <div class="absolute top-1 right-1 bg-black text-white w-6 h-6 flex items-center justify-center font-bold text-xs rounded-full">
                                                        <?= $index + 1 ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-full h-24 bg-gray-100 mb-2 flex items-center justify-center rounded">
                                                    <i class="fas fa-image text-2xl text-gray-300"></i>
                                                </div>
                                            <?php endif; ?>
                                            <h4 class="font-semibold text-gray-900 text-xs leading-tight mb-1">
                                                <?= htmlspecialchars($product['product_name']) ?>
                                            </h4>
                                            <p class="text-xs text-gray-500">
                                                <?= htmlspecialchars($product['codename'] ?? 'N/A') ?>
                                            </p>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200">
                            <!-- Price Range -->
                            <tr class="feature-row">
                                <td class="p-3 font-semibold text-gray-900">Price</td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-3 text-center border-l border-gray-200">
                                        <?php if ($product['min_price'] && $product['max_price']): ?>
                                            <div class="font-semibold text-gray-900">₱<?= number_format($product['min_price'], 0) ?></div>
                                            <?php if ($product['min_price'] != $product['max_price']): ?>
                                                <div class="text-xs text-gray-500">to ₱<?= number_format($product['max_price'], 0) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">Contact</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Colors -->
                            <tr class="feature-row">
                                <td class="p-3 font-semibold text-gray-900">Colors</td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-3 text-center border-l border-gray-200">
                                        <span class="text-xs text-gray-700">
                                            <?= !empty($product['available_colors']) ? htmlspecialchars($product['available_colors']) : '—' ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Types -->
                            <tr class="feature-row">
                                <td class="p-3 font-semibold text-gray-900">Types</td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-3 text-center border-l border-gray-200">
                                        <span class="text-xs text-gray-700">
                                            <?= !empty($product['available_types']) ? htmlspecialchars($product['available_types']) : '—' ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Sizes -->
                            <tr class="feature-row">
                                <td class="p-3 font-semibold text-gray-900">Sizes</td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-3 text-center border-l border-gray-200">
                                        <?php 
                                        $size_stmt = $conn->prepare("
                                            SELECT DISTINCT pv.size 
                                            FROM product_variants pv
                                            JOIN product_types pt ON pv.type_id = pt.id
                                            WHERE pt.product_id = ? AND pv.size IS NOT NULL AND pv.size != ''
                                            ORDER BY pv.size LIMIT 5
                                        ");
                                        $size_stmt->bind_param("i", $product['id']);
                                        $size_stmt->execute();
                                        $size_result = $size_stmt->get_result();
                                        $sizes = [];
                                        while ($size_row = $size_result->fetch_assoc()) {
                                            $sizes[] = $size_row['size'];
                                        }
                                        $size_stmt->close();
                                        ?>
                                        <span class="text-xs text-gray-700">
                                            <?= !empty($sizes) ? htmlspecialchars(implode(', ', $sizes)) : '—' ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Unit -->
                            <tr class="feature-row">
                                <td class="p-3 font-semibold text-gray-900">Unit</td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-3 text-center border-l border-gray-200">
                                        <span class="text-xs text-gray-700"><?= htmlspecialchars($product['descrip6'] ?? '—') ?></span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Description -->
                            <tr class="feature-row">
                                <td class="p-3 font-semibold text-gray-900">Description</td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-3 border-l border-gray-200">
                                        <div class="text-xs text-gray-700 line-clamp-3 leading-relaxed">
                                            <?= nl2br(htmlspecialchars($product['description'] ?? 'N/A')) ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Actions -->
                            <tr class="feature-row bg-gray-50">
                                <td class="p-3 font-semibold text-gray-900">Actions</td>
                                <?php foreach ($products as $product): ?>
                                    <td class="p-3 text-center border-l border-gray-200">
                                        <div class="flex flex-col gap-1.5">
                                            <a href="index-product_view-page-4-AA.php?id=<?= $product['id'] ?>" 
                                               class="bg-black hover:bg-gray-800 text-white px-2 py-1 text-xs rounded transition font-medium">
                                                View
                                            </a>
                                            <button onclick="removeFromComparison(<?= $product['id'] ?>)"
                                                    class="bg-gray-200 hover:bg-gray-300 text-gray-900 px-2 py-1 text-xs rounded transition font-medium">
                                                Remove
                                            </button>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <script>

        function removeFromComparison(productId) {
            if (confirm('Remove this product?')) {
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

        function clearAllComparisons() {
            if (confirm('Clear all comparisons?')) {
                localStorage.removeItem('compareProducts');
                window.location.href = 'index-shop-page-2';
            }
        }

        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body { background: white !important; }
                nav, footer, button { display: none !important; }
                .sticky-header { position: static !important; }
                table { page-break-inside: auto; }
                tr { page-break-inside: avoid; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>