<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token 
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture']     = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Session check 
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Get category_id from URL parameter
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Get category information
$category_name = "All Categories";
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $category = $result->fetch_assoc();
        $category_name = $category['name'];
    }
    $stmt->close();
}

// Get subcategories based on category - LIMIT TO 6
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT *, image_path FROM product_subcategories WHERE category_id = ? ORDER BY subcategory_name LIMIT 6");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT *, image_path FROM product_subcategories ORDER BY subcategory_name LIMIT 6";
    $result = $conn->query($sql);
}

$subcategories = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $subcategories[] = $row;
    }
}

// Get all categories for the breadcrumb/filter
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
$all_categories = [];
if ($categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $all_categories[] = $row;
    }
}

// Select categories to display in swiper
$categories = [];
if ($category_id > 0) {
    $other_categories = array_filter($all_categories, function ($cat) use ($category_id) {
        return $cat['id'] != $category_id;
    });

    shuffle($other_categories);
    $categories = array_slice($other_categories, 0, 3);

    $current_category = array_filter($all_categories, function ($cat) use ($category_id) {
        return $cat['id'] == $category_id;
    });
    if (!empty($current_category)) {
        array_unshift($categories, array_values($current_category)[0]);
    }
} else {
    $categories = array_slice($all_categories, 0, 4);
}

$activeIndex = 0;
foreach ($categories as $i => $cat) {
    if ($category_id == $cat['id']) {
        $activeIndex = $i;
        break;
    }
}

$subcategory_id = isset($_GET['subcategory_id']) ? intval($_GET['subcategory_id']) : 0;

// CORRECT QUERY: Using actual database structure
$query = "
    SELECT 
        p.id as product_id,
        p.product_name,
        p.main_image,
        p.description,
        p.unit,
        p.specification,
        pv.id as variant_id,
        pv.type_id,
        pv.product_id as pv_product_id,
        pv.color,
        pv.size,
        pv.price,
        pv.discount,
        pv.namevariant,
        pv.category_id,
        pv.subcategory_id,
        pv.category_name,
        pv.subcategory_name,
        pt.product_id as pt_product_id,
        pc.product_id as pc_product_id,
        pc.color_name
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_types pt ON pv.product_id = pt.product_id
    LEFT JOIN product_colors pc ON pv.product_id = pc.product_id
    WHERE 1=1
";

$params = [];
$types  = "";

// Filter by category if selected
if ($category_id > 0) {
    $query .= " AND pv.category_id = ? ";
    $types .= "i";
    $params[] = $category_id;
}

// Filter by subcategory if selected
if ($subcategory_id > 0) {
    if ($category_id > 0) {
        $query = str_replace("AND pv.category_id = ? ", "", $query);
        $types = str_replace("i", "", $types);
        array_pop($params);
    }
    $query .= " AND pv.subcategory_id = ? ";
    $types .= "i";
    $params[] = $subcategory_id;
}

