<?php
session_name("nobleadmin");
session_start();
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

include '../../connection/connect.php';

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $where_clause = "WHERE p.product_name LIKE '%$search_escaped%' OR p.codename LIKE '%$search_escaped%'";
}

// Get products with variant count
$result = $conn->query("
    SELECT 
        p.id, 
        p.product_name, 
        p.codename, 
        p.descrip6, 
        p.descrip7,
        COUNT(pv.id) as variant_count
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    $where_clause
    GROUP BY p.id
    ORDER BY p.id ASC
");

$total_products = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                        'noble-orange-light': '#fb923c',
                        'noble-orange-dark': '#ea580c',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 min-h-screen font-sans">

    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
        <div class="w-full px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-noble-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-cubes text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Product Management</h1>
                        <p class="text-sm text-gray-600">Manage descriptions and SKU</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="w-full px-6 py-8">

        <!-- Search Bar -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <form method="GET" class="flex gap-3 items-center">
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="search" placeholder="Search by product name or code..." 
                            value="<?= htmlspecialchars($search) ?>"
                            class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent">
                    </div>
                </div>
                <button type="submit" class="inline-flex items-center px-6 py-3 bg-noble-orange hover:bg-noble-orange-dark text-white rounded-lg transition font-semibold">
                    <i class="fas fa-search mr-2"></i>
                    Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="?search=" class="inline-flex items-center px-6 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition font-semibold">
                        <i class="fas fa-times mr-2"></i>
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Results Count -->
        <div class="mb-6">
            <p class="text-sm text-gray-600">
                <i class="fas fa-box mr-2 text-noble-orange"></i>
                Found <span class="font-semibold text-gray-900"><?= $total_products ?></span> product<?= $total_products !== 1 ? 's' : '' ?>
                <?php if (!empty($search)): ?>
                    matching "<span class="font-semibold"><?= htmlspecialchars($search) ?></span>"
                <?php endif; ?>
            </p>
        </div>

        <!-- Products Grid -->
        <?php if ($total_products > 0): ?>
            <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all hover:scale-105 overflow-hidden border border-gray-200">
                        
                        <!-- Product Info Section -->
                        <div class="p-6 border-b border-gray-200">
                            <div class="text-xs text-gray-500 mb-2 flex items-center">
                                <i class="fas fa-hashtag text-noble-orange mr-1"></i>
                                ID: <?= $row['id'] ?>
                            </div>
                            
                            <div class="text-xs text-blue-600 mb-3 font-mono bg-blue-50 px-2 py-1 rounded inline-block">
                                <?= htmlspecialchars($row['codename'] ?? '-') ?>
                            </div>

                            <h3 class="text-base font-bold text-gray-900 mb-4 line-clamp-2">
                                <?= htmlspecialchars($row['product_name'] ?? '-') ?>
                            </h3>

                            <!-- Status Indicators -->
                            <div class="space-y-2">
                                <!-- Descrip6 Status -->
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-600">Unit Info</span>
                                    <?php if (!empty($row['descrip6'])): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 font-semibold">
                                            <i class="fas fa-check mr-1"></i>
                                            Set
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 font-semibold">
                                            <i class="fas fa-times mr-1"></i>
                                            Missing
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Descrip7 Status -->
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-600">Specs</span>
                                    <?php if (!empty($row['descrip7'])): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 font-semibold">
                                            <i class="fas fa-check mr-1"></i>
                                            Set
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-700 font-semibold">
                                            <i class="fas fa-times mr-1"></i>
                                            Missing
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Variants Count -->
                                <div class="flex items-center justify-between text-xs pt-2 border-t border-gray-200">
                                    <span class="text-gray-600">Variants</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-100 text-blue-700 font-bold">
                                        <i class="fas fa-copy mr-1"></i>
                                        <?= $row['variant_count'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="p-4 space-y-2">
                            <a href="set_description-page-5-A.php?id=<?= $row['id'] ?>&type=product"
                                class="block text-center bg-noble-orange hover:bg-noble-orange-dark text-white px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center justify-center">
                                <i class="fas fa-pen-fancy mr-2"></i>
                                Descriptions
                            </a>

                            <a href="set_sku-page-5-A.php?product_id=<?= $row['id'] ?>"
                                class="block text-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center justify-center">
                                <i class="fas fa-barcode mr-2"></i>
                                SKU (<?= $row['variant_count'] ?>)
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-gray-50 rounded-xl p-12 text-center border border-dashed border-gray-300">
                <i class="fas fa-search text-gray-300 text-5xl mb-4 block"></i>
                <p class="text-gray-500 text-lg font-medium">No products found</p>
                <p class="text-gray-400 text-sm mt-1">Try adjusting your search criteria</p>
            </div>
        <?php endif; ?>

    </main>

</body>

</html>