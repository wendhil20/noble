<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Session restoration from remember_token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $_COOKIE['remember_token']);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION = array_merge($_SESSION, [
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_email' => $user['email'] ?? '',
            'user_mobile' => $user['mobile'] ?? ''
        ]);

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Input validation
$selected_categories = $_GET['category'] ?? [];
$search_keyword = trim($_GET['search'] ?? '');
$sort_by = $_GET['sort'] ?? 'name_asc';
$per_page = max(12, min(100, intval($_GET['per_page'] ?? 12)));
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ['1=1'];
$params = [];
$types = '';

if (!empty($selected_categories)) {
    $placeholders = str_repeat('?,', count($selected_categories) - 1) . '?';
    $where_conditions[] = "codename IN ($placeholders)";
    $params = array_merge($params, $selected_categories);
    $types .= str_repeat('s', count($selected_categories));
}

if (!empty($search_keyword)) {
    $where_conditions[] = "(product_name LIKE ? OR description LIKE ?)";
    $search_param = '%' . $search_keyword . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$sort_options = [
    'name_asc' => 'product_name ASC',
    'name_desc' => 'product_name DESC',
    'newest' => 'id DESC',
    'oldest' => 'id ASC'
];
$order_by = $sort_options[$sort_by] ?? 'product_name ASC';

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE $where_clause");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Get products
$stmt = $conn->prepare("SELECT id, product_name, main_image, description, codename FROM products WHERE $where_clause ORDER BY $order_by LIMIT ? OFFSET ?");
$final_params = array_merge($params, [$per_page, $offset]);
$final_types = $types . 'ii';
if (!empty($final_params)) $stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

$total_pages = ceil($total_products / $per_page);

$all_categories = [
    'furniture' => 'Furniture',
    'buildingmaterial' => 'Materials',
    'electrical' => 'Electrical',
    'lighting' => 'Lighting',
    'bedfurniture' => 'Bedroom Furniture',
    'aircon' => 'Air Conditioners',
    'doors' => 'Doors',
    'tiles' => 'Tiles',
    'windows' => 'Windows',
    'bathroom' => 'Bathroom Fixtures',
    'kitchen' => 'Kitchen Fixtures',
    'pipes' => 'Pipes',
    'aacblock' => 'AAC BLOCKS'
];

// Get category counts
$category_counts = [];
foreach ($all_categories as $cat_key => $cat_name) {
    $cat_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE codename = ?");
    $cat_stmt->bind_param("s", $cat_key);
    $cat_stmt->execute();
    $category_counts[$cat_key] = $cat_stmt->get_result()->fetch_assoc()['count'];
    $cat_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Shop Products - Noble Home</title>
    <meta name="description" content="Explore our premium collection of furniture, materials, and home décor items.">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .playfair {
            font-family: 'Playfair Display', serif;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-hover {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-8px);
        }

        .filter-panel {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(226, 232, 240, 0.8);
            backdrop-filter: blur(10px);
        }

        .category-chip {
            transition: all 0.3s ease;
            border: 1px solid rgba(226, 232, 240, 0.6);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .category-chip:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border-color: rgba(249, 115, 22, 0.3);
        }

        .category-chip input:checked+.category-content {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
        }

        .mobile-filter {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.7);
            z-index: 50;
            backdrop-filter: blur(8px);
        }

        .mobile-filter.active {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .pagination-btn {
            transition: all 0.3s ease;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .pagination-btn:hover:not(.disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border-color: #f97316;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f1f5f9;
            color: #94a3b8;
        }

        .product-image {
            transition: transform 0.6s ease;
        }

        .card-hover:hover .product-image {
            transform: scale(1.08);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @media (max-width: 1023px) {
            .desktop-filter {
                display: none;
            }
        }

        @media (min-width: 1024px) {
            .mobile-filter-toggle {
                display: none;
            }

            .sticky-filter {
                position: sticky;
                top: 2rem;
            }
        }
        /* Add this CSS to properly hide the sidebar filter on mobile */

/* Desktop Sidebar Filter - Hidden on mobile */
.desktop-sidebar-filter {
    display: none;
}

/* Show desktop sidebar only on large screens */
@media (min-width: 1024px) {
    .desktop-sidebar-filter {
        display: block;
    }
    
    /* Hide mobile filter toggle on desktop */
    .mobile-filter-toggle {
        display: none;
    }
}

/* Mobile specific styles */
@media (max-width: 1023px) {
    /* Ensure sidebar is completely hidden on mobile */
    .desktop-sidebar-filter {
        display: none !important;
    }
    
    /* Make sure mobile filter toggle shows */
    .mobile-filter-toggle {
        display: block;
    }
    
    /* Adjust main content to full width on mobile */
    .main-content-mobile {
        width: 100%;
    }
}

/* Additional mobile layout fixes */
@media (max-width: 768px) {
    .flex.flex-col.lg\\:flex-row {
        flex-direction: column;
    }
    
    .w-80 {
        display: none;
    }
}
    </style>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                        'playfair': ['Playfair Display', 'serif']
                    },
                    colors: {
                        'primary': '#f97316',
                        'primary-dark': '#ea580c'
                    }
                }
            }
        }

        function changePerPage(value) {
            updateUrl({
                per_page: value,
                page: 1
            });
        }

        function changeSort(value) {
            updateUrl({
                sort: value,
                page: 1
            });
        }

        function clearAllFilters() {
            window.location.href = window.location.pathname;
        }

        function removeFilter(type, value) {
            const url = new URL(window.location);
            if (type === 'category') {
                const categories = url.searchParams.getAll('category[]').filter(cat => cat !== value);
                url.searchParams.delete('category[]');
                categories.forEach(cat => url.searchParams.append('category[]', cat));
            } else if (type === 'search') {
                url.searchParams.delete('search');
            }
            updateUrl({
                page: 1
            });
        }

        function updateUrl(params) {
            const url = new URL(window.location);
            Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
            window.location.href = url.toString();
        }

        function jumpToPage(page) {
            updateUrl({
                page: Math.max(1, Math.min(<?= $total_pages ?>, parseInt(page)))
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('mobileFilterToggle');
            const panel = document.getElementById('mobileFilterPanel');
            const close = document.getElementById('closeMobileFilter');

            toggle?.addEventListener('click', () => panel.classList.add('active'));
            close?.addEventListener('click', () => panel.classList.remove('active'));
            panel?.addEventListener('click', (e) => {
                if (e.target === panel) panel.classList.remove('active');
            });
        });
    </script>
</head>

<body class="font-mont">
    <?php include '../navbar/top.php'; ?>

    <!-- Hero Section -->
    <section class="bg-white relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-15">
            <div class="text-center" data-aos="fade-up">
                <h1 class="text-4xl lg:text-6xl font-bold text-orange-400 mb-6">
                    Premium <span class="text-black ">Collections</span>
                </h1>
                <p class="text-xl text-black max-w-3xl mx-auto mb-8 leading-relaxed">
                    Discover exceptional furniture and materials crafted with precision and designed for modern living
                </p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 ">

        <!-- Mobile Filter Toggle -->
        <div class="mobile-filter-toggle mb-8">
            <button id="mobileFilterToggle" class="w-full bg-primary hover:bg-primary-dark text-white px-6 py-4 rounded-xl font-semibold flex items-center justify-center shadow-lg transition-all">
                <i class="fas fa-sliders-h mr-3"></i>Filter & Sort Products<i class="fas fa-chevron-down ml-3"></i>
            </button>
        </div>

        <!-- Mobile Filter Panel -->
        <div id="mobileFilterPanel" class="mobile-filter">
            <div class="bg-white m-4 rounded-2xl max-h-90vh overflow-y-auto shadow-2xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 playfair">Filter Products</h2>
                        <button id="closeMobileFilter" class="text-gray-400 hover:text-gray-600 p-2">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <form method="GET" class="space-y-8">
                        <!-- Categories -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Categories</h3>
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                                    <label class="category-chip flex items-center justify-between p-4 rounded-xl cursor-pointer">
                                        <div class="flex items-center space-x-3 category-content">
                                            <input type="checkbox" name="category[]" value="<?= $cat_key ?>" <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?> class="text-primary border-gray-300 rounded focus:ring-2 focus:ring-primary">
                                            <span class="font-medium"><?= htmlspecialchars($cat_name) ?></span>
                                        </div>
                                        <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $category_counts[$cat_key] ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Search -->
                        <div>
                            <label class="block text-lg font-semibold text-gray-900 mb-4">Search</label>
                            <div class="relative">
                                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="Search products..." class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary">
                                <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>

                        <!-- Sort -->
                        <div>
                            <label class="block text-lg font-semibold text-gray-900 mb-4">Sort By</label>
                            <select name="sort" class="w-full py-4 px-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary bg-white">
                                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            </select>
                        </div>

                        <!-- Apply Buttons -->
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-primary text-white px-6 py-4 rounded-xl hover:bg-primary-dark font-semibold">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                            <button type="button" onclick="clearAllFilters()" class="px-6 py-4 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50">Clear</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
          <!-- Products Section -->
<div class="flex-1 lg:order-1 main-content-mobile">
                <!-- Controls -->
                <div class=" p-6 mb-8 shadow-sm">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-medium text-black">Sort by:</label>
                            <select onchange="changeSort(this.value)" class="py-2.5 px-4 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary bg-white">
                                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            </select>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="allproduct" class="btn-primary">Explore Products</a>
                            <a href="allproductsub" class="btn-primary">View All Products</a>
                        </div>
                    </div>


                </div>

                <!-- Product Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-12">
                    <?php while ($row = $products->fetch_assoc()): ?>
                        <?php
                        $product_id = (int)$row['id'];
                        $variant_stmt = $conn->prepare("SELECT COUNT(*) as total FROM product_variants pv JOIN product_types pt ON pv.type_id = pt.id WHERE pt.product_id = ?");
                        $variant_stmt->bind_param("i", $product_id);
                        $variant_stmt->execute();
                        $variant_count = $variant_stmt->get_result()->fetch_assoc()['total'] ?? 0;
                        $variant_stmt->close();
                        ?>

                        <div class="card-hover group overflow-hidden hover:shadow-xl">
                            <a href="product_view.php?id=<?= $product_id ?>">
                                <!-- Product Image -->
                                <div class="relative aspect-square p-2 overflow-hidden">
                                    <?php if (!empty($row['main_image'])): ?>
                                        <img src="../../<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['product_name']) ?>" class="product-image w-full h-full object-contain" loading="lazy">
                                    <?php else: ?>
                                        <div class="product-image w-full h-full flex items-center justify-center text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Overlay -->
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all flex items-center justify-center">
                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="bg-white text-gray-900 px-6 py-3 rounded-full font-semibold shadow-lg">
                                                <i class="fas fa-eye mr-2"></i>View Details
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Category Badge -->
                                    <div class="absolute top-4 left-4">
                                        <span class="bg-primary bg-opacity-90 text-white px-3 py-1.5 rounded-full text-xs font-semibold uppercase">
                                            <?= htmlspecialchars($row['codename']) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Product Info -->
                                <div class="p-6">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-primary transition-colors line-clamp-2">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 line-clamp-2 mb-4">
                                        <?= htmlspecialchars($row['description'] ?? 'No description available.') ?>
                                    </p>

                                    <div class="flex items-center justify-between mb-4">
                                        <span class="bg-orange-50 text-primary px-3 py-1.5 rounded-lg text-xs font-semibold border border-orange-200">
                                            <i class="fas fa-layer-group mr-1"></i><?= $variant_count ?> Variant<?= $variant_count !== 1 ? 's' : '' ?>
                                        </span>
                                    </div>

                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button class="w-full bg-black text-white py-3 rounded-lg hover:bg-primary-dark transition-colors font-semibold">
                                            <i class="fas fa-shopping-cart mr-2"></i>View Product
                                        </button>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class=" p-8" data-aos="fade-up">
               

                        <!-- Controls -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                            <!-- Previous -->
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>"
                                class="pagination-btn px-6 py-3 text-sm font-semibold rounded-xl bg-white text-gray-700 shadow-sm <?= $page <= 1 ? 'disabled' : '' ?>">
                                <i class="fas fa-chevron-left mr-2"></i>Previous
                            </a>

                            <!-- Pages -->
                            <div class="flex items-center gap-2">
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);

                                for ($i = $start; $i <= $end; $i++) {
                                    $classes = $i == $page ? 'pagination-btn active' : 'pagination-btn bg-white text-gray-700';
                                    echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="' . $classes . ' w-12 h-12 flex items-center justify-center text-sm font-semibold rounded-xl shadow-sm">' . $i . '</a>';
                                }
                                ?>
                            </div>

                            <!-- Next -->
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>"
                                class="pagination-btn px-6 py-3 text-sm font-semibold rounded-xl bg-white text-gray-700 shadow-sm <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                Next<i class="fas fa-chevron-right ml-2"></i>
                            </a>
                        </div>

                        <!-- Jump to page -->
                        <div class="mt-6 text-center">
                            <div class="inline-flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                                <label class="text-sm font-medium text-gray-700">Jump to page:</label>
                                <input type="number" min="1" max="<?= $total_pages ?>" value="<?= $page ?>"
                                    class="w-20 px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-center"
                                    onchange="jumpToPage(this.value)">
                                <button onclick="jumpToPage(document.querySelector('input[type=number]').value)"
                                    class="px-4 py-1.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark">Go</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- No Products -->
                <?php if ($total_products == 0): ?>
                    <div class="text-center py-20">
                        <div class="max-w-md mx-auto">
                            <div class="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-8">
                                <i class="fas fa-search text-gray-400 text-4xl"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-4 playfair">No Products Found</h3>
                            <p class="text-gray-600 mb-8">We couldn't find any products matching your criteria.</p>
                            <a href="?" class="bg-primary text-white px-8 py-4 rounded-xl hover:bg-primary-dark font-semibold shadow-lg">
                                Clear All Filters
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>





            <!-- Sidebar Filter Component -->
            <div class="w-80 bg-white desktop-sidebar-filter">
                <!-- Filter Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-sliders-h mr-2 text-gray-600"></i>
                        Filter Products
                    </h2>
                </div>

                <!-- Filter Content -->
                <div class="p-6">
                    <form method="GET" class="space-y-6">
                        <!-- Search Section -->
                        <div class="border-b border-gray-100 pb-6">
                            <div class="flex items-center justify-between cursor-pointer py-2 hover:bg-gray-50 rounded-md px-2 -mx-2" onclick="toggleSection('search')">
                                <div class="flex items-center">
                                    <i class="fas fa-search mr-2 text-gray-600"></i>
                                    <span class="font-medium text-gray-700">Search Products</span>
                                </div>
                                <i class="fas fa-chevron-down text-gray-400 transition-transform duration-200" id="search-chevron"></i>
                            </div>
                            <div class="mt-4 transition-all duration-300 ease-in-out" id="search-content">
                                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>"
                                    placeholder="Search by name or description..."
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                        </div>

                        <!-- Categories Section -->
                        <div class="border-b border-gray-100 pb-6">
                            <div class="flex items-center justify-between cursor-pointer py-2 hover:bg-gray-50 rounded-md px-2 -mx-2" onclick="toggleSection('categories')">
                                <div class="flex items-center">
                                    <i class="fas fa-th-large mr-2 text-gray-600"></i>
                                    <span class="font-medium text-gray-700">Categories</span>
                                    <span class="ml-2 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                        <?= count($all_categories) ?>
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down text-gray-400 transition-transform duration-200" id="categories-chevron"></i>
                            </div>
                            <div class="mt-4 space-y-2 max-h-96 overflow-y-auto transition-all duration-300 ease-in-out" id="categories-content">
                                <div class="pr-2">
                                    <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                                        <label class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg cursor-pointer border border-transparent hover:border-gray-200 transition-all duration-200 mb-1 <?= ($category_counts[$cat_key] ?? 0) == 0 ? 'opacity-50' : '' ?>">
                                            <div class="flex items-center flex-1 min-w-0">
                                                <input type="checkbox" name="category[]" value="<?= $cat_key ?>"
                                                    <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?>
                                                    <?= ($category_counts[$cat_key] ?? 0) == 0 ? 'disabled' : '' ?>
                                                    class="mr-3 text-blue-600 focus:ring-blue-500 border-gray-300 rounded w-4 h-4">
                                                <span class="text-sm text-gray-700 truncate font-medium"><?= htmlspecialchars($cat_name) ?></span>
                                            </div>
                                            <span class="text-xs <?= ($category_counts[$cat_key] ?? 0) > 0 ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-400' ?> px-3 py-1.5 rounded-full ml-3 flex-shrink-0 font-semibold">
                                                <?= $category_counts[$cat_key] ?? 0 ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-3">
                            <button type="submit" class="w-full bg-black text-white px-4 py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium transition-colors duration-200">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                            <button type="button" onclick="clearAllFilters()" class="w-full bg-gray-100 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 font-medium transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>Clear All Filters
                            </button>
                        </div>
                    </form>

                    <!-- Active Filters Display -->
                    <?php if (!empty($selected_categories) || !empty($search_keyword)): ?>
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                                <i class="fas fa-tags mr-2 text-gray-600"></i>Active Filters
                            </h4>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <?php foreach ($selected_categories as $cat): ?>
                                    <span class="inline-flex items-center bg-blue-100 text-blue-800 text-xs px-3 py-1.5 rounded-full font-medium">
                                        <i class="fas fa-tag mr-1 text-xs"></i>
                                        <?= htmlspecialchars($all_categories[$cat] ?? $cat) ?>
                                        <button type="button" onclick="removeFilter('category', '<?= $cat ?>')" class="ml-2 text-blue-600 hover:text-blue-800 transition-colors duration-200">
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                    </span>
                                <?php endforeach; ?>

                                <?php if (!empty($search_keyword)): ?>
                                    <span class="inline-flex items-center bg-green-100 text-green-800 text-xs px-3 py-1.5 rounded-full font-medium">
                                        <i class="fas fa-search mr-1 text-xs"></i>
                                        "<?= htmlspecialchars($search_keyword) ?>"
                                        <button type="button" onclick="removeFilter('search', '')" class="ml-2 text-green-600 hover:text-green-800 transition-colors duration-200">
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <button onclick="clearAllFilters()" class="text-xs text-gray-500 hover:text-red-600 font-medium transition-colors duration-200">
                                <i class="fas fa-trash-alt mr-1"></i>Clear All Filters
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <?php include '../navbar/footer.php'; ?>


    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        function toggleSection(sectionId) {
            const content = document.getElementById(sectionId + '-content');
            const chevron = document.getElementById(sectionId + '-chevron');

            content.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

        function removeFilter(type, value) {
            if (type === 'category') {
                const checkbox = document.querySelector(`input[name="category[]"][value="${value}"]`);
                if (checkbox) checkbox.checked = false;
            } else if (type === 'search') {
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) searchInput.value = '';
            }

            document.querySelector('form').submit();
        }

        function clearAllFilters() {
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);

            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) searchInput.value = '';

            document.querySelector('form').submit();
        }
    </script>

    <style>
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            font-weight: 600;
            padding: 1rem 2rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(249, 115, 22, 0.4);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
        }
    </style>
</body>

</html>