$query .= " ORDER BY p.id DESC LIMIT 6";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Group products with their variants
$products = [];
while ($row = $result->fetch_assoc()) {
    $product_id = $row['product_id'];

    if (!isset($products[$product_id])) {
        $products[$product_id] = [
            'id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'main_image' => $row['main_image'],
            'description' => $row['description'],
            'unit' => $row['unit'],
            'specification' => $row['specification'],
            'category_name' => $row['category_name'],
            'category_id' => $row['category_id'],
            'subcategory_name' => $row['subcategory_name'],
            'subcategory_id' => $row['subcategory_id'],
            'variants' => [],
            'colors' => []
        ];
    }

    // Add variant if it exists
    if ($row['variant_id']) {
        $products[$product_id]['variants'][] = [
            'id' => $row['variant_id'],
            'type_id' => $row['type_id'],
            'namevariant' => $row['namevariant'],
            'color' => $row['color'],
            'size' => $row['size'],
            'price' => $row['price'],
            'discount' => $row['discount']
        ];
    }

    // Add color if it exists
    if ($row['color_name']) {
        $products[$product_id]['colors'][] = $row['color_name'];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Product Subcategories - <?php echo htmlspecialchars($category_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#64748b',
                        accent: '#f59e0b',
                        'brand-blue': '#0f172a',
                        'brand-gray': '#f8fafc'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                        'serif': ['Playfair Display', 'serif']
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-brand-gray min-h-screen font-mont antialiased">
    <?php include '../navbar/top.php'; ?>

    <!-- Hero Section -->
    <div class="bg-black/90">
        <div class="container mx-auto px-6 py-10 max-w-7xl">
            <div class="text-center text-white">
                <h1 class="text-4xl md:text-5xl font-serif font-bold mb-4 uppercase">
                    <?php echo $category_id > 0 ? htmlspecialchars($category_name) : 'Product Categories'; ?>
                </h1>
                <p class="text-xl text-gray-300 mb-8 max-w-2xl mx-auto">
                    Discover our carefully curated collection of premium products
                </p>

                <!-- Breadcrumb -->
                <nav class="flex justify-center mb-8" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-2 text-sm">
                        <li class="inline-flex items-center">
                            <a href="index.php" class="inline-flex items-center text-gray-300 hover:text-white transition-colors duration-200">
                                <i class="fas fa-home mr-2"></i>
                                Home
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                <span class="text-gray-200"><?php echo htmlspecialchars($category_name); ?></span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container mx-auto px-6 py-12 max-w-7xl">

        <!-- Category Filter Section -->
        <div class="mb-12">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900">Browse Categories</h2>
                        <div class="flex items-center space-x-3">
                            <!-- View Toggle Buttons -->
                            <div class="bg-gray-100 rounded-lg p-1 flex">
                                <button onclick="setGridView()" id="gridViewBtn" class="view-toggle active">
                                    <i class="fas fa-th-large"></i>
                                </button>
                                <button onclick="setListView()" id="listViewBtn" class="view-toggle">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>

                            <!-- Filter Button -->
                            <button onclick="showAllCategories()" class="inline-flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-orange-400 transition-colors duration-200">
                                <i class="fas fa-filter mr-2"></i>
                                All Categories
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Category Filter -->
                <div class="p-6">
                    <div class="category-filter-container relative">
                        <div class="swiper categorySwiper">
                            <div class="swiper-wrapper py-2">
                                <!-- All Categories Option -->
                                <div class="swiper-slide !w-auto flex-shrink-0">
                                    <a href="?category_id=0" class="category-pill <?php echo $category_id == 0 ? 'active' : ''; ?>">
                                        <i class="fas fa-th-list mr-2"></i>
                                        All Categories
                                    </a>
                                </div>

                                <!-- Category slides -->
                                <?php foreach ($categories as $cat): ?>
                                    <div class="swiper-slide !w-auto flex-shrink-0">
                                        <a href="?category_id=<?php echo $cat['id']; ?>" class="category-pill <?php echo $category_id == $cat['id'] ? 'active' : ''; ?>">
                                            <i class="fas fa-tag mr-2"></i>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Navigation buttons -->
                        <div class="category-nav-prev">
                            <i class="fas fa-chevron-left"></i>
                        </div>
                        <div class="category-nav-next">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <?php if (!empty($subcategories)): ?>
            <div class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center space-x-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900"><?php echo count($subcategories); ?></div>
                                <div class="text-sm text-gray-500">Subcategories</div>
                            </div>
                            <div class="w-px h-8 bg-gray-200"></div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-black"><?php echo $category_id > 0 ? htmlspecialchars($category_name) : 'All'; ?></div>
                                <div class="text-sm text-gray-500">Category</div>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search subcategories..."
                                class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all duration-200">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Subcategories Grid -->
        <div id="subcategoriesGrid" class="grid-view grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php foreach ($subcategories as $index => $subcategory): ?>
                <div class="subcategory-card group overflow-hidden hover:shadow-xl transition-all duration-300" data-name="<?php echo strtolower($subcategory['subcategory_name']); ?>">
                    <!-- Image Section -->
                    <div class="relative overflow-hidden">
                        <?php if (!empty($subcategory['image_path'])): ?>
                            <div class="aspect-square">
                                <img src="../../uploads/<?php echo htmlspecialchars($subcategory['subcategory_slug']); ?>/<?php echo htmlspecialchars($subcategory['image_path']); ?>"
                                    alt="<?php echo htmlspecialchars($subcategory['subcategory_name']); ?>"
                                    class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-110"
                                    onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200\'><i class=\'fas fa-image text-4xl text-gray-400\'></i></div>'">
                            </div>
                        <?php else: ?>
                            <div class="aspect-square flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                                <i class="fas fa-image text-4xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Hover overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300">
                            <div class="absolute bottom-4 left-4 right-4">
                                <div class="bg-white/95 backdrop-blur-sm px-4 py-2 rounded-lg transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                                    <span class="text-sm font-semibold text-gray-900">View Products</span>
                                    <i class="fas fa-arrow-right ml-2 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-orange-400 transition-colors duration-200">
                            <a href="allproductsub_variant.php?subcategory_id=<?php echo $subcategory['id']; ?>" class="stretched-link">
                                <?php echo strtoupper(htmlspecialchars($subcategory['subcategory_name'])); ?>
                            </a>
                        </h3>

                        <!-- Product Count (if available) -->
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fas fa-box mr-1"></i>
                            <span>Explore products</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Empty State -->
        <?php if (empty($subcategories)): ?>
            <div class="text-center py-24">
                <div class="w-24 h-24 mx-auto mb-6 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                    <i class="fas fa-folder-open text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-2xl font-semibold text-gray-900 mb-3">No Subcategories Found</h3>
                <p class="text-gray-500 mb-8 max-w-md mx-auto text-lg">
                    <?php echo $category_id > 0 ? 'There are no subcategories in ' . htmlspecialchars($category_name) . ' at the moment.' : 'There are no subcategories available at the moment.'; ?>
                </p>
                <?php if ($category_id > 0): ?>
                    <a href="?category_id=0" class="inline-flex items-center px-8 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-th-large mr-2"></i>
                        View All Categories
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Featured Products Section -->
        <?php if (!empty($products)): ?>
            <div class="mt-16">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-serif font-bold text-gray-900 mb-4">Featured Products</h2>
                    <p class="text-gray-600 text-lg max-w-2xl mx-auto">
                        Discover our handpicked selection of premium products
                    </p>
                </div>

                <div class="">
                    <div class="swiper productSwiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($products as $product): ?>
                                <div class="swiper-slide">
                                    <div class="product-card bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300">
                                        <div class="aspect-square overflow-hidden">
                                            <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                                                class="w-full h-full object-contain hover:scale-105 transition-transform duration-300"
                                                alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                        </div>

                                        <div class="p-4">
                                            <h3 class="font-semibold text-gray-900 mb-2 text-sm uppercase tracking-wide">
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </h3>

                                            <p class="text-gray-600 mb-3 text-xs line-clamp-2">
                                                <?php echo htmlspecialchars(substr($product['description'], 0, 80)) . '...'; ?>
                                            </p>

                                            <?php if (!empty($product['variants'])): ?>
                                                <?php $firstVariant = $product['variants'][0]; ?>
                                                <div class="mb-3">
                                                    <p class="text-accent font-bold text-lg">
                                                        ₱<?php echo number_format($firstVariant['price'], 2); ?>
                                                        <?php if ($firstVariant['discount'] > 0): ?>
                                                            <span class="text-xs text-green-600 ml-1">
                                                                (-<?php echo $firstVariant['discount']; ?>%)
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>

                                                    <div class="text-xs text-gray-500">
                                                        <?php if ($firstVariant['color']): ?>
                                                            <span class="mr-2"><?php echo htmlspecialchars($firstVariant['color']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($firstVariant['size']): ?>
                                                            <span><?php echo htmlspecialchars($firstVariant['size']); ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (count($product['variants']) > 1): ?>
                                                        <p class="text-xs text-primary mt-1">
                                                            +<?php echo count($product['variants']) - 1; ?> more variants
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <form action="product_view" method="GET" class="mt-4">
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                                <button type="submit" class="w-full bg-black text-white py-2 px-4 rounded-lg hover:bg-orange-400 transition-colors duration-200 font-medium">
                                                    <i class="fa-solid fa-bag-shopping"></i>
                                                    View Product
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="swiper-pagination mt-8"></div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for All Categories -->
    <div id="categoriesModal" class="modal-overlay">
        <div class="modal-content">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-serif font-bold text-gray-900">All Categories</h2>
                <button onclick="hideAllCategories()" class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4" id="allCategoriesGrid">
                <?php foreach ($all_categories as $cat): ?>
                    <a href="?category_id=<?php echo $cat['id']; ?>"
                        class="flex items-center p-4 rounded-xl border border-gray-200 hover:border-primary hover:bg-blue-50 transition-all duration-200 group <?php echo $category_id == $cat['id'] ? 'bg-blue-100 border-primary' : ''; ?>">
                        <i class="fas fa-tag text-gray-400 group-hover:text-primary mr-3"></i>
                        <span class="font-medium text-gray-700 group-hover:text-primary"><?php echo htmlspecialchars($cat['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button id="backToTop" class="fixed bottom-6 right-6 bg-primary hover:bg-blue-700 text-white p-3 rounded-full shadow-lg transition-all duration-300 opacity-0 invisible transform hover:scale-110 z-50">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Enhanced Styles -->
    <style>
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Line clamp utility */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Stretched link for card clickability */
        .stretched-link::after {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 1;
            content: "";
        }

        /* Enhanced Category Pills */
        .category-pill {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            margin: 0 8px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            background: #ffffff;
            border: 2px solid #e5e7eb;
            color: #6b7280;
            white-space: nowrap;
            min-width: fit-content;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .category-pill:hover {
            transform: translateY(-2px);
            border-color: #f59e0b;
            color: #f59e0b;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.15);
        }

        .category-pill.active {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        /* Enhanced Container */
        .category-filter-container {
            position: relative;
        }

        /* Enhanced Swiper */
        .categorySwiper {
            overflow: visible;
            padding: 0 60px;
        }

        .categorySwiper .swiper-wrapper {
            align-items: center;
        }

        /* Enhanced Navigation Buttons */
        .category-nav-prev,
        .category-nav-next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
            color: #6b7280;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .category-nav-prev {
            left: 8px;
        }

        .category-nav-next {
            right: 8px;
        }

        .category-nav-prev:hover,
        .category-nav-next:hover {
            background: #1e40af;
            color: white;
            border-color: #1e40af;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 4px 16px rgba(30, 64, 175, 0.3);
        }

        /* View Toggle Buttons */
        .view-toggle {
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
            color: #6b7280;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .view-toggle:hover {
            background: #e5e7eb;
            color: #1e40af;
        }

        .view-toggle.active {
            background: #000000ff;
            color: white;
        }

        /* Subcategory Cards */
        .subcategory-card {
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .subcategory-card:hover {
            transform: translateY(-8px);
        }

        /* List View Styles */
        .list-view {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .list-view .subcategory-card {
            display: flex;
            align-items: center;
            padding: 1.5rem;
        }

        .list-view .subcategory-card .aspect-square {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            margin-right: 1.5rem;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 32px;
            max-width: 700px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s ease;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }

        /* Product Swiper Customization */
        .productSwiper {
            padding: 20px 0;
        }

        .productSwiper .swiper-button-next,
        .productSwiper .swiper-button-prev {
            background: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
            color: #1e40af;
        }

        .productSwiper .swiper-button-next::after,
        .productSwiper .swiper-button-prev::after {
            font-size: 18px;
            font-weight: bold;
        }

        .productSwiper .swiper-pagination-bullet {
            background: #1e40af;
            opacity: 0.3;
        }

        .productSwiper .swiper-pagination-bullet-active {
            opacity: 1;
        }

        /* Product Cards */
        .product-card {
            position: relative;
        }

        /* Back to Top Button */
        #backToTop.show {
            opacity: 1;
            visibility: visible;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .categorySwiper {
                padding: 0 20px;
            }

            .category-nav-prev,
            .category-nav-next {
                width: 36px;
                height: 36px;
            }

            .category-nav-prev {
                left: 4px;
            }

            .category-nav-next {
                right: 4px;
            }

            .category-pill {
                padding: 10px 20px;
                font-size: 13px;
                margin: 0 4px;
            }

            .modal-content {
                padding: 24px;
            }

            .subcategory-card:hover {
                transform: translateY(-4px);
            }
        }

        /* Loading Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .subcategory-card {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .subcategory-card:nth-child(even) {
            animation-delay: 0.1s;
        }

        .subcategory-card:nth-child(3n) {
            animation-delay: 0.2s;
        }
    </style>


    <footer class="bg-black pattern-bg text-white py-16 mt-12 relative overflow-hidden">
        <!-- Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-500"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <!-- Main Footer Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

                <!-- Enhanced Branding Section -->
                <div class="lg:col-span-2">
                    <div class="flex items-center space-x-4 mb-6">
                        <!-- Logo with glow and pulse -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-2xl glow-effect floating overflow-hidden">
                                <img src="../img/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
                            </div>
                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-blue-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Text Branding -->
                        <div>
                            <h2 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">Noble Home</h2>

                        </div>
                    </div>


                    <p class="text-gray-300 leading-relaxed mb-6 max-w-md">
                        Crafting exceptional living spaces with unmatched quality and attention to detail. Your dream home awaits with our expert construction and design services.
                    </p>

                    <!-- Contact Info -->
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                    <path d="m18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">noblehomeconst.ph@gmail.com</span>
                        </div>
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">0968 591 6536</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Quick Links
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <nav class="space-y-3">
                        <a href="index" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Home</a>
                        <a href="about" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">About Us</a>
                        <a href="contact" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Contact</a>
                    </nav>
                </div>

                <!-- Services -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Our Services
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <ul class="space-y-3 text-gray-300">
                        <li class="hover:text-orange-300 transition-colors cursor-pointer">Appointment</li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                    </ul>
                </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent mb-8"></div>

            <!-- Bottom Section -->
            <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
                <!-- Copyright -->
                <div class="text-center lg:text-left">
                    <p class="text-gray-400 text-sm">
                        © 2025 Noble Home Construction. All rights reserved.
                    </p>
                    <p class="text-gray-500 text-xs mt-1">
                        Licensed & Insured | PCAB License No. 12345
                    </p>
                </div>

                <!-- Enhanced Social Media -->
                <div class="flex items-center space-x-4">
                    <span class="text-gray-400 text-sm mr-2">Follow us:</span>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Facebook">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M22 12a10 10 0 10-11.63 9.88v-6.99H8.4v-2.89h1.97V9.91c0-1.95 1.16-3.03 2.93-3.03.85 0 1.74.15 1.74.15v1.91h-.98c-.97 0-1.27.6-1.27 1.21v1.45h2.16l-.35 2.89h-1.81v6.99A10 10 0 0022 12z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Instagram">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.5.5.6.2 1 .6 1.5 1.1.4.4.8.9 1.1 1.5.2.5.4 1.3.5 2.5.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.5-.2.6-.6 1-1.1 1.5-.4.4-.9.8-1.5 1.1-.5.2-1.3.4-2.5.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.5-.5-.6-.2-1-.6-1.5-1.1-.4-.4-.8-.9-1.1-1.5-.2-.5-.4-1.3-.5-2.5C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.5.2-.6.6-1 1.1-1.5.4-.4.9-.8 1.5-1.1.5-.2 1.3-.4 2.5-.5C8.4 2.2 8.8 2.2 12 2.2zm0 2.3c-3.1 0-3.5 0-4.7.1-.9.1-1.4.2-1.8.4-.5.2-.8.4-1.2.8s-.6.7-.8 1.2c-.2.4-.3.9-.4 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1.9.2 1.4.4 1.8.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2.9.3 1.8.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c.9-.1 1.4-.2 1.8-.4.5-.2.8-.4 1.2-.8s.6-.7.8-1.2c.2-.4.3-.9.4-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-.9-.2-1.4-.4-1.8-.2-.5-.4-.8-.8-1.2s-.7-.6-1.2-.8c-.4-.2-.9-.3-1.8-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.7a5.8 5.8 0 100 11.6 5.8 5.8 0 000-11.6zm0 9.5a3.7 3.7 0 110-7.4 3.7 3.7 0 010 7.4zm5.9-9.8a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="LinkedIn">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                        </svg>
                    </a>
                </div>

                <!-- Back to Top Button -->
                <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
                    class="w-12 h-12 bg-orange-500 hover:bg-orange-600 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Background Pattern -->
        <div class="absolute bottom-0 right-0 opacity-5">
            <svg width="200" height="200" viewBox="0 0 200 200" fill="none">
                <path d="M50 50h100v100H50z" stroke="currentColor" stroke-width="2" />
                <path d="M70 70h60v60H70z" stroke="currentColor" stroke-width="1" />
                <path d="M90 90h20v20H90z" stroke="currentColor" stroke-width="1" />
            </svg>
        </div>
    </footer>


    <!-- Enhanced JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Initialize Swiper for categories
        const categorySwiper = new Swiper('.categorySwiper', {
            slidesPerView: 'auto',
            spaceBetween: 0,
            freeMode: true,
            navigation: {
                nextEl: '.category-nav-next',
                prevEl: '.category-nav-prev',
            },
            breakpoints: {
                640: {
                    slidesPerView: 'auto',
                },
                768: {
                    slidesPerView: 'auto',
                },
                1024: {
                    slidesPerView: 'auto',
                }
            }
        });

        // Initialize Swiper for products
        const productSwiper = new Swiper('.productSwiper', {
            slidesPerView: 1,
            spaceBetween: 20,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                640: {
                    slidesPerView: 2,
                    spaceBetween: 20,
                },
                768: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
                1024: {
                    slidesPerView: 4,
                    spaceBetween: 30,
                }
            }
        });

        // Modal Functions
        function showAllCategories() {
            document.getElementById('categoriesModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function hideAllCategories() {
            document.getElementById('categoriesModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // View Toggle Functions
        function setGridView() {
            const grid = document.getElementById('subcategoriesGrid');
            const gridBtn = document.getElementById('gridViewBtn');
            const listBtn = document.getElementById('listViewBtn');

            grid.className = 'grid-view grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8';
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
        }

        function setListView() {
            const grid = document.getElementById('subcategoriesGrid');
            const gridBtn = document.getElementById('gridViewBtn');
            const listBtn = document.getElementById('listViewBtn');

            grid.className = 'list-view flex flex-col gap-4';
            listBtn.classList.add('active');
            gridBtn.classList.remove('active');
        }

        // Search Functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.subcategory-card');

            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                if (name && name.includes(searchTerm)) {
                    card.style.display = 'block';
                    card.style.animation = 'fadeInUp 0.3s ease-out';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Back to Top Button
        window.addEventListener('scroll', function() {
            const backToTop = document.getElementById('backToTop');
            if (window.pageYOffset > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        document.getElementById('backToTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Close modal when clicking outside
        document.getElementById('categoriesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideAllCategories();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideAllCategories();
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Initialize tooltips and animations on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to cards
            const cards = document.querySelectorAll('.subcategory-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });

            // Initialize any other components that need DOM ready
            console.log('Enhanced subcategories page loaded successfully');
        });

        // Image lazy loading optimization
        document.addEventListener('DOMContentLoaded', function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        });

        // Performance optimization for scroll events
        let ticking = false;

        function updateScrollElements() {
            // Back to top button logic here
            const backToTop = document.getElementById('backToTop');
            if (window.pageYOffset > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(updateScrollElements);
                ticking = true;
            }
        });
    </script>
</body>

</html>